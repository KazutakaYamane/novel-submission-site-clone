<?php

namespace App\Infrastructure\Demo;

use App\Domain\Demo\SampleEpisode;
use App\Domain\Demo\SampleWork;
use App\Domain\Demo\SampleWorkSourceInterface;
use App\Domain\Work\Genre;
use App\Domain\Work\RatingFlag;
use App\Domain\Work\WorkStatus;
use DateInterval;
use Illuminate\Support\Facades\File;

/**
 * 本番のリクエストで読むので、シーダー用の database/ ではなく resources/demo/ に置く
 */
final class FileSampleWorkSource implements SampleWorkSourceInterface
{
    public function load(): SampleWork
    {
        $dir = resource_path('demo/sample-work');
        /** @var array{title: string, catchphrase: string|null, introduction: string, genre: string, status: string, rating_flags: list<string>, tags: list<string>, episodes: list<array{title: string, body_file: string, published_before: string|null}>} $work */
        $work = json_decode(File::get("{$dir}/work.json"), true, flags: JSON_THROW_ON_ERROR);

        return new SampleWork(
            title: $work['title'],
            catchphrase: $work['catchphrase'],
            introduction: $work['introduction'],
            genre: Genre::from($work['genre']),
            status: WorkStatus::from($work['status']),
            ratingFlags: array_map(RatingFlag::from(...), $work['rating_flags']),
            tags: $work['tags'],
            episodes: array_map(fn (array $episode) => new SampleEpisode(
                $episode['title'],
                // エディタがファイルの末尾に足す改行を本文に含めない
                rtrim(File::get("{$dir}/{$episode['body_file']}"), "\n"),
                // ISO 8601 の期間(P1D、PT1H)
                $episode['published_before'] === null ? null : new DateInterval($episode['published_before']),
            ), $work['episodes']),
        );
    }
}
