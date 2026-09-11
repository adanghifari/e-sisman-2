<?php

namespace App\Support\FinalDocuments;

class FinalPdfCompositionResult
{
    /**
     * @param  array<int, array<string, mixed>>  $bodyPages
     */
    public function __construct(
        public string $pdf,
        public int $coverPages,
        public int $approvalSheetPages,
        public int $bodyPagesCount,
        public array $bodyPages,
    ) {}

    public function totalPages(): int
    {
        return $this->coverPages + $this->approvalSheetPages + $this->bodyPagesCount;
    }
}
