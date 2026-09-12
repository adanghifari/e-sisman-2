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
use App\Support\FinalDocuments\DocumentWatermarkStamp;
use App\Support\FinalDocuments\FinalDocumentArtifactGenerator;
use App\Support\FinalDocuments\FinalPdfComposer;
use App\Support\FinalDocuments\PdfCompositionException;
use App\Support\FinalDocuments\PdfCompositionMode;
use App\Support\FinalDocuments\PdfPageGeometry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use setasign\Fpdi\Tcpdf\Fpdi;
use TCPDF;
use Tests\TestCase;

class PdfCompositionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        $this->ensureStatuses();
    }

    public function test_preserve_mode_composes_cover_approval_and_body_without_touching_source(): void
    {
        $composer = app(FinalPdfComposer::class);
        $body = $this->storeBodyPdf($this->tcpdfBinary([
            ['orientation' => 'P', 'size' => [210, 297], 'text' => 'Body 1'],
            ['orientation' => 'P', 'size' => [210, 297], 'text' => 'Body 2'],
            ['orientation' => 'P', 'size' => [210, 297], 'text' => 'Body 3'],
        ]));
        $sourceChecksum = hash_file('sha256', $body);

        $result = $composer->compose(
            $this->payload(),
            $this->tcpdfBinary([['text' => 'Cover']]),
            $this->tcpdfBinary([['text' => 'Approval 1'], ['text' => 'Approval 2']]),
            $body,
            PdfCompositionMode::PRESERVE,
        );

        $this->assertStringStartsWith('%PDF-', $result->pdf);
        $this->assertSame(1, $result->coverPages);
        $this->assertSame(2, $result->approvalSheetPages);
        $this->assertSame(3, $result->bodyPagesCount);
        $this->assertSame(6, $result->totalPages());
        $this->assertSame(['1 dari 3', '2 dari 3', '3 dari 3'], array_column($result->bodyPages, 'page_label'));
        $this->assertSame('preserve', $result->bodyPages[0]['mode']);
        $this->assertSame(1.0, $result->bodyPages[0]['placement']['scale']);
        $this->assertSame($sourceChecksum, hash_file('sha256', $body));
        $this->assertSame(6, $this->pdfPageCount($result->pdf));
    }

    public function test_compose_with_download_watermark_stamp_renders_properly(): void
    {
        $composer = app(FinalPdfComposer::class);
        $body = $this->storeBodyPdf($this->tcpdfBinary([
            ['orientation' => 'P', 'size' => [210, 297], 'text' => 'Body Page Content'],
        ]));

        $payload = $this->payload();
        $payload['watermark_stamp'] = DocumentWatermarkStamp::forDownload(
            userName: 'ACHMAD FAUZI',
            downloadTime: '2026-09-04 11.30',
            downloadCount: 5,
        );

        $result = $composer->compose(
            $payload,
            $this->tcpdfBinary([['text' => 'Cover']]),
            $this->tcpdfBinary([['text' => 'Approval']]),
            $body,
            PdfCompositionMode::FIT_WIDTH_TO_SAFE_TOP,
        );

        $this->assertStringStartsWith('%PDF-', $result->pdf);
        $this->assertSame(3, $result->totalPages());
    }

    public function test_fit_to_safe_area_geometry_shrinks_without_crop_or_stretch(): void
    {
        $placement = app(PdfPageGeometry::class)->fitToSafeArea(210, 297, 210, 297, app(FinalPdfComposer::class)->safeArea());

        $this->assertLessThanOrEqual(1, $placement->scale);
        $this->assertEqualsWithDelta(23, $placement->y, 0.01);
        $this->assertGreaterThanOrEqual(9, $placement->x);
        $this->assertLessThanOrEqual(201, $placement->x + $placement->width);
        $this->assertLessThanOrEqual(287, $placement->y + $placement->height);
        $this->assertEqualsWithDelta(210 / 297, $placement->width / $placement->height, 0.001);
    }

    public function test_fit_to_safe_area_mode_composes_edge_content_with_safe_placement(): void
    {
        $body = $this->storeBodyPdf($this->tcpdfBinary([
            ['orientation' => 'P', 'size' => [210, 297], 'text' => 'Edge Body'],
        ], drawEdgeBox: true));
        $sourceChecksum = hash_file('sha256', $body);

        $result = app(FinalPdfComposer::class)->compose(
            $this->payload(),
            $this->tcpdfBinary([['text' => 'Cover']]),
            $this->tcpdfBinary([['text' => 'Approval']]),
            $body,
            PdfCompositionMode::FIT_TO_SAFE_AREA,
        );

        $placement = $result->bodyPages[0]['placement'];

        $this->assertStringStartsWith('%PDF-', $result->pdf);
        $this->assertSame('fit_to_safe_area', $result->bodyPages[0]['mode']);
        $this->assertLessThanOrEqual(1, $placement['scale']);
        $this->assertGreaterThanOrEqual(22.99, $placement['y']);
        $this->assertLessThanOrEqual(287.01, $placement['y'] + $placement['height']);
        $this->assertSame($sourceChecksum, hash_file('sha256', $body));
    }

    public function test_fit_width_to_safe_top_keeps_source_height_from_footer_scaling(): void
    {
        $placement = app(PdfPageGeometry::class)->fitWidthToSafeTop(210, 297, 210, 297, app(FinalPdfComposer::class)->safeArea());

        $this->assertEqualsWithDelta(23, $placement->y, 0.01);
        $this->assertEqualsWithDelta(9, $placement->x, 0.01);
        $this->assertEqualsWithDelta(192, $placement->width, 0.01);
        $this->assertGreaterThan(270, $placement->height);
        $this->assertGreaterThan(287, $placement->y + $placement->height);
    }

    public function test_preserve_mode_processes_mixed_page_sizes_per_page(): void
    {
        $body = $this->storeBodyPdf($this->tcpdfBinary([
            ['orientation' => 'P', 'size' => [210, 297], 'text' => 'Portrait A4'],
            ['orientation' => 'L', 'size' => [210, 297], 'text' => 'Landscape A4'],
            ['orientation' => 'P', 'size' => [148, 210], 'text' => 'Portrait A5'],
        ]));

        $result = app(FinalPdfComposer::class)->compose(
            $this->payload(),
            $this->tcpdfBinary([['text' => 'Cover']]),
            $this->tcpdfBinary([['text' => 'Approval']]),
            $body,
            PdfCompositionMode::PRESERVE,
        );

        $this->assertSame('P', $result->bodyPages[0]['orientation']);
        $this->assertEqualsWithDelta(210, $result->bodyPages[0]['page_width'], 0.5);
        $this->assertSame('L', $result->bodyPages[1]['orientation']);
        $this->assertEqualsWithDelta(297, $result->bodyPages[1]['page_width'], 0.5);
        $this->assertSame('P', $result->bodyPages[2]['orientation']);
        $this->assertEqualsWithDelta(148, $result->bodyPages[2]['page_width'], 0.5);
    }

    public function test_composition_adds_attachment_list_and_merges_attachment_pdfs(): void
    {
        $body = $this->storeBodyPdf($this->tcpdfBinary([
            ['text' => 'Body 1'],
            ['text' => 'Body 2'],
        ]));
        Storage::disk('local')->put('documents/10/attachment-a.pdf', $this->tcpdfBinary([
            ['text' => 'Attachment A'],
        ]));
        Storage::disk('local')->put('documents/10/attachment-b.pdf', $this->tcpdfBinary([
            ['text' => 'Attachment B1'],
            ['text' => 'Attachment B2'],
        ]));

        $result = app(FinalPdfComposer::class)->compose(
            $this->payload([
                [
                    'number' => 1,
                    'title' => 'Barcode pengisian daftar hadir safety induction',
                    'document_number' => 'FMIK-HMK-01-05-02',
                    'path_file' => 'documents/10/attachment-a.pdf',
                ],
                [
                    'number' => 2,
                    'title' => 'Sticker safety induction untuk pekerja proyek',
                    'document_number' => 'FMIK-HMK-01-05-03',
                    'path_file' => 'documents/10/attachment-b.pdf',
                ],
            ]),
            $this->tcpdfBinary([['text' => 'Cover']]),
            $this->tcpdfBinary([['text' => 'Approval']]),
            $body,
            PdfCompositionMode::PRESERVE,
        );

        $this->assertSame(8, $result->totalPages());
        $this->assertSame(6, $result->bodyPagesCount);
        $this->assertSame('generated_attachment_list', $result->bodyPages[2]['mode']);
        $this->assertSame('attachment', $result->bodyPages[3]['mode']);
        $this->assertSame('1 dari 2', $result->bodyPages[0]['page_label']);
        $this->assertSame('attachment_form', $result->bodyPages[3]['header']);
        $this->assertSame('1 dari 1', $result->bodyPages[3]['header_page_label']);
        $this->assertSame('attachment_form', $result->bodyPages[4]['header']);
        $this->assertSame('1 dari 2', $result->bodyPages[4]['header_page_label']);
        $this->assertSame('2 dari 2', $result->bodyPages[5]['header_page_label']);
        $this->assertSame(8, $this->pdfPageCount($result->pdf));
    }

    public function test_invalid_attachment_pdf_gets_fallback_page_without_failing_composition(): void
    {
        $body = $this->storeBodyPdf($this->tcpdfBinary([
            ['text' => 'Body 1'],
        ]));
        Storage::disk('local')->put('documents/10/valid-attachment.pdf', $this->tcpdfBinary([
            ['text' => 'Attachment A'],
        ]));
        Storage::disk('local')->put('documents/10/broken-attachment.pdf', '%PDF-1.4 invalid attachment');

        $result = app(FinalPdfComposer::class)->compose(
            $this->payload([
                [
                    'number' => 1,
                    'title' => 'Lampiran Valid',
                    'document_number' => 'FMIK-HMK-01-05-02',
                    'path_file' => 'documents/10/valid-attachment.pdf',
                    'original_file_name' => 'valid-attachment.pdf',
                ],
                [
                    'number' => 2,
                    'title' => 'Lampiran Rusak',
                    'document_number' => 'FMIK-HMK-01-05-03',
                    'path_file' => 'documents/10/broken-attachment.pdf',
                    'original_file_name' => 'broken-attachment.pdf',
                ],
            ]),
            $this->tcpdfBinary([['text' => 'Cover']]),
            $this->tcpdfBinary([['text' => 'Approval']]),
            $body,
            PdfCompositionMode::PRESERVE,
        );

        $this->assertSame(4, $result->bodyPagesCount);
        $this->assertSame(6, $this->pdfPageCount($result->pdf));
        $this->assertSame('Lampiran Valid', $result->bodyPages[2]['attachment_title']);
        $this->assertSame('attachment_fallback', $result->bodyPages[3]['mode']);
        $this->assertSame('attachment_form', $result->bodyPages[3]['header']);
        $this->assertSame('1 dari 1', $result->bodyPages[3]['header_page_label']);
        $this->assertSame('Lampiran Rusak', $result->bodyPages[3]['attachment_title']);
    }

    public function test_revision_form_attachment_uses_revision_form_header(): void
    {
        $body = $this->storeBodyPdf($this->tcpdfBinary([
            ['text' => 'Body 1'],
        ]));
        Storage::disk('local')->put('documents/10/revision-form.pdf', $this->tcpdfBinary([
            ['text' => 'Revision form 1'],
            ['text' => 'Revision form 2'],
        ]));
        Storage::disk('local')->put('documents/10/attachment.pdf', $this->tcpdfBinary([
            ['text' => 'Attachment'],
        ]));

        $result = app(FinalPdfComposer::class)->compose(
            $this->payload(
                [
                    [
                        'number' => 1,
                        'title' => 'Lembar Revisi',
                        'type' => 'revision_form',
                        'path_file' => 'documents/10/revision-form.pdf',
                    ],
                    [
                        'number' => 2,
                        'title' => 'Lampiran Pendukung',
                        'document_number' => 'FMIK-OPS-01-01-09',
                        'type' => 'attachment',
                        'path_file' => 'documents/10/attachment.pdf',
                    ],
                ],
                [
                    'revision_approvals' => [
                        [
                            'stage_name' => 'Pengesahan Revisi',
                            'stage_order' => 1,
                            'approvers' => [
                                [
                                    'approval_id' => 123,
                                    'name' => 'Approver Revisi',
                                    'position' => 'Manager Pemilik Proses',
                                    'responded_at' => '2026-10-16 09:00:00',
                                ],
                            ],
                        ],
                    ],
                ],
            ),
            $this->tcpdfBinary([['text' => 'Cover']]),
            $this->tcpdfBinary([['text' => 'Approval']]),
            $body,
            PdfCompositionMode::PRESERVE,
        );

        $this->assertSame('generated_attachment_list', $result->bodyPages[1]['mode']);
        $this->assertSame([
            'Lampiran 1. Form Lembar Revisi (FMIK-OPS-01-01-08)',
            'Lampiran 2. Lampiran Pendukung (FMIK-OPS-01-01-09)',
        ], $result->bodyPages[1]['attachment_titles']);
        $this->assertSame('revision_form', $result->bodyPages[2]['header']);
        $this->assertSame('1 dari 2', $result->bodyPages[2]['header_page_label']);
        $this->assertFalse($result->bodyPages[2]['revision_approval_stamp']);
        $this->assertSame('revision_form', $result->bodyPages[3]['header']);
        $this->assertSame('2 dari 2', $result->bodyPages[3]['header_page_label']);
        $this->assertTrue($result->bodyPages[3]['revision_approval_stamp']);
        $this->assertSame('attachment_form', $result->bodyPages[4]['header']);
        $this->assertSame('1 dari 1', $result->bodyPages[4]['header_page_label']);
    }

    public function test_revision_approval_moves_to_next_page_when_revision_form_content_is_too_low(): void
    {
        $body = $this->storeBodyPdf($this->tcpdfBinary([
            ['text' => 'Body 1'],
        ]));
        Storage::disk('local')->put('documents/10/revision-form-low.pdf', $this->tcpdfBinary([
            ['text' => 'Revision form low content', 'y' => 292],
        ]));

        $result = app(FinalPdfComposer::class)->compose(
            $this->payload(
                [
                    [
                        'number' => 1,
                        'title' => 'Lembar Revisi',
                        'type' => 'revision_form',
                        'path_file' => 'documents/10/revision-form-low.pdf',
                    ],
                ],
                [
                    'revision_approvals' => [
                        [
                            'stage_name' => 'Pengesahan Revisi',
                            'stage_order' => 1,
                            'approvers' => [
                                [
                                    'approval_id' => 123,
                                    'name' => 'Approver Revisi',
                                    'position' => 'Manager Pemilik Proses',
                                    'responded_at' => '2026-10-16 09:00:00',
                                ],
                            ],
                        ],
                    ],
                ],
            ),
            $this->tcpdfBinary([['text' => 'Cover']]),
            $this->tcpdfBinary([['text' => 'Approval']]),
            $body,
            PdfCompositionMode::PRESERVE,
        );

        $this->assertSame(4, $result->bodyPagesCount);
        $this->assertFalse($result->bodyPages[2]['revision_approval_stamp']);
        $this->assertSame('revision_approval', $result->bodyPages[3]['mode']);
        $this->assertSame('2 dari 2', $result->bodyPages[3]['header_page_label']);
        $this->assertSame('4 dari 4', $result->bodyPages[3]['page_label']);
    }

    public function test_final_document_artifact_generator_includes_attachment_pdfs(): void
    {
        $document = $this->approvedDocument();
        $this->createDocumentFile($document, $this->tcpdfBinary([
            ['text' => 'Body One'],
        ]));
        $this->createAttachmentFile($document, 'Lampiran Safety', $this->tcpdfBinary([
            ['text' => 'Attachment One'],
        ]));
        $generatorUser = User::factory()->create();

        $artifact = app(FinalDocumentArtifactGenerator::class)
            ->generate($document, $generatorUser, PdfCompositionMode::PRESERVE);

        $this->assertSame(DocumentFinalArtifact::STATUS_GENERATED, $artifact->generation_status);
        $this->assertSame(5, $this->pdfPageCount(Storage::disk('local')->get($artifact->path_file)));
    }

    public function test_missing_source_throws_controlled_exception(): void
    {
        $this->expectException(PdfCompositionException::class);
        $this->expectExceptionMessage('Source body PDF is missing.');

        app(FinalPdfComposer::class)->compose(
            $this->payload(),
            $this->tcpdfBinary([['text' => 'Cover']]),
            $this->tcpdfBinary([['text' => 'Approval']]),
            Storage::disk('local')->path('documents/missing.pdf'),
            PdfCompositionMode::PRESERVE,
        );
    }

    public function test_corrupt_source_fails_with_controlled_exception_and_cleans_temp_files(): void
    {
        $tempDirectory = storage_path('app/private/documents/final/tmp');
        if (is_dir($tempDirectory)) {
            File::cleanDirectory($tempDirectory);
        }

        $body = $this->storeBodyPdf('not a pdf');

        try {
            app(FinalPdfComposer::class)->compose(
                $this->payload(),
                $this->tcpdfBinary([['text' => 'Cover']]),
                $this->tcpdfBinary([['text' => 'Approval']]),
                $body,
                PdfCompositionMode::PRESERVE,
            );
            $this->fail('Expected PDF composition exception.');
        } catch (PdfCompositionException $exception) {
            $this->assertStringContainsString('PDF composition failed', $exception->getMessage());
        }

        $this->assertSame([], glob($tempDirectory.'/*.pdf') ?: []);
    }

    public function test_final_document_artifact_generator_writes_private_generated_artifact(): void
    {
        $document = $this->approvedDocument();
        $sourceFile = $this->createDocumentFile($document, $this->tcpdfBinary([
            ['text' => 'Body One'],
            ['text' => 'Body Two'],
        ]));
        $sourceChecksum = hash('sha256', Storage::disk('local')->get($sourceFile->path_file));
        $generatorUser = User::factory()->create();
        $this->createApproval($document, User::factory()->create(), [
            'stage_name_snapshot' => 'Disetujui Oleh',
            'stage_order_snapshot' => 1,
            'approver_name_snapshot' => 'Approver Snapshot',
            'approver_position_snapshot' => 'Manager',
        ]);

        $artifact = app(FinalDocumentArtifactGenerator::class)
            ->generate($document, $generatorUser, PdfCompositionMode::PRESERVE);

        $this->assertSame(DocumentFinalArtifact::TYPE_FINAL_DOCUMENT, $artifact->artifact_type);
        $this->assertSame(DocumentFinalArtifact::STATUS_GENERATED, $artifact->generation_status);
        $this->assertSame($sourceFile->id, $artifact->source_document_file_id);
        $this->assertSame($generatorUser->id, $artifact->generated_by);
        $this->assertStringStartsWith("documents/final/{$document->id}/final_document/1/final-", $artifact->path_file);
        $this->assertNotNull($artifact->checksum_sha256);
        $this->assertGreaterThan(1000, $artifact->file_size);
        Storage::disk('local')->assertExists($artifact->path_file);
        $this->assertStringStartsWith('%PDF-', Storage::disk('local')->get($artifact->path_file));
        $this->assertSame($sourceChecksum, hash('sha256', Storage::disk('local')->get($sourceFile->path_file)));
    }

    public function test_final_document_artifact_generator_marks_failed_when_composition_fails(): void
    {
        $document = $this->approvedDocument();
        $this->createDocumentFile($document, 'not a pdf');

        try {
            app(FinalDocumentArtifactGenerator::class)->generate($document);
            $this->fail('Expected PDF composition exception.');
        } catch (PdfCompositionException) {
            $artifact = DocumentFinalArtifact::query()->firstOrFail();

            $this->assertSame(DocumentFinalArtifact::STATUS_FAILED, $artifact->generation_status);
            $this->assertNotNull($artifact->generation_error);
            Storage::disk('local')->assertMissing($artifact->path_file);
        }
    }

    /**
     * @param  array<int, array{orientation?: string, size?: array{0: float|int, 1: float|int}, text: string, y?: float|int}>  $pages
     */
    private function tcpdfBinary(array $pages, bool $drawEdgeBox = false): string
    {
        $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->setAutoPageBreak(false, 0);
        $pdf->setMargins(0, 0, 0);
        $pdf->setCompression(false);

        foreach ($pages as $page) {
            $pdf->AddPage($page['orientation'] ?? 'P', $page['size'] ?? [210, 297]);
            $pdf->SetFont('helvetica', '', 12);
            $pdf->Text(10, $page['y'] ?? 10, $page['text']);

            if ($drawEdgeBox) {
                $pdf->Rect(1, 1, $pdf->getPageWidth() - 2, $pdf->getPageHeight() - 2);
            }
        }

        return $pdf->Output('', 'S');
    }

    private function storeBodyPdf(string $contents): string
    {
        Storage::disk('local')->put('documents/body.pdf', $contents);

        return Storage::disk('local')->path('documents/body.pdf');
    }

    private function pdfPageCount(string $binary): int
    {
        $path = tempnam(storage_path('app'), 'final-pdf-test-');
        file_put_contents($path, $binary);

        try {
            return (new Fpdi)->setSourceFile($path);
        } finally {
            @unlink($path);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(array $attachments = [], array $overrides = []): array
    {
        return array_replace_recursive([
            'document' => [
                'id' => 10,
                'name' => 'Komunikasi, Konsultasi dan Partisipasi',
                'number' => 'IK - HMK - 01 - 05',
                'revision_form_number' => 'FMIK-OPS-01-01-08',
                'revision' => 0,
                'revision_label' => '00.00',
                'published_at' => '2026-08-27',
                'type' => 'Instruksi Kerja',
                'level' => [
                    'id' => 3,
                    'code' => 'level-3',
                    'name' => 'Level III',
                    'document_name' => 'Instruksi Kerja',
                ],
            ],
            'preparers' => [],
            'approvals' => [],
            'revision_approvals' => [],
            'source' => [],
            'attachments' => $attachments,
        ], $overrides);
    }

    private function approvedDocument(): Document
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
        $preparerDepartment = Department::query()->firstOrCreate(
            ['kode_department' => 'QA'],
            ['nama_department' => 'Quality Assurance'],
        );
        $officialPreparer = User::factory()->create([
            'name' => 'Official Preparer',
            'jabatan' => 'Management System Specialist',
            'm_department_id' => $preparerDepartment->id,
        ]);

        $document = Document::query()->create([
            'm_document_level_id' => $level->id,
            'm_status_document_id' => StatusDocument::query()->where('nama_status', StatusDocument::APPROVED)->value('id'),
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
            'official_preparer_id' => $officialPreparer->id,
            'official_preparer_name_snapshot' => 'Official Preparer',
            'official_preparer_position_snapshot' => 'Management System Specialist',
            'official_preparer_department_snapshot' => 'Quality Assurance',
            'nama_dokumen' => 'Dokumen Final',
            'nomor_dokumen' => 'PS-SMR-FINAL',
            'nomor_revisi' => 0,
            'tanggal_terbit' => now()->toDateString(),
            'approved_at' => now(),
            'submitted_at' => now(),
        ]);
        $document->departments()->sync([$department->id]);

        return $document;
    }

    private function createDocumentFile(Document $document, string $contents): DocumentFile
    {
        $path = "documents/{$document->id}/filled_template.pdf";
        Storage::disk('local')->put($path, $contents);

        return DocumentFile::query()->create([
            't_document_id' => $document->id,
            'type_file' => 'filled_template',
            'path_file' => $path,
            'uploaded_by' => $document->user_id,
            'updated_at' => now(),
            'original_file_name' => 'filled_template.pdf',
            'stored_file_name' => 'filled_template.pdf',
            'file_size' => strlen($contents),
        ]);
    }

    private function createAttachmentFile(Document $document, string $title, string $contents): DocumentFile
    {
        $path = "documents/{$document->id}/attachment-".Str::slug($title).'.pdf';
        Storage::disk('local')->put($path, $contents);

        return DocumentFile::query()->create([
            't_document_id' => $document->id,
            'type_file' => 'attachment',
            'attachment_title' => $title,
            'path_file' => $path,
            'uploaded_by' => $document->user_id,
            'updated_at' => now(),
            'original_file_name' => basename($path),
            'stored_file_name' => basename($path),
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
