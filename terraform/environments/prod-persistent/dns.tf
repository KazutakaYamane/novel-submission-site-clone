# 親ドメイン(kyyk517.com)は別 AWS アカウントにあるため、サブドメインの zone を
# ここに作って NS 委譲を受ける。apply 後に output subdomain_name_servers の 4 値を
# 親ゾーンへ NS レコードとして登録する(1 回だけ)。
# zone を作り直すと NS が変わり再登録が必要になるので、prod ではなくこの root に置く。

resource "aws_route53_zone" "this" {
  name    = var.domain_name
  comment = "Delegated subdomain zone for ${var.project} (parent: separate AWS account)"
}
