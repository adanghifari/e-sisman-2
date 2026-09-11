<?php

namespace Tests\Feature\DocumentManagement;

use App\Models\DocumentTemplate;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DocumentTemplateControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_template_editor_can_upload_independent_active_templates_per_level(): void
    {
        Storage::fake('local');
        $this->seed(PermissionSeeder::class);

        $editor = $this->templateEditor();

        $this->actingAs($editor)
            ->post(route('document-templates.store'), [
                'document_level' => 'level-1',
                'title' => 'Template Level Satu',
                'notes' => 'Catatan level satu.',
                'template_files' => [
                    UploadedFile::fake()->create('level-satu.docx', 32, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'),
                ],
            ])
            ->assertRedirect(route('document-templates.index'));

        $this->actingAs($editor)
            ->post(route('document-templates.store'), [
                'document_level' => 'level-2',
                'title' => 'Template Level Dua',
                'notes' => 'Catatan level dua.',
                'template_files' => [
                    UploadedFile::fake()->create('level-dua.doc', 28, 'application/msword'),
                ],
            ])
            ->assertRedirect(route('document-templates.index'));

        $levelOne = DocumentTemplate::query()->forLevel('level-1')->active()->with('files')->firstOrFail();
        $levelTwo = DocumentTemplate::query()->forLevel('level-2')->active()->with('files')->firstOrFail();

        $this->assertSame('Template Level Satu', $levelOne->title);
        $this->assertSame('Template Level Dua', $levelTwo->title);
        $this->assertSame('level-satu.docx', $levelOne->files->first()->original_file_name);
        $this->assertSame('level-dua.doc', $levelTwo->files->first()->original_file_name);
        Storage::disk('local')->assertExists($levelOne->files->first()->path_file);
        Storage::disk('local')->assertExists($levelTwo->files->first()->path_file);
    }

    public function test_template_page_renders_saved_title_notes_and_file_after_reload(): void
    {
        Storage::fake('local');
        $this->seed(PermissionSeeder::class);

        $editor = $this->templateEditor();

        $this->actingAs($editor)
            ->post(route('document-templates.store'), [
                'document_level' => 'level-3',
                'title' => 'Template Instruksi Kerja',
                'notes' => 'Gunakan untuk dokumen instruksi kerja.',
                'template_files' => [
                    UploadedFile::fake()->create('instruksi-kerja.docx', 32, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'),
                ],
            ])
            ->assertRedirect(route('document-templates.index'));

        $this->actingAs($editor)
            ->get(route('document-templates.index'))
            ->assertOk()
            ->assertSee('Template Instruksi Kerja')
            ->assertSee('Gunakan untuk dokumen instruksi kerja.')
            ->assertSee('instruksi-kerja.docx');
    }

    public function test_template_viewer_cannot_upload_template(): void
    {
        Storage::fake('local');
        $this->seed(PermissionSeeder::class);

        $viewer = User::factory()->create();
        $viewerRole = Role::query()->create(['nama_role' => 'Template Viewer']);
        $viewPermission = Permission::query()->where('code', 'document-templates.view')->firstOrFail();

        $viewerRole->permissions()->sync([$viewPermission->id]);
        $viewerRole->users()->sync([$viewer->id]);

        $this->actingAs($viewer)
            ->post(route('document-templates.store'), [
                'document_level' => 'level-1',
                'title' => 'Template Ditolak',
                'template_files' => [
                    UploadedFile::fake()->create('template.docx', 32, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'),
                ],
            ])
            ->assertForbidden();
    }

    public function test_template_upload_allows_empty_title_when_there_are_no_files(): void
    {
        Storage::fake('local');
        $this->seed(PermissionSeeder::class);

        $editor = $this->templateEditor();

        $this->actingAs($editor)
            ->from(route('document-templates.index'))
            ->post(route('document-templates.store'), [
                'document_level' => 'level-1',
                'title' => '',
                'template_files' => [],
            ])
            ->assertRedirect(route('document-templates.index'))
            ->assertSessionHasNoErrors();

        $template = DocumentTemplate::query()->firstOrFail();

        $this->assertNull($template->title);
        $this->assertSame(0, $template->files()->count());
    }

    public function test_template_upload_requires_title_when_new_files_are_uploaded(): void
    {
        Storage::fake('local');
        $this->seed(PermissionSeeder::class);

        $editor = $this->templateEditor();

        $this->actingAs($editor)
            ->from(route('document-templates.index'))
            ->post(route('document-templates.store'), [
                'document_level' => 'level-1',
                'title' => '',
                'template_files' => [
                    UploadedFile::fake()->create('template.docx', 32, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'),
                ],
            ])
            ->assertRedirect(route('document-templates.index'))
            ->assertSessionHasErrors(['title']);

        $this->assertSame(0, DocumentTemplate::query()->count());
    }

    public function test_template_upload_requires_title_when_existing_files_are_retained(): void
    {
        Storage::fake('local');
        $this->seed(PermissionSeeder::class);

        $editor = $this->templateEditor();

        $this->actingAs($editor)
            ->post(route('document-templates.store'), [
                'document_level' => 'level-1',
                'title' => 'Template Awal',
                'template_files' => [
                    UploadedFile::fake()->create('template.docx', 32, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'),
                ],
            ])
            ->assertRedirect(route('document-templates.index'));

        $activeTemplate = DocumentTemplate::query()
            ->forLevel('level-1')
            ->active()
            ->with('files')
            ->firstOrFail();

        $this->actingAs($editor)
            ->from(route('document-templates.index'))
            ->post(route('document-templates.store'), [
                'document_level' => 'level-1',
                'title' => '',
                'retained_template_file_ids_present' => '1',
                'retained_template_file_ids' => [$activeTemplate->files->first()->id],
            ])
            ->assertRedirect(route('document-templates.index'))
            ->assertSessionHasErrors(['title']);
    }

    public function test_template_editor_can_save_metadata_without_reuploading_files(): void
    {
        Storage::fake('local');
        $this->seed(PermissionSeeder::class);

        $editor = $this->templateEditor();

        $this->actingAs($editor)
            ->post(route('document-templates.store'), [
                'document_level' => 'level-2',
                'title' => 'Template Awal',
                'template_files' => [
                    UploadedFile::fake()->create('dipertahankan.docx', 32, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'),
                ],
            ])
            ->assertRedirect(route('document-templates.index'));

        $oldTemplate = DocumentTemplate::query()
            ->forLevel('level-2')
            ->active()
            ->with('files')
            ->firstOrFail();
        $this->actingAs($editor)
            ->post(route('document-templates.store'), [
                'document_level' => 'level-2',
                'title' => 'Template Baru',
                'notes' => 'File lama tetap dipakai.',
            ])
            ->assertRedirect(route('document-templates.index'));

        $newTemplate = DocumentTemplate::query()
            ->forLevel('level-2')
            ->active()
            ->with('files')
            ->firstOrFail();

        $this->assertFalse($oldTemplate->fresh()->is_active);
        $this->assertSame(2, $newTemplate->version_number);
        $this->assertSame('Template Baru', $newTemplate->title);
        $this->assertCount(1, $newTemplate->files);
        $this->assertSame('dipertahankan.docx', $newTemplate->files->first()->original_file_name);
        Storage::disk('local')->assertExists($newTemplate->files->first()->path_file);
    }

    public function test_uploading_new_files_replaces_active_template_files(): void
    {
        Storage::fake('local');
        $this->seed(PermissionSeeder::class);

        $editor = $this->templateEditor();

        $this->actingAs($editor)
            ->post(route('document-templates.store'), [
                'document_level' => 'level-2',
                'title' => 'Template Awal',
                'template_files' => [
                    UploadedFile::fake()->create('template.docx', 32, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'),
                ],
            ])
            ->assertRedirect(route('document-templates.index'));

        $this->actingAs($editor)
            ->post(route('document-templates.store'), [
                'document_level' => 'level-2',
                'title' => 'Template Baru',
                'template_files' => [
                    UploadedFile::fake()->create('pengganti.docx', 32, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'),
                ],
            ])
            ->assertRedirect(route('document-templates.index'));

        $newTemplate = DocumentTemplate::query()
            ->forLevel('level-2')
            ->active()
            ->with('files')
            ->firstOrFail();

        $this->assertCount(1, $newTemplate->files);
        $this->assertSame('pengganti.docx', $newTemplate->files->first()->original_file_name);
    }

    public function test_template_editor_can_remove_existing_template_files(): void
    {
        Storage::fake('local');
        $this->seed(PermissionSeeder::class);

        $editor = $this->templateEditor();

        $this->actingAs($editor)
            ->post(route('document-templates.store'), [
                'document_level' => 'level-2',
                'title' => 'Template Awal',
                'template_files' => [
                    UploadedFile::fake()->create('dipakai.docx', 32, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'),
                    UploadedFile::fake()->create('dihapus.docx', 32, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'),
                ],
            ])
            ->assertRedirect(route('document-templates.index'));

        $activeTemplate = DocumentTemplate::query()
            ->forLevel('level-2')
            ->active()
            ->with('files')
            ->firstOrFail();
        $retainedFile = $activeTemplate->files->firstWhere('original_file_name', 'dipakai.docx');

        $this->actingAs($editor)
            ->post(route('document-templates.store'), [
                'document_level' => 'level-2',
                'title' => 'Template Baru',
                'retained_template_file_ids_present' => '1',
                'retained_template_file_ids' => [$retainedFile->id],
            ])
            ->assertRedirect(route('document-templates.index'));

        $newTemplate = DocumentTemplate::query()
            ->forLevel('level-2')
            ->active()
            ->with('files')
            ->firstOrFail();

        $this->assertCount(1, $newTemplate->files);
        $this->assertSame('dipakai.docx', $newTemplate->files->first()->original_file_name);
    }

    public function test_template_editor_can_save_template_after_removing_all_existing_files(): void
    {
        Storage::fake('local');
        $this->seed(PermissionSeeder::class);

        $editor = $this->templateEditor();

        $this->actingAs($editor)
            ->post(route('document-templates.store'), [
                'document_level' => 'level-2',
                'title' => 'Template Awal',
                'template_files' => [
                    UploadedFile::fake()->create('dihapus.docx', 32, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'),
                ],
            ])
            ->assertRedirect(route('document-templates.index'));

        $this->actingAs($editor)
            ->post(route('document-templates.store'), [
                'document_level' => 'level-2',
                'title' => 'Template Tanpa File',
                'retained_template_file_ids_present' => '1',
                'retained_template_file_ids' => [],
            ])
            ->assertRedirect(route('document-templates.index'));

        $newTemplate = DocumentTemplate::query()
            ->forLevel('level-2')
            ->active()
            ->with('files')
            ->firstOrFail();

        $this->assertSame('Template Tanpa File', $newTemplate->title);
        $this->assertCount(0, $newTemplate->files);
    }

    public function test_template_deleter_can_remove_existing_files_without_edit_permission(): void
    {
        Storage::fake('local');
        $this->seed(PermissionSeeder::class);

        $editor = $this->templateEditor();

        $this->actingAs($editor)
            ->post(route('document-templates.store'), [
                'document_level' => 'level-2',
                'title' => 'Template Awal',
                'notes' => 'Catatan lama.',
                'template_files' => [
                    UploadedFile::fake()->create('dipakai.docx', 32, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'),
                    UploadedFile::fake()->create('dihapus.docx', 32, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'),
                ],
            ])
            ->assertRedirect(route('document-templates.index'));

        $activeTemplate = DocumentTemplate::query()
            ->forLevel('level-2')
            ->active()
            ->with('files')
            ->firstOrFail();
        $retainedFile = $activeTemplate->files->firstWhere('original_file_name', 'dipakai.docx');
        $deleter = $this->templateDeleter();

        $this->actingAs($deleter)
            ->post(route('document-templates.store'), [
                'document_level' => 'level-2',
                'title' => 'Judul yang tidak boleh dipakai',
                'notes' => 'Catatan yang tidak boleh dipakai.',
                'retained_template_file_ids_present' => '1',
                'retained_template_file_ids' => [$retainedFile->id],
            ])
            ->assertRedirect(route('document-templates.index'));

        $newTemplate = DocumentTemplate::query()
            ->forLevel('level-2')
            ->active()
            ->with('files')
            ->firstOrFail();

        $this->assertFalse($activeTemplate->fresh()->is_active);
        $this->assertSame('Template Awal', $newTemplate->title);
        $this->assertSame('Catatan lama.', $newTemplate->notes);
        $this->assertCount(1, $newTemplate->files);
        $this->assertSame('dipakai.docx', $newTemplate->files->first()->original_file_name);
    }

    public function test_template_deleter_cannot_upload_new_files_without_edit_permission(): void
    {
        Storage::fake('local');
        $this->seed(PermissionSeeder::class);

        $deleter = $this->templateDeleter();

        $this->actingAs($deleter)
            ->post(route('document-templates.store'), [
                'document_level' => 'level-2',
                'title' => 'Template Baru',
                'template_files' => [
                    UploadedFile::fake()->create('template.docx', 32, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'),
                ],
            ])
            ->assertForbidden();
    }

    private function templateEditor(): User
    {
        $editor = User::factory()->create();
        $editorRole = Role::query()->create(['nama_role' => 'Template Editor']);
        $viewPermission = Permission::query()->where('code', 'document-templates.view')->firstOrFail();
        $editPermission = Permission::query()->where('code', 'document-templates.edit')->firstOrFail();

        $editorRole->permissions()->sync([$viewPermission->id, $editPermission->id]);
        $editorRole->users()->sync([$editor->id]);

        return $editor->refresh();
    }

    private function templateDeleter(): User
    {
        $deleter = User::factory()->create();
        $deleterRole = Role::query()->create(['nama_role' => 'Template Deleter']);
        $viewPermission = Permission::query()->where('code', 'document-templates.view')->firstOrFail();
        $deletePermission = Permission::query()->where('code', 'document-templates.delete')->firstOrFail();

        $deleterRole->permissions()->sync([$viewPermission->id, $deletePermission->id]);
        $deleterRole->users()->sync([$deleter->id]);

        return $deleter->refresh();
    }
}
