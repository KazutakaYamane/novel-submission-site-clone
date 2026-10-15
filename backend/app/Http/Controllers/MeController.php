<?php

namespace App\Http\Controllers;

use App\Http\Resources\UserResource;
use App\UseCases\User\GetUserUseCase;
use Illuminate\Support\Facades\Auth;

class MeController extends Controller
{
    public function __invoke(GetUserUseCase $getUserUseCase): UserResource
    {
        return new UserResource($getUserUseCase((string) Auth::id()));
    }
}
