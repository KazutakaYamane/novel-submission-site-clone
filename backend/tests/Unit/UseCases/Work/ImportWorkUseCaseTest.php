<?php

use App\Domain\Episode\EmptyEpisodeBodyException;
use App\Domain\Episode\Episode;
use App\Domain\Episode\EpisodeStatus;
use App\Domain\Episode\PublishAtInFutureException;
use App\Domain\Work\Genre;
use App\Domain\Work\RatingFlag;
use App\Domain\Work\WorkStatus;
use App\UseCases\Work\ImportWorkUseCase;
use Tests\Doubles\FixedClock;
use Tests\Doubles\InMemoryEpisodeRepository;
use Tests\Doubles\InMemoryPublicPageCache;
use Tests\Doubles\InMemoryTransaction;
use Tests\Doubles\InMemoryWorkRepository;

beforeEach(function () {
    $this->works = new InMemoryWorkRepository;
    $this->episodes = new InMemoryEpisodeRepository($this->works);
    $this->pageCache = new InMemoryPublicPageCache;
    $this->clock = new FixedClock(new DateTimeImmutable('2026-10-22 12:00:00'));

    $this->import = fn (array $episodes) => (new ImportWorkUseCase(
        $this->works,
        $this->episodes,
        new InMemoryTransaction([$this->works, $this->episodes]),
        $this->clock,
        $this->pageCache,
    ))(
        userId: 'user-1',
        title: '走れメロス',
        catchphrase: '友のために走る',
        introduction: 'メロスは激怒した。',
        genre: Genre::Drama,
        status: WorkStatus::Completed,
        ratingFlags: [RatingFlag::Violence],
        tags: ['青空文庫', '短編'],
        episodes: $episodes,
    );

    $this->storedEpisodes = fn (string $workId) => array_values(array_filter(
        $this->episodes->rows,
        fn (Episode $episode) => $episode->workId === $workId,
    ));
});

it('作品と話を作り、話に渡した順の position を振る', function () {
    $work = ($this->import)([
        ['title' => '第一話', 'body' => '本文1', 'publishedAt' => new DateTimeImmutable('2026-10-01 09:00:00')],
        ['title' => '第二話', 'body' => '本文2', 'publishedAt' => null],
    ]);

    $episodes = ($this->storedEpisodes)($work->id);
    expect($this->works->rows[$work->id])->toEqual($work)
        ->and($work->userId)->toBe('user-1')
        ->and($work->title)->toBe('走れメロス')
        ->and($work->catchphrase)->toBe('友のために走る')
        ->and($work->introduction)->toBe('メロスは激怒した。')
        ->and($work->genre)->toBe(Genre::Drama)
        ->and($work->status)->toBe(WorkStatus::Completed)
        ->and($work->ratingFlags)->toBe([RatingFlag::Violence])
        ->and($work->tags)->toBe(['短編', '青空文庫'])
        ->and(array_map(fn (Episode $episode) => [$episode->position, $episode->title, $episode->body], $episodes))
        ->toBe([[1, '第一話', '本文1'], [2, '第二話', '本文2']]);
});

it('公開日時を渡した話は公開済み、null の話は下書きになる', function () {
    $work = ($this->import)([
        ['title' => '第一話', 'body' => '本文1', 'publishedAt' => new DateTimeImmutable('2026-10-01 09:00:00')],
        ['title' => '第二話', 'body' => '本文2', 'publishedAt' => null],
    ]);

    [$first, $second] = ($this->storedEpisodes)($work->id);
    expect($first->status)->toBe(EpisodeStatus::Published)
        ->and($first->publishedAt)->toEqual(new DateTimeImmutable('2026-10-01 09:00:00'))
        ->and($second->status)->toBe(EpisodeStatus::Draft)
        ->and($second->publishedAt)->toBeNull();
});

it('公開日時の順が入れ替わっていても、作品の公開日時は最も古い日時、最後の公開日時は最も新しい日時になる', function () {
    $work = ($this->import)([
        ['title' => '第一話', 'body' => '本文1', 'publishedAt' => new DateTimeImmutable('2026-10-10 09:00:00')],
        ['title' => '第二話', 'body' => '本文2', 'publishedAt' => new DateTimeImmutable('2026-10-01 09:00:00')],
        ['title' => '第三話', 'body' => '本文3', 'publishedAt' => new DateTimeImmutable('2026-10-20 09:00:00')],
        ['title' => '第四話', 'body' => '本文4', 'publishedAt' => new DateTimeImmutable('2026-10-05 09:00:00')],
    ]);

    expect($this->works->rows[$work->id]->publishedAt)->toEqual(new DateTimeImmutable('2026-10-01 09:00:00'))
        ->and($this->works->rows[$work->id]->lastEpisodePublishedAt)->toEqual(new DateTimeImmutable('2026-10-20 09:00:00'))
        ->and($work->publishedAt)->toEqual(new DateTimeImmutable('2026-10-01 09:00:00'))
        ->and($work->lastEpisodePublishedAt)->toEqual(new DateTimeImmutable('2026-10-20 09:00:00'));
});

it('公開済みの話があれば、作品のキャッシュの破棄を 1 回頼む', function () {
    $work = ($this->import)([
        ['title' => '第一話', 'body' => '本文1', 'publishedAt' => new DateTimeImmutable('2026-10-01 09:00:00')],
        ['title' => '第二話', 'body' => '本文2', 'publishedAt' => new DateTimeImmutable('2026-10-02 09:00:00')],
    ]);

    expect($this->pageCache->forgotten)->toBe([['work', $work->id]]);
});

it('下書きの話だけなら作品は未公開のままで、キャッシュの破棄を頼まない', function () {
    $work = ($this->import)([
        ['title' => '第一話', 'body' => '本文1', 'publishedAt' => null],
    ]);

    expect($this->works->rows[$work->id]->publishedAt)->toBeNull()
        ->and($this->works->rows[$work->id]->lastEpisodePublishedAt)->toBeNull()
        ->and($this->pageCache->forgotten)->toBe([]);
});

it('現在と同じ日時までは公開日時として受け付ける', function () {
    $work = ($this->import)([
        ['title' => '第一話', 'body' => '本文1', 'publishedAt' => $this->clock->now],
    ]);

    expect($this->works->rows[$work->id]->publishedAt)->toEqual($this->clock->now);
});

it('公開日時を現在の時刻と同じタイムゾーンに直し、秒未満を切り捨てて保存する', function () {
    $utc = new DateTimeZone('UTC');
    $work = ($this->import)([
        ['title' => '第一話', 'body' => '本文1', 'publishedAt' => (new DateTimeImmutable('2026-10-01 09:00:00.75', $utc))->setTimezone(new DateTimeZone('America/New_York'))],
    ]);

    $format = fn (DateTimeImmutable $at) => $at->format('Y-m-d H:i:s.u e');
    $expected = $format((new DateTimeImmutable('2026-10-01 09:00:00', $utc))->setTimezone($this->clock->now->getTimezone()));
    [$episode] = ($this->storedEpisodes)($work->id);
    expect($format($episode->publishedAt))->toBe($expected)
        ->and($format($this->works->rows[$work->id]->publishedAt))->toBe($expected)
        ->and($format($work->lastEpisodePublishedAt))->toBe($expected);
});

it('公開日時が現在より後の話を含むと PublishAtInFutureException を投げ、何も作らない', function () {
    expect(fn () => ($this->import)([
        ['title' => '第一話', 'body' => '本文1', 'publishedAt' => new DateTimeImmutable('2026-10-01 09:00:00')],
        ['title' => '第二話', 'body' => '本文2', 'publishedAt' => new DateTimeImmutable('2026-10-22 12:00:01')],
    ]))->toThrow(PublishAtInFutureException::class)
        ->and($this->works->rows)->toBe([])
        ->and($this->episodes->rows)->toBe([])
        ->and($this->pageCache->forgotten)->toBe([]);
});

it('本文が空白だけの話に公開日時を渡すと EmptyEpisodeBodyException を投げ、何も作らない', function () {
    expect(fn () => ($this->import)([
        ['title' => '第一話', 'body' => '本文1', 'publishedAt' => new DateTimeImmutable('2026-10-01 09:00:00')],
        ['title' => '第二話', 'body' => "　\n", 'publishedAt' => new DateTimeImmutable('2026-10-02 09:00:00')],
    ]))->toThrow(EmptyEpisodeBodyException::class)
        ->and($this->works->rows)->toBe([])
        ->and($this->episodes->rows)->toBe([])
        ->and($this->pageCache->forgotten)->toBe([]);
});

it('失敗して巻き戻したときは、その作品で初めて使ったタグの表記も残さない', function () {
    $useCase = new ImportWorkUseCase(
        $this->works,
        $this->episodes,
        new InMemoryTransaction([$this->works, $this->episodes]),
        $this->clock,
        $this->pageCache,
    );
    $import = fn (string $tag, string $body) => $useCase(
        userId: 'user-1',
        title: '走れメロス',
        catchphrase: null,
        introduction: 'メロスは激怒した。',
        genre: Genre::Drama,
        status: WorkStatus::Completed,
        ratingFlags: [],
        tags: [$tag],
        episodes: [['title' => '第一話', 'body' => $body, 'publishedAt' => new DateTimeImmutable('2026-10-01 09:00:00')]],
    );

    expect(fn () => $import('Aozora', ''))->toThrow(EmptyEpisodeBodyException::class);

    expect($import('aozora', '本文1')->tags)->toBe(['aozora']);
});
