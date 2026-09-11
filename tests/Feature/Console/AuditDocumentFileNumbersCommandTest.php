<?php

namespace Tests\Feature\Console;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditDocumentFileNumbersCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_document_file_numbers_audit_command_outputs_json_summary(): void
    {
        $this->artisan('documents:file-numbers:audit --json')
            ->expectsOutput(json_encode([
                'total_files' => 0,
                'missing_document_number' => 0,
                'missing_parent_number' => 0,
                'main_files_missing_number' => 0,
                'revision_forms_missing_number' => 0,
                'attachments_missing_number' => 0,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES))
            ->assertExitCode(0);
    }
}
