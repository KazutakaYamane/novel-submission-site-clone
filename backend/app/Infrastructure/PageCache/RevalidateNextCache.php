<?php

namespace App\Infrastructure\PageCache;

use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;

/**
 * コミット前に積むと、worker がコミット前のデータでページを作り直すうえ、ロールバックした変更でも破棄してしまう。
 * 最後まで失敗したジョブは Laravel が failed_jobs に残す。公開ページは cacheLife("days") で古さが抑えられる
 */
final class RevalidateNextCache implements ShouldQueueAfterCommit
{
    use Queueable;

    public int $tries = 5;

    /**
     * @param  list<string>  $tags
     */
    public function __construct(public readonly array $tags) {}

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        return [5, 30, 120, 300];
    }

    public function handle(): void
    {
        Http::withToken(Config::string('services.next.revalidate_secret'))
            ->timeout(5)
            ->post(Config::string('services.next.url').'/api/revalidate', ['tags' => $this->tags])
            ->throw();
    }
}
