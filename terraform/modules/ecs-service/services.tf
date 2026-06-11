# ---------------------------------------------------------------------------
# ECS Services (terraform-aws-modules/ecs/aws の service サブモジュールを薄くラップ)
# ---------------------------------------------------------------------------
# 自前で書くと嵌まりやすい Task/Exec IAM ロール・CloudWatch Log Group・
# Auto Scaling(Application Auto Scaling)の組み立てをモジュールに任せ、
# このファイルでは「何をどう動かすか」(コンテナ定義・Service Connect・LB 紐付け)
# だけを記述する(§6.2)。
#
# 共通方針:
#   - Fargate / Graviton(ARM64)。同性能で約20%安(ADR-INFRA)
#   - public subnet + Public IP(NAT 不使用)
#   - SG はモジュールに作らせず network モジュールの分離 SG を使う
#   - ログは awslogs(stdout/stderr)、保持 7 日(§11.1)

locals {
  service_connect_api_name = "api" # web からは http://api:80 で到達(§7.5)
}

# ----- web: Next.js (SSR/ISR) -----

module "web_service" {
  source  = "terraform-aws-modules/ecs/aws//modules/service"
  version = "~> 6.0"

  name        = "${local.name}-web"
  cluster_arn = aws_ecs_cluster.this.arn

  cpu    = var.web_cpu
  memory = var.web_memory

  runtime_platform = {
    operating_system_family = "LINUX"
    cpu_architecture        = "ARM64"
  }

  container_definitions = {
    web = {
      image     = var.web_image
      essential = true

      portMappings = [
        {
          name          = "web"
          containerPort = 3000
          protocol      = "tcp"
        }
      ]

      environment = [
        # SSR/ISR のサーバーサイド fetch は Service Connect の論理名で East-West
        { name = "INTERNAL_API_URL", value = "http://${local.service_connect_api_name}:80" },
        { name = "HOSTNAME", value = "0.0.0.0" },
        { name = "PORT", value = "3000" },
      ]

      secrets = [
        # ISR 共有キャッシュ(custom cacheHandler)の接続先(ADR-INFRA 決定8)
        { name = "REDIS_URL", valueFrom = var.redis_url_secret_arn },
      ]

      readonlyRootFilesystem = false # Next.js はキャッシュ等の書き込みがある

      cloudwatch_log_group_retention_in_days = var.log_retention_in_days
    }
  }

  service_connect_configuration = {
    namespace = aws_service_discovery_http_namespace.this.arn
    # service ブロックなし = クライアント専用(api を呼ぶだけで公開はしない)
  }

  load_balancer = {
    web = {
      target_group_arn = aws_lb_target_group.web.arn
      container_name   = "web"
      container_port   = 3000
    }
  }

  subnet_ids            = var.public_subnet_ids
  assign_public_ip      = true
  create_security_group = false
  security_group_ids    = [var.ecs_web_security_group_id]

  enable_autoscaling       = true
  autoscaling_min_capacity = var.autoscaling_min_capacity
  autoscaling_max_capacity = var.autoscaling_max_capacity

  enable_execute_command = true

  task_exec_secret_arns = [var.redis_url_secret_arn]

  depends_on = [aws_lb_listener.https]

  tags = { Name = "${local.name}-web" }
}

# ----- api: Laravel (nginx + php-fpm sidecar 構成) -----

module "api_service" {
  source  = "terraform-aws-modules/ecs/aws//modules/service"
  version = "~> 6.0"

  name        = "${local.name}-api"
  cluster_arn = aws_ecs_cluster.this.arn

  cpu    = var.api_cpu
  memory = var.api_memory

  runtime_platform = {
    operating_system_family = "LINUX"
    cpu_architecture        = "ARM64"
  }

  container_definitions = {
    nginx = {
      image     = var.api_nginx_image
      essential = true

      portMappings = [
        {
          name          = local.service_connect_api_name
          containerPort = 80
          protocol      = "tcp"
        }
      ]

      environment = [
        # awsvpc ではコンテナ名の DNS 解決がないため、upstream はテンプレートで
        # 切り替える(ローカル compose: app / Fargate: 127.0.0.1)。§7.3 の補正。
        { name = "PHP_FPM_HOST", value = "127.0.0.1" },
      ]

      dependsOn = [
        { containerName = "app", condition = "START" },
      ]

      readonlyRootFilesystem = false

      cloudwatch_log_group_retention_in_days = var.log_retention_in_days
    }

    app = {
      image     = var.api_app_image
      essential = true

      portMappings = [
        {
          name          = "php-fpm"
          containerPort = 9000
          protocol      = "tcp"
        }
      ]

      environment = [
        { name = "APP_ENV", value = "production" },
        { name = "APP_DEBUG", value = "false" },
        { name = "APP_URL", value = "https://${var.domain_name}" },
        { name = "LOG_CHANNEL", value = "stderr" },
        { name = "DB_CONNECTION", value = "mysql" },
        { name = "DB_HOST", value = var.db_host },
        { name = "DB_PORT", value = "3306" },
        { name = "DB_DATABASE", value = var.db_name },
        { name = "DB_USERNAME", value = var.db_username },
        { name = "SESSION_DRIVER", value = "redis" },
        { name = "CACHE_STORE", value = "redis" },
        { name = "QUEUE_CONNECTION", value = "redis" },
      ]

      secrets = [
        { name = "APP_KEY", valueFrom = var.app_key_secret_arn },
        { name = "DB_PASSWORD", valueFrom = var.db_password_secret_arn },
        { name = "REDIS_URL", valueFrom = var.redis_url_secret_arn },
      ]

      readonlyRootFilesystem = false # storage/ への書き込みがある

      cloudwatch_log_group_retention_in_days = var.log_retention_in_days
    }
  }

  service_connect_configuration = {
    namespace = aws_service_discovery_http_namespace.this.arn
    service = [
      {
        port_name = local.service_connect_api_name
        client_alias = {
          dns_name = local.service_connect_api_name
          port     = 80
        }
      }
    ]
  }

  load_balancer = {
    api = {
      target_group_arn = aws_lb_target_group.api.arn
      container_name   = "nginx"
      container_port   = 80
    }
  }

  subnet_ids            = var.public_subnet_ids
  assign_public_ip      = true
  create_security_group = false
  security_group_ids    = [var.ecs_api_security_group_id]

  enable_autoscaling       = true
  autoscaling_min_capacity = var.autoscaling_min_capacity
  autoscaling_max_capacity = var.autoscaling_max_capacity

  enable_execute_command = true

  task_exec_secret_arns = [
    var.app_key_secret_arn,
    var.db_password_secret_arn,
    var.redis_url_secret_arn,
  ]

  depends_on = [aws_lb_listener.https]

  tags = { Name = "${local.name}-api" }
}
