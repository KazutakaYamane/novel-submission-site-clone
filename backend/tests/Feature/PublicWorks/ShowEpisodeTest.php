<?php

use App\Infrastructure\Eloquent\Models\EpisodeModel;
use App\Infrastructure\Eloquent\Models\UserModel;
use App\Infrastructure\Eloquent\Models\WorkModel;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->author = UserModel::factory()->state(['name' => '図書委員', 'email' => 'author@example.com'])->create();
    $this->work = WorkModel::factory()->state([
        'user_id' => $this->author->id,
        'title' => '図書委員の異世界日誌',
        'published_at' => '2026-10-01 09:00:00',
    ])->create();
    $this->publishedEpisode = fn (WorkModel $work, string $title) => EpisodeModel::factory()
        ->state(['work_id' => $work->id, 'title' => $title, 'status' => 'published', 'published_at' => '2026-10-01 09:00:00'])
        ->create();
    $this->draftEpisode = fn (WorkModel $work, string $title) => EpisodeModel::factory()
        ->state(['work_id' => $work->id, 'title' => $title])
        ->create();
});

it('本文と作品のタイトル・作者名と、下書きを飛ばした前後の話を返す', function () {
    $first = ($this->publishedEpisode)($this->work, '第1話');
    ($this->draftEpisode)($this->work, '書きかけの第2話');
    $third = ($this->publishedEpisode)($this->work, '第3話');
    ($this->draftEpisode)($this->work, '書きかけの第4話');
    $fifth = ($this->publishedEpisode)($this->work, '第5話');

    $response = $this->getJson("/api/works/{$this->work->id}/episodes/{$third->id}")
        ->assertOk()
        ->assertJsonPath('data.id', $third->id)
        ->assertJsonPath('data.title', '第3話')
        ->assertJsonPath('data.body', $third->body)
        ->assertJsonPath('data.position', 3)
        ->assertJsonPath('data.published_at', '2026-10-01T09:00:00+00:00')
        ->assertJsonPath('data.work', [
            'id' => $this->work->id,
            'title' => '図書委員の異世界日誌',
            'author' => ['name' => '図書委員'],
        ])
        ->assertJsonPath('data.previous', ['id' => $first->id, 'title' => '第1話'])
        ->assertJsonPath('data.next', ['id' => $fifth->id, 'title' => '第5話']);

    expect($response->getContent())->not->toContain('書きかけの')
        ->not->toContain('author@example.com');
});

it('先頭の話の前と末尾の話の次は null を返す', function () {
    $first = ($this->publishedEpisode)($this->work, '第1話');
    $second = ($this->publishedEpisode)($this->work, '第2話');

    $this->getJson("/api/works/{$this->work->id}/episodes/{$first->id}")
        ->assertOk()
        ->assertJsonPath('data.previous', null)
        ->assertJsonPath('data.next', ['id' => $second->id, 'title' => '第2話']);

    $this->getJson("/api/works/{$this->work->id}/episodes/{$second->id}")
        ->assertOk()
        ->assertJsonPath('data.previous', ['id' => $first->id, 'title' => '第1話'])
        ->assertJsonPath('data.next', null);
});

it('下書きの話なら 404 を返す', function () {
    $draft = ($this->draftEpisode)($this->work, '書きかけ');

    $this->getJson("/api/works/{$this->work->id}/episodes/{$draft->id}")->assertNotFound();
});

it('非公開に戻した話なら 404 を返す', function () {
    $episode = ($this->publishedEpisode)($this->work, '第1話');
    $episode->forceFill(['status' => 'draft', 'published_at' => null])->save();

    $this->getJson("/api/works/{$this->work->id}/episodes/{$episode->id}")->assertNotFound();
});

it('別の作品の話なら 404 を返す', function () {
    $other = WorkModel::factory()->state(['published_at' => '2026-10-01 09:00:00'])->create();
    $episode = ($this->publishedEpisode)($other, '別作品の第1話');

    $this->getJson("/api/works/{$this->work->id}/episodes/{$episode->id}")->assertNotFound();
});

it('未公開の作品の話なら 404 を返す', function () {
    $draftWork = WorkModel::factory()->state(['user_id' => $this->author->id])->create();
    $episode = ($this->publishedEpisode)($draftWork, '第1話');

    $this->getJson("/api/works/{$draftWork->id}/episodes/{$episode->id}")->assertNotFound();
});

it('存在しない話なら 404 を返す', function () {
    $this->getJson("/api/works/{$this->work->id}/episodes/".Str::uuid7())->assertNotFound();
});
