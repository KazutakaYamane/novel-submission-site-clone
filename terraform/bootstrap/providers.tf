provider "aws" {
  region = var.region

  default_tags {
    tags = {
      Project   = var.project
      Component = "tfstate-bootstrap"
      ManagedBy = "terraform"
    }
  }
}
