<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // JSON だけを返す API なのでログイン画面を持たない。未ログインは AuthenticationException の 401 にする
        $middleware->redirectGuestsTo(fn () => null);
        // 話の本文は段落の頭の全角スペースや末尾の空行も作者が書いた体裁なので削らない(Str::trim は全角スペースも削る)。
        // TrimStrings はルートが決まる前に動くのでルート名では判定できず、パスで判定する。
        // タイトルは StoreEpisodeRequest・UpdateEpisodeRequest が trim する
        $middleware->trimStrings(except: [
            fn (Request $request) => $request->is('api/my/works/*/episodes', 'api/my/works/*/episodes/*'),
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
