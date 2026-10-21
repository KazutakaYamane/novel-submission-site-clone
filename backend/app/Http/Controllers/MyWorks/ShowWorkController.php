<?php

namespace App\Http\Controllers\MyWorks;

use App\Domain\Work\WorkAccessDenied;
use App\Domain\Work\WorkNotFound;
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
        } catch (WorkNotFound) {
            abort(404);
        } catch (WorkAccessDenied) {
            abort(403);
        }
    }
}
