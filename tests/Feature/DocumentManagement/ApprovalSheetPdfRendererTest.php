<?php

namespace Tests\Feature\DocumentManagement;

use App\Support\FinalDocuments\ApprovalSheetPdfRenderer;
use Tests\TestCase;

class ApprovalSheetPdfRendererTest extends TestCase
{
    public function test_approval_sheet_template_renders_document_metadata_and_dynamic_stages(): void
    {
        $html = app(ApprovalSheetPdfRenderer::class)->renderHtml($this->payload([
            [
                'stage_name' => 'Direview Legal',
                'stage_order' => 7,
                'approvers' => [
                    [
                        'name' => 'Snapshot Approver',
                        'position' => 'Legal Specialist',
                        'department' => 'Legal',
                        'responded_at' => now(),
                    ],
                ],
            ],
            [
                'stage_name' => 'Disahkan Operasional',
                'stage_order' => 8,
                'approvers' => [
                    [
                        'name' => 'Operational Approver',
                        'position' => 'Operations Manager',
                        'department' => 'Operations',
                        'responded_at' => now(),
                    ],
                ],
            ],
        ]));

        $this->assertStringContainsString('LEMBAR PENGESAHAN', $html);
        $this->assertStringContainsString('Telah disusun', $html);
        $this->assertStringContainsString('Instruksi Kerja Komunikasi, Konsultasi &amp; Partisipasi', $html);
        $this->assertStringContainsString('IK-HMK-01-05', $html);
        $this->assertStringContainsString('00.00', $html);
        $this->assertStringContainsString(now()->format('d - m - Y'), $html);
        $this->assertStringContainsString('Krakatau International Port', $html);
        $this->assertStringContainsString('SISTEM MANAJEMEN KBS', $html);
        $this->assertStringContainsString('Lembar Pengesahan', $html);
        $this->assertStringContainsString('Sistem Dokumentasi PT Krakatau Bandar Samudera berstandar Sistem Manajemen Terintegrasi', $html);
        $this->assertStringContainsString('Direview Legal', $html);
        $this->assertStringContainsString('Disahkan Operasional', $html);
        $this->assertStringContainsString('Snapshot Approver', $html);
        $this->assertStringContainsString('Legal Specialist', $html);
    }

    public function test_multiple_approvers_in_one_stage_all_render_without_hardcoded_limit(): void
    {
        $html = app(ApprovalSheetPdfRenderer::class)->renderHtml($this->payload([
            [
                'stage_name' => 'Kolaborasi Review',
                'stage_order' => 1,
                'approvers' => collect(range(1, 5))
                    ->map(fn (int $number): array => [
                        'name' => "Approver {$number}",
                        'position' => "Position {$number}",
                        'department' => null,
                        'responded_at' => now(),
                    ])
                    ->all(),
            ],
        ]));

        foreach (range(1, 5) as $number) {
            $this->assertStringContainsString("Approver {$number}", $html);
            $this->assertStringContainsString("Position {$number}", $html);
        }
    }

    public function test_approval_sheet_renders_verification_qr_for_approver_with_approval_id(): void
    {
        $html = app(ApprovalSheetPdfRenderer::class)->renderHtml($this->payload([
            [
                'stage_name' => 'Disahkan Oleh',
                'stage_order' => 1,
                'approvers' => [
                    [
                        'approval_id' => 123,
                        'name' => 'QR Approver',
                        'position' => 'Senior Manager',
                        'department' => 'SMR',
                        'responded_at' => now(),
                    ],
                ],
            ],
        ]));

        $this->assertStringContainsString('data:image/svg+xml;base64,', $html);
        $this->assertStringContainsString('QR verifikasi tanda tangan digital', $html);
        $this->assertStringContainsString('QR Approver', $html);
    }

    public function test_renderer_uses_payload_snapshot_values_not_current_profile_values(): void
    {
        $html = app(ApprovalSheetPdfRenderer::class)->renderHtml($this->payload([
            [
                'stage_name' => 'Disetujui Snapshot',
                'stage_order' => 1,
                'approvers' => [
                    [
                        'name' => 'Historical Name',
                        'position' => 'Historical Position',
                        'department' => 'Historical Department',
                        'responded_at' => now(),
                    ],
                ],
            ],
        ]));

        $this->assertStringContainsString('Historical Name', $html);
        $this->assertStringContainsString('Historical Position', $html);
        $this->assertStringNotContainsString('Current Profile Name', $html);
        $this->assertStringNotContainsString('Current Profile Position', $html);
    }

    public function test_snapshot_null_values_render_as_dash_without_crashing(): void
    {
        $html = app(ApprovalSheetPdfRenderer::class)->renderHtml($this->payload([
            [
                'stage_name' => null,
                'stage_order' => null,
                'approvers' => [
                    [
                        'name' => null,
                        'position' => null,
                        'department' => null,
                        'responded_at' => now(),
                    ],
                ],
            ],
        ]));

        $this->assertStringContainsString('LEMBAR PENGESAHAN', $html);
        $this->assertStringContainsString('>-<', preg_replace('/\s+/', '', $html));
    }

    public function test_pdf_output_is_created_and_valid(): void
    {
        $pdf = app(ApprovalSheetPdfRenderer::class)->render($this->payload([
            [
                'stage_name' => 'Disusun Oleh',
                'stage_order' => 1,
                'approvers' => [
                    [
                        'approval_id' => 456,
                        'name' => 'Single Approver',
                        'position' => 'Management System Specialist',
                        'department' => null,
                        'responded_at' => now(),
                    ],
                ],
            ],
        ]));

        $this->assertStringStartsWith('%PDF-', $pdf);
        $this->assertGreaterThan(1000, strlen($pdf));
    }

    /**
     * @param  array<int, array<string, mixed>>  $approvals
     * @return array<string, mixed>
     */
    private function payload(array $approvals): array
    {
        return [
            'document' => [
                'id' => 10,
                'name' => 'Komunikasi, Konsultasi & Partisipasi',
                'number' => 'IK-HMK-01-05',
                'revision' => 0,
                'revision_label' => '00.00',
                'revision_form_number' => null,
                'published_at' => now()->toDateString(),
                'approved_at' => now(),
                'type' => 'Instruksi Kerja',
                'level' => [
                    'id' => 3,
                    'code' => 'level-3',
                    'name' => 'Level III',
                    'document_name' => 'Instruksi Kerja',
                ],
                'business_process' => 'Komunikasi',
                'business_function' => 'Human Capital',
                'departments' => [
                    [
                        'id' => 1,
                        'name' => 'Human Capital',
                        'code' => 'HMK',
                    ],
                ],
            ],
            'approvals' => $approvals,
            'source' => [
                'id' => 99,
                'type' => 'filled_template',
                'path_file' => 'documents/10/source.pdf',
                'original_file_name' => 'source.pdf',
                'stored_file_name' => 'source.pdf',
                'file_size' => 2048,
            ],
        ];
    }
}
