<?php

namespace Tests\Feature\DocumentManagement;

use App\Support\FinalDocuments\ContentPagePdfRenderer;
use Tests\TestCase;

class ContentPagePdfRendererTest extends TestCase
{
    public function test_content_header_and_footer_render_dynamic_metadata(): void
    {
        $html = app(ContentPagePdfRenderer::class)->renderHtml(
            $this->payload(),
            ['current_page' => 2, 'total_pages' => 17],
        );

        $this->assertStringContainsString('Krakatau International Port', $html);
        $this->assertStringContainsString('PT KRAKATAU BANDAR SAMUDERA', $html);
        $this->assertStringContainsString('INSTRUKSI KERJA', $html);
        $this->assertStringContainsString('SISTEM MANAJEMEN KBS', $html);
        $this->assertStringContainsString('KOMUNIKASI, KONSULTASI DAN PARTISIPASI', $html);
        $this->assertStringContainsString('IK - HMK - 01 - 05', $html);
        $this->assertStringContainsString('00.00', $html);
        $this->assertStringContainsString('27 - 08 - 2026', $html);
        $this->assertStringContainsString('2 dari 17', $html);
        $this->assertStringContainsString('Sistem Dokumentasi PT Krakatau Bandar Samudera berstandar Sistem Manajemen Terintegrasi', $html);
    }

    public function test_missing_page_numbers_render_placeholder_without_crashing(): void
    {
        $html = app(ContentPagePdfRenderer::class)->renderHtml($this->payload());

        $this->assertStringContainsString('Halaman', $html);
        $this->assertStringContainsString('>:-<', preg_replace('/\s+/', '', $html));
    }

    public function test_long_title_header_renders_without_error(): void
    {
        $html = app(ContentPagePdfRenderer::class)->renderHtml(
            $this->payload([
                'document' => [
                    'name' => 'Pedoman Pengelolaan Komunikasi Konsultasi Partisipasi dan Koordinasi Operasional Lintas Fungsi pada Kondisi Normal dan Darurat',
                    'number' => 'IK - HMK - VERY - LONG - DOCUMENT - NUMBER - 01 - 05',
                    'revision_label' => 'Rev A',
                ],
            ]),
            ['current_page' => 10, 'total_pages' => 120],
        );

        $this->assertStringContainsString('PEDOMAN PENGELOLAAN KOMUNIKASI', $html);
        $this->assertStringContainsString('IK - HMK - VERY - LONG - DOCUMENT - NUMBER - 01 - 05', $html);
        $this->assertStringContainsString('Rev A', $html);
        $this->assertStringContainsString('10 dari 120', $html);
    }

    public function test_multi_page_sample_pdf_output_is_created_and_valid(): void
    {
        $pdf = app(ContentPagePdfRenderer::class)->render(
            $this->payload(),
            ['current_page' => 1, 'total_pages' => 3],
            ['paragraph_count' => 24, 'row_count' => 30],
        );

        $this->assertStringStartsWith('%PDF-', $pdf);
        $this->assertGreaterThan(1000, strlen($pdf));
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
                'published_at' => '2026-08-27',
                'type' => 'Instruksi Kerja',
                'level' => [
                    'id' => 3,
                    'code' => 'level-3',
                    'name' => 'Level III',
                    'document_name' => 'Instruksi Kerja',
                ],
                'business_process' => 'Komunikasi',
                'business_function' => 'Human Capital',
                'departments' => [],
            ],
            'preparers' => [],
            'approvals' => [],
            'source' => [],
        ], $overrides);
    }
}
