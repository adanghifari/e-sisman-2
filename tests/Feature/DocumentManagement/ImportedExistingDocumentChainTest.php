<?php

namespace Tests\Feature\DocumentManagement;

use App\Models\BusinessFunction;
use App\Models\BusinessProcess;
use App\Models\Department;
use App\Models\Document;
use App\Models\DocumentLevel;
use App\Models\DocumentRelation;
use App\Models\DocumentType;
use App\Models\Permission;
use App\Models\Role;
use App\Models\StatusDocument;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ImportedExistingDocumentChainTest extends TestCase
{
    use RefreshDatabase;

    public function test_imported_master_with_confirm_rebuilds_same_number_obsolete_chain(): void
    {
        Storage::fake('local');

        [$user, $level, $documentType, $businessProcess, $businessFunction, $department] = $this->fixture();

        $obsolete00 = $this->postObsolete($user, $level, $documentType, $businessProcess, $businessFunction, $department, '00.00');
        $obsolete02 = $this->postObsolete($user, $level, $documentType, $businessProcess, $businessFunction, $department, '00.02');
        $obsolete01 = $this->postObsolete($user, $level, $documentType, $businessProcess, $businessFunction, $department, '00.01');

        $this->actingAs($user)
            ->post(route('documents.master.imports.store.level', 'level-2'), $this->payload(
                $level,
                $documentType,
                $businessProcess,
                $businessFunction,
                $department,
                [
                    'nama_dokumen' => 'Imported Master Chain',
                    'nomor_revisi' => '00.03',
                    'existing_document' => UploadedFile::fake()->create('master.pdf', 100, 'application/pdf'),
                    'confirm_imported_master_number_reuse' => '1',
                ],
            ))
            ->assertRedirect(route('documents.master'));

        $master = Document::query()
            ->where('nama_dokumen', 'Imported Master Chain')
            ->firstOrFail();

        $this->assertSame(null, $obsolete00->refresh()->revised_from);
        $this->assertSame($obsolete00->id, $obsolete01->refresh()->revised_from);
        $this->assertSame($obsolete01->id, $obsolete02->refresh()->revised_from);
        $this->assertSame($obsolete02->id, $master->refresh()->revised_from);

        $this->assertSupersededBy($obsolete00, $obsolete01);
        $this->assertSupersededBy($obsolete01, $obsolete02);
        $this->assertSupersededBy($obsolete02, $master);
    }

    public function test_later_imported_obsolete_is_inserted_into_existing_same_number_chain(): void
    {
        Storage::fake('local');

        [$user, $level, $documentType, $businessProcess, $businessFunction, $department] = $this->fixture();

        $obsolete00 = $this->postObsolete($user, $level, $documentType, $businessProcess, $businessFunction, $department, '00.00');
        $obsolete02 = $this->postObsolete($user, $level, $documentType, $businessProcess, $businessFunction, $department, '00.02');

        $this->actingAs($user)
            ->post(route('documents.master.imports.store.level', 'level-2'), $this->payload(
                $level,
                $documentType,
                $businessProcess,
                $businessFunction,
                $department,
                [
                    'nama_dokumen' => 'Imported Master Existing Chain',
                    'nomor_revisi' => '00.03',
                    'existing_document' => UploadedFile::fake()->create('master.pdf', 100, 'application/pdf'),
                    'confirm_imported_master_number_reuse' => '1',
                ],
            ))
            ->assertRedirect(route('documents.master'));

        $master = Document::query()
            ->where('nama_dokumen', 'Imported Master Existing Chain')
            ->firstOrFail();

        $obsolete01 = $this->postObsolete($user, $level, $documentType, $businessProcess, $businessFunction, $department, '00.01');

        $this->assertSame(null, $obsolete00->refresh()->revised_from);
        $this->assertSame($obsolete00->id, $obsolete01->refresh()->revised_from);
        $this->assertSame($obsolete01->id, $obsolete02->refresh()->revised_from);
        $this->assertSame($obsolete02->id, $master->refresh()->revised_from);

        $this->assertSupersededBy($obsolete00, $obsolete01);
        $this->assertSupersededBy($obsolete01, $obsolete02);
        $this->assertSupersededBy($obsolete02, $master);
    }

    public function test_imported_obsolete_revision_can_be_edited_to_previous_master_revision_number(): void
    {
        Storage::fake('local');

        [$user, $level, $documentType, $businessProcess, $businessFunction, $department] = $this->fixture();

        $obsolete = $this->postObsolete($user, $level, $documentType, $businessProcess, $businessFunction, $department, '00.03');

        $this->actingAs($user)
            ->post(route('documents.master.imports.store.level', 'level-2'), $this->payload(
                $level,
                $documentType,
                $businessProcess,
                $businessFunction,
                $department,
                [
                    'nama_dokumen' => 'Imported Master Revision Edit',
                    'nomor_revisi' => '00.04',
                    'existing_document' => UploadedFile::fake()->create('master.pdf', 100, 'application/pdf'),
                    'confirm_imported_master_number_reuse' => '1',
                ],
            ))
            ->assertRedirect(route('documents.master'));

        $master = Document::query()
            ->where('nama_dokumen', 'Imported Master Revision Edit')
            ->firstOrFail();

        $this->actingAs($user)
            ->put(route('documents.master.imports.update', $master), $this->payload(
                $level,
                $documentType,
                $businessProcess,
                $businessFunction,
                $department,
                [
                    'nama_dokumen' => 'Imported Master Revision Edit',
                    'nomor_revisi' => '00.05',
                ],
            ))
            ->assertRedirect(route('documents.master.imported.show', $master));

        $this->actingAs($user)
            ->put(route('documents.master.imports.update', $obsolete), $this->payload(
                $level,
                $documentType,
                $businessProcess,
                $businessFunction,
                $department,
                [
                    'nama_dokumen' => 'Imported Obsolete 00.04',
                    'nomor_revisi' => '00.04',
                ],
            ))
            ->assertRedirect(route('documents.existing.imports.show', $obsolete));

        $this->assertSame('00.04', $obsolete->refresh()->nomor_revisi);
        $this->assertSame($obsolete->id, $master->refresh()->revised_from);
        $this->assertSupersededBy($obsolete, $master);

        $this->actingAs($user)
            ->get(route('documents.existing.imports.show', $obsolete))
            ->assertOk()
            ->assertSee('Imported Obsolete 00.04');
    }

    public function test_imported_master_page_lists_obsolete_children_by_revision_desc(): void
    {
        Storage::fake('local');

        [$user, $level, $documentType, $businessProcess, $businessFunction, $department] = $this->fixture();

        foreach (['00.04', '00.03', '00.01', '00.02', '00.00'] as $revision) {
            $this->postObsolete($user, $level, $documentType, $businessProcess, $businessFunction, $department, $revision);
        }

        $this->actingAs($user)
            ->post(route('documents.master.imports.store.level', 'level-2'), $this->payload(
                $level,
                $documentType,
                $businessProcess,
                $businessFunction,
                $department,
                [
                    'nama_dokumen' => 'Imported Master Sorted Children',
                    'nomor_revisi' => '00.05',
                    'existing_document' => UploadedFile::fake()->create('master.pdf', 100, 'application/pdf'),
                    'confirm_imported_master_number_reuse' => '1',
                ],
            ))
            ->assertRedirect(route('documents.master'));

        $this->actingAs($user)
            ->get(route('documents.master'))
            ->assertOk()
            ->assertSeeInOrder([
                'Imported Obsolete 00.04',
                'Imported Obsolete 00.03',
                'Imported Obsolete 00.02',
                'Imported Obsolete 00.01',
                'Imported Obsolete 00.00',
            ]);
    }

    public function test_second_imported_master_with_same_number_is_blocked_even_with_confirmation(): void
    {
        Storage::fake('local');

        [$user, $level, $documentType, $businessProcess, $businessFunction, $department] = $this->fixture();

        $this->actingAs($user)
            ->post(route('documents.master.imports.store.level', 'level-2'), $this->payload(
                $level,
                $documentType,
                $businessProcess,
                $businessFunction,
                $department,
                [
                    'nama_dokumen' => 'Imported Master Original',
                    'nomor_revisi' => '00.04',
                    'existing_document' => UploadedFile::fake()->create('master-original.pdf', 100, 'application/pdf'),
                ],
            ))
            ->assertRedirect(route('documents.master'));

        $this->actingAs($user)
            ->postJson(route('documents.existing.imports.number-reuse-check'), [
                'document_state' => 'master',
                'obsolete_rule_type' => 'current_rule',
                'm_document_level_id' => $level->id,
                'm_proses_bisnis_id' => $businessProcess->id,
                'm_proses_fungsi_id' => $businessFunction->id,
                'nomor_dokumen_suffix' => '77',
            ])
            ->assertOk()
            ->assertJson([
                'conflict' => true,
                'blocked' => true,
                'document_number' => 'PS-OPS-77',
            ]);

        $this->actingAs($user)
            ->from(route('documents.master.imports.create.level', 'level-2'))
            ->post(route('documents.master.imports.store.level', 'level-2'), $this->payload(
                $level,
                $documentType,
                $businessProcess,
                $businessFunction,
                $department,
                [
                    'nama_dokumen' => 'Imported Master Duplicate',
                    'nomor_revisi' => '00.05',
                    'existing_document' => UploadedFile::fake()->create('master-duplicate.pdf', 100, 'application/pdf'),
                    'confirm_imported_master_number_reuse' => '1',
                ],
            ))
            ->assertRedirect(route('documents.master.imports.create.level', 'level-2'))
            ->assertSessionHasErrors(['nomor_dokumen' => 'Nomor dokumen sudah digunakan.']);

        $this->assertSame(1, Document::query()
            ->where('nomor_dokumen', 'PS-OPS-77')
            ->whereHas('status', fn ($query) => $query->where('nama_status', StatusDocument::APPROVED))
            ->count());
    }

    public function test_deleting_middle_imported_obsolete_reconnects_revision_chain(): void
    {
        Storage::fake('local');

        [$user, $level, $documentType, $businessProcess, $businessFunction, $department] = $this->fixture();

        $obsolete00 = $this->postObsolete($user, $level, $documentType, $businessProcess, $businessFunction, $department, '00.00');
        $obsolete01 = $this->postObsolete($user, $level, $documentType, $businessProcess, $businessFunction, $department, '00.01');
        $obsolete02 = $this->postObsolete($user, $level, $documentType, $businessProcess, $businessFunction, $department, '00.02');
        $obsolete03 = $this->postObsolete($user, $level, $documentType, $businessProcess, $businessFunction, $department, '00.03');

        $this->actingAs($user)
            ->post(route('documents.master.imports.store.level', 'level-2'), $this->payload(
                $level,
                $documentType,
                $businessProcess,
                $businessFunction,
                $department,
                [
                    'nama_dokumen' => 'Imported Master Delete Chain',
                    'nomor_revisi' => '00.04',
                    'existing_document' => UploadedFile::fake()->create('master.pdf', 100, 'application/pdf'),
                    'confirm_imported_master_number_reuse' => '1',
                ],
            ))
            ->assertRedirect(route('documents.master'));

        $master = Document::query()
            ->where('nama_dokumen', 'Imported Master Delete Chain')
            ->firstOrFail();

        $this->actingAs($user)
            ->delete(route('documents.existing.imports.destroy', $obsolete02))
            ->assertRedirect(route('documents.existing.imports.index'));

        $this->assertDatabaseMissing('t_document', ['id' => $obsolete02->id]);
        $this->assertSame(null, $obsolete00->refresh()->revised_from);
        $this->assertSame($obsolete00->id, $obsolete01->refresh()->revised_from);
        $this->assertSame($obsolete01->id, $obsolete03->refresh()->revised_from);
        $this->assertSame($obsolete03->id, $master->refresh()->revised_from);

        $this->assertSupersededBy($obsolete01, $obsolete03);
        $this->assertSupersededBy($obsolete03, $master);
        $this->assertDatabaseMissing('document_relations', [
            'source_document_id' => $obsolete01->id,
            'target_document_id' => $obsolete02->id,
            'relation_type' => DocumentRelation::SUPERSEDED_BY,
        ]);
    }

    public function test_deleting_imported_master_leaves_obsolete_chain_reusable_for_future_master(): void
    {
        Storage::fake('local');

        [$user, $level, $documentType, $businessProcess, $businessFunction, $department] = $this->fixture();

        $obsolete00 = $this->postObsolete($user, $level, $documentType, $businessProcess, $businessFunction, $department, '00.00');
        $obsolete01 = $this->postObsolete($user, $level, $documentType, $businessProcess, $businessFunction, $department, '00.01');
        $obsolete02 = $this->postObsolete($user, $level, $documentType, $businessProcess, $businessFunction, $department, '00.02');

        $this->actingAs($user)
            ->post(route('documents.master.imports.store.level', 'level-2'), $this->payload(
                $level,
                $documentType,
                $businessProcess,
                $businessFunction,
                $department,
                [
                    'nama_dokumen' => 'Imported Master Delete Tail',
                    'nomor_revisi' => '00.03',
                    'existing_document' => UploadedFile::fake()->create('master.pdf', 100, 'application/pdf'),
                    'confirm_imported_master_number_reuse' => '1',
                ],
            ))
            ->assertRedirect(route('documents.master'));

        $master = Document::query()
            ->where('nama_dokumen', 'Imported Master Delete Tail')
            ->firstOrFail();

        $this->actingAs($user)
            ->delete(route('documents.existing.imports.destroy', $master))
            ->assertRedirect(route('documents.existing.imports.index'));

        $this->assertDatabaseMissing('t_document', ['id' => $master->id]);
        $this->assertSame(null, $obsolete00->refresh()->revised_from);
        $this->assertSame($obsolete00->id, $obsolete01->refresh()->revised_from);
        $this->assertSame($obsolete01->id, $obsolete02->refresh()->revised_from);
        $this->assertDatabaseMissing('document_relations', [
            'source_document_id' => $obsolete02->id,
            'target_document_id' => $master->id,
            'relation_type' => DocumentRelation::SUPERSEDED_BY,
        ]);

        $this->actingAs($user)
            ->postJson(route('documents.existing.imports.number-reuse-check'), [
                'document_state' => 'master',
                'obsolete_rule_type' => 'current_rule',
                'm_document_level_id' => $level->id,
                'm_proses_bisnis_id' => $businessProcess->id,
                'm_proses_fungsi_id' => $businessFunction->id,
                'nomor_dokumen_suffix' => '77',
            ])
            ->assertOk()
            ->assertJson([
                'conflict' => true,
                'blocked' => false,
                'document_number' => 'PS-OPS-77',
                'count' => 3,
            ]);
    }

    private function postObsolete(
        User $user,
        DocumentLevel $level,
        DocumentType $documentType,
        BusinessProcess $businessProcess,
        BusinessFunction $businessFunction,
        Department $department,
        string $revision,
    ): Document {
        $this->actingAs($user)
            ->post(route('documents.obsolete.imports.store.level', 'level-2'), $this->payload(
                $level,
                $documentType,
                $businessProcess,
                $businessFunction,
                $department,
                [
                    'nama_dokumen' => "Imported Obsolete {$revision}",
                    'nomor_revisi' => $revision,
                    'tanggal_obsolete' => '2026-01-01',
                    'obsolete_document' => UploadedFile::fake()->create("obsolete-{$revision}.pdf", 100, 'application/pdf'),
                ],
            ))
            ->assertRedirect(route('documents.obsolete'));

        return Document::query()
            ->where('nama_dokumen', "Imported Obsolete {$revision}")
            ->firstOrFail();
    }

    private function payload(
        DocumentLevel $level,
        DocumentType $documentType,
        BusinessProcess $businessProcess,
        BusinessFunction $businessFunction,
        Department $department,
        array $overrides = [],
    ): array {
        return array_merge([
            'm_document_level_id' => $level->id,
            'm_document_types_id' => $documentType->id,
            'm_proses_bisnis_id' => $businessProcess->id,
            'm_proses_fungsi_id' => $businessFunction->id,
            'department_ids' => [$department->id],
            'nomor_dokumen_suffix' => '77',
            'tanggal_terbit' => '2025-01-01',
        ], $overrides);
    }

    private function fixture(): array
    {
        $role = Role::query()->firstOrCreate(['nama_role' => 'Imported Chain Role']);
        $user = User::factory()->create();

        foreach ([
            'documents.master.imports.store-level' => 'documents.master.imports.store.level',
            'documents.master.imports.update' => 'documents.master.imports.update',
            'documents.master.view' => 'documents.master',
            'documents.obsolete.imports.store-level' => 'documents.obsolete.imports.store.level',
            'documents.existing.imports.detail' => 'documents.existing.imports.show',
            'documents.existing.imports.delete' => 'documents.existing.imports.destroy',
            'documents.existing.imports.number-reuse-check' => 'documents.existing.imports.number-reuse-check',
        ] as $code => $route) {
            $permission = Permission::query()->firstOrCreate(
                ['code' => $code],
                [
                    'name' => $code,
                    'module' => 'Manajemen Dokumen',
                    'route' => $route,
                    'action' => 'create',
                ],
            );
            $role->permissions()->syncWithoutDetaching([$permission->id]);
        }

        $user->roles()->sync([$role->id]);

        $level = DocumentLevel::query()->where('kode', 'level-2')->firstOrFail();
        $documentType = DocumentType::query()->firstOrCreate(['nama_types' => 'Prosedur']);
        $businessProcess = BusinessProcess::create([
            'kode' => 'SMR',
            'nama_proses_bisnis' => 'Sistem Manajemen Risiko',
        ]);
        $businessFunction = BusinessFunction::create([
            'kode' => 'OPS',
            'nama_proses_fungsi' => 'Operasional',
            'm_proses_bisnis_id' => $businessProcess->id,
        ]);
        $department = Department::create([
            'kode_department' => 'QA',
            'nama_department' => 'Quality Assurance',
        ]);

        StatusDocument::query()->firstOrCreate(['nama_status' => StatusDocument::APPROVED]);
        StatusDocument::query()->firstOrCreate(['nama_status' => StatusDocument::OBSOLETE]);

        return [$user, $level, $documentType, $businessProcess, $businessFunction, $department];
    }

    private function assertSupersededBy(Document $source, Document $target): void
    {
        $this->assertDatabaseHas('document_relations', [
            'source_document_id' => $source->id,
            'target_document_id' => $target->id,
            'relation_type' => DocumentRelation::SUPERSEDED_BY,
        ]);
    }
}
