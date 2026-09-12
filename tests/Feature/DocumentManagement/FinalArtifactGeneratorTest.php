<?php

namespace Tests\Feature\DocumentManagement;

use App\Models\Approval;
use App\Models\ApprovalFlow;
use App\Models\ApprovalStatus;
use App\Models\BusinessFunction;
use App\Models\BusinessProcess;
use App\Models\Department;
use App\Models\Document;
use App\Models\DocumentFile;
use App\Models\DocumentFinalArtifact;
use App\Models\DocumentLevel;
use App\Models\DocumentType;
use App\Models\StatusDocument;
use App\Models\User;
use App\Support\FinalDocuments\FinalArtifactGenerator;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class FinalArtifactGeneratorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ensureStatuses();
        Storage::fake('local');
    }

    public function test_approved_document_is_eligible_and_prepare_creates_artifact_foundation(): void
    {
        $generatorUser = User::factory()->create();
        $document = $this->createDocument([
            'nama_dokumen' => 'Dokumen Final Artifact',
            'nomor_dokumen' => 'PS-SMR-FINAL',
            'nomor_revisi' => 101,
            'tanggal_terbit' => '2026-08-28',
        ]);
        $sourceFile = $this->createDocumentFile($document, 'filled_template', 'source body');

        $preparation = app(FinalArtifactGenerator::class)->prepare($document, $generatorUser);

        $artifact = $preparation->artifact;
        $this->assertSame($document->id, $artifact->t_document_id);
        $this->assertSame($sourceFile->id, $artifact->source_document_file_id);
        $this->assertSame(DocumentFinalArtifact::TYPE_FINAL_DOCUMENT, $artifact->artifact_type);
        $this->assertSame(1, $artifact->generation_number);
        $this->assertSame(DocumentFinalArtifact::STATUS_PENDING, $artifact->generation_status);
        $this->assertStringStartsWith("documents/final/{$document->id}/final_document/1/final-ps-smr-final-g1", $artifact->path_file);
        $this->assertSame($generatorUser->id, $artifact->generated_by);
        $this->assertNull($artifact->generated_at);
        $this->assertNull($artifact->checksum_sha256);
        $this->assertNull($artifact->file_size);
        Storage::disk('local')->assertExists($sourceFile->path_file);
        Storage::disk('local')->assertMissing($artifact->path_file);
        $this->assertSame('source body', Storage::disk('local')->get($sourceFile->path_file));
    }

    public function test_non_approved_document_is_rejected(): void
    {
        $document = $this->createDocument(statusName: StatusDocument::PROPOSED);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Only approved or obsolete documents can be prepared as final artifacts.');

        app(FinalArtifactGenerator::class)->prepare($document);
    }

    public function test_metadata_payload_is_normalized(): void
    {
        $department = Department::query()->create([
            'kode_department' => 'OPS',
            'nama_department' => 'Operation Department',
        ]);
        $preparerDepartment = Department::query()->create([
            'kode_department' => 'QA',
            'nama_department' => 'Quality Assurance',
        ]);
        $officialPreparer = User::factory()->create([
            'name' => 'Official Preparer',
            'jabatan' => 'Management System Specialist',
            'm_department_id' => $preparerDepartment->id,
        ]);
        $document = $this->createDocument([
            'm_document_level_id' => $this->documentLevel('level-3', 'Level III', 'Instruksi Kerja')->id,
            'document_type_name' => 'IK',
            'official_preparer_id' => $officialPreparer->id,
            'official_preparer_name_snapshot' => 'Official Preparer',
            'official_preparer_position_snapshot' => 'Management System Specialist',
            'official_preparer_department_snapshot' => 'Quality Assurance',
            'nama_dokumen' => 'Instruksi Operasi',
            'nomor_dokumen' => 'IK-SMR-001',
            'nomor_revisi' => 203,
            'nomor_lembar_revisi' => 'FMIK-SMR-001-02',
        ]);
        $document->departments()->sync([$department->id]);
        $sourceFile = $this->createDocumentFile($document, 'filled_template');

        $payload = app(FinalArtifactGenerator::class)
            ->prepare($document)
            ->payload;

        $this->assertSame($document->id, $payload['document']['id']);
        $this->assertSame('Instruksi Operasi', $payload['document']['name']);
        $this->assertSame('IK-SMR-001', $payload['document']['number']);
        $this->assertSame(203, $payload['document']['revision']);
        $this->assertSame('02.03', $payload['document']['revision_label']);
        $this->assertSame('FMIK-SMR-001-02', $payload['document']['revision_form_number']);
        $this->assertSame('IK', $payload['document']['type']);
        $this->assertSame('level-3', $payload['document']['level']['code']);
        $this->assertSame('Operation Department', $payload['document']['departments'][0]['name']);
        $this->assertSame('Official Preparer', $payload['preparers'][0]['name']);
        $this->assertSame('Management System Specialist', $payload['preparers'][0]['position']);
        $this->assertSame('Quality Assurance', $payload['preparers'][0]['department']);
        $this->assertNull($payload['preparers'][0]['department_code']);
        $this->assertSame($sourceFile->id, $payload['source']['id']);
        $this->assertSame('filled_template', $payload['source']['type']);
    }

    public function test_approval_payload_groups_dynamic_stages_and_multiple_approvers_by_snapshot_order(): void
    {
        $document = $this->createDocument();
        $lateStage = $this->createApprovalStage($document, 2, 'Disahkan Khusus');
        $earlyStage = $this->createApprovalStage($document, 1, 'Direview Teknis');
        $firstApprover = User::factory()->create();
        $secondApprover = User::factory()->create();
        $thirdApprover = User::factory()->create();
        $thirdApproval = $this->createApproval($document, $thirdApprover, [
            'stages' => $lateStage->display_label,
            'stage_name_snapshot' => 'Disahkan Khusus',
            'stage_order_snapshot' => 2,
            'approver_name_snapshot' => 'Approver Stage Dua',
        ]);
        $firstApproval = $this->createApproval($document, $firstApprover, [
            'stages' => $earlyStage->display_label,
            'stage_name_snapshot' => 'Direview Teknis',
            'stage_order_snapshot' => 1,
            'approver_name_snapshot' => 'Approver Stage Satu A',
        ]);
        $secondApproval = $this->createApproval($document, $secondApprover, [
            'stages' => $earlyStage->display_label,
            'stage_name_snapshot' => 'Direview Teknis',
            'stage_order_snapshot' => 1,
            'approver_name_snapshot' => 'Approver Stage Satu B',
        ]);
        $this->createDocumentFile($document, 'filled_template');

        $approvals = app(FinalArtifactGenerator::class)
            ->prepare($document)
            ->payload['approvals'];

        $this->assertCount(2, $approvals);
        $this->assertSame('Direview Teknis', $approvals[0]['stage_name']);
        $this->assertSame(1, $approvals[0]['stage_order']);
        $this->assertCount(2, $approvals[0]['approvers']);
        $this->assertSame($firstApproval->id, $approvals[0]['approvers'][0]['approval_id']);
        $this->assertSame('Approver Stage Satu A', $approvals[0]['approvers'][0]['name']);
        $this->assertSame($secondApproval->id, $approvals[0]['approvers'][1]['approval_id']);
        $this->assertSame('Approver Stage Satu B', $approvals[0]['approvers'][1]['name']);
        $this->assertSame('Disahkan Khusus', $approvals[1]['stage_name']);
        $this->assertSame($thirdApproval->id, $approvals[1]['approvers'][0]['approval_id']);
    }

    public function test_approval_payload_includes_assigned_preparer_stage_but_hides_auto_signature_stage(): void
    {
        $document = $this->createDocument();
        $officialPreparer = User::query()->findOrFail($document->official_preparer_id);
        $reviewer = User::factory()->create();

        $this->createApproval($document, $officialPreparer, [
            'stages' => 'TTD Penyusun Resmi',
            'stage_name_snapshot' => 'TTD Penyusun Resmi',
            'stage_order_snapshot' => null,
            'approver_name_snapshot' => 'Penyusun Resmi',
        ]);
        $this->createApproval($document, $officialPreparer, [
            'stages' => 'Disusun Oleh',
            'stage_name_snapshot' => 'Disusun Oleh',
            'stage_order_snapshot' => 1,
            'approver_name_snapshot' => 'Penyusun Resmi',
        ]);
        $this->createApproval($document, $reviewer, [
            'stages' => 'Disusun Oleh',
            'stage_name_snapshot' => 'Disusun Oleh',
            'stage_order_snapshot' => 1,
            'approver_name_snapshot' => 'Reviewer Penyusun',
        ]);
        $this->createDocumentFile($document, 'filled_template');

        $approvals = app(FinalArtifactGenerator::class)
            ->prepare($document)
            ->payload['approvals'];

        $this->assertCount(1, $approvals);
        $this->assertSame('Disusun Oleh', $approvals[0]['stage_name']);
        $this->assertSame('Penyusun Resmi', $approvals[0]['approvers'][0]['name']);
        $this->assertSame('Reviewer Penyusun', $approvals[0]['approvers'][1]['name']);
    }

    public function test_approval_payload_uses_snapshot_instead_of_current_user_profile(): void
    {
        $oldDepartment = Department::query()->create([
            'kode_department' => 'QA',
            'nama_department' => 'Quality Assurance',
        ]);
        $newDepartment = Department::query()->create([
            'kode_department' => 'IT',
            'nama_department' => 'Information Technology',
        ]);
        $document = $this->createDocument();
        $approver = User::factory()->create([
            'name' => 'Nama Baru',
            'jabatan' => 'Jabatan Baru',
            'm_department_id' => $newDepartment->id,
        ]);
        $this->createApproval($document, $approver, [
            'stage_name_snapshot' => 'Disetujui Oleh',
            'stage_order_snapshot' => 1,
            'approver_name_snapshot' => 'Nama Lama',
            'approver_position_snapshot' => 'Jabatan Lama',
            'approver_department_snapshot' => $oldDepartment->nama_department,
        ]);
        $this->createDocumentFile($document, 'filled_template');

        $approverPayload = app(FinalArtifactGenerator::class)
            ->prepare($document)
            ->payload['approvals'][0]['approvers'][0];

        $this->assertSame('Nama Lama', $approverPayload['name']);
        $this->assertSame('Jabatan Lama', $approverPayload['position']);
        $this->assertSame('Quality Assurance', $approverPayload['department']);
    }

    public function test_source_resolution_uses_main_document_file_and_ignores_attachment(): void
    {
        $document = $this->createDocument();
        $attachment = $this->createDocumentFile($document, 'attachment');
        $sourceFile = $this->createDocumentFile($document, 'filled_template');

        $preparation = app(FinalArtifactGenerator::class)->prepare($document);

        $this->assertSame($sourceFile->id, $preparation->artifact->source_document_file_id);
        $this->assertNotSame($attachment->id, $preparation->artifact->source_document_file_id);
        $this->assertSame($sourceFile->id, $preparation->payload['source']['id']);
    }

    public function test_payload_includes_attachment_titles_for_final_merge(): void
    {
        $document = $this->createDocument();
        $sourceFile = $this->createDocumentFile($document, 'filled_template');
        $secondAttachment = $this->createDocumentFile($document, 'attachment', 'attachment body 2', [
            'attachment_title' => 'Matriks Komunikasi',
            'attachment_order' => 2,
            'original_file_name' => 'matriks.pdf',
            'stored_file_name' => 'matriks.pdf',
        ]);
        $firstAttachment = $this->createDocumentFile($document, 'attachment', 'attachment body 1', [
            'attachment_title' => 'Barcode pengisian daftar hadir safety induction',
            'attachment_order' => 1,
            'original_file_name' => 'barcode.pdf',
            'stored_file_name' => 'barcode.pdf',
        ]);

        $payload = app(FinalArtifactGenerator::class)
            ->prepare($document)
            ->payload;

        $this->assertSame($sourceFile->id, $payload['source']['id']);
        $this->assertCount(2, $payload['attachments']);
        $this->assertSame($firstAttachment->id, $payload['attachments'][0]['id']);
        $this->assertSame(1, $payload['attachments'][0]['number']);
        $this->assertSame('Barcode pengisian daftar hadir safety induction', $payload['attachments'][0]['title']);
        $this->assertSame('barcode.pdf', $payload['attachments'][0]['original_file_name']);
        $this->assertSame($secondAttachment->id, $payload['attachments'][1]['id']);
        $this->assertSame(2, $payload['attachments'][1]['number']);
        $this->assertSame('Matriks Komunikasi', $payload['attachments'][1]['title']);
    }

    public function test_revision_document_uses_revision_content_as_source(): void
    {
        $document = $this->createDocument(['request_type' => 'revision']);
        $this->createDocumentFile($document, 'revision_form');
        $sourceFile = $this->createDocumentFile($document, 'revision_content');

        $preparation = app(FinalArtifactGenerator::class)->prepare($document);

        $this->assertSame($sourceFile->id, $preparation->payload['source']['id']);
        $this->assertSame('revision_content', $preparation->payload['source']['type']);
    }

    public function test_revision_form_is_merged_as_first_attachment_for_revision_document(): void
    {
        $document = $this->createDocument(['request_type' => 'revision']);
        $revisionForm = $this->createDocumentFile($document, 'revision_form', 'revision form body', [
            'original_file_name' => 'lembar-revisi.pdf',
            'stored_file_name' => 'lembar-revisi.pdf',
        ]);
        $this->createDocumentFile($document, 'revision_content');
        $userAttachment = $this->createDocumentFile($document, 'attachment', 'attachment body', [
            'attachment_title' => 'Matriks Komunikasi',
            'attachment_order' => 1,
            'original_file_name' => 'matriks.pdf',
            'stored_file_name' => 'matriks.pdf',
        ]);

        $payload = app(FinalArtifactGenerator::class)
            ->prepare($document)
            ->payload;

        $this->assertCount(2, $payload['attachments']);
        $this->assertSame($revisionForm->id, $payload['attachments'][0]['id']);
        $this->assertSame(1, $payload['attachments'][0]['number']);
        $this->assertSame('revision_form', $payload['attachments'][0]['type']);
        $this->assertSame('Lembar Revisi', $payload['attachments'][0]['title']);
        $this->assertSame($userAttachment->id, $payload['attachments'][1]['id']);
        $this->assertSame(2, $payload['attachments'][1]['number']);
        $this->assertSame('Matriks Komunikasi', $payload['attachments'][1]['title']);
    }

    public function test_legacy_approval_with_null_snapshot_does_not_crash_or_use_current_profile(): void
    {
        $document = $this->createDocument();
        $approver = User::factory()->create([
            'name' => 'Current Profile Name',
            'jabatan' => 'Current Position',
        ]);
        $this->createApproval($document, $approver, [
            'stages' => 'Legacy Stage',
            'stage_name_snapshot' => null,
            'stage_order_snapshot' => null,
            'approver_name_snapshot' => null,
            'approver_position_snapshot' => null,
            'approver_department_snapshot' => null,
        ]);
        $this->createDocumentFile($document, 'filled_template');

        $approvalPayload = app(FinalArtifactGenerator::class)
            ->prepare($document)
            ->payload['approvals'][0];

        $this->assertSame('Legacy Stage', $approvalPayload['stage_name']);
        $this->assertNull($approvalPayload['stage_order']);
        $this->assertNull($approvalPayload['approvers'][0]['name']);
        $this->assertNull($approvalPayload['approvers'][0]['position']);
        $this->assertNull($approvalPayload['approvers'][0]['department']);
    }

    public function test_revision_final_payload_uses_revision_approvals_for_main_and_revision_approval_sheets(): void
    {
        $root = $this->createDocument([
            'nama_dokumen' => 'Prosedur Root',
            'nomor_dokumen' => 'PS-SMR-ROOT',
        ]);
        $revision = $this->createDocument([
            'm_document_level_id' => $this->documentLevel('level-4', 'Level IV', 'Form')->id,
            'document_type_name' => 'Form',
            'revised_from' => $root->id,
            'request_type' => 'revision',
            'nama_dokumen' => 'Prosedur Root Revisi',
            'nomor_dokumen' => 'PS-SMR-ROOT',
            'nomor_revisi' => 1,
        ]);
        $this->createApproval($root, User::factory()->create(), [
            'stage_name_snapshot' => 'Pengesahan Utama',
            'stage_order_snapshot' => 1,
            'approver_name_snapshot' => 'Approval Root',
        ]);
        $revisionApproval = $this->createApproval($revision, User::factory()->create(), [
            'stage_name_snapshot' => 'Pengesahan Revisi',
            'stage_order_snapshot' => 1,
            'approver_name_snapshot' => 'Approval Revisi',
        ]);
        $this->createDocumentFile($revision, 'revision_content');

        $payload = app(FinalArtifactGenerator::class)
            ->prepare($revision)
            ->payload;

        $this->assertSame($revisionApproval->id, $payload['approvals'][0]['approvers'][0]['approval_id']);
        $this->assertSame('Approval Revisi', $payload['approvals'][0]['approvers'][0]['name']);
        $this->assertSame($revisionApproval->id, $payload['revision_approvals'][0]['approvers'][0]['approval_id']);
        $this->assertSame('Approval Revisi', $payload['revision_approvals'][0]['approvers'][0]['name']);
    }

    private function createDocument(array $attributes = [], string $statusName = StatusDocument::APPROVED): Document
    {
        $submitter = User::factory()->create();
        $department = Department::query()->firstOrCreate(
            ['kode_department' => 'SMR'],
            ['nama_department' => 'System Management & Risk'],
        );
        $level = $attributes['m_document_level_id'] ?? $this->documentLevel()->id;
        $documentType = DocumentType::query()->firstOrCreate(
            ['nama_types' => $attributes['document_type_name'] ?? 'Prosedur'],
            ['is_active' => true],
        );

        unset($attributes['document_type_name']);

        $document = Document::query()->create($attributes + [
            'm_document_level_id' => $level,
            'm_status_document_id' => StatusDocument::query()->where('nama_status', $statusName)->value('id'),
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

    private function createDocumentFile(Document $document, string $type, string $contents = 'pdf body', array $attributes = []): DocumentFile
    {
        $path = $attributes['path_file'] ?? "documents/{$document->id}/{$type}.pdf";
        Storage::disk('local')->put($path, $contents);

        return DocumentFile::query()->create($attributes + [
            't_document_id' => $document->id,
            'type_file' => $type,
            'path_file' => $path,
            'uploaded_by' => $document->user_id,
            'updated_at' => now(),
            'original_file_name' => "{$type}.pdf",
            'stored_file_name' => "{$type}.pdf",
            'file_size' => strlen($contents),
        ]);
    }

    private function createApproval(Document $document, User $approver, array $attributes = []): Approval
    {
        return Approval::query()->create($attributes + [
            't_document_id' => $document->id,
            'm_approval_status_id' => ApprovalStatus::query()->where('kode_status', ApprovalStatus::APPROVED)->value('id'),
            'user_id' => $approver->id,
            'role_id' => null,
            'assigned_by' => $document->user_id,
            'assigned_at' => now(),
            'responded_at' => now(),
            'stages' => 'Approval',
        ]);
    }

    private function createApprovalStage(Document $document, int $order, string $name)
    {
        $flow = ApprovalFlow::query()->firstOrCreate(
            ['m_document_level_id' => $document->m_document_level_id],
            ['nama_flow' => 'Flow Test'],
        );

        return $flow->stages()->create([
            'stage_order' => $order,
            'nama_tahap' => $name,
        ]);
    }

    private function documentLevel(
        string $code = 'level-2',
        string $levelName = 'Level II',
        string $documentName = 'Prosedur',
    ): DocumentLevel {
        return DocumentLevel::query()->firstOrCreate(
            ['kode' => $code],
            [
                'nama_level' => $levelName,
                'nama_dokumen' => $documentName,
                'prefix' => $code === 'level-3' ? 'IK' : 'PS',
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
    }
}
