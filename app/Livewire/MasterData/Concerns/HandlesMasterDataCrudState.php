<?php

namespace App\Livewire\MasterData\Concerns;

trait HandlesMasterDataCrudState
{
    public string $search = '';

    public string $status = '';

    public int $perPage = 10;

    public ?int $editingId = null;

    public bool $showForm = false;

    public bool $showDeleteModal = false;

    public ?int $deletingId = null;

    public bool $is_active = true;

    abstract protected function masterDataModelClass(): string;

    abstract protected function permissionPrefix(): string;

    abstract protected function resetForm(): void;

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingStatus(): void
    {
        $this->resetPage();
    }

    public function create(): void
    {
        $this->authorizePermission('create');
        $this->resetForm();

        $this->showForm = true;
        $this->is_active = true;
    }

    public function confirmDelete(int $id): void
    {
        $this->authorizePermission('delete');
        $modelClass = $this->masterDataModelClass();

        $modelClass::findOrFail($id);

        $this->resetErrorBag('delete');
        $this->deletingId = $id;
        $this->showDeleteModal = true;
    }

    public function cancelDelete(): void
    {
        $this->resetErrorBag('delete');
        $this->reset([
            'deletingId',
            'showDeleteModal',
        ]);
    }

    public function cancel(): void
    {
        $this->resetForm();
    }

    protected function resetMasterDataForm(array $fields): void
    {
        $this->reset([
            'editingId',
            'showForm',
            ...$fields,
        ]);

        $this->is_active = true;
    }

    public function getCanCreateProperty(): bool
    {
        return $this->can('create');
    }

    public function getCanUpdateProperty(): bool
    {
        return $this->can('update');
    }

    public function getCanDeleteProperty(): bool
    {
        return $this->can('delete');
    }

    protected function authorizePermission(string $action): void
    {
        abort_unless($this->can($action), 403);
    }

    private function can(string $action): bool
    {
        return auth()->user()?->hasPermission($this->permissionPrefix().'.'.$action) ?? false;
    }
}
