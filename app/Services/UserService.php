<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class UserService
{
    /**
     * Create a new user with Spatie roles.
     *
     * @param  array<string, mixed>  $data
     * @param  array<string>  $roleNames
     */
    public function create(array $data, array $roleNames, ?UploadedFile $avatar = null): User
    {
        return DB::transaction(function () use ($data, $roleNames, $avatar) {
            if ($avatar) {
                $data['avatar'] = $avatar->store('avatars', 'public');
            }

            $user = User::create($data);
            $user->syncRoles($roleNames);

            return $user;
        });
    }

    /**
     * Update an existing user and their Spatie roles.
     *
     * @param  array<string, mixed>  $data
     * @param  array<string>  $roleNames
     */
    public function update(User $user, array $data, array $roleNames, ?UploadedFile $avatar = null): User
    {
        return DB::transaction(function () use ($user, $data, $roleNames, $avatar) {
            if ($avatar) {
                if ($user->avatar) {
                    Storage::disk('public')->delete($user->avatar);
                }
                $data['avatar'] = $avatar->store('avatars', 'public');
            }

            if (empty($data['password'])) {
                unset($data['password']);
            }

            $user->update($data);
            $user->syncRoles($roleNames);

            return $user;
        });
    }

    /**
     * Soft delete a user.
     */
    public function delete(User $user): void
    {
        if (auth()->id() === $user->id) {
            throw new \InvalidArgumentException('You cannot delete your own profile.');
        }

        DB::transaction(function () use ($user) {
            $user->delete();
        });
    }

    /**
     * Permanently force-delete a user (for Breeze profile termination).
     */
    public function forceDelete(User $user): void
    {
        DB::transaction(function () use ($user) {
            if ($user->avatar) {
                Storage::disk('public')->delete($user->avatar);
            }
            $user->forceDelete();
        });
    }
}
