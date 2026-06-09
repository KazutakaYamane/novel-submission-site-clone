# ローカル開発環境のセットアップ

## 前提
- Docker Desktop 4.30+ または Colima 0.6+(Mac/Linux)
- Git
- (任意)IDE: VSCode + PHP Debug 拡張、PHPStorm

## 初回起動

```bash
# 環境変数ファイルを準備
cp .env.example .env

# (任意)Linux で uid を合わせる
# echo "UID=$(id -u)" >> .env && echo "GID=$(id -g)" >> .env

# Laravel プロジェクトをまだ作っていない場合
docker compose run --rm app composer create-project laravel/laravel . "^13.0"

# 既存の場合は依存解決
docker compose run --rm app composer install

# アプリケーションキー生成
docker compose run --rm app php artisan key:generate

# 起動
docker compose up -d

# DB マイグレーション
docker compose exec app php artisan migrate

# フロントエンドが必要なら(別ターミナル)
docker compose --profile frontend up vite
```

## アクセス先

| サービス | URL |
|---|---|
| アプリ(Nginx) | http://localhost:8080 |
| Mailpit Web UI | http://localhost:8025 |
| MinIO 管理画面 | http://localhost:9001 (minioadmin/minioadmin) |
| MySQL | localhost:3306 (novel-submission-site/secret、root: secret) |
| Redis | localhost:6379 |
| Vite (HMR) | http://localhost:5173 |

## よく使うコマンド

```bash
# シェルに入る
docker compose exec app sh

# Artisan
docker compose exec app php artisan migrate
docker compose exec app php artisan tinker

# テスト
docker compose exec app ./vendor/bin/pest
docker compose exec app ./vendor/bin/phpstan analyse
docker compose exec app ./vendor/bin/pint --test

# DB クライアントで直接繋ぐ
docker compose exec db mysql -u novel-submission-site -psecret novel-submission-site

# 全部止める / ボリュームも消す
docker compose down
docker compose down -v   # MySQL データも消える、注意
```

## 本番イメージのビルド(ローカル試験)

```bash
# linux/arm64(本番 Graviton 想定)+ amd64 マルチアーキ
docker buildx build \
  --platform linux/arm64,linux/amd64 \
  -f docker/app/Dockerfile \
  --target prod \
  -t novel-submission-site-clone-app:local \
  .

docker buildx build \
  --platform linux/arm64,linux/amd64 \
  -f docker/nginx/Dockerfile \
  -t novel-submission-site-clone-nginx:local \
  .
```
