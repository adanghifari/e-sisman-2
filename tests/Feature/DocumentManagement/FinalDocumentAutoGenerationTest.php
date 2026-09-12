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
use App\Support\FinalDocuments\AutoGenerateFinalDocument;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use setasign\Fpdi\Tcpdf\Fpdi;
use TCPDF;
use Tests\TestCase;

class FinalDocumentAutoGenerationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        $this->ensureStatuses();
    }

    public function test_intermediate_approval_does_not_generate_final_document(): void
    {
        [$document, $firstApprover, $secondApprover] = $this->documentWithTwoStageApprovals();
        $this->storeDocumentFile($document, 'filled_template', $this->pdfBinary(['Body']));

        $this->actingAs($firstApprover)
            ->post(route('documents.approval.approve', $document))
            ->assertRedirect(route('documents.approval.show', $document));

        $this->assertSame(StatusDocument::PROPOSED, $document->refresh()->status->nama_status);
        $this->assertSame(
            ApprovalStatus::PENDING,
            Approval::query()->where('user_id', $secondApprover->id)->firstOrFail()->status->kode_status,
        );
        $this->assertSame(0, DocumentFinalArtifact::query()->where('artifact_type', DocumentFinalArtifact::TYPE_FINAL_DOCUMENT)->count());
    }

    public function test_last_required_approval_auto_generates_final_document_after_document_is_approved(): void
    {
        [$document, $firstApprover, $secondApprover] = $this->documentWithTwoStageApprovals();
        $this->storeDocumentFile($document, 'filled_template', $this->pdfBinary(['Body 1', 'Body 2']));

        $this->actingAs($firstApprover)->post(route('documents.approval.approve', $document));

        $this->actingAs($secondApprover)
            ->post(route('documents.approval.approve', $document))
            ->assertRedirect(route('documents.approval.show', $document));

        $artifact = DocumentFinalArtifact::query()->firstOrFail();

        $this->assertSame(StatusDocument::APPROVED, $document->refresh()->status->nama_status);
        $this->assertSame(DocumentFinalArtifact::TYPE_FINAL_DOCUMENT, $artifact->artifact_type);
        $this->assertSame(DocumentFinalArtifact::STATUS_GENERATED, $artifact->generation_status);
        $this->assertStringStartsWith("documents/final/{$document->id}/final_document/1/final-", $artifact->path_file);
        $this->assertNotNull($artifact->checksum_sha256);
        $this->assertGreaterThan(1000, $artifact->file_size);
        Storage::disk('local')->assertExists($artifact->path_file);
    }

    public function test_level_one_final_document_skips_approval_sheet_after_approval(): void
    {
        $submitter = User::factory()->create();
        $approver = User::factory()->create();
        $manualLevel = DocumentLevel::query()->where('kode', 'level-1')->firstOrFail();
        $manualType = DocumentType::query()->firstOrCreate(['nama_types' => 'Manual']);
        $document = $this->createDocument($submitter, [
            'm_document_level_id' => $manualLevel->id,
            'm_document_types_id' => $manualType->id,
            'm_proses_bisnis_id' => null,
            'm_proses_fungsi_id' => null,
            'nama_dokumen' => 'Manual Tanpa Lembar Pengesahan',
            'nomor_dokumen' => 'SM-001',
        ]);
        $document->departments()->detach();
        $this->createFlow($document, ['Verifikator Manual']);
        $this->createApproval($document, $approver, ApprovalStatus::PENDING, 'Verifikator Manual');
        $this->storeDocumentFile($document, 'imported_document', $this->pdfBinary(['Manual Body']));

        $this->actingAs($approver)
            ->post(route('documents.approval.approve', $document))
            ->assertRedirect(route('documents.approval.show', $document));

        $artifact = DocumentFinalArtifact::query()
            ->where('t_document_id', $document->id)
            ->where('artifact_type', DocumentFinalArtifact::TYPE_FINAL_DOCUMENT)
            ->firstOrFail();

        $this->assertSame(StatusDocument::APPROVED, $document->refresh()->status->nama_status);
        $this->assertSame(DocumentFinalArtifact::STATUS_GENERATED, $artifact->generation_status);
        $this->assertSame(2, $this->pageCount(Storage::disk('local')->path($artifact->path_file)));
    }

    public function test_rejected_document_does_not_generate_final_document(): void
    {
        [$document, $approver] = $this->documentWithSingleApproval();
        $this->storeDocumentFile($document, 'filled_template', $this->pdfBinary(['Body']));

        $this->actingAs($approver)
            ->post(route('documents.approval.reject', $document), ['catatan' => 'Tidak sesuai.'])
            ->assertRedirect(route('documents.approval.show', $document));

        $this->assertSame(StatusDocument::REJECTED, $document->refresh()->status->nama_status);
        $this->assertSame(0, DocumentFinalArtifact::query()->count());
    }

    public function test_duplicate_auto_generation_does_not_create_duplicate_generated_artifact(): void
    {
        [$document, $approver] = $this->documentWithSingleApproval();
        $this->storeDocumentFile($document, 'filled_template', $this->pdfBinary(['Body']));

        $this->actingAs($approver)->post(route('documents.approval.approve', $document));

        app(AutoGenerateFinalDocument::class)->generateIfNeeded($document->id, $approver->id);
        app(AutoGenerateFinalDocument::class)->generateIfNeeded($document->id, $approver->id);

        $this->assertSame(1, DocumentFinalArtifact::query()
            ->where('t_document_id', $document->id)
            ->where('artifact_type', DocumentFinalArtifact::TYPE_FINAL_DOCUMENT)
            ->where('generation_status', DocumentFinalArtifact::STATUS_GENERATED)
            ->count());
    }

    public function test_generation_failure_keeps_document_approved_and_marks_artifact_failed(): void
    {
        [$document, $approver] = $this->documentWithSingleApproval();
        $this->storeDocumentFile($document, 'filled_template', '%PDF-1.4 invalid body');

        $this->actingAs($approver)
            ->post(route('documents.approval.approve', $document))
            ->assertRedirect(route('documents.approval.show', $document));

        $artifact = DocumentFinalArtifact::query()->firstOrFail();

        $this->assertSame(StatusDocument::APPROVED, $document->refresh()->status->nama_status);
        $this->assertSame(ApprovalStatus::APPROVED, Approval::query()->firstOrFail()->status->kode_status);
        $this->assertSame(DocumentFinalArtifact::STATUS_FAILED, $artifact->generation_status);
        $this->assertNotNull($artifact->generation_error);
        Storage::disk('local')->assertMissing($artifact->path_file);
    }

    public function test_approved_revision_generates_final_document_for_new_revision_and_keeps_old_artifact(): void
    {
        [$source, $revision, $approver] = $this->revisionFixture();
        $oldArtifact = DocumentFinalArtifact::query()->create([
            't_document_id' => $source->id,
            'source_document_file_id' => $this->storeDocumentFile($source, 'filled_template', $this->pdfBinary(['Old']))->id,
            'artifact_type' => DocumentFinalArtifact::TYPE_FINAL_DOCUMENT,
            'generation_number' => 1,
            'generation_status' => DocumentFinalArtifact::STATUS_GENERATED,
            'path_file' => "documents/final/{$source->id}/final_document/1/final-old.pdf",
            'generated_file_name' => 'final-old.pdf',
            'checksum_sha256' => hash('sha256', 'old'),
            'file_size' => 3,
            'generated_at' => now(),
        ]);
        $this->storeDocumentFile($revision, 'revision_content', $this->pdfBinary(['Revision']));

        $this->actingAs($approver)
            ->post(route('documents.approval.approve', $revision))
            ->assertRedirect(route('documents.approval.show', $revision));

        $this->assertSame(StatusDocument::APPROVED, $revision->refresh()->status->nama_status);
        $this->assertSame(StatusDocument::OBSOLETE, $source->refresh()->status->nama_status);
        $this->assertDatabaseHas('document_final_artifacts', [
            'id' => $oldArtifact->id,
            't_document_id' => $source->id,
            'generation_status' => DocumentFinalArtifact::STATUS_GENERATED,
        ]);
        $this->assertDatabaseHas('document_final_artifacts', [
            't_document_id' => $revision->id,
            'artifact_type' => DocumentFinalArtifact::TYPE_FINAL_DOCUMENT,
            'generation_status' => DocumentFinalArtifact::STATUS_GENERATED,
        ]);
    }

    public function test_imported_existing_revision_auto_generates_final_document_for_new_t_document_revision(): void
    {
        [$revision, $approver, $source] = $this->importedExistingRevisionFixture();
        $this->storeDocumentFile($revision, 'revision_content', $this->pdfBinary(['Imported existing revision']));

        $this->actingAs($approver)
            ->post(route('documents.approval.approve', $revision))
            ->assertRedirect(route('documents.approval.show', $revision));

        $this->assertSame(StatusDocument::APPROVED, $revision->refresh()->status->nama_status);
        $this->assertSame(StatusDocument::OBSOLETE, $source->refresh()->status->nama_status);
        $this->assertDatabaseHas('document_final_artifacts', [
            't_document_id' => $revision->id,
            'artifact_type' => DocumentFinalArtifact::TYPE_FINAL_DOCUMENT,
            'generation_status' => DocumentFinalArtifact::STATUS_GENERATED,
        ]);
    }

    public function test_obsolete_request_approval_does_not_auto_generate_final_document(): void
    {
        [$source, $request, $approver] = $this->obsoleteRequestFixture();
        $this->storeDocumentFile($source, 'filled_template', $this->pdfBinary(['Source']));

        $this->actingAs($approver)
            ->post(route('documents.approval.approve', $request))
            ->assertRedirect(route('documents.approval.show', $request));

        $this->assertSame(StatusDocument::APPROVED, $request->refresh()->status->nama_status);
        $this->assertSame(StatusDocument::OBSOLETE, $source->refresh()->status->nama_status);
        $this->assertSame(0, DocumentFinalArtifact::query()->count());
    }

    /**
     * @return array{0: Document, 1: User}
     */
    private function documentWithSingleApproval(array $attributes = []): array
    {
        $submitter = User::factory()->create();
        $approver = User::factory()->create();
        $document = $this->createDocument($submitter, $attributes);
        $this->createFlow($document, ['Approval']);
        $this->createApproval($document, $approver, ApprovalStatus::PENDING, 'Approval');

        return [$document, $approver];
    }

    /**
     * @return array{0: Document, 1: User, 2: User}
     */
    private function documentWithTwoStageApprovals(): array
    {
        $submitter = User::factory()->create();
        $firstApprover = User::factory()->create();
        $secondApprover = User::factory()->create();
        $document = $this->createDocument($submitter);
        $this->createFlow($document, ['Stage 1', 'Stage 2']);
        $this->createApproval($document, $firstApprover, ApprovalStatus::PENDING, 'Stage 1');
        $this->createApproval($document, $secondApprover, ApprovalStatus::WAITING, 'Stage 2');

        return [$document, $firstApprover, $secondApprover];
    }

    /**
     * @return array{0: Document, 1: Document, 2: User}
     */
    private function revisionFixture(): array
    {
        $submitter = User::factory()->create();
        $approver = User::factory()->create();
        $approvedStatus = StatusDocument::findByName(StatusDocument::APPROVED);
        $source = $this->createDocument($submitter, [
            'm_status_document_id' => $approvedStatus->id,
            'approved_at' => now()->subDay(),
            'nama_dokumen' => 'Master Lama',
            'nomor_dokumen' => 'PS-SMR-REV',
        ]);
        $revision = $this->createDocument($submitter, [
            'm_document_level_id' => DocumentLevel::query()->where('kode', 'level-4')->value('id'),
            'revised_from' => $source->id,
            'request_type' => 'revision',
            'nama_dokumen' => 'Master Lama Revisi',
            'nomor_dokumen' => 'FMPS-SMR-REV-01',
            'nomor_revisi' => 1,
        ]);
        $revision->departments()->sync($source->departments()->pluck('departments.id')->all());
        $this->createFlow($source, ['Approval']);
        $this->createApproval($revision, $approver, ApprovalStatus::PENDING, 'Approval');

        return [$source, $revision, $approver];
    }

    /**
     * @return array{0: Document, 1: User, 2: Document}
     */
    private function importedExistingRevisionFixture(): array
    {
        $submitter = User::factory()->create();
        $approver = User::factory()->create();
        $base = $this->documentBase();
        $approvedStatus = StatusDocument::findByName(StatusDocument::APPROVED);
        $source = Document::query()->create([
            'origin' => Document::ORIGIN_IMPORTED_CURRENT,
            'm_status_document_id' => $approvedStatus->id,
            'm_document_level_id' => $base['level']->id,
            'm_document_types_id' => $base['type']->id,
            'm_proses_bisnis_id' => $base['businessProcess']->id,
            'm_proses_fungsi_id' => $base['businessFunction']->id,
            'user_id' => $submitter->id,
            'nama_dokumen' => 'Imported Existing Master',
            'nomor_dokumen' => 'PS-SMR-IMP',
            'nomor_revisi' => '00.00',
            'tanggal_terbit' => now()->subYear()->toDateString(),
        ]);
        $source->departments()->sync([$base['department']->id]);

        $revision = $this->createDocument($submitter, [
            'revised_from' => $source->id,
            'request_type' => 'revision',
            'nama_dokumen' => 'Imported Existing Master Revision',
            'nomor_dokumen' => 'FMPS-SMR-IMP-01',
            'nomor_revisi' => 1,
        ]);
        $this->createFlow($revision, ['Approval']);
        $this->createApproval($revision, $approver, ApprovalStatus::PENDING, 'Approval');

        return [$revision, $approver, $source];
    }

    /**
     * @return array{0: Document, 1: Document, 2: User}
     */
    private function obsoleteRequestFixture(): array
    {
        $submitter = User::factory()->create();
        $requester = User::factory()->create();
        $approver = User::factory()->create();
        $approvedStatus = StatusDocument::findByName(StatusDocument::APPROVED);
        $source = $this->createDocument($submitter, [
            'm_status_document_id' => $approvedStatus->id,
            'approved_at' => now()->subDay(),
            'nama_dokumen' => 'Master Obsolete',
            'nomor_dokumen' => 'PS-SMR-OBS',
        ]);
        $request = $this->createDocument($requester, [
            'revised_from' => $source->id,
            'request_type' => 'obsolete',
            'nama_dokumen' => 'Master Obsolete',
            'nomor_dokumen' => 'PS-SMR-OBS',
            'nomor_revisi' => 0,
        ]);
        $request->departments()->sync($source->departments()->pluck('departments.id')->all());
        $this->createFlow($request, ['Approval']);
        $this->createApproval($request, $approver, ApprovalStatus::PENDING, 'Approval');

        return [$source, $request, $approver];
    }

    private function createDocument(User $submitter, array $attributes = []): Document
    {
        $base = $this->documentBase();
        $officialPreparer = User::factory()->create([
            'name' => 'Official Preparer',
            'jabatan' => 'Management System Specialist',
            'm_department_id' => $base['department']->id,
        ]);

        $document = Document::query()->create($attributes + [
            'm_document_level_id' => $base['level']->id,
            'm_status_document_id' => StatusDocument::findByName(StatusDocument::PROPOSED)->id,
            'm_document_types_id' => $base['type']->id,
            'm_proses_bisnis_id' => $base['businessProcess']->id,
            'm_proses_fungsi_id' => $base['businessFunction']->id,
            'user_id' => $submitter->id,
            'official_preparer_id' => $officialPreparer->id,
            'official_preparer_name_snapshot' => 'Official Preparer',
            'official_preparer_position_snapshot' => 'Management System Specialist',
            'official_preparer_department_snapshot' => $base['department']->nama_department,
            'nama_dokumen' => 'Dokumen Auto Final',
            'nomor_dokumen' => 'PS-SMR-AUTO',
            'nomor_revisi' => 0,
            'tanggal_terbit' => now()->toDateString(),
            'submitted_at' => now(),
        ]);
        $document->departments()->sync([$base['department']->id]);

        return $document;
    }

    /**
     * @return array{level: DocumentLevel, type: DocumentType, businessProcess: BusinessProcess, businessFunction: BusinessFunction, department: Department}
     */
    private function documentBase(): array
    {
        return [
            'level' => DocumentLevel::query()->firstOrCreate(
                ['kode' => 'level-2'],
                ['nama_level' => 'Level II', 'nama_dokumen' => 'Prosedur', 'prefix' => 'PS', 'is_active' => true, 'sort_order' => 2],
            ),
            'type' => DocumentType::query()->firstOrCreate(['nama_types' => 'Prosedur'], ['is_active' => true]),
            'businessProcess' => BusinessProcess::query()->firstOrCreate(
                ['kode' => 'Utama'],
                ['nama_proses_bisnis' => 'Proses Inti / Utama'],
            ),
            'businessFunction' => BusinessFunction::query()->firstOrCreate(
                ['kode' => 'SMR'],
                ['nama_proses_fungsi' => 'Sistem Manajemen & Resiko'],
            ),
            'department' => Department::query()->firstOrCreate(
                ['kode_department' => 'SMR'],
                ['nama_department' => 'System Management & Risk'],
            ),
        ];
    }

    /**
     * @param  array<int, string>  $stageNames
     */
    private function createFlow(Document $document, array $stageNames): ApprovalFlow
    {
        $flow = ApprovalFlow::query()->create([
            'm_document_level_id' => $document->m_document_level_id,
            'nama_flow' => 'Auto Generation Flow '.$document->id,
        ]);

        foreach ($stageNames as $index => $stageName) {
            $flow->stages()->create([
                'stage_order' => $index + 1,
                'nama_tahap' => $stageName,
            ]);
        }

        return $flow;
    }

    private function createApproval(Document $document, User $approver, string $status, string $stage): Approval
    {
        return Approval::query()->create([
            't_document_id' => $document->id,
            'm_approval_status_id' => ApprovalStatus::findByCode($status)->id,
            'user_id' => $approver->id,
            'role_id' => null,
            'assigned_by' => $document->user_id,
            'assigned_at' => now(),
            'responded_at' => $status === ApprovalStatus::APPROVED ? now() : null,
            'stages' => $stage,
        ]);
    }

    private function storeDocumentFile(Document $document, string $type, string $contents): DocumentFile
    {
        $path = "documents/{$document->id}/{$type}.pdf";
        Storage::disk('local')->put($path, $contents);

        return DocumentFile::query()->create([
            't_document_id' => $document->id,
            'type_file' => $type,
            'path_file' => $path,
            'uploaded_by' => $document->user_id,
            'original_file_name' => "{$type}.pdf",
            'stored_file_name' => "{$type}.pdf",
            'file_size' => strlen($contents),
        ]);
    }

    /**
     * @param  array<int, string>  $pages
     */
    private function pdfBinary(array $pages): string
    {
        $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->setAutoPageBreak(false, 0);
        $pdf->setMargins(0, 0, 0);
        $pdf->setCompression(false);

        foreach ($pages as $page) {
            $pdf->AddPage('P', [210, 297]);
            $pdf->SetFont('helvetica', '', 12);
            $pdf->Text(10, 10, $page);
        }

        return $pdf->Output('', 'S');
    }

    private function pageCount(string $path): int
    {
        return (new Fpdi)->setSourceFile($path);
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
            ['nama_level' => 'Level IV', 'nama_dokumen' => 'Form', 'prefix' => 'FM', 'is_active' => true, 'sort_order' => 4],
        );
    }
}
