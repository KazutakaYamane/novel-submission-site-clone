#!/bin/sh
# prod イメージの起動スクリプト。
# config:cache はビルド時に実行してはならない: その時点の(空の)env が焼き込まれ、
# 実行時に Task Definition が注入する環境変数(DB_HOST 等)が無視されるため。
# 環境変数が揃っているコンテナ起動時にキャッシュを生成してから php-fpm を起動する。
set -e

# PID 1 のシェルは処理のないシグナルを無視するので、キャッシュ生成の途中で止められると
# stopTimeout の後の SIGKILL まで終わらない。実行中のコマンドが終わった後に抜ける。
# 本来のコマンドを実行していないので、128 + シグナル番号で失敗として終わる
# (一回限りのタスクの migration が成功に見えないように)。
# trap は exec で引き継がれないので、exec 先のプロセスのシグナルの扱いは変わらない
trap 'exit 143' TERM
trap 'exit 131' QUIT
trap 'exit 130' INT

php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache

exec "$@"
