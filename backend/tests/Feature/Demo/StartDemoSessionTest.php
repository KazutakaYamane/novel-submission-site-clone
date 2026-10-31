<?php

use App\Infrastructure\Eloquent\Models\EpisodeModel;
use App\Infrastructure\Eloquent\Models\UserModel;
use App\Infrastructure\Eloquent\Models\WorkModel;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->travelTo(Carbon::parse('2026-10-22 12:00:00'));
});

it('デモ作者を始めると 201 と、ユーザー・トークン・初期作品の ID を返す', function () {
    $response = $this->postJson('/api/demo/sessions', ['role' => 'author'])
        ->assertCreated()
        ->assertJsonPath('expires_at', now()->addDays(7)->format(DateTimeInterface::ATOM))
        ->assertJsonStructure(['data' => ['id', 'name', 'email', 'created_at'], 'token']);

    $user = UserModel::findOrFail($response->json('data.id'));
    $work = WorkModel::findOrFail($response->json('sample_work_id'));
    expect($response->json('data.email'))->toBe($user->email)
        ->and($work->user_id)->toBe($user->id);
});

it('デモユーザーのメールアドレスは demo-<UUID>@example.com、名前は役割と UUID の先頭 4 文字にする', function () {
    $author = $this->postJson('/api/demo/sessions', ['role' => 'author'])->assertCreated()->json('data');
    $reader = $this->postJson('/api/demo/sessions', ['role' => 'reader'])->assertCreated()->json('data');

    foreach ([[$author, 'デモ作者'], [$reader, 'デモ読者']] as [$user, $prefix]) {
        expect($user['email'])->toMatch('/^demo-[0-9a-f-]{36}@example\.com$/');
        $uuid = Str::between($user['email'], 'demo-', '@example.com');
        expect(Str::isUuid($uuid))->toBeTrue()
            ->and($user['name'])->toBe($prefix.'-'.substr($uuid, 0, 4));
    }
});

it('デモユーザーのパスワードはランダムな値のハッシュだけを保存する', function () {
    $id = $this->postJson('/api/demo/sessions', ['role' => 'reader'])->assertCreated()->json('data.id');

    expect(Hash::isHashed(UserModel::findOrFail($id)->getAuthPassword()))->toBeTrue();
});

it('デモ作者の初期作品は、公開済みの話 2 つと下書きの話 1 つを持つ', function () {
    $workId = $this->postJson('/api/demo/sessions', ['role' => 'author'])->assertCreated()->json('sample_work_id');

    $this->assertDatabaseHas('works', [
        'id' => $workId,
        'title' => 'はじめての連載(デモ)',
        'genre' => 'drama',
        'published_at' => '2026-10-21 12:00:00',
        'last_episode_published_at' => '2026-10-22 11:00:00',
    ]);
    $this->assertDatabaseCount('episodes', 3);
    $this->assertDatabaseHas('episodes', ['work_id' => $workId, 'position' => 1, 'status' => 'published', 'published_at' => '2026-10-21 12:00:00']);
    $this->assertDatabaseHas('episodes', ['work_id' => $workId, 'position' => 2, 'status' => 'published', 'published_at' => '2026-10-22 11:00:00']);
    $this->assertDatabaseHas('episodes', ['work_id' => $workId, 'position' => 3, 'status' => 'draft', 'published_at' => null]);
});

it('デモ作者の初期作品の公開済みの本文はルビと傍点の記法を含む', function () {
    $workId = $this->postJson('/api/demo/sessions', ['role' => 'author'])->assertCreated()->json('sample_work_id');

    $bodies = EpisodeModel::where('work_id', $workId)->where('status', 'published')->pluck('body')->implode("\n");
    expect($bodies)->toMatch('/[|｜][^《》\n]+《[^《》\n]+》/u')
        ->and($bodies)->toMatch('/《《[^《》\n]+》》/u');
});

it('デモ読者を始めると 201 と、作品の ID に null を返し、作品を作らない', function () {
    $this->postJson('/api/demo/sessions', ['role' => 'reader'])
        ->assertCreated()
        ->assertJsonPath('sample_work_id', null)
        ->assertJsonStructure(['data' => ['id', 'name', 'email', 'created_at'], 'token', 'expires_at']);

    $this->assertDatabaseCount('users', 1);
    $this->assertDatabaseCount('works', 0);
});

it('デモで返したトークンで /api/me が自分を返す', function (string $role) {
    $response = $this->postJson('/api/demo/sessions', ['role' => $role])->assertCreated();

    $this->withToken($response->json('token'))
        ->getJson('/api/me')
        ->assertOk()
        ->assertJsonPath('data.id', $response->json('data.id'));
})->with(['author', 'reader']);

it('role が無い・不正なら 422 で role のエラーを返し、ユーザーを作らない', function (array $payload) {
    $this->postJson('/api/demo/sessions', $payload)
        ->assertUnprocessable()
        ->assertOnlyJsonValidationErrors(['role']);

    $this->assertDatabaseCount('users', 0);
})->with([
    'role が無い' => [[]],
    'role が不正' => [['role' => 'admin']],
]);

it('同じ IP から 1 時間に 10 回を超えると 429 を返す', function () {
    foreach (range(1, 10) as $_) {
        $this->postJson('/api/demo/sessions', ['role' => 'reader'])->assertCreated();
    }

    $this->postJson('/api/demo/sessions', ['role' => 'reader'])->assertTooManyRequests();
});

it('1 時間たつと同じ IP からまたデモを始められる', function () {
    foreach (range(1, 10) as $_) {
        $this->postJson('/api/demo/sessions', ['role' => 'reader'])->assertCreated();
    }

    $this->travel(1)->hours();

    $this->postJson('/api/demo/sessions', ['role' => 'reader'])->assertCreated();
});
