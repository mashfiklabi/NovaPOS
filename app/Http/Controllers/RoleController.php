<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleController extends Controller
{
    /**
     * Display a listing of roles and system permissions.
     */
    public function index(): Response
    {
        $this->authorize('roles.view');

        // Eager load permissions
        $roles = Role::with('permissions')->get();
        $permissions = Permission::all();

        return Inertia::render('Roles/Index', [
            'roles' => $roles,
            'permissions' => $permissions,
        ]);
    }

    /**
     * Store a newly created role in storage.
     */
    public function store(Request $request): RedirectResponse
    {
        $this->authorize('roles.create');

        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:roles,name',
            'permission_names' => 'required|array',
            'permission_names.*' => 'exists:permissions,name',
        ]);

        $role = Role::create(['name' => $validated['name'], 'guard_name' => 'web']);
        $role->syncPermissions($validated['permission_names']);

        activity()
            ->performedOn($role)
            ->event('created')
            ->log("Created custom security role: {$role->name}");

        return redirect()->back()->with('success', 'Security role created successfully.');
    }

    /**
     * Update the specified role in storage.
     */
    public function update(Request $request, Role $role): RedirectResponse
    {
        $this->authorize('roles.update');

        if ($role->name === 'Super Admin') {
            return redirect()->back()->withErrors(['error' => 'The Super Admin role is protected and cannot be edited.']);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:roles,name,'.$role->id,
            'permission_names' => 'required|array',
            'permission_names.*' => 'exists:permissions,name',
        ]);

        $role->update(['name' => $validated['name']]);
        $role->syncPermissions($validated['permission_names']);

        activity()
            ->performedOn($role)
            ->event('updated')
            ->log("Updated security role: {$role->name}");

        return redirect()->back()->with('success', 'Security role updated successfully.');
    }

    /**
     * Remove the specified role from storage.
     */
    public function destroy(Role $role): RedirectResponse
    {
        $this->authorize('roles.delete');

        if ($role->name === 'Super Admin') {
            return redirect()->back()->withErrors(['error' => 'The Super Admin role cannot be deleted.']);
        }

        activity()
            ->performedOn($role)
            ->event('deleted')
            ->log("Permanently deleted role: {$role->name}");

        $role->delete();

        return redirect()->back()->with('success', 'Security role deleted successfully.');
    }
}
