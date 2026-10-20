#!/bin/sh
# Laravel のコードから backend/openapi.json を出力し、frontend/lib/api/schema.d.ts を作り直す。
# --check: 作り直した結果が git 管理のファイルと違えば失敗する(ファイルは書き換えない)。
# app・web コンテナが起動している必要がある。
set -eu

cd "$(dirname "$0")/.."

check=false
if [ "${1:-}" = "--check" ]; then
    check=true
fi

tmp=$(mktemp -d)
trap 'rm -rf "$tmp"' EXIT

# scramble:export はファイルにしか出力できないので、コンテナ内の一時ファイルを経由する
docker compose exec -T app sh -c \
    'f=$(mktemp) && php artisan scramble:export --path="$f" >/dev/null && cat "$f" && rm "$f"' \
    > "$tmp/openapi.json"

# web コンテナには ./frontend しかマウントされないので、定義は標準入力で渡す
docker compose exec -T web npx openapi-typescript < "$tmp/openapi.json" > "$tmp/schema.d.ts"

if [ "$check" = true ]; then
    status=0
    diff -u backend/openapi.json "$tmp/openapi.json" || status=1
    diff -u frontend/lib/api/schema.d.ts "$tmp/schema.d.ts" || status=1
    if [ "$status" -ne 0 ]; then
        echo "生成物が古い。scripts/generate-api-types.sh を実行してコミットする" >&2
    fi
    exit "$status"
fi

mkdir -p frontend/lib/api
cp "$tmp/openapi.json" backend/openapi.json
cp "$tmp/schema.d.ts" frontend/lib/api/schema.d.ts
