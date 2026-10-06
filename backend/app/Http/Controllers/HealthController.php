<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Throwable;

// AWS 上で RDS / ElastiCache / Service Connect の疎通を確認するための API。
// ALB のヘルスチェックは DB に依存しない nginx の /health が担うため、ここは使わない。
class HealthController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $checks = [
            'database' => $this->check(fn () => DB::select('select 1')),
            'redis' => $this->check(fn () => Redis::connection()->ping()),
        ];

        return response()->json([
            'status' => in_array(false, array_column($checks, 'ok'), true) ? 'degraded' : 'ok',
            'checks' => $checks,
            // nginx が REMOTE_ADDR に入れた値。8080 経由で X-User-IP が反映されることを確認できる
            'client_ip' => $request->ip(),
        ], 200);
    }

    /** @return array{ok: bool, error?: string} */
    private function check(callable $probe): array
    {
        try {
            $probe();

            return ['ok' => true];
        } catch (Throwable $e) {
            return ['ok' => false, 'error' => $e::class];
        }
    }
}
