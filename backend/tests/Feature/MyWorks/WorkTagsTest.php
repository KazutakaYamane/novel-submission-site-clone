<?php

use App\Infrastructure\Eloquent\Models\User;
use App\Infrastructure\Eloquent\Models\Work;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->withToken($this->user->createToken('web')->plainTextToken);
});

function taggedWorkPayload(mixed $tags): array
{
    return [
        'title' => '転生したら図書委員だった件',
        'introduction' => '本の虫が異世界の図書館で働く話。',
        'genre' => 'fantasy',
        'tags' => $tags,
    ];
}

it('作成時に送ったタグを返し、保存する', function () {
    $response = $this->postJson('/api/my/works', taggedWorkPayload(['異世界', 'スローライフ']))
        ->assertCreated();

    expect($response->json('data.tags'))->toEqualCanonicalizing(['異世界', 'スローライフ'])
        ->and(DB::table('tags')->pluck('name')->all())->toEqualCanonicalizing(['異世界', 'スローライフ'])
        ->and(DB::table('work_tags')->where('work_id', $response->json('data.id'))->count())->toBe(2);
});

it('タグを送らなければ、タグの無い作品を作る', function () {
    $this->postJson('/api/my/works', [
        'title' => '転生したら図書委員だった件',
        'introduction' => '本の虫が異世界の図書館で働く話。',
        'genre' => 'fantasy',
    ])
        ->assertCreated()
        ->assertJsonPath('data.tags', []);
});

it('タグの id は UUIDv7 にする', function () {
    $this->postJson('/api/my/works', taggedWorkPayload(['異世界']))->assertCreated();

    expect(Str::isUuid(DB::table('tags')->value('id'), 7))->toBeTrue();
});

it('同じ名前のタグを 2 つの作品に付けても、タグは 1 行にする', function () {
    $this->postJson('/api/my/works', taggedWorkPayload(['異世界']))->assertCreated();
    $this->postJson('/api/my/works', taggedWorkPayload(['異世界']))->assertCreated();

    $this->assertDatabaseCount('tags', 1);
    $this->assertDatabaseCount('work_tags', 2);
});

it('大文字小文字だけが違う名前は同じタグにし、最初に登録した表記を返す', function () {
    $this->postJson('/api/my/works', taggedWorkPayload(['RPG']))->assertCreated();

    $this->postJson('/api/my/works', taggedWorkPayload(['rpg']))
        ->assertCreated()
        ->assertJsonPath('data.tags', ['RPG']);

    $this->assertDatabaseCount('tags', 1);
});

it('1 つの作品に大文字小文字だけが違う名前を送ると、1 つのタグにまとめる', function () {
    $this->postJson('/api/my/works', taggedWorkPayload(['RPG', 'rpg']))
        ->assertCreated()
        ->assertJsonPath('data.tags', ['RPG']);

    $this->assertDatabaseCount('tags', 1);
});

// MySQL の utf8mb4_unicode_ci はこれらを同じ値とみなすので、照合順序に任せると 1 つのタグになってしまう
it('大文字小文字以外の違いがある名前は別のタグにする', function (string $first, string $second) {
    $this->postJson('/api/my/works', taggedWorkPayload([$first]))->assertCreated();

    $this->postJson('/api/my/works', taggedWorkPayload([$second]))
        ->assertCreated()
        ->assertJsonPath('data.tags', [$second]);

    $this->assertDatabaseCount('tags', 2);
})->with([
    '濁点' => ['パパ', 'ハハ'],
    'ひらがなとカタカナ' => ['ざまぁ', 'ザマァ'],
    '全角と半角' => ['ＲＰＧ', 'RPG'],
    '絵文字' => ['🍣', '🍺'],
]);

// PHP は数字だけの文字列を配列のキーにすると int にし、MySQL は文字列の列と int を数値として比べる
it('数字だけのタグ名は、数字で始まる別のタグと区別する', function () {
    $this->postJson('/api/my/works', taggedWorkPayload(['123話']))->assertCreated();

    $this->postJson('/api/my/works', taggedWorkPayload(['123']))
        ->assertCreated()
        ->assertJsonPath('data.tags', ['123']);
});

// 照合順序で並べると、大文字小文字以外が違うだけの名前の順が決まらない
it('タグは小文字にした名前のコードポイント順で返す', function () {
    $this->postJson('/api/my/works', taggedWorkPayload(['パパ', 'Banana', 'ハハ', 'apple']))
        ->assertCreated()
        ->assertJsonPath('data.tags', ['apple', 'Banana', 'ハハ', 'パパ']);
});

it('タグの前後の空白を除いて保存する', function () {
    $this->postJson('/api/my/works', taggedWorkPayload(['  異世界 ']))
        ->assertCreated()
        ->assertJsonPath('data.tags', ['異世界']);
});

it('タグは上限の 8 個・20 文字ちょうどなら付けられる', function () {
    $tags = array_map(fn (int $i) => str_repeat('あ', 19).$i, range(1, 8));

    $response = $this->postJson('/api/my/works', taggedWorkPayload($tags))->assertCreated();

    expect($response->json('data.tags'))->toEqualCanonicalizing($tags);
});

it('タグの形式が正しくなければ 422 を返し、保存しない', function (mixed $tags, array $fields) {
    $this->postJson('/api/my/works', taggedWorkPayload($tags))
        ->assertUnprocessable()
        ->assertOnlyJsonValidationErrors($fields);

    $this->assertDatabaseCount('works', 0);
    $this->assertDatabaseCount('tags', 0);
})->with([
    '9 個' => [array_map(fn (int $i) => "タグ{$i}", range(1, 9)), ['tags']],
    '空文字' => [[''], ['tags.0']],
    '空白だけ' => [['   '], ['tags.0']],
    '21 文字' => [[str_repeat('あ', 21)], ['tags.0']],
    '文字列でない' => [[1], ['tags.0']],
    '配列でない' => ['異世界', ['tags']],
    '連想配列' => [['a' => '異世界'], ['tags']],
]);

it('タグの 422 のメッセージは日本語で返す', function () {
    $this->postJson('/api/my/works', taggedWorkPayload(array_map(fn (int $i) => "タグ{$i}", range(1, 9))))
        ->assertUnprocessable()
        ->assertJsonPath('errors.tags', ['タグは8個以内で指定してください']);

    $this->postJson('/api/my/works', taggedWorkPayload(['', str_repeat('あ', 21)]))
        ->assertUnprocessable()
        ->assertJsonPath('errors', fn (array $errors) => $errors['tags.0'] === ['タグを入力してください']
            && $errors['tags.1'] === ['タグは20文字以内で入力してください']);
});

it('PATCH で tags を送ると、タグを全部置き換える', function () {
    $id = $this->postJson('/api/my/works', taggedWorkPayload(['異世界', 'スローライフ']))->json('data.id');

    $response = $this->patchJson("/api/my/works/{$id}", ['tags' => ['スローライフ', '料理']])
        ->assertOk();

    expect($response->json('data.tags'))->toEqualCanonicalizing(['スローライフ', '料理'])
        ->and(DB::table('work_tags')->join('tags', 'tags.id', '=', 'work_tags.tag_id')
            ->where('work_id', $id)->pluck('name')->all())->toEqualCanonicalizing(['スローライフ', '料理']);
});

it('PATCH でタグだけを変えると updated_at を変える', function () {
    $id = $this->postJson('/api/my/works', taggedWorkPayload(['異世界']))->json('data.id');
    $updatedAt = Work::findOrFail($id)->updated_at;
    $this->travel(1)->days();

    $this->patchJson("/api/my/works/{$id}", ['tags' => ['料理']])->assertOk();

    expect(Work::findOrFail($id)->updated_at)->toBeGreaterThan($updatedAt);
});

it('PATCH で今と同じタグを送っても updated_at を変えない', function () {
    $id = $this->postJson('/api/my/works', taggedWorkPayload(['異世界']))->json('data.id');
    $updatedAt = Work::findOrFail($id)->updated_at;
    $this->travel(1)->days();

    $this->patchJson("/api/my/works/{$id}", ['tags' => ['異世界']])->assertOk();

    expect(Work::findOrFail($id)->updated_at)->toEqual($updatedAt);
});

it('PATCH で空の配列を送ると、タグを全部外す', function () {
    $id = $this->postJson('/api/my/works', taggedWorkPayload(['異世界']))->json('data.id');

    $this->patchJson("/api/my/works/{$id}", ['tags' => []])
        ->assertOk()
        ->assertJsonPath('data.tags', []);

    $this->assertDatabaseCount('work_tags', 0);
});

it('PATCH で tags を送らなければ、タグを変えない', function () {
    $id = $this->postJson('/api/my/works', taggedWorkPayload(['異世界']))->json('data.id');

    $this->patchJson("/api/my/works/{$id}", ['title' => '新しいタイトル'])
        ->assertOk()
        ->assertJsonPath('data.tags', ['異世界']);

    $this->assertDatabaseCount('work_tags', 1);
});

it('PATCH のタグの形式が正しくなければ 422 を返し、タグを変えない', function () {
    $id = $this->postJson('/api/my/works', taggedWorkPayload(['異世界']))->json('data.id');

    $this->patchJson("/api/my/works/{$id}", ['tags' => [str_repeat('あ', 21)]])
        ->assertUnprocessable()
        ->assertOnlyJsonValidationErrors(['tags.0']);

    $this->getJson("/api/my/works/{$id}")->assertJsonPath('data.tags', ['異世界']);
});

it('1 件の取得でタグを返す', function () {
    $id = $this->postJson('/api/my/works', taggedWorkPayload(['異世界']))->json('data.id');

    $this->getJson("/api/my/works/{$id}")
        ->assertOk()
        ->assertJsonPath('data.tags', ['異世界']);
});

it('一覧のクエリ数は作品の件数で変わらない', function () {
    $countQueries = function (): int {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->getJson('/api/my/works')->assertOk();
        DB::disableQueryLog();

        return count(DB::getQueryLog());
    };

    $this->postJson('/api/my/works', taggedWorkPayload(['異世界', 'スローライフ']))->assertCreated();
    $one = $countQueries();

    foreach (range(1, 4) as $i) {
        $this->postJson('/api/my/works', taggedWorkPayload(["タグ{$i}", '異世界']))->assertCreated();
    }
    $five = $countQueries();

    $this->getJson('/api/my/works')->assertJsonCount(5, 'data')
        ->assertJsonPath('data.0.tags', fn (array $tags) => $tags !== []);
    expect($five)->toBe($one);
});
