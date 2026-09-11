<?php

namespace Tests\Feature;

use App\Models\Approval;
use App\Models\ApprovalStatus;
use App\Models\BusinessFunction;
use App\Models\BusinessProcess;
use App\Models\Department;
use App\Models\Document;
use App\Models\DocumentDownloadLog;
use App\Models\DocumentLevel;
use App\Models\DocumentType;
use App\Models\StatusDocument;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_the_login_page(): void
    {
        $response = $this->get(route('dashboard'));
        $response->assertRedirect(route('login'));
    }

    public function test_authenticated_users_can_visit_the_dashboard(): void
    {
        $user = User::factory()->create([
            'nik' => '000000',
            'email' => 'developer@example.com',
        ]);
        $this->actingAs($user);

        $response = $this->get(route('dashboard'));
        $response->assertOk();
    }

    public function test_dashboard_needs_process_table_uses_real_tasks_only(): void
    {
        $user = User::factory()->create([
            'nik' => '000000',
            'email' => 'developer@example.com',
        ]);
        $submitter = User::factory()->create(['name' => 'Pengaju Real']);
        $status = StatusDocument::query()->firstOrCreate(['nama_status' => StatusDocument::PROPOSED]);
        $approvalStatus = ApprovalStatus::query()->firstOrCreate(
            ['kode_status' => ApprovalStatus::PENDING],
            ['nama_status' => 'Menunggu'],
        );
        $level = DocumentLevel::query()->firstOrCreate(
            ['kode' => 'level-2'],
            ['nama_level' => 'Level II', 'nama_dokumen' => 'Level II : Prosedur SKMBS', 'sort_order' => 2],
        );
        $type = DocumentType::query()->firstOrCreate(['nama_types' => 'Prosedur']);
        $businessProcess = BusinessProcess::create([
            'kode' => 'REAL',
            'nama_proses_bisnis' => 'Proses Real',
        ]);
        $businessFunction = BusinessFunction::create([
            'kode' => 'REAL',
            'nama_proses_fungsi' => 'Fungsi Real',
        ]);
        $document = Document::create([
            'm_document_level_id' => $level->id,
            'm_status_document_id' => $status->id,
            'm_document_types_id' => $type->id,
            'm_proses_bisnis_id' => $businessProcess->id,
            'm_proses_fungsi_id' => $businessFunction->id,
            'user_id' => $submitter->id,
            'nama_dokumen' => 'Dokumen Real Dashboard',
            'nomor_dokumen' => 'PS-REAL-01',
            'nomor_revisi' => 0,
            'submitted_at' => now(),
        ]);

        Approval::create([
            't_document_id' => $document->id,
            'user_id' => $user->id,
            'assigned_by' => $submitter->id,
            'm_approval_status_id' => $approvalStatus->id,
            'stages' => 'Verifikasi Real',
            'assigned_at' => now(),
        ]);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('PS-REAL-01')
            ->assertSee('Dokumen Real Dashboard')
            ->assertDontSee('KBS-PB-PR-001');
    }

    public function test_level_statistics_count_published_masters_and_revision_forms(): void
    {
        $user = User::factory()->create([
            'nik' => '000000',
            'email' => 'developer@example.com',
        ]);
        $approvedStatus = StatusDocument::query()->firstOrCreate(['nama_status' => StatusDocument::APPROVED]);
        $obsoleteStatus = StatusDocument::query()->firstOrCreate(['nama_status' => StatusDocument::OBSOLETE]);
        $levelTwo = DocumentLevel::query()->firstOrCreate(
            ['kode' => 'level-2'],
            ['nama_level' => 'Level II', 'nama_dokumen' => 'Dokumen Level II : Prosedur SKMBS', 'sort_order' => 2],
        );
        $levelFour = DocumentLevel::query()->firstOrCreate(
            ['kode' => 'level-4'],
            ['nama_level' => 'Level IV', 'nama_dokumen' => 'Dokumen Level IV : Form / Lembar Revisi', 'sort_order' => 4],
        );
        $type = DocumentType::query()->firstOrCreate(['nama_types' => 'Prosedur']);
        $businessProcess = BusinessProcess::create([
            'kode' => 'STAT',
            'nama_proses_bisnis' => 'Proses Statistik',
        ]);
        $businessFunction = BusinessFunction::create([
            'kode' => 'STAT',
            'nama_proses_fungsi' => 'Fungsi Statistik',
        ]);

        Document::create([
            'm_document_level_id' => $levelTwo->id,
            'm_status_document_id' => $obsoleteStatus->id,
            'm_document_types_id' => $type->id,
            'm_proses_bisnis_id' => $businessProcess->id,
            'm_proses_fungsi_id' => $businessFunction->id,
            'user_id' => $user->id,
            'nama_dokumen' => 'Master Lama',
            'nomor_dokumen' => 'PS-STAT-01',
            'nomor_revisi' => 0,
        ])->departments()->attach($department = Department::query()->create([
            'kode_department' => 'STAT',
            'nama_department' => 'Department Statistik',
        ]));
        Document::create([
            'm_document_level_id' => $levelTwo->id,
            'm_status_document_id' => $approvedStatus->id,
            'm_document_types_id' => $type->id,
            'm_proses_bisnis_id' => $businessProcess->id,
            'm_proses_fungsi_id' => $businessFunction->id,
            'user_id' => $user->id,
            'nama_dokumen' => 'Master Revisi',
            'nomor_dokumen' => 'PS-STAT-01',
            'nomor_lembar_revisi' => 'FMPS-STAT-01-01',
            'nomor_revisi' => 1,
            'request_type' => 'revision',
        ])->departments()->attach($department);
        Document::create([
            'm_document_level_id' => $levelTwo->id,
            'm_status_document_id' => $approvedStatus->id,
            'm_document_types_id' => $type->id,
            'm_proses_bisnis_id' => $businessProcess->id,
            'm_proses_fungsi_id' => $businessFunction->id,
            'user_id' => $user->id,
            'nama_dokumen' => 'Request Obsolete Manual',
            'nomor_dokumen' => 'PS-STAT-01',
            'nomor_revisi' => 1,
            'request_type' => 'obsolete',
        ]);
        Document::create([
            'origin' => Document::ORIGIN_IMPORTED_LEGACY,
            'm_status_document_id' => $obsoleteStatus->id,
            'm_document_level_id' => $levelTwo->id,
            'm_document_types_id' => $type->id,
            'm_proses_bisnis_id' => $businessProcess->id,
            'm_proses_fungsi_id' => $businessFunction->id,
            'user_id' => $user->id,
            'nama_dokumen' => 'Imported Terpetakan',
            'nomor_dokumen' => 'PS-IMP-01',
            'nomor_revisi' => '00.00',
        ])->departments()->attach($department);
        Document::create([
            'origin' => Document::ORIGIN_IMPORTED_LEGACY,
            'm_status_document_id' => $obsoleteStatus->id,
            'user_id' => $user->id,
            'nama_dokumen' => 'Imported Legacy Tanpa Mapping',
            'nomor_dokumen' => 'LEGACY-001',
            'nomor_revisi' => '00.00',
        ]);

        $response = $this->actingAs($user)->get(route('dashboard'));
        $items = collect($response->viewData('levelStatistics')['items']);

        $this->assertSame(3, $items->firstWhere('label', 'Level II Prosedur SKMBS')['value']);
        $this->assertSame(1, $items->firstWhere('label', 'Level IV Form / Lembar Revisi')['value']);
        $this->assertSame(4, $response->viewData('levelStatistics')['total']);
        $this->assertSame(3, collect($response->viewData('businessFunctionStatistics')['items'])->firstWhere('label', 'Fungsi Statistik')['value']);
        $this->assertSame(3, collect($response->viewData('businessProcessTotals')['items'])->firstWhere('label', 'Proses Statistik')['value']);
        $this->assertSame(3, collect($response->viewData('departmentTotals')['items'])->firstWhere('label', 'Department Statistik')['value']);
        $this->assertNotNull($levelFour->id);
    }

    public function test_department_warning_popup_is_rendered_when_session_exists(): void
    {
        $user = User::factory()->create([
            'm_department_id' => null,
            'nik' => '000000',
            'email' => 'developer@example.com',
        ]);
        $this->actingAs($user);

        $response = $this
            ->withSession([
                'department_warning' => [
                    'title' => 'Department Belum Terdaftar',
                    'message' => 'Akun Anda belum terdaftar di department manapun. Silakan hubungi admin untuk melengkapi data department.',
                ],
            ])
            ->get(route('dashboard'));

        $response
            ->assertOk()
            ->assertSee('Department Belum Terdaftar')
            ->assertSee('Akun Anda belum terdaftar di department manapun.');
    }

    public function test_download_activity_dashboard_shows_document_number_with_revision(): void
    {
        $user = User::factory()->create([
            'nik' => '000000',
            'email' => 'developer@example.com',
        ]);
        $status = StatusDocument::query()->firstOrCreate(['nama_status' => StatusDocument::APPROVED]);
        $level = DocumentLevel::query()->where('kode', 'level-2')->firstOrFail();
        $type = DocumentType::query()->firstOrCreate(['nama_types' => 'Prosedur']);
        $businessProcess = BusinessProcess::create([
            'kode' => 'SMR',
            'nama_proses_bisnis' => 'Sistem Manajemen Risiko',
        ]);
        $businessFunction = BusinessFunction::create([
            'kode' => 'QA',
            'nama_proses_fungsi' => 'Quality Assurance',
        ]);
        $document = Document::create([
            'm_document_level_id' => $level->id,
            'm_status_document_id' => $status->id,
            'm_document_types_id' => $type->id,
            'm_proses_bisnis_id' => $businessProcess->id,
            'm_proses_fungsi_id' => $businessFunction->id,
            'user_id' => $user->id,
            'nama_dokumen' => 'Dokumen Revisi Promoted',
            'nomor_dokumen' => 'PS-SMR-SNAP',
            'nomor_revisi' => 1,
        ]);
        DocumentDownloadLog::create([
            't_document_id' => $document->id,
            'document_name_snapshot' => 'Dokumen Revisi Promoted',
            'document_number_snapshot' => 'FMPS-SMR-SNAP-01',
            'document_revision_snapshot' => 1,
            'download_context' => 'approval',
            'user_id' => $user->id,
            'downloaded_at' => now(),
        ]);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('FMPS-SMR-SNAP-01 ke 00.01')
            ->assertDontSee('Rev. 00.01');

        $this->actingAs($user)
            ->get(route('activity-log.index'))
            ->assertOk()
            ->assertSee('FMPS-SMR-SNAP-01')
            ->assertSee('00.01');
    }
}
