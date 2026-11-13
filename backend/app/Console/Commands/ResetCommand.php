<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Console\ConfirmableTrait;
use Illuminate\Support\Facades\Redis;

class ResetCommand extends Command
{
    use ConfirmableTrait;

    protected $signature = 'app:reset {--force : 本番でも確認せずに実行する}';

    protected $description = 'DB をシードの状態に戻し、Redis の全データを消す';

    public function handle(): int
    {
        if (! $this->confirmToProceed()) {
            return self::FAILURE;
        }

        // prohibitDestructiveCommands で禁じられていると、例外を投げずに FAILURE を返す
        if ($this->call('migrate:fresh', ['--seed' => true, '--force' => true]) !== self::SUCCESS) {
            return self::FAILURE;
        }

        // シード中のリクエストが作ったキャッシュを残さないよう、Redis は DB の後に消す。
        // Laravel(キュー・キャッシュ)と Next.js(キャッシュハンドラ)が DB 番号を分けて同じインスタンスを使うので、FLUSHDB ではなく FLUSHALL にする
        Redis::flushall();

        return self::SUCCESS;
    }
}
