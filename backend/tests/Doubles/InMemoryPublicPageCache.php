<?php

namespace Tests\Doubles;

use App\Domain\Shared\PublicPageCacheInterface;

final class InMemoryPublicPageCache implements PublicPageCacheInterface
{
    /** @var list<array{0: 'episode', 1: string, 2: string}|array{0: 'work', 1: string}> 破棄を頼んだ順。本物が積むジョブ 1 つが 1 行 */
    public array $forgotten = [];

    public function forgetEpisode(string $workId, string $episodeId): void
    {
        $this->forgotten[] = ['episode', $workId, $episodeId];
    }

    public function forgetWork(string $workId): void
    {
        $this->forgotten[] = ['work', $workId];
    }
}
