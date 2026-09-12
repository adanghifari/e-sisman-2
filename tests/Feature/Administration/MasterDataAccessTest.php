<?php

namespace Tests\Feature\Administration;

use App\Livewire\MasterData\BusinessFunction\Index as BusinessFunctionIndex;
use App\Livewire\MasterData\BusinessProcess\Index as BusinessProcessIndex;
use App\Livewire\MasterData\Department\Index as DepartmentIndex;
use App\Livewire\MasterData\DocumentType\Index as DocumentTypeIndex;
use App\Models\BusinessProcess;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class MasterDataAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_master_data_viewer_only_sees_read_only_business_process_page(): void
    {
        BusinessProcess::query()->create([
            'kode' => 'OPS',
            'nama_proses_bisnis' => 'Operasional',
        ]);
        $viewer = $this->userWithPermission('master-data.business-processes.view');

        Livewire::actingAs($viewer)
            ->test(BusinessProcessIndex::class)
            ->assertSee('List Proses Bisnis')
            ->assertSee('Operasional')
            ->assertDontSee('Tambah Data')
            ->assertDontSee('Edit proses bisnis')
            ->assertDontSee('Hapus proses bisnis');
    }

    public function test_master_data_viewer_cannot_run_create_update_or_delete_actions(): void
    {
        $businessProcess = BusinessProcess::query()->create([
            'kode' => 'OPS',
            'nama_proses_bisnis' => 'Operasional',
        ]);
        $viewer = $this->userWithPermission('master-data.business-processes.view');

        Livewire::actingAs($viewer)
            ->test(BusinessProcessIndex::class)
            ->call('create')
            ->assertForbidden();

        Livewire::actingAs($viewer)
            ->test(BusinessProcessIndex::class)
            ->call('edit', $businessProcess->id)
            ->assertForbidden();

        Livewire::actingAs($viewer)
            ->test(BusinessProcessIndex::class)
            ->call('toggleStatus', $businessProcess->id)
            ->assertForbidden();

        Livewire::actingAs($viewer)
            ->test(BusinessProcessIndex::class)
            ->call('confirmDelete', $businessProcess->id)
            ->assertForbidden();

        Livewire::actingAs($viewer)
            ->test(BusinessProcessIndex::class)
            ->set('kode', 'SMR')
            ->set('nama_proses_bisnis', 'Sistem Manajemen')
            ->call('save')
            ->assertForbidden();
    }

    private function userWithPermission(string $permissionCode): User
    {
        $permission = Permission::query()->firstOrCreate(
            ['code' => $permissionCode],
            [
                'name' => $permissionCode,
                'module' => 'Master Data',
                'route' => 'master-data.business-processes',
                'action' => Str::afterLast($permissionCode, '.'),
            ],
        );
        $role = Role::query()->firstOrCreate(['nama_role' => 'Role '.$permissionCode]);
        $user = User::factory()->create();

        $role->permissions()->syncWithoutDetaching([$permission->id]);
        $user->roles()->attach($role);

        return $user->refresh();
    }

    public function test_master_data_components_resolve_by_canonical_and_aliased_names(): void
    {
        // BusinessFunction
        $this->assertSame(BusinessFunctionIndex::class, Livewire::getClass('master-data.business-function.index'));
        $this->assertSame(BusinessFunctionIndex::class, Livewire::getClass('master-data.process-functions'));
        $this->assertSame(BusinessFunctionIndex::class, Livewire::getClass('master-data.process-functions.index'));
        $this->assertSame(BusinessFunctionIndex::class, Livewire::getClass('master-data.business-functions'));
        $this->assertSame(BusinessFunctionIndex::class, Livewire::getClass('app.livewire.master-data.business-function.index'));
        $this->assertSame(BusinessFunctionIndex::class, Livewire::getClass('app.livewire.master-data.process-functions'));

        // BusinessProcess
        $this->assertSame(BusinessProcessIndex::class, Livewire::getClass('master-data.business-process.index'));
        $this->assertSame(BusinessProcessIndex::class, Livewire::getClass('master-data.business-processes'));
        $this->assertSame(BusinessProcessIndex::class, Livewire::getClass('app.livewire.master-data.business-process.index'));
        $this->assertSame(BusinessProcessIndex::class, Livewire::getClass('app.livewire.master-data.business-processes'));

        // Department
        $this->assertSame(DepartmentIndex::class, Livewire::getClass('master-data.department.index'));
        $this->assertSame(DepartmentIndex::class, Livewire::getClass('master-data.departments'));
        $this->assertSame(DepartmentIndex::class, Livewire::getClass('app.livewire.master-data.department.index'));
        $this->assertSame(DepartmentIndex::class, Livewire::getClass('app.livewire.master-data.departments'));

        // DocumentType
        $this->assertSame(DocumentTypeIndex::class, Livewire::getClass('master-data.document-type.index'));
        $this->assertSame(DocumentTypeIndex::class, Livewire::getClass('master-data.document-types'));
        $this->assertSame(DocumentTypeIndex::class, Livewire::getClass('app.livewire.master-data.document-type.index'));
        $this->assertSame(DocumentTypeIndex::class, Livewire::getClass('app.livewire.master-data.document-types'));
    }
}
