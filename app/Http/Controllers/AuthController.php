<?php

namespace App\Http\Controllers;

use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Http\Requests\StorePasswordRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\PersonalAccessToken;

class AuthController extends Controller
{
    public function register(RegisterRequest $request): JsonResponse
    {
        $user = User::create([
            ...$request->validated(),
            'is_admin' => false,
            'first_login' => false,
        ]);

        return response()->json($user, 201);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $credentials = $request->validated();
        $user = User::where('email', $credentials['email'])->first();

        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            return response()->json([
                'type' => 'https://httpstatuses.com/401',
                'title' => 'Invalid credentials',
                'status' => 401,
                'message' => 'The provided credentials are incorrect.',
            ], 401);
        }

        return $this->tokenResponse($user);
    }

    public function logout(Request $request): Response
    {
        $request->user()->currentAccessToken()?->delete();

        return response()->noContent();
    }

    public function storePassword(StorePasswordRequest $request): JsonResponse
    {
        $user = $request->user();

        abort_unless($user->first_login, 403);

        $user->update([
            'password' => $request->validated('password'),
            'first_login' => false,
        ]);

        $currentToken = $user->currentAccessToken();
        $user->tokens()
            ->when(
                $currentToken instanceof PersonalAccessToken,
                fn ($query) => $query->whereKeyNot($currentToken->getKey()),
            )
            ->delete();

        return response()->json($user->refresh());
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json($request->user());
    }

    private function tokenResponse(User $user, int $status = 200): JsonResponse
    {
        return response()->json([
            'user' => $user,
            'first_login' => $user->first_login,
            'token' => $user->createToken('auth-token')->plainTextToken,
            'token_type' => 'Bearer',
        ], $status);
    }
}
