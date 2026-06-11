<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

/**
 * 縦串疎通確認用のヘルスチェック。
 * Next.js(SSR) → Laravel API → DB の到達性を JSON で返す。
 * ALB ヘルスチェック用の無条件 200 は Nginx 側の /health が担う(役割が異なる)。
 */
class HealthController extends Controller
{
    public function __invoke(): JsonResponse
    {
        try {
            DB::connection()->getPdo();
            $database = 'ok';
            $status = 200;
        } catch (\Throwable) {
            $database = 'unreachable';
            $status = 503;
        }

        return response()->json([
            'status' => $status === 200 ? 'ok' : 'degraded',
            'database' => $database,
            'time' => now()->toIso8601String(),
        ], $status);
    }
}
