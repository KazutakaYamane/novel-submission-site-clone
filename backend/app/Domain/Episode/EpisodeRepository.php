<?php

namespace App\Domain\Episode;

/**
 * @phpstan-type EpisodeChanges array{
 *     title?: string,
 *     body?: string,
 * }
 */
interface EpisodeRepository
{
    /**
     * 作品の最後の話の次の position に下書きを足す
     *
     * @return Episode|null 作品が無ければ null
     */
    public function appendDraft(string $workId, string $title, string $body): ?Episode;

    /**
     * @return Episode|null 話が無いか、別の作品の話なら null
     */
    public function findInWork(string $workId, string $episodeId): ?Episode;

    /**
     * @return list<EpisodeSummary> position の順
     */
    public function listByWork(string $workId): array;

    /**
     * 渡した項目だけを書き換える
     *
     * @param  EpisodeChanges  $changes
     * @return Episode|null 書き換えた後の話。話が無いか、別の作品の話なら null
     */
    public function update(string $workId, string $episodeId, array $changes): ?Episode;
}
