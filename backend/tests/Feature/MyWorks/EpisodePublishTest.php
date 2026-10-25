<?php

use App\Infrastructure\Eloquent\Models\Episode;
use App\Infrastructure\Eloquent\Models\User;
use App\Infrastructure\Eloquent\Models\Work;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->token = $this->user->createToken('web')->plainTextToken;
    $this->work = Work::factory()->state(['user_id' => $this->user->id])->create();
    $this->publishedEpisode = fn (Work $work, string $publishedAt) => Episode::factory()
        ->state(['work_id' => $work->id, 'status' => 'published', 'published_at' => $publishedAt])
        ->create();
    $this->travelTo(Carbon::parse('2026-10-22 12:00:00'));
});

describe('公開', function () {
    it('下書きを公開して公開日時を記録し、未公開の作品も同じ時刻で公開する', function () {
        $episode = Episode::factory()->state(['work_id' => $this->work->id])->create();

        $this->withToken($this->token)
            ->postJson("/api/my/works/{$this->work->id}/episodes/{$episode->id}/publish")
            ->assertOk()
            ->assertJsonPath('data.id', $episode->id)
            ->assertJsonPath('data.status', 'published')
            ->assertJsonPath('data.published_at', '2026-10-22T12:00:00+00:00')
            ->assertJsonPath('data.public_path', "/works/{$this->work->id}/episodes/{$episode->id}");

        $this->assertDatabaseHas('episodes', [
            'id' => $episode->id,
            'status' => 'published',
            'published_at' => '2026-10-22 12:00:00',
        ]);
        $this->assertDatabaseHas('works', [
            'id' => $this->work->id,
            'published_at' => '2026-10-22 12:00:00',
            'last_episode_published_at' => '2026-10-22 12:00:00',
        ]);
    });

    it('2 話目の公開では作品の公開日時を変えず、最後に話を公開した日時だけを変える', function () {
        $this->work->forceFill(['published_at' => '2026-10-01 09:00:00', 'last_episode_published_at' => '2026-10-01 09:00:00'])->save();
        ($this->publishedEpisode)($this->work, '2026-10-01 09:00:00');
        $second = Episode::factory()->state(['work_id' => $this->work->id])->create();

        $this->withToken($this->token)
            ->postJson("/api/my/works/{$this->work->id}/episodes/{$second->id}/publish")
            ->assertOk();

        $this->assertDatabaseHas('works', [
            'id' => $this->work->id,
            'published_at' => '2026-10-01 09:00:00',
            'last_episode_published_at' => '2026-10-22 12:00:00',
        ]);
    });

    it('本文が空白だけなら 422 を返し、公開しない', function (string $body) {
        $episode = Episode::factory()->state(['work_id' => $this->work->id, 'body' => $body])->create();

        $this->withToken($this->token)
            ->postJson("/api/my/works/{$this->work->id}/episodes/{$episode->id}/publish")
            ->assertUnprocessable()
            ->assertOnlyJsonValidationErrors(['body']);

        expect($episode->fresh()->status)->toBe('draft')
            ->and($this->work->fresh()->published_at)->toBeNull();
    })->with([
        '空文字' => '',
        '全角空白と改行だけ' => "　　\n　\n",
    ]);

    it('公開済みの話なら 422 を返し、公開日時を変えない', function () {
        $this->work->forceFill(['published_at' => '2026-10-01 09:00:00', 'last_episode_published_at' => '2026-10-01 09:00:00'])->save();
        $episode = ($this->publishedEpisode)($this->work, '2026-10-01 09:00:00');

        $this->withToken($this->token)
            ->postJson("/api/my/works/{$this->work->id}/episodes/{$episode->id}/publish")
            ->assertUnprocessable()
            ->assertOnlyJsonValidationErrors(['status']);

        expect($episode->fresh()->published_at->toDateTimeString())->toBe('2026-10-01 09:00:00')
            ->and($this->work->fresh()->last_episode_published_at->toDateTimeString())->toBe('2026-10-01 09:00:00');
    });
});

describe('非公開化', function () {
    it('下書きに戻し、最後に話を公開した日時を残りの公開済みの話の最大値にする', function () {
        $this->work->forceFill(['published_at' => '2026-10-01 09:00:00', 'last_episode_published_at' => '2026-10-10 09:00:00'])->save();
        ($this->publishedEpisode)($this->work, '2026-10-01 09:00:00');
        ($this->publishedEpisode)($this->work, '2026-10-05 09:00:00');
        $latest = ($this->publishedEpisode)($this->work, '2026-10-10 09:00:00');
        Episode::factory()->state(['work_id' => $this->work->id])->create();
        ($this->publishedEpisode)(Work::factory()->state(['user_id' => $this->user->id])->published()->create(), '2026-10-20 09:00:00');

        $this->withToken($this->token)
            ->postJson("/api/my/works/{$this->work->id}/episodes/{$latest->id}/unpublish")
            ->assertOk()
            ->assertJsonPath('data.id', $latest->id)
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.published_at', null)
            ->assertJsonPath('data.public_path', null);

        $this->assertDatabaseHas('episodes', ['id' => $latest->id, 'status' => 'draft', 'published_at' => null]);
        $this->assertDatabaseHas('works', [
            'id' => $this->work->id,
            'published_at' => '2026-10-01 09:00:00',
            'last_episode_published_at' => '2026-10-05 09:00:00',
        ]);
    });

    it('公開済みの話が残らなければ、最後に話を公開した日時を null にし、作品の公開日時は残す', function () {
        $this->work->forceFill(['published_at' => '2026-10-01 09:00:00', 'last_episode_published_at' => '2026-10-01 09:00:00'])->save();
        $episode = ($this->publishedEpisode)($this->work, '2026-10-01 09:00:00');

        $this->withToken($this->token)
            ->postJson("/api/my/works/{$this->work->id}/episodes/{$episode->id}/unpublish")
            ->assertOk();

        $this->assertDatabaseHas('works', [
            'id' => $this->work->id,
            'published_at' => '2026-10-01 09:00:00',
            'last_episode_published_at' => null,
        ]);
    });

    it('下書きの話なら 422 を返す', function () {
        $episode = Episode::factory()->state(['work_id' => $this->work->id])->create();

        $this->withToken($this->token)
            ->postJson("/api/my/works/{$this->work->id}/episodes/{$episode->id}/unpublish")
            ->assertUnprocessable()
            ->assertOnlyJsonValidationErrors(['status']);

        expect($episode->fresh()->status)->toBe('draft');
    });
});

describe('公開済みの話の更新', function () {
    beforeEach(function () {
        $this->work->forceFill(['published_at' => '2026-10-01 09:00:00', 'last_episode_published_at' => '2026-10-01 09:00:00'])->save();
        $this->episode = ($this->publishedEpisode)($this->work, '2026-10-01 09:00:00');
    });

    it('本文を空白だけにするなら 422 を返し、変えない', function (?string $body) {
        $original = $this->episode->body;

        $this->withToken($this->token)
            ->patchJson("/api/my/works/{$this->work->id}/episodes/{$this->episode->id}", ['title' => '書き換え', 'body' => $body])
            ->assertUnprocessable()
            ->assertOnlyJsonValidationErrors(['body']);

        expect($this->episode->fresh()->body)->toBe($original)
            ->and($this->episode->fresh()->title)->not->toBe('書き換え');
    })->with([
        '空文字' => '',
        'null' => null,
        '全角空白と改行だけ' => "　\n",
    ]);

    it('本文を空白でない文字を含む本文に変えられる', function () {
        $this->withToken($this->token)
            ->patchJson("/api/my/works/{$this->work->id}/episodes/{$this->episode->id}", ['body' => '新しい本文'])
            ->assertOk()
            ->assertJsonPath('data.body', '新しい本文')
            ->assertJsonPath('data.status', 'published');
    });
});

// 他人の作品は、話の状態によらず話を探す前に作品の権限で断る
describe('他人の作品', function () {
    $actions = ['公開' => ['publish', 'draft'], '非公開化' => ['unpublish', 'published']];

    it('公開済みの作品なら 403 を返し、話を変えない', function (string $action, string $status) {
        $work = Work::factory()->published()->create();
        $episode = Episode::factory()->state(['work_id' => $work->id, 'status' => $status])->create();

        $this->withToken($this->token)
            ->postJson("/api/my/works/{$work->id}/episodes/{$episode->id}/{$action}")
            ->assertForbidden();

        expect($episode->fresh()->status)->toBe($status);
    })->with($actions);

    it('未公開の作品なら、存在を隠して 404 を返し、話を変えない', function (string $action, string $status) {
        $work = Work::factory()->create();
        $episode = Episode::factory()->state(['work_id' => $work->id, 'status' => $status])->create();

        $this->withToken($this->token)
            ->postJson("/api/my/works/{$work->id}/episodes/{$episode->id}/{$action}")
            ->assertNotFound();

        expect($episode->fresh()->status)->toBe($status);
    })->with($actions);
});

describe('見つからない話', function () {
    it('自分の別の作品の話 ID なら 404 を返し、変えない', function (string $action, string $status) {
        $other = Work::factory()->state(['user_id' => $this->user->id])->create();
        $episode = Episode::factory()->state(['work_id' => $other->id, 'status' => $status])->create();

        $this->withToken($this->token)
            ->postJson("/api/my/works/{$this->work->id}/episodes/{$episode->id}/{$action}")
            ->assertNotFound();

        expect($episode->fresh()->status)->toBe($status);
    })->with(['公開' => ['publish', 'draft'], '非公開化' => ['unpublish', 'published']]);

    it('存在しない話 ID と UUID でない話 ID なら 404 を返す', function (string $action, string $episodeId) {
        $this->withToken($this->token)
            ->postJson("/api/my/works/{$this->work->id}/episodes/{$episodeId}/{$action}")
            ->assertNotFound();
    })->with(['publish', 'unpublish'])->with([
        '存在しない' => fn () => (string) Str::uuid(),
        'UUID でない' => 'not-a-uuid',
    ]);
});

it('未ログインなら 401 を返し、変えない', function (string $action) {
    $episode = Episode::factory()->state(['work_id' => $this->work->id])->create();

    $this->postJson("/api/my/works/{$this->work->id}/episodes/{$episode->id}/{$action}")->assertUnauthorized();

    expect($episode->fresh()->status)->toBe('draft');
})->with(['publish', 'unpublish']);
