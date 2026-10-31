<?php

use App\Domain\Episode\EmptyEpisodeBodyException;
use App\Domain\Episode\Episode;
use App\Domain\Episode\EpisodeNotFoundException;
use App\Domain\Episode\EpisodeStatus;
use App\Domain\Episode\EpisodeSummary;
use App\Domain\Episode\InvalidEpisodeTransitionException;
use App\Domain\Work\Genre;
use App\Domain\Work\Work;
use App\Domain\Work\WorkAccessDeniedException;
use App\Domain\Work\WorkNotFoundException;
use App\Domain\Work\WorkStatus;
use App\UseCases\Episode\CreateEpisodeDraftUseCase;
use App\UseCases\Episode\GetMyEpisodeUseCase;
use App\UseCases\Episode\ListMyEpisodesUseCase;
use App\UseCases\Episode\PublishEpisodeUseCase;
use App\UseCases\Episode\UnpublishEpisodeUseCase;
use App\UseCases\Episode\UpdateMyEpisodeUseCase;
use Tests\Doubles\FixedClock;
use Tests\Doubles\InMemoryEpisodeRepository;
use Tests\Doubles\InMemoryPublicPageCache;
use Tests\Doubles\InMemoryTransaction;
use Tests\Doubles\InMemoryWorkRepository;

beforeEach(function () {
    $this->works = new InMemoryWorkRepository;

    $this->episodes = new InMemoryEpisodeRepository($this->works);

    $this->pageCache = new InMemoryPublicPageCache;

    $this->transaction = new InMemoryTransaction([$this->works, $this->episodes]);

    $this->clock = new FixedClock(new DateTimeImmutable('2026-10-22 12:00:00'));

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

    $this->addEpisode = function (string $id, string $workId, int $position, ?DateTimeImmutable $publishedAt = null, string $body = '元の本文'): Episode {
        return $this->episodes->rows[$id] = new Episode(
            id: $id,
            workId: $workId,
            title: "第{$position}話",
            body: $body,
            position: $position,
            status: $publishedAt === null ? EpisodeStatus::Draft : EpisodeStatus::Published,
            publishedAt: $publishedAt,
        );
    };
});

dataset('他人の作品', [
    '公開済みなら WorkAccessDeniedException' => [new DateTimeImmutable('2026-10-01'), WorkAccessDeniedException::class],
    '未公開なら、存在を隠して WorkNotFoundException' => [null, WorkNotFoundException::class],
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

    it('存在しない作品なら WorkNotFoundException を投げる', function () {
        expect(fn () => (new CreateEpisodeDraftUseCase($this->works, $this->episodes))('user-1', 'work-1', '第一話', '本文'))
            ->toThrow(WorkNotFoundException::class);
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

    it('別の作品の話か存在しない話なら EpisodeNotFoundException を投げる', function (string $episodeId) {
        ($this->addWork)('work-1', 'user-1');
        ($this->addWork)('work-2', 'user-1');
        ($this->addEpisode)('episode-other', 'work-2', 1);

        expect(fn () => (new GetMyEpisodeUseCase($this->works, $this->episodes))('user-1', 'work-1', $episodeId))
            ->toThrow(EpisodeNotFoundException::class);
    })->with(['別の作品の話' => 'episode-other', '存在しない話' => 'episode-missing']);

    it('存在しない作品なら WorkNotFoundException を投げる', function () {
        expect(fn () => (new GetMyEpisodeUseCase($this->works, $this->episodes))('user-1', 'work-1', 'episode-a'))
            ->toThrow(WorkNotFoundException::class);
    });
});

describe('UpdateMyEpisodeUseCase', function () {
    it('渡した項目だけを変えて保存し、変えた話を返す', function () {
        ($this->addWork)('work-1', 'user-1');
        ($this->addEpisode)('episode-a', 'work-1', 1);
        $useCase = new UpdateMyEpisodeUseCase($this->works, $this->episodes, $this->transaction, $this->pageCache);

        $updated = $useCase('user-1', 'work-1', 'episode-a', ['title' => '新しいタイトル']);

        expect($this->episodes->rows['episode-a'])->toBe($updated)
            ->and($updated->title)->toBe('新しいタイトル')
            ->and($updated->body)->toBe('元の本文')
            ->and($useCase('user-1', 'work-1', 'episode-a', ['body' => ''])->body)->toBe('');
    });

    it('他人の作品なら例外を投げ、変えない', function (?DateTimeImmutable $publishedAt, string $exception) {
        ($this->addWork)('work-1', 'user-2', $publishedAt);
        $episode = ($this->addEpisode)('episode-a', 'work-1', 1);

        expect(fn () => (new UpdateMyEpisodeUseCase($this->works, $this->episodes, $this->transaction, $this->pageCache))('user-1', 'work-1', 'episode-a', ['title' => '書き換え']))
            ->toThrow($exception)
            ->and($this->episodes->rows['episode-a'])->toBe($episode);
    })->with('他人の作品');

    it('公開済みの話の本文を空白だけにするなら EmptyEpisodeBodyException を投げ、変えない', function () {
        ($this->addWork)('work-1', 'user-1', new DateTimeImmutable('2026-10-01'));
        $episode = ($this->addEpisode)('episode-a', 'work-1', 1, new DateTimeImmutable('2026-10-01'));

        expect(fn () => (new UpdateMyEpisodeUseCase($this->works, $this->episodes, $this->transaction, $this->pageCache))('user-1', 'work-1', 'episode-a', ['title' => '書き換え', 'body' => "　\n"]))
            ->toThrow(EmptyEpisodeBodyException::class)
            ->and($this->episodes->rows['episode-a'])->toBe($episode);
    });

    it('公開済みの話を変えたら、話のページの破棄を頼む', function () {
        ($this->addWork)('work-1', 'user-1', new DateTimeImmutable('2026-10-01'));
        ($this->addEpisode)('episode-a', 'work-1', 1, new DateTimeImmutable('2026-10-01'));

        (new UpdateMyEpisodeUseCase($this->works, $this->episodes, $this->transaction, $this->pageCache))('user-1', 'work-1', 'episode-a', ['body' => '直した本文']);

        expect($this->pageCache->forgotten)->toBe([['episode', 'work-1', 'episode-a']]);
    });

    it('下書きを変えても、破棄を頼まない', function () {
        ($this->addWork)('work-1', 'user-1');
        ($this->addEpisode)('episode-a', 'work-1', 1);

        (new UpdateMyEpisodeUseCase($this->works, $this->episodes, $this->transaction, $this->pageCache))('user-1', 'work-1', 'episode-a', ['body' => '直した本文']);

        expect($this->pageCache->forgotten)->toBe([]);
    });

    it('変えられなければ、破棄を頼まない', function () {
        ($this->addWork)('work-1', 'user-1', new DateTimeImmutable('2026-10-01'));
        ($this->addEpisode)('episode-a', 'work-1', 1, new DateTimeImmutable('2026-10-01'));

        expect(fn () => (new UpdateMyEpisodeUseCase($this->works, $this->episodes, $this->transaction, $this->pageCache))('user-1', 'work-1', 'episode-a', ['body' => '']))
            ->toThrow(EmptyEpisodeBodyException::class)
            ->and($this->pageCache->forgotten)->toBe([]);
    });

    it('別の作品の話なら EpisodeNotFoundException を投げ、変えない', function () {
        ($this->addWork)('work-1', 'user-1');
        ($this->addWork)('work-2', 'user-1');
        $episode = ($this->addEpisode)('episode-other', 'work-2', 1);

        expect(fn () => (new UpdateMyEpisodeUseCase($this->works, $this->episodes, $this->transaction, $this->pageCache))('user-1', 'work-1', 'episode-other', ['title' => '書き換え']))
            ->toThrow(EpisodeNotFoundException::class)
            ->and($this->episodes->rows['episode-other'])->toBe($episode);
    });
});

describe('PublishEpisodeUseCase', function () {
    beforeEach(function () {
        $this->publish = new PublishEpisodeUseCase($this->works, $this->episodes, $this->transaction, $this->clock, $this->pageCache);
    });

    it('下書きを公開し、未公開の作品も同じ時刻で公開する', function () {
        ($this->addWork)('work-1', 'user-1');
        ($this->addEpisode)('episode-a', 'work-1', 1);

        $episode = ($this->publish)('user-1', 'work-1', 'episode-a');

        expect($this->episodes->rows['episode-a'])->toEqual($episode)
            ->and($episode->status)->toBe(EpisodeStatus::Published)
            ->and($episode->publishedAt)->toBe($this->clock->now)
            ->and($this->works->rows['work-1']->publishedAt)->toBe($this->clock->now)
            ->and($this->works->rows['work-1']->lastEpisodePublishedAt)->toBe($this->clock->now);
    });

    it('公開済みの作品なら、作品の公開日時を変えずに最後に話を公開した日時だけを変える', function () {
        $workPublishedAt = new DateTimeImmutable('2026-10-01');
        ($this->addWork)('work-1', 'user-1', $workPublishedAt);
        ($this->addEpisode)('episode-a', 'work-1', 1, $workPublishedAt);
        ($this->addEpisode)('episode-b', 'work-1', 2);

        ($this->publish)('user-1', 'work-1', 'episode-b');

        expect($this->works->rows['work-1']->publishedAt)->toBe($workPublishedAt)
            ->and($this->works->rows['work-1']->lastEpisodePublishedAt)->toBe($this->clock->now);
    });

    it('公開したら、話のページの破棄を頼む', function () {
        ($this->addWork)('work-1', 'user-1');
        ($this->addEpisode)('episode-a', 'work-1', 1);

        ($this->publish)('user-1', 'work-1', 'episode-a');

        expect($this->pageCache->forgotten)->toBe([['episode', 'work-1', 'episode-a']]);
    });

    it('公開できなければ、破棄を頼まない', function () {
        ($this->addWork)('work-1', 'user-1');
        ($this->addEpisode)('episode-a', 'work-1', 1, body: '');

        expect(fn () => ($this->publish)('user-1', 'work-1', 'episode-a'))->toThrow(EmptyEpisodeBodyException::class)
            ->and($this->pageCache->forgotten)->toBe([]);
    });

    it('公開できない話なら例外を投げ、話も作品も変えない', function (?DateTimeImmutable $publishedAt, string $body, string $exception) {
        $work = ($this->addWork)('work-1', 'user-1');
        $episode = ($this->addEpisode)('episode-a', 'work-1', 1, $publishedAt, $body);

        expect(fn () => ($this->publish)('user-1', 'work-1', 'episode-a'))->toThrow($exception)
            ->and($this->episodes->rows['episode-a'])->toBe($episode)
            ->and($this->works->rows['work-1'])->toBe($work);
    })->with([
        '本文が全角空白と改行だけ' => [null, "　\n", EmptyEpisodeBodyException::class],
        'すでに公開済み' => [new DateTimeImmutable('2026-10-01'), '本文', InvalidEpisodeTransitionException::class],
    ]);

    it('他人の作品なら例外を投げ、公開しない', function (?DateTimeImmutable $publishedAt, string $exception) {
        ($this->addWork)('work-1', 'user-2', $publishedAt);
        $episode = ($this->addEpisode)('episode-a', 'work-1', 1);

        expect(fn () => ($this->publish)('user-1', 'work-1', 'episode-a'))->toThrow($exception)
            ->and($this->episodes->rows['episode-a'])->toBe($episode);
    })->with('他人の作品');

    it('他人の作品なら、作品の行をロックしない', function (?DateTimeImmutable $publishedAt, string $exception) {
        ($this->addWork)('work-1', 'user-2', $publishedAt);
        ($this->addEpisode)('episode-a', 'work-1', 1, null);

        expect(fn () => ($this->publish)('user-1', 'work-1', 'episode-a'))->toThrow($exception)
            ->and($this->works->lockedIds)->toBe([]);
    })->with('他人の作品');

    it('別の作品の話か存在しない話なら EpisodeNotFoundException を投げる', function (string $episodeId) {
        ($this->addWork)('work-1', 'user-1');
        ($this->addWork)('work-2', 'user-1');
        $episode = ($this->addEpisode)('episode-other', 'work-2', 1);

        expect(fn () => ($this->publish)('user-1', 'work-1', $episodeId))->toThrow(EpisodeNotFoundException::class)
            ->and($this->episodes->rows['episode-other'])->toBe($episode);
    })->with(['別の作品の話' => 'episode-other', '存在しない話' => 'episode-missing']);
});

describe('UnpublishEpisodeUseCase', function () {
    beforeEach(function () {
        $this->unpublish = new UnpublishEpisodeUseCase($this->works, $this->episodes, $this->transaction, $this->pageCache);
    });

    it('下書きに戻し、最後に話を公開した日時を残りの公開済みの話の最大値にする', function () {
        $workPublishedAt = new DateTimeImmutable('2026-10-01');
        ($this->addWork)('work-1', 'user-1', $workPublishedAt);
        ($this->addWork)('work-2', 'user-1', $workPublishedAt);
        ($this->addEpisode)('episode-a', 'work-1', 1, new DateTimeImmutable('2026-10-01'));
        ($this->addEpisode)('episode-b', 'work-1', 2, new DateTimeImmutable('2026-10-05'));
        ($this->addEpisode)('episode-c', 'work-1', 3, new DateTimeImmutable('2026-10-10'));
        ($this->addEpisode)('episode-other', 'work-2', 1, new DateTimeImmutable('2026-10-20'));

        $episode = ($this->unpublish)('user-1', 'work-1', 'episode-c');

        expect($this->episodes->rows['episode-c'])->toEqual($episode)
            ->and($episode->status)->toBe(EpisodeStatus::Draft)
            ->and($episode->publishedAt)->toBeNull()
            ->and($this->works->rows['work-1']->lastEpisodePublishedAt)->toEqual(new DateTimeImmutable('2026-10-05'))
            ->and($this->works->rows['work-1']->publishedAt)->toBe($workPublishedAt);
    });

    it('公開済みの話が残らなければ、最後に話を公開した日時を null にし、作品の公開日時は残す', function () {
        $workPublishedAt = new DateTimeImmutable('2026-10-01');
        ($this->addWork)('work-1', 'user-1', $workPublishedAt);
        ($this->addEpisode)('episode-a', 'work-1', 1, $workPublishedAt);

        ($this->unpublish)('user-1', 'work-1', 'episode-a');

        expect($this->works->rows['work-1']->lastEpisodePublishedAt)->toBeNull()
            ->and($this->works->rows['work-1']->publishedAt)->toBe($workPublishedAt);
    });

    it('非公開にしたら、話のページの破棄を頼む', function () {
        ($this->addWork)('work-1', 'user-1', new DateTimeImmutable('2026-10-01'));
        ($this->addEpisode)('episode-a', 'work-1', 1, new DateTimeImmutable('2026-10-01'));

        ($this->unpublish)('user-1', 'work-1', 'episode-a');

        expect($this->pageCache->forgotten)->toBe([['episode', 'work-1', 'episode-a']]);
    });

    it('非公開にできなければ、破棄を頼まない', function () {
        ($this->addWork)('work-1', 'user-1');
        ($this->addEpisode)('episode-a', 'work-1', 1);

        expect(fn () => ($this->unpublish)('user-1', 'work-1', 'episode-a'))->toThrow(InvalidEpisodeTransitionException::class)
            ->and($this->pageCache->forgotten)->toBe([]);
    });

    it('下書きなら InvalidEpisodeTransitionException を投げ、変えない', function () {
        $work = ($this->addWork)('work-1', 'user-1');
        $episode = ($this->addEpisode)('episode-a', 'work-1', 1);

        expect(fn () => ($this->unpublish)('user-1', 'work-1', 'episode-a'))->toThrow(InvalidEpisodeTransitionException::class)
            ->and($this->episodes->rows['episode-a'])->toBe($episode)
            ->and($this->works->rows['work-1'])->toBe($work);
    });

    it('他人の作品なら例外を投げ、非公開にしない', function (?DateTimeImmutable $publishedAt, string $exception) {
        ($this->addWork)('work-1', 'user-2', $publishedAt);
        $episode = ($this->addEpisode)('episode-a', 'work-1', 1, new DateTimeImmutable('2026-10-01'));

        expect(fn () => ($this->unpublish)('user-1', 'work-1', 'episode-a'))->toThrow($exception)
            ->and($this->episodes->rows['episode-a'])->toBe($episode);
    })->with('他人の作品');

    it('他人の作品なら、作品の行をロックしない', function (?DateTimeImmutable $publishedAt, string $exception) {
        ($this->addWork)('work-1', 'user-2', $publishedAt);
        ($this->addEpisode)('episode-a', 'work-1', 1, new DateTimeImmutable('2026-10-01'));

        expect(fn () => ($this->unpublish)('user-1', 'work-1', 'episode-a'))->toThrow($exception)
            ->and($this->works->lockedIds)->toBe([]);
    })->with('他人の作品');

    it('別の作品の話か存在しない話なら EpisodeNotFoundException を投げる', function (string $episodeId) {
        ($this->addWork)('work-1', 'user-1');
        ($this->addWork)('work-2', 'user-1');
        $episode = ($this->addEpisode)('episode-other', 'work-2', 1, new DateTimeImmutable('2026-10-01'));

        expect(fn () => ($this->unpublish)('user-1', 'work-1', $episodeId))->toThrow(EpisodeNotFoundException::class)
            ->and($this->episodes->rows['episode-other'])->toBe($episode);
    })->with(['別の作品の話' => 'episode-other', '存在しない話' => 'episode-missing']);
});
