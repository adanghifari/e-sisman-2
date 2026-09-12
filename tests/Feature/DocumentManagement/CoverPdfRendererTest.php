<?php

namespace Tests\Feature\DocumentManagement;

use App\Support\FinalDocuments\CoverPdfRenderer;
use Tests\TestCase;

class CoverPdfRendererTest extends TestCase
{
    public function test_cover_template_renders_document_metadata_and_preparer(): void
    {
        $html = app(CoverPdfRenderer::class)->renderHtml($this->payload());

        $this->assertStringContainsString('DOKUMEN PERUSAHAAN', $html);
        $this->assertStringContainsString('KOMUNIKASI, KONSULTASI DAN PARTISIPASI', $html);
        $this->assertStringContainsString('INSTRUKSI KERJA', $html);
        $this->assertStringContainsString('LEVEL 3', $html);
        $this->assertStringContainsString('IK - HMK - 01 - 05', $html);
        $this->assertStringContainsString('00.00', $html);
        $this->assertStringContainsString('28 - 08 - 2026', $html);
        $this->assertStringContainsString('Disusun oleh:', $html);
        $this->assertStringContainsString('Penyusun Resmi', $html);
        $this->assertStringContainsString('Management System Specialist', $html);
        $this->assertStringContainsString('Human Capital', $html);
    }

    public function test_cover_template_renders_multiple_preparers_without_hardcoded_limit(): void
    {
        $html = app(CoverPdfRenderer::class)->renderHtml($this->payload([
            'preparers' => [
                [
                    'name' => 'Penyusun Satu',
                    'position' => 'Senior Specialist',
                    'department' => 'Quality Assurance',
                ],
                [
                    'name' => 'Penyusun Dua',
                    'position' => 'Operations Excellence Superintendent',
                    'department' => 'Marine Operations',
                ],
                [
                    'name' => 'Penyusun Tiga',
                    'position' => 'Management Representative',
                    'department' => 'System Management & Risk',
                ],
            ],
        ]));

        foreach (['Penyusun Satu', 'Penyusun Dua', 'Penyusun Tiga'] as $name) {
            $this->assertStringContainsString($name, $html);
        }

        $this->assertStringContainsString('Operations Excellence Superintendent', $html);
        $this->assertStringContainsString('System Management &amp; Risk', $html);
    }

    public function test_cover_pdf_output_is_created_and_valid(): void
    {
        $pdf = app(CoverPdfRenderer::class)->render($this->payload());

        $this->assertStringStartsWith('%PDF-', $pdf);
        $this->assertGreaterThan(1000, strlen($pdf));
    }

    public function test_long_title_and_missing_optional_data_do_not_crash(): void
    {
        $html = app(CoverPdfRenderer::class)->renderHtml($this->payload([
            'document' => [
                'name' => 'Pedoman Pengelolaan Komunikasi Konsultasi Partisipasi dan Koordinasi Operasional Lintas Fungsi pada Kondisi Normal dan Darurat',
                'number' => 'IK - HMK - VERY - LONG - DOCUMENT - NUMBER - 01 - 05',
                'revision_label' => null,
                'published_at' => null,
                'type' => null,
                'level' => [
                    'code' => 'level-4',
                    'name' => null,
                    'document_name' => null,
                ],
            ],
            'preparers' => [
                [
                    'name' => 'Nama Penyusun Dengan Nama Sangat Panjang Untuk Stress Test',
                    'position' => 'Jabatan Sangat Panjang Pada Unit Pengelolaan Sistem Manajemen Terintegrasi Perusahaan',
                    'department' => 'Department Dengan Nama Sangat Panjang Lintas Operasi dan Pengembangan Bisnis',
                ],
            ],
        ]));

        $this->assertStringContainsString('PEDOMAN PENGELOLAAN KOMUNIKASI', $html);
        $this->assertStringContainsString('LEVEL 4', $html);
        $this->assertStringContainsString('IK - HMK - VERY - LONG - DOCUMENT - NUMBER - 01 - 05', $html);
        $this->assertStringContainsString('Nama Penyusun Dengan Nama Sangat Panjang', $html);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_replace_recursive([
            'document' => [
                'id' => 10,
                'name' => 'Komunikasi, Konsultasi dan Partisipasi',
                'number' => 'IK - HMK - 01 - 05',
                'revision' => 0,
                'revision_label' => '00.00',
                'revision_form_number' => null,
                'published_at' => '2026-08-28',
                'approved_at' => '2026-08-28 10:00:00',
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
            'preparers' => [
                [
                    'id' => 5,
                    'name' => 'Penyusun Resmi',
                    'position' => 'Management System Specialist',
                    'department' => 'Human Capital',
                    'department_code' => 'HMK',
                ],
            ],
            'approvals' => [],
            'source' => [
                'id' => 99,
                'type' => 'filled_template',
                'path_file' => 'documents/10/source.pdf',
                'original_file_name' => 'source.pdf',
                'stored_file_name' => 'source.pdf',
                'file_size' => 2048,
            ],
        ], $overrides);
    }
}
