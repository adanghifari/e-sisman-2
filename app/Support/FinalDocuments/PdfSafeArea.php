<?php

namespace App\Support\FinalDocuments;

class PdfSafeArea
{
    public function __construct(
        public float $left = 9.0,
        public float $top = 23.0,
        public float $right = 9.0,
        public float $bottom = 10.0,
    ) {}
}
