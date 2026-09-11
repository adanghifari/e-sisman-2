<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PermissionSeeder extends Seeder
{
    private const DEFAULT_USER_ROLE = 'User';

    private const DOCUMENT_CONTROL_ADMIN_ROLE = 'Admin Kontrol Dokumen';

    private const SUPER_ADMIN_ROLE = 'SuperAdmin';

    private const DEFAULT_USER_PERMISSION_CODES = [
        'dashboard.view',
        'documents.inbox.view',
        'documents.approval.view',
        'documents.approval.download',
        'documents.approval.preview',
        'documents.create.view',
        'documents.create.drafts',
        'documents.create.drafts.edit',
        'documents.create.drafts.delete',
        'documents.create.create',
        'documents.master.view',
        'documents.master.detail',
        'documents.master.download',
        'documents.master.preview',
        'documents.obsolete.view',
        'documents.obsolete.detail',
        'documents.obsolete.download',
        'documents.obsolete.preview',
        'document-templates.view',
        'document-templates.download',
    ];

    private const DOCUMENT_CONTROL_ADMIN_EXTRA_PERMISSION_CODES = [
        'approval-flows.create',
        'approval-flows.update',
        'approval-flows.delete',
    ];

    public function run(): void
    {
        $permissions = collect(config('access.permissions', []));
        $permissionCodes = $permissions->pluck('code')->all();

        $this->normalizeSuperAdminRole();

        DB::table('role_permissions')
            ->whereIn(
                'permission_id',
                DB::table('permissions')
                    ->whereNotIn('code', $permissionCodes)
                    ->select('id'),
            )
            ->delete();

        DB::table('permissions')
            ->whereNotIn('code', $permissionCodes)
            ->delete();

        foreach ($permissions as $permission) {
            DB::table('permissions')->updateOrInsert(
                ['code' => $permission['code']],
                [
                    'name' => $permission['name'],
                    'module' => $permission['module'],
                    'route' => $permission['route'] ?? null,
                    'action' => $permission['action'] ?? 'view',
                ],
            );
        }

        DB::table('roles')->updateOrInsert(
            ['nama_role' => self::SUPER_ADMIN_ROLE],
            ['nama_role' => self::SUPER_ADMIN_ROLE],
        );

        $superAdminRoleId = DB::table('roles')
            ->where('nama_role', self::SUPER_ADMIN_ROLE)
            ->value('id');

        $permissionIds = DB::table('permissions')->pluck('id');

        DB::table('role_permissions')
            ->where('role_id', $superAdminRoleId)
            ->whereNotIn('permission_id', $permissionIds)
            ->delete();

        foreach ($permissionIds as $permissionId) {
            DB::table('role_permissions')->updateOrInsert([
                'role_id' => $superAdminRoleId,
                'permission_id' => $permissionId,
            ]);
        }

        DB::table('roles')->updateOrInsert(
            ['nama_role' => self::DOCUMENT_CONTROL_ADMIN_ROLE],
            ['nama_role' => self::DOCUMENT_CONTROL_ADMIN_ROLE],
        );

        $documentControlAdminRoleId = DB::table('roles')
            ->where('nama_role', self::DOCUMENT_CONTROL_ADMIN_ROLE)
            ->value('id');

        $documentControlPermissionIds = DB::table('permissions')
            ->where(function ($query): void {
                $query
                    ->where('module', '!=', 'Administrasi')
                    ->orWhere('action', 'view')
                    ->orWhereIn('code', self::DOCUMENT_CONTROL_ADMIN_EXTRA_PERMISSION_CODES);
            })
            ->pluck('id');

        DB::table('role_permissions')
            ->where('role_id', $documentControlAdminRoleId)
            ->whereNotIn('permission_id', $documentControlPermissionIds)
            ->delete();

        foreach ($documentControlPermissionIds as $permissionId) {
            DB::table('role_permissions')->updateOrInsert([
                'role_id' => $documentControlAdminRoleId,
                'permission_id' => $permissionId,
            ]);
        }

        DB::table('roles')->updateOrInsert(
            ['nama_role' => self::DEFAULT_USER_ROLE],
            ['nama_role' => self::DEFAULT_USER_ROLE],
        );

        $defaultUserRoleId = DB::table('roles')
            ->where('nama_role', self::DEFAULT_USER_ROLE)
            ->value('id');

        $defaultUserPermissionIds = DB::table('permissions')
            ->whereIn('code', self::DEFAULT_USER_PERMISSION_CODES)
            ->pluck('id');

        DB::table('role_permissions')
            ->where('role_id', $defaultUserRoleId)
            ->whereNotIn('permission_id', $defaultUserPermissionIds)
            ->delete();

        foreach ($defaultUserPermissionIds as $permissionId) {
            DB::table('role_permissions')->updateOrInsert([
                'role_id' => $defaultUserRoleId,
                'permission_id' => $permissionId,
            ]);
        }

        $nonDeveloperUserIds = DB::table('users')
            ->where(function ($query): void {
                $query
                    ->where('nik', '!=', '000000')
                    ->orWhereNull('nik');
            })
            ->where(function ($query): void {
                $query
                    ->where('email', '!=', 'developer@example.com')
                    ->orWhereNull('email');
            })
            ->pluck('id');

        foreach ($nonDeveloperUserIds as $userId) {
            DB::table('user_roles')->updateOrInsert([
                'role_id' => $defaultUserRoleId,
                'user_id' => $userId,
            ]);
        }
    }

    private function normalizeSuperAdminRole(): void
    {
        $legacyRoleId = DB::table('roles')->where('nama_role', 'Superadmin')->value('id');
        $superAdminRoleId = DB::table('roles')->where('nama_role', self::SUPER_ADMIN_ROLE)->value('id');

        if ($legacyRoleId === null) {
            return;
        }

        if ($superAdminRoleId === null) {
            DB::table('roles')
                ->where('id', $legacyRoleId)
                ->update(['nama_role' => self::SUPER_ADMIN_ROLE]);

            return;
        }

        $legacyUserIds = DB::table('user_roles')
            ->where('role_id', $legacyRoleId)
            ->pluck('user_id');

        foreach ($legacyUserIds as $userId) {
            DB::table('user_roles')->updateOrInsert([
                'role_id' => $superAdminRoleId,
                'user_id' => $userId,
            ]);
        }

        $legacyPermissionIds = DB::table('role_permissions')
            ->where('role_id', $legacyRoleId)
            ->pluck('permission_id');

        foreach ($legacyPermissionIds as $permissionId) {
            DB::table('role_permissions')->updateOrInsert([
                'role_id' => $superAdminRoleId,
                'permission_id' => $permissionId,
            ]);
        }

        DB::table('user_roles')->where('role_id', $legacyRoleId)->delete();
        DB::table('role_permissions')->where('role_id', $legacyRoleId)->delete();
        DB::table('roles')->where('id', $legacyRoleId)->delete();
    }
}
