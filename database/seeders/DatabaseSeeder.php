<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DatabaseSeeder extends Seeder
{

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            DepartmentSeeder::class,
            DocumentStatusSeeder::class,
            ApprovalStatusSeeder::class,
            DocumentLevelSeeder::class,
            DocumentTypeSeeder::class,
            BusinessProcessSeeder::class,
            BusinessFunctionSeeder::class,
            PermissionSeeder::class,
        ]);

        $itDepartmentId = DB::table('departments')->where('kode_department', 'ITSM')->value('id');
        $hcgaDepartmentId = DB::table('departments')->where('kode_department', 'HCGA')->value('id');
        $marketingDepartmentId = DB::table('departments')->where('kode_department', 'PMK')->value('id');
        $hsseDepartmentId = DB::table('departments')->where('kode_department', 'HSSE')->value('id');
        $marineDepartmentId = DB::table('departments')->where('kode_department', 'MOP')->value('id');
        $businessDevelopmentDepartmentId = DB::table('departments')->where('kode_department', 'BDV')->value('id');

        User::updateOrCreate(
            ['nik' => '000000'],
            [
                'm_department_id' => $itDepartmentId,
                'name' => 'Developer',
                'email' => 'developer@example.com',
                'password' => 'Password123!',
            ],
        );

        $superAdmin = User::updateOrCreate(
            ['nik' => '000001'],
            [
                'm_department_id' => $itDepartmentId,
                'name' => 'Akun SuperAdmin',
                'email' => 'superadmin@example.com',
                'password' => 'Password123!',
                'jabatan' => 'Super Administrator',
            ],
        );

        $documentControlAdmin = User::updateOrCreate(
            ['nik' => '000002'],
            [
                'm_department_id' => $itDepartmentId,
                'name' => 'Akun Admin Kontrol Dokumen',
                'email' => 'admin.kontrol@example.com',
                'password' => 'Password123!',
                'jabatan' => 'Document Control Administrator',
            ],
        );

        $users = [
            ['nik' => '0000111', 'name' => 'Muhammad Akhdan Ghifari', 'email' => 'akhdan@example.com', 'jabatan' => 'Manager', 'department_id' => $businessDevelopmentDepartmentId],
            ['nik' => '0000112', 'name' => 'Muhammad Azigha Azhar', 'email' => 'azigha.lestari@example.com', 'jabatan' => 'Manager', 'department_id' => $hcgaDepartmentId],
            ['nik' => '0000113', 'name' => 'Hafiz Fawwaz Aydil', 'email' => 'aydil@example.com', 'jabatan' => 'Business Process Analyst', 'department_id' => $itDepartmentId],
            ['nik' => '0000114', 'name' => 'Nadia Putri', 'email' => 'nadia.putri@example.com', 'jabatan' => 'Quality Assurance Officer', 'department_id' => $hsseDepartmentId],
            ['nik' => '0000115', 'name' => 'Dimas Pratama', 'email' => 'dimas.pratama@example.com', 'jabatan' => 'Operations Supervisor', 'department_id' => $marineDepartmentId],
            ['nik' => '0000116', 'name' => 'Farhan Selatan', 'email' => 'parhan@example.com', 'jabatan' => 'Compliance Officer', 'department_id' => $marketingDepartmentId],
            ['nik' => '0000117', 'name' => 'Moreno', 'email' => 'moreno@example.com', 'jabatan' => 'IT STAFF', 'department_id' => $itDepartmentId],
        ];

        $seededUsers = collect();

        foreach ($users as $user) {
            $seededUsers->push(User::updateOrCreate(
                ['nik' => $user['nik']],
                [
                    'm_department_id' => $user['department_id'],
                    'name' => $user['name'],
                    'email' => $user['email'],
                    'password' => 'Password123!',
                    'jabatan' => $user['jabatan'],
                ],
            ));
        }

        $userRoleId = DB::table('roles')->where('nama_role', 'User')->value('id');
        $documentControlAdminRoleId = DB::table('roles')->where('nama_role', 'Admin Kontrol Dokumen')->value('id');
        $superAdminRoleId = DB::table('roles')->where('nama_role', 'SuperAdmin')->value('id');

        if ($userRoleId !== null) {
            $seededUsers->each(fn (User $user): array => $user->roles()->sync([$userRoleId]));
        }

        if ($documentControlAdminRoleId !== null) {
            $documentControlAdmin->roles()->sync([$documentControlAdminRoleId]);
        }

        if ($superAdminRoleId !== null) {
            $superAdmin->roles()->sync([$superAdminRoleId]);
        }
    }
}

