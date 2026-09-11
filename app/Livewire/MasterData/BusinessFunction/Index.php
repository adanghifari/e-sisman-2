<?php

namespace App\Livewire\MasterData\BusinessFunction;

use App\Actions\MasterData\BusinessFunction\CreateBusinessFunction;
use App\Actions\MasterData\BusinessFunction\DeleteBusinessFunction;
use App\Actions\MasterData\BusinessFunction\ToggleBusinessFunctionStatus;
use App\Actions\MasterData\BusinessFunction\UpdateBusinessFunction;
use App\Livewire\MasterData\Concerns\HandlesMasterDataCrudState;
use App\Models\BusinessFunction;
use Illuminate\Contracts\View\View;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Validation\ValidationException;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use HandlesMasterDataCrudState;
    use WithPagination;

    public string $kode = '';

    public string $nama_proses_fungsi = '';

    public function edit(int $id): void
    {
        $this->authorizePermission('update');

        $businessFunction = BusinessFunction::findOrFail($id);

        $this->showForm = true;
        $this->editingId = $businessFunction->id;
        $this->kode = $businessFunction->kode;
        $this->nama_proses_fungsi = $businessFunction->nama_proses_fungsi;
        $this->is_active = $businessFunction->is_active;
    }

    /**
     * @throws ValidationException
     */
    public function save(
        CreateBusinessFunction $createBusinessFunction,
        UpdateBusinessFunction $updateBusinessFunction,
    ): void {
        $this->authorizePermission($this->editingId ? 'update' : 'create');

        $data = [
            'kode' => $this->kode,
            'nama_proses_fungsi' => $this->nama_proses_fungsi,
            'is_active' => $this->is_active,
        ];

        if ($this->editingId) {
            $businessFunction = BusinessFunction::findOrFail($this->editingId);

            $updateBusinessFunction->handle($businessFunction, $data);
        } else {
            $createBusinessFunction->handle($data);
        }

        $this->resetForm();
        $this->resetPage();
    }

    public function toggleStatus(
        int $id,
        ToggleBusinessFunctionStatus $toggleBusinessFunctionStatus,
    ): void {
        $this->authorizePermission('update');

        $businessFunction = BusinessFunction::findOrFail($id);

        $toggleBusinessFunctionStatus->handle($businessFunction);
    }

    /**
     * @throws ValidationException
     */
    public function delete(DeleteBusinessFunction $deleteBusinessFunction): void
    {
        $this->authorizePermission('delete');

        if ($this->deletingId === null) {
            return;
        }

        $businessFunction = BusinessFunction::findOrFail($this->deletingId);

        $deleteBusinessFunction->handle($businessFunction);

        $this->cancelDelete();
        $this->resetPage();
    }

    public function getBusinessFunctionsProperty(): LengthAwarePaginator
    {
        return BusinessFunction::query()
            ->when($this->search !== '', function ($query): void {
                $query->where(function ($query): void {
                    $query
                        ->where('kode', 'like', '%'.$this->search.'%')
                        ->orWhere('nama_proses_fungsi', 'like', '%'.$this->search.'%');
                });
            })
            ->when($this->status !== '', function ($query): void {
                $query->where('is_active', $this->status === 'active');
            })
            ->orderBy('nama_proses_fungsi')
            ->paginate($this->perPage);
    }

    public function render(): View
    {
        return view('livewire.master-data.process-functions.index', [
            'businessFunctions' => $this->businessFunctions,
            'statusOptions' => [
                '' => 'Semua Status',
                'active' => 'Active',
                'inactive' => 'Inactive',
            ],
        ])->layout('components.layouts.app', ['title' => 'Proses / Fungsi']);
    }

    protected function masterDataModelClass(): string
    {
        return BusinessFunction::class;
    }

    protected function permissionPrefix(): string
    {
        return 'master-data.process-functions';
    }

    protected function resetForm(): void
    {
        $this->resetMasterDataForm([
            'kode',
            'nama_proses_fungsi',
        ]);
    }
}
