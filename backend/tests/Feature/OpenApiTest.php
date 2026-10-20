<?php

use Illuminate\Support\Facades\Artisan;

beforeEach(function () {
    $path = tempnam(sys_get_temp_dir(), 'openapi');
    Artisan::call('scramble:export', ['--path' => $path]);
    $this->document = json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
    unlink($path);
});

it('すべての API の操作を含む', function () {
    expect($this->document['paths'])->toHaveKeys([
        '/health',
        '/auth/register',
        '/auth/login',
        '/auth/logout',
        '/me',
    ]);
});

// APP_URL を使うと環境ごとに定義が変わり、生成物の差分チェックが落ちる
it('servers は /api の相対 URL に固定する', function () {
    expect($this->document['servers'])->toBe([['url' => '/api']]);
});

it('auth:sanctum のルートにだけ Bearer の security scheme を付ける', function () {
    expect($this->document['components']['securitySchemes']['http'])
        ->toMatchArray(['type' => 'http', 'scheme' => 'bearer']);

    // 操作の security が無ければ文書全体の security が適用される(OpenAPI の仕様)
    $security = fn (string $path, string $method) => $this->document['paths'][$path][$method]['security']
        ?? $this->document['security']
        ?? [];

    $bearer = [['http' => []]];
    expect($security('/me', 'get'))->toBe($bearer)
        ->and($security('/auth/logout', 'post'))->toBe($bearer)
        ->and($security('/auth/login', 'post'))->toBe([])
        ->and($security('/auth/register', 'post'))->toBe([])
        ->and($security('/health', 'get'))->toBe([]);
});

it('登録とログインの応答にトークンと有効期限を含む', function (string $path, string $status) {
    $schema = $this->document['paths'][$path]['post']['responses'][$status]['content']['application/json']['schema'];

    expect($schema['required'] ?? [])->toContain('data', 'token', 'expires_at')
        ->and($schema['properties']['token']['type'])->toBe('string')
        ->and($schema['properties']['expires_at']['type'])->toBe('string');
})->with([
    ['/auth/register', '201'],
    ['/auth/login', '200'],
]);

it('ヘルスチェックの応答に依存先ごとの結果の型を含む', function () {
    $checks = $this->document['paths']['/health']['get']['responses']['200']['content']['application/json']['schema']['properties']['checks'];

    expect($checks['required'])->toBe(['database', 'redis']);
    foreach (['database', 'redis'] as $name) {
        expect($checks['properties'][$name]['properties']['ok']['type'])->toBe('boolean')
            ->and($checks['properties'][$name]['properties']['error']['type'])->toBe('string')
            ->and($checks['properties'][$name]['required'])->toBe(['ok']);
    }
});

// Laravel は JSON の API だけを公開し、ドキュメントの画面は持たない
it('Scramble の既定のドキュメントのルートを公開しない', function (string $uri) {
    $this->get($uri)->assertNotFound();
})->with(['/docs/api', '/docs/api.json']);
