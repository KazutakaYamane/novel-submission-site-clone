<?php

use App\Infrastructure\Eloquent\Models\EpisodeModel;
use App\Infrastructure\Eloquent\Models\UserModel;
use App\Infrastructure\Eloquent\Models\WorkModel;
use Illuminate\Support\Carbon;

/**
 * @return list<array{slug: string, author: string, title: string, episodes: list<array{title: string}>}>
 */
function manifestWorks(): array
{
    $dir = database_path('seeders/data');
    $manifest = json_decode((string) file_get_contents("{$dir}/aozora-manifest.json"), true, flags: JSON_THROW_ON_ERROR);

    return array_map(
        fn (array $entry) => json_decode((string) file_get_contents("{$dir}/works/{$entry['slug']}.json"), true, flags: JSON_THROW_ON_ERROR),
        $manifest,
    );
}

beforeEach(function () {
    $this->now = Carbon::parse('2026-11-14 04:00:00', 'UTC');
    $this->travelTo($this->now);
    $this->seed();
});

it('マニフェストの作品を、すべて公開済みで作る', function () {
    expect(WorkModel::query()->whereNotNull('published_at')->pluck('title')->all())
        ->toEqualCanonicalizing(array_column(manifestWorks(), 'title'))
        ->and(WorkModel::query()->count())->toBe(count(manifestWorks()))
        ->and(EpisodeModel::query()->whereNull('published_at')->exists())->toBeFalse();
});

it('著者ごとに example.com のシード作者を 1 人作り、作品の所有者にする', function () {
    $authors = array_values(array_unique(array_column(manifestWorks(), 'author')));

    expect(UserModel::query()->pluck('name')->all())->toEqualCanonicalizing($authors);
    foreach (UserModel::query()->get() as $user) {
        expect($user->email)->toEndWith('@example.com');
    }
    foreach (manifestWorks() as $work) {
        $owner = UserModel::query()->whereKey(WorkModel::query()->where('title', $work['title'])->value('user_id'))->value('name');
        expect($owner)->toBe($work['author']);
    }
});

it('マニフェストの先頭の作品の最後の話を、シードの 1 時間前に公開する', function () {
    $first = manifestWorks()[0];
    $workId = WorkModel::query()->where('title', $first['title'])->value('id');
    $last = EpisodeModel::query()->where('work_id', $workId)->orderByDesc('position')->first();

    expect($last?->published_at?->equalTo($this->now->copy()->subHour()))->toBeTrue()
        ->and(WorkModel::query()->orderByDesc('last_episode_published_at')->first()?->last_episode_published_at?->equalTo($this->now->copy()->subHour()))->toBeTrue();
});

it('すべての話を過去 30 日以内かつシードの時刻より前に公開する', function () {
    foreach (EpisodeModel::query()->get() as $episode) {
        expect($episode->published_at?->lessThan($this->now))->toBeTrue()
            ->and($episode->published_at?->greaterThanOrEqualTo($this->now->copy()->subDays(30)))->toBeTrue();
    }
});

it('作品の中では前の話ほど公開日時が古い', function () {
    foreach (WorkModel::query()->pluck('id') as $workId) {
        $publishedAts = EpisodeModel::query()->where('work_id', $workId)->orderBy('position')->pluck('published_at')->map->getTimestamp()->all();
        $sorted = $publishedAts;
        sort($sorted);

        expect($publishedAts)->toBe($sorted);
    }
});
