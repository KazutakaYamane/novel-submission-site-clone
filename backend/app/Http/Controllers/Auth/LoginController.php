<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Resources\UserResource;
use App\UseCases\User\GetUserUseCase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class LoginController extends Controller
{
    public function __invoke(LoginRequest $request, GetUserUseCase $getUserUseCase): UserResource
    {
        // メールアドレスの存在を漏らさないよう、未登録とパスワード誤りを同じエラーにする
        // (応答時間は Auth::attempt の Timebox がそろえる)
        if (! Auth::attempt($request->only('email', 'password'))) {
            throw ValidationException::withMessages([
                'email' => 'メールアドレスまたはパスワードが正しくありません',
            ]);
        }

        // SessionGuard::login がセッション ID を振り直すので、ここで regenerate しない
        return new UserResource($getUserUseCase((string) Auth::id()));
    }
}
