#!/bin/sh
# 本番イメージの web-prod を起動し、キャッシュ越しの公開の反映を確かめる E2E(e2e/tests/cache.spec.ts)を実行する。
# 終了時は、成否や中断にかかわらず worker の破棄の依頼先を戻し、web-prod を消す。
set -eu

cd "$(dirname "$0")/.."

restore() {
    # 片付けの途中で失敗しても残りを続ける。シグナルの trap の exit から呼ばれたときに 2 回走らないよう外す
    trap - EXIT
    set +e
    # 呼び出し元の環境のまま作り直すので、compose の既定値か、利用者が設定した値に戻る
    docker compose up -d --no-deps --force-recreate worker
    docker compose --profile e2e rm -sf web-prod
}
trap restore EXIT
# dash はシグナルで終了するときに EXIT の trap を実行しないので、exit を経由させる
trap 'exit 130' INT
trap 'exit 143' TERM

# テストは Laravel(nginx → app → db)を呼ぶので、dev の環境が止まっていても動くように一緒に起動する
docker compose --profile e2e up -d --build --wait web-prod nginx
NEXT_REVALIDATE_URL=http://web-prod:3000 docker compose up -d --no-deps --force-recreate worker
docker compose --profile e2e run --rm e2e-cache
