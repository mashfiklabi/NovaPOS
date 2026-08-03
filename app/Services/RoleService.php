<?php

namespace App\Services;

use App\Models\Role;
use Illuminate\Support\Facades\DB;

class RoleService
{
    public function __construct(
        protected AuditLogService $auditLogService
    ) {}

    /**
     * Create a new role with permissions.
     *
     * @param  array{name: string, description: string|null}  $data
     * @param  array<int>  $permissionIds
     */
    public function create(array $data, array $permissionIds): Role
    {
        return DB::transaction(function () use ($data, $permissionIds) {
            $role = Role::create($data);
            $role->permissions()->sync($permissionIds);

            $this->auditLogService->log(
                action: 'role_created',
                modelType: Role::class,
                modelId: $role->id,
                oldValues: null,
                newValues: [
                    'name' => $role->name,
                    'description' => $role->description,
                    'permission_ids' => $permissionIds,
                ]
            );

            return $role;
        });
    }

    /**
     * Update an existing role and its permissions.
     *
     * @param  array{name: string, description: string|null}  $data
     * @param  array<int>  $permissionIds
     */
    public function update(Role $role, array $data, array $permissionIds): Role
    {
        return DB::transaction(function () use ($role, $data, $permissionIds) {
            $oldValues = [
                'name' => $role->name,
                'description' => $role->description,
                'permission_ids' => $role->permissions()->pluck('id')->toArray(),
            ];

            $role->update($data);
            $role->permissions()->sync($permissionIds);

            $this->auditLogService->log(
                action: 'role_updated',
                modelType: Role::class,
                modelId: $role->id,
                oldValues: $oldValues,
                newValues: [
                    'name' => $role->name,
                    'description' => $role->description,
                    'permission_ids' => $permissionIds,
                ]
            );

            return $role;
        });
    }

    /**
     * Delete a role.
     */
    public function delete(Role $role): void
    {
        if ($role->name === 'Admin') {
            throw new \InvalidArgumentException('The Admin role cannot be deleted.');
        }

        DB::transaction(function () use ($role) {
            $oldValues = [
                'name' => $role->name,
                'description' => $role->description,
            ];

            // Detach associations before soft deleting
            $role->permissions()->detach();
            $role->users()->detach();
            $role->delete();

            $this->auditLogService->log(
                action: 'role_deleted',
                modelType: Role::class,
                modelId: $role->id,
                oldValues: $oldValues,
                newValues: null
            );
        });
    }
}
