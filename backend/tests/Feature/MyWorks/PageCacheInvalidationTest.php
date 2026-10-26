<?php

use App\Infrastructure\Eloquent\Models\EpisodeModel;
use App\Infrastructure\Eloquent\Models\UserModel;
use App\Infrastructure\Eloquent\Models\WorkModel;
use App\Infrastructure\PageCache\RevalidateNextCache;
use Illuminate\Queue\Events\JobQueued;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    $this->user = UserModel::factory()->create();
    $this->token = $this->user->createToken('web')->plainTextToken;
    $this->work = WorkModel::factory()->state(['user_id' => $this->user->id])->create();
    $this->publishWork = fn () => $this->work->forceFill(['published_at' => '2026-10-01 09:00:00', 'last_episode_published_at' => '2026-10-01 09:00:00'])->save();
    $this->publishedEpisode = fn () => EpisodeModel::factory()
        ->state(['work_id' => $this->work->id, 'status' => 'published', 'published_at' => '2026-10-01 09:00:00'])
        ->create();
    $this->queuedTags = fn () => Queue::pushed(RevalidateNextCache::class)->map(fn (RevalidateNextCache $job) => $job->tags)->all();
});

describe('破棄を頼む', function () {
    beforeEach(fn () => Queue::fake());

    it('話を公開すると、作品・話・トップの破棄を積む', function () {
        $episode = EpisodeModel::factory()->state(['work_id' => $this->work->id])->create();

        $this->withToken($this->token)
            ->postJson("/api/my/works/{$this->work->id}/episodes/{$episode->id}/publish")
            ->assertOk();

        expect(($this->queuedTags)())->toBe([["work:{$this->work->id}", "episode:{$episode->id}", 'top']]);
    });

    it('話を非公開にすると、作品・話・トップの破棄を積む', function () {
        ($this->publishWork)();
        $episode = ($this->publishedEpisode)();

        $this->withToken($this->token)
            ->postJson("/api/my/works/{$this->work->id}/episodes/{$episode->id}/unpublish")
            ->assertOk();

        expect(($this->queuedTags)())->toBe([["work:{$this->work->id}", "episode:{$episode->id}", 'top']]);
    });

    it('公開済みの話を更新すると、作品・話・トップの破棄を積む', function () {
        ($this->publishWork)();
        $episode = ($this->publishedEpisode)();

        $this->withToken($this->token)
            ->patchJson("/api/my/works/{$this->work->id}/episodes/{$episode->id}", ['title' => '直したタイトル', 'body' => '直した本文'])
            ->assertOk();

        expect(($this->queuedTags)())->toBe([["work:{$this->work->id}", "episode:{$episode->id}", 'top']]);
    });

    it('公開済みの作品を更新すると、作品・トップの破棄を積む', function () {
        ($this->publishWork)();

        $this->withToken($this->token)
            ->patchJson("/api/my/works/{$this->work->id}", ['title' => '直したタイトル'])
            ->assertOk();

        expect(($this->queuedTags)())->toBe([["work:{$this->work->id}", 'top']]);
    });
});

describe('破棄を頼まない', function () {
    beforeEach(fn () => Queue::fake());

    it('下書きの話を更新しても積まない', function () {
        ($this->publishWork)();
        $episode = EpisodeModel::factory()->state(['work_id' => $this->work->id])->create();

        $this->withToken($this->token)
            ->patchJson("/api/my/works/{$this->work->id}/episodes/{$episode->id}", ['body' => '直した本文'])
            ->assertOk();

        Queue::assertNothingPushed();
    });

    it('未公開の作品を更新しても積まない', function () {
        $this->withToken($this->token)
            ->patchJson("/api/my/works/{$this->work->id}", ['title' => '直したタイトル'])
            ->assertOk();

        Queue::assertNothingPushed();
    });

    it('公開できなければ積まない', function () {
        $episode = EpisodeModel::factory()->state(['work_id' => $this->work->id, 'body' => ''])->create();

        $this->withToken($this->token)
            ->postJson("/api/my/works/{$this->work->id}/episodes/{$episode->id}/publish")
            ->assertUnprocessable();

        Queue::assertNothingPushed();
    });
});

it('破棄はトランザクションのコミット後に積む', function () {
    $episode = EpisodeModel::factory()->state(['work_id' => $this->work->id])->create();
    // RefreshDatabase がテスト全体をトランザクションで包むので、その深さを基準にする
    $outside = DB::transactionLevel();
    $levels = [];
    Event::listen(JobQueued::class, function () use (&$levels) {
        $levels[] = DB::transactionLevel();
    });

    $this->withToken($this->token)
        ->postJson("/api/my/works/{$this->work->id}/episodes/{$episode->id}/publish")
        ->assertOk();

    expect($levels)->toBe([$outside]);
});

it('破棄の依頼が失敗しても、話は公開されたまま', function () {
    config(['services.next.url' => 'http://web.test', 'services.next.revalidate_secret' => 'test-secret']);
    Http::fake(['*' => Http::response(null, 500)]);
    $episode = EpisodeModel::factory()->state(['work_id' => $this->work->id])->create();

    $this->withToken($this->token)
        ->postJson("/api/my/works/{$this->work->id}/episodes/{$episode->id}/publish")
        ->assertOk();
    $this->artisan('queue:work', ['--once' => true]);

    Http::assertSentCount(1);
    $this->assertDatabaseHas('episodes', ['id' => $episode->id, 'status' => 'published']);
});
