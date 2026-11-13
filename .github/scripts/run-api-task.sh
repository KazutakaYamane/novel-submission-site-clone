#!/usr/bin/env bash
# api のタスク定義で app のコマンドを一回限りのタスクとして実行し、app の終了コードで成否を返す。
# usage: run-api-task.sh <タスク定義> <コマンド>...
# 環境変数 CLUSTER と API_SERVICE が要る。ネットワーク設定は api のサービスと同じものを使う。
set -euo pipefail

task_definition=$1
shift
command=$(jq -cn '$ARGS.positional' --args -- "$@")

network=$(aws ecs describe-services --cluster "$CLUSTER" --services "$API_SERVICE" \
  --query 'services[0].networkConfiguration' --output json)

# app と同じイメージの他のコンテナ(worker・scheduler)は、ジョブやスケジュールを実行させずに待たせる。
# このタスクは Service Connect に入らず web:3000 を解決できず、DB も app のコマンドの途中の状態になるため。
# app が終わると essential の停止でタスクごと止まる。PID 1 の sleep は SIGTERM を無視し
# stopTimeout まで止まらないため、trap で終わらせる
overrides=$(aws ecs describe-task-definition --task-definition "$task_definition" \
  --query 'taskDefinition.containerDefinitions' --output json \
  | jq -c --argjson command "$command" '
      (map(select(.name == "app")) | .[0].image) as $image
      | {containerOverrides: (
          [{name: "app", command: $command}]
          + [.[] | select(.name != "app" and .image == $image)
                 | {name, command: ["sh", "-c", "trap '"'"'exit 0'"'"' TERM; sleep infinity & wait"]}])}')

# Fargate の容量不足などでタスクが作られないときは、tasks が空で failures に理由が入る
result=$(aws ecs run-task \
  --cluster "$CLUSTER" \
  --launch-type FARGATE \
  --task-definition "$task_definition" \
  --network-configuration "$(jq -c '{awsvpcConfiguration}' <<<"$network")" \
  --overrides "$overrides" \
  --output json)
task_arn=$(jq -r '.tasks[0].taskArn // empty' <<<"$result")
if [ -z "$task_arn" ]; then
  echo "::error::タスクを起動できなかった: $(jq -c '.failures' <<<"$result")"
  exit 1
fi
echo "task: $task_arn"

aws ecs wait tasks-stopped --cluster "$CLUSTER" --tasks "$task_arn"
exit_code=$(aws ecs describe-tasks --cluster "$CLUSTER" --tasks "$task_arn" \
  --query "tasks[0].containers[?name=='app'].exitCode | [0]" --output text)
if [ "$exit_code" != "0" ]; then
  echo "::error::$* が失敗した(exitCode=$exit_code)。CloudWatch Logs を確認すること"
  exit 1
fi
