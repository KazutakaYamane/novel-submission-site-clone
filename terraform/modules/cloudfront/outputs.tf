output "distribution_id" {
  description = "CloudFront ディストリビューション ID。invalidation に使う。"
  value       = aws_cloudfront_distribution.this.id
}

output "distribution_domain_name" {
  description = "CloudFront のドメイン名(xxxx.cloudfront.net)。"
  value       = aws_cloudfront_distribution.this.domain_name
}

output "static_assets_bucket" {
  description = "Next.js 静的アセット用 S3 バケット名。`aws s3 sync` の宛先。"
  value       = aws_s3_bucket.static_assets.bucket
}
