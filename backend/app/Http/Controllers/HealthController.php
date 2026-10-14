<?php

namespace App\Http\Controllers;

use App\Domain\Health\CheckResult;
use App\UseCases\Health\CheckHealthUseCase;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

// AWS 上で RDS / ElastiCache / Service Connect の疎通を確認するための API。
// ALB のヘルスチェックは DB に依存しない nginx の /health が担うため、ここは使わない。
class HealthController extends Controller
{
    public function __invoke(Request $request, CheckHealthUseCase $checkHealthUseCase): JsonResponse
    {
        $results = $checkHealthUseCase();

        $checks = array_map(
            fn (CheckResult $r) => $r->ok ? ['ok' => true] : ['ok' => false, 'error' => $r->error],
            $results,
        );

        return response()->json([
            'status' => in_array(false, array_column($checks, 'ok'), true) ? 'degraded' : 'ok',
            'checks' => $checks,
            // nginx が REMOTE_ADDR に入れた値。8080 経由で X-User-IP が反映されることを確認できる
            'client_ip' => $request->ip(),
        ], 200);
    }
}
