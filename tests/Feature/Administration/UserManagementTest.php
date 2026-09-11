<?php

namespace Tests\Feature\Administration;

use App\Livewire\Administration\User\Index;
use App\Models\Department;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_management_page_can_be_rendered(): void
    {
        User::factory()->create([
            'name' => 'Nadia Putri',
            'nik' => '0000114',
            'jabatan' => 'Quality Assurance Officer',
            'no_whatsapp' => '628123456789',
        ]);
        $viewer = $this->userWithPermission('users.view');

        Livewire::actingAs($viewer)
            ->test(Index::class)
            ->assertSee('List User')
            ->assertSee('Nadia Putri')
            ->assertSee('Quality Assurance Officer')
            ->assertSee('628123456789')
            ->assertDontSee('Edit user');
    }

    public function test_user_can_be_updated_from_management_page(): void
    {
        $department = Department::create([
            'kode_department' => 'QA',
            'nama_department' => 'Quality Assurance',
        ]);
        $role = Role::create(['nama_role' => 'Admin Kontrol Dokumen']);
        $user = User::factory()->create([
            'm_department_id' => null,
            'jabatan' => null,
            'no_whatsapp' => null,
            'is_active' => true,
        ]);
        $editor = $this->userWithPermission('users.update');

        Livewire::actingAs($editor)
            ->test(Index::class)
            ->call('edit', $user->id)
            ->set('m_department_id', (string) $department->id)
            ->set('role_id', (string) $role->id)
            ->set('jabatan', 'Document Controller')
            ->set('no_whatsapp', '628765432100')
            ->set('is_active', false)
            ->call('save')
            ->assertSet('showForm', false);

        $user->refresh();

        $this->assertSame($department->id, $user->m_department_id);
        $this->assertSame('Document Controller', $user->jabatan);
        $this->assertSame('628765432100', $user->no_whatsapp);
        $this->assertFalse($user->is_active);
        $this->assertTrue($user->roles()->whereKey($role->id)->exists());
    }

    public function test_user_viewer_cannot_run_update_actions(): void
    {
        $viewer = $this->userWithPermission('users.view');
        $user = User::factory()->create();

        Livewire::actingAs($viewer)
            ->test(Index::class)
            ->call('edit', $user->id)
            ->assertForbidden();

        Livewire::actingAs($viewer)
            ->test(Index::class)
            ->set('editingId', $user->id)
            ->call('save')
            ->assertForbidden();
    }

    private function userWithPermission(string $permissionCode): User
    {
        $permission = Permission::query()->firstOrCreate(
            ['code' => $permissionCode],
            [
                'name' => $permissionCode,
                'module' => 'Administrasi',
                'route' => 'users.index',
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
