<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

class UserController extends Controller
{
    public function index(): LengthAwarePaginator
    {
        Gate::authorize('viewAny', User::class);

        return User::query()->paginate();
    }

    public function store(StoreUserRequest $request): JsonResponse
    {
        $temporaryPassword = Str::password(16);
        $user = User::create([
            ...$request->validated(),
            'password' => $temporaryPassword,
            'first_login' => true,
        ]);

        return response()->json([
            ...$user->toArray(),
            'temporary_password' => $temporaryPassword,
        ], 201);
    }

    public function show(User $user): User
    {
        Gate::authorize('view', $user);

        return $user;
    }

    public function update(UpdateUserRequest $request, User $user): User
    {
        $user->update($request->validated());

        return $user->refresh();
    }

    public function destroy(User $user): Response
    {
        Gate::authorize('delete', $user);

        $user->delete();

        return response()->noContent();
    }
}
