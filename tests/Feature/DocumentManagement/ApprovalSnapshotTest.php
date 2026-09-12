<?php

namespace Tests\Feature\DocumentManagement;

use App\Models\Approval;
use App\Models\ApprovalFlow;
use App\Models\ApprovalStatus;
use App\Models\BusinessFunction;
use App\Models\BusinessProcess;
use App\Models\Department;
use App\Models\Document;
use App\Models\DocumentLevel;
use App\Models\DocumentType;
use App\Models\Permission;
use App\Models\Role;
use App\Models\StatusDocument;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApprovalSnapshotTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ensureStatuses();
        $this->grantUserPermissions([
            ['code' => 'documents.approval.view', 'route' => 'documents.approval.show', 'action' => 'view'],
            ['code' => 'documents.approval.assign', 'route' => 'documents.approval.assign', 'action' => 'assign'],
        ]);
    }

    public function test_approve_stores_approval_response_snapshot(): void
    {
        $department = Department::query()->create([
            'kode_department' => 'OPS',
            'nama_department' => 'Operation Department',
        ]);
        $approver = User::factory()->create([
            'name' => 'Budi Approver',
            'jabatan' => 'Manager Operasi',
            'm_department_id' => $department->id,
        ]);
        $document = $this->createDocument();
        $stage = $this->createApprovalStage($document, 'Diperiksa Oleh');
        $approval = $this->createApproval($document, $approver, ApprovalStatus::PENDING, [
            'stages' => $stage->display_label,
        ]);

        $this->actingAs($approver)
            ->post(route('documents.approval.approve', $document))
            ->assertRedirect(route('documents.approval.show', $document));

        $approval->refresh();

        $this->assertSame('Diperiksa Oleh', $approval->stage_name_snapshot);
        $this->assertSame(1, $approval->stage_order_snapshot);
        $this->assertSame('Budi Approver', $approval->approver_name_snapshot);
        $this->assertSame('Manager Operasi', $approval->approver_position_snapshot);
        $this->assertSame('Operation Department', $approval->approver_department_snapshot);
        $this->assertNotNull($approval->responded_at);
    }

    public function test_multiple_approvers_in_one_stage_store_individual_snapshots(): void
    {
        $firstDepartment = Department::query()->create([
            'kode_department' => 'HCM',
            'nama_department' => 'Human Capital Department',
        ]);
        $secondDepartment = Department::query()->create([
            'kode_department' => 'KEU',
            'nama_department' => 'Finance Department',
        ]);
        $firstApprover = User::factory()->create([
            'name' => 'Approver Satu',
            'jabatan' => 'Supervisor HCM',
            'm_department_id' => $firstDepartment->id,
        ]);
        $secondApprover = User::factory()->create([
            'name' => 'Approver Dua',
            'jabatan' => 'Supervisor Finance',
            'm_department_id' => $secondDepartment->id,
        ]);
        $document = $this->createDocument();
        $stage = $this->createApprovalStage($document, 'Disetujui Oleh');
        $this->createApproval($document, $firstApprover, ApprovalStatus::PENDING, [
            'stages' => $stage->display_label,
        ]);
        $this->createApproval($document, $secondApprover, ApprovalStatus::PENDING, [
            'stages' => $stage->display_label,
        ]);

        $this->actingAs($firstApprover)
            ->post(route('documents.approval.approve', $document))
            ->assertRedirect(route('documents.approval.show', $document));
        $this->actingAs($secondApprover)
            ->post(route('documents.approval.approve', $document))
            ->assertRedirect(route('documents.approval.show', $document));

        $this->assertDatabaseHas('t_approval', [
            't_document_id' => $document->id,
            'user_id' => $firstApprover->id,
            'stage_name_snapshot' => 'Disetujui Oleh',
            'approver_name_snapshot' => 'Approver Satu',
            'approver_position_snapshot' => 'Supervisor HCM',
            'approver_department_snapshot' => 'Human Capital Department',
        ]);
        $this->assertDatabaseHas('t_approval', [
            't_document_id' => $document->id,
            'user_id' => $secondApprover->id,
            'stage_name_snapshot' => 'Disetujui Oleh',
            'approver_name_snapshot' => 'Approver Dua',
            'approver_position_snapshot' => 'Supervisor Finance',
            'approver_department_snapshot' => 'Finance Department',
        ]);
    }

    public function test_reject_stores_approval_response_snapshot(): void
    {
        $department = Department::query()->create([
            'kode_department' => 'MRK',
            'nama_department' => 'Marketing Department',
        ]);
        $approver = User::factory()->create([
            'name' => 'Rina Reviewer',
            'jabatan' => 'Marketing Manager',
            'm_department_id' => $department->id,
        ]);
        $document = $this->createDocument();
        $stage = $this->createApprovalStage($document, 'Direview Oleh');
        $approval = $this->createApproval($document, $approver, ApprovalStatus::PENDING, [
            'stages' => $stage->display_label,
        ]);

        $this->actingAs($approver)
            ->post(route('documents.approval.reject', $document), [
                'catatan' => 'Perlu revisi konten.',
            ])
            ->assertRedirect(route('documents.approval.show', $document));

        $approval->refresh();

        $this->assertSame('Direview Oleh', $approval->stage_name_snapshot);
        $this->assertSame(1, $approval->stage_order_snapshot);
        $this->assertSame('Rina Reviewer', $approval->approver_name_snapshot);
        $this->assertSame('Marketing Manager', $approval->approver_position_snapshot);
        $this->assertSame('Marketing Department', $approval->approver_department_snapshot);
        $this->assertNotNull($approval->responded_at);
    }

    public function test_user_profile_changes_after_approval_do_not_change_snapshot(): void
    {
        $originalDepartment = Department::query()->create([
            'kode_department' => 'SMR',
            'nama_department' => 'System Management Department',
        ]);
        $newDepartment = Department::query()->create([
            'kode_department' => 'BDV',
            'nama_department' => 'Business Development Department',
        ]);
        $approver = User::factory()->create([
            'name' => 'Citra Approver',
            'jabatan' => 'Manager Sistem',
            'm_department_id' => $originalDepartment->id,
        ]);
        $document = $this->createDocument();
        $stage = $this->createApprovalStage($document, 'Disahkan Oleh');
        $approval = $this->createApproval($document, $approver, ApprovalStatus::PENDING, [
            'stages' => $stage->display_label,
        ]);

        $this->actingAs($approver)
            ->post(route('documents.approval.approve', $document))
            ->assertRedirect(route('documents.approval.show', $document));

        $approver->update([
            'name' => 'Citra Renamed',
            'jabatan' => 'Direktur Baru',
            'm_department_id' => $newDepartment->id,
        ]);
        $originalDepartment->update([
            'nama_department' => 'Renamed Department',
        ]);

        $approval->refresh();

        $this->assertSame('Citra Approver', $approval->approver_name_snapshot);
        $this->assertSame('Manager Sistem', $approval->approver_position_snapshot);
        $this->assertSame('System Management Department', $approval->approver_department_snapshot);
    }

    public function test_reassignment_before_response_keeps_snapshot_empty_for_new_pending_approval(): void
    {
        $document = $this->createDocument();
        $stage = $this->createApprovalStage($document, 'Diperiksa Oleh');
        $oldApprover = User::factory()->create();
        $newApprover = User::factory()->create();
        $admin = $this->documentControlAdmin($document->departments()->firstOrFail());

        $this->createApproval($document, $oldApprover, ApprovalStatus::PENDING, [
            'stages' => $stage->display_label,
        ]);

        $this->actingAs($admin)
            ->post(route('documents.approval.assign', $document), [
                'stage_approvers' => [
                    $stage->id => [$newApprover->id],
                ],
            ])
            ->assertRedirect(route('documents.approval.show', $document));

        $this->assertDatabaseMissing('t_approval', [
            't_document_id' => $document->id,
            'user_id' => $oldApprover->id,
        ]);

        $newApproval = Approval::query()
            ->where('t_document_id', $document->id)
            ->where('user_id', $newApprover->id)
            ->firstOrFail();

        $this->assertNull($newApproval->responded_at);
        $this->assertNull($newApproval->stage_name_snapshot);
        $this->assertNull($newApproval->approver_name_snapshot);
        $this->assertNull($newApproval->approver_position_snapshot);
        $this->assertNull($newApproval->approver_department_snapshot);
    }

    public function test_legacy_approval_without_snapshot_can_still_be_read(): void
    {
        $approver = User::factory()->create(['name' => 'Legacy Approver']);
        $document = $this->createDocument();
        $this->createApproval($document, $approver, ApprovalStatus::APPROVED, [
            'responded_at' => now(),
            'stages' => 'Legacy Stage',
        ]);

        $this->actingAs($approver)
            ->get(route('documents.approval.show', $document))
            ->assertOk()
            ->assertSee('Legacy Stage');
    }

    private function createDocument(?User $submitter = null): Document
    {
        $submitter ??= User::factory()->create();
        $department = Department::query()->firstOrCreate(
            ['kode_department' => 'QA'],
            ['nama_department' => 'Quality Assurance'],
        );
        $level = DocumentLevel::query()->firstOrCreate(
            ['kode' => 'level-2'],
            [
                'nama_level' => 'Level II',
                'nama_dokumen' => 'Prosedur',
                'prefix' => 'PS',
                'is_active' => true,
                'sort_order' => 2,
            ],
        );
        $documentType = DocumentType::query()->firstOrCreate(
            ['nama_types' => 'Prosedur'],
            ['is_active' => true],
        );
        $document = Document::query()->create([
            'm_document_level_id' => $level->id,
            'm_status_document_id' => StatusDocument::query()->where('nama_status', StatusDocument::PROPOSED)->value('id'),
            'm_document_types_id' => $documentType->id,
            'm_proses_bisnis_id' => BusinessProcess::query()->firstOrCreate(
                ['kode' => 'Utama'],
                ['nama_proses_bisnis' => 'Proses Inti / Utama'],
            )->id,
            'm_proses_fungsi_id' => BusinessFunction::query()->firstOrCreate(
                ['kode' => 'SMR'],
                ['nama_proses_fungsi' => 'Sistem Manajemen & Resiko'],
            )->id,
            'user_id' => $submitter->id,
            'official_preparer_id' => $submitter->id,
            'nama_dokumen' => 'Dokumen Snapshot Approval',
            'nomor_dokumen' => 'PS-SMR-SNAPSHOT',
            'submitted_at' => now(),
        ]);
        $document->departments()->sync([$department->id]);

        return $document;
    }

    private function createApprovalStage(Document $document, string $stageName)
    {
        $flow = ApprovalFlow::query()->create([
            'm_document_level_id' => $document->m_document_level_id,
            'nama_flow' => 'Flow Snapshot',
        ]);

        return $flow->stages()->create([
            'stage_order' => 1,
            'nama_tahap' => $stageName,
        ]);
    }

    private function createApproval(Document $document, User $approver, string $statusCode, array $attributes = []): Approval
    {
        return Approval::query()->create($attributes + [
            't_document_id' => $document->id,
            'm_approval_status_id' => ApprovalStatus::query()->where('kode_status', $statusCode)->value('id'),
            'user_id' => $approver->id,
            'role_id' => null,
            'assigned_by' => $document->user_id,
            'assigned_at' => now(),
            'stages' => 'Approval',
        ]);
    }

    private function ensureStatuses(): void
    {
        foreach ([StatusDocument::DRAFT, StatusDocument::PROPOSED, StatusDocument::APPROVED, StatusDocument::REJECTED, StatusDocument::CANCELLED, StatusDocument::OBSOLETE] as $status) {
            StatusDocument::query()->firstOrCreate(['nama_status' => $status]);
        }

        foreach ([ApprovalStatus::PENDING, ApprovalStatus::WAITING, ApprovalStatus::APPROVED, ApprovalStatus::REJECTED, ApprovalStatus::TERMINATED] as $status) {
            ApprovalStatus::query()->firstOrCreate([
                'kode_status' => $status,
            ], [
                'nama_status' => $status,
            ]);
        }
    }

    private function grantUserPermissions(array $permissions): void
    {
        $role = Role::query()->firstOrCreate(['nama_role' => 'User']);

        $role->permissions()->syncWithoutDetaching(collect($permissions)
            ->map(fn (array $permission): int => Permission::query()->firstOrCreate(
                ['code' => $permission['code']],
                [
                    'name' => $permission['code'],
                    'module' => 'Manajemen Dokumen',
                    'route' => $permission['route'],
                    'action' => $permission['action'],
                ],
            )->id)
            ->all());
    }

    private function documentControlAdmin(Department $department): User
    {
        $role = Role::query()->firstOrCreate(['nama_role' => 'Admin Kontrol Dokumen']);
        $permission = Permission::query()->firstOrCreate(
            ['code' => 'documents.approval.assign'],
            [
                'name' => 'Assign Approver Dokumen',
                'module' => 'Manajemen Dokumen',
                'route' => 'documents.approval.assign',
                'action' => 'assign',
            ],
        );
        $role->permissions()->syncWithoutDetaching([$permission->id]);

        $user = User::factory()->create(['m_department_id' => $department->id]);
        $user->roles()->syncWithoutDetaching([$role->id]);

        return $user;
    }
}
