# ---------------------------------------------------------------------------
# Route 53: サブドメイン委譲用 hosted zone
# ---------------------------------------------------------------------------
# 親ドメイン(kyyk517.com)は別 AWS アカウントの Route 53 で管理されているため、
# このアカウントにサブドメイン専用の hosted zone を作成し、NS 委譲を受ける。
#
# 手動作業(apply 後に 1 回だけ):
#   `terraform output subdomain_name_servers` で出力される NS レコード 4 つを、
#   親アカウントの kyyk517.com ゾーンに
#   「novel-portfolio.kyyk517.com NS <4値>」として登録する。
#   委譲が効くまで ACM の DNS 検証(= prod 側の apply)は完了しない。
#
# zone を destroy すると NS 4 値が変わり、親アカウントへの再登録 + 伝搬待ちが
# 発生するため、prod 本体の destroy/recreate サイクルから切り離してこの root に置く。
#
# ACM 検証 CNAME / CloudFront への Alias / ALB オリジン用レコードは
# prod 側(ecs-service / cloudfront モジュール)がこのゾーンに作成する。

resource "aws_route53_zone" "this" {
  name    = var.domain_name
  comment = "Delegated subdomain zone for ${var.project} (parent: separate AWS account)"
}
