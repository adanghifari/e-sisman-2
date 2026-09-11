<?php

namespace App\Support\FinalDocuments;

class PdfPageGeometry
{
    public function preserve(float $pageWidth, float $pageHeight, float $sourceWidth, float $sourceHeight): PdfPagePlacement
    {
        return new PdfPagePlacement(
            pageWidth: $pageWidth,
            pageHeight: $pageHeight,
            sourceWidth: $sourceWidth,
            sourceHeight: $sourceHeight,
            x: 0.0,
            y: 0.0,
            width: $sourceWidth,
            height: $sourceHeight,
            scale: 1.0,
        );
    }

    public function fitToSafeArea(
        float $pageWidth,
        float $pageHeight,
        float $sourceWidth,
        float $sourceHeight,
        ?PdfSafeArea $safeArea = null,
    ): PdfPagePlacement {
        $safeArea ??= new PdfSafeArea;
        $availableWidth = $pageWidth - $safeArea->left - $safeArea->right;
        $availableHeight = $pageHeight - $safeArea->top - $safeArea->bottom;

        if ($availableWidth <= 0 || $availableHeight <= 0) {
            throw new PdfCompositionException('Page is too small for configured header/footer safe area.');
        }

        $scale = min(1.0, $availableWidth / $sourceWidth, $availableHeight / $sourceHeight);
        $width = $sourceWidth * $scale;
        $height = $sourceHeight * $scale;

        return new PdfPagePlacement(
            pageWidth: $pageWidth,
            pageHeight: $pageHeight,
            sourceWidth: $sourceWidth,
            sourceHeight: $sourceHeight,
            x: $safeArea->left + (($availableWidth - $width) / 2),
            y: $safeArea->top + (($availableHeight - $height) / 2),
            width: $width,
            height: $height,
            scale: $scale,
        );
    }

    public function fitWidthToSafeTop(
        float $pageWidth,
        float $pageHeight,
        float $sourceWidth,
        float $sourceHeight,
        ?PdfSafeArea $safeArea = null,
    ): PdfPagePlacement {
        $safeArea ??= new PdfSafeArea;
        $availableWidth = $pageWidth - $safeArea->left - $safeArea->right;

        if ($availableWidth <= 0) {
            throw new PdfCompositionException('Page is too small for configured header/footer safe area.');
        }

        $scale = min(1.0, $availableWidth / $sourceWidth);
        $width = $sourceWidth * $scale;
        $height = $sourceHeight * $scale;

        return new PdfPagePlacement(
            pageWidth: $pageWidth,
            pageHeight: $pageHeight,
            sourceWidth: $sourceWidth,
            sourceHeight: $sourceHeight,
            x: $safeArea->left + (($availableWidth - $width) / 2),
            y: $safeArea->top,
            width: $width,
            height: $height,
            scale: $scale,
        );
    }
}
