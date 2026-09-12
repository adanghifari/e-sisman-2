<?php

namespace Tests\Feature\DocumentManagement;

use App\Models\ApprovalStatus;
use App\Models\BusinessFunction;
use App\Models\BusinessProcess;
use App\Models\Department;
use App\Models\Document;
use App\Models\DocumentFile;
use App\Models\DocumentLevel;
use App\Models\DocumentType;
use App\Models\StatusDocument;
use App\Models\User;
use App\Support\FinalDocuments\FinalArtifactGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class OfficialPreparerSnapshotTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ensureStatuses();
        Storage::fake('local');
        Storage::disk('local')->makeDirectory('documents');
    }

    public function test_submit_document_snapshots_official_preparer_profile(): void
    {
        $submitter = User::factory()->create(['nik' => '000000']);
        $preparerDepartment = Department::query()->create([
            'kode_department' => 'QA',
            'nama_department' => 'Quality Assurance',
        ]);
        $documentDepartment = Department::query()->create([
            'kode_department' => 'OPS',
            'nama_department' => 'Operations',
        ]);
        $officialPreparer = User::factory()->create([
            'name' => 'Nama Penyusun Final',
            'jabatan' => 'Management System Specialist',
            'm_department_id' => $preparerDepartment->id,
        ]);
        $businessProcess = BusinessProcess::query()->create([
            'kode' => 'UTM',
            'nama_proses_bisnis' => 'Utama',
        ]);
        $businessFunction = BusinessFunction::query()->create([
            'kode' => 'SMR',
            'nama_proses_fungsi' => 'Sistem Manajemen',
        ]);
        $this->documentLevel();
        DocumentType::query()->create(['nama_types' => 'Prosedur', 'is_active' => true]);

        $this->actingAs($submitter)
            ->post(route('documents.store', 'level-2'), [
                'nama_dokumen' => 'Dokumen Snapshot Penyusun',
                'm_proses_bisnis_id' => $businessProcess->id,
                'm_proses_fungsi_id' => $businessFunction->id,
                'department_ids' => [$documentDepartment->id],
                'official_preparer_id' => $officialPreparer->id,
                'nomor_dokumen_suffix' => '001',
                'filled_template' => UploadedFile::fake()->create('body.pdf', 12, 'application/pdf'),
                'filled_template_word' => UploadedFile::fake()->create('body.docx', 24, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'),
                'submit_action' => 'submit',
            ])
            ->assertRedirect(route('documents.create'));

        $document = Document::query()->firstOrFail();

        $this->assertSame($officialPreparer->id, $document->official_preparer_id);
        $this->assertSame('Nama Penyusun Final', $document->official_preparer_name_snapshot);
        $this->assertSame('Management System Specialist', $document->official_preparer_position_snapshot);
        $this->assertSame('Quality Assurance', $document->official_preparer_department_snapshot);
        $this->assertDatabaseHas('t_approval', [
            't_document_id' => $document->id,
            'user_id' => $officialPreparer->id,
            'stages' => 'TTD Penyusun Resmi',
        ]);
    }

    public function test_existing_snapshot_is_not_overwritten(): void
    {
        $department = Department::query()->create([
            'kode_department' => 'NEW',
            'nama_department' => 'New Department',
        ]);
        $officialPreparer = User::factory()->create([
            'name' => 'New Name',
            'jabatan' => 'New Position',
            'm_department_id' => $department->id,
        ]);
        $document = $this->approvedDocument([
            'official_preparer_id' => $officialPreparer->id,
            'official_preparer_name_snapshot' => 'Old Name',
            'official_preparer_position_snapshot' => 'Old Position',
            'official_preparer_department_snapshot' => 'Old Department',
        ]);

        $document->snapshotOfficialPreparer();
        $document->refresh();

        $this->assertSame('Old Name', $document->official_preparer_name_snapshot);
        $this->assertSame('Old Position', $document->official_preparer_position_snapshot);
        $this->assertSame('Old Department', $document->official_preparer_department_snapshot);
    }

    public function test_imported_existing_revision_submit_snapshots_official_preparer_profile(): void
    {
        $submitter = User::factory()->create(['nik' => '000000']);
        $preparerDepartment = Department::query()->create([
            'kode_department' => 'IES',
            'nama_department' => 'Imported Existing System',
        ]);
        $documentDepartment = Department::query()->create([
            'kode_department' => 'MST',
            'nama_department' => 'Master Department',
        ]);
        $officialPreparer = User::factory()->create([
            'name' => 'Imported Revision Preparer',
            'jabatan' => 'Revision Specialist',
            'm_department_id' => $preparerDepartment->id,
        ]);
        $documentType = DocumentType::query()->firstOrCreate(
            ['nama_types' => 'Prosedur'],
            ['is_active' => true],
        );
        $businessProcess = BusinessProcess::query()->create([
            'kode' => 'IMP',
            'nama_proses_bisnis' => 'Imported Process',
        ]);
        $businessFunction = BusinessFunction::query()->create([
            'kode' => 'REV',
            'nama_proses_fungsi' => 'Revision Function',
        ]);
        $approvedStatus = StatusDocument::query()->firstOrCreate(
            ['nama_status' => StatusDocument::APPROVED],
            ['is_active' => true],
        );
        $source = Document::query()->create([
            'origin' => Document::ORIGIN_IMPORTED_CURRENT,
            'm_status_document_id' => $approvedStatus->id,
            'm_document_level_id' => $this->documentLevel()->id,
            'm_document_types_id' => $documentType->id,
            'm_proses_bisnis_id' => $businessProcess->id,
            'm_proses_fungsi_id' => $businessFunction->id,
            'user_id' => $submitter->id,
            'nama_dokumen' => 'Imported Existing Master',
            'nomor_dokumen' => 'PS-IMP-001',
            'nomor_revisi' => '00.00',
            'tanggal_terbit' => '2026-08-01',
        ]);
        $source->departments()->sync([$documentDepartment->id]);

        $response = $this->actingAs($submitter)
            ->post(route('documents.store', 'level-4'), [
                'revised_from' => $source->id,
                'submit_action' => 'submit',
                'nama_dokumen' => 'Imported Existing Master Revision',
                'm_proses_bisnis_id' => $businessProcess->id,
                'm_proses_fungsi_id' => $businessFunction->id,
                'department_ids' => [$documentDepartment->id],
                'official_preparer_id' => $officialPreparer->id,
                'catatan_revisi' => 'Update revision',
                'tanggal_terbit' => '2026-08-29',
                'revision_content' => UploadedFile::fake()->create('revision-content.pdf', 12, 'application/pdf'),
                'revision_content_word' => UploadedFile::fake()->create('revision-content.docx', 24, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'),
                'revision_form' => UploadedFile::fake()->create('revision-form.pdf', 12, 'application/pdf'),
                'revision_form_word' => UploadedFile::fake()->create('revision-form.docx', 24, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'),
            ]);

        $response->assertRedirect();

        $document = Document::query()->where('revised_from', $source->id)->firstOrFail();

        $this->assertSame($officialPreparer->id, $document->official_preparer_id);
        $this->assertSame('Imported Revision Preparer', $document->official_preparer_name_snapshot);
        $this->assertSame('Revision Specialist', $document->official_preparer_position_snapshot);
        $this->assertSame('Imported Existing System', $document->official_preparer_department_snapshot);
    }

    public function test_profile_changes_after_snapshot_do_not_change_final_payload(): void
    {
        $oldDepartment = Department::query()->create([
            'kode_department' => 'OLD',
            'nama_department' => 'Old Department',
        ]);
        $newDepartment = Department::query()->create([
            'kode_department' => 'NEW',
            'nama_department' => 'New Department',
        ]);
        $officialPreparer = User::factory()->create([
            'name' => 'Old Name',
            'jabatan' => 'Old Position',
            'm_department_id' => $oldDepartment->id,
        ]);
        $document = $this->approvedDocument(['official_preparer_id' => $officialPreparer->id]);
        $document->snapshotOfficialPreparer();
        $this->createDocumentFile($document);

        $officialPreparer->update([
            'name' => 'New Name',
            'jabatan' => 'New Position',
            'm_department_id' => $newDepartment->id,
        ]);

        $payload = app(FinalArtifactGenerator::class)
            ->prepare($document->refresh())
            ->payload;

        $this->assertSame('Old Name', $payload['preparers'][0]['name']);
        $this->assertSame('Old Position', $payload['preparers'][0]['position']);
        $this->assertSame('Old Department', $payload['preparers'][0]['department']);
        $this->assertStringNotContainsString('New Name', json_encode($payload['preparers']));
    }

    public function test_legacy_document_without_snapshot_uses_official_preparer_as_cover_fallback(): void
    {
        $department = Department::query()->create([
            'kode_department' => 'CUR',
            'nama_department' => 'Current Department',
        ]);
        $officialPreparer = User::factory()->create([
            'name' => 'Current Name',
            'jabatan' => 'Current Position',
            'm_department_id' => $department->id,
        ]);
        $document = $this->approvedDocument(['official_preparer_id' => $officialPreparer->id]);
        $this->createDocumentFile($document);

        $payload = app(FinalArtifactGenerator::class)
            ->prepare($document)
            ->payload;

        $this->assertSame($officialPreparer->id, $payload['preparers'][0]['id']);
        $this->assertSame('Current Name', $payload['preparers'][0]['name']);
        $this->assertSame('Current Position', $payload['preparers'][0]['position']);
        $this->assertSame('Current Department', $payload['preparers'][0]['department']);
    }

    public function test_approved_revision_cover_uses_revision_official_preparer_when_source_preparer_is_different(): void
    {
        $sourcePreparerDepartment = Department::query()->create([
            'kode_department' => 'SRC',
            'nama_department' => 'Source Department',
        ]);
        $revisionPreparerDepartment = Department::query()->create([
            'kode_department' => 'REV',
            'nama_department' => 'Revision Department',
        ]);
        $sourcePreparer = User::factory()->create([
            'name' => 'Source Preparer',
            'jabatan' => 'Source Position',
            'm_department_id' => $sourcePreparerDepartment->id,
        ]);
        $revisionPreparer = User::factory()->create([
            'name' => 'Revision Preparer',
            'jabatan' => 'Revision Position',
            'm_department_id' => $revisionPreparerDepartment->id,
        ]);
        $level = DocumentLevel::query()->firstOrCreate(
            ['kode' => 'level-3'],
            [
                'nama_level' => 'Level III',
                'nama_dokumen' => 'Instruksi Kerja',
                'prefix' => 'IK',
                'is_active' => true,
                'sort_order' => 3,
            ],
        );
        $formLevel = DocumentLevel::query()->firstOrCreate(
            ['kode' => 'level-4'],
            [
                'nama_level' => 'Level IV',
                'nama_dokumen' => 'Form',
                'prefix' => 'FM',
                'is_active' => true,
                'sort_order' => 4,
            ],
        );
        $documentType = DocumentType::query()->firstOrCreate(['nama_types' => 'IK'], ['is_active' => true]);
        $formType = DocumentType::query()->firstOrCreate(['nama_types' => 'Form'], ['is_active' => true]);

        $source = $this->approvedDocument([
            'm_document_level_id' => $level->id,
            'm_document_types_id' => $documentType->id,
            'official_preparer_id' => $sourcePreparer->id,
            'official_preparer_name_snapshot' => 'Source Preparer',
            'official_preparer_position_snapshot' => 'Source Position',
            'official_preparer_department_snapshot' => 'Source Department',
            'nama_dokumen' => 'Dokumen Tes Intruksi Kerja',
            'nomor_dokumen' => 'IK-SMR-01-01',
        ]);

        $revision = $this->approvedDocument([
            'm_document_level_id' => $formLevel->id,
            'm_document_types_id' => $formType->id,
            'official_preparer_id' => $revisionPreparer->id,
            'official_preparer_name_snapshot' => null,
            'official_preparer_position_snapshot' => null,
            'official_preparer_department_snapshot' => null,
            'revised_from' => $source->id,
            'request_type' => 'revision',
            'nama_dokumen' => 'Dokumen Tes Intruksi Kerja Part 1 Fokus Lampiran',
            'nomor_dokumen' => 'IK-SMR-01-01',
            'nomor_revisi' => 1,
            'nomor_lembar_revisi' => 'FMIK-SMR-01-01-01',
        ]);
        $this->createDocumentFile($revision, 'revision_content');

        $payload = app(FinalArtifactGenerator::class)
            ->prepare($revision)
            ->payload;

        $this->assertSame('Revision Preparer', $payload['preparers'][0]['name']);
        $this->assertSame('Revision Position', $payload['preparers'][0]['position']);
        $this->assertSame('Revision Department', $payload['preparers'][0]['department']);
        $this->assertStringNotContainsString('Source Preparer', json_encode($payload['preparers']));
    }

    private function approvedDocument(array $attributes = []): Document
    {
        $submitter = User::factory()->create();
        $department = Department::query()->firstOrCreate(
            ['kode_department' => 'SMR'],
            ['nama_department' => 'System Management & Risk'],
        );
        $documentType = DocumentType::query()->firstOrCreate(
            ['nama_types' => 'Prosedur'],
            ['is_active' => true],
        );

        $document = Document::query()->create($attributes + [
            'm_document_level_id' => $this->documentLevel()->id,
            'm_status_document_id' => StatusDocument::query()->where('nama_status', StatusDocument::APPROVED)->value('id'),
            'm_document_types_id' => $documentType->id,
            'm_proses_bisnis_id' => BusinessProcess::query()->firstOrCreate(
                ['kode' => 'UTM'],
                ['nama_proses_bisnis' => 'Utama'],
            )->id,
            'm_proses_fungsi_id' => BusinessFunction::query()->firstOrCreate(
                ['kode' => 'SMR'],
                ['nama_proses_fungsi' => 'Sistem Manajemen'],
            )->id,
            'user_id' => $submitter->id,
            'nama_dokumen' => 'Dokumen Approved',
            'nomor_dokumen' => 'PS-SMR-001',
            'nomor_revisi' => 0,
            'tanggal_terbit' => now()->toDateString(),
            'approved_at' => now(),
            'submitted_at' => now(),
        ]);
        $document->departments()->sync([$department->id]);

        return $document;
    }

    private function createDocumentFile(Document $document, string $type = 'filled_template'): DocumentFile
    {
        $path = "documents/{$document->id}/{$type}.pdf";
        Storage::disk('local')->put($path, 'pdf body');

        return DocumentFile::query()->create([
            't_document_id' => $document->id,
            'type_file' => $type,
            'path_file' => $path,
            'uploaded_by' => $document->user_id,
            'updated_at' => now(),
            'original_file_name' => "{$type}.pdf",
            'stored_file_name' => "{$type}.pdf",
            'file_size' => 8,
        ]);
    }

    private function documentLevel(): DocumentLevel
    {
        return DocumentLevel::query()->firstOrCreate(
            ['kode' => 'level-2'],
            [
                'nama_level' => 'Level II',
                'nama_dokumen' => 'Prosedur',
                'prefix' => 'PS',
                'is_active' => true,
                'sort_order' => 2,
            ],
        );
    }

    private function ensureStatuses(): void
    {
        foreach ([StatusDocument::DRAFT, StatusDocument::PROPOSED, StatusDocument::APPROVED, StatusDocument::REJECTED, StatusDocument::CANCELLED, StatusDocument::OBSOLETE] as $status) {
            StatusDocument::query()->firstOrCreate(['nama_status' => $status]);
        }

        foreach ([ApprovalStatus::PENDING, ApprovalStatus::WAITING, ApprovalStatus::APPROVED, ApprovalStatus::REJECTED, ApprovalStatus::TERMINATED] as $status) {
            ApprovalStatus::query()->firstOrCreate(
                ['kode_status' => $status],
                ['nama_status' => $status],
            );
        }

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

        DocumentType::query()->firstOrCreate(
            ['nama_types' => 'Form'],
            ['is_active' => true],
        );
    }
}
