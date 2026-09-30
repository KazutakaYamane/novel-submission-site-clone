#!/bin/sh
# prod イメージの起動スクリプト。
# config:cache はビルド時に実行してはならない: その時点の(空の)env が焼き込まれ、
# 実行時に Task Definition が注入する環境変数(DB_HOST 等)が無視されるため。
# 環境変数が揃っているコンテナ起動時にキャッシュを生成してから php-fpm を起動する。
set -e

php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache

exec "$@"
