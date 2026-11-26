<?php

use Illuminate\Support\Facades\Artisan;

// 個人情報を保持しないため、利用者を作る経路はデモログインだけにする(ADR 0015)
it('会員登録とメールアドレス・パスワードでのログインの API は 404 を返す', function (string $path, array $body) {
    $this->postJson($path, $body)->assertNotFound();
})->with([
    ['/api/auth/register', ['name' => '山田太郎', 'email' => 'taro@example.com', 'password' => 'password123']],
    ['/api/auth/login', ['email' => 'taro@example.com', 'password' => 'password123']],
]);

it('OpenAPI に会員登録とログインの操作を含めない', function () {
    $path = tempnam(sys_get_temp_dir(), 'openapi');
    Artisan::call('scramble:export', ['--path' => $path]);
    $document = json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
    unlink($path);

    expect($document['paths'])->not->toHaveKey('/auth/register')
        ->and($document['paths'])->not->toHaveKey('/auth/login');
});
