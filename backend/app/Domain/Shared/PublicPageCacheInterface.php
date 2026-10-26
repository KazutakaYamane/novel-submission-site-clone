<?php

namespace App\Domain\Shared;

/**
 * ゲストに見せるページのキャッシュ(Next.js)の破棄を頼む。トランザクションの中で呼ぶと、コミットしてから頼む
 */
interface PublicPageCacheInterface
{
    /**
     * 話のページと、話の一覧を含む作品のページ・トップ
     */
    public function forgetEpisode(string $workId, string $episodeId): void;

    /**
     * 作品のページと、作品を載せるトップ
     */
    public function forgetWork(string $workId): void;
}
