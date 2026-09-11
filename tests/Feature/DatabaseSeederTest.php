<?php

namespace Tests\Feature;

use App\Models\BusinessFunction;
use App\Models\BusinessProcess;
use App\Models\Department;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DatabaseSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_database_seeder_creates_departments_and_baseline_roles(): void
    {
        $this->seed(DatabaseSeeder::class);

        foreach (['ITSM', 'HCGA', 'PMK', 'HSSE', 'MOP', 'SDV'] as $departmentCode) {
            $this->assertTrue(Department::query()->where('kode_department', $departmentCode)->exists());
        }

        $this->assertFalse(Department::query()->where('kode_department', 'ITMS')->exists());
        $this->assertFalse(Department::query()->where('kode_department', 'DEFAULT')->exists());
        $this->assertSame(3, BusinessProcess::query()->count());
        $this->assertSame(11, BusinessFunction::query()->count());
        $this->assertTrue(BusinessProcess::query()->where('kode', 'Utama')->exists());
        $this->assertTrue(BusinessFunction::query()->where('kode', 'OPS')->exists());

        $userRole = Role::query()->where('nama_role', 'User')->firstOrFail();
        $documentControlRole = Role::query()->where('nama_role', 'Admin Kontrol Dokumen')->firstOrFail();
        $superAdminRole = Role::query()->where('nama_role', 'SuperAdmin')->firstOrFail();

        $allPermissionIds = Permission::query()->pluck('id')->sort()->values()->all();
        $this->assertSame($allPermissionIds, $superAdminRole->permissions()->pluck('permissions.id')->sort()->values()->all());

        $this->assertTrue($documentControlRole->permissions()->where('module', 'Manajemen Dokumen')->exists());
        $this->assertTrue($documentControlRole->permissions()->where('module', 'Administrasi')->where('action', 'view')->exists());
        $this->assertTrue($documentControlRole->permissions()->where('code', 'approval-flows.create')->exists());
        $this->assertTrue($documentControlRole->permissions()->where('code', 'approval-flows.update')->exists());
        $this->assertTrue($documentControlRole->permissions()->where('code', 'approval-flows.delete')->exists());
        $this->assertTrue($documentControlRole->permissions()->where('code', 'documents.obsolete.create')->exists());
        $this->assertTrue($documentControlRole->permissions()->where('code', 'documents.obsolete.restore')->exists());
        $this->assertFalse($documentControlRole->permissions()->where('code', 'users.create')->exists());
        $this->assertFalse($documentControlRole->permissions()->where('code', 'users.update')->exists());
        $this->assertFalse($documentControlRole->permissions()->where('code', 'users.delete')->exists());

        $this->assertTrue($userRole->permissions()->where('code', 'documents.master.view')->exists());
        $this->assertTrue($userRole->permissions()->where('code', 'documents.master.detail')->exists());
        $this->assertTrue($userRole->permissions()->where('code', 'documents.master.download')->exists());
        $this->assertTrue($userRole->permissions()->where('code', 'documents.master.preview')->exists());
        $this->assertTrue($userRole->permissions()->where('code', 'documents.obsolete.view')->exists());
        $this->assertTrue($userRole->permissions()->where('code', 'documents.obsolete.detail')->exists());
        $this->assertTrue($userRole->permissions()->where('code', 'documents.obsolete.download')->exists());
        $this->assertTrue($userRole->permissions()->where('code', 'documents.obsolete.preview')->exists());
        $this->assertFalse($userRole->permissions()->where('code', 'documents.obsolete.create')->exists());
        $this->assertFalse($userRole->permissions()->where('code', 'documents.obsolete.restore')->exists());
        $this->assertFalse($userRole->permissions()->where('code', 'documents.approval.assign')->exists());

        $documentControlAdmin = User::query()->where('email', 'admin.kontrol@example.com')->firstOrFail();
        $this->assertTrue($documentControlAdmin->roles()->whereKey($documentControlRole->id)->exists());
        $this->assertTrue($documentControlAdmin->hasExplicitPermission('approval-flows.create'));
        $this->assertTrue($documentControlAdmin->hasExplicitPermission('approval-flows.update'));
        $this->assertTrue($documentControlAdmin->hasExplicitPermission('approval-flows.delete'));
        $this->assertTrue(User::query()->where('email', 'superadmin@example.com')->firstOrFail()->roles()->whereKey($superAdminRole->id)->exists());
    }
}
