<?php

namespace App\Http\Controllers\RolePermission;

use App\Http\Controllers\Controller;
use App\Http\Requests\RolePermission\StoreRoleRequest;
use App\Http\Requests\RolePermission\UpdateRoleRequest;
use App\Models\Role;
use App\Services\RolePermission\RoleService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class RoleController extends Controller
{
    /**
     * Display roles and assignable system permissions.
     */
    public function index(RoleService $roleService): View
    {
        return view('roles.index', [
            'roles' => $roleService->paginateRoles(),
            'permissionsByGroup' => $roleService->activePermissionsGrouped(),
        ]);
    }

    /**
     * Store a newly created role.
     */
    public function store(StoreRoleRequest $request, RoleService $roleService): RedirectResponse
    {
        $roleService->createRole($request->validated());

        return redirect()
            ->route('roles.index')
            ->with('success', 'Role created successfully.');
    }

    /**
     * Update the specified role.
     */
    public function update(
        UpdateRoleRequest $request,
        Role $role,
        RoleService $roleService,
    ): RedirectResponse {
        $roleService->updateRole($role, $request->validated());

        return redirect()
            ->route('roles.index')
            ->with('success', 'Role updated successfully.');
    }

    /**
     * Activate the specified role.
     */
    public function activate(Role $role, RoleService $roleService): RedirectResponse
    {
        $roleService->activate($role);

        return redirect()
            ->route('roles.index')
            ->with('success', 'Role activated successfully.');
    }

    /**
     * Deactivate the specified role.
     */
    public function deactivate(Role $role, RoleService $roleService): RedirectResponse
    {
        $roleService->deactivate($role);

        return redirect()
            ->route('roles.index')
            ->with('success', 'Role deactivated successfully.');
    }
}
