<?php

use App\Infrastructure\Eloquent\Models\User;
use App\Infrastructure\Eloquent\Models\Work;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->work = Work::factory()->state(['user_id' => $this->user->id])->create([
        'title' => '元のタイトル',
        'catchphrase' => '元のキャッチコピー',
        'introduction' => '元のあらすじ',
        'genre' => 'fantasy',
        'status' => 'ongoing',
        'rating_flags' => ['violence'],
    ]);
});

it('送った項目だけを変え、ほかの項目は変えない', function () {
    $this->withToken($this->user->createToken('web')->plainTextToken)
        ->patchJson("/api/my/works/{$this->work->id}", ['title' => '新しいタイトル', 'status' => 'completed'])
        ->assertOk()
        ->assertJsonPath('data.title', '新しいタイトル')
        ->assertJsonPath('data.status', 'completed')
        ->assertJsonPath('data.catchphrase', '元のキャッチコピー');

    $work = $this->work->fresh();
    expect($work->title)->toBe('新しいタイトル')
        ->and($work->status)->toBe('completed')
        ->and($work->catchphrase)->toBe('元のキャッチコピー')
        ->and($work->introduction)->toBe('元のあらすじ')
        ->and($work->genre)->toBe('fantasy')
        ->and($work->rating_flags)->toBe(['violence']);
});

it('catchphrase に null を送ると消し、rating_flags に空の配列を送ると外す', function () {
    $this->withToken($this->user->createToken('web')->plainTextToken)
        ->patchJson("/api/my/works/{$this->work->id}", ['catchphrase' => null, 'rating_flags' => []])
        ->assertOk();

    $work = $this->work->fresh();
    expect($work->catchphrase)->toBeNull()
        ->and($work->rating_flags)->toBe([]);
});

it('変える項目が無ければ updated_at を変えない', function (array $payload) {
    $updatedAt = $this->work->updated_at;
    $this->travel(1)->days();

    $this->withToken($this->user->createToken('web')->plainTextToken)
        ->patchJson("/api/my/works/{$this->work->id}", $payload)
        ->assertOk();

    expect($this->work->fresh()->updated_at)->toEqual($updatedAt);
})->with([
    '空' => [[]],
    '今と同じ値' => [['title' => '元のタイトル', 'rating_flags' => ['violence']]],
]);

it('user_id・published_at・last_episode_published_at を送っても変えない', function () {
    $other = User::factory()->create();

    $this->withToken($this->user->createToken('web')->plainTextToken)
        ->patchJson("/api/my/works/{$this->work->id}", [
            'user_id' => $other->id,
            'published_at' => '2026-01-01T00:00:00+09:00',
            'last_episode_published_at' => '2026-01-01T00:00:00+09:00',
        ])
        ->assertOk();

    $work = $this->work->fresh();
    expect($work->user_id)->toBe($this->user->id)
        ->and($work->published_at)->toBeNull()
        ->and($work->last_episode_published_at)->toBeNull();
});

it('必須の項目を空にすると 422 を返し、変えない', function (string $field) {
    $this->withToken($this->user->createToken('web')->plainTextToken)
        ->patchJson("/api/my/works/{$this->work->id}", [$field => null])
        ->assertUnprocessable()
        ->assertOnlyJsonValidationErrors([$field]);

    expect($this->work->fresh()->{$field})->toBe($this->work->{$field});
})->with(['title', 'introduction', 'genre', 'status', 'rating_flags']);

it('定義外の値や上限を超える文字数なら 422 を返す', function (array $payload, string $field) {
    $this->withToken($this->user->createToken('web')->plainTextToken)
        ->patchJson("/api/my/works/{$this->work->id}", $payload)
        ->assertUnprocessable()
        ->assertOnlyJsonValidationErrors([$field]);
})->with([
    'genre' => [['genre' => 'isekai'], 'genre'],
    'status' => [['status' => 'paused'], 'status'],
    'rating_flags' => [['rating_flags' => ['gore']], 'rating_flags.0'],
    'title' => [['title' => str_repeat('あ', 101)], 'title'],
    'catchphrase' => [['catchphrase' => str_repeat('あ', 36)], 'catchphrase'],
    'introduction' => [['introduction' => str_repeat('あ', 2001)], 'introduction'],
]);

it('他人の未公開の作品なら 404 を返し、変えない', function () {
    $work = Work::factory()->create(['title' => '他人の作品']);

    $this->withToken($this->user->createToken('web')->plainTextToken)
        ->patchJson("/api/my/works/{$work->id}", ['title' => '書き換え'])
        ->assertNotFound();

    expect($work->fresh()->title)->toBe('他人の作品');
});

it('他人の公開済みの作品なら 403 を返し、変えない', function () {
    $work = Work::factory()->published()->create(['title' => '他人の作品']);

    $this->withToken($this->user->createToken('web')->plainTextToken)
        ->patchJson("/api/my/works/{$work->id}", ['title' => '書き換え'])
        ->assertForbidden();

    expect($work->fresh()->title)->toBe('他人の作品');
});

it('存在しない ID と UUID でない ID なら 404 を返す', function (string $id) {
    $this->withToken($this->user->createToken('web')->plainTextToken)
        ->patchJson("/api/my/works/{$id}", ['title' => '書き換え'])
        ->assertNotFound();
})->with([
    '存在しない' => fn () => (string) Str::uuid(),
    'UUID でない' => 'not-a-uuid',
]);

it('未ログインなら 401 を返す', function () {
    $this->patchJson("/api/my/works/{$this->work->id}", ['title' => '書き換え'])
        ->assertUnauthorized();

    expect($this->work->fresh()->title)->toBe('元のタイトル');
});
