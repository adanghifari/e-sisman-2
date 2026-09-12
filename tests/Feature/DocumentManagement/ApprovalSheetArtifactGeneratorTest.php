<?php

namespace Tests\Feature\DocumentManagement;

use App\Models\Approval;
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
use App\Support\FinalDocuments\ApprovalSheetArtifactGenerator;
use App\Support\FinalDocuments\ApprovalSheetPdfRenderer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

class ApprovalSheetArtifactGeneratorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ensureStatuses();
        Storage::fake('local');
    }

    public function test_it_generates_approval_sheet_pdf_artifact_without_changing_source_file(): void
    {
        $generatorUser = User::factory()->create();
        $document = $this->createApprovedDocument();
        $sourceFile = $this->createDocumentFile($document, 'filled_template', 'original source content');
        $this->createApproval($document, User::factory()->create(), [
            'stage_name_snapshot' => 'Disusun Oleh',
            'stage_order_snapshot' => 1,
            'approver_name_snapshot' => 'Snapshot Person',
            'approver_position_snapshot' => 'Management System Specialist',
        ]);

        $artifact = app(ApprovalSheetArtifactGenerator::class)->generate($document, $generatorUser);

        $this->assertSame(DocumentFinalArtifact::STATUS_GENERATED, $artifact->generation_status);
        $this->assertSame($document->id, $artifact->t_document_id);
        $this->assertSame($sourceFile->id, $artifact->source_document_file_id);
        $this->assertSame(DocumentFinalArtifact::TYPE_APPROVAL_SHEET, $artifact->artifact_type);
        $this->assertSame($generatorUser->id, $artifact->generated_by);
        $this->assertNotNull($artifact->generated_at);
        $this->assertNotNull($artifact->checksum_sha256);
        $this->assertGreaterThan(1000, $artifact->file_size);
        $this->assertStringStartsWith("documents/final/{$document->id}/approval_sheet/1/approval-sheet-", $artifact->path_file);
        Storage::disk('local')->assertExists($artifact->path_file);
        $this->assertStringStartsWith('%PDF-', Storage::disk('local')->get($artifact->path_file));
        $this->assertSame('original source content', Storage::disk('local')->get($sourceFile->path_file));
    }

    public function test_regeneration_creates_new_immutable_artifact_path(): void
    {
        $document = $this->createApprovedDocument();
        $this->createDocumentFile($document, 'filled_template');
        $this->createApproval($document, User::factory()->create(), [
            'stage_name_snapshot' => 'Diperiksa Oleh',
            'stage_order_snapshot' => 1,
            'approver_name_snapshot' => 'First Approver',
            'approver_position_snapshot' => 'Reviewer',
        ]);

        $firstArtifact = app(ApprovalSheetArtifactGenerator::class)->generate($document);
        $secondArtifact = app(ApprovalSheetArtifactGenerator::class)->generate($document);

        $this->assertSame(1, $firstArtifact->generation_number);
        $this->assertSame(2, $secondArtifact->generation_number);
        $this->assertNotSame($firstArtifact->path_file, $secondArtifact->path_file);
        Storage::disk('local')->assertExists($firstArtifact->path_file);
        Storage::disk('local')->assertExists($secondArtifact->path_file);
    }

    public function test_failed_render_marks_artifact_failed_without_writing_pdf(): void
    {
        $document = $this->createApprovedDocument();
        $this->createDocumentFile($document, 'filled_template');
        $this->createApproval($document, User::factory()->create(), [
            'stage_name_snapshot' => 'Disetujui Oleh',
            'stage_order_snapshot' => 1,
            'approver_name_snapshot' => 'Approver',
            'approver_position_snapshot' => 'Manager',
        ]);

        $this->mock(ApprovalSheetPdfRenderer::class, function ($mock): void {
            $mock->shouldReceive('render')
                ->once()
                ->andThrow(new RuntimeException('Renderer failed'));
        });

        try {
            app(ApprovalSheetArtifactGenerator::class)->generate($document);
            $this->fail('Expected renderer exception was not thrown.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Renderer failed', $exception->getMessage());
        }

        $artifact = DocumentFinalArtifact::query()->firstOrFail();

        $this->assertSame(DocumentFinalArtifact::STATUS_FAILED, $artifact->generation_status);
        $this->assertSame('Renderer failed', $artifact->generation_error);
        Storage::disk('local')->assertMissing($artifact->path_file);
    }

    private function createApprovedDocument(): Document
    {
        $submitter = User::factory()->create();
        $department = Department::query()->firstOrCreate(
            ['kode_department' => 'SMR'],
            ['nama_department' => 'System Management & Risk'],
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
            'm_status_document_id' => StatusDocument::query()->where('nama_status', StatusDocument::APPROVED)->value('id'),
            'm_document_types_id' => $documentType->id,
            'm_proses_bisnis_id' => BusinessProcess::query()->create([
                'kode' => 'Utama',
                'nama_proses_bisnis' => 'Proses Inti / Utama',
            ])->id,
            'm_proses_fungsi_id' => BusinessFunction::query()->create([
                'kode' => 'SMR',
                'nama_proses_fungsi' => 'Sistem Manajemen & Resiko',
            ])->id,
            'user_id' => $submitter->id,
            'official_preparer_id' => $submitter->id,
            'nama_dokumen' => 'Dokumen Lembar Pengesahan',
            'nomor_dokumen' => 'PS-SMR-APPROVAL-SHEET',
            'nomor_revisi' => 0,
            'tanggal_terbit' => now()->toDateString(),
            'submitted_at' => now(),
            'approved_at' => now(),
        ]);
        $document->departments()->sync([$department->id]);

        return $document;
    }

    private function createDocumentFile(Document $document, string $type, string $contents = 'pdf source'): DocumentFile
    {
        $path = "documents/{$document->id}/{$type}.pdf";
        Storage::disk('local')->put($path, $contents);

        return DocumentFile::query()->create([
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
