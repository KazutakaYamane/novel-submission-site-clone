<?php

namespace App\Http\Controllers\MyWorks;

use App\Domain\Episode\EpisodeNotFoundException;
use App\Domain\Episode\InvalidEpisodeTransitionException;
use App\Domain\Work\WorkAccessDeniedException;
use App\Domain\Work\WorkNotFoundException;
use App\Http\Controllers\Controller;
use App\Http\Resources\EpisodeResource;
use App\UseCases\Episode\UnpublishEpisodeUseCase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class UnpublishEpisodeController extends Controller
{
    /**
     * @throws ValidationException
     */
    public function __invoke(string $work, string $episode, UnpublishEpisodeUseCase $unpublishEpisodeUseCase): EpisodeResource
    {
        try {
            return new EpisodeResource($unpublishEpisodeUseCase((string) Auth::id(), $work, $episode));
        } catch (WorkNotFoundException|EpisodeNotFoundException) {
            abort(404);
        } catch (WorkAccessDeniedException) {
            abort(403);
        } catch (InvalidEpisodeTransitionException) {
            throw ValidationException::withMessages(['status' => '公開されていない話です。']);
        }
    }
}
