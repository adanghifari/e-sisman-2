<?php

namespace App\Support\FinalDocuments;

use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Contracts\View\Factory as ViewFactory;

class CoverPdfRenderer
{
    public function __construct(
        private readonly ViewFactory $view,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function render(array $payload): string
    {
        $html = $this->renderHtml($payload);
        $dompdf = new Dompdf($this->options());
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('a4', 'portrait');
        $dompdf->render();

        return $dompdf->output();
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function renderHtml(array $payload): string
    {
        return $this->view->make('final-documents.cover', [
            'document' => $payload['document'] ?? [],
            'preparers' => $payload['preparers'] ?? [],
            'logoPath' => public_path('image/kopsuratlogo.jpeg'),
            'sideIconPath' => public_path('image/sideicon.jpeg'),
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
