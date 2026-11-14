<?php

namespace Database\Seeders;

use App\Domain\Shared\ClockInterface;
use App\Domain\Shared\PasswordHasherInterface;
use App\Domain\User\UserRepositoryInterface;
use App\Domain\Work\Genre;
use App\Domain\Work\WorkStatus;
use App\UseCases\Work\ImportWorkUseCase;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;

/**
 * 公開日時はシードの時刻からの相対にし、いつリセットしてもトップの新着の先頭が 1 時間前の作品になるようにする。
 * マニフェストの i 番目(0 始まり)の作品の最後の話を「今 − 1 時間 − i 日」に置き、その作品の話を 1 日ずつ遡る。
 * すべての話が過去 30 日に収まることは、30 日前にそろえる処理を持たずにテストで確かめる。データを足して超えたらテストが落ちる
 */
class AozoraWorkSeeder extends Seeder
{
    public function run(
        ImportWorkUseCase $importWork,
        UserRepositoryInterface $users,
        PasswordHasherInterface $hasher,
        ClockInterface $clock,
    ): void {
        $dir = database_path('seeders/data');
        /** @var list<array{slug: string}> $manifest */
        $manifest = json_decode(File::get("{$dir}/aozora-manifest.json"), true, flags: JSON_THROW_ON_ERROR);
        $now = $clock->now();
        // シード作者にはログインさせないので、ランダムな値のハッシュを全員で共有する。ハッシュは遅いので 1 回だけ計算する
        $hashedPassword = $hasher->hash(bin2hex(random_bytes(32)));
        /** @var array<string, string> $authorIds 著者名 → ユーザー ID */
        $authorIds = [];

        foreach ($manifest as $i => $entry) {
            /** @var array{author: string, title: string, catchphrase: string, introduction: string, genre: string, status: string, tags: list<string>, episodes: list<array{title: string, body: string}>} $work */
            $work = json_decode(File::get("{$dir}/works/{$entry['slug']}.json"), true, flags: JSON_THROW_ON_ERROR);
            $authorIds[$work['author']] ??= $users->create(
                $work['author'],
                'seed-author-'.(count($authorIds) + 1).'@example.com',
                $hashedPassword,
            )->id;

            $lastPublishedAt = $now->modify('-1 hour')->modify("-{$i} days");
            $lastIndex = count($work['episodes']) - 1;
            $importWork(
                userId: $authorIds[$work['author']],
                title: $work['title'],
                catchphrase: $work['catchphrase'],
                introduction: $work['introduction'],
                genre: Genre::from($work['genre']),
                status: WorkStatus::from($work['status']),
                ratingFlags: [],
                tags: $work['tags'],
                episodes: array_map(fn (int $j, array $episode) => [
                    'title' => $episode['title'],
                    'body' => $episode['body'],
                    'publishedAt' => $lastPublishedAt->modify('-'.($lastIndex - $j).' days'),
                ], array_keys($work['episodes']), $work['episodes']),
            );
        }
    }
}
