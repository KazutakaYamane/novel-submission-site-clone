<?php

namespace App\Http\Controllers\PublicWorks;

use App\Domain\Work\WorkNotFoundException;
use App\Http\Controllers\Controller;
use App\Http\Resources\PublicWorkResource;
use App\UseCases\PublicWork\GetPublishedWorkUseCase;

class ShowPublicWorkController extends Controller
{
    public function __invoke(string $work, GetPublishedWorkUseCase $getPublishedWorkUseCase): PublicWorkResource
    {
        try {
            return new PublicWorkResource($getPublishedWorkUseCase($work));
        } catch (WorkNotFoundException) {
            abort(404);
        }
    }
}
