<?php

use App\Domain\Episode\Episode;
use App\Domain\Episode\EpisodeNotFound;
use App\Domain\Episode\EpisodeRepository;
use App\Domain\Episode\EpisodeStatus;
use App\Domain\Episode\EpisodeSummary;
use App\Domain\Work\Genre;
use App\Domain\Work\Work;
use App\Domain\Work\WorkAccessDenied;
use App\Domain\Work\WorkNotFound;
use App\Domain\Work\WorkRepository;
use App\Domain\Work\WorkStatus;
use App\UseCases\Episode\CreateEpisodeDraftUseCase;
use App\UseCases\Episode\GetMyEpisodeUseCase;
use App\UseCases\Episode\ListMyEpisodesUseCase;
use App\UseCases\Episode\UpdateMyEpisodeUseCase;

beforeEach(function () {
    $this->works = new class implements WorkRepository
    {
        /** @var array<string, Work> */
        public array $rows = [];

        /** @var array<string, string> 小文字にした名前 => 最初に登録された表記。本物の tags テーブルと同じく作品の間で共有する */
        private array $tagNames = [];

        private int $sequence = 0;

        public function create(
            string $userId,
            string $title,
            ?string $catchphrase,
            string $introduction,
            Genre $genre,
            WorkStatus $status,
            array $ratingFlags,
            array $tags,
        ): Work {
            $work = new Work(
                // addWork で入れた行の id と重ならないよう、接頭辞を分ける
                id: 'created-'.++$this->sequence,
                userId: $userId,
                title: $title,
                catchphrase: $catchphrase,
                introduction: $introduction,
                genre: $genre,
                status: $status,
                ratingFlags: $ratingFlags,
                tags: $this->storeTags($tags),
                publishedAt: null,
                lastEpisodePublishedAt: null,
                createdAt: new DateTimeImmutable,
            );
            $this->rows[$work->id] = $work;

            return $work;
        }

        public function findById(string $id): ?Work
        {
            return $this->rows[$id] ?? null;
        }

        public function listByUser(string $userId): array
        {
            $works = array_values(array_filter($this->rows, fn (Work $work) => $work->userId === $userId));
            // 本物は同じ時刻なら UUIDv7 の id の降順。ここでは連番の id で代える
            usort($works, fn (Work $a, Work $b) => [$b->createdAt, $b->id] <=> [$a->createdAt, $a->id]);

            return $works;
        }

        public function update(string $id, array $changes): ?Work
        {
            $stored = $this->rows[$id] ?? null;
            if ($stored === null) {
                return null;
            }

            return $this->rows[$id] = new Work(
                id: $stored->id,
                userId: $stored->userId,
                title: $changes['title'] ?? $stored->title,
                catchphrase: array_key_exists('catchphrase', $changes) ? $changes['catchphrase'] : $stored->catchphrase,
                introduction: $changes['introduction'] ?? $stored->introduction,
                genre: $changes['genre'] ?? $stored->genre,
                status: $changes['status'] ?? $stored->status,
                ratingFlags: $changes['ratingFlags'] ?? $stored->ratingFlags,
                tags: array_key_exists('tags', $changes) ? $this->storeTags($changes['tags']) : $stored->tags,
                publishedAt: $stored->publishedAt,
                lastEpisodePublishedAt: $stored->lastEpisodePublishedAt,
                createdAt: $stored->createdAt,
            );
        }

        /**
         * @param  list<string>  $names
         * @return list<string>
         */
        private function storeTags(array $names): array
        {
            $tags = [];
            foreach ($names as $name) {
                $key = mb_strtolower($name);
                $this->tagNames[$key] ??= $name;
                $tags[$key] = $this->tagNames[$key];
            }
            // 本物は utf8mb4_bin の normalized_name で並べる。バイト順は UTF-8 のコードポイント順と同じ
            ksort($tags, SORT_STRING);

            return array_values($tags);
        }
    };

    $this->episodes = new class($this->works) implements EpisodeRepository
    {
        /** @var array<string, Episode> */
        public array $rows = [];

        private int $sequence = 0;

        // 本物の episodes.work_id の外部キーと同じく、無い作品には話を足せない
        public function __construct(private WorkRepository $works) {}

        public function appendDraft(string $workId, string $title, string $body): ?Episode
        {
            if ($this->works->findById($workId) === null) {
                return null;
            }

            $positions = array_map(
                fn (Episode $episode) => $episode->position,
                array_filter($this->rows, fn (Episode $episode) => $episode->workId === $workId),
            );
            $episode = new Episode(
                id: 'episode-'.++$this->sequence,
                workId: $workId,
                title: $title,
                body: $body,
                position: ($positions === [] ? 0 : max($positions)) + 1,
                status: EpisodeStatus::Draft,
                publishedAt: null,
            );

            return $this->rows[$episode->id] = $episode;
        }

        public function findInWork(string $workId, string $episodeId): ?Episode
        {
            $episode = $this->rows[$episodeId] ?? null;

            return $episode?->workId === $workId ? $episode : null;
        }

        public function listByWork(string $workId): array
        {
            $episodes = array_values(array_filter($this->rows, fn (Episode $episode) => $episode->workId === $workId));
            usort($episodes, fn (Episode $a, Episode $b) => $a->position <=> $b->position);

            return array_map(fn (Episode $episode) => new EpisodeSummary(
                id: $episode->id,
                title: $episode->title,
                position: $episode->position,
                status: $episode->status,
                publishedAt: $episode->publishedAt,
            ), $episodes);
        }

        public function update(string $workId, string $episodeId, array $changes): ?Episode
        {
            $stored = $this->findInWork($workId, $episodeId);
            if ($stored === null) {
                return null;
            }

            return $this->rows[$episodeId] = new Episode(
                id: $stored->id,
                workId: $stored->workId,
                title: $changes['title'] ?? $stored->title,
                body: $changes['body'] ?? $stored->body,
                position: $stored->position,
                status: $stored->status,
                publishedAt: $stored->publishedAt,
            );
        }
    };

    $this->addWork = function (string $id, string $userId, ?DateTimeImmutable $publishedAt = null): Work {
        return $this->works->rows[$id] = new Work(
            id: $id,
            userId: $userId,
            title: '図書委員の異世界日誌',
            catchphrase: null,
            introduction: '本の虫が異世界の図書館で働く話。',
            genre: Genre::Fantasy,
            status: WorkStatus::Ongoing,
            ratingFlags: [],
            tags: [],
            publishedAt: $publishedAt,
            lastEpisodePublishedAt: null,
            createdAt: new DateTimeImmutable,
        );
    };

    $this->addEpisode = function (string $id, string $workId, int $position): Episode {
        return $this->episodes->rows[$id] = new Episode(
            id: $id,
            workId: $workId,
            title: "第{$position}話",
            body: '元の本文',
            position: $position,
            status: EpisodeStatus::Draft,
            publishedAt: null,
        );
    };
});

dataset('他人の作品', [
    '公開済みなら WorkAccessDenied' => [new DateTimeImmutable('2026-10-01'), WorkAccessDenied::class],
    '未公開なら、存在を隠して WorkNotFound' => [null, WorkNotFound::class],
]);

describe('CreateEpisodeDraftUseCase', function () {
    it('作品の最後の話の次に下書きとして保存する', function () {
        ($this->addWork)('work-1', 'user-1');
        ($this->addEpisode)('episode-a', 'work-1', 1);

        $episode = (new CreateEpisodeDraftUseCase($this->works, $this->episodes))('user-1', 'work-1', '第二話', '本文');

        expect($this->episodes->rows[$episode->id])->toBe($episode)
            ->and($episode->workId)->toBe('work-1')
            ->and($episode->title)->toBe('第二話')
            ->and($episode->body)->toBe('本文')
            ->and($episode->position)->toBe(2)
            ->and($episode->status)->toBe(EpisodeStatus::Draft)
            ->and($episode->publishedAt)->toBeNull();
    });

    it('他人の作品なら例外を投げ、保存しない', function (?DateTimeImmutable $publishedAt, string $exception) {
        ($this->addWork)('work-1', 'user-2', $publishedAt);

        expect(fn () => (new CreateEpisodeDraftUseCase($this->works, $this->episodes))('user-1', 'work-1', '第一話', '本文'))
            ->toThrow($exception)
            ->and($this->episodes->rows)->toBe([]);
    })->with('他人の作品');

    it('存在しない作品なら WorkNotFound を投げる', function () {
        expect(fn () => (new CreateEpisodeDraftUseCase($this->works, $this->episodes))('user-1', 'work-1', '第一話', '本文'))
            ->toThrow(WorkNotFound::class);
    });
});

describe('ListMyEpisodesUseCase', function () {
    it('作品の話だけを position の順に返す', function () {
        ($this->addWork)('work-1', 'user-1');
        ($this->addWork)('work-2', 'user-1');
        ($this->addEpisode)('episode-b', 'work-1', 2);
        ($this->addEpisode)('episode-a', 'work-1', 1);
        ($this->addEpisode)('episode-c', 'work-2', 1);

        $episodes = (new ListMyEpisodesUseCase($this->works, $this->episodes))('user-1', 'work-1');

        expect(array_map(fn (EpisodeSummary $episode) => $episode->id, $episodes))->toBe(['episode-a', 'episode-b']);
    });

    it('他人の作品なら例外を投げる', function (?DateTimeImmutable $publishedAt, string $exception) {
        ($this->addWork)('work-1', 'user-2', $publishedAt);
        ($this->addEpisode)('episode-a', 'work-1', 1);

        expect(fn () => (new ListMyEpisodesUseCase($this->works, $this->episodes))('user-1', 'work-1'))
            ->toThrow($exception);
    })->with('他人の作品');
});

describe('GetMyEpisodeUseCase', function () {
    it('自分の作品の話を返す', function () {
        ($this->addWork)('work-1', 'user-1');
        $episode = ($this->addEpisode)('episode-a', 'work-1', 1);

        expect((new GetMyEpisodeUseCase($this->works, $this->episodes))('user-1', 'work-1', 'episode-a'))->toBe($episode);
    });

    it('他人の作品なら、話があるかどうかによらず同じ例外を投げる', function (?DateTimeImmutable $publishedAt, string $exception) {
        ($this->addWork)('work-1', 'user-2', $publishedAt);
        ($this->addEpisode)('episode-a', 'work-1', 1);
        $useCase = new GetMyEpisodeUseCase($this->works, $this->episodes);

        expect(fn () => $useCase('user-1', 'work-1', 'episode-a'))->toThrow($exception)
            ->and(fn () => $useCase('user-1', 'work-1', 'episode-missing'))->toThrow($exception);
    })->with('他人の作品');

    it('別の作品の話か存在しない話なら EpisodeNotFound を投げる', function (string $episodeId) {
        ($this->addWork)('work-1', 'user-1');
        ($this->addWork)('work-2', 'user-1');
        ($this->addEpisode)('episode-other', 'work-2', 1);

        expect(fn () => (new GetMyEpisodeUseCase($this->works, $this->episodes))('user-1', 'work-1', $episodeId))
            ->toThrow(EpisodeNotFound::class);
    })->with(['別の作品の話' => 'episode-other', '存在しない話' => 'episode-missing']);

    it('存在しない作品なら WorkNotFound を投げる', function () {
        expect(fn () => (new GetMyEpisodeUseCase($this->works, $this->episodes))('user-1', 'work-1', 'episode-a'))
            ->toThrow(WorkNotFound::class);
    });
});

describe('UpdateMyEpisodeUseCase', function () {
    it('渡した項目だけを変えて保存し、変えた話を返す', function () {
        ($this->addWork)('work-1', 'user-1');
        ($this->addEpisode)('episode-a', 'work-1', 1);
        $useCase = new UpdateMyEpisodeUseCase($this->works, $this->episodes);

        $updated = $useCase('user-1', 'work-1', 'episode-a', ['title' => '新しいタイトル']);

        expect($this->episodes->rows['episode-a'])->toBe($updated)
            ->and($updated->title)->toBe('新しいタイトル')
            ->and($updated->body)->toBe('元の本文')
            ->and($useCase('user-1', 'work-1', 'episode-a', ['body' => ''])->body)->toBe('');
    });

    it('他人の作品なら例外を投げ、変えない', function (?DateTimeImmutable $publishedAt, string $exception) {
        ($this->addWork)('work-1', 'user-2', $publishedAt);
        $episode = ($this->addEpisode)('episode-a', 'work-1', 1);

        expect(fn () => (new UpdateMyEpisodeUseCase($this->works, $this->episodes))('user-1', 'work-1', 'episode-a', ['title' => '書き換え']))
            ->toThrow($exception)
            ->and($this->episodes->rows['episode-a'])->toBe($episode);
    })->with('他人の作品');

    it('別の作品の話なら EpisodeNotFound を投げ、変えない', function () {
        ($this->addWork)('work-1', 'user-1');
        ($this->addWork)('work-2', 'user-1');
        $episode = ($this->addEpisode)('episode-other', 'work-2', 1);

        expect(fn () => (new UpdateMyEpisodeUseCase($this->works, $this->episodes))('user-1', 'work-1', 'episode-other', ['title' => '書き換え']))
            ->toThrow(EpisodeNotFound::class)
            ->and($this->episodes->rows['episode-other'])->toBe($episode);
    });
});
