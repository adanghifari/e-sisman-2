<?php

namespace Tests\Feature;

use App\Models\Approval;
use App\Models\ApprovalStatus;
use App\Models\BusinessFunction;
use App\Models\BusinessProcess;
use App\Models\Department;
use App\Models\Document;
use App\Models\DocumentLevel;
use App\Models\DocumentType;
use App\Models\StatusDocument;
use App\Models\User;
use App\Support\DigitalSignatures\SignatureVerificationUrl;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DigitalSignatureVerificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ensureStatuses();
    }

    public function test_signed_signature_url_shows_verified_signature_data_without_login(): void
    {
        $department = Department::query()->create([
            'kode_department' => 'SMR',
            'nama_department' => 'System Management & Risk',
        ]);
        $approver = User::factory()->create([
            'name' => 'Current Approver Name',
            'jabatan' => 'Current Position',
            'm_department_id' => $department->id,
        ]);
        $document = $this->createDocument($approver, $department);
        $approval = Approval::query()->create([
            't_document_id' => $document->id,
            'm_approval_status_id' => ApprovalStatus::query()->where('kode_status', ApprovalStatus::APPROVED)->value('id'),
            'user_id' => $approver->id,
            'role_id' => null,
            'assigned_by' => $approver->id,
            'assigned_at' => now()->subDay(),
            'responded_at' => now(),
            'stages' => 'Disahkan Oleh',
            'stage_name_snapshot' => 'Disahkan Oleh',
            'stage_order_snapshot' => 2,
            'approver_name_snapshot' => 'Snapshot Approver',
            'approver_position_snapshot' => 'Manager SMR',
            'approver_department_snapshot' => 'System Management & Risk',
        ]);

        $response = $this->get(app(SignatureVerificationUrl::class)->forApproval($approval));

        $response
            ->assertOk()
            ->assertSee('Tanda Tangan Digital Terverifikasi')
            ->assertSee('Bukti Tanda Tangan Sah')
            ->assertSee('Snapshot Approver')
            ->assertSee('Manager SMR')
            ->assertSee('System Management &amp; Risk', false)
            ->assertSee('Disahkan Oleh')
            ->assertSee('Dokumen Validasi TTD')
            ->assertSee('PS-SMR-TTD');
    }

    public function test_unsigned_signature_url_is_rejected(): void
    {
        $department = Department::query()->create([
            'kode_department' => 'SMR',
            'nama_department' => 'System Management & Risk',
        ]);
        $approver = User::factory()->create(['m_department_id' => $department->id]);
        $document = $this->createDocument($approver, $department);
        $approval = Approval::query()->create([
            't_document_id' => $document->id,
            'm_approval_status_id' => ApprovalStatus::query()->where('kode_status', ApprovalStatus::APPROVED)->value('id'),
            'user_id' => $approver->id,
            'role_id' => null,
            'assigned_by' => $approver->id,
            'assigned_at' => now(),
            'responded_at' => now(),
            'stages' => 'Disahkan Oleh',
        ]);

        $this->get(route('digital-signatures.verify', $approval))->assertForbidden();
    }

    private function createDocument(User $user, Department $department): Document
    {
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
        $type = DocumentType::query()->firstOrCreate(
            ['nama_types' => 'Prosedur'],
            ['is_active' => true],
        );
        $process = BusinessProcess::query()->firstOrCreate(
            ['kode' => 'Utama'],
            ['nama_proses_bisnis' => 'Proses Inti / Utama'],
        );
        $function = BusinessFunction::query()->firstOrCreate(
            ['kode' => 'SMR'],
            ['nama_proses_fungsi' => 'Sistem Manajemen & Resiko'],
        );

        $document = Document::query()->create([
            'm_status_document_id' => StatusDocument::query()->where('nama_status', StatusDocument::APPROVED)->value('id'),
            'm_document_level_id' => $level->id,
            'm_document_types_id' => $type->id,
            'm_proses_bisnis_id' => $process->id,
            'm_proses_fungsi_id' => $function->id,
            'user_id' => $user->id,
            'official_preparer_id' => $user->id,
            'nama_dokumen' => 'Dokumen Validasi TTD',
            'nomor_dokumen' => 'PS-SMR-TTD',
            'nomor_revisi' => 0,
            'tanggal_terbit' => now()->toDateString(),
            'submitted_at' => now(),
            'approved_at' => now(),
        ]);

        $document->departments()->sync([$department->id]);

        return $document;
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
