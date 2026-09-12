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
use App\Support\FinalDocuments\AutoGenerateApprovalPreview;
use App\Support\FinalDocuments\CoverPdfRenderer;
use App\Support\FinalDocuments\FinalArtifactGenerator;
use App\Support\FinalDocuments\FinalPdfComposer;
use App\Support\FinalDocuments\PdfCompositionMode;
use App\Support\FinalDocuments\PdfDocumentContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use setasign\Fpdi\Tcpdf\Fpdi;
use TCPDF;
use Tests\TestCase;

class ApprovalPreviewArtifactTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $storageRoot = storage_path('app/testing/approval-preview-artifacts');
        File::ensureDirectoryExists($storageRoot);
        File::cleanDirectory($storageRoot);

        config(['filesystems.disks.local.root' => $storageRoot]);
        Storage::forgetDisk('local');

        $this->ensureStatuses();
    }

    public function test_approval_preview_context_composes_cover_and_body_without_approval_sheet(): void
    {
        $body = $this->storeRawBodyPdf($this->pdfBinary(['Body page']));

        try {
            $result = app(FinalPdfComposer::class)->compose(
                payload: $this->payload(),
                coverPdf: $this->pdfBinary(['Cover page']),
                approvalSheetPdf: $this->pdfBinary(['Approval sheet marker']),
                bodyPdfPath: $body,
                mode: PdfCompositionMode::PRESERVE,
                context: PdfDocumentContext::APPROVAL_PREVIEW,
            );

            $this->assertSame(1, $result->coverPages);
            $this->assertSame(0, $result->approvalSheetPages);
            $this->assertSame(1, $result->bodyPagesCount);
            $this->assertSame(2, $result->totalPages());
            $this->assertSame(2, $this->pdfPageCount($result->pdf));
            $this->assertSame(['1 dari 1'], array_column($result->bodyPages, 'page_label'));
        } finally {
            @unlink($body);
        }
    }

    public function test_normal_submit_auto_generates_approval_preview_artifact(): void
    {
        [$user, $businessProcess, $businessFunction, $department] = $this->submitFixture();

        $this->actingAs($user)
            ->post(route('documents.store', 'level-2'), [
                'nama_dokumen' => 'Prosedur Preview',
                'm_proses_bisnis_id' => $businessProcess->id,
                'm_proses_fungsi_id' => $businessFunction->id,
                'department_ids' => [$department->id],
                'official_preparer_id' => $user->id,
                'nomor_dokumen_suffix' => '777',
                'tanggal_terbit' => '2026-08-29',
                'filled_template' => UploadedFile::fake()->createWithContent('template.pdf', $this->pdfBinary(['Body'])),
                'filled_template_word' => UploadedFile::fake()->create('template.docx', 24, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'),
                'submit_action' => 'submit',
            ])
            ->assertRedirect(route('documents.create'));

        $document = Document::query()->where('nama_dokumen', 'Prosedur Preview')->firstOrFail();
        $artifact = DocumentFinalArtifact::query()->firstOrFail();

        $this->assertSame(StatusDocument::PROPOSED, $document->status->nama_status);
        $this->assertSame(DocumentFinalArtifact::TYPE_APPROVAL_PREVIEW, $artifact->artifact_type);
        $this->assertSame(DocumentFinalArtifact::STATUS_GENERATED, $artifact->generation_status);
        $this->assertStringStartsWith("documents/final/{$document->id}/approval_preview/1/approval-preview-", $artifact->path_file);
        $this->assertNotNull($artifact->checksum_sha256);
        $this->assertGreaterThan(1000, $artifact->file_size);
        Storage::disk('local')->assertExists($artifact->path_file);
    }

    public function test_approval_preview_payload_and_cover_render_published_date_as_dash(): void
    {
        $document = $this->proposedDocument(['tanggal_terbit' => '2026-08-29']);
        $this->storeDocumentFile($document, 'filled_template', $this->pdfBinary(['Body']));

        $preparation = app(FinalArtifactGenerator::class)->prepareApprovalPreview($document);
        $html = app(CoverPdfRenderer::class)->renderHtml($preparation->payload);

        $this->assertNull($preparation->payload['document']['published_at']);
        $this->assertStringContainsString('TGLTERBIT</td><tdclass="bottom-value">:-</td>', preg_replace('/\s+/', '', $html));
        $this->assertStringNotContainsString('29 - 08 - 2026', $html);
    }

    public function test_draft_submit_action_does_not_generate_approval_preview(): void
    {
        [$user, $businessProcess, $businessFunction, $department] = $this->submitFixture();

        $this->actingAs($user)
            ->post(route('documents.store', 'level-2'), [
                'nama_dokumen' => 'Draft Preview',
                'm_proses_bisnis_id' => $businessProcess->id,
                'm_proses_fungsi_id' => $businessFunction->id,
                'department_ids' => [$department->id],
                'official_preparer_id' => $user->id,
                'nomor_dokumen_suffix' => '778',
                'filled_template' => UploadedFile::fake()->createWithContent('template.pdf', $this->pdfBinary(['Body'])),
                'submit_action' => 'draft',
            ])
            ->assertRedirect(route('documents.create.drafts'));

        $this->assertSame(0, DocumentFinalArtifact::query()->count());
    }

    public function test_intermediate_approval_does_not_regenerate_approval_preview(): void
    {
        [$document, $firstApprover] = $this->documentWithTwoStageApprovals();
        $sourceFile = $this->storeDocumentFile($document, 'filled_template', $this->pdfBinary(['Body']));
        $existing = DocumentFinalArtifact::query()->create([
            't_document_id' => $document->id,
            'source_document_file_id' => $sourceFile->id,
            'artifact_type' => DocumentFinalArtifact::TYPE_APPROVAL_PREVIEW,
            'generation_number' => 1,
            'generation_status' => DocumentFinalArtifact::STATUS_GENERATED,
            'path_file' => "documents/final/{$document->id}/approval_preview/1/approval-preview-existing.pdf",
            'generated_file_name' => 'approval-preview-existing.pdf',
            'checksum_sha256' => hash('sha256', 'preview'),
            'file_size' => 7,
            'generated_at' => now(),
        ]);

        $this->actingAs($firstApprover)
            ->post(route('documents.approval.approve', $document))
            ->assertRedirect(route('documents.approval.show', $document));

        $this->assertSame(StatusDocument::PROPOSED, $document->refresh()->status->nama_status);
        $this->assertSame([$existing->id], DocumentFinalArtifact::query()->pluck('id')->all());
    }

    public function test_preview_generation_failure_keeps_submit_proposed_and_marks_artifact_failed(): void
    {
        [$user, $businessProcess, $businessFunction, $department] = $this->submitFixture();

        $this->actingAs($user)
            ->post(route('documents.store', 'level-2'), [
                'nama_dokumen' => 'Preview Gagal',
                'm_proses_bisnis_id' => $businessProcess->id,
                'm_proses_fungsi_id' => $businessFunction->id,
                'department_ids' => [$department->id],
                'official_preparer_id' => $user->id,
                'nomor_dokumen_suffix' => '779',
                'filled_template' => UploadedFile::fake()->createWithContent('template.pdf', '%PDF-1.4 invalid'),
                'filled_template_word' => UploadedFile::fake()->create('template.docx', 24, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'),
                'submit_action' => 'submit',
            ])
            ->assertRedirect(route('documents.create'));

        $document = Document::query()->where('nama_dokumen', 'Preview Gagal')->firstOrFail();
        $artifact = DocumentFinalArtifact::query()->firstOrFail();

        $this->assertSame(StatusDocument::PROPOSED, $document->status->nama_status);
        $this->assertSame(DocumentFinalArtifact::TYPE_APPROVAL_PREVIEW, $artifact->artifact_type);
        $this->assertSame(DocumentFinalArtifact::STATUS_FAILED, $artifact->generation_status);
        $this->assertNotNull($artifact->generation_error);
    }

    public function test_duplicate_auto_preview_trigger_does_not_create_duplicate_generated_artifact(): void
    {
        $document = $this->proposedDocument();
        $this->storeDocumentFile($document, 'filled_template', $this->pdfBinary(['Body']));

        app(AutoGenerateApprovalPreview::class)->generateIfNeeded($document->id, $document->user_id);
        app(AutoGenerateApprovalPreview::class)->generateIfNeeded($document->id, $document->user_id);

        $this->assertSame(1, DocumentFinalArtifact::query()
            ->where('t_document_id', $document->id)
            ->where('artifact_type', DocumentFinalArtifact::TYPE_APPROVAL_PREVIEW)
            ->where('generation_status', DocumentFinalArtifact::STATUS_GENERATED)
            ->count());
    }

    public function test_revision_submit_generates_preview_and_final_approval_generates_separate_final_document(): void
    {
        [$source, $revision, $approver] = $this->revisionFixture();
        $this->storeDocumentFile($revision, 'revision_content', $this->pdfBinary(['Revision body']));
        $this->storeDocumentFile($revision, 'revision_form', $this->pdfBinary(['Revision form']));

        app(AutoGenerateApprovalPreview::class)->generateIfNeeded($revision->id, $revision->user_id);

        $preview = DocumentFinalArtifact::query()
            ->where('t_document_id', $revision->id)
            ->where('artifact_type', DocumentFinalArtifact::TYPE_APPROVAL_PREVIEW)
            ->firstOrFail();

        $this->actingAs($approver)
            ->post(route('documents.approval.approve', $revision))
            ->assertRedirect(route('documents.approval.show', $revision));

        $this->assertSame(StatusDocument::OBSOLETE, $source->refresh()->status->nama_status);
        $this->assertSame(StatusDocument::APPROVED, $revision->refresh()->status->nama_status);
        $this->assertDatabaseHas('document_final_artifacts', [
            'id' => $preview->id,
            'artifact_type' => DocumentFinalArtifact::TYPE_APPROVAL_PREVIEW,
            'generation_status' => DocumentFinalArtifact::STATUS_GENERATED,
        ]);
        $this->assertDatabaseHas('document_final_artifacts', [
            't_document_id' => $revision->id,
            'artifact_type' => DocumentFinalArtifact::TYPE_FINAL_DOCUMENT,
            'generation_status' => DocumentFinalArtifact::STATUS_GENERATED,
        ]);
    }

    public function test_revision_approval_preview_merges_revision_form_as_first_attachment(): void
    {
        [, $revision] = $this->revisionFixture();
        $revisionForm = $this->storeDocumentFile($revision, 'revision_form', $this->pdfBinary(['Revision form']), [
            'document_number' => 'FMPS-SMR-PREV-REV-01',
            'original_file_name' => 'lembar-revisi.pdf',
            'stored_file_name' => 'lembar-revisi.pdf',
        ]);
        $this->storeDocumentFile($revision, 'revision_content', $this->pdfBinary(['Revision body']));
        $userAttachment = $this->storeDocumentFile($revision, 'attachment', $this->pdfBinary(['User attachment']), [
            'document_number' => 'FMPS-SMR-PREV-REV-02',
            'attachment_title' => 'Matriks Komunikasi',
            'attachment_order' => 1,
            'original_file_name' => 'matriks.pdf',
            'stored_file_name' => 'matriks.pdf',
        ]);

        $payload = app(FinalArtifactGenerator::class)
            ->prepareApprovalPreview($revision)
            ->payload;

        $this->assertSame($revisionForm->id, $payload['attachments'][0]['id']);
        $this->assertSame(1, $payload['attachments'][0]['number']);
        $this->assertSame('FMPS-SMR-PREV-REV-01', $payload['attachments'][0]['document_number']);
        $this->assertSame('Lembar Revisi', $payload['attachments'][0]['title']);
        $this->assertSame($userAttachment->id, $payload['attachments'][1]['id']);
        $this->assertSame(2, $payload['attachments'][1]['number']);
        $this->assertSame('FMPS-SMR-PREV-REV-02', $payload['attachments'][1]['document_number']);
        $this->assertSame('Matriks Komunikasi', $payload['attachments'][1]['title']);
    }

    public function test_revision_approval_preview_does_not_stamp_revision_approval_before_all_approvals_complete(): void
    {
        [, $revision, $approver] = $this->revisionFixture();
        $this->createApproval($revision, $approver, ApprovalStatus::APPROVED, 'Approval Revisi Sebagian');
        $this->storeDocumentFile($revision, 'revision_form', $this->pdfBinary(['Revision form']));
        $this->storeDocumentFile($revision, 'revision_content', $this->pdfBinary(['Revision body']));

        $payload = app(FinalArtifactGenerator::class)
            ->prepareApprovalPreview($revision)
            ->payload;

        $this->assertSame(StatusDocument::PROPOSED, $revision->status->nama_status);
        $this->assertSame([], $payload['revision_approvals']);
    }

    public function test_revision_approval_preview_cover_uses_source_document_level_and_type(): void
    {
        [$source, $revision] = $this->revisionFixture();
        $formType = DocumentType::query()->firstOrCreate(['nama_types' => 'Form'], ['is_active' => true]);
        $revision->update(['m_document_types_id' => $formType->id]);
        $this->storeDocumentFile($revision, 'revision_content', $this->pdfBinary(['Revision body']));

        $payload = app(FinalArtifactGenerator::class)
            ->prepareApprovalPreview($revision)
            ->payload;
        $html = app(CoverPdfRenderer::class)->renderHtml($payload);

        $this->assertSame($source->documentLevel->id, $payload['document']['level']['id']);
        $this->assertSame('level-2', $payload['document']['level']['code']);
        $this->assertSame('Prosedur', $payload['document']['type']);
        $this->assertStringContainsString('PROSEDUR', $html);
        $this->assertStringContainsString('LEVEL 2', $html);
        $this->assertStringNotContainsString('LEVEL 4', $html);
    }

    public function test_imported_existing_revision_submit_generates_approval_preview(): void
    {
        [$user, $source] = $this->importedExistingFixture();

        $this->actingAs($user)
            ->post(route('documents.store', 'level-4'), [
                'revised_from' => $source->id,
                'submit_action' => 'submit',
                'nama_dokumen' => 'Imported Revision Preview',
                'm_proses_bisnis_id' => $source->m_proses_bisnis_id,
                'm_proses_fungsi_id' => $source->m_proses_fungsi_id,
                'department_ids' => $source->departments->pluck('id')->all(),
                'official_preparer_id' => $user->id,
                'catatan_revisi' => 'Update content.',
                'tanggal_terbit' => '2026-08-29',
                'revision_content' => UploadedFile::fake()->createWithContent('revision-content.pdf', $this->pdfBinary(['Revision content'])),
                'revision_content_word' => UploadedFile::fake()->create('revision-content.docx', 24, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'),
                'revision_form' => UploadedFile::fake()->createWithContent('revision-form.pdf', $this->pdfBinary(['Revision form'])),
                'revision_form_word' => UploadedFile::fake()->create('revision-form.docx', 24, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'),
                'attachment_titles' => ['Matriks Komunikasi'],
                'attachment_orders' => [1],
                'attachments' => [
                    UploadedFile::fake()->createWithContent('matriks.pdf', $this->pdfBinary(['Matriks komunikasi'])),
                ],
            ])
            ->assertRedirect();

        $revision = Document::query()->where('nama_dokumen', 'Imported Revision Preview')->firstOrFail();
        $payload = app(FinalArtifactGenerator::class)
            ->prepareApprovalPreview($revision)
            ->payload;

        $this->assertSame(StatusDocument::PROPOSED, $revision->status->nama_status);
        $this->assertDatabaseHas('document_final_artifacts', [
            't_document_id' => $revision->id,
            'artifact_type' => DocumentFinalArtifact::TYPE_APPROVAL_PREVIEW,
            'generation_status' => DocumentFinalArtifact::STATUS_GENERATED,
        ]);
        $this->assertSame('Lembar Revisi', $payload['attachments'][0]['title']);
        $this->assertSame('Matriks Komunikasi', $payload['attachments'][1]['title']);
        $this->assertSame(2, $payload['attachments'][1]['number']);
    }

    /**
     * @return array{0: User, 1: BusinessProcess, 2: BusinessFunction, 3: Department}
     */
    private function submitFixture(): array
    {
        $base = $this->documentBase();
        $user = User::factory()->create([
            'email' => 'developer@example.com',
            'nik' => '000000',
            'm_department_id' => $base['department']->id,
        ]);

        return [$user, $base['businessProcess'], $base['businessFunction'], $base['department']];
    }

    private function proposedDocument(array $attributes = []): Document
    {
        return $this->createDocument(User::factory()->create(), $attributes);
    }

    /**
     * @return array{0: Document, 1: User}
     */
    private function documentWithTwoStageApprovals(): array
    {
        $document = $this->proposedDocument();
        $firstApprover = User::factory()->create();
        $secondApprover = User::factory()->create();
        $this->createFlow($document, ['Stage 1', 'Stage 2']);
        $this->createApproval($document, $firstApprover, ApprovalStatus::PENDING, 'Stage 1');
        $this->createApproval($document, $secondApprover, ApprovalStatus::WAITING, 'Stage 2');

        return [$document, $firstApprover];
    }

    /**
     * @return array{0: Document, 1: Document, 2: User}
     */
    private function revisionFixture(): array
    {
        $submitter = User::factory()->create();
        $approver = User::factory()->create();
        $source = $this->createDocument($submitter, [
            'm_status_document_id' => StatusDocument::findByName(StatusDocument::APPROVED)->id,
            'approved_at' => now()->subDay(),
            'nama_dokumen' => 'Source Master',
            'nomor_dokumen' => 'PS-SMR-PREV-REV',
        ]);
        $revision = $this->createDocument($submitter, [
            'm_document_level_id' => DocumentLevel::query()->where('kode', 'level-4')->value('id'),
            'revised_from' => $source->id,
            'request_type' => 'revision',
            'nama_dokumen' => 'Source Master Revision',
            'nomor_dokumen' => 'FMPS-SMR-PREV-REV-01',
            'nomor_revisi' => 1,
        ]);
        $revision->departments()->sync($source->departments()->pluck('departments.id')->all());
        $this->createFlow($source, ['Approval']);
        $this->createApproval($revision, $approver, ApprovalStatus::PENDING, 'Approval');

        return [$source, $revision, $approver];
    }

    /**
     * @return array{0: User, 1: Document}
     */
    private function importedExistingFixture(): array
    {
        [$user, $businessProcess, $businessFunction, $department] = $this->submitFixture();
        $base = $this->documentBase();
        $approvedStatus = StatusDocument::findByName(StatusDocument::APPROVED);
        $source = Document::query()->create([
            'origin' => Document::ORIGIN_IMPORTED_CURRENT,
            'm_status_document_id' => $approvedStatus->id,
            'm_document_level_id' => $base['level']->id,
            'm_document_types_id' => $base['type']->id,
            'm_proses_bisnis_id' => $businessProcess->id,
            'm_proses_fungsi_id' => $businessFunction->id,
            'user_id' => $user->id,
            'nama_dokumen' => 'Imported Existing Preview Source',
            'nomor_dokumen' => 'PS-SMR-IMP-PREV',
            'nomor_revisi' => '00.00',
            'tanggal_terbit' => now()->subYear()->toDateString(),
        ]);
        $source->departments()->sync([$department->id]);

        return [$user, $source];
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
            'nama_dokumen' => 'Dokumen Preview',
            'nomor_dokumen' => 'PS-SMR-PREVIEW',
            'nomor_revisi' => 0,
            'tanggal_terbit' => null,
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
            'businessProcess' => BusinessProcess::query()->firstOrCreate(['kode' => 'SMR'], ['nama_proses_bisnis' => 'Sistem Manajemen Risiko']),
            'businessFunction' => BusinessFunction::query()->firstOrCreate(['kode' => 'SMR'], ['nama_proses_fungsi' => 'Sistem Manajemen & Resiko']),
            'department' => Department::query()->firstOrCreate(['kode_department' => 'SMR'], ['nama_department' => 'System Management & Risk']),
        ];
    }

    /**
     * @param  array<int, string>  $stageNames
     */
    private function createFlow(Document $document, array $stageNames): ApprovalFlow
    {
        $flow = ApprovalFlow::query()->create([
            'm_document_level_id' => $document->m_document_level_id,
            'nama_flow' => 'Preview Flow '.$document->id,
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

    private function storeDocumentFile(Document $document, string $type, string $contents, array $attributes = []): DocumentFile
    {
        $path = $attributes['path_file'] ?? "documents/{$document->id}/{$type}.pdf";
        Storage::disk('local')->put($path, $contents);

        return DocumentFile::query()->create($attributes + [
            't_document_id' => $document->id,
            'type_file' => $type,
            'path_file' => $path,
            'uploaded_by' => $document->user_id,
            'original_file_name' => "{$type}.pdf",
            'stored_file_name' => "{$type}.pdf",
            'file_size' => strlen($contents),
        ]);
    }

    private function storeRawBodyPdf(string $contents): string
    {
        $path = tempnam(storage_path('app'), 'body-preview-test-');
        file_put_contents($path, $contents);

        return $path;
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(): array
    {
        return [
            'document' => [
                'name' => 'Dokumen Preview',
                'number' => 'PS-SMR-PREVIEW',
                'revision_label' => '00.00',
                'published_at' => null,
                'type' => 'Prosedur',
                'level' => ['document_name' => 'Prosedur'],
            ],
            'preparers' => [],
            'approvals' => [],
            'source' => [],
        ];
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

    private function pdfPageCount(string $binary): int
    {
        $path = tempnam(storage_path('app'), 'approval-preview-test-');
        file_put_contents($path, $binary);

        try {
            return (new Fpdi)->setSourceFile($path);
        } finally {
            @unlink($path);
        }
    }

    private function ensureStatuses(): void
    {
        foreach ([StatusDocument::DRAFT, StatusDocument::PROPOSED, StatusDocument::APPROVED, StatusDocument::REJECTED, StatusDocument::CANCELLED, StatusDocument::OBSOLETE] as $status) {
            StatusDocument::query()->firstOrCreate(['nama_status' => $status]);
        }

        foreach ([ApprovalStatus::PENDING, ApprovalStatus::WAITING, ApprovalStatus::APPROVED, ApprovalStatus::REJECTED, ApprovalStatus::TERMINATED] as $status) {
            ApprovalStatus::query()->firstOrCreate(['kode_status' => $status], ['nama_status' => $status]);
        }

        DocumentLevel::query()->firstOrCreate(
            ['kode' => 'level-4'],
            ['nama_level' => 'Level IV', 'nama_dokumen' => 'Form', 'prefix' => 'FM', 'is_active' => true, 'sort_order' => 4],
        );

        DocumentType::query()->firstOrCreate(
            ['nama_types' => 'Form'],
            ['is_active' => true],
        );
    }
}
