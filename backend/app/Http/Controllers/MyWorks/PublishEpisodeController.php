<?php

namespace App\Http\Controllers\MyWorks;

use App\Domain\Episode\EmptyEpisodeBodyException;
use App\Domain\Episode\EpisodeNotFoundException;
use App\Domain\Episode\InvalidEpisodeTransitionException;
use App\Domain\Work\WorkAccessDeniedException;
use App\Domain\Work\WorkNotFoundException;
use App\Http\Controllers\Controller;
use App\Http\Resources\EpisodeResource;
use App\UseCases\Episode\PublishEpisodeUseCase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class PublishEpisodeController extends Controller
{
    /**
     * @throws ValidationException
     */
    public function __invoke(string $work, string $episode, PublishEpisodeUseCase $publishEpisodeUseCase): EpisodeResource
    {
        try {
            return new EpisodeResource($publishEpisodeUseCase((string) Auth::id(), $work, $episode));
        } catch (WorkNotFoundException|EpisodeNotFoundException) {
            abort(404);
        } catch (WorkAccessDeniedException) {
            abort(403);
        } catch (EmptyEpisodeBodyException) {
            throw ValidationException::withMessages(['body' => '本文が空の話は公開できません。']);
        } catch (InvalidEpisodeTransitionException) {
            throw ValidationException::withMessages(['status' => 'すでに公開されている話です。']);
        }
    }
}
