<?php

use App\Infrastructure\Eloquent\Models\EpisodeModel;
use App\Infrastructure\Eloquent\Models\UserModel;
use App\Infrastructure\Eloquent\Models\WorkModel;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->user = UserModel::factory()->create();
    $this->token = $this->user->createToken('web')->plainTextToken;
    $this->work = WorkModel::factory()->state(['user_id' => $this->user->id])->create();
});

describe('作成', function () {
    it('下書きとして保存し、201 を返す', function () {
        $response = $this->withToken($this->token)
            ->postJson("/api/my/works/{$this->work->id}/episodes", ['title' => '第一話 貸出カード', 'body' => '本文です。'])
            ->assertCreated()
            ->assertJsonPath('data.title', '第一話 貸出カード')
            ->assertJsonPath('data.body', '本文です。')
            ->assertJsonPath('data.position', 1)
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.published_at', null);

        $id = $response->json('data.id');
        expect(Str::isUuid($id))->toBeTrue();
        $this->assertDatabaseHas('episodes', [
            'id' => $id,
            'work_id' => $this->work->id,
            'title' => '第一話 貸出カード',
            'body' => '本文です。',
            'position' => 1,
            'status' => 'draft',
            'published_at' => null,
        ]);
    });

    it('position は作品ごとに 1 から振る', function () {
        $other = WorkModel::factory()->state(['user_id' => $this->user->id])->create();
        $create = fn (WorkModel $work) => $this->withToken($this->token)
            ->postJson("/api/my/works/{$work->id}/episodes", ['title' => '話', 'body' => '本文'])
            ->assertCreated()
            ->json('data.position');

        expect([$create($this->work), $create($this->work), $create($this->work), $create($other)])
            ->toBe([1, 2, 3, 1]);
    });

    it('本文が空でも保存する', function (array $payload) {
        $id = $this->withToken($this->token)
            ->postJson("/api/my/works/{$this->work->id}/episodes", ['title' => '第一話', ...$payload])
            ->assertCreated()
            ->assertJsonPath('data.body', '')
            ->json('data.id');

        expect(EpisodeModel::find($id)->body)->toBe('');
    })->with([
        '空文字' => [['body' => '']],
        'null' => [['body' => null]],
        '送らない' => [[]],
    ]);

    it('タイトルが空か、上限を超える文字数なら 422 を返し、保存しない', function (array $payload, string $field) {
        $this->withToken($this->token)
            ->postJson("/api/my/works/{$this->work->id}/episodes", $payload)
            ->assertUnprocessable()
            ->assertOnlyJsonValidationErrors([$field]);

        expect(EpisodeModel::count())->toBe(0);
    })->with([
        'タイトルが無い' => [['body' => '本文'], 'title'],
        'タイトルが空' => [['title' => '', 'body' => '本文'], 'title'],
        'タイトルが空白だけ' => [['title' => '　 ', 'body' => '本文'], 'title'],
        'タイトルが 101 字' => [['title' => str_repeat('あ', 101), 'body' => '本文'], 'title'],
    ]);

    it('タイトルが上限ちょうどの文字数なら保存する', function () {
        $this->withToken($this->token)
            ->postJson("/api/my/works/{$this->work->id}/episodes", ['title' => str_repeat('あ', 100), 'body' => '本文'])
            ->assertCreated();
    });

    // 数え方は小さい上限で確かめる。数 MB の本文のリクエストはテストの間メモリに残り、PHP の上限を超えるため
    it('本文の上限は文字数ではなく UTF-8 のバイト数で数える', function (string $body, int $status) {
        Config::set('work.episode_body_max_bytes', 9);

        $this->withToken($this->token)
            ->postJson("/api/my/works/{$this->work->id}/episodes", ['title' => '第一話', 'body' => $body])
            ->assertStatus($status);
    })->with([
        'ASCII で 9 バイト' => ['aaaaaaaaa', 201],
        'ASCII で 10 バイト' => ['aaaaaaaaaa', 422],
        '全角 3 字で 9 バイト' => ['あああ', 201],
        '全角 4 字で 12 バイト' => ['ああああ', 422],
    ]);

    it('本文は 6990474 バイトまで保存し、超えると上限のバイト数をエラーで伝える', function () {
        $this->withToken($this->token)
            ->postJson("/api/my/works/{$this->work->id}/episodes", ['title' => '第一話', 'body' => str_repeat('a', 6990474)])
            ->assertCreated();
        expect(strlen(EpisodeModel::sole()->body))->toBe(6990474);

        $this->withToken($this->token)
            ->postJson("/api/my/works/{$this->work->id}/episodes", ['title' => '第二話', 'body' => str_repeat('a', 6990475)])
            ->assertUnprocessable()
            ->assertJsonPath('errors.body.0', '本文は6990474バイト以内で入力してください');
        expect(EpisodeModel::count())->toBe(1);
    });

    it('タイトルの前後の空白を削る', function () {
        $this->withToken($this->token)
            ->postJson("/api/my/works/{$this->work->id}/episodes", ['title' => "　第一話 \n", 'body' => '本文'])
            ->assertCreated()
            ->assertJsonPath('data.title', '第一話');
    });

    // 段落の頭の全角スペースと末尾の空行は作者が書いた体裁なので、そのまま残す
    it('本文の前後の空白と改行を削らない', function () {
        $body = "　彼は扉を開けた。\n\n　中は暗かった。\n\n";

        $id = $this->withToken($this->token)
            ->postJson("/api/my/works/{$this->work->id}/episodes", ['title' => '第一話', 'body' => $body])
            ->assertCreated()
            ->assertJsonPath('data.body', $body)
            ->json('data.id');

        expect(EpisodeModel::find($id)->body)->toBe($body);
    });
});

describe('一覧', function () {
    it('本文を含めず、position の順に返す', function () {
        $second = EpisodeModel::factory()->state(['work_id' => $this->work->id])->create(['position' => 2, 'title' => '第二話']);
        $first = EpisodeModel::factory()->state(['work_id' => $this->work->id])->create(['position' => 1, 'title' => '第一話']);
        EpisodeModel::factory()->create();

        $this->withToken($this->token)
            ->getJson("/api/my/works/{$this->work->id}/episodes")
            ->assertOk()
            ->assertExactJson(['data' => [
                ['id' => $first->id, 'title' => '第一話', 'position' => 1, 'status' => 'draft', 'published_at' => null],
                ['id' => $second->id, 'title' => '第二話', 'position' => 2, 'status' => 'draft', 'published_at' => null],
            ]]);
    });
});

describe('取得', function () {
    it('本文を含めて返す', function () {
        $episode = EpisodeModel::factory()->state(['work_id' => $this->work->id])->create(['title' => '第一話', 'body' => "一行目\n二行目"]);

        $this->withToken($this->token)
            ->getJson("/api/my/works/{$this->work->id}/episodes/{$episode->id}")
            ->assertOk()
            ->assertExactJson(['data' => [
                'id' => $episode->id,
                'title' => '第一話',
                'body' => "一行目\n二行目",
                'position' => 1,
                'status' => 'draft',
                'published_at' => null,
                'public_path' => null,
            ]]);
    });
});

describe('更新', function () {
    beforeEach(function () {
        $this->episode = EpisodeModel::factory()->state(['work_id' => $this->work->id])->create(['title' => '元のタイトル', 'body' => '元の本文']);
    });

    it('送った項目だけを変え、ほかの項目は変えない', function () {
        $this->withToken($this->token)
            ->patchJson("/api/my/works/{$this->work->id}/episodes/{$this->episode->id}", ['title' => '新しいタイトル'])
            ->assertOk()
            ->assertJsonPath('data.title', '新しいタイトル')
            ->assertJsonPath('data.body', '元の本文');

        $episode = $this->episode->fresh();
        expect($episode->title)->toBe('新しいタイトル')
            ->and($episode->body)->toBe('元の本文');
    });

    it('タイトルの前後の空白を削る', function () {
        $this->withToken($this->token)
            ->patchJson("/api/my/works/{$this->work->id}/episodes/{$this->episode->id}", ['title' => '　新しいタイトル '])
            ->assertOk()
            ->assertJsonPath('data.title', '新しいタイトル');
    });

    it('本文の前後の空白と改行を削らない', function () {
        $body = "　書き直した。\n\n";

        $this->withToken($this->token)
            ->patchJson("/api/my/works/{$this->work->id}/episodes/{$this->episode->id}", ['body' => $body])
            ->assertOk()
            ->assertJsonPath('data.body', $body);

        expect($this->episode->fresh()->body)->toBe($body);
    });

    it('本文に空文字か null を送ると空にする', function (?string $body) {
        $this->withToken($this->token)
            ->patchJson("/api/my/works/{$this->work->id}/episodes/{$this->episode->id}", ['body' => $body])
            ->assertOk()
            ->assertJsonPath('data.body', '');

        expect($this->episode->fresh()->body)->toBe('');
    })->with(['空文字' => '', 'null' => null]);

    it('status・position・work_id・published_at を送っても変えない', function () {
        $other = WorkModel::factory()->state(['user_id' => $this->user->id])->create();

        $this->withToken($this->token)
            ->patchJson("/api/my/works/{$this->work->id}/episodes/{$this->episode->id}", [
                'status' => 'published',
                'position' => 5,
                'work_id' => $other->id,
                'published_at' => '2026-01-01T00:00:00+09:00',
            ])
            ->assertOk();

        $episode = $this->episode->fresh();
        expect($episode->status)->toBe('draft')
            ->and($episode->position)->toBe(1)
            ->and($episode->work_id)->toBe($this->work->id)
            ->and($episode->published_at)->toBeNull();
    });

    it('タイトルを空にするか、上限を超える文字数なら 422 を返し、変えない', function (array $payload, string $field) {
        $this->withToken($this->token)
            ->patchJson("/api/my/works/{$this->work->id}/episodes/{$this->episode->id}", $payload)
            ->assertUnprocessable()
            ->assertOnlyJsonValidationErrors([$field]);

        $episode = $this->episode->fresh();
        expect($episode->title)->toBe('元のタイトル')
            ->and($episode->body)->toBe('元の本文');
    })->with([
        'タイトルが null' => [['title' => null], 'title'],
        'タイトルが空白だけ' => [['title' => '　 '], 'title'],
        'タイトルが 101 字' => [['title' => str_repeat('あ', 101)], 'title'],
    ]);

    it('本文がバイト数の上限を超えると 422 を返し、変えない', function (string $body, int $status) {
        Config::set('work.episode_body_max_bytes', 9);

        $this->withToken($this->token)
            ->patchJson("/api/my/works/{$this->work->id}/episodes/{$this->episode->id}", ['body' => $body])
            ->assertStatus($status);

        expect($this->episode->fresh()->body)->toBe($status === 200 ? $body : '元の本文');
    })->with([
        '全角 3 字で 9 バイト' => ['あああ', 200],
        '全角 4 字で 12 バイト' => ['ああああ', 422],
    ]);
});

// 他人の作品は、話 ID が何であっても話を探す前に作品の権限で断る
describe('他人の作品', function () {
    $requests = [
        '作成' => fn (string $workId, string $episodeId) => ['postJson', "/api/my/works/{$workId}/episodes", ['title' => '書き込み', 'body' => '本文']],
        '一覧' => fn (string $workId, string $episodeId) => ['getJson', "/api/my/works/{$workId}/episodes", []],
        '取得' => fn (string $workId, string $episodeId) => ['getJson', "/api/my/works/{$workId}/episodes/{$episodeId}", []],
        '更新' => fn (string $workId, string $episodeId) => ['patchJson', "/api/my/works/{$workId}/episodes/{$episodeId}", ['title' => '書き換え']],
    ];

    it('公開済みの作品なら 403 を返し、話を変えない', function (Closure $request) {
        $work = WorkModel::factory()->published()->create();
        $episode = EpisodeModel::factory()->state(['work_id' => $work->id])->create(['title' => '他人の話']);
        [$method, $uri, $payload] = $request($work->id, $episode->id);

        $this->withToken($this->token)->{$method}($uri, $payload)->assertForbidden();

        expect(EpisodeModel::count())->toBe(1)
            ->and($episode->fresh()->title)->toBe('他人の話');
    })->with($requests);

    it('未公開の作品なら、存在を隠して 404 を返し、話を変えない', function (Closure $request) {
        $work = WorkModel::factory()->create();
        $episode = EpisodeModel::factory()->state(['work_id' => $work->id])->create(['title' => '他人の話']);
        [$method, $uri, $payload] = $request($work->id, $episode->id);

        $this->withToken($this->token)->{$method}($uri, $payload)->assertNotFound();

        expect(EpisodeModel::count())->toBe(1)
            ->and($episode->fresh()->title)->toBe('他人の話');
    })->with($requests);
});

describe('見つからない話', function () {
    $requests = [
        '取得' => fn (string $workId, string $episodeId) => ['getJson', "/api/my/works/{$workId}/episodes/{$episodeId}", []],
        '更新' => fn (string $workId, string $episodeId) => ['patchJson', "/api/my/works/{$workId}/episodes/{$episodeId}", ['title' => '書き換え']],
    ];

    it('自分の別の作品の話 ID なら 404 を返し、変えない', function (Closure $request) {
        $other = WorkModel::factory()->state(['user_id' => $this->user->id])->create();
        $episode = EpisodeModel::factory()->state(['work_id' => $other->id])->create(['title' => '別の作品の話']);
        [$method, $uri, $payload] = $request($this->work->id, $episode->id);

        $this->withToken($this->token)->{$method}($uri, $payload)->assertNotFound();

        expect($episode->fresh()->title)->toBe('別の作品の話');
    })->with($requests);

    it('存在しない話 ID と UUID でない話 ID なら 404 を返す', function (Closure $request, string $episodeId) {
        [$method, $uri, $payload] = $request($this->work->id, $episodeId);

        $this->withToken($this->token)->{$method}($uri, $payload)->assertNotFound();
    })->with($requests)->with([
        '存在しない' => fn () => (string) Str::uuid(),
        'UUID でない' => 'not-a-uuid',
    ]);

    it('存在しない作品 ID なら 404 を返す', function () {
        $this->withToken($this->token)
            ->postJson('/api/my/works/'.Str::uuid().'/episodes', ['title' => '第一話', 'body' => '本文'])
            ->assertNotFound();

        expect(EpisodeModel::count())->toBe(0);
    });
});

it('未ログインなら 401 を返す', function (string $method, Closure $uri) {
    $episode = EpisodeModel::factory()->state(['work_id' => $this->work->id])->create();

    $this->{$method}($uri($this->work->id, $episode->id), ['title' => '書き換え'])->assertUnauthorized();

    expect(EpisodeModel::count())->toBe(1);
})->with([
    '作成' => ['postJson', fn (string $workId) => "/api/my/works/{$workId}/episodes"],
    '一覧' => ['getJson', fn (string $workId) => "/api/my/works/{$workId}/episodes"],
    '取得' => ['getJson', fn (string $workId, string $episodeId) => "/api/my/works/{$workId}/episodes/{$episodeId}"],
    '更新' => ['patchJson', fn (string $workId, string $episodeId) => "/api/my/works/{$workId}/episodes/{$episodeId}"],
]);
