<?php

use App\Infrastructure\Eloquent\Models\EpisodeModel;
use App\Infrastructure\Eloquent\Models\UserModel;
use App\Infrastructure\Eloquent\Models\WorkModel;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->user = UserModel::factory()->create();
    $this->token = $this->user->createToken('web')->plainTextToken;
    $this->work = fn (string $updatedAt) => WorkModel::factory()
        ->state(['user_id' => $this->user->id, 'created_at' => $updatedAt, 'updated_at' => $updatedAt])
        ->create();
    $this->episode = fn (WorkModel $work, string $status, string $updatedAt) => EpisodeModel::factory()
        ->state([
            'work_id' => $work->id,
            'status' => $status,
            'published_at' => $status === 'published' ? $updatedAt : null,
            'updated_at' => $updatedAt,
        ])
        ->create();
    $this->listedIds = fn () => $this->withToken($this->token)->getJson('/api/my/works')->assertOk()->json('data.*.id');
});

it('自分の作品だけを、公開した話の数・下書きの話の数・更新日時をつけて返す', function () {
    $work = ($this->work)('2026-10-01 09:00:00');
    ($this->episode)($work, 'published', '2026-10-02 09:00:00');
    ($this->episode)($work, 'published', '2026-10-03 09:00:00');
    ($this->episode)($work, 'draft', '2026-10-04 09:00:00');
    WorkModel::factory()->create();

    $this->withToken($this->token)
        ->getJson('/api/my/works')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $work->id)
        ->assertJsonPath('data.0.published_episode_count', 2)
        ->assertJsonPath('data.0.draft_episode_count', 1)
        ->assertJsonPath('data.0.updated_at', '2026-10-04T09:00:00+00:00');
});

it('作品を話より後に更新したときは、作品の更新日時を返す', function () {
    $work = ($this->work)('2026-10-05 09:00:00');
    ($this->episode)($work, 'draft', '2026-10-04 09:00:00');

    $this->withToken($this->token)
        ->getJson('/api/my/works')
        ->assertJsonPath('data.0.updated_at', '2026-10-05T09:00:00+00:00');
});

it('話の無い作品は、2 つの数を 0 にして作品の更新日時を返す', function () {
    ($this->work)('2026-10-01 09:00:00');

    $this->withToken($this->token)
        ->getJson('/api/my/works')
        ->assertJsonPath('data.0.published_episode_count', 0)
        ->assertJsonPath('data.0.draft_episode_count', 0)
        ->assertJsonPath('data.0.updated_at', '2026-10-01T09:00:00+00:00');
});

it('話の更新日時も含めた更新日時の新しい順に返す', function () {
    $updatedByEpisode = ($this->work)('2026-10-01 09:00:00');
    ($this->episode)($updatedByEpisode, 'draft', '2026-10-05 09:00:00');
    $updatedByWork = ($this->work)('2026-10-03 09:00:00');
    $oldest = ($this->work)('2026-10-02 09:00:00');

    expect(($this->listedIds)())->toBe([$updatedByEpisode->id, $updatedByWork->id, $oldest->id]);
});

it('話を保存・公開・非公開に戻すと、その作品の更新日時が変わって先頭に来る', function (string $method, string $suffix, array $body) {
    $this->travelTo(Carbon::parse('2026-10-01 09:00:00'));
    $target = ($this->work)('2026-10-01 09:00:00');
    $episode = ($this->episode)($target, $suffix === '/unpublish' ? 'published' : 'draft', '2026-10-01 09:00:00');
    $other = ($this->work)('2026-10-02 09:00:00');
    expect(($this->listedIds)())->toBe([$other->id, $target->id]);

    $this->travelTo(Carbon::parse('2026-10-03 09:00:00'));
    $this->withToken($this->token)
        ->json($method, "/api/my/works/{$target->id}/episodes/{$episode->id}{$suffix}", $body)
        ->assertOk();

    $this->withToken($this->token)
        ->getJson('/api/my/works')
        ->assertJsonPath('data.0.id', $target->id)
        ->assertJsonPath('data.0.updated_at', '2026-10-03T09:00:00+00:00');
})->with([
    '保存' => ['PATCH', '', ['body' => '書き直した本文']],
    '公開' => ['POST', '/publish', []],
    '非公開に戻す' => ['POST', '/unpublish', []],
]);

// updated_at は秒単位なので、同じ秒に作った作品の順は id で決める
it('同じ時刻に更新した作品は、後に作った方を先に返す', function () {
    $this->freezeSecond();
    $this->withToken($this->token);

    $ids = collect(range(1, 5))->map(fn (int $i) => $this->postJson('/api/my/works', [
        'title' => "作品{$i}",
        'introduction' => 'あらすじ',
        'genre' => 'fantasy',
    ])->json('data.id'));

    expect(($this->listedIds)())->toBe($ids->reverse()->values()->all());
});

it('作品と話の数を変えてもクエリ数は変わらない', function () {
    $countQueries = function () {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->withToken($this->token)->getJson('/api/my/works')->assertOk();
        DB::disableQueryLog();

        return count(DB::getQueryLog());
    };

    ($this->work)('2026-10-01 09:00:00');
    // 最初のリクエストだけトークンとユーザーを読むクエリが入るので、測る前に 1 回読んでおく
    $countQueries();
    $withOne = $countQueries();

    foreach (range(2, 5) as $day) {
        $work = ($this->work)(sprintf('2026-10-%02d 09:00:00', $day));
        ($this->episode)($work, 'published', sprintf('2026-10-%02d 10:00:00', $day));
        ($this->episode)($work, 'draft', sprintf('2026-10-%02d 11:00:00', $day));
    }

    expect($countQueries())->toBe($withOne);
});

it('一覧を読んでも作品と話の更新日時は変わらない', function () {
    $work = ($this->work)('2026-10-01 09:00:00');
    $episode = ($this->episode)($work, 'draft', '2026-10-02 09:00:00');

    $this->withToken($this->token)->getJson('/api/my/works')->assertOk();

    $this->assertDatabaseHas('works', ['id' => $work->id, 'updated_at' => '2026-10-01 09:00:00']);
    $this->assertDatabaseHas('episodes', ['id' => $episode->id, 'updated_at' => '2026-10-02 09:00:00']);
});

it('作品が無ければ空の配列を返す', function () {
    $this->withToken($this->token)
        ->getJson('/api/my/works')
        ->assertOk()
        ->assertExactJson(['data' => []]);
});

it('未ログインなら 401 を返す', function () {
    $this->getJson('/api/my/works')->assertUnauthorized();
});
