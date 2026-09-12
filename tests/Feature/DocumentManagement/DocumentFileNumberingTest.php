<?php

namespace Tests\Feature\DocumentManagement;

use App\Models\BusinessFunction;
use App\Models\BusinessProcess;
use App\Models\Document;
use App\Models\DocumentLevel;
use App\Models\DocumentType;
use App\Models\StatusDocument;
use App\Models\User;
use App\Support\DocumentFiles\DocumentFileNumbering;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DocumentFileNumberingTest extends TestCase
{
    use RefreshDatabase;

    public function test_document_file_numbers_are_assigned_from_parent_family(): void
    {
        $document = $this->document('IK-KMS-01-02');

        $mainFile = $this->file($document, 'filled_template');
        $secondDisplayAttachment = $this->file($document, 'attachment', 'Sketsa', 2);
        $firstDisplayAttachment = $this->file($document, 'attachment', 'Invoice', 1);
        $revisionForm = $this->file($document, 'revision_form');

        app(DocumentFileNumbering::class)->assignMissingNumbers($document);

        $this->assertSame('IK-KMS-01-02', $mainFile->refresh()->document_number);
        $this->assertSame('FMIK-KMS-01-02-01', $revisionForm->refresh()->document_number);
        $this->assertSame('FMIK-KMS-01-02-02', $firstDisplayAttachment->refresh()->document_number);
        $this->assertSame('FMIK-KMS-01-02-03', $secondDisplayAttachment->refresh()->document_number);

        $firstDisplayAttachment->update(['attachment_order' => 9]);
        $secondDisplayAttachment->update(['attachment_order' => 1]);

        app(DocumentFileNumbering::class)->assignMissingNumbers($document->refresh());

        $this->assertSame('FMIK-KMS-01-02-02', $firstDisplayAttachment->refresh()->document_number);
        $this->assertSame('FMIK-KMS-01-02-03', $secondDisplayAttachment->refresh()->document_number);
    }

    public function test_revision_form_number_stays_fixed_across_revisions(): void
    {
        $source = $this->document('IK-KMS-01-02', 0);
        $firstRevision = $this->document('IK-KMS-01-02', 1, $source);
        $secondRevision = $this->document('IK-KMS-01-02', 2, $source);

        $numbering = app(DocumentFileNumbering::class);

        $this->assertSame('FMIK-KMS-01-02-01', $numbering->revisionFormNumber($firstRevision));
        $this->assertSame('FMIK-KMS-01-02-01', $numbering->revisionFormNumber($secondRevision));
    }

    public function test_attachment_numbers_do_not_use_revision_form_suffix_when_revision_form_is_missing(): void
    {
        $document = $this->document('IK-KMS-01-02');
        $firstAttachment = $this->file($document, 'attachment', 'Lampiran A', 1);
        $secondAttachment = $this->file($document, 'attachment', 'Lampiran B', 2);

        $numbering = app(DocumentFileNumbering::class);

        $numbering->assignMissingNumbers($document);
        $numbering->compactActiveAttachmentNumbers($document);

        $this->assertSame('FMIK-KMS-01-02-02', $firstAttachment->refresh()->document_number);
        $this->assertSame('FMIK-KMS-01-02-03', $secondAttachment->refresh()->document_number);

        $firstAttachment->update(['attachment_order' => 2]);
        $secondAttachment->update(['attachment_order' => 1]);

        $numbering->compactActiveAttachmentNumbers($document);

        $this->assertSame('FMIK-KMS-01-02-03', $firstAttachment->refresh()->document_number);
        $this->assertSame('FMIK-KMS-01-02-02', $secondAttachment->refresh()->document_number);
    }

    private function document(string $number, int $revision = 0, ?Document $source = null): Document
    {
        $level = DocumentLevel::query()->firstOrCreate(
            ['kode' => 'level-3'],
            [
                'nama_level' => 'Level III',
                'nama_dokumen' => 'Dokumen Level III : Instruksi Kerja',
                'prefix' => 'IK',
                'description' => 'Instruksi kerja',
                'sort_order' => 3,
                'is_active' => true,
            ],
        );
        $status = StatusDocument::query()->firstOrCreate(['nama_status' => StatusDocument::APPROVED]);
        $type = DocumentType::query()->firstOrCreate(['nama_types' => 'IK']);
        $process = BusinessProcess::query()->firstOrCreate([
            'kode' => 'KMS',
            'nama_proses_bisnis' => 'Knowledge Management System',
        ]);
        $function = BusinessFunction::query()->firstOrCreate([
            'kode' => 'KMS',
            'nama_proses_fungsi' => 'Knowledge Management System',
        ]);
        $user = User::factory()->create();

        return Document::query()->create([
            'm_document_level_id' => $level->id,
            'm_status_document_id' => $status->id,
            'm_document_types_id' => $type->id,
            'm_proses_bisnis_id' => $process->id,
            'm_proses_fungsi_id' => $function->id,
            'user_id' => $user->id,
            'revised_from' => $source?->id,
            'request_type' => $source ? 'revision' : null,
            'nama_dokumen' => 'Instruksi Kerja KMS',
            'nomor_dokumen' => $number,
            'nomor_revisi' => $revision,
            'created_at' => now(),
            'submitted_at' => now(),
            'approved_at' => now(),
        ]);
    }

    private function file(Document $document, string $type, ?string $title = null, ?int $order = null)
    {
        return $document->files()->create([
            'type_file' => $type,
            'attachment_title' => $title,
            'attachment_order' => $order,
            'path_file' => 'documents/testing/'.$type.'.pdf',
            'uploaded_by' => $document->user_id,
            'updated_at' => now(),
            'original_file_name' => $type.'.pdf',
            'stored_file_name' => $type.'.pdf',
            'file_size' => 100,
        ]);
    }
}
