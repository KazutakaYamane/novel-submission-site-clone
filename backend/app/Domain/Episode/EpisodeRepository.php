<?php

namespace App\Domain\Episode;

use DateTimeImmutable;

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
     * findInWork と同じ話を返し、トランザクションが終わるまで話の行をロックする
     *
     * @return Episode|null 話が無いか、別の作品の話なら null
     */
    public function findInWorkForUpdate(string $workId, string $episodeId): ?Episode;

    /**
     * @return list<EpisodeSummary> position の順
     */
    public function listByWork(string $workId): array;

    /**
     * 渡した項目だけを書き換える
     *
     * @param  EpisodeChanges  $changes
     * @return Episode 書き換えた後の話
     */
    public function update(Episode $episode, array $changes): Episode;

    /**
     * 話の状態と公開日時だけを書き込む
     */
    public function savePublication(Episode $episode): void;

    /**
     * @return DateTimeImmutable|null 作品の公開済みの話の公開日時の最大値。公開済みの話が無ければ null
     */
    public function latestPublishedAt(string $workId): ?DateTimeImmutable;
}
