<?php

namespace Tests\Feature\DocumentManagement;

use App\Http\Controllers\DocumentManagement\DocumentController;
use App\Models\Approval;
use App\Models\ApprovalFlow;
use App\Models\ApprovalStatus;
use App\Models\BusinessFunction;
use App\Models\BusinessProcess;
use App\Models\Department;
use App\Models\Document;
use App\Models\DocumentLevel;
use App\Models\DocumentNumberRegistry;
use App\Models\DocumentRelation;
use App\Models\DocumentType;
use App\Models\Permission;
use App\Models\Role;
use App\Models\StatusDocument;
use App\Models\User;
use App\Support\DocumentRejectionHistory;
use App\Support\FinalDocuments\FinalArtifactGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CreateDocumentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $role = Role::query()->firstOrCreate(['nama_role' => 'User']);
        $permissions = collect([
            [
                'code' => 'documents.create.view',
                'name' => 'Lihat Tambah Dokumen',
                'module' => 'Manajemen Dokumen',
                'route' => 'documents.create',
                'action' => 'view',
            ],
            [
                'code' => 'documents.create.create',
                'name' => 'Submit Tambah Dokumen',
                'module' => 'Manajemen Dokumen',
                'route' => 'documents.store',
                'action' => 'create',
            ],
            [
                'code' => 'documents.create.drafts',
                'name' => 'Lihat Draft Dokumen Saya',
                'module' => 'Manajemen Dokumen',
                'route' => 'documents.create.drafts',
                'action' => 'view',
            ],
            [
                'code' => 'documents.create.drafts.edit',
                'name' => 'Edit Draft Dokumen Saya',
                'module' => 'Manajemen Dokumen',
                'route' => 'documents.create.drafts.edit',
                'action' => 'view',
            ],
            [
                'code' => 'documents.create.drafts.delete',
                'name' => 'Hapus Draft Dokumen Saya',
                'module' => 'Manajemen Dokumen',
                'route' => 'documents.create.drafts.destroy',
                'action' => 'delete',
            ],
            [
                'code' => 'documents.master.view',
                'name' => 'Lihat Dokumen Master',
                'module' => 'Manajemen Dokumen',
                'route' => 'documents.master',
                'action' => 'view',
            ],
            [
                'code' => 'documents.master.detail',
                'name' => 'Lihat Detail Dokumen Master',
                'module' => 'Manajemen Dokumen',
                'route' => 'documents.master.show',
                'action' => 'view',
            ],
            [
                'code' => 'documents.inbox.view',
                'name' => 'Lihat Inbox Approval',
                'module' => 'Manajemen Dokumen',
                'route' => 'documents.inbox',
                'action' => 'view',
            ],
        ])->map(fn (array $permission): Permission => Permission::query()->firstOrCreate(
            ['code' => $permission['code']],
            $permission,
        ));

        $role->permissions()->syncWithoutDetaching($permissions->pluck('id')->all());
    }

    public function test_document_number_lock_name_stays_within_mysql_limit(): void
    {
        $controller = app(DocumentController::class);
        $method = new \ReflectionMethod($controller, 'documentNumberLockName');
        $method->setAccessible(true);

        $lockName = $method->invoke($controller, 'PS-KSA-001');

        $this->assertStringStartsWith('doc-num:', $lockName);
        $this->assertLessThanOrEqual(64, strlen($lockName));
    }

    public function test_level_three_create_page_is_displayed(): void
    {
        $user = User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        $this->actingAs($user)
            ->get(route('documents.create.level', 'level-3'))
            ->assertOk()
            ->assertSee('Nama Dokumen')
            ->assertDontSee('Assign Approver')
            ->assertSee('Test User');
    }

    public function test_level_three_create_page_lists_active_master_procedure_reference(): void
    {
        $user = User::factory()->create();
        $businessProcess = BusinessProcess::create([
            'kode' => 'KSA',
            'nama_proses_bisnis' => 'Kesisteman',
        ]);
        $businessFunction = BusinessFunction::create([
            'kode' => 'OPS',
            'nama_proses_fungsi' => 'Operasional',
        ]);
        $obsoleteStatus = StatusDocument::create(['nama_status' => StatusDocument::OBSOLETE]);
        $approvedStatus = StatusDocument::create(['nama_status' => StatusDocument::APPROVED]);
        $procedureType = DocumentType::create(['nama_types' => 'Prosedur']);
        $procedureLevel = DocumentLevel::query()->where('kode', 'level-2')->firstOrFail();

        $oldProcedure = Document::create([
            'm_document_level_id' => $procedureLevel->id,
            'm_status_document_id' => $obsoleteStatus->id,
            'm_document_types_id' => $procedureType->id,
            'm_proses_bisnis_id' => $businessProcess->id,
            'm_proses_fungsi_id' => $businessFunction->id,
            'user_id' => $user->id,
            'nama_dokumen' => 'Prosedur Lama',
            'nomor_dokumen' => 'PS-KSA-02',
            'nomor_revisi' => 0,
        ]);

        Document::create([
            'm_document_level_id' => $procedureLevel->id,
            'm_status_document_id' => $approvedStatus->id,
            'm_document_types_id' => $procedureType->id,
            'm_proses_bisnis_id' => $businessProcess->id,
            'm_proses_fungsi_id' => $businessFunction->id,
            'user_id' => $user->id,
            'revised_from' => $oldProcedure->id,
            'nama_dokumen' => 'Prosedur Aktif Revisi',
            'nomor_dokumen' => 'PS-KSA-02',
            'nomor_revisi' => 1,
            'approved_at' => now(),
        ]);

        $this->actingAs($user)
            ->get(route('documents.create.level', 'level-3'))
            ->assertOk()
            ->assertSee('PS-KSA-02 - Prosedur Aktif Revisi')
            ->assertDontSee('PS-KSA-02 - Prosedur Lama');
    }

    public function test_level_three_create_page_lists_approved_revision_request_as_active_procedure_reference(): void
    {
        $user = User::factory()->create();
        $businessProcess = BusinessProcess::create([
            'kode' => 'KSA',
            'nama_proses_bisnis' => 'Kondisi Solusi Abadi',
        ]);
        $businessFunction = BusinessFunction::create([
            'kode' => 'KTL',
            'nama_proses_fungsi' => 'Koefisiensi Terima Literasi',
        ]);
        $obsoleteStatus = StatusDocument::create(['nama_status' => StatusDocument::OBSOLETE]);
        $approvedStatus = StatusDocument::create(['nama_status' => StatusDocument::APPROVED]);
        $procedureType = DocumentType::create(['nama_types' => 'Prosedur']);
        $revisionType = DocumentType::query()->firstOrCreate(['nama_types' => 'Revisi']);
        $procedureLevel = DocumentLevel::query()->where('kode', 'level-2')->firstOrFail();
        $formLevel = DocumentLevel::query()->where('kode', 'level-4')->firstOrFail();

        $sourceProcedure = Document::create([
            'm_document_level_id' => $procedureLevel->id,
            'm_status_document_id' => $obsoleteStatus->id,
            'm_document_types_id' => $procedureType->id,
            'm_proses_bisnis_id' => $businessProcess->id,
            'm_proses_fungsi_id' => $businessFunction->id,
            'user_id' => $user->id,
            'nama_dokumen' => 'Prosedur Sebelum Revisi',
            'nomor_dokumen' => 'PS-KSA-02',
            'nomor_revisi' => 0,
        ]);

        Document::create([
            'm_document_level_id' => $formLevel->id,
            'm_status_document_id' => $approvedStatus->id,
            'm_document_types_id' => $revisionType->id,
            'm_proses_bisnis_id' => $businessProcess->id,
            'm_proses_fungsi_id' => $businessFunction->id,
            'user_id' => $user->id,
            'revised_from' => $sourceProcedure->id,
            'request_type' => 'revision',
            'nama_dokumen' => 'Prosedur Sesudah Revisi',
            'nomor_dokumen' => 'FMPS-KSA-02',
            'nomor_revisi' => 1,
            'approved_at' => now(),
        ]);

        $this->actingAs($user)
            ->get(route('documents.create.level', 'level-3'))
            ->assertOk()
            ->assertSee('PS-KSA-02 - Prosedur Sesudah Revisi')
            ->assertDontSee('FMPS-KSA-02 - Prosedur Sesudah Revisi');
    }

    public function test_level_two_create_page_uses_integrated_create_form(): void
    {
        $user = User::factory()->create([
            'name' => 'Level Two User',
            'email' => 'level-two@example.com',
        ]);
        BusinessProcess::create([
            'kode' => 'SMR',
            'nama_proses_bisnis' => 'Sistem Manajemen Risiko',
        ]);
        BusinessFunction::create([
            'kode' => 'OPS',
            'nama_proses_fungsi' => 'Operasional',
        ]);

        $this->actingAs($user)
            ->get(route('documents.create.level', 'level-2'))
            ->assertOk()
            ->assertSee('Tambah Dokumen Level II')
            ->assertSee('Nama Dokumen')
            ->assertSee('SMR - Sistem Manajemen Risiko')
            ->assertSee('OPS - Operasional')
            ->assertDontSee('Level Dokumen:')
            ->assertSee('Penyusun Pemilik Proses')
            ->assertSee('Template Dokumen yang Sudah Diisi')
            ->assertSee('Upload Template Terisi PDF')
            ->assertSee('Upload Template Terisi Word')
            ->assertSee('Level Two User');
    }

    public function test_level_one_create_page_is_displayed(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('documents.create.level', 'level-1'))
            ->assertOk()
            ->assertSee('Import Dokumen Level I')
            ->assertSee('Nama Dokumen')
            ->assertSee('Upload Dokumen')
            ->assertSee('Import Dokumen')
            ->assertSee('Submit Dokumen')
            ->assertSee('value="001"', false)
            ->assertSee('value="00.00"', false);
    }

    public function test_level_one_create_page_suggests_next_manual_document_number(): void
    {
        $user = User::factory()->create();
        $status = StatusDocument::create(['nama_status' => StatusDocument::APPROVED]);
        $documentType = DocumentType::create(['nama_types' => 'Manual']);
        $level = DocumentLevel::query()->where('kode', 'level-1')->firstOrFail();

        Document::create([
            'm_document_level_id' => $level->id,
            'm_status_document_id' => $status->id,
            'm_document_types_id' => $documentType->id,
            'user_id' => $user->id,
            'nama_dokumen' => 'Manual Lama',
            'nomor_dokumen' => 'SM-001',
            'nomor_revisi' => 0,
            'approved_at' => now(),
        ]);

        DocumentNumberRegistry::create([
            'document_number' => 'SM-002',
            'scope_identifier' => 'SM',
            'source_type' => DocumentNumberRegistry::SOURCE_IMPORTED_EXISTING,
            'source_id' => 1,
            'registered_by' => $user->id,
            'registered_at' => now(),
        ]);

        $this->actingAs($user)
            ->get(route('documents.create.level', 'level-1'))
            ->assertOk()
            ->assertSee('value="003"', false);
    }

    public function test_level_two_create_uses_imported_master_registry_as_next_document_number(): void
    {
        Storage::fake('local');

        $user = User::factory()->create();
        $businessProcess = BusinessProcess::create([
            'kode' => 'SMR',
            'nama_proses_bisnis' => 'Sistem Manajemen Risiko',
        ]);
        $businessFunction = BusinessFunction::create([
            'kode' => 'OPS',
            'nama_proses_fungsi' => 'Operasional',
            'm_proses_bisnis_id' => $businessProcess->id,
        ]);
        $department = Department::create([
            'kode_department' => 'QA',
            'nama_department' => 'Quality Assurance',
        ]);
        StatusDocument::create(['nama_status' => StatusDocument::DRAFT]);
        StatusDocument::create(['nama_status' => StatusDocument::PROPOSED]);
        ApprovalStatus::create([
            'kode_status' => ApprovalStatus::APPROVED,
            'nama_status' => 'Disetujui',
        ]);
        DocumentType::create(['nama_types' => 'Prosedur']);

        DocumentNumberRegistry::create([
            'document_number' => 'PS-OPS-03',
            'scope_identifier' => 'PS-OPS',
            'source_type' => DocumentNumberRegistry::SOURCE_IMPORTED_EXISTING,
            'source_id' => 1,
            'registered_by' => $user->id,
            'registered_at' => now(),
        ]);

        $this->actingAs($user)
            ->get(route('documents.create.level', 'level-2'))
            ->assertOk()
            ->assertSee('"scope:PS-OPS":"04"', false)
            ->assertDontSee('data-user-edited="true"', false);

        $this->actingAs($user)
            ->from(route('documents.create.level', 'level-2'))
            ->post(route('documents.store', 'level-2'), [
                'nama_dokumen' => 'Prosedur Nomor Lama',
                'm_proses_bisnis_id' => $businessProcess->id,
                'm_proses_fungsi_id' => $businessFunction->id,
                'department_ids' => [$department->id],
                'official_preparer_id' => $user->id,
                'nomor_dokumen_suffix' => '01',
                'filled_template' => UploadedFile::fake()->create('template.pdf', 24, 'application/pdf'),
                'filled_template_word' => UploadedFile::fake()->create('template.docx', 24, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'),
                'submit_action' => 'submit',
            ])
            ->assertRedirect(route('documents.create.level', 'level-2'))
            ->assertSessionHasErrors(['nomor_dokumen_suffix']);

        $this->actingAs($user)
            ->post(route('documents.store', 'level-2'), [
                'nama_dokumen' => 'Prosedur Nomor Berikutnya',
                'm_proses_bisnis_id' => $businessProcess->id,
                'm_proses_fungsi_id' => $businessFunction->id,
                'department_ids' => [$department->id],
                'official_preparer_id' => $user->id,
                'nomor_dokumen_suffix' => '04',
                'filled_template' => UploadedFile::fake()->create('template-next.pdf', 24, 'application/pdf'),
                'filled_template_word' => UploadedFile::fake()->create('template-next.docx', 24, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'),
                'submit_action' => 'submit',
            ])
            ->assertRedirect(route('documents.create'));

        $this->assertDatabaseHas('t_document', [
            'nama_dokumen' => 'Prosedur Nomor Berikutnya',
            'nomor_dokumen' => 'PS-OPS-04',
        ]);
    }

    public function test_create_document_sidebar_stays_active_on_level_forms(): void
    {
        $user = User::factory()->create();

        foreach (['level-1', 'level-2', 'level-3'] as $level) {
            $this->actingAs($user)
                ->get(route('documents.create.level', $level))
                ->assertOk()
                ->assertSee('bg-white text-sky-800 shadow-sm', false)
                ->assertSee('Tambah Dokumen');
        }
    }

    public function test_level_one_document_can_be_saved_as_draft(): void
    {
        Storage::fake('local');

        $user = User::factory()->create();

        BusinessProcess::create([
            'kode' => 'SMR',
            'nama_proses_bisnis' => 'Sistem Manajemen Risiko',
        ]);
        BusinessFunction::create([
            'kode' => 'OPS',
            'nama_proses_fungsi' => 'Operasional',
        ]);

        StatusDocument::create(['nama_status' => StatusDocument::DRAFT]);
        StatusDocument::create(['nama_status' => StatusDocument::PROPOSED]);
        DocumentType::create(['nama_types' => 'Manual']);

        $level = DocumentLevel::query()->where('kode', 'level-1')->firstOrFail();

        $this->actingAs($user)
            ->post(route('documents.store', 'level-1'), [
                'nama_dokumen' => 'Manual SKMBS',
                'nomor_dokumen_suffix' => '001',
                'nomor_revisi' => '00.00',
                'tanggal_terbit' => '2026-08-12',
                'catatan_revisi' => 'Dokumen awal.',
                'imported_document' => UploadedFile::fake()->create('manual.pdf', 24, 'application/pdf'),
                'submit_action' => 'draft',
            ])
            ->assertRedirect(route('documents.create.drafts'));

        $document = Document::query()->firstOrFail();

        $this->assertSame($level->id, $document->m_document_level_id);
        $this->assertSame('Manual SKMBS', $document->nama_dokumen);
        $this->assertSame('SM-001', $document->nomor_dokumen);
        $this->assertSame('00.00', $document->nomor_revisi);
        $this->assertSame('Dokumen awal.', $document->catatan_revisi);
        $this->assertNull($document->submitted_at);
        $this->assertTrue($document->files()->where('type_file', 'imported_document')->exists());
    }

    public function test_level_one_document_can_be_submitted_to_needs_process(): void
    {
        Storage::fake('local');

        $submitter = User::factory()->create([
            'name' => 'Pengaju Manual',
        ]);
        $officialPreparer = User::factory()->create([
            'name' => 'Penyusun Manual',
        ]);
        $documentControlAdmin = User::factory()->create([
            'name' => 'Admin Kontrol Dokumen',
        ]);

        $documentControlRole = Role::query()->firstOrCreate(['nama_role' => 'Admin Kontrol Dokumen']);
        $assignPermission = Permission::query()->firstOrCreate(
            ['code' => 'documents.approval.assign'],
            [
                'name' => 'Assign Approver Dokumen',
                'module' => 'Manajemen Dokumen',
                'route' => 'documents.approval.assign',
                'action' => 'assign',
            ],
        );
        $documentControlRole->permissions()->syncWithoutDetaching([$assignPermission->id]);
        $documentControlAdmin->roles()->attach($documentControlRole);

        StatusDocument::create(['nama_status' => StatusDocument::DRAFT]);
        StatusDocument::create(['nama_status' => StatusDocument::PROPOSED]);
        StatusDocument::create(['nama_status' => StatusDocument::APPROVED]);
        foreach ([
            ApprovalStatus::PENDING => 'Dalam Review',
            ApprovalStatus::WAITING => 'Menunggu',
            ApprovalStatus::APPROVED => 'Disetujui',
            ApprovalStatus::REJECTED => 'Ditolak',
            ApprovalStatus::TERMINATED => 'Dihentikan',
        ] as $code => $name) {
            ApprovalStatus::create([
                'kode_status' => $code,
                'nama_status' => $name,
            ]);
        }
        DocumentType::create(['nama_types' => 'Manual']);

        $level = DocumentLevel::query()->where('kode', 'level-1')->firstOrFail();
        $flow = ApprovalFlow::create([
            'm_document_level_id' => $level->id,
            'nama_flow' => 'Flow Manual SKMBS',
        ]);
        $stage = $flow->stages()->create([
            'stage_order' => 1,
            'nama_tahap' => 'Verifikator Manual',
        ]);

        $this->actingAs($submitter)
            ->post(route('documents.store', 'level-1'), [
                'nama_dokumen' => 'Manual SKMBS Submit',
                'official_preparer_id' => $officialPreparer->id,
                'nomor_dokumen_suffix' => '002',
                'nomor_revisi' => '00.00',
                'tanggal_terbit' => '2026-08-12',
                'catatan_revisi' => 'Dokumen manual siap diproses.',
                'imported_document' => UploadedFile::fake()->create('manual-submit.pdf', 24, 'application/pdf'),
                'submit_action' => 'submit',
            ])
            ->assertRedirect(route('documents.create'))
            ->assertSessionHas('document_success.title', 'Dokumen berhasil disubmit');

        $document = Document::query()->firstOrFail();

        $this->assertSame($level->id, $document->m_document_level_id);
        $this->assertSame(StatusDocument::PROPOSED, $document->status->nama_status);
        $this->assertSame($officialPreparer->id, $document->official_preparer_id);
        $this->assertSame('Manual SKMBS Submit', $document->nama_dokumen);
        $this->assertSame('SM-002', $document->nomor_dokumen);
        $this->assertSame('00.00', $document->nomor_revisi);
        $this->assertNotNull($document->submitted_at);
        $this->assertNull($document->m_proses_bisnis_id);
        $this->assertNull($document->m_proses_fungsi_id);
        $this->assertNull($document->reference);
        $this->assertCount(0, $document->departments);
        $this->assertTrue($document->files()->where('type_file', 'imported_document')->exists());
        $this->assertTrue(
            $document->approvals()
                ->where('user_id', $officialPreparer->id)
                ->where('stages', 'TTD Penyusun Resmi')
                ->exists(),
        );

        $this->actingAs($documentControlAdmin)
            ->get(route('documents.inbox', ['tab' => 'needs-process']))
            ->assertOk()
            ->assertSee('Manual SKMBS Submit')
            ->assertSee('SM-002')
            ->assertSee('Manual')
            ->assertSee('Belum assign approver');

        $this->actingAs($documentControlAdmin)
            ->get(route('documents.approval.show', $document))
            ->assertOk()
            ->assertSee('Manual SKMBS Submit')
            ->assertSee('Approval Flow Dokumen Level I')
            ->assertSee('Verifikator Manual')
            ->assertSee('Save Approver');

        $this->actingAs($documentControlAdmin)
            ->post(route('documents.approval.assign', $document), [
                'stage_approvers' => [
                    $stage->id => [$documentControlAdmin->id],
                ],
            ])
            ->assertRedirect(route('documents.approval.show', $document));

        $this->assertTrue(
            $document->approvals()
                ->where('user_id', $documentControlAdmin->id)
                ->where('m_approval_flow_stage_id', $stage->id)
                ->whereHas('status', fn ($query) => $query->where('kode_status', ApprovalStatus::PENDING))
                ->exists(),
        );

        $this->actingAs($documentControlAdmin)
            ->post(route('documents.approval.approve', $document))
            ->assertRedirect(route('documents.approval.show', $document));

        $this->assertSame(StatusDocument::APPROVED, $document->refresh()->status->nama_status);
        $this->assertNotNull($document->approved_at);
        $this->assertTrue(
            $document->approvals()
                ->where('user_id', $documentControlAdmin->id)
                ->where('m_approval_flow_stage_id', $stage->id)
                ->whereHas('status', fn ($query) => $query->where('kode_status', ApprovalStatus::APPROVED))
                ->exists(),
        );
    }

    public function test_level_two_document_can_be_saved_as_draft(): void
    {
        Storage::fake('local');

        $user = User::factory()->create();
        $businessProcess = BusinessProcess::create([
            'kode' => 'SMR',
            'nama_proses_bisnis' => 'Sistem Manajemen Risiko',
        ]);
        $businessFunction = BusinessFunction::create([
            'kode' => 'OPS',
            'nama_proses_fungsi' => 'Operasional',
        ]);
        $department = Department::create([
            'kode_department' => 'QA',
            'nama_department' => 'Quality Assurance',
        ]);

        StatusDocument::create(['nama_status' => StatusDocument::DRAFT]);
        StatusDocument::create(['nama_status' => StatusDocument::PROPOSED]);
        DocumentType::create(['nama_types' => 'Prosedur']);

        $level = DocumentLevel::query()->where('kode', 'level-2')->firstOrFail();

        $this->actingAs($user)
            ->post(route('documents.store', 'level-2'), [
                'm_document_level_id' => $level->id,
                'nama_dokumen' => 'Prosedur Pengujian',
                'm_proses_bisnis_id' => $businessProcess->id,
                'm_proses_fungsi_id' => $businessFunction->id,
                'department_ids' => [$department->id],
                'official_preparer_id' => $user->id,
                'nomor_dokumen_suffix' => '002',
                'filled_template' => UploadedFile::fake()->create('template.pdf', 24, 'application/pdf'),
                'submit_action' => 'draft',
            ])
            ->assertRedirect(route('documents.create.drafts'));

        $document = Document::query()->firstOrFail();

        $this->assertSame($level->id, $document->m_document_level_id);
        $this->assertSame('Prosedur Pengujian', $document->nama_dokumen);
        $this->assertSame($user->id, $document->official_preparer_id);
        $this->assertSame('PS-OPS-002', $document->nomor_dokumen);
        $this->assertTrue($document->departments()->whereKey($department->id)->exists());
    }

    public function test_user_can_list_and_continue_own_draft(): void
    {
        Storage::fake('local');

        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $businessProcess = BusinessProcess::create([
            'kode' => 'SMR',
            'nama_proses_bisnis' => 'Sistem Manajemen Risiko',
        ]);
        $businessFunction = BusinessFunction::create([
            'kode' => 'OPS',
            'nama_proses_fungsi' => 'Operasional',
        ]);
        $department = Department::create([
            'kode_department' => 'QA',
            'nama_department' => 'Quality Assurance',
        ]);

        $draftStatus = StatusDocument::create(['nama_status' => StatusDocument::DRAFT]);
        DocumentType::create(['nama_types' => 'Prosedur']);
        $level = DocumentLevel::query()->where('kode', 'level-2')->firstOrFail();

        $draft = Document::create([
            'm_document_level_id' => $level->id,
            'm_status_document_id' => $draftStatus->id,
            'm_document_types_id' => DocumentType::query()->where('nama_types', 'Prosedur')->value('id'),
            'm_proses_bisnis_id' => $businessProcess->id,
            'm_proses_fungsi_id' => $businessFunction->id,
            'user_id' => $user->id,
            'official_preparer_id' => $user->id,
            'nama_dokumen' => 'Draft Prosedur Saya',
            'nomor_dokumen' => 'PS-SMR-010',
            'nomor_revisi' => 0,
            'created_at' => now(),
        ]);
        $draft->departments()->sync([$department->id]);
        $draft->files()->create([
            'type_file' => 'filled_template',
            'path_file' => 'documents/1/template.pdf',
            'uploaded_by' => $user->id,
            'updated_at' => now(),
            'original_file_name' => 'template.pdf',
            'stored_file_name' => 'template.pdf',
            'file_size' => 1024,
        ]);

        Document::create([
            'm_document_level_id' => $level->id,
            'm_status_document_id' => $draftStatus->id,
            'm_document_types_id' => DocumentType::query()->where('nama_types', 'Prosedur')->value('id'),
            'm_proses_bisnis_id' => $businessProcess->id,
            'm_proses_fungsi_id' => $businessFunction->id,
            'user_id' => $otherUser->id,
            'nama_dokumen' => 'Draft User Lain',
            'nomor_dokumen' => 'PS-SMR-011',
            'nomor_revisi' => 0,
            'created_at' => now(),
        ]);

        $this->actingAs($user)
            ->get(route('documents.create'))
            ->assertOk()
            ->assertSee('Draft Saya')
            ->assertSee('1');

        $this->actingAs($user)
            ->get(route('documents.create.drafts'))
            ->assertOk()
            ->assertSee('Draft Prosedur Saya')
            ->assertDontSee('Draft User Lain');

        $this->actingAs($user)
            ->get(route('documents.create.drafts.edit', $draft))
            ->assertOk()
            ->assertSee('Draft Prosedur Saya')
            ->assertSee('010')
            ->assertSee('template.pdf')
            ->assertSee('data-existing-file-item', false)
            ->assertSee('Tanpa perwakilan');
    }

    public function test_saving_existing_draft_updates_same_document(): void
    {
        Storage::fake('local');

        $user = User::factory()->create();
        $businessProcess = BusinessProcess::create([
            'kode' => 'SMR',
            'nama_proses_bisnis' => 'Sistem Manajemen Risiko',
        ]);
        $businessFunction = BusinessFunction::create([
            'kode' => 'OPS',
            'nama_proses_fungsi' => 'Operasional',
        ]);
        $department = Department::create([
            'kode_department' => 'QA',
            'nama_department' => 'Quality Assurance',
        ]);

        $draftStatus = StatusDocument::create(['nama_status' => StatusDocument::DRAFT]);
        StatusDocument::create(['nama_status' => StatusDocument::PROPOSED]);
        $documentType = DocumentType::create(['nama_types' => 'Prosedur']);
        $level = DocumentLevel::query()->where('kode', 'level-2')->firstOrFail();

        $draft = Document::create([
            'm_document_level_id' => $level->id,
            'm_status_document_id' => $draftStatus->id,
            'm_document_types_id' => $documentType->id,
            'm_proses_bisnis_id' => $businessProcess->id,
            'm_proses_fungsi_id' => $businessFunction->id,
            'user_id' => $user->id,
            'official_preparer_id' => $user->id,
            'nama_dokumen' => 'Nama Lama',
            'nomor_dokumen' => 'PS-SMR-010',
            'nomor_revisi' => 0,
            'created_at' => now(),
        ]);
        $draft->departments()->sync([$department->id]);

        $this->actingAs($user)
            ->post(route('documents.store', 'level-2'), [
                'draft_id' => $draft->id,
                'nama_dokumen' => 'Nama Baru Draft',
                'm_proses_bisnis_id' => $businessProcess->id,
                'm_proses_fungsi_id' => $businessFunction->id,
                'department_ids' => [$department->id],
                'official_preparer_id' => $user->id,
                'nomor_dokumen_suffix' => '012',
                'filled_template' => UploadedFile::fake()->create('template-baru.pdf', 24, 'application/pdf'),
                'submit_action' => 'draft',
            ])
            ->assertRedirect(route('documents.create.drafts'));

        $this->assertSame(1, Document::query()->count());

        $draft->refresh();
        $this->assertSame('Nama Baru Draft', $draft->nama_dokumen);
        $this->assertSame('PS-OPS-012', $draft->nomor_dokumen);
        $this->assertSame(StatusDocument::DRAFT, $draft->status->nama_status);
        $this->assertTrue($draft->files()->where('original_file_name', 'template-baru.pdf')->exists());
    }

    public function test_user_can_delete_own_draft_from_draft_list(): void
    {
        Storage::fake('local');

        $user = User::factory()->create();
        $businessProcess = BusinessProcess::create([
            'kode' => 'SMR',
            'nama_proses_bisnis' => 'Sistem Manajemen Risiko',
        ]);
        $businessFunction = BusinessFunction::create([
            'kode' => 'OPS',
            'nama_proses_fungsi' => 'Operasional',
        ]);
        $department = Department::create([
            'kode_department' => 'QA',
            'nama_department' => 'Quality Assurance',
        ]);

        $draftStatus = StatusDocument::create(['nama_status' => StatusDocument::DRAFT]);
        $documentType = DocumentType::create(['nama_types' => 'Prosedur']);
        $level = DocumentLevel::query()->where('kode', 'level-2')->firstOrFail();

        $draft = Document::create([
            'm_document_level_id' => $level->id,
            'm_status_document_id' => $draftStatus->id,
            'm_document_types_id' => $documentType->id,
            'm_proses_bisnis_id' => $businessProcess->id,
            'm_proses_fungsi_id' => $businessFunction->id,
            'user_id' => $user->id,
            'official_preparer_id' => $user->id,
            'nama_dokumen' => 'Draft Akan Dihapus',
            'nomor_dokumen' => 'PS-SMR-016',
            'nomor_revisi' => 0,
            'created_at' => now(),
        ]);
        $draft->departments()->sync([$department->id]);

        Storage::disk('local')->put('documents/'.$draft->id.'/template.pdf', 'dummy');
        $file = $draft->files()->create([
            'type_file' => 'filled_template',
            'path_file' => 'documents/'.$draft->id.'/template.pdf',
            'uploaded_by' => $user->id,
            'updated_at' => now(),
            'original_file_name' => 'template.pdf',
            'stored_file_name' => 'template.pdf',
            'file_size' => 1024,
        ]);

        $this->actingAs($user)
            ->get(route('documents.create.drafts'))
            ->assertOk()
            ->assertSee('Draft Akan Dihapus')
            ->assertSee('Hapus')
            ->assertSee(route('documents.create.drafts.destroy', $draft), false);

        $this->actingAs($user)
            ->delete(route('documents.create.drafts.destroy', $draft))
            ->assertRedirect(route('documents.create.drafts'))
            ->assertSessionHas('status', 'Draft berhasil dihapus.');

        $this->assertDatabaseMissing('t_document', [
            'id' => $draft->id,
        ]);
        $this->assertDatabaseMissing('t_document_files', [
            'id' => $file->id,
        ]);
        $this->assertDatabaseMissing('document_departments', [
            't_document_id' => $draft->id,
            'department_id' => $department->id,
        ]);
        Storage::disk('local')->assertMissing('documents/'.$draft->id.'/template.pdf');
    }

    public function test_user_cannot_delete_another_users_draft(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $businessProcess = BusinessProcess::create([
            'kode' => 'SMR',
            'nama_proses_bisnis' => 'Sistem Manajemen Risiko',
        ]);
        $businessFunction = BusinessFunction::create([
            'kode' => 'OPS',
            'nama_proses_fungsi' => 'Operasional',
        ]);

        $draftStatus = StatusDocument::create(['nama_status' => StatusDocument::DRAFT]);
        $documentType = DocumentType::create(['nama_types' => 'Prosedur']);
        $level = DocumentLevel::query()->where('kode', 'level-2')->firstOrFail();

        $draft = Document::create([
            'm_document_level_id' => $level->id,
            'm_status_document_id' => $draftStatus->id,
            'm_document_types_id' => $documentType->id,
            'm_proses_bisnis_id' => $businessProcess->id,
            'm_proses_fungsi_id' => $businessFunction->id,
            'user_id' => $otherUser->id,
            'nama_dokumen' => 'Draft User Lain',
            'nomor_dokumen' => 'PS-SMR-017',
            'nomor_revisi' => 0,
            'created_at' => now(),
        ]);

        $this->actingAs($user)
            ->delete(route('documents.create.drafts.destroy', $draft))
            ->assertForbidden();

        $this->assertDatabaseHas('t_document', [
            'id' => $draft->id,
        ]);
    }

    public function test_saving_existing_draft_can_remove_saved_file(): void
    {
        Storage::fake('local');

        $user = User::factory()->create();
        $businessProcess = BusinessProcess::create([
            'kode' => 'SMR',
            'nama_proses_bisnis' => 'Sistem Manajemen Risiko',
        ]);
        $businessFunction = BusinessFunction::create([
            'kode' => 'OPS',
            'nama_proses_fungsi' => 'Operasional',
        ]);
        $department = Department::create([
            'kode_department' => 'QA',
            'nama_department' => 'Quality Assurance',
        ]);

        $draftStatus = StatusDocument::create(['nama_status' => StatusDocument::DRAFT]);
        StatusDocument::create(['nama_status' => StatusDocument::PROPOSED]);
        $documentType = DocumentType::create(['nama_types' => 'Prosedur']);
        $level = DocumentLevel::query()->where('kode', 'level-2')->firstOrFail();

        $draft = Document::create([
            'm_document_level_id' => $level->id,
            'm_status_document_id' => $draftStatus->id,
            'm_document_types_id' => $documentType->id,
            'm_proses_bisnis_id' => $businessProcess->id,
            'm_proses_fungsi_id' => $businessFunction->id,
            'user_id' => $user->id,
            'official_preparer_id' => $user->id,
            'nama_dokumen' => 'Draft Dengan File',
            'nomor_dokumen' => 'PS-SMR-015',
            'nomor_revisi' => 0,
            'created_at' => now(),
        ]);
        $draft->departments()->sync([$department->id]);

        Storage::disk('local')->put('documents/'.$draft->id.'/template.pdf', 'dummy');
        $file = $draft->files()->create([
            'type_file' => 'filled_template',
            'path_file' => 'documents/'.$draft->id.'/template.pdf',
            'uploaded_by' => $user->id,
            'updated_at' => now(),
            'original_file_name' => 'template.pdf',
            'stored_file_name' => 'template.pdf',
            'file_size' => 1024,
        ]);

        $this->actingAs($user)
            ->post(route('documents.store', 'level-2'), [
                'draft_id' => $draft->id,
                'nama_dokumen' => 'Draft Dengan File',
                'm_proses_bisnis_id' => $businessProcess->id,
                'm_proses_fungsi_id' => $businessFunction->id,
                'department_ids' => [$department->id],
                'official_preparer_id' => $user->id,
                'nomor_dokumen_suffix' => '015',
                'remove_existing_files' => [$file->id],
                'submit_action' => 'draft',
            ])
            ->assertRedirect(route('documents.create.drafts'));

        $this->assertDatabaseMissing('t_document_files', [
            'id' => $file->id,
        ]);
        Storage::disk('local')->assertMissing('documents/'.$draft->id.'/template.pdf');
    }

    public function test_existing_draft_can_be_submitted_without_reuploading_saved_main_file(): void
    {
        Storage::fake('local');

        $user = User::factory()->create();
        $businessProcess = BusinessProcess::create([
            'kode' => 'SMR',
            'nama_proses_bisnis' => 'Sistem Manajemen Risiko',
        ]);
        $businessFunction = BusinessFunction::create([
            'kode' => 'OPS',
            'nama_proses_fungsi' => 'Operasional',
        ]);
        $department = Department::create([
            'kode_department' => 'QA',
            'nama_department' => 'Quality Assurance',
        ]);

        $draftStatus = StatusDocument::create(['nama_status' => StatusDocument::DRAFT]);
        StatusDocument::create(['nama_status' => StatusDocument::PROPOSED]);
        ApprovalStatus::create([
            'kode_status' => ApprovalStatus::APPROVED,
            'nama_status' => 'Disetujui',
        ]);
        $documentType = DocumentType::create(['nama_types' => 'Prosedur']);
        $level = DocumentLevel::query()->where('kode', 'level-2')->firstOrFail();

        $draft = Document::create([
            'm_document_level_id' => $level->id,
            'm_status_document_id' => $draftStatus->id,
            'm_document_types_id' => $documentType->id,
            'm_proses_bisnis_id' => $businessProcess->id,
            'm_proses_fungsi_id' => $businessFunction->id,
            'user_id' => $user->id,
            'official_preparer_id' => $user->id,
            'nama_dokumen' => 'Draft Siap Submit',
            'nomor_dokumen' => 'PS-SMR-014',
            'nomor_revisi' => 0,
            'created_at' => now(),
        ]);
        $draft->departments()->sync([$department->id]);
        $draft->files()->create([
            'type_file' => 'filled_template',
            'path_file' => 'documents/'.$draft->id.'/template.pdf',
            'uploaded_by' => $user->id,
            'updated_at' => now(),
            'original_file_name' => 'template.pdf',
            'stored_file_name' => 'template.pdf',
            'file_size' => 1024,
        ]);
        $draft->files()->create([
            'type_file' => 'filled_template_word',
            'path_file' => 'documents/'.$draft->id.'/template.docx',
            'uploaded_by' => $user->id,
            'updated_at' => now(),
            'original_file_name' => 'template.docx',
            'stored_file_name' => 'template.docx',
            'file_size' => 1024,
        ]);

        $this->actingAs($user)
            ->post(route('documents.store', 'level-2'), [
                'draft_id' => $draft->id,
                'nama_dokumen' => 'Draft Siap Submit',
                'm_proses_bisnis_id' => $businessProcess->id,
                'm_proses_fungsi_id' => $businessFunction->id,
                'department_ids' => [$department->id],
                'official_preparer_id' => $user->id,
                'nomor_dokumen_suffix' => '014',
                'submit_action' => 'submit',
            ])
            ->assertRedirect(route('documents.create'));

        $this->assertSame(1, Document::query()->count());

        $draft->refresh();
        $this->assertSame(StatusDocument::PROPOSED, $draft->status->nama_status);
        $this->assertNotNull($draft->submitted_at);
        $this->assertTrue($draft->approvals()
            ->where('user_id', $user->id)
            ->where('stages', 'TTD Penyusun Resmi')
            ->exists());
    }

    public function test_submitted_document_records_official_preparer_signature_without_stage_assignment(): void
    {
        Storage::fake('local');

        $user = User::factory()->create();
        $officialPreparer = User::factory()->create();
        $businessProcess = BusinessProcess::create([
            'kode' => 'SMR',
            'nama_proses_bisnis' => 'Sistem Manajemen Risiko',
        ]);
        $businessFunction = BusinessFunction::create([
            'kode' => 'OPS',
            'nama_proses_fungsi' => 'Operasional',
        ]);
        $department = Department::create([
            'kode_department' => 'QA',
            'nama_department' => 'Quality Assurance',
        ]);

        StatusDocument::create(['nama_status' => StatusDocument::DRAFT]);
        StatusDocument::create(['nama_status' => StatusDocument::PROPOSED]);
        ApprovalStatus::create([
            'kode_status' => ApprovalStatus::APPROVED,
            'nama_status' => 'Disetujui',
        ]);
        ApprovalStatus::create([
            'kode_status' => ApprovalStatus::PENDING,
            'nama_status' => 'Menunggu',
        ]);
        ApprovalStatus::create([
            'kode_status' => ApprovalStatus::WAITING,
            'nama_status' => 'Menunggu Giliran',
        ]);
        ApprovalStatus::create([
            'kode_status' => ApprovalStatus::REJECTED,
            'nama_status' => 'Ditolak',
        ]);
        ApprovalStatus::create([
            'kode_status' => ApprovalStatus::TERMINATED,
            'nama_status' => 'Dihentikan',
        ]);
        DocumentType::create(['nama_types' => 'Prosedur']);
        DocumentType::create(['nama_types' => 'Form']);

        $this->actingAs($user)
            ->post(route('documents.store', 'level-2'), [
                'nama_dokumen' => 'Prosedur Submit Approval',
                'm_proses_bisnis_id' => $businessProcess->id,
                'm_proses_fungsi_id' => $businessFunction->id,
                'department_ids' => [$department->id],
                'official_preparer_id' => $officialPreparer->id,
                'nomor_dokumen_suffix' => '009',
                'filled_template' => UploadedFile::fake()->create('template.pdf', 24, 'application/pdf'),
                'filled_template_word' => UploadedFile::fake()->create('template.docx', 24, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'),
                'submit_action' => 'submit',
            ])
            ->assertRedirect(route('documents.create'));

        $document = Document::query()->where('nama_dokumen', 'Prosedur Submit Approval')->firstOrFail();

        $this->assertSame(StatusDocument::PROPOSED, $document->status->nama_status);
        $this->assertSame($officialPreparer->id, $document->official_preparer_id);
        $this->assertTrue($document->approvals()
            ->where('user_id', $officialPreparer->id)
            ->where('stages', 'TTD Penyusun Resmi')
            ->whereNotNull('responded_at')
            ->whereHas('status', fn ($query) => $query->where('kode_status', ApprovalStatus::APPROVED))
            ->exists());

        $this->actingAs($officialPreparer)
            ->get(route('documents.inbox', ['tab' => 'needs-process']))
            ->assertOk()
            ->assertDontSee('Prosedur Submit Approval');

        $this->actingAs($officialPreparer)
            ->get(route('documents.inbox', ['tab' => 'processed-history']))
            ->assertOk()
            ->assertSee('Prosedur Submit Approval')
            ->assertDontSee('TTD Penyusun Resmi')
            ->assertSee('Disetujui');

        $this->actingAs($officialPreparer)
            ->get(route('documents.approval.show', $document))
            ->assertOk()
            ->assertSee('Prosedur Submit Approval');

        $this->actingAs($user)
            ->get(route('documents.inbox', ['tab' => 'processed-history']))
            ->assertOk()
            ->assertSee('Prosedur Submit Approval')
            ->assertSee('Pengajuan Dokumen')
            ->assertSee(StatusDocument::PROPOSED);

        $this->actingAs($user)
            ->get(route('documents.approval.show', $document))
            ->assertOk()
            ->assertSee('Prosedur Submit Approval');
    }

    public function test_user_from_document_department_can_submit_revision_from_master_document(): void
    {
        Storage::fake('local');

        $businessProcess = BusinessProcess::create([
            'kode' => 'SMR',
            'nama_proses_bisnis' => 'Sistem Manajemen Risiko',
        ]);
        $businessFunction = BusinessFunction::create([
            'kode' => 'OPS',
            'nama_proses_fungsi' => 'Operasional',
        ]);
        $sourceDepartment = Department::create([
            'kode_department' => 'QA',
            'nama_department' => 'Quality Assurance',
        ]);
        $otherDepartment = Department::create([
            'kode_department' => 'HR',
            'nama_department' => 'Human Resources',
        ]);
        $submitter = User::factory()->create(['m_department_id' => $sourceDepartment->id]);
        $officialPreparer = User::factory()->create();
        $otherUser = User::factory()->create(['m_department_id' => $otherDepartment->id]);
        $userRole = Role::query()->firstOrCreate(['nama_role' => 'User']);
        $submitter->roles()->syncWithoutDetaching([$userRole->id]);
        $level = DocumentLevel::query()->where('kode', 'level-2')->firstOrFail();
        $approvedStatus = StatusDocument::create(['nama_status' => StatusDocument::APPROVED]);
        StatusDocument::create(['nama_status' => StatusDocument::DRAFT]);
        StatusDocument::create(['nama_status' => StatusDocument::PROPOSED]);
        Permission::query()->firstOrCreate(
            ['code' => 'documents.create.level'],
            [
                'name' => 'Lihat Form Tambah Dokumen',
                'module' => 'Manajemen Dokumen',
                'route' => 'documents.create.level',
                'action' => 'view',
            ],
        );
        Permission::query()->firstOrCreate(
            ['code' => 'documents.create.create'],
            [
                'name' => 'Submit Tambah Dokumen',
                'module' => 'Manajemen Dokumen',
                'route' => 'documents.store',
                'action' => 'create',
            ],
        );
        ApprovalStatus::create([
            'kode_status' => ApprovalStatus::APPROVED,
            'nama_status' => 'Disetujui',
        ]);
        ApprovalStatus::create([
            'kode_status' => ApprovalStatus::PENDING,
            'nama_status' => 'Menunggu',
        ]);
        ApprovalStatus::create([
            'kode_status' => ApprovalStatus::WAITING,
            'nama_status' => 'Menunggu Giliran',
        ]);
        ApprovalStatus::create([
            'kode_status' => ApprovalStatus::REJECTED,
            'nama_status' => 'Ditolak',
        ]);
        ApprovalStatus::create([
            'kode_status' => ApprovalStatus::TERMINATED,
            'nama_status' => 'Dihentikan',
        ]);
        DocumentType::create(['nama_types' => 'Prosedur']);
        DocumentType::create(['nama_types' => 'Form']);
        $source = Document::create([
            'm_document_level_id' => $level->id,
            'm_status_document_id' => $approvedStatus->id,
            'm_document_types_id' => DocumentType::query()->where('nama_types', 'Prosedur')->firstOrFail()->id,
            'm_proses_bisnis_id' => $businessProcess->id,
            'm_proses_fungsi_id' => $businessFunction->id,
            'user_id' => $submitter->id,
            'official_preparer_id' => $submitter->id,
            'nama_dokumen' => 'Prosedur Revisi Master',
            'nomor_dokumen' => 'PS-SMR-010',
            'nomor_revisi' => 0,
            'tanggal_terbit' => '2026-08-12',
            'approved_at' => now(),
        ]);
        $source->departments()->sync([$sourceDepartment->id]);

        $this->actingAs($otherUser)
            ->get(route('documents.create.level', ['level-4', 'revised_from' => $source->id]))
            ->assertForbidden();

        $this->actingAs($submitter)
            ->get(route('documents.create.level', ['level-4', 'revised_from' => $source->id]))
            ->assertOk()
            ->assertSee('Dokumen Level IV: Form Prosedur')
            ->assertSee('Import Dokumen Level II: Prosedur SKMBS')
            ->assertSee('Dokumen Revisi')
            ->assertSee('1. Lembar Revisi')
            ->assertSee('2. Dokumen Revisi')
            ->assertSee('Upload Lembar Revisi PDF')
            ->assertSee('Upload Lembar Revisi Word')
            ->assertSee('Upload Dokumen Revisi PDF')
            ->assertSee('Upload Dokumen Revisi Word')
            ->assertSee('Penyusun Pemilik Proses')
            ->assertSee('Pilih Penyusun Resmi')
            ->assertSee('FMPS')
            ->assertSee('SMR')
            ->assertSee('010')
            ->assertSee('00.01')
            ->assertSee('Quality Assurance')
            ->assertDontSee('Tambah Department')
            ->assertDontSee('-Pilih-');

        $this->actingAs($submitter)
            ->post(route('documents.store', 'level-4'), [
                'revised_from' => $source->id,
                'nama_dokumen' => 'Prosedur Revisi Master Updated',
                'm_proses_bisnis_id' => $businessProcess->id,
                'm_proses_fungsi_id' => $businessFunction->id,
                'department_ids' => [$sourceDepartment->id],
                'official_preparer_id' => $officialPreparer->id,
                'nomor_dokumen_suffix' => '999',
                'submit_action' => 'draft',
            ])
            ->assertRedirect(route('documents.create.drafts'));

        $draftRevision = Document::query()
            ->where('nama_dokumen', 'Prosedur Revisi Master Updated')
            ->firstOrFail();

        $this->assertSame(StatusDocument::DRAFT, $draftRevision->status->nama_status);

        $draftRevision->departments()->detach();
        $draftRevision->delete();

        $this->actingAs($submitter)
            ->post(route('documents.store', 'level-4'), [
                'revised_from' => $source->id,
                'nama_dokumen' => 'Prosedur Revisi Master Updated',
                'm_proses_bisnis_id' => $businessProcess->id,
                'm_proses_fungsi_id' => $businessFunction->id,
                'department_ids' => [$sourceDepartment->id],
                'official_preparer_id' => $officialPreparer->id,
                'nomor_dokumen_suffix' => '999',
                'revision_content' => UploadedFile::fake()->create('dokumen-revisi.pdf', 24, 'application/pdf'),
                'revision_content_word' => UploadedFile::fake()->create('dokumen-revisi.docx', 24, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'),
                'revision_form' => UploadedFile::fake()->create('lembar-revisi.pdf', 24, 'application/pdf'),
                'revision_form_word' => UploadedFile::fake()->create('lembar-revisi.docx', 24, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'),
                'submit_action' => 'submit',
            ])
            ->assertRedirect(route('documents.create'));

        $revision = Document::query()
            ->where('nama_dokumen', 'Prosedur Revisi Master Updated')
            ->firstOrFail();

        $this->assertSame($source->id, $revision->revised_from);
        $this->assertSame('PS-SMR-010', $revision->nomor_dokumen);
        $this->assertSame('FMPS-SMR-010-01', $revision->nomor_lembar_revisi);
        $this->assertSame('level-4', $revision->documentLevel->kode);
        $this->assertSame('Form', $revision->documentType->nama_types);
        $this->assertSame('00.01', $revision->nomor_revisi);
        $this->assertSame(StatusDocument::PROPOSED, $revision->status->nama_status);
        $this->assertSame($officialPreparer->id, $revision->official_preparer_id);
        $this->assertSame($businessProcess->id, $revision->m_proses_bisnis_id);
        $this->assertSame($businessFunction->id, $revision->m_proses_fungsi_id);
        $this->assertTrue($revision->departments()->whereKey($sourceDepartment->id)->exists());
        $this->assertFalse($revision->departments()->whereKey($otherDepartment->id)->exists());
        $this->assertTrue($revision->files()->where('type_file', 'revision_content')->exists());
        $this->assertTrue($revision->files()->where('type_file', 'revision_content_word')->exists());
        $this->assertTrue($revision->files()->where('type_file', 'revision_form')->exists());
        $this->assertTrue($revision->files()->where('type_file', 'revision_form_word')->exists());

        $this->actingAs($submitter)
            ->get(route('documents.approval.show', $revision))
            ->assertOk()
            ->assertSee('Dokumen Revisi')
            ->assertSee('Lembar Revisi')
            ->assertSee('dokumen-revisi.pdf')
            ->assertSee('dokumen-revisi.docx')
            ->assertSee('lembar-revisi.pdf')
            ->assertSee('lembar-revisi.docx')
            ->assertDontSee('Assign Approver');

        $this->actingAs($submitter)
            ->get(route('documents.inbox', ['tab' => 'needs-process']))
            ->assertOk()
            ->assertDontSee('Prosedur Revisi Master Updated');

        $this->actingAs($submitter)
            ->get(route('documents.inbox', ['tab' => 'processed-history']))
            ->assertOk()
            ->assertSee('Prosedur Revisi Master Updated')
            ->assertSee('Pengajuan Revisi')
            ->assertSee('FMPS-SMR-010-01')
            ->assertSee(StatusDocument::PROPOSED);

        $documentControlRole = Role::query()->firstOrCreate(['nama_role' => 'Admin Kontrol Dokumen']);
        $assignPermission = Permission::query()->firstOrCreate(
            ['code' => 'documents.approval.assign'],
            [
                'name' => 'Assign Approver Dokumen',
                'module' => 'Manajemen Dokumen',
                'route' => 'documents.approval.assign',
                'action' => 'assign',
            ],
        );
        $documentControlRole->permissions()->syncWithoutDetaching([$assignPermission->id]);
        $documentControlAdmin = User::factory()->create([
            'm_department_id' => $sourceDepartment->id,
            'name' => 'Admin Kontrol Dokumen',
        ]);
        $documentControlAdmin->roles()->attach($documentControlRole);
        $approver = User::factory()->create(['name' => 'Approver Revisi']);
        $flow = ApprovalFlow::create([
            'm_document_level_id' => $source->m_document_level_id,
            'nama_flow' => 'Flow Revisi Prosedur',
        ]);
        $stage = $flow->stages()->create([
            'stage_order' => 1,
            'keterangan' => 'Diperiksa oleh',
            'nama_tahap' => 'Superintendent',
        ]);

        $this->actingAs($documentControlAdmin)
            ->get(route('documents.inbox', ['tab' => 'needs-process']))
            ->assertOk()
            ->assertSee('Prosedur Revisi Master Updated')
            ->assertSee('Form')
            ->assertSee('Belum assign approver')
            ->assertSee('Perlu Verifikasi Admin KD');

        $this->actingAs($documentControlAdmin)
            ->post(route('documents.approval.assign', $revision), [
                'stage_approvers' => [
                    $stage->id => [$approver->id],
                ],
            ])
            ->assertRedirect(route('documents.approval.show', $revision));

        $revision->refresh();
        $this->assertSame('Revisi', $revision->documentType->nama_types);

        $this->actingAs($submitter)
            ->get(route('documents.inbox', ['tab' => 'needs-process']))
            ->assertOk()
            ->assertDontSee('Prosedur Revisi Master Updated');

        $this->actingAs($submitter)
            ->from(route('documents.create.level', ['level-4', 'revised_from' => $source->id]))
            ->post(route('documents.store', 'level-4'), [
                'revised_from' => $source->id,
                'nama_dokumen' => 'Prosedur Revisi Master Kedua',
                'm_proses_bisnis_id' => $businessProcess->id,
                'm_proses_fungsi_id' => $businessFunction->id,
                'department_ids' => [$sourceDepartment->id],
                'official_preparer_id' => $officialPreparer->id,
                'nomor_dokumen_suffix' => '999',
                'revision_content' => UploadedFile::fake()->create('dokumen-revisi-2.pdf', 24, 'application/pdf'),
                'revision_content_word' => UploadedFile::fake()->create('dokumen-revisi-2.docx', 24, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'),
                'revision_form' => UploadedFile::fake()->create('lembar-revisi-2.pdf', 24, 'application/pdf'),
                'revision_form_word' => UploadedFile::fake()->create('lembar-revisi-2.docx', 24, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'),
                'submit_action' => 'submit',
            ])
            ->assertRedirect(route('documents.create.level', ['level-4', 'revised_from' => $source->id]))
            ->assertSessionHasErrors(['revised_from']);

        $this->assertFalse(Document::query()
            ->where('nama_dokumen', 'Prosedur Revisi Master Kedua')
            ->exists());
    }

    public function test_obsolete_document_cannot_be_used_as_revision_source(): void
    {
        $user = User::factory()->create();
        $businessProcess = BusinessProcess::create([
            'kode' => 'SMR',
            'nama_proses_bisnis' => 'Sistem Manajemen Risiko',
        ]);
        $businessFunction = BusinessFunction::create([
            'kode' => 'QA',
            'nama_proses_fungsi' => 'Quality Assurance',
        ]);
        $department = Department::create([
            'kode_department' => 'QA',
            'nama_department' => 'Quality Assurance',
        ]);
        $obsoleteStatus = StatusDocument::create(['nama_status' => StatusDocument::OBSOLETE]);
        $level = DocumentLevel::query()->where('kode', 'level-2')->firstOrFail();
        $type = DocumentType::create(['nama_types' => 'Prosedur']);

        $source = Document::create([
            'm_document_level_id' => $level->id,
            'm_status_document_id' => $obsoleteStatus->id,
            'm_document_types_id' => $type->id,
            'm_proses_bisnis_id' => $businessProcess->id,
            'm_proses_fungsi_id' => $businessFunction->id,
            'user_id' => $user->id,
            'official_preparer_id' => $user->id,
            'nama_dokumen' => 'Prosedur Obsolete',
            'nomor_dokumen' => 'PS-SMR-OLD',
            'nomor_revisi' => 0,
            'approved_at' => now(),
        ]);
        $source->departments()->sync([$department->id]);

        $this->actingAs($user)
            ->get(route('documents.create.level', ['level-4', 'revised_from' => $source->id]))
            ->assertNotFound();
    }

    public function test_active_revision_request_blocks_new_revision_creation(): void
    {
        Storage::fake('local');

        [$source, $submitter, $officialPreparer] = $this->revisionCreationFixture();
        $proposedStatus = StatusDocument::query()->where('nama_status', StatusDocument::PROPOSED)->firstOrFail();
        $formLevel = DocumentLevel::query()->where('kode', 'level-4')->firstOrFail();
        $formType = DocumentType::query()->where('nama_types', 'Form')->firstOrFail();

        Document::create([
            'm_document_level_id' => $formLevel->id,
            'm_status_document_id' => $proposedStatus->id,
            'm_document_types_id' => $formType->id,
            'm_proses_bisnis_id' => $source->m_proses_bisnis_id,
            'm_proses_fungsi_id' => $source->m_proses_fungsi_id,
            'user_id' => $submitter->id,
            'official_preparer_id' => $officialPreparer->id,
            'revised_from' => $source->id,
            'request_type' => 'revision',
            'nama_dokumen' => 'Prosedur Revisi Aktif',
            'nomor_dokumen' => 'PS-SMR-010',
            'nomor_lembar_revisi' => 'FMPS-SMR-010-02',
            'nomor_revisi' => 2,
            'submitted_at' => now(),
        ]);

        $this->actingAs($submitter)
            ->from(route('documents.create.level', ['level-4', 'revised_from' => $source->id]))
            ->post(route('documents.store', 'level-4'), $this->revisionSubmitPayload($source, $officialPreparer, [
                'nama_dokumen' => 'Prosedur Revisi Baru',
            ]))
            ->assertRedirect(route('documents.create.level', ['level-4', 'revised_from' => $source->id]))
            ->assertSessionHasErrors(['revised_from']);

        $this->assertFalse(Document::query()
            ->where('nama_dokumen', 'Prosedur Revisi Baru')
            ->exists());
    }

    public function test_rejected_revision_resubmission_creates_new_attempt_and_reuses_revision_number_and_form_number(): void
    {
        Storage::fake('local');

        [$source, $submitter, $officialPreparer] = $this->revisionCreationFixture();
        $rejectedStatus = StatusDocument::query()->where('nama_status', StatusDocument::REJECTED)->firstOrFail();
        $formLevel = DocumentLevel::query()->where('kode', 'level-4')->firstOrFail();
        $formType = DocumentType::query()->where('nama_types', 'Form')->firstOrFail();

        $rejectedRevision = Document::create([
            'm_document_level_id' => $formLevel->id,
            'm_status_document_id' => $rejectedStatus->id,
            'm_document_types_id' => $formType->id,
            'm_proses_bisnis_id' => $source->m_proses_bisnis_id,
            'm_proses_fungsi_id' => $source->m_proses_fungsi_id,
            'user_id' => $submitter->id,
            'official_preparer_id' => $officialPreparer->id,
            'revised_from' => $source->id,
            'request_type' => 'revision',
            'nama_dokumen' => 'Prosedur Revisi Ditolak',
            'nomor_dokumen' => 'PS-SMR-010',
            'nomor_lembar_revisi' => 'FMPS-SMR-010-01',
            'nomor_revisi' => 1,
            'rejected_at' => now(),
        ]);

        $this->actingAs($submitter)
            ->post(route('documents.store', 'level-4'), $this->revisionSubmitPayload($source, $officialPreparer, [
                'nama_dokumen' => 'Prosedur Revisi Setelah Ditolak',
                'resubmitted_from' => $rejectedRevision->id,
            ]))
            ->assertRedirect(route('documents.create'));

        $revision = Document::query()
            ->where('nama_dokumen', 'Prosedur Revisi Setelah Ditolak')
            ->firstOrFail();

        $this->assertNotSame($rejectedRevision->id, $revision->id);
        $this->assertSame($rejectedRevision->id, $revision->resubmitted_from);
        $this->assertSame(StatusDocument::REJECTED, $rejectedRevision->refresh()->status->nama_status);
        $this->assertSame(StatusDocument::PROPOSED, $revision->status->nama_status);
        $this->assertNull($revision->rejected_at);
        $this->assertSame('00.01', $revision->nomor_revisi);
        $this->assertSame('00.01', $revision->formatted_revision);
        $this->assertSame('PS-SMR-010', $revision->nomor_dokumen);
        $this->assertSame('FMPS-SMR-010-01', $revision->nomor_lembar_revisi);
    }

    public function test_new_revision_request_ignores_rejected_attempt_when_incrementing_revision_number(): void
    {
        Storage::fake('local');

        [$source, $submitter, $officialPreparer] = $this->revisionCreationFixture();
        $rejectedStatus = StatusDocument::query()->where('nama_status', StatusDocument::REJECTED)->firstOrFail();
        $formLevel = DocumentLevel::query()->where('kode', 'level-4')->firstOrFail();
        $formType = DocumentType::query()->where('nama_types', 'Form')->firstOrFail();

        $source->update([
            'nomor_revisi' => 6,
        ]);

        Document::create([
            'm_document_level_id' => $formLevel->id,
            'm_status_document_id' => $rejectedStatus->id,
            'm_document_types_id' => $formType->id,
            'm_proses_bisnis_id' => $source->m_proses_bisnis_id,
            'm_proses_fungsi_id' => $source->m_proses_fungsi_id,
            'user_id' => $submitter->id,
            'official_preparer_id' => $officialPreparer->id,
            'revised_from' => $source->id,
            'request_type' => 'revision',
            'nama_dokumen' => 'Prosedur Revisi Ditolak',
            'nomor_dokumen' => 'PS-SMR-010',
            'nomor_lembar_revisi' => 'FMPS-SMR-010-07',
            'nomor_revisi' => 7,
            'rejected_at' => now(),
        ]);

        $this->actingAs($submitter)
            ->get(route('documents.create.level', ['level-4', 'revised_from' => $source->id]))
            ->assertOk()
            ->assertSee('00.07')
            ->assertDontSee('00.08');

        $this->actingAs($submitter)
            ->post(route('documents.store', 'level-4'), $this->revisionSubmitPayload($source, $officialPreparer, [
                'nama_dokumen' => 'Prosedur Revisi Baru Setelah Rejected',
            ]))
            ->assertRedirect(route('documents.create'));

        $revision = Document::query()
            ->where('nama_dokumen', 'Prosedur Revisi Baru Setelah Rejected')
            ->firstOrFail();

        $this->assertSame('00.07', $revision->nomor_revisi);
        $this->assertSame('00.07', $revision->formatted_revision);
    }

    public function test_multiple_rejected_revision_resubmissions_keep_same_revision_number(): void
    {
        Storage::fake('local');

        [$source, $submitter, $officialPreparer] = $this->revisionCreationFixture();
        $rejectedStatus = StatusDocument::query()->where('nama_status', StatusDocument::REJECTED)->firstOrFail();
        $formLevel = DocumentLevel::query()->where('kode', 'level-4')->firstOrFail();
        $formType = DocumentType::query()->where('nama_types', 'Form')->firstOrFail();

        $firstAttempt = Document::create([
            'm_document_level_id' => $formLevel->id,
            'm_status_document_id' => $rejectedStatus->id,
            'm_document_types_id' => $formType->id,
            'm_proses_bisnis_id' => $source->m_proses_bisnis_id,
            'm_proses_fungsi_id' => $source->m_proses_fungsi_id,
            'user_id' => $submitter->id,
            'official_preparer_id' => $officialPreparer->id,
            'revised_from' => $source->id,
            'request_type' => 'revision',
            'nama_dokumen' => 'Revisi Ditolak Pertama',
            'nomor_dokumen' => 'PS-SMR-010',
            'nomor_lembar_revisi' => 'FMPS-SMR-010-01',
            'nomor_revisi' => 1,
            'rejected_at' => now()->subDay(),
        ]);
        $secondAttempt = Document::create([
            'm_document_level_id' => $formLevel->id,
            'm_status_document_id' => $rejectedStatus->id,
            'm_document_types_id' => $formType->id,
            'm_proses_bisnis_id' => $source->m_proses_bisnis_id,
            'm_proses_fungsi_id' => $source->m_proses_fungsi_id,
            'user_id' => $submitter->id,
            'official_preparer_id' => $officialPreparer->id,
            'revised_from' => $source->id,
            'resubmitted_from' => $firstAttempt->id,
            'request_type' => 'revision',
            'nama_dokumen' => 'Revisi Ditolak Kedua',
            'nomor_dokumen' => 'PS-SMR-010',
            'nomor_lembar_revisi' => 'FMPS-SMR-010-01',
            'nomor_revisi' => 1,
            'rejected_at' => now(),
        ]);

        $this->actingAs($submitter)
            ->get(route('documents.create.level', ['level-4', 'resubmitted_from' => $secondAttempt->id]))
            ->assertOk()
            ->assertSee('00.01')
            ->assertDontSee('00.02');

        $this->actingAs($submitter)
            ->post(route('documents.store', 'level-4'), $this->revisionSubmitPayload($source, $officialPreparer, [
                'nama_dokumen' => 'Revisi Setelah Ditolak Dua Kali',
                'resubmitted_from' => $secondAttempt->id,
            ]))
            ->assertRedirect(route('documents.create'));

        $revision = Document::query()
            ->where('nama_dokumen', 'Revisi Setelah Ditolak Dua Kali')
            ->firstOrFail();

        $this->assertNotSame($secondAttempt->id, $revision->id);
        $this->assertSame($secondAttempt->id, $revision->resubmitted_from);
        $this->assertSame(StatusDocument::REJECTED, $secondAttempt->refresh()->status->nama_status);
        $this->assertSame(StatusDocument::PROPOSED, $revision->status->nama_status);
        $this->assertNull($revision->rejected_at);
        $this->assertSame($source->id, $revision->revised_from);
        $this->assertSame('00.01', $revision->nomor_revisi);
        $this->assertSame('00.01', $revision->formatted_revision);
        $this->assertSame('PS-SMR-010', $revision->nomor_dokumen);
        $this->assertSame('FMPS-SMR-010-01', $revision->nomor_lembar_revisi);
    }

    public function test_rejected_revision_resubmission_copies_previous_files_and_keeps_new_attachment_numbers_sequential(): void
    {
        Storage::fake('local');

        [$source, $submitter, $officialPreparer] = $this->revisionCreationFixture();
        $rejectedStatus = StatusDocument::query()->where('nama_status', StatusDocument::REJECTED)->firstOrFail();
        $formLevel = DocumentLevel::query()->where('kode', 'level-4')->firstOrFail();
        $formType = DocumentType::query()->where('nama_types', 'Form')->firstOrFail();

        foreach ([
            ['Lampiran BAPP', 'FMPS-SMR-010-02', 1],
            ['Lampiran Sketsa', 'FMPS-SMR-010-03', 2],
        ] as [$title, $number, $order]) {
            $source->files()->create([
                'type_file' => 'attachment',
                'document_number' => $number,
                'attachment_title' => $title,
                'attachment_order' => $order,
                'path_file' => "documents/{$source->id}/{$number}.pdf",
                'uploaded_by' => $source->user_id,
                'updated_at' => now(),
                'original_file_name' => "{$title}.pdf",
                'stored_file_name' => "{$title}.pdf",
                'file_size' => 24,
            ]);
            Storage::disk('local')->put("documents/{$source->id}/{$number}.pdf", 'PDF test content');
        }

        $oldRejectedRevision = Document::create([
            'm_document_level_id' => $formLevel->id,
            'm_status_document_id' => $rejectedStatus->id,
            'm_document_types_id' => $formType->id,
            'm_proses_bisnis_id' => $source->m_proses_bisnis_id,
            'm_proses_fungsi_id' => $source->m_proses_fungsi_id,
            'user_id' => $submitter->id,
            'official_preparer_id' => $officialPreparer->id,
            'revised_from' => $source->id,
            'request_type' => 'revision',
            'nama_dokumen' => 'Revisi Lama Ditolak Dengan Lampiran',
            'nomor_dokumen' => 'PS-SMR-010',
            'nomor_lembar_revisi' => 'FMPS-SMR-010-01',
            'nomor_revisi' => 1,
            'rejected_at' => now()->subDay(),
        ]);
        $oldRejectedRevision->files()->create([
            'type_file' => 'attachment',
            'document_number' => 'FMPS-SMR-010-04',
            'attachment_title' => 'Invoice Lama Ditolak',
            'attachment_order' => 3,
            'path_file' => "documents/{$oldRejectedRevision->id}/invoice-lama-ditolak.pdf",
            'uploaded_by' => $submitter->id,
            'updated_at' => now()->subDay(),
            'original_file_name' => 'invoice-lama-ditolak.pdf',
            'stored_file_name' => 'invoice-lama-ditolak.pdf',
            'file_size' => 24,
        ]);

        $rejectedRevision = Document::create([
            'm_document_level_id' => $formLevel->id,
            'm_status_document_id' => $rejectedStatus->id,
            'm_document_types_id' => $formType->id,
            'm_proses_bisnis_id' => $source->m_proses_bisnis_id,
            'm_proses_fungsi_id' => $source->m_proses_fungsi_id,
            'user_id' => $submitter->id,
            'official_preparer_id' => $officialPreparer->id,
            'revised_from' => $source->id,
            'request_type' => 'revision',
            'nama_dokumen' => 'Revisi Ditolak Dengan Lampiran',
            'nomor_dokumen' => 'PS-SMR-010',
            'nomor_lembar_revisi' => 'FMPS-SMR-010-01',
            'nomor_revisi' => 1,
            'rejected_at' => now(),
        ]);
        $rejectedRevision->files()->create([
            'type_file' => 'attachment',
            'document_number' => 'FMPS-SMR-010-04',
            'attachment_title' => 'Invoice Ditolak',
            'attachment_order' => 3,
            'path_file' => "documents/{$rejectedRevision->id}/invoice-ditolak.pdf",
            'uploaded_by' => $submitter->id,
            'updated_at' => now(),
            'original_file_name' => 'invoice-ditolak.pdf',
            'stored_file_name' => 'invoice-ditolak.pdf',
            'file_size' => 24,
        ]);
        Storage::disk('local')->put("documents/{$rejectedRevision->id}/invoice-ditolak.pdf", 'PDF test content');

        $this->actingAs($submitter)
            ->post(route('documents.store', 'level-4'), $this->revisionSubmitPayload($source, $officialPreparer, [
                'nama_dokumen' => 'Revisi Resubmit Dengan Lampiran',
                'resubmitted_from' => $rejectedRevision->id,
                'included_attachment_ids' => $source->files()->where('type_file', 'attachment')->pluck('id')->all(),
                'attachment_titles' => ['Invoice', 'Ringkasan ETA'],
                'attachment_orders' => [3, 4],
                'attachments' => [
                    UploadedFile::fake()->create('invoice.pdf', 24, 'application/pdf'),
                    UploadedFile::fake()->create('ringkasan-eta.pdf', 24, 'application/pdf'),
                ],
            ]))
            ->assertRedirect(route('documents.create'));

        $revision = Document::query()
            ->where('nama_dokumen', 'Revisi Resubmit Dengan Lampiran')
            ->firstOrFail();
        $newAttachments = $revision->files()
            ->where('type_file', 'attachment')
            ->whereNull('source_file_id')
            ->reorder()
            ->orderBy('attachment_order')
            ->get();

        $this->assertSame(StatusDocument::PROPOSED, $revision->status->nama_status);
        $this->assertSame($rejectedRevision->id, $revision->resubmitted_from);
        $this->assertSame(StatusDocument::REJECTED, $rejectedRevision->refresh()->status->nama_status);
        $this->assertSame(['FMPS-SMR-010-05', 'FMPS-SMR-010-06'], $newAttachments->pluck('document_number')->all());
        $this->assertSame(['Invoice', 'Ringkasan ETA'], $newAttachments->pluck('attachment_title')->all());
        $this->assertSame([
            ['number' => 1, 'title' => 'Lembar Revisi', 'document_number' => 'FMPS-SMR-010-01'],
            ['number' => 2, 'title' => 'Lampiran BAPP', 'document_number' => 'FMPS-SMR-010-02'],
            ['number' => 3, 'title' => 'Lampiran Sketsa', 'document_number' => 'FMPS-SMR-010-03'],
            ['number' => 4, 'title' => 'Invoice Ditolak', 'document_number' => 'FMPS-SMR-010-04'],
            ['number' => 5, 'title' => 'Invoice', 'document_number' => 'FMPS-SMR-010-05'],
            ['number' => 6, 'title' => 'Ringkasan ETA', 'document_number' => 'FMPS-SMR-010-06'],
        ], collect(app(FinalArtifactGenerator::class)->collectAttachments($revision))->map(
            fn (array $attachment): array => [
                'number' => $attachment['number'],
                'title' => $attachment['title'],
                'document_number' => $attachment['document_number'],
            ],
        )->all());
        $this->assertTrue(Storage::disk('local')->exists("documents/{$rejectedRevision->id}/invoice-ditolak.pdf"));
        $this->assertSame(3, Document::query()->where('revised_from', $source->id)->where('request_type', 'revision')->count());
    }

    public function test_revision_submit_with_new_attachment_compacts_active_attachment_numbers(): void
    {
        Storage::fake('local');

        [$source, $submitter, $officialPreparer] = $this->revisionCreationFixture();
        $approvedStatus = StatusDocument::query()->where('nama_status', StatusDocument::APPROVED)->firstOrFail();
        $formLevel = DocumentLevel::query()->where('kode', 'level-4')->firstOrFail();
        $formType = DocumentType::query()->where('nama_types', 'Form')->firstOrFail();

        foreach ([
            ['Lampiran BAPP', 'FMPS-SMR-010-02', 1],
            ['Lampiran Sketsa', 'FMPS-SMR-010-03', 2],
        ] as [$title, $number, $order]) {
            $source->files()->create([
                'type_file' => 'attachment',
                'document_number' => $number,
                'attachment_title' => $title,
                'attachment_order' => $order,
                'path_file' => "documents/{$source->id}/{$number}.pdf",
                'uploaded_by' => $source->user_id,
                'updated_at' => now(),
                'original_file_name' => "{$title}.pdf",
                'stored_file_name' => "{$title}.pdf",
                'file_size' => 24,
            ]);
            Storage::disk('local')->put("documents/{$source->id}/{$number}.pdf", 'PDF test content');
        }

        $approvedRevision = Document::create([
            'm_document_level_id' => $formLevel->id,
            'm_status_document_id' => $approvedStatus->id,
            'm_document_types_id' => $formType->id,
            'm_proses_bisnis_id' => $source->m_proses_bisnis_id,
            'm_proses_fungsi_id' => $source->m_proses_fungsi_id,
            'user_id' => $submitter->id,
            'official_preparer_id' => $officialPreparer->id,
            'revised_from' => $source->id,
            'request_type' => 'revision',
            'nama_dokumen' => 'Revisi Lama Approved',
            'nomor_dokumen' => 'PS-SMR-010',
            'nomor_lembar_revisi' => 'FMPS-SMR-010-01',
            'nomor_revisi' => 1,
            'approved_at' => now()->subDay(),
        ]);
        $approvedRevision->files()->create([
            'type_file' => 'attachment',
            'document_number' => 'FMPS-SMR-010-04',
            'attachment_title' => 'Lampiran Approved Lama',
            'attachment_order' => 3,
            'path_file' => "documents/{$approvedRevision->id}/approved-lama.pdf",
            'uploaded_by' => $submitter->id,
            'updated_at' => now()->subDay(),
            'original_file_name' => 'approved-lama.pdf',
            'stored_file_name' => 'approved-lama.pdf',
            'file_size' => 24,
        ]);

        $this->actingAs($submitter)
            ->post(route('documents.store', 'level-4'), $this->revisionSubmitPayload($source, $officialPreparer, [
                'nama_dokumen' => 'Revisi Baru Dengan Lampiran',
                'included_attachment_ids' => $source->files()->where('type_file', 'attachment')->pluck('id')->all(),
                'attachment_titles' => ['Tambah Dari Pengajuan Revisi'],
                'attachment_orders' => [3],
                'attachments' => [
                    UploadedFile::fake()->create('tambah-dari-pengajuan.pdf', 24, 'application/pdf'),
                ],
            ]))
            ->assertRedirect(route('documents.create'));

        $revision = Document::query()
            ->where('nama_dokumen', 'Revisi Baru Dengan Lampiran')
            ->firstOrFail();

        $this->assertSame([
            ['number' => 1, 'title' => 'Lembar Revisi', 'document_number' => 'FMPS-SMR-010-01'],
            ['number' => 2, 'title' => 'Lampiran BAPP', 'document_number' => 'FMPS-SMR-010-02'],
            ['number' => 3, 'title' => 'Lampiran Sketsa', 'document_number' => 'FMPS-SMR-010-03'],
            ['number' => 4, 'title' => 'Tambah Dari Pengajuan Revisi', 'document_number' => 'FMPS-SMR-010-04'],
        ], collect(app(FinalArtifactGenerator::class)->collectAttachments($revision))->map(
            fn (array $attachment): array => [
                'number' => $attachment['number'],
                'title' => $attachment['title'],
                'document_number' => $attachment['document_number'],
            ],
        )->all());
    }

    public function test_level_four_revision_from_work_instruction_uses_fmik_document_number(): void
    {
        $submitter = User::factory()->create();
        $officialPreparer = User::factory()->create();
        $businessProcess = BusinessProcess::create([
            'kode' => 'MRI',
            'nama_proses_bisnis' => 'Manajemen Risiko Industri',
        ]);
        $businessFunction = BusinessFunction::create([
            'kode' => 'OPS',
            'nama_proses_fungsi' => 'Operasional',
        ]);
        $department = Department::create([
            'kode_department' => 'QA',
            'nama_department' => 'Quality Assurance',
        ]);
        $submitter->forceFill(['m_department_id' => $department->id])->save();
        $approvedStatus = StatusDocument::create(['nama_status' => StatusDocument::APPROVED]);
        StatusDocument::create(['nama_status' => StatusDocument::DRAFT]);
        DocumentType::create(['nama_types' => 'IK']);
        DocumentType::create(['nama_types' => 'Form']);
        $level = DocumentLevel::query()->where('kode', 'level-3')->firstOrFail();

        $source = Document::create([
            'm_document_level_id' => $level->id,
            'm_status_document_id' => $approvedStatus->id,
            'm_document_types_id' => DocumentType::query()->where('nama_types', 'IK')->firstOrFail()->id,
            'm_proses_bisnis_id' => $businessProcess->id,
            'm_proses_fungsi_id' => $businessFunction->id,
            'user_id' => $submitter->id,
            'official_preparer_id' => $submitter->id,
            'nama_dokumen' => 'Instruksi Kerja Revisi Master',
            'nomor_dokumen' => 'IK-MRI-01-04',
            'nomor_revisi' => 0,
            'approved_at' => now(),
        ]);
        $source->departments()->sync([$department->id]);

        $this->actingAs($submitter)
            ->get(route('documents.create.level', ['level-4', 'revised_from' => $source->id]))
            ->assertOk()
            ->assertSee('Dokumen Level IV: Form Instruksi Kerja')
            ->assertSeeInOrder(['FMIK', 'MRI', '01', '04', '01'])
            ->assertSee('name="nomor_dokumen_suffix"', false)
            ->assertSee('readonly', false);

        $this->actingAs($submitter)
            ->post(route('documents.store', 'level-4'), [
                'revised_from' => $source->id,
                'nama_dokumen' => 'Instruksi Kerja Revisi Master Updated',
                'm_proses_bisnis_id' => $businessProcess->id,
                'm_proses_fungsi_id' => $businessFunction->id,
                'department_ids' => [$department->id],
                'official_preparer_id' => $officialPreparer->id,
                'nomor_dokumen_suffix' => '999',
                'submit_action' => 'draft',
            ])
            ->assertRedirect(route('documents.create.drafts'));

        $revision = Document::query()
            ->where('nama_dokumen', 'Instruksi Kerja Revisi Master Updated')
            ->firstOrFail();

        $this->assertSame('IK-MRI-01-04', $revision->nomor_dokumen);
        $this->assertSame('FMIK-MRI-01-04-01', $revision->nomor_lembar_revisi);
    }

    public function test_level_three_document_can_be_saved_as_draft(): void
    {
        Storage::fake('local');

        $user = User::factory()->create();
        $businessProcess = BusinessProcess::create([
            'kode' => 'SMR',
            'nama_proses_bisnis' => 'Sistem Manajemen Risiko',
        ]);
        $businessFunction = BusinessFunction::create([
            'kode' => 'OPS',
            'nama_proses_fungsi' => 'Operasional',
        ]);
        $department = Department::create([
            'kode_department' => 'QA',
            'nama_department' => 'Quality Assurance',
        ]);
        $secondDepartment = Department::create([
            'kode_department' => 'OPS',
            'nama_department' => 'Operasional',
        ]);

        StatusDocument::create(['nama_status' => StatusDocument::DRAFT]);
        StatusDocument::create(['nama_status' => StatusDocument::PROPOSED]);
        $approvedStatus = StatusDocument::create(['nama_status' => StatusDocument::APPROVED]);
        $procedureType = DocumentType::create(['nama_types' => 'Prosedur']);
        DocumentType::create(['nama_types' => 'IK']);

        $level = DocumentLevel::query()->where('kode', 'level-3')->firstOrFail();
        $procedureLevel = DocumentLevel::query()->where('kode', 'level-2')->firstOrFail();
        $procedure = Document::create([
            'm_document_level_id' => $procedureLevel->id,
            'm_status_document_id' => $approvedStatus->id,
            'm_document_types_id' => $procedureType->id,
            'm_proses_bisnis_id' => $businessProcess->id,
            'm_proses_fungsi_id' => $businessFunction->id,
            'user_id' => $user->id,
            'nama_dokumen' => 'Prosedur Pengujian',
            'nomor_dokumen' => 'PS-SMR-001',
        ]);

        $this->actingAs($user)
            ->post(route('documents.store', 'level-3'), [
                'm_document_level_id' => $level->id,
                'nama_dokumen' => 'Instruksi Kerja Pengujian',
                'm_proses_bisnis_id' => $businessProcess->id,
                'm_proses_fungsi_id' => $businessFunction->id,
                'reference' => "existing-{$procedure->id}",
                'department_ids' => [$department->id, $secondDepartment->id],
                'official_preparer_id' => $user->id,
                'nomor_dokumen_suffix' => '001',
                'filled_template' => UploadedFile::fake()->create('template.pdf', 24, 'application/pdf'),
                'submit_action' => 'draft',
            ])
            ->assertRedirect(route('documents.create.drafts'));

        $document = Document::query()
            ->where('nama_dokumen', 'Instruksi Kerja Pengujian')
            ->firstOrFail();

        $this->assertSame($level->id, $document->m_document_level_id);
        $this->assertSame('Instruksi Kerja Pengujian', $document->nama_dokumen);
        $this->assertSame($user->id, $document->official_preparer_id);
        $this->assertDatabaseHas('document_relations', [
            'source_document_id' => $document->id,
            'target_document_id' => $procedure->id,
            'relation_type' => DocumentRelation::REFERENCES,
        ]);
        $this->assertSame('IK-SMR-001-001', $document->nomor_dokumen);
        $this->assertTrue($document->departments()->whereKey($department->id)->exists());
        $this->assertTrue($document->departments()->whereKey($secondDepartment->id)->exists());
    }

    public function test_level_three_draft_keeps_uploaded_file_without_defaulting_official_preparer(): void
    {
        Storage::fake('local');

        $user = User::factory()->create();
        $businessProcess = BusinessProcess::create([
            'kode' => 'SMR',
            'nama_proses_bisnis' => 'Sistem Manajemen Risiko',
        ]);
        $businessFunction = BusinessFunction::create([
            'kode' => 'OPS',
            'nama_proses_fungsi' => 'Operasional',
        ]);
        $department = Department::create([
            'kode_department' => 'QA',
            'nama_department' => 'Quality Assurance',
        ]);
        $approvedStatus = StatusDocument::create(['nama_status' => StatusDocument::APPROVED]);
        StatusDocument::create(['nama_status' => StatusDocument::DRAFT]);
        DocumentType::create(['nama_types' => 'Instruksi Kerja']);
        $procedureType = DocumentType::create(['nama_types' => 'Prosedur']);
        $procedureLevel = DocumentLevel::query()->where('kode', 'level-2')->firstOrFail();

        $procedure = Document::create([
            'm_document_level_id' => $procedureLevel->id,
            'm_status_document_id' => $approvedStatus->id,
            'm_document_types_id' => $procedureType->id,
            'm_proses_bisnis_id' => $businessProcess->id,
            'm_proses_fungsi_id' => $businessFunction->id,
            'user_id' => $user->id,
            'nama_dokumen' => 'Prosedur Acuan',
            'nomor_dokumen' => 'PS-SMR-001',
            'nomor_revisi' => 0,
            'approved_at' => now(),
        ]);

        $this->actingAs($user)
            ->post(route('documents.store', 'level-3'), [
                'nama_dokumen' => 'Draft IK Dengan File',
                'm_proses_bisnis_id' => $businessProcess->id,
                'm_proses_fungsi_id' => $businessFunction->id,
                'reference' => "existing-{$procedure->id}",
                'department_ids' => [$department->id],
                'nomor_dokumen_suffix' => '020',
                'filled_template' => UploadedFile::fake()->create('template-draft.pdf', 24, 'application/pdf'),
                'submit_action' => 'draft',
            ])
            ->assertRedirect(route('documents.create.drafts'));

        $document = Document::query()
            ->where('nama_dokumen', 'Draft IK Dengan File')
            ->firstOrFail();

        $this->assertNull($document->official_preparer_id);
        $this->assertTrue($document->files()
            ->where('type_file', 'filled_template')
            ->where('original_file_name', 'template-draft.pdf')
            ->exists());
    }

    public function test_required_fields_are_validated(): void
    {
        $user = User::factory()->create();
        DocumentType::create(['nama_types' => 'IK']);

        $this->actingAs($user)
            ->from(route('documents.create.level', 'level-3'))
            ->post(route('documents.store', 'level-3'), [
                'submit_action' => 'submit',
            ])
            ->assertRedirect(route('documents.create.level', 'level-3'))
            ->assertSessionHasErrors([
                'nama_dokumen',
                'm_proses_bisnis_id',
                'm_proses_fungsi_id',
                'reference',
                'department_ids',
                'official_preparer_id',
                'nomor_dokumen_suffix',
                'filled_template',
            ]);
    }

    public function test_level_three_empty_draft_can_be_saved(): void
    {
        $user = User::factory()->create();
        BusinessProcess::create([
            'kode' => 'SMR',
            'nama_proses_bisnis' => 'Sistem Manajemen Risiko',
        ]);
        BusinessFunction::create([
            'kode' => 'OPS',
            'nama_proses_fungsi' => 'Operasional',
        ]);
        StatusDocument::create(['nama_status' => StatusDocument::DRAFT]);
        DocumentType::create(['nama_types' => 'Instruksi Kerja']);

        $this->actingAs($user)
            ->post(route('documents.store', 'level-3'), [
                'submit_action' => 'draft',
            ])
            ->assertRedirect(route('documents.create.drafts'));

        $document = Document::query()->firstOrFail();

        $this->assertSame('Draft tanpa judul', $document->nama_dokumen);
        $this->assertNull($document->m_proses_bisnis_id);
        $this->assertNull($document->m_proses_fungsi_id);
        $this->assertNull($document->official_preparer_id);
        $this->assertNull($document->nomor_dokumen);
    }

    public function test_level_three_reference_must_match_selected_process_and_function(): void
    {
        Storage::fake('local');

        $user = User::factory()->create();
        $businessProcess = BusinessProcess::create([
            'kode' => 'SMR',
            'nama_proses_bisnis' => 'Sistem Manajemen Risiko',
        ]);
        $otherBusinessProcess = BusinessProcess::create([
            'kode' => 'OPS',
            'nama_proses_bisnis' => 'Operasional',
        ]);
        $businessFunction = BusinessFunction::create([
            'kode' => 'QA',
            'nama_proses_fungsi' => 'Quality Assurance',
        ]);
        $department = Department::create([
            'kode_department' => 'QA',
            'nama_department' => 'Quality Assurance',
        ]);
        $approvedStatus = StatusDocument::create(['nama_status' => StatusDocument::APPROVED]);
        StatusDocument::create(['nama_status' => StatusDocument::DRAFT]);
        StatusDocument::create(['nama_status' => StatusDocument::PROPOSED]);
        $procedureType = DocumentType::create(['nama_types' => 'Prosedur']);
        DocumentType::create(['nama_types' => 'IK']);

        $procedureLevel = DocumentLevel::query()->where('kode', 'level-2')->firstOrFail();
        $procedure = Document::create([
            'm_document_level_id' => $procedureLevel->id,
            'm_status_document_id' => $approvedStatus->id,
            'm_document_types_id' => $procedureType->id,
            'm_proses_bisnis_id' => $otherBusinessProcess->id,
            'm_proses_fungsi_id' => $businessFunction->id,
            'user_id' => $user->id,
            'nama_dokumen' => 'Prosedur Operasional',
            'nomor_dokumen' => 'PS-OPS-001',
        ]);

        $this->actingAs($user)
            ->from(route('documents.create.level', 'level-3'))
            ->post(route('documents.store', 'level-3'), [
                'nama_dokumen' => 'Instruksi Kerja Pengujian',
                'm_proses_bisnis_id' => $businessProcess->id,
                'm_proses_fungsi_id' => $businessFunction->id,
                'reference' => "existing-{$procedure->id}",
                'department_ids' => [$department->id],
                'official_preparer_id' => $user->id,
                'nomor_dokumen_suffix' => '001',
                'filled_template' => UploadedFile::fake()->create('template.pdf', 24, 'application/pdf'),
                'filled_template_word' => UploadedFile::fake()->create('template.docx', 24, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'),
                'submit_action' => 'submit',
            ])
            ->assertRedirect(route('documents.create.level', 'level-3'))
            ->assertSessionHasErrors(['reference']);
    }

    public function test_document_number_must_be_unique(): void
    {
        Storage::fake('local');

        $user = User::factory()->create();
        $businessProcess = BusinessProcess::create([
            'kode' => 'SMR',
            'nama_proses_bisnis' => 'Sistem Manajemen Risiko',
        ]);
        $businessFunction = BusinessFunction::create([
            'kode' => 'QA',
            'nama_proses_fungsi' => 'Quality Assurance',
        ]);
        $department = Department::create([
            'kode_department' => 'QA',
            'nama_department' => 'Quality Assurance',
        ]);
        StatusDocument::create(['nama_status' => StatusDocument::DRAFT]);
        $proposedStatus = StatusDocument::create(['nama_status' => StatusDocument::PROPOSED]);
        $documentType = DocumentType::create(['nama_types' => 'Prosedur']);
        $level = DocumentLevel::query()->where('kode', 'level-2')->firstOrFail();

        Document::create([
            'm_document_level_id' => $level->id,
            'm_status_document_id' => $proposedStatus->id,
            'm_document_types_id' => $documentType->id,
            'm_proses_bisnis_id' => $businessProcess->id,
            'm_proses_fungsi_id' => $businessFunction->id,
            'user_id' => $user->id,
            'nama_dokumen' => 'Prosedur Lama',
            'nomor_dokumen' => 'PS-QA-001',
        ]);

        $this->actingAs($user)
            ->from(route('documents.create.level', 'level-2'))
            ->post(route('documents.store', 'level-2'), [
                'nama_dokumen' => 'Prosedur Baru',
                'm_proses_bisnis_id' => $businessProcess->id,
                'm_proses_fungsi_id' => $businessFunction->id,
                'department_ids' => [$department->id],
                'official_preparer_id' => $user->id,
                'nomor_dokumen_suffix' => '001',
                'filled_template' => UploadedFile::fake()->create('template.pdf', 24, 'application/pdf'),
                'filled_template_word' => UploadedFile::fake()->create('template.docx', 24, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'),
                'submit_action' => 'submit',
            ])
            ->assertRedirect(route('documents.create.level', 'level-2'))
            ->assertSessionHasErrors(['nomor_dokumen_suffix']);
    }

    public function test_rejected_initial_submission_number_can_be_reused_with_resubmission_chain(): void
    {
        Storage::fake('local');

        [$user, $businessProcess, $businessFunction, $department, $level, $documentType] = $this->initialResubmissionFixture();
        $rejectedStatus = StatusDocument::query()->where('nama_status', StatusDocument::REJECTED)->firstOrFail();
        $previous = $this->createRejectedInitialAttempt($user, $level, $documentType, $businessProcess, $businessFunction, 'PS-QA-003', [
            'm_status_document_id' => $rejectedStatus->id,
        ]);

        $this->actingAs($user)
            ->post(route('documents.store', 'level-2'), $this->initialSubmitPayload($businessProcess, $businessFunction, $department, '003'))
            ->assertRedirect(route('documents.create'));

        $newDocument = Document::query()
            ->where('nomor_dokumen', 'PS-QA-003')
            ->whereKeyNot($previous->id)
            ->firstOrFail();

        $this->assertSame($previous->id, $newDocument->resubmitted_from);
        $this->assertSame(StatusDocument::REJECTED, $previous->refresh()->status->nama_status);
    }

    public function test_rejected_initial_submission_resubmit_prefills_and_copies_previous_file(): void
    {
        Storage::fake('local');

        [$user, $businessProcess, $businessFunction, $department, $level, $documentType] = $this->initialResubmissionFixture();
        $previous = $this->createRejectedInitialAttempt($user, $level, $documentType, $businessProcess, $businessFunction, 'PS-QA-013', [
            'nama_dokumen' => 'Prosedur Ditolak Perlu Revisi',
        ]);
        $previousFile = $previous->files()->create([
            'type_file' => 'filled_template',
            'path_file' => "documents/{$previous->id}/template-ditolak.pdf",
            'uploaded_by' => $user->id,
            'updated_at' => now(),
            'original_file_name' => 'template-ditolak.pdf',
            'stored_file_name' => 'template-ditolak.pdf',
            'file_size' => 24,
        ]);
        $previousWordFile = $previous->files()->create([
            'type_file' => 'filled_template_word',
            'path_file' => "documents/{$previous->id}/template-ditolak.docx",
            'uploaded_by' => $user->id,
            'updated_at' => now(),
            'original_file_name' => 'template-ditolak.docx',
            'stored_file_name' => 'template-ditolak.docx',
            'file_size' => 24,
        ]);
        Storage::disk('local')->put($previousFile->path_file, 'PDF test content');
        Storage::disk('local')->put($previousWordFile->path_file, 'Word test content');

        $this->actingAs($user)
            ->get(route('documents.rejected.resubmit', $previous))
            ->assertRedirect(route('documents.create.level', [
                'level' => 'level-2',
                'resubmitted_from' => $previous->id,
            ]));

        $this->actingAs($user)
            ->get(route('documents.create.level', [
                'level' => 'level-2',
                'resubmitted_from' => $previous->id,
            ]))
            ->assertOk()
            ->assertSee('Prosedur Ditolak Perlu Revisi')
            ->assertSee('template-ditolak.pdf');

        $this->actingAs($user)
            ->post(route('documents.store', 'level-2'), [
                'nama_dokumen' => 'Prosedur Ditolak Perlu Revisi',
                'm_proses_bisnis_id' => $businessProcess->id,
                'm_proses_fungsi_id' => $businessFunction->id,
                'department_ids' => [$department->id],
                'official_preparer_id' => $user->id,
                'nomor_dokumen_suffix' => '999',
                'resubmitted_from' => $previous->id,
                'submit_action' => 'submit',
            ])
            ->assertRedirect(route('documents.create'));

        $resubmission = Document::query()
            ->where('nomor_dokumen', 'PS-QA-013')
            ->whereKeyNot($previous->id)
            ->firstOrFail();
        $copiedFile = $resubmission->files()->where('source_file_id', $previousFile->id)->firstOrFail();

        $this->assertSame($previous->id, $resubmission->resubmitted_from);
        $this->assertSame('template-ditolak.pdf', $copiedFile->original_file_name);
        Storage::disk('local')->assertExists($copiedFile->path_file);
        Storage::disk('local')->assertExists($previousFile->path_file);
    }

    public function test_rejected_initial_submission_clears_file_numbers_before_resubmission(): void
    {
        Storage::fake('local');

        [$user, $businessProcess, $businessFunction, $department] = $this->initialResubmissionFixture();
        $approver = User::factory()->create();

        $this->actingAs($user)
            ->post(route('documents.store', 'level-2'), $this->initialSubmitPayload($businessProcess, $businessFunction, $department, '008', [
                'attachment_titles' => ['Lampiran Ditolak A', 'Lampiran Ditolak B'],
                'attachment_orders' => [1, 2],
                'attachments' => [
                    UploadedFile::fake()->create('lampiran-ditolak-a.pdf', 24, 'application/pdf'),
                    UploadedFile::fake()->create('lampiran-ditolak-b.pdf', 24, 'application/pdf'),
                ],
            ]))
            ->assertRedirect(route('documents.create'));

        $rejectedAttempt = Document::query()
            ->where('nomor_dokumen', 'PS-QA-008')
            ->firstOrFail();

        Approval::create([
            't_document_id' => $rejectedAttempt->id,
            'm_approval_status_id' => ApprovalStatus::findByCode(ApprovalStatus::PENDING)->id,
            'user_id' => $approver->id,
            'role_id' => null,
            'assigned_by' => $user->id,
            'assigned_at' => now(),
            'stages' => 'Approval Dokumen',
        ]);

        $this->actingAs($approver)
            ->post(route('documents.approval.reject', $rejectedAttempt), [
                'catatan' => 'Lampiran perlu diperbaiki.',
            ])
            ->assertRedirect(route('documents.approval.show', $rejectedAttempt));

        $rejectedAttempt->refresh();

        $this->assertSame(StatusDocument::REJECTED, $rejectedAttempt->status->nama_status);
        $this->assertSame(0, $rejectedAttempt->files()->whereNotNull('document_number')->count());

        $this->actingAs($user)
            ->post(route('documents.store', 'level-2'), $this->initialSubmitPayload($businessProcess, $businessFunction, $department, '008', [
                'attachment_titles' => ['Lampiran Baru'],
                'attachment_orders' => [1],
                'attachments' => [
                    UploadedFile::fake()->create('lampiran-baru.pdf', 24, 'application/pdf'),
                ],
            ]))
            ->assertRedirect(route('documents.create'));

        $resubmission = Document::query()
            ->where('nomor_dokumen', 'PS-QA-008')
            ->whereKeyNot($rejectedAttempt->id)
            ->firstOrFail();
        $attachment = $resubmission->files()
            ->where('type_file', 'attachment')
            ->firstOrFail();

        $this->assertSame($rejectedAttempt->id, $resubmission->resubmitted_from);
        $this->assertSame('FMPS-QA-008-02', $attachment->document_number);
    }

    public function test_multiple_rejected_initial_submissions_keep_immediate_chain_and_history_notes(): void
    {
        Storage::fake('local');

        [$user, $businessProcess, $businessFunction, $department, $level, $documentType] = $this->initialResubmissionFixture();
        $attempts = collect();
        $previous = null;

        foreach (range(1, 4) as $index) {
            $attempt = $this->createRejectedInitialAttempt($user, $level, $documentType, $businessProcess, $businessFunction, 'PS-QA-004', [
                'nama_dokumen' => "Attempt {$index}",
                'resubmitted_from' => $previous?->id,
            ], "Catatan penolakan {$index}");

            $attempts->push($attempt);
            $previous = $attempt;
        }

        $this->actingAs($user)
            ->post(route('documents.store', 'level-2'), $this->initialSubmitPayload($businessProcess, $businessFunction, $department, '004'))
            ->assertRedirect(route('documents.create'));

        $active = Document::query()
            ->where('nomor_dokumen', 'PS-QA-004')
            ->whereHas('status', fn ($query) => $query->where('nama_status', StatusDocument::PROPOSED))
            ->firstOrFail();

        $this->assertSame($attempts->last()->id, $active->resubmitted_from);
        $this->assertCount(5, Document::query()->where('nomor_dokumen', 'PS-QA-004')->get());

        $history = app(DocumentRejectionHistory::class)->forDocument($active);

        $this->assertSame(
            ['Catatan penolakan 1', 'Catatan penolakan 2', 'Catatan penolakan 3', 'Catatan penolakan 4'],
            $history->pluck('catatan')->all(),
        );
        $this->assertSame($attempts->pluck('id')->all(), $history->pluck('document_id')->all());

        $this->actingAs($user)
            ->get(route('documents.approval.show', $active))
            ->assertOk()
            ->assertSee('Pengajuan ulang dibuat dari transaksi #'.$attempts->last()->id)
            ->assertSee('Catatan penolakan 4');
    }

    public function test_rejected_initial_resubmission_normalizes_single_digit_suffix_before_linking_history(): void
    {
        Storage::fake('local');

        [$user, $businessProcess, $businessFunction, $department, $level, $documentType] = $this->initialResubmissionFixture();
        $firstAttempt = $this->createRejectedInitialAttempt(
            $user,
            $level,
            $documentType,
            $businessProcess,
            $businessFunction,
            'PS-QA-07',
            [],
            'Catatan penolakan awal',
        );
        $secondAttempt = $this->createRejectedInitialAttempt(
            $user,
            $level,
            $documentType,
            $businessProcess,
            $businessFunction,
            'PS-QA-07',
            ['resubmitted_from' => $firstAttempt->id],
            'Catatan penolakan kedua',
        );

        $this->actingAs($user)
            ->post(route('documents.store', 'level-2'), $this->initialSubmitPayload($businessProcess, $businessFunction, $department, '7'))
            ->assertRedirect(route('documents.create'));

        $active = Document::query()
            ->where('nomor_dokumen', 'PS-QA-07')
            ->whereHas('status', fn ($query) => $query->where('nama_status', StatusDocument::PROPOSED))
            ->firstOrFail();

        $this->assertSame($secondAttempt->id, $active->resubmitted_from);
        $this->assertFalse(Document::query()->where('nomor_dokumen', 'PS-QA-7')->exists());

        $history = app(DocumentRejectionHistory::class)->forDocument($active);

        $this->assertSame(
            ['Catatan penolakan awal', 'Catatan penolakan kedua'],
            $history->pluck('catatan')->all(),
        );
    }

    public function test_document_number_suffix_must_be_alphanumeric(): void
    {
        Storage::fake('local');

        [$user, $businessProcess, $businessFunction, $department] = $this->initialResubmissionFixture();

        $this->actingAs($user)
            ->from(route('documents.create.level', 'level-2'))
            ->post(route('documents.store', 'level-2'), $this->initialSubmitPayload($businessProcess, $businessFunction, $department, '07-A'))
            ->assertRedirect(route('documents.create.level', 'level-2'))
            ->assertSessionHasErrors(['nomor_dokumen_suffix']);
    }

    public function test_drafts_do_not_lock_document_number_until_one_user_submits_it(): void
    {
        Storage::fake('local');

        [$firstUser, $businessProcess, $businessFunction, $department, $level, $documentType] = $this->initialResubmissionFixture();
        $secondUser = User::factory()->create();
        $thirdUser = User::factory()->create();
        $draftStatus = StatusDocument::query()->where('nama_status', StatusDocument::DRAFT)->firstOrFail();
        $drafts = collect([$firstUser, $secondUser])->map(function (User $user, int $index) use ($businessProcess, $businessFunction, $department, $level, $documentType, $draftStatus): Document {
            $draft = Document::create([
                'm_document_level_id' => $level->id,
                'm_status_document_id' => $draftStatus->id,
                'm_document_types_id' => $documentType->id,
                'm_proses_bisnis_id' => $businessProcess->id,
                'm_proses_fungsi_id' => $businessFunction->id,
                'user_id' => $user->id,
                'official_preparer_id' => $user->id,
                'nama_dokumen' => 'Draft Nomor Sama '.($index + 1),
                'nomor_dokumen' => 'PS-QA-010',
                'nomor_revisi' => 0,
            ]);

            $draft->departments()->sync([$department->id]);
            Storage::disk('local')->put("documents/{$draft->id}/template.pdf", 'PDF draft content');
            $draft->files()->create([
                'type_file' => 'filled_template',
                'path_file' => "documents/{$draft->id}/template.pdf",
                'original_file_name' => 'template.pdf',
                'stored_file_name' => 'template.pdf',
                'file_size' => 1024,
                'uploaded_by' => $user->id,
            ]);

            return $draft;
        });

        $this->assertSame(2, Document::query()
            ->where('nomor_dokumen', 'PS-QA-010')
            ->whereHas('status', fn ($query) => $query->where('nama_status', StatusDocument::DRAFT))
            ->count());

        $this->actingAs($thirdUser)
            ->post(route('documents.store', 'level-2'), $this->initialSubmitPayload($businessProcess, $businessFunction, $department, '010', [
                'official_preparer_id' => $thirdUser->id,
            ]))
            ->assertRedirect(route('documents.create'));

        $submitted = Document::query()
            ->where('nomor_dokumen', 'PS-QA-010')
            ->where('user_id', $thirdUser->id)
            ->whereHas('status', fn ($query) => $query->where('nama_status', StatusDocument::PROPOSED))
            ->firstOrFail();

        $this->assertNull($submitted->resubmitted_from);

        $blockedDraft = $drafts->first();

        $this->actingAs($firstUser)
            ->from(route('documents.create.drafts.edit', $blockedDraft))
            ->post(route('documents.store', 'level-2'), $this->initialSubmitPayload($businessProcess, $businessFunction, $department, '010', [
                'draft_id' => $blockedDraft->id,
                'filled_template' => null,
                'official_preparer_id' => $firstUser->id,
            ]))
            ->assertRedirect(route('documents.create.drafts.edit', $blockedDraft))
            ->assertSessionHasErrors(['nomor_dokumen_suffix']);

        $blockedDraft->refresh();

        $this->assertSame(StatusDocument::DRAFT, $blockedDraft->status->nama_status);
        $this->assertSame('PS-QA-010', $blockedDraft->nomor_dokumen);
    }

    public function test_document_number_suggestion_ignores_drafts(): void
    {
        [$user, $businessProcess, $businessFunction, $department, $level, $documentType] = $this->initialResubmissionFixture();
        $approvedStatus = StatusDocument::query()->where('nama_status', StatusDocument::APPROVED)->firstOrFail();
        $draftStatus = StatusDocument::query()->where('nama_status', StatusDocument::DRAFT)->firstOrFail();

        Document::create([
            'm_document_level_id' => $level->id,
            'm_status_document_id' => $approvedStatus->id,
            'm_document_types_id' => $documentType->id,
            'm_proses_bisnis_id' => $businessProcess->id,
            'm_proses_fungsi_id' => $businessFunction->id,
            'user_id' => $user->id,
            'official_preparer_id' => $user->id,
            'nama_dokumen' => 'Nomor Terakhir Submit',
            'nomor_dokumen' => 'PS-QA-009',
            'nomor_revisi' => 0,
            'approved_at' => now(),
        ]);
        Document::create([
            'm_document_level_id' => $level->id,
            'm_status_document_id' => $draftStatus->id,
            'm_document_types_id' => $documentType->id,
            'm_proses_bisnis_id' => $businessProcess->id,
            'm_proses_fungsi_id' => $businessFunction->id,
            'user_id' => User::factory()->create()->id,
            'official_preparer_id' => $user->id,
            'nama_dokumen' => 'Draft Tidak Mengunci Nomor',
            'nomor_dokumen' => 'PS-QA-010',
            'nomor_revisi' => 0,
        ]);

        $this->actingAs($user)
            ->get(route('documents.create.level', 'level-2'))
            ->assertOk()
            ->assertSee('"'.$businessFunction->id.'":"10"', false)
            ->assertDontSee('"'.$businessFunction->id.'":"11"', false);
    }

    public function test_autosave_updates_same_draft_when_document_number_changes(): void
    {
        [$user, $businessProcess, $businessFunction, $department, $level, $documentType] = $this->initialResubmissionFixture();
        $draft = Document::create([
            'm_document_level_id' => $level->id,
            'm_status_document_id' => StatusDocument::query()->where('nama_status', StatusDocument::DRAFT)->firstOrFail()->id,
            'm_document_types_id' => $documentType->id,
            'm_proses_bisnis_id' => $businessProcess->id,
            'm_proses_fungsi_id' => $businessFunction->id,
            'user_id' => $user->id,
            'official_preparer_id' => $user->id,
            'nama_dokumen' => 'Draft Nomor Lama',
            'nomor_dokumen' => 'PS-QA-010',
            'nomor_revisi' => 0,
        ]);

        $this->actingAs($user)
            ->postJson(route('documents.autosave', 'level-2'), [
                'draft_id' => $draft->id,
                'nama_dokumen' => 'Draft Nomor Baru',
                'm_proses_bisnis_id' => $businessProcess->id,
                'm_proses_fungsi_id' => $businessFunction->id,
                'department_ids' => [$department->id],
                'official_preparer_id' => $user->id,
                'nomor_dokumen_suffix' => '7',
                'nomor_revisi' => '00.00',
            ])
            ->assertOk()
            ->assertJson([
                'saved' => true,
                'draft_id' => $draft->id,
            ]);

        $draft->refresh();

        $this->assertSame('Draft Nomor Baru', $draft->nama_dokumen);
        $this->assertSame('PS-QA-07', $draft->nomor_dokumen);
        $this->assertSame(1, Document::query()
            ->where('user_id', $user->id)
            ->whereHas('status', fn ($query) => $query->where('nama_status', StatusDocument::DRAFT))
            ->count());
    }

    public function test_active_document_number_duplicate_is_blocked(): void
    {
        Storage::fake('local');

        [$user, $businessProcess, $businessFunction, $department, $level, $documentType] = $this->initialResubmissionFixture();
        $proposedStatus = StatusDocument::query()->where('nama_status', StatusDocument::PROPOSED)->firstOrFail();

        Document::create([
            'm_document_level_id' => $level->id,
            'm_status_document_id' => $proposedStatus->id,
            'm_document_types_id' => $documentType->id,
            'm_proses_bisnis_id' => $businessProcess->id,
            'm_proses_fungsi_id' => $businessFunction->id,
            'user_id' => $user->id,
            'official_preparer_id' => $user->id,
            'nama_dokumen' => 'Prosedur Aktif',
            'nomor_dokumen' => 'PS-QA-005',
            'submitted_at' => now(),
        ]);

        $this->actingAs($user)
            ->from(route('documents.create.level', 'level-2'))
            ->post(route('documents.store', 'level-2'), $this->initialSubmitPayload($businessProcess, $businessFunction, $department, '005'))
            ->assertRedirect(route('documents.create.level', 'level-2'))
            ->assertSessionHasErrors(['nomor_dokumen_suffix']);
    }

    public function test_master_document_number_duplicate_is_blocked_even_if_rejected_attempt_exists(): void
    {
        Storage::fake('local');

        [$user, $businessProcess, $businessFunction, $department, $level, $documentType] = $this->initialResubmissionFixture();
        $approvedStatus = StatusDocument::query()->where('nama_status', StatusDocument::APPROVED)->firstOrFail();

        Document::create([
            'm_document_level_id' => $level->id,
            'm_status_document_id' => $approvedStatus->id,
            'm_document_types_id' => $documentType->id,
            'm_proses_bisnis_id' => $businessProcess->id,
            'm_proses_fungsi_id' => $businessFunction->id,
            'user_id' => $user->id,
            'official_preparer_id' => $user->id,
            'nama_dokumen' => 'Prosedur Master',
            'nomor_dokumen' => 'PS-QA-006',
            'approved_at' => now(),
        ]);
        $this->createRejectedInitialAttempt($user, $level, $documentType, $businessProcess, $businessFunction, 'PS-QA-006');

        $this->actingAs($user)
            ->from(route('documents.create.level', 'level-2'))
            ->post(route('documents.store', 'level-2'), $this->initialSubmitPayload($businessProcess, $businessFunction, $department, '006'))
            ->assertRedirect(route('documents.create.level', 'level-2'))
            ->assertSessionHasErrors(['nomor_dokumen_suffix']);
    }

    public function test_rejection_history_does_not_mix_same_number_outside_resubmission_chain(): void
    {
        [$user, $businessProcess, $businessFunction, $department, $level, $documentType] = $this->initialResubmissionFixture();
        $unrelated = $this->createRejectedInitialAttempt($user, $level, $documentType, $businessProcess, $businessFunction, 'PS-SMR-007', [
            'nama_dokumen' => 'Unrelated',
        ], 'Catatan tidak boleh ikut');
        $chainAttempt = $this->createRejectedInitialAttempt($user, $level, $documentType, $businessProcess, $businessFunction, 'PS-SMR-007', [
            'nama_dokumen' => 'Chain',
        ], 'Catatan chain');
        $active = Document::create([
            'm_document_level_id' => $level->id,
            'm_status_document_id' => StatusDocument::query()->where('nama_status', StatusDocument::PROPOSED)->firstOrFail()->id,
            'm_document_types_id' => $documentType->id,
            'm_proses_bisnis_id' => $businessProcess->id,
            'm_proses_fungsi_id' => $businessFunction->id,
            'user_id' => $user->id,
            'official_preparer_id' => $user->id,
            'nama_dokumen' => 'Active',
            'nomor_dokumen' => 'PS-SMR-007',
            'resubmitted_from' => $chainAttempt->id,
        ]);

        $history = app(DocumentRejectionHistory::class)->forDocument($active);

        $this->assertSame(['Catatan chain'], $history->pluck('catatan')->all());
        $this->assertNotContains($unrelated->id, $history->pluck('document_id')->all());
    }

    public function test_template_upload_must_be_pdf_document(): void
    {
        Storage::fake('local');

        $user = User::factory()->create();
        $businessProcess = BusinessProcess::create([
            'kode' => 'SMR',
            'nama_proses_bisnis' => 'Sistem Manajemen Risiko',
        ]);
        $businessFunction = BusinessFunction::create([
            'kode' => 'OPS',
            'nama_proses_fungsi' => 'Operasional',
        ]);
        $department = Department::create([
            'kode_department' => 'QA',
            'nama_department' => 'Quality Assurance',
        ]);

        StatusDocument::create(['nama_status' => StatusDocument::DRAFT]);
        StatusDocument::create(['nama_status' => StatusDocument::PROPOSED]);
        DocumentType::create(['nama_types' => 'IK']);

        $this->actingAs($user)
            ->from(route('documents.create.level', 'level-3'))
            ->post(route('documents.store', 'level-3'), [
                'nama_dokumen' => 'Instruksi Kerja Pengujian',
                'm_proses_bisnis_id' => $businessProcess->id,
                'm_proses_fungsi_id' => $businessFunction->id,
                'department_ids' => [$department->id],
                'official_preparer_id' => $user->id,
                'nomor_dokumen_suffix' => '001',
                'filled_template' => UploadedFile::fake()->create('template.docx', 24),
                'submit_action' => 'draft',
            ])
            ->assertRedirect(route('documents.create.level', 'level-3'))
            ->assertSessionHasErrors(['filled_template']);
    }

    public function test_filled_template_word_upload_is_stored_with_document(): void
    {
        Storage::fake('local');

        $user = User::factory()->create();
        $businessProcess = BusinessProcess::create([
            'kode' => 'SMR',
            'nama_proses_bisnis' => 'Sistem Manajemen Risiko',
        ]);
        $businessFunction = BusinessFunction::create([
            'kode' => 'OPS',
            'nama_proses_fungsi' => 'Operasional',
        ]);
        $department = Department::create([
            'kode_department' => 'QA',
            'nama_department' => 'Quality Assurance',
        ]);

        StatusDocument::create(['nama_status' => StatusDocument::DRAFT]);
        StatusDocument::create(['nama_status' => StatusDocument::PROPOSED]);
        DocumentType::create(['nama_types' => 'IK']);

        $this->actingAs($user)
            ->post(route('documents.store', 'level-3'), [
                'nama_dokumen' => 'Instruksi Kerja Pengujian',
                'm_proses_bisnis_id' => $businessProcess->id,
                'm_proses_fungsi_id' => $businessFunction->id,
                'department_ids' => [$department->id],
                'official_preparer_id' => $user->id,
                'nomor_dokumen_suffix' => '001',
                'filled_template' => UploadedFile::fake()->create('template.pdf', 24, 'application/pdf'),
                'filled_template_word' => UploadedFile::fake()->create('template.docx', 24, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'),
                'submit_action' => 'draft',
            ])
            ->assertRedirect(route('documents.create.drafts'));

        $this->assertDatabaseHas('t_document_files', [
            'type_file' => 'filled_template',
            'original_file_name' => 'template.pdf',
        ]);
        $this->assertDatabaseHas('t_document_files', [
            'type_file' => 'filled_template_word',
            'original_file_name' => 'template.docx',
        ]);
    }

    public function test_initial_document_submit_requires_pdf_and_word_template_files(): void
    {
        Storage::fake('local');

        [$user, $businessProcess, $businessFunction, $department] = $this->initialResubmissionFixture();

        $this->actingAs($user)
            ->from(route('documents.create.level', 'level-2'))
            ->post(route('documents.store', 'level-2'), $this->initialSubmitPayload($businessProcess, $businessFunction, $department, '001', [
                'filled_template_word' => null,
            ]))
            ->assertRedirect(route('documents.create.level', 'level-2'))
            ->assertSessionHasErrors(['filled_template_word']);
    }

    public function test_revision_word_uploads_are_stored_with_revision_document(): void
    {
        Storage::fake('local');

        [$source, $submitter, $officialPreparer] = $this->revisionCreationFixture();

        $this->actingAs($submitter)
            ->post(route('documents.store', 'level-4'), $this->revisionSubmitPayload($source, $officialPreparer, [
                'revision_content_word' => UploadedFile::fake()->create('dokumen-revisi.docx', 24, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'),
                'revision_form_word' => UploadedFile::fake()->create('lembar-revisi.docx', 24, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'),
            ]))
            ->assertRedirect(route('documents.create'));

        $revision = Document::query()
            ->where('nama_dokumen', 'Prosedur Revisi Baru')
            ->firstOrFail();

        $this->assertDatabaseHas('t_document_files', [
            't_document_id' => $revision->id,
            'type_file' => 'revision_content_word',
            'original_file_name' => 'dokumen-revisi.docx',
        ]);
        $this->assertDatabaseHas('t_document_files', [
            't_document_id' => $revision->id,
            'type_file' => 'revision_form_word',
            'original_file_name' => 'lembar-revisi.docx',
        ]);
    }

    public function test_revision_submit_requires_pdf_and_word_for_revision_sections(): void
    {
        Storage::fake('local');

        [$source, $submitter, $officialPreparer] = $this->revisionCreationFixture();

        $this->actingAs($submitter)
            ->from(route('documents.create.level', ['level-4', 'revised_from' => $source->id]))
            ->post(route('documents.store', 'level-4'), $this->revisionSubmitPayload($source, $officialPreparer, [
                'revision_content_word' => null,
                'revision_form_word' => null,
            ]))
            ->assertRedirect(route('documents.create.level', ['level-4', 'revised_from' => $source->id]))
            ->assertSessionHasErrors(['revision_content_word', 'revision_form_word']);
    }

    public function test_attachments_must_be_pdf_documents(): void
    {
        Storage::fake('local');

        $user = User::factory()->create();
        $businessProcess = BusinessProcess::create([
            'kode' => 'SMR',
            'nama_proses_bisnis' => 'Sistem Manajemen Risiko',
        ]);
        $businessFunction = BusinessFunction::create([
            'kode' => 'OPS',
            'nama_proses_fungsi' => 'Operasional',
        ]);
        $department = Department::create([
            'kode_department' => 'QA',
            'nama_department' => 'Quality Assurance',
        ]);

        StatusDocument::create(['nama_status' => StatusDocument::DRAFT]);
        StatusDocument::create(['nama_status' => StatusDocument::PROPOSED]);
        DocumentType::create(['nama_types' => 'IK']);

        $this->actingAs($user)
            ->from(route('documents.create.level', 'level-3'))
            ->post(route('documents.store', 'level-3'), [
                'nama_dokumen' => 'Instruksi Kerja Pengujian',
                'm_proses_bisnis_id' => $businessProcess->id,
                'm_proses_fungsi_id' => $businessFunction->id,
                'department_ids' => [$department->id],
                'official_preparer_id' => $user->id,
                'nomor_dokumen_suffix' => '001',
                'filled_template' => UploadedFile::fake()->create('template.pdf', 24, 'application/pdf'),
                'attachments' => [
                    UploadedFile::fake()->create('lampiran.xlsx', 24),
                ],
                'submit_action' => 'draft',
            ])
            ->assertRedirect(route('documents.create.level', 'level-3'))
            ->assertSessionHasErrors(['attachments.0']);
    }

    public function test_attachment_title_is_stored_with_uploaded_attachment(): void
    {
        Storage::fake('local');

        $user = User::factory()->create();
        $businessProcess = BusinessProcess::create([
            'kode' => 'SMR',
            'nama_proses_bisnis' => 'Sistem Manajemen Risiko',
        ]);
        $businessFunction = BusinessFunction::create([
            'kode' => 'OPS',
            'nama_proses_fungsi' => 'Operasional',
        ]);
        $department = Department::create([
            'kode_department' => 'QA',
            'nama_department' => 'Quality Assurance',
        ]);

        StatusDocument::create(['nama_status' => StatusDocument::DRAFT]);
        StatusDocument::create(['nama_status' => StatusDocument::PROPOSED]);
        DocumentType::create(['nama_types' => 'IK']);

        $this->actingAs($user)
            ->post(route('documents.store', 'level-3'), [
                'nama_dokumen' => 'Instruksi Kerja Pengujian',
                'm_proses_bisnis_id' => $businessProcess->id,
                'm_proses_fungsi_id' => $businessFunction->id,
                'department_ids' => [$department->id],
                'official_preparer_id' => $user->id,
                'nomor_dokumen_suffix' => '001',
                'filled_template' => UploadedFile::fake()->create('template.pdf', 24, 'application/pdf'),
                'attachment_titles' => ['Catatan Brainstorming', 'Matriks Komunikasi'],
                'attachment_orders' => [1, 2],
                'attachments' => [
                    UploadedFile::fake()->create('lampiran.pdf', 24, 'application/pdf'),
                    UploadedFile::fake()->create('matriks.pdf', 24, 'application/pdf'),
                ],
                'submit_action' => 'draft',
            ])
            ->assertRedirect(route('documents.create.drafts'));

        $this->assertDatabaseHas('t_document_files', [
            'type_file' => 'attachment',
            'attachment_title' => 'Catatan Brainstorming',
            'attachment_order' => 1,
            'original_file_name' => 'lampiran.pdf',
        ]);
        $this->assertDatabaseHas('t_document_files', [
            'type_file' => 'attachment',
            'attachment_title' => 'Matriks Komunikasi',
            'attachment_order' => 2,
            'original_file_name' => 'matriks.pdf',
        ]);
    }

    public function test_matching_attachment_upload_is_not_stored_twice(): void
    {
        Storage::fake('local');

        $user = User::factory()->create();
        $businessProcess = BusinessProcess::create([
            'kode' => 'SMR',
            'nama_proses_bisnis' => 'Sistem Manajemen Risiko',
        ]);
        $businessFunction = BusinessFunction::create([
            'kode' => 'OPS',
            'nama_proses_fungsi' => 'Operasional',
        ]);
        $department = Department::create([
            'kode_department' => 'QA',
            'nama_department' => 'Quality Assurance',
        ]);

        StatusDocument::create(['nama_status' => StatusDocument::DRAFT]);
        StatusDocument::create(['nama_status' => StatusDocument::PROPOSED]);
        DocumentType::create(['nama_types' => 'IK']);

        $payload = [
            'nama_dokumen' => 'Instruksi Kerja Pengujian',
            'm_proses_bisnis_id' => $businessProcess->id,
            'm_proses_fungsi_id' => $businessFunction->id,
            'department_ids' => [$department->id],
            'official_preparer_id' => $user->id,
            'nomor_dokumen_suffix' => '001',
            'filled_template' => UploadedFile::fake()->create('template.pdf', 24, 'application/pdf'),
            'attachment_titles' => ['Catatan Brainstorming'],
            'attachments' => [
                UploadedFile::fake()->create('lampiran.pdf', 24, 'application/pdf'),
            ],
            'submit_action' => 'draft',
        ];

        $this->actingAs($user)
            ->post(route('documents.store', 'level-3'), $payload)
            ->assertRedirect(route('documents.create.drafts'));

        $draft = Document::query()->firstOrFail();

        $this->actingAs($user)
            ->post(route('documents.store', 'level-3'), [
                ...$payload,
                'draft_id' => $draft->id,
                'filled_template' => UploadedFile::fake()->create('template.pdf', 24, 'application/pdf'),
                'attachments' => [
                    UploadedFile::fake()->create('lampiran.pdf', 24, 'application/pdf'),
                ],
            ])
            ->assertRedirect(route('documents.create.drafts'));

        $this->assertSame(
            1,
            $draft->files()->where('type_file', 'attachment')->count(),
        );
    }

    private function initialResubmissionFixture(): array
    {
        $user = User::factory()->create();
        $businessProcess = BusinessProcess::create([
            'kode' => 'SMR',
            'nama_proses_bisnis' => 'Sistem Manajemen Risiko',
        ]);
        $businessFunction = BusinessFunction::create([
            'kode' => 'QA',
            'nama_proses_fungsi' => 'Quality Assurance',
        ]);
        $department = Department::create([
            'kode_department' => 'QA',
            'nama_department' => 'Quality Assurance',
        ]);

        foreach ([StatusDocument::DRAFT, StatusDocument::PROPOSED, StatusDocument::APPROVED, StatusDocument::REJECTED] as $status) {
            StatusDocument::query()->firstOrCreate(['nama_status' => $status]);
        }

        foreach ([
            ApprovalStatus::PENDING => 'Menunggu',
            ApprovalStatus::APPROVED => 'Disetujui',
            ApprovalStatus::REJECTED => 'Ditolak',
            ApprovalStatus::TERMINATED => 'Dihentikan',
        ] as $code => $name) {
            ApprovalStatus::query()->firstOrCreate([
                'kode_status' => $code,
            ], [
                'nama_status' => $name,
            ]);
        }

        $documentType = DocumentType::query()->firstOrCreate(['nama_types' => 'Prosedur']);
        $level = DocumentLevel::query()->where('kode', 'level-2')->firstOrFail();

        return [$user, $businessProcess, $businessFunction, $department, $level, $documentType];
    }

    private function initialSubmitPayload(BusinessProcess $businessProcess, BusinessFunction $businessFunction, Department $department, string $suffix, array $overrides = []): array
    {
        return array_merge([
            'nama_dokumen' => 'Prosedur Resubmit',
            'm_proses_bisnis_id' => $businessProcess->id,
            'm_proses_fungsi_id' => $businessFunction->id,
            'department_ids' => [$department->id],
            'official_preparer_id' => User::query()->firstOrFail()->id,
            'nomor_dokumen_suffix' => $suffix,
            'filled_template' => UploadedFile::fake()->create('template.pdf', 24, 'application/pdf'),
            'filled_template_word' => UploadedFile::fake()->create('template.docx', 24, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'),
            'submit_action' => 'submit',
        ], $overrides);
    }

    private function createRejectedInitialAttempt(
        User $user,
        DocumentLevel $level,
        DocumentType $documentType,
        BusinessProcess $businessProcess,
        BusinessFunction $businessFunction,
        string $documentNumber,
        array $overrides = [],
        string $note = 'Dokumen belum sesuai.',
    ): Document {
        $document = Document::create(array_merge([
            'm_document_level_id' => $level->id,
            'm_status_document_id' => StatusDocument::query()->where('nama_status', StatusDocument::REJECTED)->firstOrFail()->id,
            'm_document_types_id' => $documentType->id,
            'm_proses_bisnis_id' => $businessProcess->id,
            'm_proses_fungsi_id' => $businessFunction->id,
            'user_id' => $user->id,
            'official_preparer_id' => $user->id,
            'nama_dokumen' => 'Prosedur Ditolak',
            'nomor_dokumen' => $documentNumber,
            'rejected_at' => now(),
        ], $overrides));

        Approval::create([
            't_document_id' => $document->id,
            'm_approval_status_id' => ApprovalStatus::findByCode(ApprovalStatus::REJECTED)->id,
            'user_id' => User::factory()->create()->id,
            'role_id' => null,
            'assigned_by' => $user->id,
            'assigned_at' => now()->subMinute(),
            'responded_at' => now(),
            'stages' => 'Approval Dokumen',
            'catatan' => $note,
        ]);

        return $document;
    }

    private function revisionCreationFixture(): array
    {
        $businessProcess = BusinessProcess::create([
            'kode' => 'SMR',
            'nama_proses_bisnis' => 'Sistem Manajemen Risiko',
        ]);
        $businessFunction = BusinessFunction::create([
            'kode' => 'OPS',
            'nama_proses_fungsi' => 'Operasional',
        ]);
        $department = Department::create([
            'kode_department' => 'QA',
            'nama_department' => 'Quality Assurance',
        ]);
        $submitter = User::factory()->create(['m_department_id' => $department->id]);
        $officialPreparer = User::factory()->create();
        $approvedStatus = StatusDocument::query()->firstOrCreate(['nama_status' => StatusDocument::APPROVED]);
        StatusDocument::query()->firstOrCreate(['nama_status' => StatusDocument::PROPOSED]);
        StatusDocument::query()->firstOrCreate(['nama_status' => StatusDocument::REJECTED]);
        StatusDocument::query()->firstOrCreate(['nama_status' => StatusDocument::CANCELLED]);
        StatusDocument::query()->firstOrCreate(['nama_status' => StatusDocument::OBSOLETE]);
        ApprovalStatus::query()->firstOrCreate([
            'kode_status' => ApprovalStatus::APPROVED,
        ], [
            'nama_status' => 'Disetujui',
        ]);
        DocumentType::query()->firstOrCreate(['nama_types' => 'Prosedur']);
        DocumentType::query()->firstOrCreate(['nama_types' => 'Form']);
        $level = DocumentLevel::query()->where('kode', 'level-2')->firstOrFail();

        $source = Document::create([
            'm_document_level_id' => $level->id,
            'm_status_document_id' => $approvedStatus->id,
            'm_document_types_id' => DocumentType::query()->where('nama_types', 'Prosedur')->firstOrFail()->id,
            'm_proses_bisnis_id' => $businessProcess->id,
            'm_proses_fungsi_id' => $businessFunction->id,
            'user_id' => $submitter->id,
            'official_preparer_id' => $submitter->id,
            'nama_dokumen' => 'Prosedur Revisi Master',
            'nomor_dokumen' => 'PS-SMR-010',
            'nomor_revisi' => 0,
            'approved_at' => now(),
        ]);
        $source->departments()->sync([$department->id]);

        return [$source, $submitter, $officialPreparer];
    }

    private function revisionSubmitPayload(Document $source, User $officialPreparer, array $overrides = []): array
    {
        return array_merge([
            'revised_from' => $source->id,
            'nama_dokumen' => 'Prosedur Revisi Baru',
            'm_proses_bisnis_id' => $source->m_proses_bisnis_id,
            'm_proses_fungsi_id' => $source->m_proses_fungsi_id,
            'department_ids' => $source->departments()->pluck('departments.id')->all(),
            'official_preparer_id' => $officialPreparer->id,
            'nomor_dokumen_suffix' => '999',
            'revision_content' => UploadedFile::fake()->create('dokumen-revisi.pdf', 24, 'application/pdf'),
            'revision_content_word' => UploadedFile::fake()->create('dokumen-revisi.docx', 24, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'),
            'revision_form' => UploadedFile::fake()->create('lembar-revisi.pdf', 24, 'application/pdf'),
            'revision_form_word' => UploadedFile::fake()->create('lembar-revisi.docx', 24, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'),
            'submit_action' => 'submit',
        ], $overrides);
    }
}
