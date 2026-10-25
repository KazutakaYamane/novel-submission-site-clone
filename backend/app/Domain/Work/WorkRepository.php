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
 *     tags?: list<string>,
 * }
 */
interface WorkRepository
{
    /**
     * @param  list<RatingFlag>  $ratingFlags
     * @param  list<string>  $tags  大文字小文字だけが違う名前は同じタグとして 1 つにまとめる
     */
    public function create(
        string $userId,
        string $title,
        ?string $catchphrase,
        string $introduction,
        Genre $genre,
        WorkStatus $status,
        array $ratingFlags,
        array $tags,
    ): Work;

    public function findById(string $id): ?Work;

    /**
     * findById と同じ作品を返し、トランザクションが終わるまで作品の行をロックする
     */
    public function findByIdForUpdate(string $id): ?Work;

    /**
     * @return list<Work> 作成日時の新しい順。同じ日時なら後に作った順
     */
    public function listByUser(string $userId): array;

    /**
     * 渡した項目だけを書き換える。同時に別の項目を変えたリクエストの値を上書きしないため
     * tags を渡すと、作品のタグを全部置き換える
     *
     * @param  WorkChanges  $changes
     * @return Work|null 書き換えた後の作品。作品が無ければ null
     */
    public function update(string $id, array $changes): ?Work;

    /**
     * 作品の公開日時と、最後に話を公開した日時だけを書き込む
     */
    public function savePublication(Work $work): void;
}
