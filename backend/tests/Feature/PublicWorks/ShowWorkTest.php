<?php

use App\Infrastructure\Eloquent\Models\EpisodeModel;
use App\Infrastructure\Eloquent\Models\TagModel;
use App\Infrastructure\Eloquent\Models\UserModel;
use App\Infrastructure\Eloquent\Models\WorkModel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->author = UserModel::factory()->state(['name' => '図書委員', 'email' => 'author@example.com'])->create();
    $this->work = WorkModel::factory()->state([
        'user_id' => $this->author->id,
        'title' => '図書委員の異世界日誌',
        'published_at' => '2026-10-01 09:00:00',
        'last_episode_published_at' => '2026-10-03 09:00:00',
    ])->create();
    $this->work->tags()->attach(TagModel::create(['name' => '異世界', 'normalized_name' => '異世界'])->id);
    $this->publishedEpisode = fn (string $title, string $publishedAt) => EpisodeModel::factory()
        ->state(['work_id' => $this->work->id, 'title' => $title, 'status' => 'published', 'published_at' => $publishedAt])
        ->create();
});

it('作品とタグ・作者名・公開済みの話だけの目次を返す', function () {
    $first = ($this->publishedEpisode)('第1話 司書になる', '2026-10-01 09:00:00');
    EpisodeModel::factory()->state(['work_id' => $this->work->id, 'title' => '書きかけの第2話'])->create();
    $third = ($this->publishedEpisode)('第3話 禁書庫', '2026-10-03 09:00:00');

    $response = $this->getJson("/api/works/{$this->work->id}")
        ->assertOk()
        ->assertJsonPath('data.id', $this->work->id)
        ->assertJsonPath('data.title', '図書委員の異世界日誌')
        ->assertJsonPath('data.tags', ['異世界'])
        ->assertJsonPath('data.author', ['name' => '図書委員'])
        ->assertJsonPath('data.published_at', '2026-10-01T09:00:00+00:00')
        ->assertJsonPath('data.episodes', [
            ['id' => $first->id, 'title' => '第1話 司書になる', 'position' => 1, 'published_at' => '2026-10-01T09:00:00+00:00'],
            ['id' => $third->id, 'title' => '第3話 禁書庫', 'position' => 3, 'published_at' => '2026-10-03T09:00:00+00:00'],
        ]);

    expect($response->getContent())->not->toContain('書きかけの第2話')
        ->not->toContain('author@example.com');
});

it('公開済みの話が無ければ目次を空にして返す', function () {
    EpisodeModel::factory()->state(['work_id' => $this->work->id])->create();

    $this->getJson("/api/works/{$this->work->id}")
        ->assertOk()
        ->assertJsonPath('data.episodes', []);
});

it('未公開の作品なら 404 を返す', function () {
    $draft = WorkModel::factory()->state(['user_id' => $this->author->id])->create();

    $this->getJson("/api/works/{$draft->id}")->assertNotFound();
});

it('存在しない作品なら 404 を返す', function () {
    $this->getJson('/api/works/'.Str::uuid7())->assertNotFound();
});

it('目次の話数を変えてもクエリ数は変わらない', function () {
    $countQueries = function () {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->getJson("/api/works/{$this->work->id}")->assertOk();
        DB::disableQueryLog();

        return count(DB::getQueryLog());
    };

    ($this->publishedEpisode)('第1話', '2026-10-01 09:00:00');
    $withOne = $countQueries();

    foreach (range(2, 5) as $position) {
        ($this->publishedEpisode)("第{$position}話", '2026-10-01 09:00:00');
    }

    expect($countQueries())->toBe($withOne);
});
