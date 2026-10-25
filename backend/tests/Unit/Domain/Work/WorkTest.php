<?php

use App\Domain\Work\Genre;
use App\Domain\Work\Work;
use App\Domain\Work\WorkStatus;

beforeEach(function () {
    $this->work = fn (?DateTimeImmutable $publishedAt, ?DateTimeImmutable $lastEpisodePublishedAt) => new Work(
        id: 'work-1',
        userId: 'user-1',
        title: '図書委員の異世界日誌',
        catchphrase: null,
        introduction: '本の虫が異世界の図書館で働く話。',
        genre: Genre::Fantasy,
        status: WorkStatus::Ongoing,
        ratingFlags: [],
        tags: [],
        publishedAt: $publishedAt,
        lastEpisodePublishedAt: $lastEpisodePublishedAt,
        createdAt: new DateTimeImmutable('2026-09-01'),
    );
});

describe('recordEpisodePublished', function () {
    it('未公開の作品なら、作品の公開日時と最後に話を公開した日時を記録する', function () {
        $at = new DateTimeImmutable('2026-10-22 12:00:00');

        $recorded = ($this->work)(null, null)->recordEpisodePublished($at);

        expect($recorded->publishedAt)->toBe($at)
            ->and($recorded->lastEpisodePublishedAt)->toBe($at)
            ->and($recorded->title)->toBe('図書委員の異世界日誌');
    });

    it('公開済みの作品なら、作品の公開日時を変えない', function () {
        $workPublishedAt = new DateTimeImmutable('2026-10-01');
        $at = new DateTimeImmutable('2026-10-22 12:00:00');

        $recorded = ($this->work)($workPublishedAt, $workPublishedAt)->recordEpisodePublished($at);

        expect($recorded->publishedAt)->toBe($workPublishedAt)
            ->and($recorded->lastEpisodePublishedAt)->toBe($at);
    });

    it('記録済みの日時より前の時刻なら、最後に話を公開した日時を戻さない', function () {
        $workPublishedAt = new DateTimeImmutable('2026-10-01');
        $lastEpisodePublishedAt = new DateTimeImmutable('2026-10-22 12:00:05');

        $recorded = ($this->work)($workPublishedAt, $lastEpisodePublishedAt)
            ->recordEpisodePublished(new DateTimeImmutable('2026-10-22 12:00:03'));

        expect($recorded->lastEpisodePublishedAt)->toBe($lastEpisodePublishedAt);
    });
});

describe('withLastEpisodePublishedAt', function () {
    it('最後に話を公開した日時だけを置き換える', function (?DateTimeImmutable $at) {
        $workPublishedAt = new DateTimeImmutable('2026-10-01');

        $changed = ($this->work)($workPublishedAt, new DateTimeImmutable('2026-10-22'))->withLastEpisodePublishedAt($at);

        expect($changed->lastEpisodePublishedAt)->toBe($at)
            ->and($changed->publishedAt)->toBe($workPublishedAt);
    })->with([
        '日時' => [new DateTimeImmutable('2026-10-10')],
        'null' => [null],
    ]);
});
