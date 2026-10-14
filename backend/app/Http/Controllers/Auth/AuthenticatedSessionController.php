<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class AuthenticatedSessionController extends Controller
{
    public function store(LoginRequest $request): JsonResponse
    {
        if (! Auth::guard('web')->attempt($request->validated())) {
            return response()->json([
                'message' => 'メールアドレスまたはパスワードが正しくありません。',
                'errors' => [
                    'email' => ['メールアドレスまたはパスワードが正しくありません。'],
                ],
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $request->session()->regenerate();

        return response()->json([
            'message' => 'ログインしました。',
        ]);
    }

    public function destroy(Request $request): Response
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->noContent();
    }
}
