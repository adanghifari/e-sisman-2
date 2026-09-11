<?php

namespace App\Support\FinalDocuments;

use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Contracts\View\Factory as ViewFactory;

class ContentPagePdfRenderer
{
    public function __construct(
        private readonly ViewFactory $view,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $page
     * @param  array<string, mixed>  $sample
     */
    public function render(array $payload, array $page = [], array $sample = []): string
    {
        $html = $this->renderHtml($payload, $page, $sample);
        $dompdf = new Dompdf($this->options());
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('a4', 'portrait');
        $dompdf->render();

        return $dompdf->output();
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $page
     * @param  array<string, mixed>  $sample
     */
    public function renderHtml(array $payload, array $page = [], array $sample = []): string
    {
        return $this->view->make('final-documents.content-page-sample', [
            'document' => $payload['document'] ?? [],
            'page' => $page,
            'sample' => $sample,
            'logoPath' => public_path('image/kopsuratlogo.jpeg'),
        ])->render();
    }

    private function options(): Options
    {
        $options = new Options;
        $options->set('isRemoteEnabled', false);
        $options->set('isHtml5ParserEnabled', true);
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('chroot', public_path());

        return $options;
    }
}
