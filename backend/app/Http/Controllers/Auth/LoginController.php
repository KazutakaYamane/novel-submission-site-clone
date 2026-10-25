<?php

namespace App\Http\Controllers\Auth;

use App\Domain\Auth\InvalidCredentialsException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Resources\UserResource;
use App\UseCases\Auth\LoginUseCase;
use DateTimeInterface;
use Illuminate\Validation\ValidationException;

class LoginController extends Controller
{
    public function __invoke(LoginRequest $request, LoginUseCase $loginUseCase): UserResource
    {
        try {
            $authenticated = $loginUseCase(
                $request->string('email')->toString(),
                $request->string('password')->toString(),
            );
        } catch (InvalidCredentialsException) {
            throw ValidationException::withMessages([
                'email' => 'メールアドレスまたはパスワードが正しくありません',
            ]);
        }

        return (new UserResource($authenticated->user))->additional([
            'token' => $authenticated->token->token,
            'expires_at' => $authenticated->token->expiresAt->format(DateTimeInterface::ATOM),
        ]);
    }
}
