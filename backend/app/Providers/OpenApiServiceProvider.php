<?php

namespace App\Providers;

use Dedoc\Scramble\Scramble;
use Dedoc\Scramble\SecurityDocumentation\MiddlewareAuthSecurityStrategy;
use Dedoc\Scramble\Support\Generator\OpenApi;
use Dedoc\Scramble\Support\Generator\Server;
use Illuminate\Support\ServiceProvider;

// Scramble は dev 依存なので、インストールされているときだけ AppServiceProvider が登録する。
// 本番の config:cache が Scramble のクラスを読まないよう、設定は config/ に置かずここで入れる
class OpenApiServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        config([
            'scramble.export_path' => 'openapi.json',
            'scramble.security_strategy' => MiddlewareAuthSecurityStrategy::class,
        ]);

        // Laravel は JSON の API だけを公開する。定義は scramble:export でファイルにだけ出す
        Scramble::ignoreDefaultRoutes();
    }

    public function boot(): void
    {
        // Scramble は servers を url() で絶対 URL にするため、APP_URL ごとに定義が変わらないよう相対 URL で上書きする
        Scramble::configure()->withDocumentTransformers(function (OpenApi $openApi) {
            $openApi->servers = [Server::make('/api')];
        });
    }
}
