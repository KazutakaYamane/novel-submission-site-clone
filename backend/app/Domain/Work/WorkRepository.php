<?php

namespace App\Domain\Work;

/**
 * @phpstan-type WorkChanges array{
 *     title?: string,
 *     catchphrase?: string|null,
 *     introduction?: string,
 *     genre?: Genre,
 *     status?: WorkStatus,
 *     ratingFlags?: list<RatingFlag>,
 * }
 */
interface WorkRepository
{
    /**
     * @param  list<RatingFlag>  $ratingFlags
     */
    public function create(
        string $userId,
        string $title,
        ?string $catchphrase,
        string $introduction,
        Genre $genre,
        WorkStatus $status,
        array $ratingFlags,
    ): Work;

    public function findById(string $id): ?Work;

    /**
     * @return list<Work> 作成日時の新しい順。同じ日時なら後に作った順
     */
    public function listByUser(string $userId): array;

    /**
     * 渡した項目だけを書き換える。同時に別の項目を変えたリクエストの値を上書きしないため
     *
     * @param  WorkChanges  $changes
     * @return Work|null 書き換えた後の作品。作品が無ければ null
     */
    public function update(string $id, array $changes): ?Work;
}
