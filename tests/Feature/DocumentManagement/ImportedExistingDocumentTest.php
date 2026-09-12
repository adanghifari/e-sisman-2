<?php

namespace Tests\Feature\DocumentManagement;

use App\Models\Approval;
use App\Models\ApprovalFlow;
use App\Models\ApprovalFlowStage;
use App\Models\ApprovalStatus;
use App\Models\BusinessFunction;
use App\Models\BusinessProcess;
use App\Models\Department;
use App\Models\Document;
use App\Models\DocumentFile;
use App\Models\DocumentLevel;
use App\Models\DocumentNumberingSetup;
use App\Models\DocumentNumberRegistry;
use App\Models\DocumentRelation;
use App\Models\DocumentType;
use App\Models\Permission;
use App\Models\Role;
use App\Models\StatusDocument;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ImportedExistingDocumentTest extends TestCase
{
    use RefreshDatabase;

    public function test_imported_existing_pages_render(): void
    {
        $user = User::factory()->create([
            'nik' => '000000',
            'email' => 'developer@example.com',
        ]);
        $document = $this->createImportedObsolete($user, [
            'nama_dokumen' => 'Legacy Render',
            'nomor_dokumen' => 'LEG-RENDER',
            'nomor_revisi' => '00.01',
        ]);

        $this->actingAs($user)
            ->get(route('documents.existing.imports.index'))
            ->assertOk()
            ->assertSee('Arsip Dokumen Existing')
            ->assertSee('Legacy Render');

        $this->actingAs($user)
            ->get(route('documents.obsolete.imports.create'))
            ->assertOk()
            ->assertSee('Import Dokumen Obsolete')
            ->assertSee('Dokumen Level I : Manual SKMBS')
            ->assertSee('Dokumen Level II : Prosedur SKMBS')
            ->assertSee('Dokumen Level III : Instruksi Kerja')
            ->assertSee('Ketentuan Dokumen Lama')
            ->assertSee(route('documents.obsolete.imports.create.level', 'level-2'), false)
            ->assertSee(route('documents.obsolete.imports.create.legacy'), false);

        $this->actingAs($user)
            ->get(route('documents.existing.imports.show', $document))
            ->assertOk()
            ->assertSee('Detail Arsip Dokumen Existing')
            ->assertSee('LEG-RENDER')
            ->assertSee('00.01');
    }

    public function test_imported_existing_obsolete_can_be_stored_with_nullable_modern_master_data(): void
    {
        Storage::fake('local');

        $user = $this->userWithPermissions([
            'documents.obsolete.imports.store',
        ]);

        $this->createImportedObsolete($user, [
            'nama_dokumen' => 'Legacy Duplicate Sebelumnya',
            'nomor_dokumen' => 'LEG-SAME-001',
        ]);

        $this->actingAs($user)
            ->post(route('documents.obsolete.imports.store'), [
                'obsolete_rule_type' => Document::LEGACY_RULE,
                'nama_dokumen' => 'Instruksi Legacy Obsolete',
                'nomor_dokumen' => 'LEG-SAME-001',
                'nomor_revisi' => 'Rev A',
                'tanggal_terbit' => '2020-01-10',
                'tanggal_obsolete' => '2024-04-05',
                'catatan' => 'Diarsipkan dari dokumen lama yang belum mengikuti struktur saat ini.',
                'obsolete_document' => UploadedFile::fake()->create('legacy-obsolete.pdf', 100, 'application/pdf'),
            ])
            ->assertRedirect();

        $document = Document::query()
            ->where('nama_dokumen', 'Instruksi Legacy Obsolete')
            ->firstOrFail();

        $this->assertSame(Document::LEGACY_RULE, $document->obsolete_rule_type);
        $this->assertNull($document->m_document_level_id);
        $this->assertNull($document->m_document_types_id);
        $this->assertNull($document->m_proses_bisnis_id);
        $this->assertNull($document->m_proses_fungsi_id);
        $this->assertSame('LEG-SAME-001', $document->nomor_dokumen);
        $this->assertSame('Rev A', $document->nomor_revisi);
        $this->assertSame('Diarsipkan dari dokumen lama yang belum mengikuti struktur saat ini.', $document->catatan);
        $this->assertSame(2, Document::query()->where('nomor_dokumen', 'LEG-SAME-001')->count());

        $file = $document->files()->firstOrFail();

        $this->assertSame(DocumentFile::TYPE_IMPORTED_DOCUMENT, $file->type_file);
        Storage::disk('local')->assertExists($file->path_file);
    }

    public function test_imported_existing_document_can_store_relations_to_imported_and_t_document(): void
    {
        Storage::fake('local');

        $user = $this->userWithPermissions([
            'documents.obsolete.imports.store',
        ]);
        $targetImported = $this->createImportedObsolete($user, [
            'nama_dokumen' => 'Legacy Target',
            'nomor_dokumen' => 'LEG-TARGET',
        ]);
        $targetDocument = $this->createExistingDocument($user);

        $this->actingAs($user)
            ->post(route('documents.obsolete.imports.store'), [
                'obsolete_rule_type' => Document::LEGACY_RULE,
                'nama_dokumen' => 'Legacy Source',
                'nomor_revisi' => '00.01',
                'obsolete_document' => UploadedFile::fake()->create('legacy-source.pdf', 100, 'application/pdf'),
                'relations' => [
                    [
                        'related_imported_existing_document_id' => $targetImported->id,
                        'relation_type' => DocumentRelation::SUPERSEDED_BY,
                        'keterangan' => 'Digantikan arsip legacy berikutnya.',
                    ],
                    [
                        'related_document_id' => $targetDocument->id,
                        'relation_type' => DocumentRelation::REFERENCES,
                    ],
                ],
            ])
            ->assertRedirect();

        $source = Document::query()
            ->where('nama_dokumen', 'Legacy Source')
            ->firstOrFail();

        $this->assertSame(2, $source->outgoingRelations()->count());
        $this->assertDatabaseHas('document_relations', [
            'source_document_id' => $source->id,
            'target_document_id' => $targetImported->id,
            'relation_type' => DocumentRelation::SUPERSEDED_BY,
        ]);
        $this->assertDatabaseHas('document_relations', [
            'source_document_id' => $source->id,
            'target_document_id' => $targetDocument->id,
            'relation_type' => DocumentRelation::REFERENCES,
        ]);
    }

    public function test_imported_existing_document_relations_are_limited_to_two_entries(): void
    {
        Storage::fake('local');

        $user = $this->userWithPermissions([
            'documents.obsolete.imports.store',
        ]);

        $targets = collect(range(1, 3))->map(fn () => $this->createExistingDocument($user));

        $this->actingAs($user)
            ->post(route('documents.obsolete.imports.store'), [
                'obsolete_rule_type' => Document::LEGACY_RULE,
                'nama_dokumen' => 'Legacy Source With Too Many Relations',
                'nomor_revisi' => '00.01',
                'obsolete_document' => UploadedFile::fake()->create('legacy-source.pdf', 100, 'application/pdf'),
                'relations' => $targets->map(fn (Document $target) => [
                    'related_document_id' => $target->id,
                    'relation_type' => DocumentRelation::REFERENCES,
                ])->all(),
            ])
            ->assertSessionHasErrors('relations');
    }

    public function test_imported_existing_master_can_be_stored_with_current_rule_and_claimed_number(): void
    {
        Storage::fake('local');

        [$user, $level, $documentType, $businessProcess, $businessFunction, $department] = $this->existingMasterFixture([
            'documents.master.imports.store-level',
            'documents.master.imports.create-level',
        ]);

        $this->actingAs($user)
            ->post(route('documents.master.imports.store.level', 'level-2'), [
                'm_document_level_id' => $level->id,
                'm_document_types_id' => $documentType->id,
                'm_proses_bisnis_id' => $businessProcess->id,
                'm_proses_fungsi_id' => $businessFunction->id,
                'department_ids' => [$department->id],
                'nama_dokumen' => 'Existing Master Sebelum Go Live',
                'nomor_dokumen' => 'PS-SMR-120',
                'nomor_revisi' => '00.00',
                'existing_document' => UploadedFile::fake()->create('existing-master.pdf', 100, 'application/pdf'),
            ])
            ->assertRedirect();

        $document = Document::query()
            ->where('nama_dokumen', 'Existing Master Sebelum Go Live')
            ->firstOrFail();

        $this->assertSame(Document::STATE_MASTER, $document->document_state);
        $this->assertSame(Document::CURRENT_RULE, $document->obsolete_rule_type);
        $this->assertTrue($document->departments()->whereKey($department->id)->exists());
        $this->assertDatabaseHas('document_number_registry', [
            'document_number' => 'PS-SMR-120',
            'source_type' => DocumentNumberRegistry::SOURCE_T_DOCUMENT,
            'source_id' => $document->id,
        ]);
    }

    public function test_imported_existing_master_revision_number_must_use_two_digit_dot_format(): void
    {
        Storage::fake('local');

        [$user, $level, $documentType, $businessProcess, $businessFunction, $department] = $this->existingMasterFixture([
            'documents.master.imports.store-level',
            'documents.master.imports.create-level',
        ]);

        $this->actingAs($user)
            ->from(route('documents.master.imports.create.level', 'level-2'))
            ->post(route('documents.master.imports.store.level', 'level-2'), [
                'm_document_level_id' => $level->id,
                'm_document_types_id' => $documentType->id,
                'm_proses_bisnis_id' => $businessProcess->id,
                'm_proses_fungsi_id' => $businessFunction->id,
                'department_ids' => [$department->id],
                'nama_dokumen' => 'Existing Master Format Revisi Salah',
                'nomor_dokumen' => 'PS-SMR-121',
                'nomor_revisi' => '00',
                'existing_document' => UploadedFile::fake()->create('existing-master.pdf', 100, 'application/pdf'),
            ])
            ->assertRedirect(route('documents.master.imports.create.level', 'level-2'))
            ->assertSessionHasErrors('nomor_revisi');

        $this->assertFalse(Document::query()->where('nama_dokumen', 'Existing Master Format Revisi Salah')->exists());
    }

    public function test_imported_existing_master_ignores_attachment_uploads(): void
    {
        Storage::fake('local');

        [$user, $level, $documentType, $businessProcess, $businessFunction, $department] = $this->existingMasterFixture([
            'documents.master.imports.store-level',
            'documents.master.imports.create-level',
        ]);

        $this->actingAs($user)
            ->post(route('documents.master.imports.store.level', 'level-2'), [
                'm_document_level_id' => $level->id,
                'm_document_types_id' => $documentType->id,
                'm_proses_bisnis_id' => $businessProcess->id,
                'm_proses_fungsi_id' => $businessFunction->id,
                'department_ids' => [$department->id],
                'nama_dokumen' => 'Existing Master Tanpa Lampiran',
                'nomor_dokumen' => 'PS-SMR-122',
                'nomor_revisi' => '00.00',
                'existing_document' => UploadedFile::fake()->create('existing-master.pdf', 100, 'application/pdf'),
                'attachments' => [
                    UploadedFile::fake()->create('lampiran-master.pdf', 100, 'application/pdf'),
                ],
            ])
            ->assertRedirect();

        $document = Document::query()
            ->where('nama_dokumen', 'Existing Master Tanpa Lampiran')
            ->firstOrFail();

        $this->assertSame(1, $document->files()->count());
        $this->assertTrue($document->files()->where('type_file', DocumentFile::TYPE_IMPORTED_DOCUMENT)->exists());
        $this->assertFalse($document->files()->where('type_file', DocumentFile::TYPE_ATTACHMENT)->exists());
    }

    public function test_imported_existing_obsolete_current_rule_can_be_stored_per_level(): void
    {
        Storage::fake('local');

        [$user, $level, $documentType, $businessProcess, $businessFunction, $department] = $this->existingMasterFixture([
            'documents.obsolete.imports.create',
            'documents.obsolete.imports.create-level',
            'documents.obsolete.imports.store-level',
        ]);
        $this->createExistingDocument($user);
        $replacementDocument = $this->createImportedMaster($user, [
            'm_document_level_id' => $level->id,
            'm_document_types_id' => $documentType->id,
            'm_proses_bisnis_id' => $businessProcess->id,
            'm_proses_fungsi_id' => $businessFunction->id,
            'nama_dokumen' => 'Imported Master Versi 00.01',
            'nomor_dokumen' => 'PS-SMR-OBSOLETE-001',
            'nomor_revisi' => '00.01',
        ]);
        $this->createImportedObsolete($user, [
            'obsolete_rule_type' => 'current_rule',
            'm_document_level_id' => $level->id,
            'm_document_types_id' => $documentType->id,
            'm_proses_bisnis_id' => $businessProcess->id,
            'm_proses_fungsi_id' => $businessFunction->id,
            'nama_dokumen' => 'Imported Obsolete Versi 00.01',
            'nomor_dokumen' => 'PS-SMR-OBSOLETE-OLD',
            'nomor_revisi' => '00.00',
        ]);
        $wrongLevel = DocumentLevel::query()->where('kode', 'level-1')->firstOrFail();
        $this->createImportedMaster($user, [
            'm_document_level_id' => $wrongLevel->id,
            'm_document_types_id' => $documentType->id,
            'm_proses_bisnis_id' => $businessProcess->id,
            'm_proses_fungsi_id' => $businessFunction->id,
            'nama_dokumen' => 'Imported Master Salah Level',
            'nomor_dokumen' => 'MS-SMR-WRONG-LEVEL',
            'nomor_revisi' => '00.00',
        ]);

        $this->actingAs($user)
            ->get(route('documents.obsolete.imports.create.level', 'level-2'))
            ->assertOk()
            ->assertSee('Dokumen Pengganti')
            ->assertSee('Digantikan Oleh')
            ->assertSee('PS-SMR-OBSOLETE-001 - Imported Master Versi 00.01')
            ->assertSee('Imported Master - Revisi 00.01')
            ->assertDontSee('PS-SMR-OBSOLETE-OLD')
            ->assertDontSee('MS-SMR-WRONG-LEVEL')
            ->assertDontSee('Tambah Relasi');

        $this->actingAs($user)
            ->post(route('documents.obsolete.imports.store.level', 'level-2'), [
                'm_document_level_id' => $level->id,
                'm_document_types_id' => $documentType->id,
                'm_proses_bisnis_id' => $businessProcess->id,
                'm_proses_fungsi_id' => $businessFunction->id,
                'department_ids' => [$department->id],
                'nama_dokumen' => 'Existing Obsolete Sesuai Ketentuan',
                'nomor_dokumen' => 'PS-SMR-OBSOLETE-120',
                'nomor_revisi' => '00.00',
                'tanggal_obsolete' => '2026-08-28',
                'obsolete_document' => UploadedFile::fake()->create('existing-obsolete.pdf', 100, 'application/pdf'),
                'replacement_reference' => 'imported-'.$replacementDocument->id,
            ])
            ->assertRedirect();

        $document = Document::query()
            ->where('nama_dokumen', 'Existing Obsolete Sesuai Ketentuan')
            ->firstOrFail();

        $this->assertSame(Document::STATE_OBSOLETE, $document->document_state);
        $this->assertSame(Document::CURRENT_RULE, $document->obsolete_rule_type);
        $this->assertSame($level->id, $document->m_document_level_id);
        $this->assertSame($documentType->id, $document->m_document_types_id);
        $this->assertTrue($document->departments()->whereKey($department->id)->exists());
        $this->assertSame('2026-08-28', $document->tanggal_obsolete?->toDateString());
        $this->assertSame(DocumentFile::TYPE_IMPORTED_DOCUMENT, $document->files()->firstOrFail()->type_file);
        $this->assertTrue($document->outgoingRelations()
            ->where('target_document_id', $replacementDocument->id)
            ->where('relation_type', DocumentRelation::SUPERSEDED_BY)
            ->exists());
    }

    public function test_numbering_setup_blocks_v2_document_number_inside_reserved_range(): void
    {
        Storage::fake('local');

        [$user, $level, $documentType, $businessProcess, $businessFunction, $department] = $this->existingMasterFixture([
            'documents.create.create',
        ]);
        StatusDocument::query()->firstOrCreate(['nama_status' => StatusDocument::DRAFT]);
        StatusDocument::query()->firstOrCreate(['nama_status' => StatusDocument::PROPOSED]);
        ApprovalStatus::query()->firstOrCreate(['kode_status' => ApprovalStatus::APPROVED], ['nama_status' => 'Disetujui']);

        DocumentNumberingSetup::create([
            'scope_identifier' => 'PS-OPS',
            'existing_start_number' => 1,
            'existing_end_number' => 127,
            'v2_start_number' => 128,
            'configured_by' => $user->id,
            'configured_at' => now(),
        ]);

        $this->actingAs($user)
            ->from(route('documents.create.level', 'level-2'))
            ->post(route('documents.store', 'level-2'), [
                'nama_dokumen' => 'Prosedur Nomor Reserved',
                'm_document_level_id' => $level->id,
                'm_document_types_id' => $documentType->id,
                'm_proses_bisnis_id' => $businessProcess->id,
                'm_proses_fungsi_id' => $businessFunction->id,
                'department_ids' => [$department->id],
                'official_preparer_id' => $user->id,
                'nomor_dokumen_suffix' => '001',
                'filled_template' => UploadedFile::fake()->create('template.pdf', 100, 'application/pdf'),
                'filled_template_word' => UploadedFile::fake()->create('template.docx', 24, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'),
                'submit_action' => 'submit',
            ])
            ->assertRedirect(route('documents.create.level', 'level-2'))
            ->assertSessionHasErrors('nomor_dokumen_suffix');

        $this->assertFalse(Document::query()->where('nomor_dokumen', 'PS-SMR-001')->exists());
    }

    public function test_late_import_conflicting_with_v2_number_is_rejected_without_auto_renumber(): void
    {
        Storage::fake('local');

        [$user, $level, $documentType, $businessProcess, $businessFunction, $department] = $this->existingMasterFixture([
            'documents.master.imports.store-level',
            'documents.master.imports.create-level',
        ]);
        $status = StatusDocument::query()->firstOrCreate(['nama_status' => StatusDocument::APPROVED]);

        Document::create([
            'm_document_level_id' => $level->id,
            'm_status_document_id' => $status->id,
            'm_document_types_id' => $documentType->id,
            'm_proses_bisnis_id' => $businessProcess->id,
            'm_proses_fungsi_id' => $businessFunction->id,
            'user_id' => $user->id,
            'nama_dokumen' => 'V2 Sudah Pakai Nomor',
            'nomor_dokumen' => 'PS-SMR-128',
            'nomor_revisi' => 0,
            'approved_at' => now(),
        ]);

        $this->actingAs($user)
            ->from(route('documents.master.imports.create.level', 'level-2'))
            ->post(route('documents.master.imports.store.level', 'level-2'), [
                'm_document_level_id' => $level->id,
                'm_document_types_id' => $documentType->id,
                'm_proses_bisnis_id' => $businessProcess->id,
                'm_proses_fungsi_id' => $businessFunction->id,
                'department_ids' => [$department->id],
                'nama_dokumen' => 'Late Import Bentrok',
                'nomor_dokumen' => 'PS-SMR-128',
                'nomor_revisi' => '00.00',
                'existing_document' => UploadedFile::fake()->create('late-import.pdf', 100, 'application/pdf'),
            ])
            ->assertRedirect(route('documents.master.imports.create.level', 'level-2'))
            ->assertSessionHasErrors('nomor_dokumen');

        $this->assertFalse(Document::query()->where('nama_dokumen', 'Late Import Bentrok')->exists());
    }

    public function test_imported_existing_master_revision_bridge_creates_t_document_and_obsoletes_source_after_approval(): void
    {
        Storage::fake('local');

        [$user, $level, $documentType, $businessProcess, $businessFunction, $department] = $this->existingMasterFixture([
            'documents.existing.imports.revision',
            'documents.approval.approve',
            'documents.approval.show',
            'documents.master.view',
        ]);
        $proposedStatus = StatusDocument::query()->firstOrCreate(['nama_status' => StatusDocument::PROPOSED]);
        $approvedStatus = ApprovalStatus::query()->firstOrCreate(['kode_status' => ApprovalStatus::APPROVED], ['nama_status' => 'Disetujui']);
        $pendingStatus = ApprovalStatus::query()->firstOrCreate(['kode_status' => ApprovalStatus::PENDING], ['nama_status' => 'Menunggu']);
        ApprovalStatus::query()->firstOrCreate(['kode_status' => ApprovalStatus::WAITING], ['nama_status' => 'Menunggu Giliran']);
        ApprovalStatus::query()->firstOrCreate(['kode_status' => ApprovalStatus::REJECTED], ['nama_status' => 'Ditolak']);
        ApprovalStatus::query()->firstOrCreate(['kode_status' => ApprovalStatus::TERMINATED], ['nama_status' => 'Dihentikan']);
        StatusDocument::query()->firstOrCreate(['nama_status' => StatusDocument::APPROVED]);
        StatusDocument::query()->firstOrCreate(['nama_status' => StatusDocument::OBSOLETE]);

        $source = $this->createImportedMaster($user, [
            'm_document_level_id' => $level->id,
            'm_document_types_id' => $documentType->id,
            'm_proses_bisnis_id' => $businessProcess->id,
            'm_proses_fungsi_id' => $businessFunction->id,
            'nama_dokumen' => 'Imported Master Source',
            'nomor_dokumen' => 'PS-SMR-120',
            'nomor_revisi' => '00.01',
        ]);
        $source->departments()->sync([$department->id]);
        $olderObsolete = $this->createImportedObsolete($user, [
            'nama_dokumen' => 'Imported Master Source Versi Lama',
            'nomor_dokumen' => 'PS-SMR-120-OLD',
            'nomor_revisi' => '00.00',
            'obsolete_at' => now()->subDay(),
        ]);
        DocumentRelation::create([
            'source_document_id' => $olderObsolete->id,
            'target_document_id' => $source->id,
            'relation_type' => DocumentRelation::SUPERSEDED_BY,
            'created_by' => $user->id,
        ]);
        DocumentNumberRegistry::create([
            'document_number' => 'PS-SMR-120',
            'scope_identifier' => 'PS-SMR',
            'source_type' => DocumentNumberRegistry::SOURCE_T_DOCUMENT,
            'source_id' => $source->id,
            'registered_by' => $user->id,
            'registered_at' => now(),
        ]);

        $response = $this->actingAs($user)
            ->post(route('documents.store', 'level-4'), [
                'imported_source' => $source->id,
                'submit_action' => 'submit',
                'nama_dokumen' => 'Imported Master Source Rev 1',
                'm_proses_bisnis_id' => $businessProcess->id,
                'm_proses_fungsi_id' => $businessFunction->id,
                'department_ids' => [$department->id],
                'official_preparer_id' => $user->id,
                'revision_content' => UploadedFile::fake()->create('revision-content.pdf', 100, 'application/pdf'),
                'revision_content_word' => UploadedFile::fake()->create('revision-content.docx', 24, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'),
                'revision_form' => UploadedFile::fake()->create('revision-form.pdf', 100, 'application/pdf'),
                'revision_form_word' => UploadedFile::fake()->create('revision-form.docx', 24, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'),
            ]);

        $response->assertRedirect();

        $revision = Document::query()
            ->where('revised_from', $source->id)
            ->firstOrFail();

        $this->assertDatabaseHas('document_number_registry', [
            'document_number' => 'PS-SMR-120',
            'source_type' => DocumentNumberRegistry::SOURCE_T_DOCUMENT,
            'source_id' => $revision->id,
        ]);

        $this->assertSame($proposedStatus->id, $revision->m_status_document_id);
        $this->assertSame($source->id, $revision->revised_from);
        $this->assertSame('revision', $revision->request_type);
        $this->assertSame(2, $revision->numeric_revision);
        $this->assertSame('00.02', $revision->formatted_revision);
        $this->assertTrue($revision->departments()->whereKey($department->id)->exists());

        $revision->approvals()->create([
            'm_approval_status_id' => $pendingStatus->id,
            'user_id' => $user->id,
            'role_id' => null,
            'assigned_by' => $user->id,
            'assigned_at' => now(),
            'stages' => 'Approval Imported Existing',
        ]);

        $approvalResponse = $this->actingAs($user)
            ->post(route('documents.approval.approve', $revision));

        $approvalResponse->assertRedirect(route('documents.approval.show', $revision));

        $revision->refresh();
        $source->refresh();

        $this->assertSame(StatusDocument::APPROVED, $revision->status->nama_status);
        $this->assertSame('PS-SMR-120', $revision->nomor_dokumen);
        $this->assertSame(Document::STATE_OBSOLETE, $source->document_state);
        $this->assertDatabaseHas('document_relations', [
            'source_document_id' => $source->id,
            'target_document_id' => $revision->id,
            'relation_type' => DocumentRelation::SUPERSEDED_BY,
        ]);
        $this->assertSame($approvedStatus->id, $revision->approvals()->first()->m_approval_status_id);

        $this->actingAs($user)
            ->get(route('documents.master'))
            ->assertOk()
            ->assertSee('Imported Master Source Rev 1')
            ->assertSee('PS-SMR-120-OLD')
            ->assertSee('Imported Master Source Versi Lama');
    }

    public function test_imported_existing_master_cannot_start_revision_when_revision_is_still_proposed(): void
    {
        Storage::fake('local');

        [$user, $level, $documentType, $businessProcess, $businessFunction, $department] = $this->existingMasterFixture([
            'documents.existing.imports.revision',
        ]);
        $source = $this->createImportedMaster($user, [
            'm_document_level_id' => $level->id,
            'm_document_types_id' => $documentType->id,
            'm_proses_bisnis_id' => $businessProcess->id,
            'm_proses_fungsi_id' => $businessFunction->id,
            'nama_dokumen' => 'Imported Master Revisi Aktif',
            'nomor_dokumen' => 'PS-SMR-ACTIVE-IMPORT',
            'nomor_revisi' => '00.00',
        ]);
        $source->departments()->sync([$department->id]);

        $proposedStatus = StatusDocument::query()->firstOrCreate(['nama_status' => StatusDocument::PROPOSED]);
        Document::create([
            'm_document_level_id' => $level->id,
            'm_status_document_id' => $proposedStatus->id,
            'm_document_types_id' => $documentType->id,
            'm_proses_bisnis_id' => $businessProcess->id,
            'm_proses_fungsi_id' => $businessFunction->id,
            'user_id' => $user->id,
            'official_preparer_id' => $user->id,
            'revised_from' => $source->id,
            'request_type' => 'revision',
            'nama_dokumen' => 'Imported Master Revisi Aktif Rev 1',
            'nomor_dokumen' => 'PS-SMR-ACTIVE-IMPORT',
            'nomor_lembar_revisi' => 'FMPS-SMR-ACTIVE-IMPORT-01',
            'nomor_revisi' => 1,
            'submitted_at' => now(),
            'created_at' => now(),
        ]);

        $this->actingAs($user)
            ->from(route('documents.create.level', ['level-4', 'imported_source' => $source->id]))
            ->post(route('documents.store', 'level-4'), [
                'imported_source' => $source->id,
                'submit_action' => 'submit',
                'nama_dokumen' => 'Imported Master Revisi Aktif Rev 2',
                'm_proses_bisnis_id' => $businessProcess->id,
                'm_proses_fungsi_id' => $businessFunction->id,
                'department_ids' => [$department->id],
                'official_preparer_id' => $user->id,
                'revision_content' => UploadedFile::fake()->create('revision-content.pdf', 100, 'application/pdf'),
                'revision_content_word' => UploadedFile::fake()->create('revision-content.docx', 24, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'),
                'revision_form' => UploadedFile::fake()->create('revision-form.pdf', 100, 'application/pdf'),
                'revision_form_word' => UploadedFile::fake()->create('revision-form.docx', 24, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'),
            ])
            ->assertRedirect(route('documents.create.level', ['level-4', 'imported_source' => $source->id]))
            ->assertSessionHasErrors(['imported_source']);

        $this->assertSame(1, Document::query()
            ->where('revised_from', $source->id)
            ->where('request_type', 'revision')
            ->count());
    }

    public function test_imported_existing_master_revision_can_be_saved_as_draft_edited_and_submitted(): void
    {
        Storage::fake('local');

        [$user, $level, $documentType, $businessProcess, $businessFunction, $department] = $this->existingMasterFixture([
            'documents.existing.imports.revision',
            'documents.create.drafts',
            'documents.create.drafts.edit',
        ]);
        $draftStatus = StatusDocument::query()->firstOrCreate(['nama_status' => StatusDocument::DRAFT]);
        $proposedStatus = StatusDocument::query()->firstOrCreate(['nama_status' => StatusDocument::PROPOSED]);
        ApprovalStatus::query()->firstOrCreate(['kode_status' => ApprovalStatus::APPROVED], ['nama_status' => 'Disetujui']);
        ApprovalStatus::query()->firstOrCreate(['kode_status' => ApprovalStatus::PENDING], ['nama_status' => 'Menunggu']);

        $source = $this->createImportedMaster($user, [
            'm_document_level_id' => $level->id,
            'm_document_types_id' => $documentType->id,
            'm_proses_bisnis_id' => $businessProcess->id,
            'm_proses_fungsi_id' => $businessFunction->id,
            'nama_dokumen' => 'Imported Master For Draft',
            'nomor_dokumen' => 'PS-SMR-DRAFT-01',
            'nomor_revisi' => '00.00',
        ]);
        $source->departments()->sync([$department->id]);

        // 1. Simpan Draft
        $this->actingAs($user)
            ->post(route('documents.store', 'level-4'), [
                'imported_source' => $source->id,
                'submit_action' => 'draft',
                'nama_dokumen' => 'Draft Revisi Imported Master',
                'm_proses_bisnis_id' => $businessProcess->id,
                'm_proses_fungsi_id' => $businessFunction->id,
                'department_ids' => [$department->id],
            ])
            ->assertRedirect(route('documents.create.drafts'));

        $draft = Document::query()
            ->where('revised_from', $source->id)
            ->firstOrFail();

        $this->assertSame($draftStatus->id, $draft->m_status_document_id);
        $this->assertSame('revision', $draft->request_type);
        $this->assertSame('PS-SMR-DRAFT-01', $draft->nomor_dokumen);
        $this->assertNull($draft->submitted_at);

        // 2. Akses halaman Draft Saya
        $this->actingAs($user)
            ->get(route('documents.create.drafts'))
            ->assertOk()
            ->assertSee('Draft Revisi Imported Master')
            ->assertSee('PS-SMR-DRAFT-01');

        // 3. Edit Draft
        $this->actingAs($user)
            ->get(route('documents.create.drafts.edit', $draft))
            ->assertOk()
            ->assertSee('Draft Revisi Imported Master')
            ->assertSee('PS-SMR-DRAFT-01');

        // 4. Submit dari Draft
        $this->actingAs($user)
            ->post(route('documents.store', 'level-4'), [
                'draft_id' => $draft->id,
                'imported_source' => $source->id,
                'submit_action' => 'submit',
                'nama_dokumen' => 'Revisi Final Imported Master',
                'm_proses_bisnis_id' => $businessProcess->id,
                'm_proses_fungsi_id' => $businessFunction->id,
                'department_ids' => [$department->id],
                'official_preparer_id' => $user->id,
                'revision_content' => UploadedFile::fake()->create('rev-content.pdf', 100, 'application/pdf'),
                'revision_content_word' => UploadedFile::fake()->create('rev-content.docx', 24, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'),
                'revision_form' => UploadedFile::fake()->create('rev-form.pdf', 100, 'application/pdf'),
                'revision_form_word' => UploadedFile::fake()->create('rev-form.docx', 24, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'),
            ])
            ->assertRedirect(route('documents.create'));

        $draft->refresh();
        $this->assertSame($proposedStatus->id, $draft->m_status_document_id);
        $this->assertSame('Revisi Final Imported Master', $draft->nama_dokumen);
        $this->assertNotNull($draft->submitted_at);
    }

    public function test_imported_existing_master_revision_page_renders_with_prefilled_metadata(): void
    {
        [$user, $level, $documentType, $businessProcess, $businessFunction, $department] = $this->existingMasterFixture([
            'documents.existing.imports.revision',
        ]);
        $source = $this->createImportedMaster($user, [
            'm_document_level_id' => $level->id,
            'm_document_types_id' => $documentType->id,
            'm_proses_bisnis_id' => $businessProcess->id,
            'm_proses_fungsi_id' => $businessFunction->id,
            'nama_dokumen' => 'Imported Master Page Test',
            'nomor_dokumen' => 'PS-SMR-PAGE-TEST',
            'nomor_revisi' => '00.01',
        ]);
        $source->departments()->sync([$department->id]);

        $this->actingAs($user)
            ->get(route('documents.create.level', ['level-4', 'imported_source' => $source->id]))
            ->assertOk()
            ->assertSee('PS-SMR-PAGE-TEST')
            ->assertSee('Imported Master Page Test')
            ->assertSee('name="imported_source"', false)
            ->assertSee('value="'.$source->id.'"', false);
    }

    public function test_rejected_imported_existing_master_revision_can_be_accessed_and_resubmitted_without_404(): void
    {
        Storage::fake('local');

        [$user, $level, $documentType, $businessProcess, $businessFunction, $department] = $this->existingMasterFixture([
            'documents.existing.imports.revision',
            'documents.create.create',
        ]);
        $proposedStatus = StatusDocument::query()->firstOrCreate(['nama_status' => StatusDocument::PROPOSED]);
        $rejectedStatus = StatusDocument::query()->firstOrCreate(['nama_status' => StatusDocument::REJECTED]);
        ApprovalStatus::query()->firstOrCreate(['kode_status' => ApprovalStatus::APPROVED], ['nama_status' => 'Disetujui']);
        ApprovalStatus::query()->firstOrCreate(['kode_status' => ApprovalStatus::PENDING], ['nama_status' => 'Menunggu']);
        ApprovalStatus::query()->firstOrCreate(['kode_status' => ApprovalStatus::REJECTED], ['nama_status' => 'Ditolak']);
        $formLevel = DocumentLevel::query()->where('kode', 'level-4')->firstOrFail();
        $formType = DocumentType::query()->where('nama_types', 'Form')->firstOrFail();

        $source = $this->createImportedMaster($user, [
            'm_document_level_id' => $level->id,
            'm_document_types_id' => $documentType->id,
            'm_proses_bisnis_id' => $businessProcess->id,
            'm_proses_fungsi_id' => $businessFunction->id,
            'nama_dokumen' => 'Imported Master Resubmit Test',
            'nomor_dokumen' => 'PS-SMR-RESUBMIT',
            'nomor_revisi' => '00.01',
        ]);
        $source->departments()->sync([$department->id]);

        $rejectedRevision = Document::create([
            'm_document_level_id' => $formLevel->id,
            'm_status_document_id' => $rejectedStatus->id,
            'm_document_types_id' => $formType->id,
            'm_proses_bisnis_id' => $businessProcess->id,
            'm_proses_fungsi_id' => $businessFunction->id,
            'user_id' => $user->id,
            'official_preparer_id' => $user->id,
            'revised_from' => $source->id,
            'request_type' => 'revision',
            'nama_dokumen' => 'Imported Master Revisi Ditolak',
            'nomor_dokumen' => 'PS-SMR-RESUBMIT',
            'nomor_lembar_revisi' => 'FMPS-SMR-RESUBMIT-02',
            'nomor_revisi' => 2,
            'rejected_at' => now(),
            'created_at' => now(),
        ]);
        $rejectedRevision->departments()->sync([$department->id]);

        $rejectedRevision->files()->create([
            'path_file' => 'documents/rev-content.pdf',
            'original_file_name' => 'rev-content.pdf',
            'stored_file_name' => 'rev-content.pdf',
            'file_size' => 1024,
            'type_file' => DocumentFile::TYPE_REVISION_CONTENT,
            'uploaded_by' => $user->id,
        ]);
        $rejectedRevision->files()->create([
            'path_file' => 'documents/rev-form.pdf',
            'original_file_name' => 'rev-form.pdf',
            'stored_file_name' => 'rev-form.pdf',
            'file_size' => 1024,
            'type_file' => DocumentFile::TYPE_REVISION_FORM,
            'uploaded_by' => $user->id,
        ]);

        // 1. Tombol Ajukan Ulang mengarah ke documents.rejected.resubmit
        $this->actingAs($user)
            ->get(route('documents.rejected.resubmit', $rejectedRevision))
            ->assertRedirect(route('documents.create.level', [
                'level' => 'level-4',
                'resubmitted_from' => $rejectedRevision->id,
            ]));

        // 2. Akses halaman create level-4 dengan resubmitted_from tidak boleh 404
        $this->actingAs($user)
            ->get(route('documents.create.level', [
                'level' => 'level-4',
                'resubmitted_from' => $rejectedRevision->id,
            ]))
            ->assertOk()
            ->assertSee('PS-SMR-RESUBMIT')
            ->assertSee('Imported Master Revisi Ditolak')
            ->assertSee('name="resubmitted_from"', false)
            ->assertSee('value="'.$rejectedRevision->id.'"', false);

        // 3. Submit pengajuan ulang revisi
        $response = $this->actingAs($user)
            ->post(route('documents.store', 'level-4'), [
                'resubmitted_from' => $rejectedRevision->id,
                'submit_action' => 'submit',
                'nama_dokumen' => 'Imported Master Revisi Diajukan Ulang',
                'm_proses_bisnis_id' => $businessProcess->id,
                'm_proses_fungsi_id' => $businessFunction->id,
                'department_ids' => [$department->id],
                'official_preparer_id' => $user->id,
                'revision_content' => UploadedFile::fake()->create('new-revision-content.pdf', 100, 'application/pdf'),
                'revision_content_word' => UploadedFile::fake()->create('new-revision-content.docx', 24, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'),
                'revision_form' => UploadedFile::fake()->create('new-revision-form.pdf', 100, 'application/pdf'),
                'revision_form_word' => UploadedFile::fake()->create('new-revision-form.docx', 24, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'),
            ]);

        $response->assertRedirect(route('documents.create'));

        $resubmittedDoc = Document::query()
            ->where('nama_dokumen', 'Imported Master Revisi Diajukan Ulang')
            ->firstOrFail();

        $this->assertSame($rejectedRevision->id, $resubmittedDoc->resubmitted_from);
        $this->assertSame($source->id, $resubmittedDoc->revised_from);
        $this->assertSame(StatusDocument::PROPOSED, $resubmittedDoc->status->nama_status);
        $this->assertSame(StatusDocument::REJECTED, $rejectedRevision->fresh()->status->nama_status);
        $this->assertSame(2, $resubmittedDoc->numeric_revision);
        $this->assertSame('00.02', $resubmittedDoc->formatted_revision);
        $this->assertSame('PS-SMR-RESUBMIT', $resubmittedDoc->nomor_dokumen);
        $this->assertSame('FMPS-SMR-RESUBMIT-02', $resubmittedDoc->nomor_lembar_revisi);
        $this->assertNull($resubmittedDoc->rejected_at);
    }

    public function test_current_rule_requires_all_modern_master_data(): void
    {
        Storage::fake('local');

        $user = $this->userWithPermissions([
            'documents.obsolete.imports.store',
        ]);

        $this->actingAs($user)
            ->from(route('documents.obsolete.imports.create'))
            ->post(route('documents.obsolete.imports.store'), [
                'obsolete_rule_type' => Document::CURRENT_RULE,
                'nama_dokumen' => 'Current Rule Tanpa Master Data',
                'obsolete_document' => UploadedFile::fake()->create('current-rule.pdf', 100, 'application/pdf'),
            ])
            ->assertRedirect(route('documents.obsolete.imports.create'))
            ->assertSessionHasErrors([
                'm_document_level_id',
                'm_document_types_id',
                'm_proses_bisnis_id',
                'm_proses_fungsi_id',
            ]);

        $this->assertFalse(Document::query()->where('nama_dokumen', 'Current Rule Tanpa Master Data')->exists());
    }

    public function test_imported_existing_relation_requires_exactly_one_target(): void
    {
        Storage::fake('local');

        $user = $this->userWithPermissions([
            'documents.obsolete.imports.store',
        ]);
        $targetImported = $this->createImportedObsolete($user, [
            'nama_dokumen' => 'Legacy Target',
        ]);
        $targetDocument = $this->createExistingDocument($user);

        $this->actingAs($user)
            ->from(route('documents.obsolete.imports.create'))
            ->post(route('documents.obsolete.imports.store'), [
                'obsolete_rule_type' => Document::LEGACY_RULE,
                'nama_dokumen' => 'Legacy Invalid No Target',
                'obsolete_document' => UploadedFile::fake()->create('legacy-no-target.pdf', 100, 'application/pdf'),
                'relations' => [
                    [
                        'relation_type' => DocumentRelation::REFERENCES,
                    ],
                ],
            ])
            ->assertRedirect(route('documents.obsolete.imports.create'))
            ->assertSessionHasErrors('relations.0.related_imported_existing_document_id');

        $this->actingAs($user)
            ->from(route('documents.obsolete.imports.create'))
            ->post(route('documents.obsolete.imports.store'), [
                'obsolete_rule_type' => Document::LEGACY_RULE,
                'nama_dokumen' => 'Legacy Invalid Two Targets',
                'obsolete_document' => UploadedFile::fake()->create('legacy-two-targets.pdf', 100, 'application/pdf'),
                'relations' => [
                    [
                        'related_imported_existing_document_id' => $targetImported->id,
                        'related_document_id' => $targetDocument->id,
                        'relation_type' => DocumentRelation::REFERENCES,
                    ],
                ],
            ])
            ->assertRedirect(route('documents.obsolete.imports.create'))
            ->assertSessionHasErrors('relations.0.related_imported_existing_document_id');

        $this->assertFalse(Document::query()->where('nama_dokumen', 'Legacy Invalid No Target')->exists());
        $this->assertFalse(Document::query()->where('nama_dokumen', 'Legacy Invalid Two Targets')->exists());
    }

    public function test_imported_existing_preview_only_accepts_pdf_files(): void
    {
        Storage::fake('local');

        $user = User::factory()->create([
            'nik' => '000000',
            'email' => 'developer@example.com',
        ]);
        $document = $this->createImportedObsolete($user, [
            'nama_dokumen' => 'Legacy Preview',
        ]);
        Storage::disk('local')->put('documents/imported-existing/preview.pdf', "%PDF-1.4\nfixture");
        Storage::disk('local')->put('documents/imported-existing/preview.docx', 'doc fixture');
        $pdfFile = $document->files()->create([
            'type_file' => DocumentFile::TYPE_IMPORTED_DOCUMENT,
            'path_file' => 'documents/imported-existing/preview.pdf',
            'uploaded_by' => $user->id,
            'original_file_name' => 'preview.pdf',
            'stored_file_name' => 'preview.pdf',
            'file_size' => 16,
        ]);
        $wordFile = $document->files()->create([
            'type_file' => DocumentFile::TYPE_ATTACHMENT,
            'path_file' => 'documents/imported-existing/preview.docx',
            'uploaded_by' => $user->id,
            'original_file_name' => 'preview.docx',
            'stored_file_name' => 'preview.docx',
            'file_size' => 11,
        ]);

        $this->actingAs($user)
            ->get(route('documents.existing.imports.files.preview', [$document, $pdfFile]))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        $this->actingAs($user)
            ->get(route('documents.existing.imports.files.preview', [$document, $wordFile]))
            ->assertStatus(415);
    }

    public function test_imported_existing_master_level_2_normalizes_single_digit_suffix_to_two_digits(): void
    {
        Storage::fake('local');

        [$user, $level, $documentType, $businessProcess, $businessFunction, $department] = $this->existingMasterFixture([
            'documents.master.imports.store-level',
            'documents.master.imports.create-level',
        ]);

        $this->actingAs($user)
            ->post(route('documents.master.imports.store.level', 'level-2'), [
                'm_document_level_id' => $level->id,
                'm_document_types_id' => $documentType->id,
                'm_proses_bisnis_id' => $businessProcess->id,
                'm_proses_fungsi_id' => $businessFunction->id,
                'department_ids' => [$department->id],
                'nama_dokumen' => 'Existing Master Level 2 Suffix Normalization',
                'nomor_dokumen_suffix' => '7',
                'nomor_revisi' => '00.00',
                'existing_document' => UploadedFile::fake()->create('existing-master.pdf', 100, 'application/pdf'),
            ])
            ->assertRedirect();

        $document = Document::query()
            ->where('nama_dokumen', 'Existing Master Level 2 Suffix Normalization')
            ->firstOrFail();

        $this->assertSame('PS-OPS-07', $document->nomor_dokumen);
        $this->assertDatabaseHas('document_number_registry', [
            'document_number' => 'PS-OPS-07',
            'source_type' => DocumentNumberRegistry::SOURCE_T_DOCUMENT,
            'source_id' => $document->id,
        ]);
    }

    public function test_imported_existing_master_level_3_constructs_number_from_procedure_reference_and_suffix(): void
    {
        Storage::fake('local');

        [$user, $level2, $documentType2, $businessProcess, $businessFunction, $department] = $this->existingMasterFixture([
            'documents.master.imports.store-level',
            'documents.master.imports.create-level',
        ]);

        $level3 = DocumentLevel::query()->where('kode', 'level-3')->firstOrFail();
        $documentType3 = DocumentType::query()->firstOrCreate(['nama_types' => 'Instruksi Kerja']);

        $procDoc = $this->createImportedMaster($user, [
            'm_document_level_id' => $level2->id,
            'm_document_types_id' => $documentType2->id,
            'm_proses_bisnis_id' => $businessProcess->id,
            'm_proses_fungsi_id' => $businessFunction->id,
            'nama_dokumen' => 'Parent Procedure Master',
            'nomor_dokumen' => 'PS-PMS-01',
            'nomor_revisi' => '00.00',
        ]);

        $this->actingAs($user)
            ->post(route('documents.master.imports.store.level', 'level-3'), [
                'm_document_level_id' => $level3->id,
                'm_document_types_id' => $documentType3->id,
                'm_proses_bisnis_id' => $businessProcess->id,
                'm_proses_fungsi_id' => $businessFunction->id,
                'reference' => "imported-{$procDoc->id}",
                'department_ids' => [$department->id],
                'nama_dokumen' => 'IK Master Dari Prosedur',
                'nomor_dokumen_suffix' => '7',
                'nomor_revisi' => '00.00',
                'existing_document' => UploadedFile::fake()->create('ik-master.pdf', 100, 'application/pdf'),
            ])
            ->assertRedirect();

        $document = Document::query()
            ->where('nama_dokumen', 'IK Master Dari Prosedur')
            ->firstOrFail();

        $this->assertSame('IK-PMS-01-07', $document->nomor_dokumen);
        $this->assertDatabaseHas('document_number_registry', [
            'document_number' => 'IK-PMS-01-07',
            'source_type' => DocumentNumberRegistry::SOURCE_T_DOCUMENT,
            'source_id' => $document->id,
        ]);
    }

    public function test_imported_existing_master_level_3_page_renders_document_number_segments(): void
    {
        [$user, $level2, $documentType2, $businessProcess, $businessFunction] = $this->existingMasterFixture([
            'documents.master.imports.create-level',
        ]);

        $this->createImportedMaster($user, [
            'm_document_level_id' => $level2->id,
            'm_document_types_id' => $documentType2->id,
            'm_proses_bisnis_id' => $businessProcess->id,
            'm_proses_fungsi_id' => $businessFunction->id,
            'nama_dokumen' => 'Imported Procedure',
            'nomor_dokumen' => 'PS-PMS-01',
            'nomor_revisi' => '00.00',
        ]);

        $approvedStatus = StatusDocument::query()->firstOrCreate(['nama_status' => StatusDocument::APPROVED]);
        Document::create([
            'origin' => Document::ORIGIN_WORKFLOW,
            'm_document_level_id' => $level2->id,
            'm_status_document_id' => $approvedStatus->id,
            'm_document_types_id' => $documentType2->id,
            'm_proses_bisnis_id' => $businessProcess->id,
            'm_proses_fungsi_id' => $businessFunction->id,
            'user_id' => $user->id,
            'official_preparer_id' => $user->id,
            'nama_dokumen' => 'Workflow Procedure',
            'nomor_dokumen' => 'PS-SMR-02',
            'nomor_revisi' => '00.00',
            'approved_at' => now(),
        ]);

        $this->actingAs($user)
            ->get(route('documents.master.imports.create.level', 'level-3'))
            ->assertOk()
            ->assertSee('IK')
            ->assertSee('data-document-number-segment="procedure-reference-0"', false)
            ->assertSee('data-document-number-segment="procedure-reference-1"', false)
            ->assertSee('name="nomor_dokumen_suffix"', false);
    }

    public function test_imported_existing_master_validates_invalid_suffix_characters(): void
    {
        Storage::fake('local');

        [$user, $level, $documentType, $businessProcess, $businessFunction, $department] = $this->existingMasterFixture([
            'documents.master.imports.store-level',
            'documents.master.imports.create-level',
        ]);

        $this->actingAs($user)
            ->from(route('documents.master.imports.create.level', 'level-2'))
            ->post(route('documents.master.imports.store.level', 'level-2'), [
                'm_document_level_id' => $level->id,
                'm_document_types_id' => $documentType->id,
                'm_proses_bisnis_id' => $businessProcess->id,
                'm_proses_fungsi_id' => $businessFunction->id,
                'department_ids' => [$department->id],
                'nama_dokumen' => 'Invalid Suffix Master',
                'nomor_dokumen_suffix' => '###',
                'nomor_revisi' => '00.00',
                'existing_document' => UploadedFile::fake()->create('existing-master.pdf', 100, 'application/pdf'),
            ])
            ->assertRedirect(route('documents.master.imports.create.level', 'level-2'))
            ->assertSessionHasErrors('nomor_dokumen_suffix');
    }

    public function test_imported_existing_master_rejected_when_number_exists_in_t_document(): void
    {
        Storage::fake('local');

        [$user, $level, $documentType, $businessProcess, $businessFunction, $department] = $this->existingMasterFixture([
            'documents.master.imports.store-level',
            'documents.master.imports.create-level',
        ]);

        $status = StatusDocument::query()->firstOrCreate(['nama_status' => StatusDocument::APPROVED]);
        Document::create([
            'origin' => Document::ORIGIN_WORKFLOW,
            'm_document_level_id' => $level->id,
            'm_status_document_id' => $status->id,
            'm_document_types_id' => $documentType->id,
            'm_proses_bisnis_id' => $businessProcess->id,
            'm_proses_fungsi_id' => $businessFunction->id,
            'user_id' => $user->id,
            'official_preparer_id' => $user->id,
            'nama_dokumen' => 'Existing V2 Document',
            'nomor_dokumen' => 'PS-OPS-01',
            'nomor_revisi' => '00.00',
            'approved_at' => now(),
        ]);

        $this->actingAs($user)
            ->from(route('documents.master.imports.create.level', 'level-2'))
            ->post(route('documents.master.imports.store.level', 'level-2'), [
                'm_document_level_id' => $level->id,
                'm_document_types_id' => $documentType->id,
                'm_proses_bisnis_id' => $businessProcess->id,
                'm_proses_fungsi_id' => $businessFunction->id,
                'department_ids' => [$department->id],
                'nama_dokumen' => 'Duplicate V2 Number Import',
                'nomor_dokumen_suffix' => '01',
                'nomor_revisi' => '00.00',
                'existing_document' => UploadedFile::fake()->create('existing-master.pdf', 100, 'application/pdf'),
            ])
            ->assertRedirect(route('documents.master.imports.create.level', 'level-2'))
            ->assertSessionHasErrors(['nomor_dokumen' => 'Nomor dokumen sudah digunakan.']);
    }

    public function test_imported_existing_master_rejected_when_number_exists_in_imported_master_documents(): void
    {
        Storage::fake('local');

        [$user, $level, $documentType, $businessProcess, $businessFunction, $department] = $this->existingMasterFixture([
            'documents.master.imports.store-level',
            'documents.master.imports.create-level',
        ]);

        $this->createImportedMaster($user, [
            'm_document_level_id' => $level->id,
            'm_document_types_id' => $documentType->id,
            'm_proses_bisnis_id' => $businessProcess->id,
            'm_proses_fungsi_id' => $businessFunction->id,
            'nama_dokumen' => 'First Imported Master',
            'nomor_dokumen' => 'PS-OPS-05',
            'nomor_revisi' => '00.00',
        ]);

        $this->actingAs($user)
            ->from(route('documents.master.imports.create.level', 'level-2'))
            ->post(route('documents.master.imports.store.level', 'level-2'), [
                'm_document_level_id' => $level->id,
                'm_document_types_id' => $documentType->id,
                'm_proses_bisnis_id' => $businessProcess->id,
                'm_proses_fungsi_id' => $businessFunction->id,
                'department_ids' => [$department->id],
                'nama_dokumen' => 'Second Imported Master With Same Number',
                'nomor_dokumen_suffix' => '05',
                'nomor_revisi' => '00.00',
                'existing_document' => UploadedFile::fake()->create('existing-master.pdf', 100, 'application/pdf'),
            ])
            ->assertRedirect(route('documents.master.imports.create.level', 'level-2'))
            ->assertSessionHasErrors(['nomor_dokumen' => 'Nomor dokumen sudah digunakan.']);
    }

    public function test_non_admin_cannot_access_edit_imported_master_document(): void
    {
        [$user, $level, $documentType, $businessProcess, $businessFunction, $department] = $this->existingMasterFixture([
            'documents.master.view',
            'documents.master.detail',
        ]);

        $importedMaster = $this->createImportedMaster($user, [
            'm_document_level_id' => $level->id,
            'm_document_types_id' => $documentType->id,
            'm_proses_bisnis_id' => $businessProcess->id,
            'm_proses_fungsi_id' => $businessFunction->id,
            'nama_dokumen' => 'Master To Edit Non Admin',
            'nomor_dokumen' => 'PS-OPS-11',
            'nomor_revisi' => '00.00',
        ]);

        $this->actingAs($user)
            ->get(route('documents.master.imported.show', $importedMaster))
            ->assertDontSee('Edit Dokumen');

        $this->actingAs($user)
            ->get(route('documents.master.imports.edit', $importedMaster))
            ->assertForbidden();

        $this->actingAs($user)
            ->put(route('documents.master.imports.update', $importedMaster), [
                'nama_dokumen' => 'Hacked Name',
                'm_proses_bisnis_id' => $businessProcess->id,
                'm_proses_fungsi_id' => $businessFunction->id,
                'department_ids' => [$department->id],
            ])
            ->assertForbidden();
    }

    public function test_admin_can_access_edit_imported_master_document(): void
    {
        [$user, $level, $documentType, $businessProcess, $businessFunction, $department] = $this->existingMasterFixture([]);

        $adminRole = Role::query()->firstOrCreate(['nama_role' => 'admin']);
        $admin = User::factory()->create();
        $admin->roles()->sync([$adminRole->id]);

        $importedMaster = $this->createImportedMaster($user, [
            'm_document_level_id' => $level->id,
            'm_document_types_id' => $documentType->id,
            'm_proses_bisnis_id' => $businessProcess->id,
            'm_proses_fungsi_id' => $businessFunction->id,
            'nama_dokumen' => 'Master To Edit Admin',
            'nomor_dokumen' => 'PS-OPS-12',
            'nomor_revisi' => '00.00',
        ]);
        $importedMaster->departments()->sync([$department->id]);

        $this->actingAs($admin)
            ->get(route('documents.master.imported.show', $importedMaster))
            ->assertOk()
            ->assertSee('Edit Dokumen');

        $this->actingAs($admin)
            ->get(route('documents.master.imports.edit', $importedMaster))
            ->assertOk()
            ->assertSee('Master To Edit Admin')
            ->assertSee('PS-OPS-12');
    }

    public function test_admin_can_update_metadata_imported_master_document(): void
    {
        [$user, $level, $documentType, $businessProcess, $businessFunction, $department] = $this->existingMasterFixture([]);

        $adminRole = Role::query()->firstOrCreate(['nama_role' => 'admin']);
        $admin = User::factory()->create();
        $admin->roles()->sync([$adminRole->id]);

        $importedMaster = $this->createImportedMaster($user, [
            'm_document_level_id' => $level->id,
            'm_document_types_id' => $documentType->id,
            'm_proses_bisnis_id' => $businessProcess->id,
            'm_proses_fungsi_id' => $businessFunction->id,
            'nama_dokumen' => 'Original Name Before Edit',
            'nomor_dokumen' => 'PS-OPS-15',
            'nomor_revisi' => '00.00',
            'catatan' => 'Original Note',
        ]);
        $importedMaster->departments()->sync([$department->id]);

        DocumentNumberRegistry::create([
            'document_number' => 'PS-OPS-15',
            'scope_identifier' => 'PS-OPS',
            'source_type' => DocumentNumberRegistry::SOURCE_T_DOCUMENT,
            'source_id' => $importedMaster->id,
            'registered_by' => $admin->id,
            'registered_at' => now(),
        ]);

        $response = $this->actingAs($admin)
            ->put(route('documents.master.imports.update', $importedMaster), [
                'nama_dokumen' => 'Updated Document Name',
                'm_proses_bisnis_id' => $businessProcess->id,
                'm_proses_fungsi_id' => $businessFunction->id,
                'department_ids' => [$department->id],
                'nomor_dokumen_suffix' => '16',
                'nomor_revisi' => '01.00',
                'tanggal_terbit' => '2026-03-01',
                'catatan' => 'Updated note by admin',
            ]);

        $response->assertRedirect(route('documents.master.imported.show', $importedMaster));
        $response->assertSessionHas('status', 'Metadata dokumen berhasil diperbarui.');

        $importedMaster->refresh();
        $this->assertSame('Updated Document Name', $importedMaster->nama_dokumen);
        $this->assertSame('PS-OPS-16', $importedMaster->nomor_dokumen);
        $this->assertSame('01.00', $importedMaster->nomor_revisi);
        $this->assertSame('2026-03-01', $importedMaster->tanggal_terbit->format('Y-m-d'));
        $this->assertSame('Updated note by admin', $importedMaster->catatan);

        $this->assertDatabaseHas('document_number_registry', [
            'document_number' => 'PS-OPS-16',
            'source_type' => DocumentNumberRegistry::SOURCE_T_DOCUMENT,
            'source_id' => $importedMaster->id,
        ]);
    }

    public function test_admin_cannot_update_with_duplicate_document_number(): void
    {
        [$user, $level, $documentType, $businessProcess, $businessFunction, $department] = $this->existingMasterFixture([]);

        $adminRole = Role::query()->firstOrCreate(['nama_role' => 'admin']);
        $admin = User::factory()->create();
        $admin->roles()->sync([$adminRole->id]);

        $importedMaster1 = $this->createImportedMaster($user, [
            'm_document_level_id' => $level->id,
            'm_document_types_id' => $documentType->id,
            'm_proses_bisnis_id' => $businessProcess->id,
            'm_proses_fungsi_id' => $businessFunction->id,
            'nama_dokumen' => 'First Master Doc',
            'nomor_dokumen' => 'PS-OPS-20',
            'nomor_revisi' => '00.00',
        ]);

        $importedMaster2 = $this->createImportedMaster($user, [
            'm_document_level_id' => $level->id,
            'm_document_types_id' => $documentType->id,
            'm_proses_bisnis_id' => $businessProcess->id,
            'm_proses_fungsi_id' => $businessFunction->id,
            'nama_dokumen' => 'Second Master Doc',
            'nomor_dokumen' => 'PS-OPS-21',
            'nomor_revisi' => '00.00',
        ]);

        $this->actingAs($admin)
            ->from(route('documents.master.imports.edit', $importedMaster2))
            ->put(route('documents.master.imports.update', $importedMaster2), [
                'nama_dokumen' => 'Second Master Doc Updated',
                'm_proses_bisnis_id' => $businessProcess->id,
                'm_proses_fungsi_id' => $businessFunction->id,
                'department_ids' => [$department->id],
                'nomor_dokumen_suffix' => '20', // clashes with importedMaster1 (PS-OPS-20)
                'nomor_revisi' => '00.00',
            ])
            ->assertRedirect(route('documents.master.imports.edit', $importedMaster2))
            ->assertSessionHasErrors(['nomor_dokumen' => 'Nomor dokumen sudah digunakan.']);
    }

    public function test_admin_updating_file_replaces_old_file_and_leaves_only_one_file(): void
    {
        Storage::fake('local');
        [$user, $level, $documentType, $businessProcess, $businessFunction, $department] = $this->existingMasterFixture([]);

        $adminRole = Role::query()->firstOrCreate(['nama_role' => 'admin']);
        $admin = User::factory()->create();
        $admin->roles()->sync([$adminRole->id]);

        $importedMaster = $this->createImportedMaster($user, [
            'm_document_level_id' => $level->id,
            'm_document_types_id' => $documentType->id,
            'm_proses_bisnis_id' => $businessProcess->id,
            'm_proses_fungsi_id' => $businessFunction->id,
            'nama_dokumen' => 'Master With File',
            'nomor_dokumen' => 'PS-OPS-30',
            'nomor_revisi' => '00.00',
        ]);
        $importedMaster->departments()->sync([$department->id]);

        $oldFile = $importedMaster->files()->create([
            'type_file' => DocumentFile::TYPE_IMPORTED_DOCUMENT,
            'path_file' => 'documents/imported-existing/test-old.pdf',
            'uploaded_by' => $user->id,
            'original_file_name' => 'old_file.pdf',
            'stored_file_name' => 'test-old.pdf',
            'file_size' => 1024,
        ]);
        Storage::disk('local')->put($oldFile->path_file, 'old content');

        $this->assertCount(1, $importedMaster->files()->where('type_file', DocumentFile::TYPE_IMPORTED_DOCUMENT)->get());

        $newUploadedFile = UploadedFile::fake()->create('new_replacement_file.pdf', 500, 'application/pdf');

        $this->actingAs($admin)
            ->put(route('documents.master.imports.update', $importedMaster), [
                'nama_dokumen' => 'Master With Replaced File',
                'm_proses_bisnis_id' => $businessProcess->id,
                'm_proses_fungsi_id' => $businessFunction->id,
                'department_ids' => [$department->id],
                'nomor_dokumen_suffix' => '30',
                'nomor_revisi' => '00.00',
                'existing_document' => $newUploadedFile,
            ])
            ->assertRedirect(route('documents.master.imported.show', $importedMaster));

        // Must still have exactly 1 file
        $currentFiles = $importedMaster->files()->where('type_file', DocumentFile::TYPE_IMPORTED_DOCUMENT)->get();
        $this->assertCount(1, $currentFiles);
        $this->assertSame('new_replacement_file.pdf', $currentFiles->first()->original_file_name);
        Storage::disk('local')->assertMissing('documents/imported-existing/test-old.pdf');
    }

    public function test_show_imported_cleans_up_duplicate_existing_document_files(): void
    {
        Storage::fake('local');
        [$user, $level, $documentType, $businessProcess, $businessFunction, $department] = $this->existingMasterFixture([
            'documents.master.view',
            'documents.master.detail',
        ]);

        $importedMaster = $this->createImportedMaster($user, [
            'm_document_level_id' => $level->id,
            'm_document_types_id' => $documentType->id,
            'm_proses_bisnis_id' => $businessProcess->id,
            'm_proses_fungsi_id' => $businessFunction->id,
            'nama_dokumen' => 'Master Duplicate Files',
            'nomor_dokumen' => 'PS-OPS-31',
            'nomor_revisi' => '00.00',
        ]);

        $file1 = $importedMaster->files()->create([
            'type_file' => DocumentFile::TYPE_IMPORTED_DOCUMENT,
            'path_file' => 'documents/imported-existing/file1.pdf',
            'uploaded_by' => $user->id,
            'original_file_name' => 'file1.pdf',
            'stored_file_name' => 'file1.pdf',
            'file_size' => 1024,
        ]);
        Storage::disk('local')->put($file1->path_file, 'file 1');

        $file2 = $importedMaster->files()->create([
            'type_file' => DocumentFile::TYPE_IMPORTED_DOCUMENT,
            'path_file' => 'documents/imported-existing/file2.pdf',
            'uploaded_by' => $user->id,
            'original_file_name' => 'file2.pdf',
            'stored_file_name' => 'file2.pdf',
            'file_size' => 1024,
        ]);
        Storage::disk('local')->put($file2->path_file, 'file 2');

        $this->assertCount(2, $importedMaster->files()->where('type_file', DocumentFile::TYPE_IMPORTED_DOCUMENT)->get());

        $this->actingAs($user)
            ->get(route('documents.master.imported.show', $importedMaster))
            ->assertOk()
            ->assertSee('file2.pdf')
            ->assertDontSee('file1.pdf');

        $this->assertCount(1, $importedMaster->files()->where('type_file', DocumentFile::TYPE_IMPORTED_DOCUMENT)->get());
    }

    public function test_imported_existing_master_revision_can_display_stages_and_be_assigned_approvers(): void
    {
        Storage::fake('local');

        [$user, $level, $documentType, $businessProcess, $businessFunction, $department] = $this->existingMasterFixture([
            'documents.existing.imports.revision',
            'documents.approval.assign',
            'documents.approval.show',
        ]);
        StatusDocument::query()->firstOrCreate(['nama_status' => StatusDocument::PROPOSED]);
        StatusDocument::query()->firstOrCreate(['nama_status' => StatusDocument::APPROVED]);
        StatusDocument::query()->firstOrCreate(['nama_status' => StatusDocument::OBSOLETE]);
        ApprovalStatus::query()->firstOrCreate(['kode_status' => ApprovalStatus::PENDING], ['nama_status' => 'Menunggu']);
        ApprovalStatus::query()->firstOrCreate(['kode_status' => ApprovalStatus::WAITING], ['nama_status' => 'Menunggu Giliran']);
        ApprovalStatus::query()->firstOrCreate(['kode_status' => ApprovalStatus::APPROVED], ['nama_status' => 'Disetujui']);
        ApprovalStatus::query()->firstOrCreate(['kode_status' => ApprovalStatus::REJECTED], ['nama_status' => 'Ditolak']);
        ApprovalStatus::query()->firstOrCreate(['kode_status' => ApprovalStatus::TERMINATED], ['nama_status' => 'Dihentikan']);

        $flow = ApprovalFlow::create([
            'm_document_level_id' => $level->id,
            'm_document_types_id' => $documentType->id,
            'nama_flow' => 'Flow Level 2',
        ]);
        $stage = ApprovalFlowStage::create([
            'm_approval_flow_id' => $flow->id,
            'stage_order' => 1,
            'nama_tahap' => 'Tahap Pemeriksaan',
            'display_label' => 'Tahap Pemeriksaan',
        ]);

        $source = $this->createImportedMaster($user, [
            'm_document_level_id' => $level->id,
            'm_document_types_id' => $documentType->id,
            'm_proses_bisnis_id' => $businessProcess->id,
            'm_proses_fungsi_id' => $businessFunction->id,
            'nama_dokumen' => 'Imported Master Source For Assign',
            'nomor_dokumen' => 'PS-SMR-130',
            'nomor_revisi' => '00.00',
        ]);
        $source->departments()->sync([$department->id]);
        DocumentNumberRegistry::create([
            'document_number' => 'PS-SMR-130',
            'scope_identifier' => 'PS-SMR',
            'source_type' => DocumentNumberRegistry::SOURCE_T_DOCUMENT,
            'source_id' => $source->id,
            'registered_by' => $user->id,
            'registered_at' => now(),
        ]);

        $this->actingAs($user)
            ->post(route('documents.store', 'level-4'), [
                'imported_source' => $source->id,
                'submit_action' => 'submit',
                'nama_dokumen' => 'Imported Master Revision Assign Test',
                'm_proses_bisnis_id' => $businessProcess->id,
                'm_proses_fungsi_id' => $businessFunction->id,
                'department_ids' => [$department->id],
                'official_preparer_id' => $user->id,
                'revision_content' => UploadedFile::fake()->create('revision-content.pdf', 100, 'application/pdf'),
                'revision_content_word' => UploadedFile::fake()->create('revision-content.docx', 24, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'),
                'revision_form' => UploadedFile::fake()->create('revision-form.pdf', 100, 'application/pdf'),
                'revision_form_word' => UploadedFile::fake()->create('revision-form.docx', 24, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'),
            ])
            ->assertRedirect();

        $revision = Document::query()
            ->where('revised_from', $source->id)
            ->firstOrFail();

        $approver = User::factory()->create();

        $this->actingAs($user)
            ->get(route('documents.approval.show', $revision))
            ->assertOk()
            ->assertSee('Tahap Pemeriksaan')
            ->assertDontSee('Belum ada aturan tahap approval.');

        $response = $this->actingAs($user)
            ->post(route('documents.approval.assign', $revision), [
                'stage_approvers' => [
                    $stage->id => [$approver->id],
                ],
            ]);
        $response->assertRedirect(route('documents.approval.show', $revision));

        $this->assertDatabaseHas('t_approval', [
            't_document_id' => $revision->id,
            'user_id' => $approver->id,
            'm_approval_flow_stage_id' => $stage->id,
            'stages' => 'Tahap Pemeriksaan',
        ]);
    }

    private function existingMasterFixture(array $permissionCodes): array
    {
        $user = $this->userWithPermissions($permissionCodes);
        $level = DocumentLevel::query()->where('kode', 'level-2')->firstOrFail();
        DocumentLevel::query()->firstOrCreate(
            ['kode' => 'level-4'],
            [
                'nama_level' => 'Level IV',
                'nama_dokumen' => 'Form / Lembar Revisi',
                'prefix' => 'FM',
                'is_active' => true,
                'sort_order' => 4,
            ],
        );
        $documentType = DocumentType::query()->firstOrCreate(['nama_types' => 'Prosedur']);
        DocumentType::query()->firstOrCreate(['nama_types' => 'Form']);
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
        $user->update(['m_department_id' => $department->id]);

        return [$user, $level, $documentType, $businessProcess, $businessFunction, $department];
    }

    private function createExistingDocument(User $user): Document
    {
        $status = StatusDocument::query()->firstOrCreate(['nama_status' => StatusDocument::APPROVED]);
        $documentType = DocumentType::query()->firstOrCreate(['nama_types' => fake()->unique()->word()]);
        $businessProcess = BusinessProcess::create([
            'kode' => fake()->unique()->lexify('???'),
            'nama_proses_bisnis' => fake()->unique()->words(3, true),
        ]);
        $businessFunction = BusinessFunction::create([
            'kode' => fake()->unique()->lexify('???'),
            'nama_proses_fungsi' => fake()->unique()->words(3, true),
            'm_proses_bisnis_id' => $businessProcess->id,
        ]);
        $level = DocumentLevel::query()->where('kode', 'level-2')->firstOrFail();

        return Document::create([
            'origin' => Document::ORIGIN_WORKFLOW,
            'm_document_level_id' => $level->id,
            'm_status_document_id' => $status->id,
            'm_document_types_id' => $documentType->id,
            'm_proses_bisnis_id' => $businessProcess->id,
            'm_proses_fungsi_id' => $businessFunction->id,
            'user_id' => $user->id,
            'official_preparer_id' => $user->id,
            'nama_dokumen' => 'Master Aktif Saat Ini',
            'nomor_dokumen' => 'PS-SMR-ACTIVE',
            'nomor_revisi' => '00.00',
            'approved_at' => now(),
            'created_at' => now(),
        ]);
    }

    private function createImportedMaster(User $user, array $attributes = []): Document
    {
        $approvedStatus = StatusDocument::query()->firstOrCreate(['nama_status' => StatusDocument::APPROVED]);

        return Document::create($attributes + [
            'origin' => Document::ORIGIN_IMPORTED_CURRENT,
            'm_status_document_id' => $approvedStatus->id,
            'user_id' => $user->id,
            'official_preparer_id' => $user->id,
            'nama_dokumen' => 'Imported Master',
            'nomor_revisi' => '00.00',
            'approved_at' => now(),
            'created_at' => now(),
        ]);
    }

    private function createImportedObsolete(User $user, array $attributes = []): Document
    {
        $obsoleteStatus = StatusDocument::query()->firstOrCreate(['nama_status' => StatusDocument::OBSOLETE]);

        return Document::create($attributes + [
            'origin' => ($attributes['obsolete_rule_type'] ?? null) === 'current_rule'
                ? Document::ORIGIN_IMPORTED_CURRENT
                : Document::ORIGIN_IMPORTED_LEGACY,
            'm_status_document_id' => $obsoleteStatus->id,
            'user_id' => $user->id,
            'official_preparer_id' => $user->id,
            'nama_dokumen' => 'Imported Obsolete',
            'nomor_revisi' => '00.00',
            'obsolete_at' => now(),
            'created_at' => now(),
        ]);
    }

    /**
     * @param  array<int, string>  $permissionCodes
     */
    private function userWithPermissions(array $permissionCodes): User
    {
        $role = Role::query()->firstOrCreate(['nama_role' => 'Role Imported Existing']);
        $user = User::factory()->create();

        foreach ($permissionCodes as $permissionCode) {
            $permission = Permission::query()->firstOrCreate(
                ['code' => $permissionCode],
                [
                    'name' => $permissionCode,
                    'module' => 'Manajemen Dokumen',
                    'route' => match ($permissionCode) {
                        'documents.master.imports.create' => 'documents.master.imports.create',
                        'documents.master.imports.create-level' => 'documents.master.imports.create.level',
                        'documents.master.imports.store' => 'documents.master.imports.store',
                        'documents.master.imports.store-level' => 'documents.master.imports.store.level',
                        'documents.obsolete.imports.create' => 'documents.obsolete.imports.create',
                        'documents.obsolete.imports.create-legacy' => 'documents.obsolete.imports.create.legacy',
                        'documents.obsolete.imports.create-level' => 'documents.obsolete.imports.create.level',
                        'documents.obsolete.imports.store' => 'documents.obsolete.imports.store',
                        'documents.obsolete.imports.store-level' => 'documents.obsolete.imports.store.level',
                        'documents.existing.imports.revision' => 'documents.existing.imports.revisions.store',
                        'documents.existing.imports.numbering-setup' => 'documents.existing.imports.numbering-setups.store',
                        'documents.create.create' => 'documents.store',
                        'documents.master.view' => 'documents.master',
                        'documents.approval.approve' => 'documents.approval.approve',
                        'documents.approval.show' => 'documents.approval.show',
                        'documents.existing.imports.detail' => 'documents.existing.imports.show',
                        'documents.existing.imports.download' => 'documents.existing.imports.files.show',
                        'documents.existing.imports.preview' => 'documents.existing.imports.files.preview',
                        default => 'documents.existing.imports.index',
                    },
                    'action' => str_contains($permissionCode, '.create') || str_contains($permissionCode, '.store')
                        ? 'create'
                        : 'view',
                ],
            );

            $role->permissions()->syncWithoutDetaching([$permission->id]);
        }

        $user->roles()->attach($role);

        return $user->refresh();
    }
}
