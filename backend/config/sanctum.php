<?php

// Next.js のサーバーが Bearer トークンだけで認証し、Laravel はセッションを使わない(docs/adr/0011-sanctum-api-token.md)
return [

    // 空にすると、Sanctum はセッションのガードを試さずにトークンだけで認証する
    'guard' => [],

    // セッションを使わないので、XSRF-TOKEN を発行する csrf-cookie のルートを登録しない
    'routes' => false,

    // 応答の expires_at と、Next.js が auth_token cookie に付ける有効期限もこの値から決まる
    'expiration' => (int) env('SANCTUM_TOKEN_EXPIRATION_MINUTES', 10080),

    'token_prefix' => env('SANCTUM_TOKEN_PREFIX', ''),

];
