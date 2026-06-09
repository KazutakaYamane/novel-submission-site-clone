output "state_bucket_name" {
  description = "Name of the S3 bucket holding Terraform state files. Use this in backend blocks of other root modules."
  value       = aws_s3_bucket.tfstate.id
}

output "state_bucket_arn" {
  description = "ARN of the Terraform state bucket."
  value       = aws_s3_bucket.tfstate.arn
}

output "region" {
  description = "Region where the state bucket and lock table live."
  value       = var.region
}

output "backend_snippet" {
  description = "Copy this into the backend \"s3\" block of other root modules."
  value       = <<-EOT
    backend "s3" {
      bucket       = "${aws_s3_bucket.tfstate.id}"
      key          = "<env>/<component>/terraform.tfstate"
      region       = "${var.region}"
      use_lockfile = true
      encrypt      = true
    }
  EOT
}
