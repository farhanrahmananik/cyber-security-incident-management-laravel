<?php

namespace App\Http\Controllers\UserManagement;

use App\Http\Controllers\Controller;
use App\Http\Requests\UserManagement\StoreUserRequest;
use App\Http\Requests\UserManagement\UpdateUserRequest;
use App\Models\User;
use App\Services\UserManagement\UserService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class UserController extends Controller
{
    /**
     * Display managed users.
     */
    public function index(UserService $userService): View
    {
        return view('users.index', [
            'users' => $userService->paginateUsers(),
            'roles' => $userService->activeRoles(),
        ]);
    }

    /**
     * Store a newly created user.
     */
    public function store(StoreUserRequest $request, UserService $userService): RedirectResponse
    {
        $userService->createUser($request->validated());

        return redirect()
            ->route('users.index')
            ->with('success', 'User created successfully.');
    }

    /**
     * Update the specified user.
     */
    public function update(
        UpdateUserRequest $request,
        User $user,
        UserService $userService,
    ): RedirectResponse {
        $userService->updateUser($user, $request->validated());

        return redirect()
            ->route('users.index')
            ->with('success', 'User updated successfully.');
    }

    /**
     * Activate the specified user.
     */
    public function activate(User $user, UserService $userService): RedirectResponse
    {
        $userService->activate($user);

        return redirect()
            ->route('users.index')
            ->with('success', 'User activated successfully.');
    }

    /**
     * Deactivate the specified user.
     */
    public function deactivate(
        Request $request,
        User $user,
        UserService $userService,
    ): RedirectResponse {
        $userService->deactivate($user, $request->user());

        return redirect()
            ->route('users.index')
            ->with('success', 'User deactivated successfully.');
    }
}
