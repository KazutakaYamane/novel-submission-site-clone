terraform {
  required_version = "~> 1.11" # S3 ネイティブロック(use_lockfile)は 1.11 で GA

  required_providers {
    aws = {
      source  = "hashicorp/aws"
      version = "~> 6.0"
    }
  }
}
