<?php

use App\Domain\Work\Genre;
use App\Domain\Work\RatingFlag;
use App\Domain\Work\Work;
use App\Domain\Work\WorkAccessDenied;
use App\Domain\Work\WorkNotFound;
use App\Domain\Work\WorkStatus;
use App\UseCases\Work\CreateWorkUseCase;
use App\UseCases\Work\GetMyWorkUseCase;
use App\UseCases\Work\ListMyWorksUseCase;
use App\UseCases\Work\UpdateMyWorkUseCase;
use Tests\Doubles\InMemoryWorkRepository;

beforeEach(function () {
    $this->works = new InMemoryWorkRepository;

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
