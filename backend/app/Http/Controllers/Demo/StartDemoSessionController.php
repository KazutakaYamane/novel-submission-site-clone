<?php

namespace App\Http\Controllers\Demo;

use App\Domain\Demo\DemoRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Demo\StartDemoSessionRequest;
use App\Http\Resources\UserResource;
use App\UseCases\Demo\StartDemoSessionUseCase;
use DateTimeInterface;
use Illuminate\Http\JsonResponse;

class StartDemoSessionController extends Controller
{
    public function __invoke(StartDemoSessionRequest $request, StartDemoSessionUseCase $startDemoSessionUseCase): JsonResponse
    {
        $session = $startDemoSessionUseCase(DemoRole::from($request->string('role')->toString()));

        return (new UserResource($session->authenticated->user))
            ->additional([
                'token' => $session->authenticated->token->token,
                'expires_at' => $session->authenticated->token->expiresAt->format(DateTimeInterface::ATOM),
                'sample_work_id' => $session->sampleWorkId,
            ])
            ->response()
            ->setStatusCode(201);
    }
}
