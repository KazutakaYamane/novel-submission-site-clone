<?php

use App\Infrastructure\Eloquent\Models\UserModel;

it('ログアウトすると 204 を返し、使ったトークンだけが無効になる', function () {
    $user = UserModel::factory()->create();
    $loggedOut = $user->createToken('web')->plainTextToken;
    $other = $user->createToken('web')->plainTextToken;

    $this->withToken($loggedOut)->postJson('/api/auth/logout')->assertNoContent();

    // テストではアプリが使い回され、sanctum ガードが前のリクエストで解決したユーザーを持ち越すため、リクエストごとにガードを作り直す
    $this->app['auth']->forgetGuards();
    $this->withToken($loggedOut)->getJson('/api/me')->assertUnauthorized();

    $this->app['auth']->forgetGuards();
    $this->withToken($other)->getJson('/api/me')->assertOk();
});

it('トークンなしでログアウトすると 401 を返す', function () {
    $this->postJson('/api/auth/logout')->assertUnauthorized();
});
