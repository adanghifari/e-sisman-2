<?php

namespace App\Support\FinalDocuments;

class PdfPagePlacement
{
    public function __construct(
        public float $pageWidth,
        public float $pageHeight,
        public float $sourceWidth,
        public float $sourceHeight,
        public float $x,
        public float $y,
        public float $width,
        public float $height,
        public float $scale,
    ) {}
}
