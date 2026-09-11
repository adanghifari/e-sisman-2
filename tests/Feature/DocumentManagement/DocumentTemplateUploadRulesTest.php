<?php

namespace Tests\Feature\DocumentManagement;

use App\Support\DocumentTemplates\DocumentTemplateUploadRules;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class DocumentTemplateUploadRulesTest extends TestCase
{
    use RefreshDatabase;

    public function test_document_template_accepts_word_files_only(): void
    {
        $validator = Validator::make([
            'document_level' => 'level-2',
            'title' => 'Template Prosedur',
            'template_files' => [
                UploadedFile::fake()->create('template.docx', 24, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'),
                UploadedFile::fake()->create('template.doc', 24, 'application/msword'),
            ],
        ], DocumentTemplateUploadRules::rules());

        $this->assertFalse($validator->fails());
    }

    public function test_document_template_rejects_pdf_files(): void
    {
        $validator = Validator::make([
            'document_level' => 'level-2',
            'title' => 'Template Prosedur',
            'template_files' => [
                UploadedFile::fake()->create('template.pdf', 24, 'application/pdf'),
            ],
        ], DocumentTemplateUploadRules::rules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('template_files.0', $validator->errors()->messages());
    }
}
