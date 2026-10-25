<?php

use App\Domain\Episode\EmptyEpisodeBodyException;
use App\Domain\Episode\Episode;
use App\Domain\Episode\EpisodeStatus;
use App\Domain\Episode\InvalidEpisodeTransitionException;

beforeEach(function () {
    $this->episode = fn (EpisodeStatus $status, string $body = '本文', ?DateTimeImmutable $publishedAt = null) => new Episode(
        id: 'episode-1',
        workId: 'work-1',
        title: '第一話',
        body: $body,
        position: 1,
        status: $status,
        publishedAt: $publishedAt,
    );
});

describe('publish', function () {
    it('下書きを公開済みにし、公開日時を記録した新しい話を返す', function () {
        $draft = ($this->episode)(EpisodeStatus::Draft);
        $at = new DateTimeImmutable('2026-10-22 12:00:00');

        $published = $draft->publish($at);

        expect($published->status)->toBe(EpisodeStatus::Published)
            ->and($published->publishedAt)->toBe($at)
            ->and($published->id)->toBe('episode-1')
            ->and($published->body)->toBe('本文')
            ->and($draft->status)->toBe(EpisodeStatus::Draft);
    });

    it('公開済みの話なら InvalidEpisodeTransitionException を投げる', function () {
        $published = ($this->episode)(EpisodeStatus::Published, publishedAt: new DateTimeImmutable('2026-10-01'));

        expect(fn () => $published->publish(new DateTimeImmutable))->toThrow(InvalidEpisodeTransitionException::class);
    });

    it('本文が空白だけなら EmptyEpisodeBodyException を投げる', function (string $body) {
        expect(fn () => ($this->episode)(EpisodeStatus::Draft, $body)->publish(new DateTimeImmutable))
            ->toThrow(EmptyEpisodeBodyException::class);
    })->with([
        '空文字' => '',
        '半角空白と改行' => " \n\t\r\n",
        '全角空白' => '　　',
        '全角空白と改行' => "　\n　",
    ]);

    it('空白のほかに 1 文字でもあれば公開する', function () {
        expect(($this->episode)(EpisodeStatus::Draft, "　\nあ\n")->publish(new DateTimeImmutable)->status)
            ->toBe(EpisodeStatus::Published);
    });
});

describe('assertBodyAcceptable', function () {
    it('公開済みの話の本文を空白だけにするなら EmptyEpisodeBodyException を投げる', function () {
        $published = ($this->episode)(EpisodeStatus::Published, publishedAt: new DateTimeImmutable('2026-10-01'));

        expect(fn () => $published->assertBodyAcceptable("　\n"))->toThrow(EmptyEpisodeBodyException::class);
    });

    it('公開済みの話でも空白のほかの文字があれば受け入れる', function () {
        $published = ($this->episode)(EpisodeStatus::Published, publishedAt: new DateTimeImmutable('2026-10-01'));

        $published->assertBodyAcceptable('新しい本文');
    })->throwsNoExceptions();

    it('下書きなら空の本文も受け入れる', function () {
        ($this->episode)(EpisodeStatus::Draft)->assertBodyAcceptable('');
    })->throwsNoExceptions();
});

describe('unpublish', function () {
    it('公開済みの話を下書きに戻し、公開日時を消した新しい話を返す', function () {
        $published = ($this->episode)(EpisodeStatus::Published, publishedAt: new DateTimeImmutable('2026-10-01'));

        $draft = $published->unpublish();

        expect($draft->status)->toBe(EpisodeStatus::Draft)
            ->and($draft->publishedAt)->toBeNull()
            ->and($published->status)->toBe(EpisodeStatus::Published);
    });

    it('下書きなら InvalidEpisodeTransitionException を投げる', function () {
        expect(fn () => ($this->episode)(EpisodeStatus::Draft)->unpublish())->toThrow(InvalidEpisodeTransitionException::class);
    });
});

describe('withChanges', function () {
    it('渡した項目だけを置き換えた新しい話を返す', function () {
        $episode = ($this->episode)(EpisodeStatus::Draft);

        $changed = $episode->withChanges(['title' => '新しいタイトル']);

        expect($changed->title)->toBe('新しいタイトル')
            ->and($changed->body)->toBe('本文')
            ->and($changed->id)->toBe('episode-1')
            ->and($episode->title)->toBe('第一話');
    });
});
