<?php

namespace App\Support\Access;

use App\Models\Permission;
use Illuminate\Support\Collection;

class PermissionCatalog
{
    public function bundles(?string $module = null, ?string $search = null): Collection
    {
        return Permission::query()
            ->when(filled($search), function ($query) use ($search): void {
                $query->where(function ($query) use ($search): void {
                    $query
                        ->where('code', 'like', '%'.$search.'%')
                        ->orWhere('name', 'like', '%'.$search.'%')
                        ->orWhere('route', 'like', '%'.$search.'%');
                });
            })
            ->when(filled($module), fn ($query) => $query->where('module', $module))
            ->orderBy('module')
            ->orderBy('route')
            ->orderBy('action')
            ->orderBy('name')
            ->get()
            ->groupBy(fn (Permission $permission): string => $this->featureKey($permission))
            ->map(fn (Collection $permissions, string $featureKey): array => $this->bundle($permissions, $featureKey))
            ->sortBy([
                ['module', 'asc'],
                ['label', 'asc'],
            ]);
    }

    public function enforceReadDependencies(array $permissionIds): array
    {
        $selected = collect($permissionIds)->map(fn ($permissionId): int => (int) $permissionId);

        foreach ($this->bundles() as $bundle) {
            $hasSelectedNonRead = collect($bundle['actions'])
                ->reject(fn (array $actionGroup, string $action): bool => $action === 'read')
                ->flatMap(fn (array $actionGroup): array => $actionGroup['permission_ids'])
                ->contains(fn (int $permissionId): bool => $selected->contains($permissionId));

            if ($hasSelectedNonRead) {
                $selected = $selected->merge($bundle['read_permission_ids']);
            }
        }

        return $selected->unique()->values()->all();
    }

    public function readDependenciesFor(Permission $permission): array
    {
        if ($this->actionKey($permission->action) === 'read') {
            return [];
        }

        return $this->bundles()->get($this->featureKey($permission))['read_permission_ids'] ?? [];
    }

    public function modules(): array
    {
        return Permission::query()
            ->select('module')
            ->distinct()
            ->orderBy('module')
            ->pluck('module', 'module')
            ->prepend('Semua Module', '')
            ->all();
    }

    public function featureKey(Permission $permission): string
    {
        $suffix = '.'.$permission->action;

        if (str_ends_with($permission->code, $suffix)) {
            return substr($permission->code, 0, -strlen($suffix));
        }

        return $permission->route ?: $permission->code;
    }

    public function actionKey(string $action): string
    {
        return match ($action) {
            'view' => 'read',
            'edit' => 'update',
            default => $action,
        };
    }

    private function bundle(Collection $permissions, string $featureKey): array
    {
        /** @var Permission $firstPermission */
        $firstPermission = $permissions->first();
        $actions = $permissions
            ->groupBy(fn (Permission $permission): string => $this->actionKey($permission->action))
            ->map(fn (Collection $actionPermissions, string $action): array => [
                'action' => $action,
                'label' => $this->actionLabel($action),
                'permission_ids' => $actionPermissions->pluck('id')->map(fn ($id): int => (int) $id)->values()->all(),
                'permissions' => $actionPermissions
                    ->sortBy(fn (Permission $permission): int => $this->actionOrder($permission->action))
                    ->values(),
            ])
            ->sortBy(fn (array $group): int => $this->actionOrder($group['action']))
            ->all();

        return [
            'module' => $firstPermission->module,
            'label' => $this->featureName($firstPermission),
            'feature' => $this->featureName($firstPermission),
            'feature_key' => $featureKey,
            'route' => $firstPermission->route,
            'permission_ids' => $permissions->pluck('id')->map(fn ($id): int => (int) $id)->values()->all(),
            'read_permission_ids' => $actions['read']['permission_ids'] ?? [],
            'permissions' => $permissions
                ->sortBy(fn (Permission $permission): int => $this->actionOrder($permission->action))
                ->values(),
            'actions' => $actions,
            'action_groups' => collect($actions)->values(),
            'requires_read' => $permissions->contains(fn (Permission $permission): bool => $this->actionKey($permission->action) !== 'read')
                && ! array_key_exists('read', $actions),
        ];
    }

    private function featureName(Permission $permission): string
    {
        return trim(preg_replace(
            '/^(Lihat|Tambah|Buat|Kelola|Edit|Ubah|Hapus|Submit)\s+/i',
            '',
            $permission->name,
        )) ?: $permission->name;
    }

    private function actionLabel(string $action): string
    {
        return [
            'read' => 'Read',
            'create' => 'Create',
            'update' => 'Update',
            'delete' => 'Delete',
            'manage' => 'Legacy Manage',
        ][$action] ?? ucfirst($action);
    }

    private function actionOrder(string $action): int
    {
        return [
            'view' => 10,
            'read' => 10,
            'create' => 20,
            'edit' => 30,
            'update' => 30,
            'delete' => 40,
            'manage' => 90,
        ][$action] ?? 90;
    }
}
