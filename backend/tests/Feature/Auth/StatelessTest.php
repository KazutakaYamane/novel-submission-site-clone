<?php

// ブラウザからの直接アクセスと同じ Origin を付けても、セッション cookie と XSRF-TOKEN を発行しないことを確かめる
beforeEach(function () {
    $this->withHeader('Origin', 'http://localhost:3000');
});

it('デモログイン、/api/me、ログアウトの応答に cookie を付けない', function () {
    $start = $this->postJson('/api/demo/sessions', ['role' => 'reader'])->assertCreated();
    $token = $start->json('token');

    $me = $this->withToken($token)->getJson('/api/me')->assertOk();

    $this->app['auth']->forgetGuards();
    $logout = $this->withToken($token)->postJson('/api/auth/logout')->assertNoContent();

    expect($start->headers->getCookies())->toBeEmpty()
        ->and($me->headers->getCookies())->toBeEmpty()
        ->and($logout->headers->getCookies())->toBeEmpty();
});
