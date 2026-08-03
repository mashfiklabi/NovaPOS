<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\UserStatus;
use App\Models\User;
use App\Services\UserService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Enum;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    public function __construct(
        protected UserService $userService
    ) {}

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): Response
    {
        $this->authorize('users.view');

        $search = $request->input('search');

        // Eager load roles
        $users = User::with('roles')
            ->when($search, function ($query, $search) {
                $query->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            })
            ->orderBy('id', 'desc')
            ->paginate(10)
            ->withQueryString();

        $roles = Role::select('id', 'name')->get();

        return Inertia::render('Users/Index', [
            'users' => $users,
            'roles' => $roles,
            'filters' => [
                'search' => $search,
            ],
        ]);
    }

    /**
     * Store a newly created user in storage.
     */
    public function store(Request $request): RedirectResponse
    {
        $this->authorize('users.create');

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
            'phone' => 'nullable|string|max:50',
            'status' => ['required', new Enum(UserStatus::class)],
            'role_names' => 'required|array|min:1',
            'role_names.*' => 'exists:roles,name',
            'avatar' => 'nullable|image|max:2048',
        ]);

        $roleNames = $validated['role_names'];
        unset($validated['role_names']);

        $avatar = $request->file('avatar');
        unset($validated['avatar']);

        $this->userService->create($validated, $roleNames, $avatar);

        return redirect()->back()->with('success', 'User account created successfully.');
    }

    /**
     * Update the specified user in storage.
     */
    public function update(Request $request, User $user): RedirectResponse
    {
        $this->authorize('users.update');

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users,email,'.$user->id,
            'password' => 'nullable|string|min:8|confirmed',
            'phone' => 'nullable|string|max:50',
            'status' => ['required', new Enum(UserStatus::class)],
            'role_names' => 'required|array|min:1',
            'role_names.*' => 'exists:roles,name',
            'avatar' => 'nullable|image|max:2048',
        ]);

        $roleNames = $validated['role_names'];
        unset($validated['role_names']);

        $avatar = $request->file('avatar');
        unset($validated['avatar']);

        $this->userService->update($user, $validated, $roleNames, $avatar);

        return redirect()->back()->with('success', 'User account updated successfully.');
    }

    /**
     * Remove the specified user from storage.
     */
    public function destroy(User $user): RedirectResponse
    {
        $this->authorize('users.delete');

        try {
            $this->userService->delete($user);

            return redirect()->back()->with('success', 'User soft-deleted successfully.');
        } catch (\InvalidArgumentException $e) {
            return redirect()->back()->withErrors(['error' => $e->getMessage()]);
        }
    }
}
