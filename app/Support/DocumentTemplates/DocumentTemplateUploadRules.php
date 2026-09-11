<?php

namespace App\Support\DocumentTemplates;

use Illuminate\Validation\Rule;

class DocumentTemplateUploadRules
{
    /**
     * @return array<string, mixed>
     */
    public static function rules(bool $requireFiles = true): array
    {
        $upload = config('document-templates.upload');

        return [
            'document_level' => [
                'required',
                'string',
                Rule::in(array_keys(config('document-levels'))),
            ],
            'title' => [
                'nullable',
                'string',
                'max:255',
            ],
            'notes' => [
                'nullable',
                'string',
                'max:2000',
            ],
            'template_files' => [
                $requireFiles ? 'required' : 'nullable',
                'array',
                $requireFiles ? 'min:1' : 'min:0',
                'max:'.$upload['max_files'],
            ],
            'template_files.*' => [
                $requireFiles ? 'required' : 'nullable',
                'file',
                'max:'.$upload['max_file_size_kb'],
                'mimes:'.implode(',', $upload['allowed_extensions']),
            ],
            'retained_template_file_ids_present' => [
                'nullable',
                'boolean',
            ],
            'retained_template_file_ids' => [
                'nullable',
                'array',
                'max:'.$upload['max_files'],
            ],
            'retained_template_file_ids.*' => [
                'integer',
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function messages(): array
    {
        $upload = config('document-templates.upload');
        $maxFileSizeMb = (int) ceil($upload['max_file_size_kb'] / 1024);
        $allowedExtensions = strtoupper(implode(', ', $upload['allowed_extensions']));

        return [
            'document_level.required' => 'Level dokumen wajib dipilih.',
            'document_level.in' => 'Level dokumen tidak valid.',
            'title.max' => 'Judul template maksimal 255 karakter.',
            'notes.max' => 'Catatan singkat maksimal 2000 karakter.',
            'template_files.required' => 'File template wajib diunggah.',
            'template_files.array' => 'File template tidak valid.',
            'template_files.min' => 'Minimal unggah 1 file template.',
            'template_files.max' => 'Maksimal '.$upload['max_files'].' file template.',
            'template_files.*.required' => 'File template wajib diunggah.',
            'template_files.*.uploaded' => 'File gagal diunggah. Pastikan ukuran file tidak melebihi batas server.',
            'template_files.*.file' => 'File template tidak valid.',
            'template_files.*.max' => 'Ukuran maksimal per file adalah '.$maxFileSizeMb.' MB.',
            'template_files.*.mimes' => 'Format file template harus '.$allowedExtensions.'.',
            'retained_template_file_ids.array' => 'Daftar file template lama tidak valid.',
            'retained_template_file_ids.max' => 'Maksimal '.$upload['max_files'].' file template.',
            'retained_template_file_ids.*.integer' => 'File template lama tidak valid.',
        ];
    }
}
