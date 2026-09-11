<?php

namespace App\Support\FinalDocuments;

use Carbon\Carbon;
use DateTimeInterface;
use Illuminate\Support\Str;
use setasign\Fpdi\Tcpdf\Fpdi;
use Throwable;

class DocumentWatermarkStamp
{
    public const STAMP_COPY = 'COPY';

    public const STAMP_MASTER = 'MASTER';

    public const STAMP_OBSOLETE = 'OBSOLETE';

    /**
     * @return array{
     *     title: string,
     *     banner_text: string,
     *     rows: array<int, array{label: string, value: string}>
     * }
     */
    public static function forDownload(string $userName, DateTimeInterface|string $downloadTime, int $downloadCount): array
    {
        $timeStr = $downloadTime instanceof DateTimeInterface
            ? $downloadTime->format('Y-m-d H.i')
            : (string) $downloadTime;

        return [
            'title' => self::STAMP_COPY,
            'banner_text' => 'ESISMAN PT KBS',
            'rows' => [
                ['label' => 'Diunduh Oleh', 'value' => Str::upper(trim($userName))],
                ['label' => 'Waktu Unduh', 'value' => $timeStr],
                ['label' => 'Unduhan Ke', 'value' => (string) max(1, $downloadCount)],
            ],
        ];
    }

    /**
     * @return array{
     *     title: string,
     *     banner_text: string,
     *     rows: array<int, array{label: string, value: string}>
     * }
     */
    public static function forMaster(string $documentNumber, string $revision, DateTimeInterface|string|null $publishedAt = null): array
    {
        if ($publishedAt instanceof DateTimeInterface) {
            $publishedStr = $publishedAt->format('d-m-Y');
        } elseif (filled($publishedAt)) {
            try {
                $publishedStr = Carbon::parse($publishedAt)->format('d-m-Y');
            } catch (Throwable) {
                $publishedStr = (string) $publishedAt;
            }
        } else {
            $publishedStr = '-';
        }

        return [
            'title' => self::STAMP_MASTER,
            'banner_text' => 'ESISMAN PT KBS',
            'rows' => [
                ['label' => 'Nomor Dokumen', 'value' => $documentNumber],
                ['label' => 'Revisi Ke', 'value' => $revision],
                ['label' => 'Tanggal Terbit', 'value' => $publishedStr],
            ],
        ];
    }

    /**
     * @return array{
     *     title: string,
     *     banner_text: string,
     *     rows: array<int, array{label: string, value: string}>
     * }
     */
    public static function forObsolete(string $documentNumber, string $revision, DateTimeInterface|string|null $obsoleteAt = null): array
    {
        if ($obsoleteAt instanceof DateTimeInterface) {
            $obsoleteStr = $obsoleteAt->format('d-m-Y');
        } elseif (filled($obsoleteAt)) {
            try {
                $obsoleteStr = Carbon::parse($obsoleteAt)->format('d-m-Y');
            } catch (Throwable) {
                $obsoleteStr = (string) $obsoleteAt;
            }
        } else {
            $obsoleteStr = '-';
        }

        return [
            'title' => self::STAMP_OBSOLETE,
            'banner_text' => 'ESISMAN PT KBS',
            'rows' => [
                ['label' => 'Nomor Dokumen', 'value' => $documentNumber],
                ['label' => 'Revisi Ke', 'value' => $revision],
                ['label' => 'Tanggal Obsolete', 'value' => $obsoleteStr],
            ],
        ];
    }

    /**
     * @param  array{
     *     title?: string,
     *     banner_text?: string,
     *     rows?: array<int, array{label: string, value: string}>,
     *     width?: float,
     *     height?: float,
     *     center_x?: float,
     *     center_y?: float,
     *     angle?: float,
     *     alpha?: float,
     * }  $config
     */
    public function render(Fpdi $pdf, array $config, float $pageWidth, float $pageHeight): void
    {
        $stampWidth = (float) ($config['width'] ?? 105.0);
        $stampHeight = (float) ($config['height'] ?? 52.0);
        $centerX = (float) ($config['center_x'] ?? ($pageWidth / 2));
        $centerY = (float) ($config['center_y'] ?? 145.0);
        $angle = (float) ($config['angle'] ?? 17.0);
        $alpha = (float) ($config['alpha'] ?? 0.32);
        $title = (string) ($config['title'] ?? self::STAMP_COPY);
        $bannerText = (string) ($config['banner_text'] ?? 'ESISMAN PT KBS');
        $rows = (array) ($config['rows'] ?? []);

        // Adjust center if page is landscape
        if ($pageWidth > $pageHeight) {
            $centerX = $pageWidth * 0.50;
            $centerY = $pageHeight * 0.50;
        }

        $x = $centerX - ($stampWidth / 2);
        $y = $centerY - ($stampHeight / 2);

        $pdf->SetAlpha($alpha);
        $pdf->StartTransform();
        $pdf->Rotate($angle, $centerX, $centerY);

        $lineColor = [120, 120, 120];
        $labelColor = [75, 75, 75];
        $valueColor = [40, 40, 40];
        $pdf->SetDrawColor($lineColor[0], $lineColor[1], $lineColor[2]);
        $pdf->SetLineWidth(0.28);

        // Outer border
        $pdf->Rect($x, $y, $stampWidth, $stampHeight);

        $topHeight = 26.0;
        $leftWidth = 42.0;
        $rightWidth = $stampWidth - $leftWidth;

        // Vertical divider between logo and stamp title
        $pdf->Line($x + $leftWidth, $y, $x + $leftWidth, $y + $topHeight);

        // Horizontal divider between top section and table
        $pdf->Line($x, $y + $topHeight, $x + $stampWidth, $y + $topHeight);

        // Left box: Logo (Black & White / Grayscale)
        $logoPath = public_path('image/krakatau_logo_bw.png');
        if (! is_file($logoPath)) {
            $logoPath = public_path('image/kopsuratlogo.jpeg');
        }
        if (is_file($logoPath)) {
            $logoWidth = 37.0;
            $logoHeight = str_ends_with($logoPath, '.png') ? ($logoWidth * (200 / 800)) : ($logoWidth * (89 / 375));
            $logoX = $x + (($leftWidth - $logoWidth) / 2);
            $logoY = $y + (($topHeight - $logoHeight) / 2);
            $pdf->Image($logoPath, $logoX, $logoY, $logoWidth, $logoHeight);
        }

        // Right box top banner
        $bannerHeight = 7.0;
        $pdf->SetFillColor(140, 140, 140);
        $pdf->Rect($x + $leftWidth, $y, $rightWidth, $bannerHeight, 'F');
        $pdf->SetTextColor(255, 255, 255);
        $pdf->SetFont('helvetica', 'B', 8.0);
        $pdf->MultiCell($rightWidth, $bannerHeight, $bannerText, 0, 'C', false, 1, $x + $leftWidth, $y + 1.2);

        // Right box title (COPY / MASTER / OBSOLETE)
        $titleHeight = $topHeight - $bannerHeight;
        $pdf->SetTextColor(105, 105, 105);
        $fontSize = strlen($title) > 6 ? 17 : 21;
        $pdf->SetFont('helvetica', '', $fontSize);
        $pdf->MultiCell($rightWidth, $titleHeight, $title, 0, 'C', false, 1, $x + $leftWidth, $y + $bannerHeight + 1.8);

        // Bottom table
        $tableY = $y + $topHeight;
        $tableHeight = $stampHeight - $topHeight;
        $rowCount = max(1, count($rows));
        $rowHeight = $tableHeight / $rowCount;

        // Table vertical divider line (aligns with upper vertical divider)
        $pdf->Line($x + $leftWidth, $tableY, $x + $leftWidth, $y + $stampHeight);

        foreach ($rows as $index => $row) {
            $rowY = $tableY + ($index * $rowHeight);
            if ($index > 0) {
                $pdf->Line($x, $rowY, $x + $stampWidth, $rowY);
            }

            $label = (string) ($row['label'] ?? '');
            $value = (string) ($row['value'] ?? '');

            // Label (left-aligned)
            $pdf->SetFont('helvetica', '', 8.0);
            $pdf->SetTextColor($labelColor[0], $labelColor[1], $labelColor[2]);
            $pdf->MultiCell($leftWidth - 4, $rowHeight, $label, 0, 'L', false, 1, $x + 2.5, $rowY + 1.8);

            // Value (right-aligned)
            $pdf->SetFont('helvetica', 'B', 8.0);
            $pdf->SetTextColor($valueColor[0], $valueColor[1], $valueColor[2]);
            $pdf->MultiCell($rightWidth - 5, $rowHeight, $value, 0, 'R', false, 1, $x + $leftWidth + 2.0, $rowY + 1.8);
        }

        $pdf->StopTransform();
        $pdf->SetAlpha(1.0);
    }
}
