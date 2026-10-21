<?php

use App\Infrastructure\Eloquent\Models\User;
use App\Infrastructure\Eloquent\Models\Work;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->user = User::factory()->create();
});

it('自分の作品を返す', function () {
    $work = Work::factory()->state(['user_id' => $this->user->id])->create([
        'title' => '図書委員の異世界日誌',
        'catchphrase' => '貸出期限は守ってください',
        'genre' => 'fantasy',
        'status' => 'completed',
        'rating_flags' => ['violence'],
        'published_at' => '2026-10-01 12:00:00',
        'last_episode_published_at' => '2026-10-02 12:00:00',
        'created_at' => '2026-09-30 12:00:00',
    ]);

    $this->withToken($this->user->createToken('web')->plainTextToken)
        ->getJson("/api/my/works/{$work->id}")
        ->assertOk()
        ->assertExactJson(['data' => [
            'id' => $work->id,
            'title' => '図書委員の異世界日誌',
            'catchphrase' => '貸出期限は守ってください',
            'introduction' => $work->introduction,
            'genre' => 'fantasy',
            'status' => 'completed',
            'rating_flags' => ['violence'],
            'published_at' => '2026-10-01T12:00:00+00:00',
            'last_episode_published_at' => '2026-10-02T12:00:00+00:00',
            'created_at' => '2026-09-30T12:00:00+00:00',
        ]]);
});

it('他人の未公開の作品なら、存在を隠して 404 を返す', function () {
    $work = Work::factory()->create();

    $this->withToken($this->user->createToken('web')->plainTextToken)
        ->getJson("/api/my/works/{$work->id}")
        ->assertNotFound();
});

it('他人の公開済みの作品なら 403 を返す', function () {
    $work = Work::factory()->published()->create();

    $this->withToken($this->user->createToken('web')->plainTextToken)
        ->getJson("/api/my/works/{$work->id}")
        ->assertForbidden();
});

it('存在しない ID なら 404 を返す', function () {
    $this->withToken($this->user->createToken('web')->plainTextToken)
        ->getJson('/api/my/works/'.Str::uuid())
        ->assertNotFound();
});

it('UUID でない ID なら 404 を返す', function () {
    $this->withToken($this->user->createToken('web')->plainTextToken)
        ->getJson('/api/my/works/not-a-uuid')
        ->assertNotFound();
});

it('未ログインなら 401 を返す', function () {
    $work = Work::factory()->state(['user_id' => $this->user->id])->create();

    $this->getJson("/api/my/works/{$work->id}")->assertUnauthorized();
});
