<?php

declare(strict_types=1);

namespace Rominas\Users\Controllers;

use Rominas\Users\Actions\CreateUserAction;
use Rominas\Users\Actions\DeleteUserAction;
use Rominas\Users\Actions\UpdateProfileAction;
use Rominas\Users\Actions\UpdateUserAction;
use Rominas\Users\Factories\ProfileDataFactory;
use Rominas\Users\Factories\UserDataFactory;
use Rominas\Users\QueryBuilders\UserQueryBuilder;
use Rominas\Users\Requests\CreateUserRequest;
use Rominas\Users\Requests\UpdateProfileRequest;
use Rominas\Users\Requests\UpdateUserRequest;
use Rominas\Users\Resources\UserResource;
use Rominas\Users\Model\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

use function response;

class UsersController
{
    public function index(Request $request): AnonymousResourceCollection
    {
        /** @var User $user */
        $user = $request->user();

        return UserResource::collection(
            User::query()
                ->with(['roles', 'permissions'])
                ->visibleToUser($user)
                ->search($request->query('search'))
                ->orderBy(
                    $request->query('orderBy'),
                    $request->query('orderDir', 'asc'),
                )
                ->paginate($request->query('perPage', UserQueryBuilder::PER_PAGE)),
        );
    }

    public function show(User $user): UserResource
    {
        return new UserResource($user);
    }

    /**
     * The authenticated admin's own account, with roles and effective permissions — the
     * management dashboard hydrates the current user from here after login.
     */
    public function me(Request $request): UserResource
    {
        /** @var User $user */
        $user = $request->user();

        return new UserResource($user->load(['roles', 'permissions']));
    }

    public function create(CreateUserRequest $request, CreateUserAction $createUserAction): JsonResponse
    {
        $user = DB::transaction(
            fn() => $createUserAction->execute(UserDataFactory::fromRequest($request)),
        );

        return (new UserResource($user))->response()->setStatusCode(201);
    }

    public function update(User $user, UpdateUserRequest $request, UpdateUserAction $updateUserAction): UserResource
    {
        $user = DB::transaction(
            fn() => $updateUserAction->execute($user, UserDataFactory::fromRequest($request)),
        );

        return new UserResource($user);
    }

    public function updateProfile(
        User $user,
        UpdateProfileRequest $request,
        UpdateProfileAction $updateProfileAction,
    ): UserResource {
        $user = $updateProfileAction->execute($user, ProfileDataFactory::fromRequest($request));

        return new UserResource($user);
    }

    public function delete(User $user, DeleteUserAction $deleteUserAction): JsonResponse
    {
        $result = $deleteUserAction->execute($user);

        return response()->json(['success' => $result]);
    }
}
