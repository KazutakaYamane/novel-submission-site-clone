<?php

namespace App\Http\Controllers\MyWorks;

use App\Http\Controllers\Controller;
use App\Http\Requests\MyWorks\StoreWorkRequest;
use App\Http\Resources\WorkResource;
use App\UseCases\Work\CreateWorkUseCase;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class StoreWorkController extends Controller
{
    public function __invoke(StoreWorkRequest $request, CreateWorkUseCase $createWorkUseCase): JsonResponse
    {
        $work = $createWorkUseCase(
            (string) Auth::id(),
            $request->string('title')->toString(),
            $request->filled('catchphrase') ? $request->string('catchphrase')->toString() : null,
            $request->string('introduction')->toString(),
            $request->genre(),
            $request->status(),
            $request->ratingFlags(),
            $request->tags(),
        );

        return (new WorkResource($work))->response()->setStatusCode(201);
    }
}
