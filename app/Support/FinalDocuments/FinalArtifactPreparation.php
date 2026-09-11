<?php

namespace App\Support\FinalDocuments;

use App\Models\DocumentFinalArtifact;

class FinalArtifactPreparation
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public DocumentFinalArtifact $artifact,
        public array $payload,
    ) {}
}
