<?php

namespace App\Http\Controllers\MyWorks;

use App\Domain\Episode\EpisodeNotFound;
use App\Domain\Episode\InvalidEpisodeTransition;
use App\Domain\Work\WorkAccessDenied;
use App\Domain\Work\WorkNotFound;
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
        } catch (WorkNotFound|EpisodeNotFound) {
            abort(404);
        } catch (WorkAccessDenied) {
            abort(403);
        } catch (InvalidEpisodeTransition) {
            throw ValidationException::withMessages(['status' => '公開されていない話です。']);
        }
    }
}
