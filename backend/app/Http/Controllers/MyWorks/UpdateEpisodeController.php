<?php

namespace App\Http\Controllers\MyWorks;

use App\Domain\Episode\EmptyEpisodeBody;
use App\Domain\Episode\EpisodeNotFound;
use App\Domain\Work\WorkAccessDenied;
use App\Domain\Work\WorkNotFound;
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
        } catch (WorkNotFound|EpisodeNotFound) {
            abort(404);
        } catch (WorkAccessDenied) {
            abort(403);
        } catch (EmptyEpisodeBody) {
            throw ValidationException::withMessages(['body' => '公開中の話の本文は空にできません。']);
        }
    }
}
