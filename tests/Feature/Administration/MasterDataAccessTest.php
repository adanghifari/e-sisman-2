<?php

namespace Tests\Feature\Administration;

use App\Livewire\MasterData\BusinessProcess\Index as BusinessProcessIndex;
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
}
