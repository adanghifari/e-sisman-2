<?php

namespace Tests\Feature\DocumentManagement;

use App\Models\BusinessFunction;
use App\Models\BusinessProcess;
use App\Models\Department;
use App\Models\Document;
use App\Models\DocumentType;
use App\Models\StatusDocument;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DocumentAutosaveTest extends TestCase
{
    use RefreshDatabase;

    public function test_autosave_keeps_incomplete_draft_without_auto_picked_context_or_number(): void
    {
        [$user] = $this->autosaveFixture();

        $response = $this->actingAs($user)
            ->postJson(route('documents.autosave', 'level-2'), [
                'nama_dokumen' => 'D',
            ])
            ->assertOk()
            ->assertJson(['saved' => true]);

        $draft = Document::query()->findOrFail($response->json('draft_id'));

        $this->assertSame(StatusDocument::DRAFT, $draft->status->nama_status);
        $this->assertSame('D', $draft->nama_dokumen);
        $this->assertNull($draft->m_proses_bisnis_id);
        $this->assertNull($draft->m_proses_fungsi_id);
        $this->assertNull($draft->official_preparer_id);
        $this->assertNull($draft->nomor_dokumen);
    }

    public function test_autosave_reuses_latest_incomplete_draft_when_browser_cannot_send_draft_id(): void
    {
        [$user] = $this->autosaveFixture();

        $firstResponse = $this->actingAs($user)
            ->postJson(route('documents.autosave', 'level-2'), [
                'nama_dokumen' => 'Draft awal',
            ])
            ->assertOk()
            ->assertJson(['saved' => true]);

        $secondResponse = $this->actingAs($user)
            ->postJson(route('documents.autosave', 'level-2'), [
                'nama_dokumen' => 'Draft diperbarui',
            ])
            ->assertOk()
            ->assertJson([
                'saved' => true,
                'draft_id' => $firstResponse->json('draft_id'),
            ]);

        $this->assertSame($firstResponse->json('draft_id'), $secondResponse->json('draft_id'));
        $this->assertSame(1, Document::query()
            ->where('user_id', $user->id)
            ->whereNull('nomor_dokumen')
            ->whereHas('status', fn ($query) => $query->where('nama_status', StatusDocument::DRAFT))
            ->count());
        $this->assertDatabaseHas('t_document', [
            'id' => $firstResponse->json('draft_id'),
            'nama_dokumen' => 'Draft diperbarui',
            'nomor_dokumen' => null,
        ]);
    }

    public function test_autosave_creates_and_updates_draft_for_same_document_number(): void
    {
        [$user, $businessProcess, $businessFunction, $department] = $this->autosaveFixture();

        $response = $this->actingAs($user)
            ->postJson(route('documents.autosave', 'level-2'), [
                'nama_dokumen' => 'Draft Autosave Prosedur',
                'm_proses_bisnis_id' => $businessProcess->id,
                'm_proses_fungsi_id' => $businessFunction->id,
                'department_ids' => [$department->id],
                'official_preparer_id' => $user->id,
                'nomor_dokumen_suffix' => '77',
                'catatan_revisi' => 'Catatan pertama',
            ])
            ->assertOk()
            ->assertJson(['saved' => true]);

        $draftId = $response->json('draft_id');
        $draft = Document::query()->findOrFail($draftId);

        $this->assertSame(StatusDocument::DRAFT, $draft->status->nama_status);
        $this->assertSame('PS-OPS-77', $draft->nomor_dokumen);
        $this->assertSame('Catatan pertama', $draft->catatan_revisi);

        $this->actingAs($user)
            ->postJson(route('documents.autosave', 'level-2'), [
                'draft_id' => $draftId,
                'nama_dokumen' => 'Draft Autosave Prosedur Updated',
                'm_proses_bisnis_id' => $businessProcess->id,
                'm_proses_fungsi_id' => $businessFunction->id,
                'department_ids' => [$department->id],
                'official_preparer_id' => $user->id,
                'nomor_dokumen_suffix' => '77',
                'catatan_revisi' => 'Catatan kedua',
            ])
            ->assertOk()
            ->assertJson([
                'saved' => true,
                'draft_id' => $draftId,
            ]);

        $this->assertSame(1, Document::query()->where('user_id', $user->id)->whereHas('status', fn ($query) => $query->where('nama_status', StatusDocument::DRAFT))->count());
        $this->assertSame('Catatan kedua', $draft->refresh()->catatan_revisi);
        $this->assertSame('Draft Autosave Prosedur Updated', $draft->nama_dokumen);
    }

    public function test_autosave_updates_same_draft_when_document_number_changes(): void
    {
        [$user, $businessProcess, $businessFunction, $department] = $this->autosaveFixture();

        $firstResponse = $this->actingAs($user)
            ->postJson(route('documents.autosave', 'level-2'), [
                'nama_dokumen' => 'Draft Nomor Pertama',
                'm_proses_bisnis_id' => $businessProcess->id,
                'm_proses_fungsi_id' => $businessFunction->id,
                'department_ids' => [$department->id],
                'official_preparer_id' => $user->id,
                'nomor_dokumen_suffix' => '81',
            ])
            ->assertOk();

        $secondResponse = $this->actingAs($user)
            ->postJson(route('documents.autosave', 'level-2'), [
                'draft_id' => $firstResponse->json('draft_id'),
                'nama_dokumen' => 'Draft Nomor Kedua',
                'm_proses_bisnis_id' => $businessProcess->id,
                'm_proses_fungsi_id' => $businessFunction->id,
                'department_ids' => [$department->id],
                'official_preparer_id' => $user->id,
                'nomor_dokumen_suffix' => '82',
            ])
            ->assertOk();

        $this->assertSame($firstResponse->json('draft_id'), $secondResponse->json('draft_id'));
        $this->assertSame(1, Document::query()->where('user_id', $user->id)->whereHas('status', fn ($query) => $query->where('nama_status', StatusDocument::DRAFT))->count());
        $this->assertDatabaseHas('t_document', [
            'id' => $firstResponse->json('draft_id'),
            'nama_dokumen' => 'Draft Nomor Kedua',
            'nomor_dokumen' => 'PS-OPS-82',
        ]);
    }

    public function test_autosave_works_for_level_4_revision_document(): void
    {
        [$user, $businessProcess, $businessFunction, $department] = $this->autosaveFixture();

        $level2 = \App\Models\DocumentLevel::query()->firstOrCreate(['kode' => 'level-2'], ['nama_level' => 'Level II', 'nama_dokumen' => 'Prosedur', 'prefix' => 'PS']);
        \App\Models\DocumentLevel::query()->firstOrCreate(['kode' => 'level-4'], ['nama_level' => 'Level IV', 'nama_dokumen' => 'Formulir', 'prefix' => 'FM']);
        DocumentType::query()->firstOrCreate(['nama_types' => 'Form']);
        $approvedStatus = StatusDocument::query()->firstOrCreate(['nama_status' => StatusDocument::APPROVED]);
        $masterDoc = Document::create([
            'm_document_level_id' => $level2->id,
            'm_status_document_id' => $approvedStatus->id,
            'm_document_types_id' => DocumentType::query()->where('nama_types', 'Prosedur')->value('id'),
            'm_proses_bisnis_id' => $businessProcess->id,
            'm_proses_fungsi_id' => $businessFunction->id,
            'user_id' => $user->id,
            'nama_dokumen' => 'Master Prosedur',
            'nomor_dokumen' => 'PS-OPS-01',
            'nomor_revisi' => 0,
        ]);

        $user->forceFill(['m_department_id' => $department->id])->save();
        $masterDoc->departments()->attach($department->id);

        $response = $this->actingAs($user)
            ->postJson(route('documents.autosave', 'level-4'), [
                'revised_from' => $masterDoc->id,
                'catatan_revisi' => 'Revisi tahap 1',
            ])
            ->assertOk()
            ->assertJson(['saved' => true]);

        $draftId = $response->json('draft_id');
        $draft = Document::query()->findOrFail($draftId);

        $this->assertSame(StatusDocument::DRAFT, $draft->status->nama_status);
        $this->assertSame($masterDoc->id, $draft->revised_from);
        $this->assertSame('revision', $draft->request_type);
        $this->assertSame('PS-OPS-01', $draft->nomor_dokumen);
        $this->assertSame('Revisi tahap 1', $draft->catatan_revisi);
    }

    private function autosaveFixture(): array
    {
        StatusDocument::query()->firstOrCreate(['nama_status' => StatusDocument::DRAFT]);
        DocumentType::query()->firstOrCreate(['nama_types' => 'Prosedur']);
        $user = User::factory()->create();
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
            'kode_department' => 'ITSM',
            'nama_department' => 'IT & Management System Department',
        ]);

        return [$user, $businessProcess, $businessFunction, $department];
    }
}
