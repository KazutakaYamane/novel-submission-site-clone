variable "project" {
  description = "Project name used as the prefix for all resources."
  type        = string
  default     = "novel-submission-site-clone"
}

variable "environment" {
  description = "Environment name (prod)."
  type        = string
  default     = "prod"
}

variable "region" {
  description = "Primary AWS region."
  type        = string
  default     = "ap-northeast-1"
}

variable "domain_name" {
  description = "公開ドメイン名(例: novel-portfolio.kyyk517.com)。この名前で hosted zone を作成し、親ドメインから NS 委譲を受ける。"
  type        = string
}

variable "github_repository" {
  description = "GitHub Actions OIDC の信頼ポリシーで縛るリポジトリ(owner/repo 形式)。"
  type        = string
  default     = "KazutakaYamane/novel-submission-site-clone"
}

variable "github_owner_id" {
  description = "GitHub オーナーの数値 ID。OIDC の sub クレームは repo:<owner>@<owner_id>/<repo>@<repo_id> の形式で発行される。"
  type        = string
  default     = "50511241"
}

variable "github_repository_id" {
  description = "GitHub リポジトリの数値 ID。同名のリポジトリを作り直した場合に別物として扱うため、sub の照合に含める。"
  type        = string
  default     = "1408407741"
}
