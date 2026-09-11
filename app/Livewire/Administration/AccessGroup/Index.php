<?php

namespace App\Livewire\Administration\AccessGroup;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Support\Access\PermissionCatalog;
use Illuminate\Contracts\View\View;
use Illuminate\Database\QueryException;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use WithPagination;

    public string $search = '';

    public int $perPage = 10;

    public bool $showForm = false;

    public bool $showDeleteModal = false;

    public ?int $editingId = null;

    public ?int $deletingId = null;

    public string $nama_role = '';

    public string $selectedUserId = '';

    /** @var array<int, int> */
    public array $permissionIds = [];

    /** @var array<int, int> */
    public array $userIds = [];

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function create(): void
    {
        $this->authorizePermission('access-groups.create');

        $this->resetForm();
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        $this->authorizePermission('access-groups.update');

        $role = Role::query()
            ->with(['permissions:id', 'users:id'])
            ->findOrFail($id);

        $this->showForm = true;
        $this->editingId = $role->id;
        $this->nama_role = $role->nama_role;
        $this->permissionIds = $role->permissions->pluck('id')->all();
        $this->userIds = $role->users->pluck('id')->all();
    }

    public function addUserFromPicker(string|int $userId): void
    {
        $this->addUser($userId);

        $this->selectedUserId = '';
    }

    private function addUser(string|int|null $userId): void
    {
        if ($userId === null || $userId === '') {
            return;
        }

        $userId = (int) $userId;

        User::findOrFail($userId);

        if (! in_array($userId, $this->userIds, true)) {
            $this->userIds[] = $userId;
        }
    }

    public function removeUser(int $userId): void
    {
        $this->userIds = array_values(array_filter(
            $this->userIds,
            fn (int $selectedUserId): bool => $selectedUserId !== $userId,
        ));
    }

    public function save(): void
    {
        $this->authorizePermission($this->editingId ? 'access-groups.update' : 'access-groups.create');

        $this->permissionIds = app(PermissionCatalog::class)->enforceReadDependencies($this->permissionIds);

        $validated = $this->validate([
            'nama_role' => [
                'required',
                'string',
                'max:255',
                Rule::unique('roles', 'nama_role')->ignore($this->editingId),
            ],
            'permissionIds' => ['array'],
            'permissionIds.*' => ['integer', Rule::exists('permissions', 'id')],
            'userIds' => ['array'],
            'userIds.*' => ['integer', Rule::exists('users', 'id')],
        ]);

        $role = Role::query()->updateOrCreate(
            ['id' => $this->editingId],
            ['nama_role' => $validated['nama_role']],
        );

        $role->permissions()->sync($this->permissionIds);
        $role->users()->sync($this->userIds);

        $this->resetForm();
        $this->resetPage();
    }

    public function togglePermission(int $permissionId): void
    {
        $permission = Permission::findOrFail($permissionId);

        if ($this->hasPermissionSelected($permissionId)) {
            $this->permissionIds = array_values(array_filter(
                $this->permissionIds,
                fn (int $selectedPermissionId): bool => $selectedPermissionId !== $permissionId,
            ));
            $this->enforceReadDependencies();

            return;
        }

        $this->permissionIds[] = $permissionId;
        $this->permissionIds = array_merge(
            $this->permissionIds,
            app(PermissionCatalog::class)->readDependenciesFor($permission),
        );
        $this->normalizePermissionIds();
    }

    public function toggleBundle(string $bundleKey): void
    {
        $permissionBundles = app(PermissionCatalog::class)->bundles()->all();
        $bundle = $permissionBundles[$bundleKey] ?? null;

        if ($bundle === null) {
            return;
        }

        if ($this->allSelected($bundle['permission_ids'])) {
            $this->permissionIds = array_values(array_diff($this->permissionIds, $bundle['permission_ids']));
            $this->enforceReadDependencies();

            return;
        }

        $this->permissionIds = array_merge($this->permissionIds, $bundle['permission_ids']);
        $this->normalizePermissionIds();
    }

    public function toggleActionGroup(string $bundleKey, string $action): void
    {
        $permissionBundles = app(PermissionCatalog::class)->bundles()->all();
        $bundle = $permissionBundles[$bundleKey] ?? null;
        $actionGroup = $bundle['actions'][$action] ?? null;

        if ($bundle === null || $actionGroup === null) {
            return;
        }

        if ($this->allSelected($actionGroup['permission_ids'])) {
            $this->permissionIds = array_values(array_diff($this->permissionIds, $actionGroup['permission_ids']));
            $this->enforceReadDependencies();

            return;
        }

        $this->permissionIds = array_merge($this->permissionIds, $actionGroup['permission_ids']);

        if ($action !== 'read') {
            $this->permissionIds = array_merge($this->permissionIds, $bundle['read_permission_ids']);
        }

        $this->normalizePermissionIds();
    }

    public function confirmDelete(int $id): void
    {
        $this->authorizePermission('access-groups.delete');

        Role::findOrFail($id);

        $this->resetErrorBag('delete');
        $this->deletingId = $id;
        $this->showDeleteModal = true;
    }

    public function delete(): void
    {
        $this->authorizePermission('access-groups.delete');

        if ($this->deletingId === null) {
            return;
        }

        $role = Role::findOrFail($this->deletingId);

        try {
            $role->permissions()->detach();
            $role->users()->detach();
            $role->delete();
        } catch (QueryException) {
            $this->addError('delete', 'Group akses masih digunakan pada data approval.');

            return;
        }

        $this->cancelDelete();
        $this->resetPage();
    }

    public function cancelDelete(): void
    {
        $this->resetErrorBag('delete');
        $this->reset(['deletingId', 'showDeleteModal']);
    }

    public function cancel(): void
    {
        $this->resetForm();
    }

    public function getRolesProperty(): LengthAwarePaginator
    {
        return Role::query()
            ->withCount(['permissions', 'users'])
            ->when($this->search !== '', function ($query): void {
                $query->where('nama_role', 'like', '%'.$this->search.'%');
            })
            ->orderBy('nama_role')
            ->paginate($this->perPage);
    }

    public function getUsersProperty(): Collection
    {
        return User::query()
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'jabatan']);
    }

    public function getSelectedUsersProperty(): Collection
    {
        return $this->users
            ->whereIn('id', $this->userIds)
            ->values();
    }

    public function render(): View
    {
        return view('livewire.administration.access-groups.index', [
            'roles' => $this->roles,
            'permissionBundles' => app(PermissionCatalog::class)->bundles(),
            'users' => $this->users,
            'selectedUsers' => $this->selectedUsers,
            'canCreate' => auth()->user()?->hasPermission('access-groups.create') ?? false,
            'canUpdate' => auth()->user()?->hasPermission('access-groups.update') ?? false,
            'canDelete' => auth()->user()?->hasPermission('access-groups.delete') ?? false,
        ])->layout('components.layouts.app', ['title' => 'Group Akses']);
    }

    private function resetForm(): void
    {
        $this->reset([
            'editingId',
            'showForm',
            'nama_role',
            'selectedUserId',
            'permissionIds',
            'userIds',
        ]);
    }

    private function normalizePermissionIds(): void
    {
        $this->permissionIds = collect($this->permissionIds)
            ->map(fn ($permissionId): int => (int) $permissionId)
            ->unique()
            ->values()
            ->all();
    }

    private function enforceReadDependencies(): void
    {
        $this->permissionIds = app(PermissionCatalog::class)->enforceReadDependencies($this->permissionIds);
    }

    private function hasPermissionSelected(int $permissionId): bool
    {
        return in_array($permissionId, $this->permissionIds, true);
    }

    private function allSelected(array $permissionIds): bool
    {
        if ($permissionIds === []) {
            return false;
        }

        return collect($permissionIds)->every(fn (int $permissionId): bool => $this->hasPermissionSelected($permissionId));
    }

    private function authorizePermission(string $permissionCode): void
    {
        abort_unless(auth()->user()?->hasPermission($permissionCode), 403);
    }
}
