<?php

namespace App\Http\Controllers\MyWorks;

use App\Domain\Episode\EmptyEpisodeBody;
use App\Domain\Episode\EpisodeNotFound;
use App\Domain\Episode\InvalidEpisodeTransition;
use App\Domain\Work\WorkAccessDenied;
use App\Domain\Work\WorkNotFound;
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
        } catch (WorkNotFound|EpisodeNotFound) {
            abort(404);
        } catch (WorkAccessDenied) {
            abort(403);
        } catch (EmptyEpisodeBody) {
            throw ValidationException::withMessages(['body' => '本文が空の話は公開できません。']);
        } catch (InvalidEpisodeTransition) {
            throw ValidationException::withMessages(['status' => 'すでに公開されている話です。']);
        }
    }
}
