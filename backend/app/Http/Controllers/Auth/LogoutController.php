<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\UseCases\Auth\LogoutUseCase;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class LogoutController extends Controller
{
    public function __invoke(Request $request, LogoutUseCase $logoutUseCase): Response
    {
        $logoutUseCase((string) $request->bearerToken());

        return response()->noContent();
    }
}
