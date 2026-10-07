# terraform-aws-modules/ecs の service サブモジュールに Task/Exec IAM ロール・
# Log Group・Auto Scaling を任せ、ここではコンテナ定義と接続だけを書く。

locals {
  service_connect_api_name = "api" # web からは http://api:8080 で到達
}

# ----- web: Next.js (SSR/ISR) -----

module "web_service" {
  source  = "terraform-aws-modules/ecs/aws//modules/service"
  version = "~> 6.0"

  name        = "${local.name}-web"
  cluster_arn = aws_ecs_cluster.this.arn

  # CI がイメージを差し替えたリビジョンをサービスに指定するため、
  # terraform apply でサービスが Terraform のリビジョンに戻らないようにする
  ignore_task_definition_changes = true

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
        { name = "INTERNAL_API_URL", value = "http://${local.service_connect_api_name}:8080" },
        { name = "HOSTNAME", value = "0.0.0.0" },
        { name = "PORT", value = "3000" },
        { name = "REDIS_URL", value = var.redis_url },
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

  depends_on = [aws_lb_listener.https]

  tags = { Name = "${local.name}-web" }
}

# ----- api: Laravel (nginx + php-fpm sidecar 構成) -----

module "api_service" {
  source  = "terraform-aws-modules/ecs/aws//modules/service"
  version = "~> 6.0"

  name        = "${local.name}-api"
  cluster_arn = aws_ecs_cluster.this.arn

  # CI がイメージを差し替えたリビジョンをサービスに指定するため、
  # terraform apply でサービスが Terraform のリビジョンに戻らないようにする
  ignore_task_definition_changes = true

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

      # 80 は ALB 経由(ブラウザ)、8080 は Service Connect 経由(Next.js の SSR)。
      # nginx は 8080 で受けたときだけ SSR が付けたユーザー IP を信頼する
      portMappings = [
        {
          name          = "http"
          containerPort = 80
          protocol      = "tcp"
        },
        {
          name          = local.service_connect_api_name
          containerPort = 8080
          protocol      = "tcp"
        }
      ]

      environment = [
        # awsvpc ではコンテナ名で名前解決できないため、同一タスク内の php-fpm を
        # 127.0.0.1 で指す(ローカル compose では app)
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
        { name = "REDIS_URL", value = var.redis_url },
        { name = "SESSION_DRIVER", value = "redis" },
        { name = "CACHE_STORE", value = "redis" },
        { name = "QUEUE_CONNECTION", value = "redis" },
      ]

      secrets = [
        { name = "APP_KEY", valueFrom = var.app_key_secret_arn },
        { name = "DB_PASSWORD", valueFrom = var.db_password_secret_arn },
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
          port     = 8080
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
  ]

  depends_on = [aws_lb_listener.https, aws_lb_listener_rule.api]

  tags = { Name = "${local.name}-api" }
}
