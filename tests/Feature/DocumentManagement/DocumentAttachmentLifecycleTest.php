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
use App\Models\Permission;
use App\Models\Role;
use App\Models\StatusDocument;
use App\Models\User;
use App\Support\FinalDocuments\FinalArtifactGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DocumentAttachmentLifecycleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');

        foreach ([StatusDocument::DRAFT, StatusDocument::PROPOSED, StatusDocument::APPROVED, StatusDocument::REJECTED, StatusDocument::CANCELLED, StatusDocument::OBSOLETE] as $status) {
            StatusDocument::query()->firstOrCreate(['nama_status' => $status]);
        }

        foreach ([
            ApprovalStatus::PENDING => 'Menunggu',
            ApprovalStatus::APPROVED => 'Disetujui',
            ApprovalStatus::REJECTED => 'Ditolak',
            ApprovalStatus::WAITING => 'Menunggu Giliran',
            ApprovalStatus::TERMINATED => 'Dihentikan',
        ] as $code => $name) {
            ApprovalStatus::query()->firstOrCreate(['kode_status' => $code], ['nama_status' => $name]);
        }

        DocumentType::query()->firstOrCreate(['nama_types' => 'Prosedur']);
        DocumentType::query()->firstOrCreate(['nama_types' => 'IK']);
        DocumentType::query()->firstOrCreate(['nama_types' => 'Form']);

        $role = Role::query()->firstOrCreate(['nama_role' => 'User']);
        $permissions = collect([
            ['code' => 'documents.create.view', 'route' => 'documents.create', 'action' => 'view'],
            ['code' => 'documents.create.level', 'route' => 'documents.create.level', 'action' => 'view'],
            ['code' => 'documents.create.create', 'route' => 'documents.store', 'action' => 'create'],
            ['code' => 'documents.create.drafts', 'route' => 'documents.create.drafts', 'action' => 'view'],
            ['code' => 'documents.existing.imports.revision', 'route' => 'documents.existing.imports.revisions.store', 'action' => 'create'],
        ])->map(fn (array $permission): Permission => Permission::query()->firstOrCreate(
            ['code' => $permission['code']],
            [
                'name' => $permission['code'],
                'module' => 'Manajemen Dokumen',
                'route' => $permission['route'],
                'action' => $permission['action'],
            ],
        ));

        $role->permissions()->syncWithoutDetaching($permissions->pluck('id')->all());
    }

    public function test_draft_files_keep_document_number_null(): void
    {
        [$user, $businessProcess, $businessFunction, $department] = $this->baseFixture();

        $this->actingAs($user)
            ->post(route('documents.store', 'level-3'), [
                'nama_dokumen' => 'Draft IK Tanpa Nomor',
                'm_proses_bisnis_id' => $businessProcess->id,
                'm_proses_fungsi_id' => $businessFunction->id,
                'department_ids' => [$department->id],
                'filled_template' => UploadedFile::fake()->create('template.pdf', 24, 'application/pdf'),
                'attachment_titles' => ['Lampiran Draft'],
                'attachments' => [
                    UploadedFile::fake()->create('lampiran-draft.pdf', 24, 'application/pdf'),
                ],
                'submit_action' => 'draft',
            ])
            ->assertRedirect(route('documents.create.drafts'));

        $document = Document::query()->where('nama_dokumen', 'Draft IK Tanpa Nomor')->firstOrFail();

        $this->assertNull($document->nomor_dokumen);
        $this->assertTrue($document->files()->whereNull('document_number')->where('type_file', 'filled_template')->exists());
        $this->assertTrue($document->files()->whereNull('document_number')->where('type_file', 'attachment')->exists());
    }

    public function test_revision_can_carry_forward_source_attachment_with_same_number_and_copied_file(): void
    {
        [$source, $submitter, $officialPreparer] = $this->revisionFixture();
        $sourceAttachment = $this->storeDocumentFile($source, 'attachment', [
            'attachment_title' => 'Lampiran Risiko',
            'attachment_order' => 1,
            'document_number' => 'FMPS-SMR-010-02',
            'path_file' => "documents/{$source->id}/lampiran-risiko.pdf",
            'original_file_name' => 'lampiran-risiko.pdf',
            'stored_file_name' => 'lampiran-risiko.pdf',
        ]);

        $this->actingAs($submitter)
            ->post(route('documents.store', 'level-4'), $this->revisionPayload($source, $officialPreparer, [
                'nama_dokumen' => 'Revisi Cantum Lampiran',
                'included_attachment_ids' => [$sourceAttachment->id],
            ]))
            ->assertRedirect(route('documents.create'));

        $revision = Document::query()->where('nama_dokumen', 'Revisi Cantum Lampiran')->firstOrFail();
        $attachment = $revision->files()->where('type_file', 'attachment')->firstOrFail();

        $this->assertSame($sourceAttachment->id, $attachment->source_file_id);
        $this->assertSame('FMPS-SMR-010-02', $attachment->document_number);
        $this->assertNotSame($sourceAttachment->path_file, $attachment->path_file);
        Storage::disk('local')->assertExists($attachment->path_file);
    }

    public function test_revision_can_submit_with_all_source_attachments_unchecked(): void
    {
        [$source, $submitter, $officialPreparer] = $this->revisionFixture();
        $firstAttachment = $this->storeDocumentFile($source, 'attachment', [
            'attachment_title' => 'Lampiran Risiko',
            'attachment_order' => 1,
            'document_number' => 'FMPS-SMR-010-02',
            'path_file' => "documents/{$source->id}/lampiran-risiko.pdf",
            'original_file_name' => 'lampiran-risiko.pdf',
            'stored_file_name' => 'lampiran-risiko.pdf',
        ]);
        $secondAttachment = $this->storeDocumentFile($source, 'attachment', [
            'attachment_title' => 'Lampiran BAPP',
            'attachment_order' => 2,
            'document_number' => 'FMPS-SMR-010-03',
            'path_file' => "documents/{$source->id}/lampiran-bapp.pdf",
            'original_file_name' => 'lampiran-bapp.pdf',
            'stored_file_name' => 'lampiran-bapp.pdf',
        ]);

        $payload = $this->revisionPayload($source, $officialPreparer, [
            'nama_dokumen' => 'Revisi Semua Lampiran Tidak Dicentang',
        ]);
        unset($payload['included_attachment_ids']);

        $this->actingAs($submitter)
            ->post(route('documents.store', 'level-4'), $payload)
            ->assertRedirect(route('documents.create'));

        $revision = Document::query()->where('nama_dokumen', 'Revisi Semua Lampiran Tidak Dicentang')->firstOrFail();

        $this->assertFalse($revision->files()->where('type_file', 'attachment')->where('source_file_id', $firstAttachment->id)->exists());
        $this->assertFalse($revision->files()->where('type_file', 'attachment')->where('source_file_id', $secondAttachment->id)->exists());
        $this->assertFalse($revision->files()->where('type_file', 'attachment')->exists());
    }

    public function test_unchecked_source_attachment_remains_available_for_next_revision(): void
    {
        [$source, $submitter, $officialPreparer] = $this->revisionFixture();
        $firstAttachment = $this->storeDocumentFile($source, 'attachment', [
            'attachment_title' => 'Lampiran Risiko',
            'attachment_order' => 1,
            'document_number' => 'FMPS-SMR-010-02',
            'path_file' => "documents/{$source->id}/lampiran-risiko.pdf",
            'original_file_name' => 'lampiran-risiko.pdf',
            'stored_file_name' => 'lampiran-risiko.pdf',
        ]);
        $secondAttachment = $this->storeDocumentFile($source, 'attachment', [
            'attachment_title' => 'Lampiran BAPP',
            'attachment_order' => 2,
            'document_number' => 'FMPS-SMR-010-03',
            'path_file' => "documents/{$source->id}/lampiran-bapp.pdf",
            'original_file_name' => 'lampiran-bapp.pdf',
            'stored_file_name' => 'lampiran-bapp.pdf',
        ]);

        $this->actingAs($submitter)
            ->post(route('documents.store', 'level-4'), $this->revisionPayload($source, $officialPreparer, [
                'nama_dokumen' => 'Revisi Dengan Lampiran Tidak Dicentang',
                'included_attachment_ids' => [$firstAttachment->id],
            ]))
            ->assertRedirect(route('documents.create'));

        $revision = Document::query()->where('nama_dokumen', 'Revisi Dengan Lampiran Tidak Dicentang')->firstOrFail();

        $this->assertTrue($revision->files()->where('type_file', 'attachment')->where('source_file_id', $firstAttachment->id)->exists());
        $this->assertFalse($revision->files()->where('type_file', 'attachment')->where('source_file_id', $secondAttachment->id)->exists());

        $revision->forceFill([
            'm_status_document_id' => StatusDocument::findByName(StatusDocument::APPROVED)->id,
            'approved_at' => now(),
        ])->save();

        $response = $this->actingAs($submitter)
            ->get(route('documents.create.level', ['level-4', 'revised_from' => $revision->id]))
            ->assertOk()
            ->assertSeeInOrder([
                'Lampiran Master Sebelumnya',
                'Dicantumkan',
                'Lampiran Risiko',
                'FMPS-SMR-010-02',
                'Tidak Dicantumkan',
                'Lampiran BAPP',
                'FMPS-SMR-010-03',
            ]);

        $content = $response->getContent();
        $this->assertMatchesRegularExpression(
            '/name="included_attachment_ids\[\]"\s+value="'.$revision->files()->where('type_file', 'attachment')->firstOrFail()->id.'"\s+data-master-attachment-include(?!\s+disabled)/',
            $content,
        );
        $this->assertMatchesRegularExpression(
            '/name="included_attachment_ids\[\]"\s+value="'.$secondAttachment->id.'"\s+data-master-attachment-include\s+disabled/',
            $content,
        );

        $this->actingAs($submitter)
            ->post(route('documents.store', 'level-4'), $this->revisionPayload($revision, $officialPreparer, [
                'nama_dokumen' => 'Revisi Berikutnya Mengikuti Checkbox Lampiran',
                'included_attachment_ids' => $revision->files()->where('type_file', 'attachment')->pluck('id')->all(),
            ]))
            ->assertRedirect(route('documents.create'));

        $nextRevision = Document::query()->where('nama_dokumen', 'Revisi Berikutnya Mengikuti Checkbox Lampiran')->firstOrFail();

        $this->assertTrue($nextRevision->files()->where('type_file', 'attachment')->where('document_number', 'FMPS-SMR-010-02')->exists());
        $this->assertFalse($nextRevision->files()->where('type_file', 'attachment')->where('document_number', 'FMPS-SMR-010-03')->exists());
    }

    public function test_revision_attachment_replacement_keeps_source_number_and_lineage(): void
    {
        [$source, $submitter, $officialPreparer] = $this->revisionFixture();
        $sourceAttachment = $this->storeDocumentFile($source, 'attachment', [
            'attachment_title' => 'Lampiran Mutu',
            'attachment_order' => 1,
            'document_number' => 'FMPS-SMR-010-02',
            'path_file' => "documents/{$source->id}/lampiran-mutu.pdf",
            'original_file_name' => 'lampiran-mutu.pdf',
            'stored_file_name' => 'lampiran-mutu.pdf',
        ]);

        $this->actingAs($submitter)
            ->post(route('documents.store', 'level-4'), $this->revisionPayload($source, $officialPreparer, [
                'nama_dokumen' => 'Revisi File Lampiran',
                'included_attachment_ids' => [$sourceAttachment->id],
                'revised_attachments' => [
                    $sourceAttachment->id => UploadedFile::fake()->create('lampiran-mutu-revisi.pdf', 24, 'application/pdf'),
                ],
            ]))
            ->assertRedirect(route('documents.create'));

        $revision = Document::query()->where('nama_dokumen', 'Revisi File Lampiran')->firstOrFail();
        $attachment = $revision->files()->where('type_file', 'attachment')->firstOrFail();

        $this->assertSame($sourceAttachment->id, $attachment->source_file_id);
        $this->assertSame('FMPS-SMR-010-02', $attachment->document_number);
        $this->assertSame('lampiran-mutu-revisi.pdf', $attachment->original_file_name);
        $this->assertSame('Lampiran Mutu', $attachment->attachment_title);
    }

    public function test_new_revision_attachment_uses_next_family_suffix_after_carried_forward_attachments(): void
    {
        [$source, $submitter, $officialPreparer] = $this->revisionFixture();
        $firstAttachment = $this->storeDocumentFile($source, 'attachment', [
            'attachment_title' => 'Lampiran Pertama',
            'attachment_order' => 1,
            'document_number' => 'FMPS-SMR-010-02',
        ]);
        $secondAttachment = $this->storeDocumentFile($source, 'attachment', [
            'attachment_title' => 'Lampiran Kedua',
            'attachment_order' => 2,
            'document_number' => 'FMPS-SMR-010-03',
        ]);

        $this->actingAs($submitter)
            ->post(route('documents.store', 'level-4'), $this->revisionPayload($source, $officialPreparer, [
                'nama_dokumen' => 'Revisi Tambah Lampiran Baru',
                'included_attachment_ids' => [$firstAttachment->id, $secondAttachment->id],
                'attachment_titles' => ['Lampiran Baru'],
                'attachment_orders' => [3],
                'attachments' => [
                    UploadedFile::fake()->create('lampiran-baru.pdf', 24, 'application/pdf'),
                ],
            ]))
            ->assertRedirect(route('documents.create'));

        $revision = Document::query()->where('nama_dokumen', 'Revisi Tambah Lampiran Baru')->firstOrFail();
        $newAttachment = $revision->files()
            ->where('type_file', 'attachment')
            ->whereNull('source_file_id')
            ->firstOrFail();

        $this->assertSame('FMPS-SMR-010-04', $newAttachment->document_number);

        $response = $this->actingAs($submitter)
            ->get(route('documents.approval.show', $revision))
            ->assertOk();

        $response->assertSeeInOrder([
            'Lampiran Pertama',
            'Lampiran Kedua',
            'Lampiran Baru',
        ]);
    }

    public function test_revision_form_lineage_points_to_previous_revision_form(): void
    {
        [$source, $submitter, $officialPreparer] = $this->revisionFixture();

        $this->actingAs($submitter)
            ->post(route('documents.store', 'level-4'), $this->revisionPayload($source, $officialPreparer, [
                'nama_dokumen' => 'Revisi Pertama',
            ]))
            ->assertRedirect(route('documents.create'));

        $firstRevision = Document::query()->where('nama_dokumen', 'Revisi Pertama')->firstOrFail();
        $firstRevision->update([
            'm_status_document_id' => StatusDocument::findByName(StatusDocument::APPROVED)->id,
            'approved_at' => now(),
        ]);
        $firstRevisionForm = $firstRevision->files()->where('type_file', 'revision_form')->firstOrFail();

        $this->actingAs($submitter)
            ->post(route('documents.store', 'level-4'), $this->revisionPayload($source, $officialPreparer, [
                'nama_dokumen' => 'Revisi Kedua',
            ]))
            ->assertRedirect(route('documents.create'));

        $secondRevision = Document::query()->where('nama_dokumen', 'Revisi Kedua')->firstOrFail();
        $secondRevisionForm = $secondRevision->files()->where('type_file', 'revision_form')->firstOrFail();

        $this->assertNull($firstRevisionForm->source_file_id);
        $this->assertSame($firstRevisionForm->id, $secondRevisionForm->source_file_id);
        $this->assertSame('FMPS-SMR-010-01', $secondRevisionForm->document_number);
    }

    public function test_imported_existing_revision_assigns_file_numbers_from_imported_family(): void
    {
        [$user, $businessProcess, $businessFunction, $department] = $this->baseFixture();
        $level = DocumentLevel::query()->where('kode', 'level-2')->firstOrFail();
        $type = DocumentType::query()->where('nama_types', 'Prosedur')->firstOrFail();
        $approvedStatus = StatusDocument::findByName(StatusDocument::APPROVED);
        $source = Document::query()->create([
            'origin' => Document::ORIGIN_IMPORTED_CURRENT,
            'm_status_document_id' => $approvedStatus->id,
            'm_document_level_id' => $level->id,
            'm_document_types_id' => $type->id,
            'm_proses_bisnis_id' => $businessProcess->id,
            'm_proses_fungsi_id' => $businessFunction->id,
            'user_id' => $user->id,
            'nama_dokumen' => 'Imported Master Numbering',
            'nomor_dokumen' => 'PS-SMR-120',
            'nomor_revisi' => '00.01',
        ]);
        $source->departments()->sync([$department->id]);

        $this->actingAs($user)
            ->post(route('documents.store', 'level-4'), [
                'revised_from' => $source->id,
                'submit_action' => 'submit',
                'nama_dokumen' => 'Imported Master Numbering Rev',
                'm_proses_bisnis_id' => $businessProcess->id,
                'm_proses_fungsi_id' => $businessFunction->id,
                'department_ids' => [$department->id],
                'official_preparer_id' => $user->id,
                'revision_content' => UploadedFile::fake()->create('revision-content.pdf', 24, 'application/pdf'),
                'revision_content_word' => UploadedFile::fake()->create('revision-content.docx', 24, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'),
                'revision_form' => UploadedFile::fake()->create('revision-form.pdf', 24, 'application/pdf'),
                'revision_form_word' => UploadedFile::fake()->create('revision-form.docx', 24, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'),
                'attachment_titles' => ['Lampiran Imported'],
                'attachment_orders' => [1],
                'attachments' => [
                    UploadedFile::fake()->create('lampiran-imported.pdf', 24, 'application/pdf'),
                ],
            ])
            ->assertRedirect();

        $revision = Document::query()->where('nama_dokumen', 'Imported Master Numbering Rev')->firstOrFail();

        $this->assertSame('FMPS-SMR-120-01', $revision->nomor_lembar_revisi);
        $this->assertTrue($revision->files()->where('type_file', 'revision_content')->where('document_number', 'PS-SMR-120')->exists());
        $this->assertTrue($revision->files()->where('type_file', 'revision_form')->where('document_number', 'FMPS-SMR-120-01')->exists());
        $this->assertTrue($revision->files()->where('type_file', 'attachment')->where('document_number', 'FMPS-SMR-120-02')->exists());
    }

    public function test_final_attachment_payload_order_follows_official_document_number(): void
    {
        [$source] = $this->revisionFixture();
        $this->storeDocumentFile($source, 'attachment', [
            'attachment_title' => 'sketsa',
            'attachment_order' => 1,
            'document_number' => 'FMPS-SMR-010-03',
        ]);
        $this->storeDocumentFile($source, 'attachment', [
            'attachment_title' => 'Invoice',
            'attachment_order' => 2,
            'document_number' => 'FMPS-SMR-010-02',
        ]);

        $attachments = app(FinalArtifactGenerator::class)->collectAttachments($source);

        $this->assertSame('Invoice', $attachments[0]['title']);
        $this->assertSame('FMPS-SMR-010-02', $attachments[0]['document_number']);
        $this->assertSame(1, $attachments[0]['number']);
        $this->assertSame('sketsa', $attachments[1]['title']);
        $this->assertSame('FMPS-SMR-010-03', $attachments[1]['document_number']);
        $this->assertSame(2, $attachments[1]['number']);
    }

    public function test_revision_form_lists_source_attachments_by_official_document_number(): void
    {
        [$source, $submitter] = $this->revisionFixture();
        $this->storeDocumentFile($source, 'attachment', [
            'attachment_title' => 'sketsa',
            'attachment_order' => 1,
            'document_number' => 'FMPS-SMR-010-03',
            'original_file_name' => 'sketsa.pdf',
            'stored_file_name' => 'sketsa.pdf',
        ]);
        $this->storeDocumentFile($source, 'attachment', [
            'attachment_title' => 'Invoice',
            'attachment_order' => 2,
            'document_number' => 'FMPS-SMR-010-02',
            'original_file_name' => 'invoice.pdf',
            'stored_file_name' => 'invoice.pdf',
        ]);

        $response = $this->actingAs($submitter)
            ->get(route('documents.create.level', ['level-4', 'revised_from' => $source->id]))
            ->assertOk();

        $content = $response->getContent();
        $invoicePosition = strpos($content, 'Invoice');
        $sketsaPosition = strpos($content, 'sketsa');

        $this->assertStringContainsString('Lampiran Master Sebelumnya', $content);
        $this->assertStringContainsString('FMPS-SMR-010-02', $content);
        $this->assertStringContainsString('FMPS-SMR-010-03', $content);
        $this->assertNotFalse($invoicePosition);
        $this->assertNotFalse($sketsaPosition);
        $this->assertLessThan($sketsaPosition, $invoicePosition);
    }

    private function baseFixture(): array
    {
        $businessProcess = BusinessProcess::query()->firstOrCreate(['kode' => 'SMR'], ['nama_proses_bisnis' => 'Sistem Manajemen Risiko']);
        $businessFunction = BusinessFunction::query()->firstOrCreate(['kode' => 'OPS'], ['nama_proses_fungsi' => 'Operasional']);
        $department = Department::query()->firstOrCreate(['kode_department' => 'QA'], ['nama_department' => 'Quality Assurance']);
        $user = User::factory()->create(['m_department_id' => $department->id]);
        $role = Role::query()->where('nama_role', 'User')->firstOrFail();
        $user->roles()->syncWithoutDetaching([$role->id]);

        return [$user, $businessProcess, $businessFunction, $department];
    }

    private function revisionFixture(): array
    {
        [$submitter, $businessProcess, $businessFunction, $department] = $this->baseFixture();
        $officialPreparer = User::factory()->create();
        $level = DocumentLevel::query()->where('kode', 'level-2')->firstOrFail();
        $type = DocumentType::query()->where('nama_types', 'Prosedur')->firstOrFail();

        $source = Document::query()->create([
            'm_document_level_id' => $level->id,
            'm_status_document_id' => StatusDocument::findByName(StatusDocument::APPROVED)->id,
            'm_document_types_id' => $type->id,
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

    private function revisionPayload(Document $source, User $officialPreparer, array $overrides = []): array
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

    private function storeDocumentFile(Document $document, string $type, array $attributes = []): DocumentFile
    {
        $path = $attributes['path_file'] ?? "documents/{$document->id}/{$type}-".($attributes['attachment_order'] ?? 'main').'.pdf';
        Storage::disk('local')->put($path, 'PDF test content');

        return $document->files()->create($attributes + [
            'type_file' => $type,
            'path_file' => $path,
            'uploaded_by' => $document->user_id,
            'updated_at' => now(),
            'original_file_name' => basename($path),
            'stored_file_name' => basename($path),
            'file_size' => 16,
        ]);
    }
}
