<?php

use App\Infrastructure\Eloquent\Models\EpisodeModel;
use App\Infrastructure\Eloquent\Models\UserModel;
use App\Infrastructure\Eloquent\Models\WorkModel;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->author = UserModel::factory()->state(['name' => '図書委員', 'email' => 'author@example.com'])->create();
    $this->publishedWork = function (string $lastEpisodePublishedAt, array $state = []): WorkModel {
        $work = WorkModel::factory()->state([
            'user_id' => $this->author->id,
            'published_at' => '2026-10-01 09:00:00',
            'last_episode_published_at' => $lastEpisodePublishedAt,
            ...$state,
        ])->create();
        EpisodeModel::factory()
            ->state(['work_id' => $work->id, 'status' => 'published', 'published_at' => $lastEpisodePublishedAt])
            ->create();

        return $work;
    };
    $this->listedIds = fn (string $query = '') => array_column($this->getJson("/api/works{$query}")->assertOk()->json('data'), 'id');
});

it('公開済みの作品を、最後に話を公開した日時の新しい順に返す', function () {
    $old = ($this->publishedWork)('2026-10-02 09:00:00');
    $newest = ($this->publishedWork)('2026-10-05 09:00:00');
    $middle = ($this->publishedWork)('2026-10-03 09:00:00');

    expect(($this->listedIds)())->toBe([$newest->id, $middle->id, $old->id]);
});

it('最後に話を公開した日時が同じ作品は作品 ID の昇順に並べる', function () {
    $works = collect(range(1, 3))->map(fn () => ($this->publishedWork)('2026-10-02 09:00:00'));

    expect(($this->listedIds)())->toBe($works->pluck('id')->sort()->values()->all());
});

it('作品の一覧用の項目と作者名を返し、メールアドレスは返さない', function () {
    $work = ($this->publishedWork)('2026-10-03 09:00:00', [
        'title' => '図書委員の異世界日誌',
        'catchphrase' => '本の虫、異世界で司書になる',
        'genre' => 'fantasy',
        'status' => 'completed',
    ]);

    $response = $this->getJson('/api/works')
        ->assertOk()
        ->assertJsonPath('data.0', [
            'id' => $work->id,
            'title' => '図書委員の異世界日誌',
            'catchphrase' => '本の虫、異世界で司書になる',
            'genre' => 'fantasy',
            'status' => 'completed',
            'published_episode_count' => 1,
            'last_episode_published_at' => '2026-10-03T09:00:00+00:00',
            'author' => ['name' => '図書委員'],
        ]);

    expect($response->getContent())->not->toContain('author@example.com');
});

it('話数は公開済みの話だけを数える', function () {
    $work = ($this->publishedWork)('2026-10-03 09:00:00');
    EpisodeModel::factory()->state(['work_id' => $work->id, 'status' => 'published', 'published_at' => '2026-10-02 09:00:00'])->create();
    EpisodeModel::factory()->state(['work_id' => $work->id])->create();

    $this->getJson('/api/works')
        ->assertOk()
        ->assertJsonPath('data.0.published_episode_count', 2);
});

it('一度も話を公開していない作品は返さない', function () {
    $published = ($this->publishedWork)('2026-10-03 09:00:00');
    $draft = WorkModel::factory()->state(['user_id' => $this->author->id])->create();
    EpisodeModel::factory()->state(['work_id' => $draft->id])->create();

    expect(($this->listedIds)())->toBe([$published->id]);
});

it('全話を非公開に戻した作品は返さない', function () {
    $published = ($this->publishedWork)('2026-10-03 09:00:00');
    // 非公開化は作品の published_at を残し、last_episode_published_at だけを null に戻す
    $unpublished = WorkModel::factory()->state([
        'user_id' => $this->author->id,
        'published_at' => '2026-10-01 09:00:00',
        'last_episode_published_at' => null,
    ])->create();
    EpisodeModel::factory()->state(['work_id' => $unpublished->id])->create();

    expect(($this->listedIds)())->toBe([$published->id]);
});

it('limit を省くと 20 件を返す', function () {
    foreach (range(1, 21) as $day) {
        ($this->publishedWork)(sprintf('2026-10-%02d 09:00:00', $day));
    }

    expect(($this->listedIds)())->toHaveCount(20);
});

it('limit の件数だけを新しい順に返す', function () {
    ($this->publishedWork)('2026-10-02 09:00:00');
    $newest = ($this->publishedWork)('2026-10-05 09:00:00');
    $middle = ($this->publishedWork)('2026-10-03 09:00:00');

    expect(($this->listedIds)('?limit=2'))->toBe([$newest->id, $middle->id]);
});

it('limit は 50 まで受け付ける', function () {
    ($this->publishedWork)('2026-10-02 09:00:00');

    $this->getJson('/api/works?limit=50')->assertOk();
});

it('limit が範囲外か整数でなければ 422 を返す', function (string $limit) {
    $this->getJson("/api/works?limit={$limit}")
        ->assertUnprocessable()
        ->assertJsonValidationErrors('limit');
})->with(['0', '51', 'abc', '1.5']);

it('作品の数を変えてもクエリ数は変わらない', function () {
    $countQueries = function () {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->getJson('/api/works')->assertOk();
        DB::disableQueryLog();

        return count(DB::getQueryLog());
    };

    ($this->publishedWork)('2026-10-01 09:00:00');
    $withOne = $countQueries();

    foreach (range(2, 5) as $day) {
        // 作者が作品ごとに違っても、作者名をまとめて読む
        ($this->publishedWork)(sprintf('2026-10-%02d 09:00:00', $day), ['user_id' => UserModel::factory()]);
    }

    expect($countQueries())->toBe($withOne);
});
