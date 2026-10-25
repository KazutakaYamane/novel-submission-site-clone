<?php

namespace App\Http\Controllers\MyWorks;

use App\Domain\Episode\EmptyEpisodeBodyException;
use App\Domain\Episode\EpisodeNotFoundException;
use App\Domain\Work\WorkAccessDeniedException;
use App\Domain\Work\WorkNotFoundException;
use App\Http\Controllers\Controller;
use App\Http\Requests\MyWorks\UpdateEpisodeRequest;
use App\Http\Resources\EpisodeResource;
use App\UseCases\Episode\UpdateMyEpisodeUseCase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class UpdateEpisodeController extends Controller
{
    public function __invoke(string $work, string $episode, UpdateEpisodeRequest $request, UpdateMyEpisodeUseCase $updateMyEpisodeUseCase): EpisodeResource
    {
        try {
            return new EpisodeResource($updateMyEpisodeUseCase((string) Auth::id(), $work, $episode, $request->changes()));
        } catch (WorkNotFoundException|EpisodeNotFoundException) {
            abort(404);
        } catch (WorkAccessDeniedException) {
            abort(403);
        } catch (EmptyEpisodeBodyException) {
            throw ValidationException::withMessages(['body' => '公開中の話の本文は空にできません。']);
        }
    }
}
