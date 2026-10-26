<?php

use App\Domain\Episode\Episode;
use App\Domain\Episode\EpisodeNotFoundException;
use App\Domain\Episode\EpisodeStatus;
use App\Domain\Work\Genre;
use App\Domain\Work\Work;
use App\Domain\Work\WorkNotFoundException;
use App\Domain\Work\WorkStatus;
use App\UseCases\PublicWork\GetPublishedEpisodeUseCase;
use App\UseCases\PublicWork\GetPublishedWorkUseCase;
use Tests\Doubles\InMemoryEpisodeRepository;
use Tests\Doubles\InMemoryUserRepository;
use Tests\Doubles\InMemoryWorkRepository;

beforeEach(function () {
    $this->users = new InMemoryUserRepository;
    $this->works = new InMemoryWorkRepository;
    $this->episodes = new InMemoryEpisodeRepository($this->works);

    $this->author = $this->users->create('図書委員', 'author@example.com', 'hashed');

    $this->addWork = function (string $id, ?DateTimeImmutable $publishedAt): Work {
        return $this->works->rows[$id] = new Work(
            id: $id,
            userId: $this->author->id,
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

    $this->addEpisode = function (string $id, string $workId, int $position, bool $published): Episode {
        return $this->episodes->rows[$id] = new Episode(
            id: $id,
            workId: $workId,
            title: "第{$position}話",
            body: '本文',
            position: $position,
            status: $published ? EpisodeStatus::Published : EpisodeStatus::Draft,
            publishedAt: $published ? new DateTimeImmutable('2026-10-01 09:00:00') : null,
        );
    };

    $this->getWork = new GetPublishedWorkUseCase($this->works, $this->users, $this->episodes);
    $this->getEpisode = new GetPublishedEpisodeUseCase($this->works, $this->users, $this->episodes);
});

describe('GetPublishedWorkUseCase', function () {
    it('公開済みの作品を、作者名と公開済みの話だけの目次を付けて返す', function () {
        $work = ($this->addWork)('work-1', new DateTimeImmutable('2026-10-01'));
        ($this->addEpisode)('episode-3', 'work-1', 3, published: true);
        ($this->addEpisode)('episode-2', 'work-1', 2, published: false);
        ($this->addEpisode)('episode-1', 'work-1', 1, published: true);

        $published = ($this->getWork)('work-1');

        expect($published->work)->toBe($work)
            ->and($published->authorName)->toBe('図書委員')
            ->and(array_map(fn ($episode) => $episode->id, $published->episodes))->toBe(['episode-1', 'episode-3']);
    });

    it('未公開の作品なら WorkNotFoundException を投げる', function () {
        ($this->addWork)('work-1', null);

        ($this->getWork)('work-1');
    })->throws(WorkNotFoundException::class);

    it('存在しない作品なら WorkNotFoundException を投げる', function () {
        ($this->getWork)('missing');
    })->throws(WorkNotFoundException::class);
});

describe('GetPublishedEpisodeUseCase', function () {
    beforeEach(function () {
        ($this->addWork)('work-1', new DateTimeImmutable('2026-10-01'));
    });

    it('前後の話は、間の下書きを飛ばした公開済みの話にする', function () {
        ($this->addEpisode)('episode-1', 'work-1', 1, published: true);
        ($this->addEpisode)('episode-2', 'work-1', 2, published: false);
        $current = ($this->addEpisode)('episode-3', 'work-1', 3, published: true);
        ($this->addEpisode)('episode-4', 'work-1', 4, published: false);
        ($this->addEpisode)('episode-5', 'work-1', 5, published: true);

        $published = ($this->getEpisode)('work-1', 'episode-3');

        expect($published->episode)->toBe($current)
            ->and($published->work->id)->toBe('work-1')
            ->and($published->authorName)->toBe('図書委員')
            ->and($published->previous?->id)->toBe('episode-1')
            ->and($published->next?->id)->toBe('episode-5');
    });

    it('前や次に公開済みの話が無ければ null にする', function () {
        ($this->addEpisode)('episode-1', 'work-1', 1, published: false);
        ($this->addEpisode)('episode-2', 'work-1', 2, published: true);
        ($this->addEpisode)('episode-3', 'work-1', 3, published: false);

        $published = ($this->getEpisode)('work-1', 'episode-2');

        expect($published->previous)->toBeNull()
            ->and($published->next)->toBeNull();
    });

    it('下書きの話なら EpisodeNotFoundException を投げる', function () {
        ($this->addEpisode)('episode-1', 'work-1', 1, published: false);

        ($this->getEpisode)('work-1', 'episode-1');
    })->throws(EpisodeNotFoundException::class);

    it('別の作品の話なら EpisodeNotFoundException を投げる', function () {
        ($this->addWork)('work-2', new DateTimeImmutable('2026-10-01'));
        ($this->addEpisode)('episode-1', 'work-2', 1, published: true);

        ($this->getEpisode)('work-1', 'episode-1');
    })->throws(EpisodeNotFoundException::class);

    it('未公開の作品の話なら WorkNotFoundException を投げる', function () {
        ($this->addWork)('work-2', null);
        ($this->addEpisode)('episode-1', 'work-2', 1, published: true);

        ($this->getEpisode)('work-2', 'episode-1');
    })->throws(WorkNotFoundException::class);
});
