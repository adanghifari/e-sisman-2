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
use App\Models\DocumentDownloadLog;
use App\Models\DocumentFile;
use App\Models\DocumentLevel;
use App\Models\DocumentRelation;
use App\Models\DocumentType;
use App\Models\Permission;
use App\Models\Role;
use App\Models\StatusDocument;
use App\Models\User;
use App\Support\FinalDocuments\DynamicFinalDocumentRenderer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DocumentMasterTest extends TestCase
{
    use RefreshDatabase;

    public function test_master_page_shows_only_approved_documents(): void
    {
        $user = $this->userWithPermission('documents.master.view');
        $approvedStatus = StatusDocument::create(['nama_status' => StatusDocument::APPROVED]);
        $proposedStatus = StatusDocument::create(['nama_status' => StatusDocument::PROPOSED]);

        $this->createDocument($user, $approvedStatus, [
            'nama_dokumen' => 'Dokumen Master Approved',
            'nomor_dokumen' => 'PS-SMR-APP',
        ]);
        $this->createDocument($user, $proposedStatus, [
            'nama_dokumen' => 'Dokumen Masih Proposed',
            'nomor_dokumen' => 'PS-SMR-PROP',
        ]);

        $this->actingAs($user)
            ->get(route('documents.master'))
            ->assertOk()
            ->assertSee('Dokumen Master Approved')
            ->assertSee('PS-SMR-APP')
            ->assertSee('Master')
            ->assertDontSee('Dokumen Masih Proposed')
            ->assertDontSee('PS-SMR-PROP');
    }

    public function test_master_page_shows_import_master_button_for_user_with_permission(): void
    {
        $user = $this->userWithPermission('documents.master.imports.create');

        $this->actingAs($user)
            ->get(route('documents.master'))
            ->assertOk()
            ->assertSee('Import Dokumen Master')
            ->assertSee(route('documents.master.imports.create'), false);
    }

    public function test_master_page_hides_import_master_button_without_permission(): void
    {
        $user = $this->userWithPermission('documents.master.view');

        $this->actingAs($user)
            ->get(route('documents.master'))
            ->assertOk()
            ->assertDontSee('Import Dokumen Master');
    }

    public function test_imported_existing_master_appears_in_master_page_and_search(): void
    {
        $user = $this->userWithPermission('documents.master.view');
        $approvedStatus = StatusDocument::create(['nama_status' => StatusDocument::APPROVED]);
        [$level, $documentType, $businessProcess, $businessFunction] = $this->masterMetadata();

        $this->createDocument($user, $approvedStatus, [
            'nama_dokumen' => 'Workflow Master Normal',
            'nomor_dokumen' => 'PS-SMR-WORKFLOW',
        ]);

        $importedMaster = $this->createImportedExistingMaster($user, [
            'm_document_level_id' => $level->id,
            'm_document_types_id' => $documentType->id,
            'm_proses_bisnis_id' => $businessProcess->id,
            'm_proses_fungsi_id' => $businessFunction->id,
            'nama_dokumen' => 'Imported Master Go Live',
            'nomor_dokumen' => 'PS-SMR-IMPORT',
            'nomor_revisi' => '00.00',
        ]);

        $this->actingAs($user)
            ->get(route('documents.master'))
            ->assertOk()
            ->assertSee('Workflow Master Normal')
            ->assertSee('Imported Master Go Live')
            ->assertSee('PS-SMR-IMPORT')
            ->assertSee('Master')
            ->assertSee(route('documents.master.imported.show', $importedMaster), false);

        $this->actingAs($user)
            ->get(route('documents.master', ['search' => 'Go Live']))
            ->assertOk()
            ->assertSee('Imported Master Go Live')
            ->assertDontSee('Workflow Master Normal');
    }

    public function test_imported_existing_master_uses_master_detail_layout_and_imported_file_routes(): void
    {
        Storage::fake('local');

        $user = $this->userWithPermission('documents.master.imported.detail');
        [$level, $documentType, $businessProcess, $businessFunction] = $this->masterMetadata();
        $importedMaster = $this->createImportedExistingMaster($user, [
            'm_document_level_id' => $level->id,
            'm_document_types_id' => $documentType->id,
            'm_proses_bisnis_id' => $businessProcess->id,
            'm_proses_fungsi_id' => $businessFunction->id,
            'nama_dokumen' => 'Imported Master Detail',
            'nomor_dokumen' => 'PS-SMR-IMD',
            'catatan' => 'Dokumen hasil import arsip sebelum go-live.',
        ]);
        Storage::disk('local')->put('documents/imported-existing/master-detail.pdf', "%PDF-1.4\nfixture");
        $file = $importedMaster->files()->create([
            'type_file' => DocumentFile::TYPE_IMPORTED_DOCUMENT,
            'path_file' => 'documents/imported-existing/master-detail.pdf',
            'uploaded_by' => $user->id,
            'original_file_name' => 'master-detail.pdf',
            'stored_file_name' => 'master-detail.pdf',
            'file_size' => 16,
        ]);
        $importedMaster->files()->create([
            'type_file' => DocumentFile::TYPE_ATTACHMENT,
            'path_file' => 'documents/imported-existing/master-detail.pdf',
            'uploaded_by' => $user->id,
            'original_file_name' => 'lampiran-master-detail.pdf',
            'stored_file_name' => 'lampiran-master-detail.pdf',
            'file_size' => 16,
        ]);

        $this->actingAs($user)
            ->get(route('documents.master.imported.show', $importedMaster))
            ->assertOk()
            ->assertSee('Detail Dokumen Master')
            ->assertSee('Imported Master Detail')
            ->assertSee('PS-SMR-IMD')
            ->assertSee('Imported')
            ->assertSee('Catatan Import')
            ->assertSee('Dokumen hasil import arsip sebelum go-live.')
            ->assertDontSee('Dokumen ini berasal dari imported existing master sebelum go-live')
            ->assertSee(route('documents.existing.imports.files.show', [$importedMaster, $file]), false)
            ->assertSee(route('documents.existing.imports.files.preview', [$importedMaster, $file]), false)
            ->assertSee('Download Printout PDF')
            ->assertDontSee('lampiran-master-detail.pdf')
            ->assertSee('Ajukan Revisi')
            ->assertSee(route('documents.create.level', ['level-4', 'imported_source' => $importedMaster->id]), false);

        $this->actingAs($user)
            ->get(route('documents.existing.imports.files.preview', [$importedMaster, $file]))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    public function test_imported_existing_master_can_be_obsoleted_from_master_detail(): void
    {
        Storage::fake('local');

        $user = $this->userWithPermission('documents.master.imported.detail');
        $importedMaster = $this->createImportedExistingMaster($user, [
            'nama_dokumen' => 'Imported Master Untuk Obsolete',
            'nomor_dokumen' => 'PS-SMR-OBS',
            'catatan' => 'Catatan import awal.',
        ]);
        Storage::disk('local')->put('documents/imported-existing/imported-master.pdf', "%PDF-1.4\nfixture");
        $file = $importedMaster->files()->create([
            'type_file' => DocumentFile::TYPE_IMPORTED_DOCUMENT,
            'path_file' => 'documents/imported-existing/imported-master.pdf',
            'uploaded_by' => $user->id,
            'original_file_name' => 'imported-master.pdf',
            'stored_file_name' => 'imported-master.pdf',
            'file_size' => 16,
        ]);

        $this->actingAs($user)
            ->get(route('documents.master.imported.show', $importedMaster))
            ->assertOk()
            ->assertSee('Obsolete');

        $this->actingAs($user)
            ->post(route('documents.master.imported.obsolete', $importedMaster), [
                'catatan_obsolete' => 'Diganti oleh dokumen versi baru.',
            ])
            ->assertRedirect(route('documents.existing.imports.show', $importedMaster));

        $this->assertDatabaseCount('t_document', 1);
        $this->assertDatabaseHas('t_document', [
            'id' => $importedMaster->id,
            'm_status_document_id' => StatusDocument::query()->where('nama_status', StatusDocument::OBSOLETE)->value('id'),
            'catatan' => "Catatan import awal.\n\nAlasan obsolete: Diganti oleh dokumen versi baru.",
        ]);
        $this->assertNotNull($importedMaster->refresh()->obsolete_at);
        $this->assertSame(DocumentFile::TYPE_IMPORTED_DOCUMENT, $file->refresh()->type_file);

        $this->actingAs($user)
            ->get(route('documents.master.imported.show', $importedMaster))
            ->assertNotFound();

        $this->actingAs($user)
            ->get(route('documents.existing.imports.show', $importedMaster))
            ->assertOk()
            ->assertSee('Alasan obsolete: Diganti oleh dokumen versi baru.')
            ->assertSee('File Dokumen');
    }

    public function test_imported_existing_master_obsolete_action_accepts_existing_import_obsolete_permission(): void
    {
        $user = $this->userWithPermission('documents.obsolete.imports.create');
        $importedMaster = $this->createImportedExistingMaster($user, [
            'nama_dokumen' => 'Imported Master Dengan Permission Existing',
            'nomor_dokumen' => 'PS-SMR-PER',
        ]);

        $this->actingAs($user)
            ->get(route('documents.master.imported.show', $importedMaster))
            ->assertOk()
            ->assertSee('Obsolete');
    }

    public function test_imported_obsolete_related_to_master_appears_once(): void
    {
        $user = $this->userWithPermission('documents.master.view');
        $approvedStatus = StatusDocument::create(['nama_status' => StatusDocument::APPROVED]);
        $master = $this->createDocument($user, $approvedStatus, [
            'nama_dokumen' => 'Master Dengan Obsolete Imported',
            'nomor_dokumen' => 'PS-SMR-REL',
        ]);
        $obsolete = $this->createImportedExistingObsolete($user, [
            'nama_dokumen' => 'Imported Obsolete Terkait',
            'nomor_dokumen' => 'PS-SMR-REL-OLD',
            'nomor_revisi' => 'Rev A',
        ]);

        DocumentRelation::create([
            'source_document_id' => $obsolete->id,
            'target_document_id' => $master->id,
            'relation_type' => DocumentRelation::SUPERSEDED_BY,
            'created_by' => $user->id,
        ]);
        DocumentRelation::create([
            'source_document_id' => $obsolete->id,
            'target_document_id' => $master->id,
            'relation_type' => DocumentRelation::SUPERSEDED_BY,
            'created_by' => $user->id,
        ]);

        $response = $this->actingAs($user)
            ->get(route('documents.master'))
            ->assertOk()
            ->assertSee('Imported Obsolete Terkait')
            ->assertSee('PS-SMR-REL-OLD');

        $this->assertSame(1, substr_count($response->getContent(), 'PS-SMR-REL-OLD'));
    }

    public function test_master_raw_file_download_is_not_available_after_approval(): void
    {
        Storage::fake('local');

        $user = $this->userWithPermission('documents.master.download');
        $approvedStatus = StatusDocument::create(['nama_status' => StatusDocument::APPROVED]);

        $source = $this->createDocument($user, $approvedStatus, [
            'nama_dokumen' => 'Prosedur Sumber Revisi',
            'nomor_dokumen' => 'PS-SMR-SNAP',
            'nomor_revisi' => 0,
        ]);
        $revision = $this->createDocument($user, $approvedStatus, [
            'nama_dokumen' => 'Prosedur Sumber Revisi',
            'nomor_dokumen' => 'PS-SMR-SNAP',
            'nomor_revisi' => 1,
            'revised_from' => $source->id,
            'request_type' => null,
        ]);

        Storage::disk('local')->put('documents/revision-master.pdf', 'master revision content');
        $file = DocumentFile::create([
            't_document_id' => $revision->id,
            'type_file' => 'revision_content',
            'path_file' => 'documents/revision-master.pdf',
            'uploaded_by' => $user->id,
            'original_file_name' => 'revision-master.pdf',
            'stored_file_name' => 'revision-master.pdf',
            'file_size' => 23,
        ]);

        $this->actingAs($user)
            ->get(route('documents.master.files.show', [$revision, $file]))
            ->assertNotFound();

        $this->assertSame(0, DocumentDownloadLog::query()->count());
    }

    public function test_master_detail_moves_printout_download_button_to_document_summary_and_logs_download_click(): void
    {
        $user = $this->userWithPermission('documents.master.download');
        $approvedStatus = StatusDocument::create(['nama_status' => StatusDocument::APPROVED]);
        $document = $this->createDocument($user, $approvedStatus, [
            'nama_dokumen' => 'Master Dengan Printout',
            'nomor_dokumen' => 'PS-SMR-PRINT',
        ]);

        $this->mock(DynamicFinalDocumentRenderer::class, function ($mock): void {
            $mock->shouldReceive('canRender')->andReturn(true);
            $mock->shouldReceive('render')->andReturn("%PDF-1.4\nfixture");
            $mock->shouldReceive('fileName')->andReturn('final-document.pdf');
        });

        $downloadUrl = route('documents.master.generated.show', [$document, 'download' => 1]);

        $this->actingAs($user)
            ->get(route('documents.master.show', $document))
            ->assertOk()
            ->assertSee('Printout PDF Final')
            ->assertDontSee('>Buka<', false)
            ->assertSee('Download Printout PDF')
            ->assertSee($downloadUrl, false);

        $this->actingAs($user)
            ->get(route('documents.master.generated.show', $document))
            ->assertOk();

        $this->assertSame(0, DocumentDownloadLog::query()->count());

        $this->actingAs($user)
            ->get($downloadUrl)
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        $log = DocumentDownloadLog::query()->firstOrFail();

        $this->assertSame($document->id, $log->t_document_id);
        $this->assertNull($log->t_document_file_id);
        $this->assertSame($user->id, $log->user_id);
        $this->assertSame('master', $log->download_context);
        $this->assertSame('Master Dengan Printout', $log->document_name_snapshot);
        $this->assertSame('PS-SMR-PRINT', $log->document_number_snapshot);
    }

    public function test_master_generated_preview_applies_master_stamp_and_download_applies_copy_stamp(): void
    {
        $user = $this->userWithPermission('documents.master.detail');
        $downloadPermission = Permission::query()->firstOrCreate(
            ['code' => 'documents.master.download'],
            [
                'name' => 'documents.master.download',
                'module' => 'Manajemen Dokumen',
                'route' => 'documents.master.files.show',
                'action' => 'download',
            ],
        );
        $user->roles()->first()->permissions()->syncWithoutDetaching([$downloadPermission->id]);

        $approvedStatus = StatusDocument::query()->firstOrCreate(['nama_status' => StatusDocument::APPROVED]);
        $document = $this->createDocument($user, $approvedStatus, [
            'nama_dokumen' => 'Master Dengan Watermark',
            'nomor_dokumen' => 'IK-OPS-01-01',
            'nomor_revisi' => 2,
            'tanggal_terbit' => '2025-10-21',
        ]);

        $capturedStamps = [];
        $this->mock(DynamicFinalDocumentRenderer::class, function ($mock) use (&$capturedStamps): void {
            $mock->shouldReceive('canRender')->andReturn(true);
            $mock->shouldReceive('render')
                ->andReturnUsing(function ($doc, $context, $mode = null, $watermarkStamp = null) use (&$capturedStamps) {
                    $capturedStamps[] = $watermarkStamp;

                    return "%PDF-1.4\nfixture";
                });
            $mock->shouldReceive('fileName')->andReturn('final-document.pdf');
        });

        // 1. Preview inline -> MASTER stamp
        $this->actingAs($user)
            ->get(route('documents.master.generated.show', $document))
            ->assertOk();

        $this->assertCount(1, $capturedStamps);
        $masterStamp = $capturedStamps[0];
        $this->assertIsArray($masterStamp);
        $this->assertSame('MASTER', $masterStamp['title']);
        $this->assertSame('ESISMAN PT KBS', $masterStamp['banner_text']);
        $this->assertEquals([
            ['label' => 'Nomor Dokumen', 'value' => 'IK-OPS-01-01'],
            ['label' => 'Revisi Ke', 'value' => '00.02'],
            ['label' => 'Tanggal Terbit', 'value' => '21-10-2025'],
        ], $masterStamp['rows']);

        // 2. Download -> COPY stamp
        $this->actingAs($user)
            ->get(route('documents.master.generated.show', [$document, 'download' => 1]))
            ->assertOk();

        $this->assertCount(2, $capturedStamps);
        $copyStamp = $capturedStamps[1];
        $this->assertIsArray($copyStamp);
        $this->assertSame('COPY', $copyStamp['title']);
        $this->assertSame('ESISMAN PT KBS', $copyStamp['banner_text']);
        $this->assertSame('Diunduh Oleh', $copyStamp['rows'][0]['label']);
        $this->assertSame('Waktu Unduh', $copyStamp['rows'][1]['label']);
        $this->assertSame('Unduhan Ke', $copyStamp['rows'][2]['label']);
    }

    public function test_obsolete_generated_printout_download_click_is_logged(): void
    {
        $user = $this->userWithPermission('documents.obsolete.detail');
        $downloadPermission = Permission::query()->firstOrCreate(
            ['code' => 'documents.obsolete.download'],
            [
                'name' => 'documents.obsolete.download',
                'module' => 'Manajemen Dokumen',
                'route' => 'documents.obsolete.files.show',
                'action' => 'download',
            ],
        );
        $user->roles()->firstOrFail()->permissions()->syncWithoutDetaching([$downloadPermission->id]);
        StatusDocument::create(['nama_status' => StatusDocument::APPROVED]);
        $obsoleteStatus = StatusDocument::create(['nama_status' => StatusDocument::OBSOLETE]);
        $document = $this->createDocument($user, $obsoleteStatus, [
            'nama_dokumen' => 'Obsolete Dengan Printout',
            'nomor_dokumen' => 'PS-SMR-OBS-PRINT',
            'request_type' => 'revision',
        ]);

        $this->mock(DynamicFinalDocumentRenderer::class, function ($mock): void {
            $mock->shouldReceive('canRender')->andReturn(true);
            $mock->shouldReceive('render')->andReturn("%PDF-1.4\nfixture");
            $mock->shouldReceive('fileName')->andReturn('final-document.pdf');
        });

        $downloadUrl = route('documents.obsolete.generated.show', [$document, 'download' => 1]);

        $this->actingAs($user)
            ->get(route('documents.obsolete.show', $document))
            ->assertOk()
            ->assertDontSee('>Buka<', false)
            ->assertSee('Download Printout PDF')
            ->assertSee($downloadUrl, false);

        $this->actingAs($user)
            ->get($downloadUrl)
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        $log = DocumentDownloadLog::query()->firstOrFail();

        $this->assertSame($document->id, $log->t_document_id);
        $this->assertNull($log->t_document_file_id);
        $this->assertSame($user->id, $log->user_id);
        $this->assertSame('obsolete', $log->download_context);
        $this->assertSame('Obsolete Dengan Printout', $log->document_name_snapshot);
        $this->assertSame('PS-SMR-OBS-PRINT', $log->document_number_snapshot);
    }

    public function test_obsolete_generated_preview_applies_obsolete_stamp_and_download_applies_copy_stamp(): void
    {
        $user = $this->userWithPermission('documents.obsolete.detail');
        $downloadPermission = Permission::query()->firstOrCreate(
            ['code' => 'documents.obsolete.download'],
            [
                'name' => 'documents.obsolete.download',
                'module' => 'Manajemen Dokumen',
                'route' => 'documents.obsolete.files.show',
                'action' => 'download',
            ],
        );
        $user->roles()->firstOrFail()->permissions()->syncWithoutDetaching([$downloadPermission->id]);

        StatusDocument::query()->firstOrCreate(['nama_status' => StatusDocument::APPROVED]);
        $obsoleteStatus = StatusDocument::query()->firstOrCreate(['nama_status' => StatusDocument::OBSOLETE]);
        $document = $this->createDocument($user, $obsoleteStatus, [
            'nama_dokumen' => 'Obsolete Dengan Watermark',
            'nomor_dokumen' => 'IK-OPS-01-01',
            'nomor_revisi' => 1,
            'obsolete_at' => '2026-01-15',
            'request_type' => 'revision',
        ]);

        $capturedStamps = [];
        $this->mock(DynamicFinalDocumentRenderer::class, function ($mock) use (&$capturedStamps): void {
            $mock->shouldReceive('canRender')->andReturn(true);
            $mock->shouldReceive('render')
                ->andReturnUsing(function ($doc, $context, $mode = null, $watermarkStamp = null) use (&$capturedStamps) {
                    $capturedStamps[] = $watermarkStamp;

                    return "%PDF-1.4\nfixture";
                });
            $mock->shouldReceive('fileName')->andReturn('final-document.pdf');
        });

        // 1. Preview inline -> OBSOLETE stamp
        $this->actingAs($user)
            ->get(route('documents.obsolete.generated.show', $document))
            ->assertOk();

        $this->assertCount(1, $capturedStamps);
        $obsoleteStamp = $capturedStamps[0];
        $this->assertIsArray($obsoleteStamp);
        $this->assertSame('OBSOLETE', $obsoleteStamp['title']);
        $this->assertSame('ESISMAN PT KBS', $obsoleteStamp['banner_text']);
        $this->assertEquals([
            ['label' => 'Nomor Dokumen', 'value' => 'IK-OPS-01-01'],
            ['label' => 'Revisi Ke', 'value' => '00.01'],
            ['label' => 'Tanggal Obsolete', 'value' => '15-01-2026'],
        ], $obsoleteStamp['rows']);

        // 2. Download -> COPY stamp
        $this->actingAs($user)
            ->get(route('documents.obsolete.generated.show', [$document, 'download' => 1]))
            ->assertOk();

        $this->assertCount(2, $capturedStamps);
        $copyStamp = $capturedStamps[1];
        $this->assertIsArray($copyStamp);
        $this->assertSame('COPY', $copyStamp['title']);
        $this->assertSame('ESISMAN PT KBS', $copyStamp['banner_text']);
        $this->assertSame('Diunduh Oleh', $copyStamp['rows'][0]['label']);
        $this->assertSame('Waktu Unduh', $copyStamp['rows'][1]['label']);
        $this->assertSame('Unduhan Ke', $copyStamp['rows'][2]['label']);
    }

    public function test_master_page_does_not_show_approved_obsolete_request_transaction(): void
    {
        $user = $this->userWithPermission('documents.master.view');
        $approvedStatus = StatusDocument::create(['nama_status' => StatusDocument::APPROVED]);
        $obsoleteStatus = StatusDocument::create(['nama_status' => StatusDocument::OBSOLETE]);

        $source = $this->createDocument($user, $obsoleteStatus, [
            'nama_dokumen' => 'Master Akan Obsolete',
            'nomor_dokumen' => 'PS-SMR-OBS-REQ',
        ]);
        $obsoleteRequest = $this->createDocument($user, $approvedStatus, [
            'nama_dokumen' => 'Transaksi Obsolete Master',
            'nomor_dokumen' => 'FMPS-SMR-OBS-REQ',
            'revised_from' => $source->id,
            'request_type' => 'obsolete',
        ]);

        $this->actingAs($user)
            ->get(route('documents.master'))
            ->assertOk()
            ->assertDontSee('Master Akan Obsolete')
            ->assertDontSee('Transaksi Obsolete Master')
            ->assertDontSee('PS-SMR-OBS-REQ')
            ->assertDontSee('FMPS-SMR-OBS-REQ');

        $this->actingAs($user)
            ->get(route('documents.master.show', $obsoleteRequest))
            ->assertNotFound();
    }

    public function test_master_page_groups_obsolete_revision_inside_master_document(): void
    {
        $user = $this->userWithPermission('documents.master.view');
        $approvedStatus = StatusDocument::create(['nama_status' => StatusDocument::APPROVED]);
        $obsoleteStatus = StatusDocument::create(['nama_status' => StatusDocument::OBSOLETE]);

        $oldDocument = $this->createDocument($user, $obsoleteStatus, [
            'nama_dokumen' => 'Prosedur Pengendalian Dokumen',
            'nomor_dokumen' => 'PS-SMR-001',
            'nomor_revisi' => 0,
        ]);

        $this->createDocument($user, $approvedStatus, [
            'nama_dokumen' => 'Prosedur Pengendalian Dokumen Revisi',
            'nomor_dokumen' => 'PS-SMR-001',
            'nomor_revisi' => 1,
            'revised_from' => $oldDocument->id,
        ]);

        $this->actingAs($user)
            ->get(route('documents.master'))
            ->assertOk()
            ->assertSee('Prosedur Pengendalian Dokumen Revisi')
            ->assertSee('Tgl Obsolete')
            ->assertSee('PS-SMR-001')
            ->assertSee('Obsolete');
    }

    public function test_master_page_shows_latest_approved_revision_as_primary_row(): void
    {
        $user = $this->userWithPermission('documents.master.view');
        $approvedStatus = StatusDocument::create(['nama_status' => StatusDocument::APPROVED]);
        $obsoleteStatus = StatusDocument::create(['nama_status' => StatusDocument::OBSOLETE]);

        $oldDocument = $this->createDocument($user, $obsoleteStatus, [
            'nama_dokumen' => 'Instruksi Kerja Lama',
            'nomor_dokumen' => 'IK-SMR-010',
            'nomor_revisi' => 0,
            'approved_at' => now()->subDays(2),
        ]);

        $latestRevision = $this->createDocument($user, $approvedStatus, [
            'nama_dokumen' => 'Instruksi Kerja Revisi Aktif',
            'nomor_dokumen' => 'FMIK-SMR-010',
            'nomor_revisi' => 1,
            'revised_from' => $oldDocument->id,
            'approved_at' => now(),
        ]);

        $response = $this->actingAs($user)
            ->get(route('documents.master'))
            ->assertOk()
            ->assertSee('Instruksi Kerja Revisi Aktif')
            ->assertSee('IK-SMR-010')
            ->assertSee('Tgl Obsolete')
            ->assertSee('00.00');

        $this->assertLessThan(
            strpos($response->getContent(), 'Tgl Obsolete'),
            strpos($response->getContent(), 'Instruksi Kerja Revisi Aktif'),
        );
        $response->assertSee(route('documents.master.show', $latestRevision), false);
    }

    public function test_master_page_obsolete_dropdown_uses_master_document_number_for_revision_forms(): void
    {
        $user = $this->userWithPermission('documents.master.view');
        $approvedStatus = StatusDocument::create(['nama_status' => StatusDocument::APPROVED]);
        $obsoleteStatus = StatusDocument::create(['nama_status' => StatusDocument::OBSOLETE]);

        $rootDocument = $this->createDocument($user, $obsoleteStatus, [
            'nama_dokumen' => 'Prosedur Lama',
            'nomor_dokumen' => 'PS-SMR-001',
            'nomor_revisi' => 0,
            'approved_at' => now()->subDays(3),
        ]);
        $obsoleteRevision = $this->createDocument($user, $obsoleteStatus, [
            'nama_dokumen' => 'Prosedur Revisi Pertama',
            'nomor_dokumen' => 'FMPS-SMR-001',
            'nomor_revisi' => 1,
            'revised_from' => $rootDocument->id,
            'approved_at' => now()->subDays(2),
        ]);
        $this->createDocument($user, $approvedStatus, [
            'nama_dokumen' => 'Prosedur Revisi Aktif',
            'nomor_dokumen' => 'FMPS-SMR-001',
            'nomor_revisi' => 2,
            'revised_from' => $rootDocument->id,
            'approved_at' => now(),
        ]);

        $response = $this->actingAs($user)
            ->get(route('documents.master'))
            ->assertOk()
            ->assertSee('Prosedur Revisi Aktif')
            ->assertSee('PS-SMR-001')
            ->assertSee('00.01')
            ->assertSee(route('documents.obsolete.show', $obsoleteRevision), false);

        $this->assertStringNotContainsString(
            '<td class="px-5 py-4 font-semibold uppercase tracking-wide text-slate-700">'.PHP_EOL.'                                                                FMPS-SMR-001',
            $response->getContent(),
        );
    }

    public function test_master_page_obsolete_date_ignores_unpublished_revision_attempts(): void
    {
        $user = $this->userWithPermission('documents.master.view');
        $approvedStatus = StatusDocument::create(['nama_status' => StatusDocument::APPROVED]);
        $obsoleteStatus = StatusDocument::create(['nama_status' => StatusDocument::OBSOLETE]);
        $rejectedStatus = StatusDocument::create(['nama_status' => StatusDocument::REJECTED]);

        $rootDocument = $this->createDocument($user, $obsoleteStatus, [
            'nama_dokumen' => 'Prosedur Lama',
            'nomor_dokumen' => 'PS-SMR-001',
            'nomor_revisi' => 0,
            'tanggal_terbit' => '2026-09-01',
            'approved_at' => '2026-09-01 08:00:00',
        ]);
        $obsoleteRevision = $this->createDocument($user, $obsoleteStatus, [
            'nama_dokumen' => 'Prosedur Revisi Pertama',
            'nomor_dokumen' => 'FMPS-SMR-001',
            'nomor_revisi' => 1,
            'revised_from' => $rootDocument->id,
            'request_type' => 'revision',
            'tanggal_terbit' => '2026-09-02',
            'approved_at' => '2026-09-02 08:00:00',
        ]);
        $this->createDocument($user, $rejectedStatus, [
            'nama_dokumen' => 'Prosedur Revisi Kedua Gagal',
            'nomor_dokumen' => 'FMPS-SMR-001',
            'nomor_revisi' => 2,
            'revised_from' => $rootDocument->id,
            'request_type' => 'revision',
            'tanggal_terbit' => null,
            'approved_at' => null,
        ]);
        $this->createDocument($user, $approvedStatus, [
            'nama_dokumen' => 'Prosedur Revisi Kedua Aktif',
            'nomor_dokumen' => 'FMPS-SMR-001',
            'nomor_revisi' => 2,
            'revised_from' => $rootDocument->id,
            'request_type' => 'revision',
            'tanggal_terbit' => '2026-09-03',
            'approved_at' => '2026-09-03 08:00:00',
        ]);

        $content = $this->actingAs($user)
            ->get(route('documents.master'))
            ->assertOk()
            ->assertSee(route('documents.obsolete.show', $obsoleteRevision), false)
            ->getContent();

        $this->assertMatchesRegularExpression('/00\.01.*02\/09\/2026.*03\/09\/2026/s', $content);
    }

    public function test_obsolete_page_shows_only_obsolete_documents(): void
    {
        $user = $this->userWithPermission('documents.obsolete.view');
        $approvedStatus = StatusDocument::create(['nama_status' => StatusDocument::APPROVED]);
        $obsoleteStatus = StatusDocument::create(['nama_status' => StatusDocument::OBSOLETE]);

        $obsoleteDocument = $this->createDocument($user, $obsoleteStatus, [
            'nama_dokumen' => 'Dokumen Lama Obsolete',
            'nomor_dokumen' => 'PS-SMR-OBS',
            'nomor_revisi' => 2,
        ]);
        $this->createDocument($user, $approvedStatus, [
            'nama_dokumen' => 'Dokumen Master Aktif',
            'nomor_dokumen' => 'PS-SMR-ACT',
        ]);

        $this->actingAs($user)
            ->get(route('documents.obsolete'))
            ->assertOk()
            ->assertSee('Dokumen Obsolete')
            ->assertDontSee('Tambah Dokumen Obsolete')
            ->assertSee('Dokumen Lama Obsolete')
            ->assertSee('PS-SMR-OBS')
            ->assertSee('00.02')
            ->assertSee('Obsolete')
            ->assertSee(route('documents.obsolete.show', $obsoleteDocument), false)
            ->assertDontSee('Dokumen Master Aktif')
            ->assertDontSee('PS-SMR-ACT');
    }

    public function test_obsolete_page_does_not_show_obsolete_request_transaction(): void
    {
        $user = $this->userWithPermission('documents.obsolete.view');
        $obsoleteStatus = StatusDocument::create(['nama_status' => StatusDocument::OBSOLETE]);

        $masterDocument = $this->createDocument($user, $obsoleteStatus, [
            'nama_dokumen' => 'Dokumen Obsolete Asli',
            'nomor_dokumen' => 'PS-SMR-OBS-REAL',
        ]);
        $this->createDocument($user, $obsoleteStatus, [
            'nama_dokumen' => 'Transaksi Obsolete Approved',
            'nomor_dokumen' => 'FMPS-SMR-OBS-REQ',
            'nomor_revisi' => 1,
            'revised_from' => $masterDocument->id,
            'request_type' => 'obsolete',
        ]);

        $this->actingAs($user)
            ->get(route('documents.obsolete'))
            ->assertOk()
            ->assertSee('Dokumen Obsolete Asli')
            ->assertDontSee('Transaksi Obsolete Approved')
            ->assertDontSee('FMPS-SMR-OBS-REQ');
    }

    public function test_obsolete_page_groups_versions_under_latest_obsolete_revision(): void
    {
        $user = $this->userWithPermission('documents.obsolete.view');
        $obsoleteStatus = StatusDocument::create(['nama_status' => StatusDocument::OBSOLETE]);

        $rootDocument = $this->createDocument($user, $obsoleteStatus, [
            'nama_dokumen' => 'Prosedur Obsolete Group',
            'nomor_dokumen' => 'PS-SMR-OBS-GRP',
            'nomor_revisi' => 0,
            'approved_at' => now()->subDays(3),
        ]);
        $firstRevision = $this->createDocument($user, $obsoleteStatus, [
            'nama_dokumen' => 'Prosedur Obsolete Group Revisi 1',
            'nomor_dokumen' => 'FMPS-SMR-OBS-GRP',
            'nomor_revisi' => 1,
            'revised_from' => $rootDocument->id,
            'request_type' => 'revision',
            'approved_at' => now()->subDays(2),
        ]);
        $latestRevision = $this->createDocument($user, $obsoleteStatus, [
            'nama_dokumen' => 'Prosedur Obsolete Group Revisi 2',
            'nomor_dokumen' => 'FMPS-SMR-OBS-GRP',
            'nomor_revisi' => 2,
            'revised_from' => $rootDocument->id,
            'request_type' => 'revision',
            'approved_at' => now()->subDay(),
        ]);

        $response = $this->actingAs($user)
            ->get(route('documents.obsolete'))
            ->assertOk()
            ->assertSee('Tampilkan riwayat versi obsolete')
            ->assertSee('Prosedur Obsolete Group Revisi 2')
            ->assertSee('00.02')
            ->assertSee('Prosedur Obsolete Group Revisi 1')
            ->assertSee('00.01')
            ->assertSee('00.00')
            ->assertSee(route('documents.obsolete.show', $latestRevision), false)
            ->assertSee(route('documents.obsolete.show', $firstRevision), false)
            ->assertSee(route('documents.obsolete.show', $rootDocument), false);

        $content = $response->getContent();

        $this->assertLessThan(
            strpos($content, route('documents.obsolete.show', $firstRevision)),
            strpos($content, route('documents.obsolete.show', $latestRevision)),
        );
        $this->assertLessThan(
            strpos($content, route('documents.obsolete.show', $rootDocument)),
            strpos($content, route('documents.obsolete.show', $firstRevision)),
        );
    }

    public function test_obsolete_page_shows_imported_obsolete_document(): void
    {
        $user = $this->userWithPermission('documents.obsolete.view');
        $importedObsolete = $this->createImportedExistingObsolete($user, [
            'nama_dokumen' => 'Prosedur Obsolete Manual Standar',
            'nomor_dokumen' => 'PS-SMR-OBS-IMP-1',
            'nomor_revisi' => '00.00',
        ]);

        $this->actingAs($user)
            ->get(route('documents.obsolete'))
            ->assertOk()
            ->assertSee('Prosedur Obsolete Manual Standar')
            ->assertSee('PS-SMR-OBS-IMP-1')
            ->assertSee('00.00')
            ->assertSee('Imported')
            ->assertSee(route('documents.existing.imports.show', $importedObsolete), false);
    }

    public function test_obsolete_page_groups_workflow_and_imported_obsolete_with_same_document_number(): void
    {
        $user = $this->userWithPermission('documents.obsolete.view');
        $obsoleteStatus = StatusDocument::create(['nama_status' => StatusDocument::OBSOLETE]);

        $workflowObsolete = $this->createDocument($user, $obsoleteStatus, [
            'nama_dokumen' => 'Prosedur Bersama Workflow Rev 1',
            'nomor_dokumen' => 'PS-SMR-SHARED',
            'nomor_revisi' => 1,
            'approved_at' => now()->subDays(2),
        ]);

        $importedObsolete = $this->createImportedExistingObsolete($user, [
            'nama_dokumen' => 'Prosedur Bersama Import Awal',
            'nomor_dokumen' => 'PS-SMR-SHARED',
            'nomor_revisi' => '00.00',
            'tanggal_terbit' => now()->subMonth()->toDateString(),
        ]);

        $response = $this->actingAs($user)
            ->get(route('documents.obsolete'))
            ->assertOk()
            ->assertSee('Menampilkan 1 grup dari total 2 dokumen obsolete.', false)
            ->assertSee('Tampilkan riwayat versi obsolete')
            ->assertSee('Prosedur Bersama Workflow Rev 1')
            ->assertSee('00.01')
            ->assertSee('Prosedur Bersama Import Awal')
            ->assertSee('00.00')
            ->assertSee(route('documents.obsolete.show', $workflowObsolete), false)
            ->assertSee(route('documents.existing.imports.show', $importedObsolete), false);

        $content = $response->getContent();
        $this->assertLessThan(
            strpos($content, route('documents.existing.imports.show', $importedObsolete)),
            strpos($content, route('documents.obsolete.show', $workflowObsolete)),
        );
    }

    public function test_obsolete_page_groups_multiple_imported_obsolete_documents_with_same_document_number(): void
    {
        $user = $this->userWithPermission('documents.obsolete.view');

        $olderImported = $this->createImportedExistingObsolete($user, [
            'nama_dokumen' => 'Imported Obsolete Rev 0',
            'nomor_dokumen' => 'PS-SMR-MULTI-IMP',
            'nomor_revisi' => '00.00',
            'tanggal_terbit' => now()->subYear()->toDateString(),
        ]);

        $newerImported = $this->createImportedExistingObsolete($user, [
            'nama_dokumen' => 'Imported Obsolete Rev 1',
            'nomor_dokumen' => 'PS-SMR-MULTI-IMP',
            'nomor_revisi' => '00.01',
            'tanggal_terbit' => now()->subMonths(3)->toDateString(),
        ]);

        $response = $this->actingAs($user)
            ->get(route('documents.obsolete'))
            ->assertOk()
            ->assertSee('Menampilkan 1 grup dari total 2 dokumen obsolete.', false)
            ->assertSee('Tampilkan riwayat versi obsolete')
            ->assertSee('Imported Obsolete Rev 1')
            ->assertSee('Imported Obsolete Rev 0')
            ->assertSee(route('documents.existing.imports.show', $newerImported), false)
            ->assertSee(route('documents.existing.imports.show', $olderImported), false);

        $content = $response->getContent();
        $this->assertLessThan(
            strpos($content, route('documents.existing.imports.show', $olderImported)),
            strpos($content, route('documents.existing.imports.show', $newerImported)),
        );
    }

    public function test_obsolete_page_shows_add_button_for_user_with_create_permission(): void
    {
        $user = $this->userWithPermission('documents.obsolete.imports.create');

        $this->actingAs($user)
            ->get(route('documents.obsolete'))
            ->assertOk()
            ->assertSee('Tambah Dokumen Obsolete')
            ->assertSee(route('documents.obsolete.imports.create'), false);
    }

    public function test_user_role_can_read_obsolete_list_and_detail(): void
    {
        $obsoleteStatus = StatusDocument::create(['nama_status' => StatusDocument::OBSOLETE]);
        $userRole = Role::create(['nama_role' => 'User']);
        $viewPermission = Permission::create([
            'code' => 'documents.obsolete.view',
            'name' => 'Lihat Dokumen Obsolete',
            'module' => 'Manajemen Dokumen',
            'route' => 'documents.obsolete',
            'action' => 'view',
        ]);
        $detailPermission = Permission::create([
            'code' => 'documents.obsolete.detail',
            'name' => 'Lihat Detail Dokumen Obsolete',
            'module' => 'Manajemen Dokumen',
            'route' => 'documents.obsolete.show',
            'action' => 'view',
        ]);
        $userRole->permissions()->sync([$viewPermission->id, $detailPermission->id]);
        $user = User::factory()->create();
        $document = $this->createDocument($user, $obsoleteStatus, [
            'nama_dokumen' => 'Dokumen Obsolete Untuk User',
            'nomor_dokumen' => 'PS-SMR-USER-OBS',
        ]);

        $this->actingAs($user->fresh())
            ->get(route('documents.obsolete'))
            ->assertOk()
            ->assertSee('Manajemen Dokumen')
            ->assertSee('Dokumen Obsolete')
            ->assertSee('href="'.route('documents.obsolete').'"', false)
            ->assertSee('Dokumen Obsolete Untuk User')
            ->assertSee(route('documents.obsolete.show', $document), false);

        $this->actingAs($user->fresh())
            ->get(route('documents.obsolete.show', $document))
            ->assertOk()
            ->assertSee('Detail Dokumen Obsolete')
            ->assertSee('PS-SMR-USER-OBS');
    }

    public function test_obsolete_page_requires_view_permission_when_permissions_exist(): void
    {
        Permission::create([
            'code' => 'documents.obsolete.view',
            'name' => 'Lihat Dokumen Obsolete',
            'module' => 'Manajemen Dokumen',
            'route' => 'documents.obsolete',
            'action' => 'view',
        ]);
        $role = Role::create(['nama_role' => 'Viewer Tanpa Obsolete']);
        $user = User::factory()->create();
        $user->roles()->attach($role);

        $this->actingAs($user)
            ->get(route('documents.obsolete'))
            ->assertForbidden();
    }

    public function test_obsolete_page_uses_master_document_number_for_revision_forms(): void
    {
        $user = $this->userWithPermission('documents.obsolete.view');
        $obsoleteStatus = StatusDocument::create(['nama_status' => StatusDocument::OBSOLETE]);

        $rootDocument = $this->createDocument($user, $obsoleteStatus, [
            'nama_dokumen' => 'Prosedur Lama',
            'nomor_dokumen' => 'PS-SMR-001',
            'nomor_revisi' => 0,
        ]);
        $obsoleteRevision = $this->createDocument($user, $obsoleteStatus, [
            'nama_dokumen' => 'Prosedur Revisi Obsolete',
            'nomor_dokumen' => 'FMPS-SMR-001',
            'nomor_revisi' => 1,
            'revised_from' => $rootDocument->id,
        ]);

        $response = $this->actingAs($user)
            ->get(route('documents.obsolete'))
            ->assertOk()
            ->assertSee('Prosedur Revisi Obsolete')
            ->assertSee('PS-SMR-001')
            ->assertSee('00.01')
            ->assertSee(route('documents.obsolete.show', $obsoleteRevision), false);

        $this->assertStringNotContainsString(
            '<td class="px-3 py-4 font-semibold text-slate-700">FMPS-SMR-001</td>',
            $response->getContent(),
        );
    }

    public function test_approved_level_four_revision_becomes_master_and_groups_old_master_as_obsolete(): void
    {
        $submitter = $this->userWithPermission('documents.master.view');
        $approver = User::factory()->create();
        $approvedStatus = StatusDocument::create(['nama_status' => StatusDocument::APPROVED]);
        StatusDocument::create(['nama_status' => StatusDocument::PROPOSED]);
        StatusDocument::create(['nama_status' => StatusDocument::OBSOLETE]);
        ApprovalStatus::create(['kode_status' => ApprovalStatus::PENDING, 'nama_status' => 'Dalam Review']);
        ApprovalStatus::create(['kode_status' => ApprovalStatus::WAITING, 'nama_status' => 'Menunggu']);
        ApprovalStatus::create(['kode_status' => ApprovalStatus::APPROVED, 'nama_status' => 'Disetujui']);
        ApprovalStatus::create(['kode_status' => ApprovalStatus::REJECTED, 'nama_status' => 'Ditolak']);
        ApprovalStatus::create(['kode_status' => ApprovalStatus::TERMINATED, 'nama_status' => 'Dibatalkan']);

        $source = $this->createDocument($submitter, $approvedStatus, [
            'nama_dokumen' => 'Prosedur Ikatan Dinas SSO',
            'nomor_dokumen' => 'PS-KSA-02',
            'nomor_revisi' => 0,
            'approved_at' => now()->subDay(),
        ]);
        $levelFour = DocumentLevel::query()->where('kode', 'level-4')->firstOrFail();
        $revisionType = DocumentType::query()->firstOrCreate(['nama_types' => 'Revisi']);

        $revision = Document::create([
            'm_document_level_id' => $levelFour->id,
            'm_status_document_id' => StatusDocument::query()->where('nama_status', StatusDocument::PROPOSED)->firstOrFail()->id,
            'm_document_types_id' => $revisionType->id,
            'm_proses_bisnis_id' => $source->m_proses_bisnis_id,
            'm_proses_fungsi_id' => $source->m_proses_fungsi_id,
            'user_id' => $submitter->id,
            'official_preparer_id' => $submitter->id,
            'revised_from' => $source->id,
            'request_type' => 'revision',
            'nama_dokumen' => 'Prosedur Ikatan Dinas SSO',
            'nomor_dokumen' => 'FMPS-KSA-02',
            'nomor_revisi' => 1,
            'submitted_at' => now(),
        ]);
        $revision->departments()->sync($source->departments()->pluck('departments.id')->all());
        DocumentFile::create([
            't_document_id' => $revision->id,
            'type_file' => 'revision_content',
            'path_file' => "documents/{$revision->id}/dokumen-revisi.pdf",
            'uploaded_by' => $submitter->id,
            'original_file_name' => 'dokumen-revisi.pdf',
            'stored_file_name' => 'dokumen-revisi.pdf',
            'file_size' => 24000,
        ]);
        DocumentFile::create([
            't_document_id' => $revision->id,
            'type_file' => 'revision_form',
            'path_file' => "documents/{$revision->id}/lembar-revisi.pdf",
            'uploaded_by' => $submitter->id,
            'original_file_name' => 'lembar-revisi.pdf',
            'stored_file_name' => 'lembar-revisi.pdf',
            'file_size' => 12000,
        ]);

        $flow = ApprovalFlow::create([
            'm_document_level_id' => $source->m_document_level_id,
            'nama_flow' => 'Flow Revisi Prosedur',
        ]);
        $stage = $flow->stages()->create([
            'stage_order' => 1,
            'keterangan' => 'Disahkan Oleh',
            'nama_tahap' => 'Superintendent',
        ]);

        Approval::create([
            't_document_id' => $revision->id,
            'm_approval_status_id' => ApprovalStatus::findByCode(ApprovalStatus::PENDING)->id,
            'user_id' => $approver->id,
            'role_id' => null,
            'assigned_by' => $submitter->id,
            'assigned_at' => now(),
            'stages' => $stage->display_label,
        ]);

        $this->actingAs($approver)
            ->post(route('documents.approval.approve', $revision))
            ->assertRedirect(route('documents.approval.show', $revision));

        $revision->refresh();
        $source->refresh();

        $this->assertSame(StatusDocument::APPROVED, $revision->status->nama_status);
        $this->assertSame('revision', $revision->request_type);
        $this->assertSame(StatusDocument::OBSOLETE, $source->status->nama_status);
        $this->assertNotNull($source->obsolete_at);
        $this->assertSame($revision->approved_at->toDateString(), $source->obsolete_at->toDateString());
        $this->assertSame($source->m_document_level_id, $revision->m_document_level_id);
        $this->assertSame($source->m_document_types_id, $revision->m_document_types_id);
        $this->assertSame('PS-KSA-02', $revision->nomor_dokumen);
        $this->assertSame('FMPS-KSA-02-01', $revision->nomor_lembar_revisi);
        $this->assertSame('1', (string) $revision->nomor_revisi);

        $this->actingAs($submitter)
            ->get(route('documents.master'))
            ->assertOk()
            ->assertSee('Prosedur Ikatan Dinas SSO')
            ->assertSee('PS-KSA-02')
            ->assertSee('00.01')
            ->assertDontSee('FMPS-KSA-02')
            ->assertSee('Tgl Obsolete')
            ->assertSee('00.00')
            ->assertSee('Obsolete');

        $this->actingAs($submitter)
            ->get(route('documents.master.show', $revision))
            ->assertOk()
            ->assertSee('Nomor Lembar Revisi')
            ->assertSee('FMPS-KSA-02-01')
            ->assertSee('Printout PDF Final')
            ->assertDontSee('<iframe src="'.route('documents.master.generated.show', $revision), false)
            ->assertDontSee('dokumen-revisi.pdf')
            ->assertDontSee('lembar-revisi.pdf');

        $this->actingAs($submitter)
            ->get(route('documents.approval.show', $revision))
            ->assertOk()
            ->assertSee('Nomor Lembar Revisi')
            ->assertSee('FMPS-KSA-02-01');

        $this->actingAs($submitter)
            ->get(route('documents.approval.show', $revision))
            ->assertOk()
            ->assertSee('FMPS-KSA-02');
    }

    public function test_master_detail_shows_approval_history_sorted_by_stage_with_response_timestamp(): void
    {
        $viewer = $this->userWithPermission('documents.master.detail');
        $approvedStatus = StatusDocument::create(['nama_status' => StatusDocument::APPROVED]);
        $approvedApprovalStatus = ApprovalStatus::create([
            'kode_status' => ApprovalStatus::APPROVED,
            'nama_status' => 'Disetujui',
        ]);
        $document = $this->createDocument($viewer, $approvedStatus, [
            'nama_dokumen' => 'Master Dengan Riwayat',
            'nomor_dokumen' => 'PS-SMR-HISTORY',
        ]);
        $flow = ApprovalFlow::create([
            'm_document_level_id' => $document->m_document_level_id,
            'nama_flow' => 'Flow Master',
        ]);
        ApprovalFlowStage::create([
            'm_approval_flow_id' => $flow->id,
            'stage_order' => 1,
            'nama_tahap' => 'Staff',
        ]);
        ApprovalFlowStage::create([
            'm_approval_flow_id' => $flow->id,
            'stage_order' => 2,
            'nama_tahap' => 'Direktur Utama',
        ]);
        $staffRole = Role::create(['nama_role' => 'Staff']);
        $directorRole = Role::create(['nama_role' => 'Direktur Utama']);
        $staffApprover = User::factory()->create(['name' => 'Staff Approver']);
        $directorApprover = User::factory()->create(['name' => 'Director Approver']);

        Approval::create([
            't_document_id' => $document->id,
            'm_approval_status_id' => $approvedApprovalStatus->id,
            'user_id' => $directorApprover->id,
            'role_id' => $directorRole->id,
            'assigned_by' => $viewer->id,
            'assigned_at' => now()->subDay(),
            'responded_at' => now()->setDate(2026, 8, 18)->setTime(15, 10, 20),
            'stages' => 'Direktur Utama',
        ]);
        Approval::create([
            't_document_id' => $document->id,
            'm_approval_status_id' => $approvedApprovalStatus->id,
            'user_id' => $staffApprover->id,
            'role_id' => $staffRole->id,
            'assigned_by' => $viewer->id,
            'assigned_at' => now(),
            'responded_at' => now()->setDate(2026, 8, 18)->setTime(14, 25, 36),
            'stages' => 'Staff',
        ]);

        $response = $this->actingAs($viewer)
            ->get(route('documents.master.show', $document))
            ->assertOk()
            ->assertSee('Staff')
            ->assertSee('Diproses pada 18 Aug 2026 14:25:36')
            ->assertSee('Direktur Utama')
            ->assertDontSee('Tahap 1')
            ->assertDontSee('Tahap 2');

        $this->assertLessThan(
            strpos($response->getContent(), 'Direktur Utama'),
            strpos($response->getContent(), 'Staff'),
        );
    }

    public function test_master_detail_for_revision_shows_parent_and_revision_numbers_without_reference_rows(): void
    {
        $viewer = $this->userWithPermission('documents.master.detail');
        $approvedStatus = StatusDocument::create(['nama_status' => StatusDocument::APPROVED]);
        $referenceDocument = $this->createDocument($viewer, $approvedStatus, [
            'nama_dokumen' => 'Dokumen Acuan Lama',
            'nomor_dokumen' => 'PS-SMR-REF',
        ]);
        $source = $this->createDocument($viewer, $approvedStatus, [
            'nama_dokumen' => 'Instruksi Induk',
            'nomor_dokumen' => 'IK-SMR-010',
            'nomor_revisi' => 0,
        ]);
        $revision = $this->createDocument($viewer, $approvedStatus, [
            'nama_dokumen' => 'Instruksi Revisi Aktif',
            'nomor_dokumen' => 'IK-SMR-010',
            'nomor_lembar_revisi' => 'FMIK-SMR-010-01',
            'nomor_revisi' => 1,
            'revised_from' => $source->id,
        ]);
        foreach ([$source, $revision] as $instruction) {
            $instruction->outgoingRelations()->create([
                'target_document_id' => $referenceDocument->id,
                'relation_type' => DocumentRelation::REFERENCES,
                'created_by' => $viewer->id,
            ]);
        }

        $response = $this->actingAs($viewer)
            ->get(route('documents.master.show', $revision))
            ->assertOk()
            ->assertSee('Nomor Dokumen')
            ->assertSee('IK-SMR-010')
            ->assertSee('Nomor Lembar Revisi')
            ->assertSee('FMIK-SMR-010-01')
            ->assertSee('00.01')
            ->assertSee('Riwayat Dokumen')
            ->assertSee('Dokumen disetujui')
            ->assertDontSee('Dokumen Acuan')
            ->assertDontSee('Revisi Dari')
            ->assertDontSee('PS-SMR-REF - Dokumen Acuan Lama');

        $this->assertLessThan(
            strpos($response->getContent(), 'Nomor Lembar Revisi'),
            strpos($response->getContent(), 'Nomor Dokumen'),
        );
    }

    public function test_revision_button_only_shows_for_user_from_document_department(): void
    {
        $approvedStatus = StatusDocument::create(['nama_status' => StatusDocument::APPROVED]);
        $owner = User::factory()->create();
        $document = $this->createDocument($owner, $approvedStatus, [
            'nama_dokumen' => 'Master Bisa Direvisi',
            'nomor_dokumen' => 'PS-SMR-REV',
        ]);
        $documentDepartment = $document->departments()->firstOrFail();
        $sameDepartmentUser = $this->userWithoutPermission('documents.obsolete.create', [
            'm_department_id' => $documentDepartment->id,
        ]);
        $otherDepartment = Department::create([
            'kode_department' => 'HR',
            'nama_department' => 'Human Resources',
        ]);
        $otherDepartmentUser = $this->userWithoutPermission('documents.obsolete.create', [
            'm_department_id' => $otherDepartment->id,
        ]);

        $this->actingAs($sameDepartmentUser)
            ->get(route('documents.master.show', $document))
            ->assertOk()
            ->assertSee('Ajukan Revisi')
            ->assertSee(route('documents.create.level', ['level-4', 'revised_from' => $document->id]), false)
            ->assertSee('data-obsolete-modal-open', false);

        $this->actingAs($otherDepartmentUser)
            ->get(route('documents.master.show', $document))
            ->assertOk()
            ->assertDontSee('Ajukan Revisi');
    }

    public function test_revision_button_is_disabled_when_revision_request_is_proposed(): void
    {
        $approvedStatus = StatusDocument::create(['nama_status' => StatusDocument::APPROVED]);
        $proposedStatus = StatusDocument::create(['nama_status' => StatusDocument::PROPOSED]);
        $owner = User::factory()->create();
        $document = $this->createDocument($owner, $approvedStatus, [
            'nama_dokumen' => 'Master Dengan Revisi Berjalan',
            'nomor_dokumen' => 'PS-SMR-ACTIVE-REV',
        ]);
        $documentDepartment = $document->departments()->firstOrFail();
        $sameDepartmentUser = $this->userWithoutPermission('documents.obsolete.create', [
            'm_department_id' => $documentDepartment->id,
        ]);

        $this->createDocument($owner, $proposedStatus, [
            'nama_dokumen' => 'Master Dengan Revisi Berjalan Rev 1',
            'nomor_dokumen' => 'PS-SMR-ACTIVE-REV',
            'nomor_revisi' => 1,
            'revised_from' => $document->id,
            'request_type' => 'revision',
            'submitted_at' => now(),
        ]);

        $this->actingAs($sameDepartmentUser)
            ->get(route('documents.master.show', $document))
            ->assertOk()
            ->assertSee('Revisi dalam Pengajuan')
            ->assertSee('disabled', false)
            ->assertDontSee(route('documents.create.level', ['level-4', 'revised_from' => $document->id]), false);
    }

    public function test_imported_master_revision_button_is_disabled_when_revision_request_is_proposed(): void
    {
        $proposedStatus = StatusDocument::create(['nama_status' => StatusDocument::PROPOSED]);
        $user = $this->userWithPermission('documents.master.imported.detail');
        $importedMaster = $this->createImportedExistingMaster($user, [
            'nama_dokumen' => 'Imported Master Dengan Revisi Berjalan',
            'nomor_dokumen' => 'PS-SMR-IMP-ACT',
        ]);

        $this->createDocument($user, $proposedStatus, [
            'nama_dokumen' => 'Imported Master Dengan Revisi Berjalan Rev 1',
            'nomor_dokumen' => 'PS-SMR-IMP-ACT',
            'nomor_revisi' => 1,
            'revised_from' => $importedMaster->id,
            'request_type' => 'revision',
            'submitted_at' => now(),
        ]);

        $this->actingAs($user)
            ->get(route('documents.master.imported.show', $importedMaster))
            ->assertOk()
            ->assertSee('Revisi dalam Pengajuan')
            ->assertSee('disabled', false)
            ->assertDontSee(route('documents.create.level', ['level-4', 'imported_source' => $importedMaster->id]), false);
    }

    public function test_imported_master_revision_button_shows_for_regular_user_from_document_department(): void
    {
        $department = Department::create([
            'kode_department' => 'QA',
            'nama_department' => 'Quality Assurance',
        ]);
        $otherDepartment = Department::create([
            'kode_department' => 'FIN',
            'nama_department' => 'Finance',
        ]);

        $owner = User::factory()->create();
        $importedMaster = $this->createImportedExistingMaster($owner, [
            'nama_dokumen' => 'Imported Master Dept Test',
            'nomor_dokumen' => 'PS-QA-001',
        ]);
        $importedMaster->departments()->sync([$department->id]);

        $regularUserSameDept = $this->userWithPermission('documents.master.detail');
        $regularUserSameDept->update(['m_department_id' => $department->id]);

        $regularUserOtherDept = $this->userWithPermission('documents.master.detail');
        $regularUserOtherDept->update(['m_department_id' => $otherDepartment->id]);

        // Same department user should see "Ajukan Revisi"
        $this->actingAs($regularUserSameDept)
            ->get(route('documents.master.imported.show', $importedMaster))
            ->assertOk()
            ->assertSee('Ajukan Revisi')
            ->assertSee(route('documents.create.level', ['level-4', 'imported_source' => $importedMaster->id]), false);

        // Other department user should NOT see "Ajukan Revisi"
        $this->actingAs($regularUserOtherDept)
            ->get(route('documents.master.imported.show', $importedMaster))
            ->assertOk()
            ->assertDontSee(route('documents.create.level', ['level-4', 'imported_source' => $importedMaster->id]), false);
    }

    public function test_obsolete_button_only_shows_for_user_from_document_department(): void
    {
        $approvedStatus = StatusDocument::create(['nama_status' => StatusDocument::APPROVED]);
        $owner = User::factory()->create();
        $document = $this->createDocument($owner, $approvedStatus, [
            'nama_dokumen' => 'Master Bisa Diobsoletekan',
            'nomor_dokumen' => 'PS-SMR-OBS-BTN',
        ]);
        $documentDepartment = $document->departments()->firstOrFail();
        $obsoleteUser = $this->userWithoutPermission('documents.obsolete.create', [
            'm_department_id' => $documentDepartment->id,
        ]);
        $otherDepartment = Department::create([
            'kode_department' => 'FN',
            'nama_department' => 'Finance',
        ]);
        $otherDepartmentUser = $this->userWithoutPermission('documents.obsolete.create', [
            'm_department_id' => $otherDepartment->id,
        ]);

        $this->actingAs($obsoleteUser)
            ->get(route('documents.master.show', $document))
            ->assertOk()
            ->assertSee('Obsolete')
            ->assertSee('data-obsolete-modal-open', false);

        $this->actingAs($otherDepartmentUser)
            ->get(route('documents.master.show', $document))
            ->assertOk()
            ->assertDontSee('data-obsolete-modal-open', false);
    }

    public function test_obsolete_document_detail_uses_obsolete_page_and_is_read_only(): void
    {
        $obsoleteStatus = StatusDocument::create(['nama_status' => StatusDocument::OBSOLETE]);
        $user = $this->userWithPermission('documents.obsolete.detail');
        $document = $this->createDocument($user, $obsoleteStatus, [
            'nama_dokumen' => 'Master Sudah Obsolete',
            'nomor_dokumen' => 'PS-SMR-OLD',
        ]);

        $this->actingAs($user)
            ->get(route('documents.obsolete.show', $document))
            ->assertOk()
            ->assertSee('Detail Dokumen Obsolete')
            ->assertSee('Dokumen Obsolete')
            ->assertSee('Obsolete')
            ->assertSee('Riwayat Dokumen')
            ->assertSee('Dokumen disetujui')
            ->assertDontSee('Ajukan Revisi')
            ->assertDontSee('Pengajuan Obsolete')
            ->assertDontSee('Jadikan Master')
            ->assertDontSee('data-obsolete-modal-open', false);

        $this->actingAs($user)
            ->get(route('documents.master.show', $document))
            ->assertNotFound();
    }

    public function test_obsolete_revision_detail_and_preview_accept_revision_transaction(): void
    {
        Storage::fake('local');

        $obsoleteStatus = StatusDocument::create(['nama_status' => StatusDocument::OBSOLETE]);
        $user = $this->userWithPermission('documents.obsolete.detail');
        $previewPermission = Permission::query()->firstOrCreate(
            ['code' => 'documents.obsolete.preview'],
            [
                'name' => 'documents.obsolete.preview',
                'module' => 'Manajemen Dokumen',
                'route' => 'documents.obsolete.files.preview',
                'action' => 'view',
            ],
        );
        $user->roles()->firstOrFail()->permissions()->syncWithoutDetaching([$previewPermission->id]);
        $master = $this->createDocument($user, $obsoleteStatus, [
            'nama_dokumen' => 'Master Sebelum Revisi',
            'nomor_dokumen' => 'PS-SMR-REVISION',
            'nomor_revisi' => 0,
        ]);
        $revision = $this->createDocument($user, $obsoleteStatus, [
            'nama_dokumen' => 'Master Sebelum Revisi',
            'nomor_dokumen' => 'PS-SMR-REVISION',
            'nomor_revisi' => 1,
            'revised_from' => $master->id,
            'request_type' => 'revision',
        ]);
        $file = DocumentFile::create([
            't_document_id' => $revision->id,
            'type_file' => 'revision_content',
            'path_file' => "documents/{$revision->id}/revision.pdf",
            'uploaded_by' => $user->id,
            'original_file_name' => 'revision.pdf',
            'stored_file_name' => 'revision.pdf',
            'file_size' => 3,
        ]);
        Storage::disk('local')->put($file->path_file, "%PDF-1.4\n% revision fixture");

        $this->actingAs($user)
            ->get(route('documents.obsolete.show', $revision))
            ->assertOk()
            ->assertSee('Detail Dokumen Obsolete')
            ->assertSee('Printout PDF Final')
            ->assertSee('data-lazy-pdf-preview', false)
            ->assertSee('data-lazy-pdf-load', false)
            ->assertSee(route('documents.obsolete.generated.show', $revision), false)
            ->assertDontSee('<iframe src="'.route('documents.obsolete.generated.show', $revision), false)
            ->assertDontSee('revision.pdf');

        $this->actingAs($user)
            ->get(route('documents.obsolete.files.preview', [$revision, $file]))
            ->assertNotFound();
    }

    public function test_regular_user_cannot_restore_obsolete_document_as_master(): void
    {
        $obsoleteStatus = StatusDocument::create(['nama_status' => StatusDocument::OBSOLETE]);
        StatusDocument::create(['nama_status' => StatusDocument::APPROVED]);
        $user = $this->userWithoutPermission('documents.obsolete.restore');
        $document = $this->createDocument($user, $obsoleteStatus, [
            'nama_dokumen' => 'Master Tidak Boleh Restore',
            'nomor_dokumen' => 'PS-SMR-NO-RESTORE',
        ]);

        $this->actingAs($user)
            ->post(route('documents.obsolete.restore', $document))
            ->assertForbidden();

        $this->assertSame(StatusDocument::OBSOLETE, $document->refresh()->status->nama_status);
    }

    public function test_obsolete_revision_can_be_restored_as_master_and_only_older_versions_become_children(): void
    {
        $obsoleteStatus = StatusDocument::create(['nama_status' => StatusDocument::OBSOLETE]);
        StatusDocument::create(['nama_status' => StatusDocument::APPROVED]);
        $owner = User::factory()->create();

        $rootDocument = $this->createDocument($owner, $obsoleteStatus, [
            'nama_dokumen' => 'Prosedur Restore',
            'nomor_dokumen' => 'PS-SMR-RESTORE',
            'nomor_revisi' => 0,
            'approved_at' => now()->subDays(6),
        ]);

        $revisions = collect(range(1, 5))->map(function (int $revisionNumber) use ($owner, $obsoleteStatus, $rootDocument): Document {
            return $this->createDocument($owner, $obsoleteStatus, [
                'nama_dokumen' => 'Prosedur Restore Revisi '.$revisionNumber,
                'nomor_dokumen' => 'FMPS-SMR-RESTORE',
                'nomor_revisi' => $revisionNumber,
                'revised_from' => $rootDocument->id,
                'approved_at' => now()->subDays(6 - $revisionNumber),
            ]);
        });
        $selectedRevision = $revisions->firstWhere('nomor_revisi', 3);
        $documentControlAdmin = $this->userWithPermission('documents.obsolete.restore');

        $this->actingAs($documentControlAdmin)
            ->get(route('documents.obsolete.show', $selectedRevision))
            ->assertOk()
            ->assertSee('Jadikan Master');

        $this->actingAs($documentControlAdmin)
            ->post(route('documents.obsolete.restore', $selectedRevision))
            ->assertRedirect(route('documents.master.show', $selectedRevision));

        $this->assertSame(StatusDocument::APPROVED, $selectedRevision->refresh()->status->nama_status);
        $this->assertSame(StatusDocument::OBSOLETE, $rootDocument->refresh()->status->nama_status);
        $this->assertSame(StatusDocument::OBSOLETE, $revisions->firstWhere('nomor_revisi', 5)->refresh()->status->nama_status);

        $response = $this->actingAs($documentControlAdmin)
            ->get(route('documents.master'))
            ->assertOk()
            ->assertSee('Prosedur Restore Revisi 3')
            ->assertSee('00.03')
            ->assertSee('00.00')
            ->assertSee('00.01')
            ->assertSee('00.02')
            ->assertSee(route('documents.master.show', $selectedRevision), false);

        $this->assertStringNotContainsString('00.04', $response->getContent());
        $this->assertStringNotContainsString('00.05', $response->getContent());
        $this->assertStringNotContainsString(route('documents.master.show', $revisions->firstWhere('nomor_revisi', 4)), $response->getContent());
        $this->assertStringNotContainsString(route('documents.master.show', $revisions->firstWhere('nomor_revisi', 5)), $response->getContent());
    }

    public function test_approved_obsolete_request_transaction_does_not_block_restore_as_master(): void
    {
        $obsoleteStatus = StatusDocument::create(['nama_status' => StatusDocument::OBSOLETE]);
        $approvedStatus = StatusDocument::create(['nama_status' => StatusDocument::APPROVED]);
        $owner = User::factory()->create();

        $rootDocument = $this->createDocument($owner, $obsoleteStatus, [
            'nama_dokumen' => 'Prosedur Restore Dengan Request',
            'nomor_dokumen' => 'PS-SMR-REQ',
            'nomor_revisi' => 0,
            'approved_at' => now()->subDays(3),
        ]);

        $selectedRevision = $this->createDocument($owner, $obsoleteStatus, [
            'nama_dokumen' => 'Prosedur Restore Dengan Request Revisi',
            'nomor_dokumen' => 'FMPS-SMR-REQ',
            'nomor_revisi' => 1,
            'revised_from' => $rootDocument->id,
            'approved_at' => now()->subDays(2),
        ]);

        $this->createDocument($owner, $approvedStatus, [
            'nama_dokumen' => 'Request Obsolete Approved',
            'nomor_dokumen' => 'FMPS-SMR-REQ',
            'nomor_revisi' => 2,
            'revised_from' => $rootDocument->id,
            'request_type' => 'obsolete',
            'approved_at' => now()->subDay(),
        ]);

        $documentControlAdmin = $this->userWithPermission('documents.obsolete.restore');

        $this->actingAs($documentControlAdmin)
            ->get(route('documents.master'))
            ->assertOk()
            ->assertDontSee('Request Obsolete Approved');

        $this->actingAs($documentControlAdmin)
            ->post(route('documents.obsolete.restore', $selectedRevision))
            ->assertRedirect(route('documents.master.show', $selectedRevision))
            ->assertSessionMissing('restore_warning');

        $this->assertSame(StatusDocument::APPROVED, $selectedRevision->refresh()->status->nama_status);
    }

    public function test_obsolete_revision_restore_is_blocked_when_same_family_still_has_active_master(): void
    {
        $obsoleteStatus = StatusDocument::create(['nama_status' => StatusDocument::OBSOLETE]);
        $approvedStatus = StatusDocument::create(['nama_status' => StatusDocument::APPROVED]);
        $owner = User::factory()->create();

        $rootDocument = $this->createDocument($owner, $obsoleteStatus, [
            'nama_dokumen' => 'Prosedur Restore Blocked',
            'nomor_dokumen' => 'PS-SMR-BLOCK',
            'nomor_revisi' => 0,
        ]);
        $olderRevision = $this->createDocument($owner, $obsoleteStatus, [
            'nama_dokumen' => 'Prosedur Restore Blocked Revisi 2',
            'nomor_dokumen' => 'FMPS-SMR-BLOCK',
            'nomor_revisi' => 2,
            'revised_from' => $rootDocument->id,
        ]);
        $activeMaster = $this->createDocument($owner, $approvedStatus, [
            'nama_dokumen' => 'Prosedur Restore Blocked Revisi 3',
            'nomor_dokumen' => 'FMPS-SMR-BLOCK',
            'nomor_revisi' => 3,
            'revised_from' => $rootDocument->id,
            'request_type' => 'revision',
        ]);
        $newerRevision = $this->createDocument($owner, $obsoleteStatus, [
            'nama_dokumen' => 'Prosedur Restore Blocked Revisi 5',
            'nomor_dokumen' => 'FMPS-SMR-BLOCK',
            'nomor_revisi' => 5,
            'revised_from' => $rootDocument->id,
        ]);
        $documentDepartment = $rootDocument->departments()->firstOrFail();
        $olderRevision->departments()->sync([$documentDepartment->id]);
        $activeMaster->departments()->sync([$documentDepartment->id]);
        $newerRevision->departments()->sync([$documentDepartment->id]);
        $documentControlAdmin = $this->userWithPermission('documents.obsolete.restore');

        $this->actingAs($documentControlAdmin)
            ->get(route('documents.obsolete.show', $olderRevision))
            ->assertOk()
            ->assertDontSee('Jadikan Master');

        $this->actingAs($documentControlAdmin)
            ->post(route('documents.obsolete.restore', $olderRevision))
            ->assertRedirect(route('documents.obsolete.show', $olderRevision))
            ->assertSessionHas('restore_warning.message', 'Versi terbaru 00.03 masih menjadi master. Silakan obsolete-kan versi terbaru dulu.');

        $this->assertSame(StatusDocument::OBSOLETE, $olderRevision->refresh()->status->nama_status);
        $this->assertSame(StatusDocument::APPROVED, $activeMaster->refresh()->status->nama_status);

        $this->actingAs($documentControlAdmin)
            ->get(route('documents.obsolete.show', $newerRevision))
            ->assertOk()
            ->assertDontSee('Jadikan Master');

        $this->actingAs($documentControlAdmin)
            ->post(route('documents.obsolete.restore', $newerRevision))
            ->assertRedirect(route('documents.obsolete.show', $newerRevision))
            ->assertSessionHas('restore_warning.message', 'Versi 00.03 masih menjadi master. Silakan obsolete-kan versi 00.03 dulu.');

        $this->assertSame(StatusDocument::OBSOLETE, $newerRevision->refresh()->status->nama_status);
        $this->assertSame(StatusDocument::APPROVED, $activeMaster->refresh()->status->nama_status);
    }

    public function test_user_from_other_department_cannot_submit_master_obsolete_request(): void
    {
        $approvedStatus = StatusDocument::create(['nama_status' => StatusDocument::APPROVED]);
        StatusDocument::create(['nama_status' => StatusDocument::PROPOSED]);
        $owner = User::factory()->create();
        $document = $this->createDocument($owner, $approvedStatus, [
            'nama_dokumen' => 'Master Tidak Boleh Obsolete',
            'nomor_dokumen' => 'PS-SMR-NO-OBS',
        ]);
        $otherDepartment = Department::create([
            'kode_department' => 'FN',
            'nama_department' => 'Finance',
        ]);
        $otherDepartmentUser = $this->userWithoutPermission('documents.obsolete.create', [
            'm_department_id' => $otherDepartment->id,
        ]);

        $this->actingAs($otherDepartmentUser)
            ->post(route('documents.master.obsolete', $document), [
                'catatan_obsolete' => 'Dokumen sudah tidak digunakan.',
            ])
            ->assertForbidden();

        $this->assertFalse(
            Document::query()
                ->where('revised_from', $document->id)
                ->where('request_type', 'obsolete')
                ->exists(),
        );
    }

    public function test_user_from_document_department_can_submit_master_obsolete_request(): void
    {
        $approvedStatus = StatusDocument::create(['nama_status' => StatusDocument::APPROVED]);
        StatusDocument::create(['nama_status' => StatusDocument::PROPOSED]);
        $owner = User::factory()->create();
        $document = $this->createDocument($owner, $approvedStatus, [
            'nama_dokumen' => 'Master Jadi Obsolete',
            'nomor_dokumen' => 'PS-SMR-OBS',
        ]);
        $documentDepartment = $document->departments()->firstOrFail();
        $obsoleteUser = $this->userWithoutPermission('documents.obsolete.create', [
            'm_department_id' => $documentDepartment->id,
        ]);

        $this->actingAs($obsoleteUser)
            ->post(route('documents.master.obsolete', $document), [
                'catatan_obsolete' => 'Dokumen sudah tidak digunakan.',
            ])
            ->assertRedirect(route('documents.inbox'));

        $this->assertSame(StatusDocument::APPROVED, $document->refresh()->status->nama_status);

        $request = Document::query()
            ->where('revised_from', $document->id)
            ->where('request_type', 'obsolete')
            ->firstOrFail();

        $this->assertSame(StatusDocument::PROPOSED, $request->status->nama_status);
        $this->assertSame($document->documentType->nama_types, $request->documentType->nama_types);
        $this->assertSame($document->documentLevel->kode, $request->documentLevel->kode);
        $this->assertSame('PS-SMR-OBS', $request->nomor_dokumen);
        $this->assertSame('Dokumen sudah tidak digunakan.', $request->catatan_revisi);
    }

    private function userWithPermission(string $permissionCode): User
    {
        $permission = Permission::query()->firstOrCreate(
            ['code' => $permissionCode],
            [
                'name' => $permissionCode,
                'module' => 'Manajemen Dokumen',
                'route' => match ($permissionCode) {
                    'documents.master.view' => 'documents.master',
                    'documents.master.detail' => 'documents.master.show',
                    'documents.master.imported.detail' => 'documents.master.imported.show',
                    'documents.master.imports.create' => 'documents.master.imports.create',
                    'documents.obsolete.detail' => 'documents.obsolete.show',
                    'documents.obsolete.imports.create' => 'documents.obsolete.imports.create',
                    'documents.existing.imports.revision' => 'documents.existing.imports.revisions.store',
                    'documents.obsolete.restore' => 'documents.obsolete.restore',
                    default => 'documents.obsolete',
                },
                'action' => match ($permissionCode) {
                    'documents.master.imports.create', 'documents.obsolete.create', 'documents.obsolete.imports.create', 'documents.existing.imports.revision' => 'create',
                    'documents.obsolete.restore' => 'restore',
                    default => 'view',
                },
            ],
        );
        $role = Role::query()->firstOrCreate(['nama_role' => 'Role '.$permissionCode]);
        $user = User::factory()->create();

        $role->permissions()->syncWithoutDetaching([$permission->id]);
        $masterDetailPermission = Permission::query()->firstOrCreate(
            ['code' => 'documents.master.detail'],
            [
                'name' => 'documents.master.detail',
                'module' => 'Manajemen Dokumen',
                'route' => 'documents.master.show',
                'action' => 'view',
            ],
        );

        $role->permissions()->syncWithoutDetaching([$masterDetailPermission->id]);

        if (in_array($permissionCode, ['documents.obsolete.create', 'documents.obsolete.imports.create', 'documents.obsolete.restore'], true)) {
            $viewPermission = Permission::query()->firstOrCreate(
                ['code' => 'documents.obsolete.view'],
                [
                    'name' => 'documents.obsolete.view',
                    'module' => 'Manajemen Dokumen',
                    'route' => 'documents.obsolete',
                    'action' => 'view',
                ],
            );

            $role->permissions()->syncWithoutDetaching([$viewPermission->id]);
        }

        if (in_array($permissionCode, ['documents.master.imports.create', 'documents.master.imported.detail'], true)) {
            $masterViewPermission = Permission::query()->firstOrCreate(
                ['code' => 'documents.master.view'],
                [
                    'name' => 'documents.master.view',
                    'module' => 'Manajemen Dokumen',
                    'route' => 'documents.master',
                    'action' => 'view',
                ],
            );

            $role->permissions()->syncWithoutDetaching([$masterViewPermission->id]);
        }

        if ($permissionCode === 'documents.master.imported.detail') {
            foreach ([
                ['documents.existing.imports.revision', 'documents.existing.imports.revisions.store', 'create'],
                ['documents.master.imported.obsolete', 'documents.master.imported.obsolete', 'create'],
                ['documents.existing.imports.detail', 'documents.existing.imports.show', 'view'],
                ['documents.existing.imports.download', 'documents.existing.imports.files.show', 'download'],
                ['documents.existing.imports.preview', 'documents.existing.imports.files.preview', 'preview'],
            ] as [$code, $route, $action]) {
                $permission = Permission::query()->firstOrCreate(
                    ['code' => $code],
                    [
                        'name' => $code,
                        'module' => 'Manajemen Dokumen',
                        'route' => $route,
                        'action' => $action,
                    ],
                );

                $role->permissions()->syncWithoutDetaching([$permission->id]);
            }
        }

        if ($permissionCode === 'documents.obsolete.restore') {
            $detailPermission = Permission::query()->firstOrCreate(
                ['code' => 'documents.obsolete.detail'],
                [
                    'name' => 'documents.obsolete.detail',
                    'module' => 'Manajemen Dokumen',
                    'route' => 'documents.obsolete.show',
                    'action' => 'view',
                ],
            );

            $role->permissions()->syncWithoutDetaching([$detailPermission->id]);

            $masterViewPermission = Permission::query()->firstOrCreate(
                ['code' => 'documents.master.view'],
                [
                    'name' => 'documents.master.view',
                    'module' => 'Manajemen Dokumen',
                    'route' => 'documents.master',
                    'action' => 'view',
                ],
            );

            $role->permissions()->syncWithoutDetaching([$masterViewPermission->id]);
        }
        $user->roles()->attach($role);

        return $user->refresh();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function userWithoutPermission(string $permissionCode, array $attributes = []): User
    {
        Permission::query()->firstOrCreate(
            ['code' => $permissionCode],
            [
                'name' => $permissionCode,
                'module' => 'Manajemen Dokumen',
                'route' => match ($permissionCode) {
                    'documents.master.view' => 'documents.master',
                    'documents.master.detail' => 'documents.master.show',
                    'documents.master.imported.detail' => 'documents.master.imported.show',
                    'documents.obsolete.detail' => 'documents.obsolete.show',
                    'documents.obsolete.restore' => 'documents.obsolete.restore',
                    default => 'documents.obsolete',
                },
                'action' => match ($permissionCode) {
                    'documents.obsolete.create' => 'create',
                    'documents.obsolete.restore' => 'restore',
                    default => 'view',
                },
            ],
        );
        $role = Role::query()->firstOrCreate(['nama_role' => 'Role tanpa '.$permissionCode]);
        $user = User::factory()->create($attributes);
        $masterDetailPermission = Permission::query()->firstOrCreate(
            ['code' => 'documents.master.detail'],
            [
                'name' => 'documents.master.detail',
                'module' => 'Manajemen Dokumen',
                'route' => 'documents.master.show',
                'action' => 'view',
            ],
        );

        $role->permissions()->syncWithoutDetaching([$masterDetailPermission->id]);
        $user->roles()->attach($role);

        return $user->refresh();
    }

    private function masterMetadata(): array
    {
        $level = DocumentLevel::query()->where('kode', 'level-2')->firstOrFail();
        $documentType = DocumentType::create(['nama_types' => fake()->unique()->word()]);
        $businessProcess = BusinessProcess::create([
            'kode' => fake()->unique()->lexify('???'),
            'nama_proses_bisnis' => fake()->unique()->words(3, true),
        ]);
        $businessFunction = BusinessFunction::create([
            'kode' => fake()->unique()->lexify('???'),
            'nama_proses_fungsi' => fake()->unique()->words(3, true),
            'm_proses_bisnis_id' => $businessProcess->id,
        ]);

        return [$level, $documentType, $businessProcess, $businessFunction];
    }

    private function createImportedExistingMaster(User $user, array $attributes = []): Document
    {
        [$level, $documentType, $businessProcess, $businessFunction] = $this->masterMetadata();
        $approvedStatus = StatusDocument::query()->firstOrCreate(['nama_status' => StatusDocument::APPROVED]);

        return Document::create($attributes + [
            'origin' => Document::ORIGIN_IMPORTED_CURRENT,
            'm_status_document_id' => $approvedStatus->id,
            'm_document_level_id' => $level->id,
            'm_document_types_id' => $documentType->id,
            'm_proses_bisnis_id' => $businessProcess->id,
            'm_proses_fungsi_id' => $businessFunction->id,
            'user_id' => $user->id,
            'official_preparer_id' => $user->id,
            'nama_dokumen' => 'Imported Existing Master',
            'nomor_dokumen' => 'PS-SMR-IMP',
            'nomor_revisi' => '00.00',
            'tanggal_terbit' => now()->toDateString(),
            'approved_at' => now(),
        ]);
    }

    private function createImportedExistingObsolete(User $user, array $attributes = []): Document
    {
        [$level, $documentType, $businessProcess, $businessFunction] = $this->masterMetadata();
        $obsoleteStatus = StatusDocument::query()->firstOrCreate(['nama_status' => StatusDocument::OBSOLETE]);

        return Document::create($attributes + [
            'origin' => Document::ORIGIN_IMPORTED_LEGACY,
            'm_status_document_id' => $obsoleteStatus->id,
            'm_document_level_id' => $level->id,
            'm_document_types_id' => $documentType->id,
            'm_proses_bisnis_id' => $businessProcess->id,
            'm_proses_fungsi_id' => $businessFunction->id,
            'user_id' => $user->id,
            'official_preparer_id' => $user->id,
            'nama_dokumen' => 'Imported Existing Obsolete',
            'nomor_dokumen' => 'PS-SMR-OBS-IMP',
            'nomor_revisi' => '00.00',
            'tanggal_terbit' => now()->subMonths(6)->toDateString(),
            'obsolete_at' => now(),
        ]);
    }

    private function createDocument(User $user, StatusDocument $status, array $attributes = []): Document
    {
        $documentType = DocumentType::create(['nama_types' => fake()->unique()->word()]);
        $businessProcess = BusinessProcess::create([
            'kode' => fake()->unique()->lexify('???'),
            'nama_proses_bisnis' => fake()->unique()->words(3, true),
        ]);
        $businessFunction = BusinessFunction::create([
            'kode' => fake()->unique()->lexify('???'),
            'nama_proses_fungsi' => fake()->unique()->words(3, true),
            'm_proses_bisnis_id' => $businessProcess->id,
        ]);
        $department = Department::create([
            'kode_department' => fake()->unique()->lexify('??'),
            'nama_department' => fake()->unique()->words(2, true),
        ]);
        $level = DocumentLevel::query()->where('kode', 'level-2')->firstOrFail();

        $document = Document::create($attributes + [
            'm_document_level_id' => $level->id,
            'm_status_document_id' => $status->id,
            'm_document_types_id' => $documentType->id,
            'm_proses_bisnis_id' => $businessProcess->id,
            'm_proses_fungsi_id' => $businessFunction->id,
            'user_id' => $user->id,
            'official_preparer_id' => $user->id,
            'nama_dokumen' => 'Dokumen Master',
            'nomor_dokumen' => 'PS-SMR-001',
            'nomor_revisi' => 0,
            'tanggal_terbit' => now()->toDateString(),
            'approved_at' => now(),
        ]);
        $document->departments()->sync([$department->id]);

        return $document;
    }
}
