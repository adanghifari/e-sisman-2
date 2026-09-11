<?php

namespace App\Livewire\Administration\User;

use App\Models\Department;
use App\Models\Role;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use WithPagination;

    public string $search = '';

    public string $role = '';

    public string $department = '';

    public string $status = '';

    public int $perPage = 10;

    public bool $showForm = false;

    public ?int $editingId = null;

    public string $m_department_id = '';

    public string $role_id = '';

    public string $jabatan = '';

    public string $no_whatsapp = '';

    public bool $is_active = true;

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingRole(): void
    {
        $this->resetPage();
    }

    public function updatingDepartment(): void
    {
        $this->resetPage();
    }

    public function updatingStatus(): void
    {
        $this->resetPage();
    }

    public function getUsersProperty(): LengthAwarePaginator
    {
        return User::query()
            ->with(['department', 'roles'])
            ->when($this->search !== '', function ($query): void {
                $query->where(function ($query): void {
                    $query
                        ->where('name', 'like', '%'.$this->search.'%')
                        ->orWhere('nik', 'like', '%'.$this->search.'%')
                        ->orWhere('jabatan', 'like', '%'.$this->search.'%')
                        ->orWhere('email', 'like', '%'.$this->search.'%');
                });
            })
            ->when($this->role !== '', function ($query): void {
                $query->whereHas('roles', fn ($query) => $query->whereKey($this->role));
            })
            ->when($this->department !== '', function ($query): void {
                $query->where('m_department_id', $this->department);
            })
            ->when($this->status !== '', function ($query): void {
                $query->where('is_active', $this->status === 'active');
            })
            ->orderBy('name')
            ->paginate($this->perPage);
    }

    public function edit(int $id): void
    {
        $this->authorizePermission('users.update');

        $user = User::query()
            ->with('roles:id')
            ->findOrFail($id);

        $this->showForm = true;
        $this->editingId = $user->id;
        $this->m_department_id = (string) ($user->m_department_id ?? '');
        $this->role_id = (string) ($user->roles->first()?->id ?? '');
        $this->jabatan = $user->jabatan ?? '';
        $this->no_whatsapp = $user->no_whatsapp ?? '';
        $this->is_active = $user->is_active;
    }

    public function save(): void
    {
        $this->authorizePermission('users.update');

        $validated = $this->validate([
            'm_department_id' => ['nullable', 'integer', Rule::exists('departments', 'id')],
            'role_id' => ['nullable', 'integer', Rule::exists('roles', 'id')],
            'jabatan' => ['nullable', 'string', 'max:255'],
            'no_whatsapp' => ['nullable', 'string', 'max:30'],
            'is_active' => ['boolean'],
        ]);

        $user = User::findOrFail($this->editingId);

        $user->update([
            'm_department_id' => $validated['m_department_id'] !== '' ? $validated['m_department_id'] : null,
            'jabatan' => filled($validated['jabatan']) ? trim($validated['jabatan']) : null,
            'no_whatsapp' => filled($validated['no_whatsapp']) ? trim($validated['no_whatsapp']) : null,
            'is_active' => $validated['is_active'],
        ]);

        $validated['role_id'] !== ''
            ? $user->roles()->sync([(int) $validated['role_id']])
            : $user->roles()->detach();

        $this->cancel();
        $this->resetPage();
    }

    public function cancel(): void
    {
        $this->reset([
            'showForm',
            'editingId',
            'm_department_id',
            'role_id',
            'jabatan',
            'no_whatsapp',
            'is_active',
        ]);

        $this->is_active = true;
    }

    public function getRoleOptionsProperty(): array
    {
        return ['' => 'Semua Role'] + Role::query()
            ->orderBy('nama_role')
            ->pluck('nama_role', 'id')
            ->all();
    }

    public function getDepartmentOptionsProperty(): array
    {
        return ['' => 'Semua Department'] + Department::query()
            ->orderBy('nama_department')
            ->pluck('nama_department', 'id')
            ->all();
    }

    public function render(): View
    {
        return view('livewire.administration.users.index', [
            'users' => $this->users,
            'roleOptions' => $this->roleOptions,
            'departmentOptions' => $this->departmentOptions,
            'statusOptions' => [
                '' => 'Semua Status',
                'active' => 'Active',
                'inactive' => 'Inactive',
            ],
            'canUpdate' => auth()->user()?->hasPermission('users.update') ?? false,
        ])->layout('components.layouts.app', ['title' => 'User']);
    }

    private function authorizePermission(string $permissionCode): void
    {
        abort_unless(auth()->user()?->hasPermission($permissionCode), 403);
    }
}
