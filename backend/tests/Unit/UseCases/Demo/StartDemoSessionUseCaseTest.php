<?php

use App\Domain\Demo\DemoRole;
use App\Domain\Demo\SampleEpisode;
use App\Domain\Demo\SampleWork;
use App\Domain\Demo\SampleWorkSourceInterface;
use App\Domain\Episode\EmptyEpisodeBodyException;
use App\Domain\Episode\Episode;
use App\Domain\Episode\EpisodeStatus;
use App\Domain\Shared\UuidGeneratorInterface;
use App\Domain\Work\Genre;
use App\Domain\Work\RatingFlag;
use App\Domain\Work\WorkStatus;
use App\UseCases\Demo\StartDemoSessionUseCase;
use App\UseCases\Work\ImportWorkUseCase;
use Tests\Doubles\FixedClock;
use Tests\Doubles\InMemoryAccessTokens;
use Tests\Doubles\InMemoryEpisodeRepository;
use Tests\Doubles\InMemoryPublicPageCache;
use Tests\Doubles\InMemoryTransaction;
use Tests\Doubles\InMemoryUserRepository;
use Tests\Doubles\InMemoryWorkRepository;
use Tests\Doubles\PrefixPasswordHasher;

beforeEach(function () {
    $this->users = new InMemoryUserRepository;
    $this->tokens = new InMemoryAccessTokens;
    $this->works = new InMemoryWorkRepository;
    $this->episodes = new InMemoryEpisodeRepository($this->works);
    $transaction = new InMemoryTransaction([$this->users, $this->tokens, $this->works, $this->episodes]);
    $clock = new FixedClock(new DateTimeImmutable('2026-10-22 12:00:00'));

    $uuids = new class implements UuidGeneratorInterface
    {
        /** @var list<string> generate が先頭から順に返す値 */
        public array $queue = [
            'a1b2c3d4-0000-4000-8000-000000000001',
            'e5f6a7b8-0000-4000-8000-000000000002',
        ];

        public function generate(): string
        {
            return array_shift($this->queue) ?? throw new LogicException('用意した UUID を使い切った');
        }
    };

    $this->sampleWorks = new class implements SampleWorkSourceInterface
    {
        public SampleWork $sampleWork;

        public function load(): SampleWork
        {
            return $this->sampleWork;
        }
    };
    $this->sampleWorks->sampleWork = new SampleWork(
        title: 'はじめての連載(デモ)',
        catchphrase: '書き換えて、公開してみてください',
        introduction: 'デモ作者のために用意した作品です。',
        genre: Genre::Drama,
        status: WorkStatus::Ongoing,
        ratingFlags: [RatingFlag::Violence],
        tags: ['デモ'],
        episodes: [
            new SampleEpisode('第1話', '本文1', new DateInterval('P1D')),
            new SampleEpisode('第2話', '本文2', new DateInterval('PT1H')),
            new SampleEpisode('第3話', '本文3', null),
        ],
    );

    $this->startDemoSession = new StartDemoSessionUseCase(
        $this->users,
        new PrefixPasswordHasher,
        $this->tokens,
        $transaction,
        $uuids,
        $this->sampleWorks,
        new ImportWorkUseCase($this->works, $this->episodes, $transaction, $clock, new InMemoryPublicPageCache),
        $clock,
    );
});

it('デモ作者は UUID から作ったメールアドレスと名前で作られ、トークンを受け取る', function () {
    $session = ($this->startDemoSession)(DemoRole::Author);

    $user = $session->authenticated->user;
    expect($user->email)->toBe('demo-a1b2c3d4-0000-4000-8000-000000000001@example.com')
        ->and($user->name)->toBe('デモ作者-a1b2')
        ->and($this->users->findById($user->id))->toEqual($user)
        ->and($this->tokens->issued[$session->authenticated->token->token])->toBe($user->id);
});

it('デモ読者は「デモ読者-」で始まる名前で作られる', function () {
    $session = ($this->startDemoSession)(DemoRole::Reader);

    expect($session->authenticated->user->name)->toBe('デモ読者-a1b2');
});

it('パスワードはデモごとに違うランダムな値をハッシュにして保存する', function () {
    $first = ($this->startDemoSession)(DemoRole::Reader)->authenticated->user;
    $second = ($this->startDemoSession)(DemoRole::Reader)->authenticated->user;

    $firstHash = $this->users->rows[$first->email]['hashedPassword'];
    $secondHash = $this->users->rows[$second->email]['hashedPassword'];
    expect($firstHash)->toStartWith('hashed:')
        ->and(strlen($firstHash))->toBeGreaterThan(strlen('hashed:') + 16)
        ->and($firstHash)->not->toBe($secondHash);
});

it('デモ作者には初期作品を作り、その ID を返す', function () {
    $session = ($this->startDemoSession)(DemoRole::Author);

    $work = $this->works->rows[$session->sampleWorkId];
    expect($work->userId)->toBe($session->authenticated->user->id)
        ->and($work->title)->toBe('はじめての連載(デモ)')
        ->and($work->catchphrase)->toBe('書き換えて、公開してみてください')
        ->and($work->introduction)->toBe('デモ作者のために用意した作品です。')
        ->and($work->genre)->toBe(Genre::Drama)
        ->and($work->status)->toBe(WorkStatus::Ongoing)
        ->and($work->ratingFlags)->toBe([RatingFlag::Violence])
        ->and($work->tags)->toBe(['デモ']);
});

it('初期作品の話の公開日時は、デモを始めた時刻から指定した分だけ前になる', function () {
    $session = ($this->startDemoSession)(DemoRole::Author);

    $episodes = array_values(array_filter(
        $this->episodes->rows,
        fn (Episode $episode) => $episode->workId === $session->sampleWorkId,
    ));
    expect(array_map(fn (Episode $episode) => [$episode->title, $episode->body, $episode->status, $episode->publishedAt], $episodes))
        ->toEqual([
            ['第1話', '本文1', EpisodeStatus::Published, new DateTimeImmutable('2026-10-21 12:00:00')],
            ['第2話', '本文2', EpisodeStatus::Published, new DateTimeImmutable('2026-10-22 11:00:00')],
            ['第3話', '本文3', EpisodeStatus::Draft, null],
        ]);
});

it('デモ読者には作品を作らず、作品の ID は null を返す', function () {
    $session = ($this->startDemoSession)(DemoRole::Reader);

    expect($session->sampleWorkId)->toBeNull()
        ->and($this->works->rows)->toBe([]);
});

it('初期作品を作れなければ、ユーザーもトークンも残さない', function () {
    $this->sampleWorks->sampleWork = new SampleWork(
        title: 'はじめての連載(デモ)',
        catchphrase: null,
        introduction: 'デモ作者のために用意した作品です。',
        genre: Genre::Drama,
        status: WorkStatus::Ongoing,
        ratingFlags: [],
        tags: [],
        episodes: [new SampleEpisode('第1話', '  ', new DateInterval('P1D'))],
    );

    expect(fn () => ($this->startDemoSession)(DemoRole::Author))->toThrow(EmptyEpisodeBodyException::class);

    expect($this->users->rows)->toBe([])
        ->and($this->tokens->issued)->toBe([])
        ->and($this->works->rows)->toBe([]);
});
