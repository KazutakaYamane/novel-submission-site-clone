<?php

namespace App\Infrastructure\PageCache;

use App\Domain\Shared\PublicPageCacheInterface;

/**
 * タグの名前は Next.js の cacheTag・/api/revalidate との取り決め。Laravel ではここにだけ書く
 */
final class NextPublicPageCache implements PublicPageCacheInterface
{
    public function forgetEpisode(string $workId, string $episodeId): void
    {
        RevalidateNextCache::dispatch(["work:{$workId}", "episode:{$episodeId}", 'top']);
    }

    public function forgetWork(string $workId): void
    {
        RevalidateNextCache::dispatch(["work:{$workId}", 'top']);
    }
}
