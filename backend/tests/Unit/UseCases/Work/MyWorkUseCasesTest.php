<?php

use App\Domain\Work\Genre;
use App\Domain\Work\RatingFlag;
use App\Domain\Work\Work;
use App\Domain\Work\WorkAccessDenied;
use App\Domain\Work\WorkNotFound;
use App\Domain\Work\WorkRepository;
use App\Domain\Work\WorkStatus;
use App\UseCases\Work\CreateWorkUseCase;
use App\UseCases\Work\GetMyWorkUseCase;
use App\UseCases\Work\ListMyWorksUseCase;
use App\UseCases\Work\UpdateMyWorkUseCase;

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

    $this->addWork = function (string $id, string $userId, ?DateTimeImmutable $publishedAt = null, string $createdAt = 'now'): Work {
        return $this->works->rows[$id] = new Work(
            id: $id,
            userId: $userId,
            title: '図書委員の異世界日誌',
            catchphrase: '貸出期限は守ってください',
            introduction: '本の虫が異世界の図書館で働く話。',
            genre: Genre::Fantasy,
            status: WorkStatus::Ongoing,
            ratingFlags: [RatingFlag::Violence],
            tags: ['異世界'],
            publishedAt: $publishedAt,
            lastEpisodePublishedAt: null,
            createdAt: new DateTimeImmutable($createdAt),
        );
    };
});

describe('CreateWorkUseCase', function () {
    it('ログイン中のユーザーの作品として保存する', function () {
        $work = (new CreateWorkUseCase($this->works))(
            'user-1', '図書委員の異世界日誌', null, '本の虫の話。', Genre::Fantasy, WorkStatus::Completed, [RatingFlag::Cruelty], [],
        );

        expect($this->works->rows)->toBe([$work->id => $work])
            ->and($work->userId)->toBe('user-1')
            ->and($work->status)->toBe(WorkStatus::Completed)
            ->and($work->ratingFlags)->toBe([RatingFlag::Cruelty]);
    });

    it('status を指定しなければ連載中にする', function () {
        $work = (new CreateWorkUseCase($this->works))(
            'user-1', '図書委員の異世界日誌', null, '本の虫の話。', Genre::Fantasy, null, [], [],
        );

        expect($work->status)->toBe(WorkStatus::Ongoing);
    });

    it('タグは小文字にした名前のコードポイント順で返す', function () {
        $work = (new CreateWorkUseCase($this->works))(
            'user-1', '図書委員の異世界日誌', null, '本の虫の話。', Genre::Fantasy, null, [], ['Banana', 'apple'],
        );

        expect($work->tags)->toBe(['apple', 'Banana']);
    });

    it('渡したタグを付けて保存する', function () {
        $work = (new CreateWorkUseCase($this->works))(
            'user-1', '図書委員の異世界日誌', null, '本の虫の話。', Genre::Fantasy, null, [], ['図書館', '異世界'],
        );

        expect($this->works->rows[$work->id]->tags)->toEqualCanonicalizing(['図書館', '異世界']);
    });
});

describe('ListMyWorksUseCase', function () {
    it('自分の作品だけを新しい順に返す', function () {
        $old = ($this->addWork)('work-old', 'user-1', createdAt: '2026-10-01');
        $new = ($this->addWork)('work-new', 'user-1', createdAt: '2026-10-02');
        ($this->addWork)('work-other', 'user-2');

        expect((new ListMyWorksUseCase($this->works))('user-1'))->toBe([$new, $old]);
    });
});

describe('GetMyWorkUseCase', function () {
    it('自分の作品なら返す', function () {
        $work = ($this->addWork)('work-1', 'user-1');

        expect((new GetMyWorkUseCase($this->works))('user-1', 'work-1'))->toBe($work);
    });

    it('自分の作品なら未公開でも返す', function () {
        $work = ($this->addWork)('work-1', 'user-1', publishedAt: null);

        expect((new GetMyWorkUseCase($this->works))('user-1', 'work-1'))->toBe($work);
    });

    it('他人の公開済みの作品なら WorkAccessDenied を投げる', function () {
        ($this->addWork)('work-1', 'user-2', publishedAt: new DateTimeImmutable('2026-10-01'));

        expect(fn () => (new GetMyWorkUseCase($this->works))('user-1', 'work-1'))
            ->toThrow(WorkAccessDenied::class);
    });

    it('他人の未公開の作品なら、存在を隠して WorkNotFound を投げる', function () {
        ($this->addWork)('work-1', 'user-2', publishedAt: null);

        expect(fn () => (new GetMyWorkUseCase($this->works))('user-1', 'work-1'))
            ->toThrow(WorkNotFound::class);
    });

    it('存在しない作品なら WorkNotFound を投げる', function () {
        expect(fn () => (new GetMyWorkUseCase($this->works))('user-1', 'work-1'))
            ->toThrow(WorkNotFound::class);
    });
});

describe('UpdateMyWorkUseCase', function () {
    it('渡した項目だけを変えて保存し、変えた作品を返す', function () {
        ($this->addWork)('work-1', 'user-1');

        $updated = (new UpdateMyWorkUseCase($this->works))('user-1', 'work-1', [
            'title' => '新しいタイトル',
            'catchphrase' => null,
            'ratingFlags' => [],
        ]);

        expect($this->works->rows['work-1'])->toEqual($updated)
            ->and($updated->title)->toBe('新しいタイトル')
            ->and($updated->catchphrase)->toBeNull()
            ->and($updated->ratingFlags)->toBe([])
            ->and($updated->introduction)->toBe('本の虫が異世界の図書館で働く話。')
            ->and($updated->genre)->toBe(Genre::Fantasy)
            ->and($updated->status)->toBe(WorkStatus::Ongoing);
    });

    it('タグを渡すと全部置き換え、渡さなければ変えない', function () {
        ($this->addWork)('work-1', 'user-1');
        $useCase = new UpdateMyWorkUseCase($this->works);

        expect($useCase('user-1', 'work-1', ['title' => '新しいタイトル'])->tags)->toBe(['異世界'])
            ->and($useCase('user-1', 'work-1', ['tags' => ['図書館', '料理']])->tags)->toEqualCanonicalizing(['図書館', '料理'])
            ->and($this->works->rows['work-1']->tags)->toEqualCanonicalizing(['図書館', '料理']);
    });

    it('他人の公開済みの作品なら WorkAccessDenied を投げ、変えない', function () {
        $work = ($this->addWork)('work-1', 'user-2', publishedAt: new DateTimeImmutable('2026-10-01'));

        expect(fn () => (new UpdateMyWorkUseCase($this->works))('user-1', 'work-1', ['title' => '書き換え']))
            ->toThrow(WorkAccessDenied::class)
            ->and($this->works->rows['work-1'])->toBe($work);
    });

    it('他人の未公開の作品なら WorkNotFound を投げ、変えない', function () {
        $work = ($this->addWork)('work-1', 'user-2', publishedAt: null);

        expect(fn () => (new UpdateMyWorkUseCase($this->works))('user-1', 'work-1', ['title' => '書き換え']))
            ->toThrow(WorkNotFound::class)
            ->and($this->works->rows['work-1'])->toBe($work);
    });

    it('存在しない作品なら WorkNotFound を投げる', function () {
        expect(fn () => (new UpdateMyWorkUseCase($this->works))('user-1', 'work-1', ['title' => '書き換え']))
            ->toThrow(WorkNotFound::class);
    });
});
