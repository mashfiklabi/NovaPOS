<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class UserService
{
    public function __construct(
        protected AuditLogService $auditLogService
    ) {}

    /**
     * Create a new user with roles.
     *
     * @param array{name: string, email: string, password: string, status: string} $data
     * @param array<int> $roleIds
     */
    public function create(array $data, array $roleIds): User
    {
        return DB::transaction(function () use ($data, $roleIds) {
            $user = User::create($data);
            $user->roles()->sync($roleIds);

            $this->auditLogService->log(
                action: 'user_created',
                modelType: User::class,
                modelId: $user->id,
                oldValues: null,
                newValues: [
                    'name' => $user->name,
                    'email' => $user->email,
                    'status' => $user->status,
                    'role_ids' => $roleIds,
                ]
            );

            return $user;
        });
    }

    /**
     * Update an existing user.
     *
     * @param array{name: string, email: string, password?: string|null, status: string} $data
     * @param array<int> $roleIds
     */
    public function update(User $user, array $data, array $roleIds): User
    {
        return DB::transaction(function () use ($user, $data, $roleIds) {
            $oldValues = [
                'name' => $user->name,
                'email' => $user->email,
                'status' => $user->status,
                'role_ids' => $user->roles()->pluck('id')->toArray(),
            ];

            if (empty($data['password'])) {
                unset($data['password']);
            }

            $user->update($data);
            $user->roles()->sync($roleIds);

            $this->auditLogService->log(
                action: 'user_updated',
                modelType: User::class,
                modelId: $user->id,
                oldValues: $oldValues,
                newValues: [
                    'name' => $user->name,
                    'email' => $user->email,
                    'status' => $user->status,
                    'role_ids' => $roleIds,
                ]
            );

            return $user;
        });
    }

    /**
     * Delete a user (soft delete).
     */
    public function delete(User $user): void
    {
        // Prevent deleting oneself
        if (auth()->id() === $user->id) {
            throw new \InvalidArgumentException('You cannot delete your own account.');
        }

        DB::transaction(function () use ($user) {
            $oldValues = [
                'name' => $user->name,
                'email' => $user->email,
                'status' => $user->status,
            ];

            // Soft delete user
            $user->delete();

            $this->auditLogService->log(
                action: 'user_deleted',
                modelType: User::class,
                modelId: $user->id,
                oldValues: $oldValues,
                newValues: null
            );
        });
    }
}
