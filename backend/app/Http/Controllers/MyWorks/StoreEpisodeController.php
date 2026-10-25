<?php

namespace App\Http\Controllers\MyWorks;

use App\Domain\Work\WorkAccessDeniedException;
use App\Domain\Work\WorkNotFoundException;
use App\Http\Controllers\Controller;
use App\Http\Requests\MyWorks\StoreEpisodeRequest;
use App\Http\Resources\EpisodeResource;
use App\UseCases\Episode\CreateEpisodeDraftUseCase;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class StoreEpisodeController extends Controller
{
    public function __invoke(string $work, StoreEpisodeRequest $request, CreateEpisodeDraftUseCase $createEpisodeDraftUseCase): JsonResponse
    {
        try {
            $episode = $createEpisodeDraftUseCase(
                (string) Auth::id(),
                $work,
                $request->string('title')->toString(),
                $request->string('body')->toString(),
            );
        } catch (WorkNotFoundException) {
            abort(404);
        } catch (WorkAccessDeniedException) {
            abort(403);
        }

        return (new EpisodeResource($episode))->response()->setStatusCode(201);
    }
}
