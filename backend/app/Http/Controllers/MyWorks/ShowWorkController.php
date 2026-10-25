<?php

namespace App\Http\Controllers\MyWorks;

use App\Domain\Work\WorkAccessDeniedException;
use App\Domain\Work\WorkNotFoundException;
use App\Http\Controllers\Controller;
use App\Http\Resources\WorkResource;
use App\UseCases\Work\GetMyWorkUseCase;
use Illuminate\Support\Facades\Auth;

class ShowWorkController extends Controller
{
    public function __invoke(string $work, GetMyWorkUseCase $getMyWorkUseCase): WorkResource
    {
        try {
            return new WorkResource($getMyWorkUseCase((string) Auth::id(), $work));
        } catch (WorkNotFoundException) {
            abort(404);
        } catch (WorkAccessDeniedException) {
            abort(403);
        }
    }
}
