<?php

use App\Infrastructure\Eloquent\Models\UserModel;
use App\Infrastructure\Eloquent\Models\WorkModel;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->user = UserModel::factory()->create();
    $this->withToken($this->user->createToken('web')->plainTextToken);
});

function workPayload(array $overrides = []): array
{
    return [
        'title' => '転生したら図書委員だった件',
        'introduction' => '本の虫が異世界の図書館で働く話。',
        'genre' => 'fantasy',
        ...$overrides,
    ];
}

it('必須の項目だけで作成すると 201 を返し、既定値で保存する', function () {
    $response = $this->postJson('/api/my/works', workPayload())
        ->assertCreated()
        ->assertJsonPath('data.title', '転生したら図書委員だった件')
        ->assertJsonPath('data.catchphrase', null)
        ->assertJsonPath('data.introduction', '本の虫が異世界の図書館で働く話。')
        ->assertJsonPath('data.genre', 'fantasy')
        ->assertJsonPath('data.status', 'ongoing')
        ->assertJsonPath('data.rating_flags', [])
        ->assertJsonPath('data.published_at', null)
        ->assertJsonPath('data.last_episode_published_at', null);

    $work = WorkModel::sole();
    expect(Str::isUuid($response->json('data.id')))->toBeTrue()
        ->and($work->id)->toBe($response->json('data.id'))
        ->and($work->user_id)->toBe($this->user->id)
        ->and($work->catchphrase)->toBeNull()
        ->and($work->status)->toBe('ongoing')
        ->and($work->rating_flags)->toBe([])
        ->and($work->published_at)->toBeNull()
        ->and($work->last_episode_published_at)->toBeNull();
});

// v7 は先頭が作成時刻なので、主キーのインデックスへの挿入が末尾への追加になる
it('作品の id は UUIDv7 にする', function () {
    $id = $this->postJson('/api/my/works', workPayload())->assertCreated()->json('data.id');

    expect(Str::isUuid($id, 7))->toBeTrue();
});

it('任意の項目も送ると、その値で保存する', function () {
    $this->postJson('/api/my/works', workPayload([
        'catchphrase' => '貸出期限は守ってください',
        'status' => 'completed',
        'rating_flags' => ['violence', 'cruelty'],
    ]))
        ->assertCreated()
        ->assertJsonPath('data.catchphrase', '貸出期限は守ってください')
        ->assertJsonPath('data.status', 'completed')
        ->assertJsonPath('data.rating_flags', ['violence', 'cruelty']);

    $work = WorkModel::sole();
    expect($work->catchphrase)->toBe('貸出期限は守ってください')
        ->and($work->status)->toBe('completed')
        ->and($work->rating_flags)->toBe(['violence', 'cruelty']);
});

it('入力で user_id や published_at を送っても使わない', function () {
    $other = UserModel::factory()->create();

    $this->postJson('/api/my/works', workPayload([
        'user_id' => $other->id,
        'published_at' => '2026-01-01T00:00:00+09:00',
        'last_episode_published_at' => '2026-01-01T00:00:00+09:00',
    ]))->assertCreated();

    $work = WorkModel::sole();
    expect($work->user_id)->toBe($this->user->id)
        ->and($work->published_at)->toBeNull()
        ->and($work->last_episode_published_at)->toBeNull();
});

it('必須の項目が無ければ 422 を返し、保存しない', function (string $field) {
    $payload = workPayload();
    unset($payload[$field]);

    $this->postJson('/api/my/works', $payload)
        ->assertUnprocessable()
        ->assertOnlyJsonValidationErrors([$field]);

    $this->assertDatabaseCount('works', 0);
})->with(['title', 'introduction', 'genre']);

it('定義外の値なら 422 を返す', function (array $overrides, array $fields) {
    $this->postJson('/api/my/works', workPayload($overrides))
        ->assertUnprocessable()
        ->assertOnlyJsonValidationErrors($fields);
})->with([
    'genre' => [['genre' => 'isekai'], ['genre']],
    'status' => [['status' => 'paused'], ['status']],
    'rating_flags の要素' => [['rating_flags' => ['gore']], ['rating_flags.0']],
    'rating_flags の重複' => [['rating_flags' => ['violence', 'violence']], ['rating_flags.0', 'rating_flags.1']],
    'rating_flags が配列でない' => [['rating_flags' => 'violence'], ['rating_flags']],
    'rating_flags が連想配列' => [['rating_flags' => ['a' => 'violence']], ['rating_flags']],
]);

it('上限の文字数ちょうどなら作成できる', function () {
    $this->postJson('/api/my/works', workPayload([
        'title' => str_repeat('あ', 100),
        'catchphrase' => str_repeat('あ', 35),
        'introduction' => str_repeat('あ', 10000),
    ]))->assertCreated();
});

it('上限の文字数を超えると 422 を返す', function (string $field, int $max) {
    $this->postJson('/api/my/works', workPayload([$field => str_repeat('あ', $max + 1)]))
        ->assertUnprocessable()
        ->assertOnlyJsonValidationErrors([$field]);
})->with([
    ['title', 100],
    ['catchphrase', 35],
    ['introduction', 10000],
]);

it('422 のメッセージは日本語の項目名で返す', function () {
    $this->postJson('/api/my/works', [
        'title' => '',
        'catchphrase' => str_repeat('あ', 36),
        'genre' => 'isekai',
        'status' => 'paused',
        'rating_flags' => ['gore'],
    ])
        ->assertUnprocessable()
        ->assertJsonPath('errors.title', ['タイトルを入力してください'])
        ->assertJsonPath('errors.catchphrase', ['キャッチコピーは35文字以内で入力してください'])
        ->assertJsonPath('errors.introduction', ['あらすじを入力してください'])
        ->assertJsonPath('errors.genre', ['ジャンルの値が正しくありません'])
        ->assertJsonPath('errors.status', ['連載状況の値が正しくありません'])
        ->assertJsonPath('errors', fn (array $errors) => $errors['rating_flags.0'] === ['セルフレイティングの値が正しくありません']);
});

it('未ログインなら 401 を返し、保存しない', function () {
    $this->withoutToken()
        ->postJson('/api/my/works', workPayload())
        ->assertUnauthorized();

    $this->assertDatabaseCount('works', 0);
});
