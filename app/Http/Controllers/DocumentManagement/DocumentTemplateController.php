<?php

namespace App\Http\Controllers\DocumentManagement;

use App\Http\Controllers\Controller;
use App\Models\DocumentTemplate;
use App\Models\DocumentTemplateFile;
use App\Support\DocumentTemplates\DocumentTemplateUploadRules;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

class DocumentTemplateController extends Controller
{
    public function index(): View
    {
        $templates = DocumentTemplate::query()
            ->active()
            ->with(['files' => fn ($query) => $query->orderBy('file_order')])
            ->get()
            ->keyBy('document_level');

        return view('document-management.templates.index', [
            'templates' => $templates,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $canEdit = $request->user()->hasAnyPermission(['document-templates.edit', 'document-templates.manage']);
        $canDelete = $request->user()->hasAnyPermission(['document-templates.delete', 'document-templates.manage']);

        abort_unless($canEdit || $canDelete, 403);

        $validated = $request->validate(
            DocumentTemplateUploadRules::rules(requireFiles: false),
            DocumentTemplateUploadRules::messages(),
        );

        $activeTemplate = DocumentTemplate::query()
            ->forLevel($validated['document_level'])
            ->active()
            ->with('files')
            ->first();
        $uploadedFiles = collect($request->file('template_files', []));
        $retainedFileIdsWereSubmitted = $request->boolean('retained_template_file_ids_present');
        $retainedFileIds = collect($request->input('retained_template_file_ids', []))
            ->map(fn ($fileId) => (int) $fileId)
            ->unique()
            ->values();

        $retainedFiles = collect();

        if ($activeTemplate) {
            $activeFiles = $activeTemplate->files->sortBy('file_order')->values();

            if ($retainedFileIdsWereSubmitted) {
                $invalidRetainedFileIds = $retainedFileIds->diff($activeFiles->pluck('id'));

                if ($invalidRetainedFileIds->isNotEmpty()) {
                    throw ValidationException::withMessages([
                        'retained_template_file_ids' => 'File template lama tidak valid untuk level dokumen ini.',
                    ]);
                }

                $retainedFiles = $activeFiles
                    ->whereIn('id', $retainedFileIds)
                    ->values();
            } elseif ($uploadedFiles->isEmpty()) {
                $retainedFiles = $activeFiles;
            }
        }

        if (! $canEdit) {
            $activeFileCount = $activeTemplate?->files->count() ?? 0;
            $isDeletingRetainedFiles = $retainedFileIdsWereSubmitted && $retainedFileIds->count() < $activeFileCount;

            abort_unless($canDelete && $activeTemplate && $isDeletingRetainedFiles && $uploadedFiles->isEmpty(), 403);

            $validated['title'] = $retainedFiles->isEmpty() ? null : $activeTemplate->title;
            $validated['notes'] = $retainedFiles->isEmpty() ? null : $activeTemplate->notes;
        }

        if ($retainedFiles->count() + $uploadedFiles->count() > DocumentTemplate::MAX_FILES) {
            throw ValidationException::withMessages([
                'template_files' => 'Maksimal '.DocumentTemplate::MAX_FILES.' file template.',
            ]);
        }

        $hasTemplateFiles = $retainedFiles->isNotEmpty() || $uploadedFiles->isNotEmpty();
        $title = trim((string) ($validated['title'] ?? ''));

        if ($hasTemplateFiles && $title === '') {
            throw ValidationException::withMessages([
                'title' => 'Judul template wajib diisi jika template memiliki file.',
            ]);
        }

        $validated['title'] = $title === '' ? null : $title;

        DB::transaction(function () use ($request, $validated, $retainedFiles): void {
            DocumentTemplate::query()
                ->forLevel($validated['document_level'])
                ->update([
                    'is_active' => false,
                    'active_template_key' => null,
                ]);

            $nextVersion = ((int) DocumentTemplate::query()
                ->forLevel($validated['document_level'])
                ->max('version_number')) + 1;

            $template = DocumentTemplate::query()->create([
                'document_level' => $validated['document_level'],
                'version_number' => $nextVersion,
                'title' => $validated['title'],
                'notes' => $validated['notes'] ?? null,
                'uploaded_by' => $request->user()->id,
                'is_active' => true,
                'active_template_key' => $validated['document_level'],
                'activated_at' => now(),
            ]);

            $fileOrder = 1;

            foreach ($retainedFiles as $file) {
                $extension = pathinfo($file->stored_file_name, PATHINFO_EXTENSION);
                $storedFileName = uniqid('template_', true).($extension ? ".{$extension}" : '');
                $path = "document-templates/{$template->id}/{$storedFileName}";

                $this->copyRetainedTemplateFile($file, $path);

                $template->files()->create([
                    'file_order' => $fileOrder++,
                    'disk' => $file->disk,
                    'path_file' => $path,
                    'original_file_name' => $file->original_file_name,
                    'stored_file_name' => $storedFileName,
                    'mime_type' => $file->mime_type,
                    'file_size' => $file->file_size,
                ]);
            }

            foreach ($request->file('template_files', []) as $file) {
                $path = $this->storeUploadedTemplateFile($file, "document-templates/{$template->id}");

                $template->files()->create([
                    'file_order' => $fileOrder++,
                    'disk' => 'local',
                    'path_file' => $path,
                    'original_file_name' => $file->getClientOriginalName(),
                    'stored_file_name' => basename($path),
                    'mime_type' => $file->getClientMimeType(),
                    'file_size' => $file->getSize(),
                ]);
            }
        });

        return redirect()
            ->route('document-templates.index')
            ->with('status', 'Template dokumen berhasil disimpan.')
            ->with('active_template_level', $validated['document_level']);
    }

    public function file(Request $request, DocumentTemplateFile $file): BinaryFileResponse
    {
        abort_unless($request->user()->hasAnyPermission(['document-templates.download', 'document-templates.edit', 'document-templates.manage']), 403);
        abort_unless($file->documentTemplate()->where('is_active', true)->exists(), 404);

        $path = Storage::disk($file->disk)->path($file->path_file);
        abort_unless(is_file($path), 404);

        return response()->file($path, [
            'Content-Disposition' => 'inline; filename="'.$file->original_file_name.'"',
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
            'Expires' => '0',
        ]);
    }

    /**
     * @throws ValidationException
     */
    private function copyRetainedTemplateFile(DocumentTemplateFile $file, string $path): void
    {
        $disk = Storage::disk($file->disk);

        if (! $disk->exists($file->path_file)) {
            throw ValidationException::withMessages([
                'template_files' => 'File template lama tidak ditemukan di storage.',
            ]);
        }

        try {
            $copied = $disk->copy($file->path_file, $path);
        } catch (Throwable) {
            throw ValidationException::withMessages([
                'template_files' => 'Gagal menyalin file template lama. Coba simpan ulang template.',
            ]);
        }

        if (! $copied) {
            throw ValidationException::withMessages([
                'template_files' => 'Gagal menyalin file template lama. Coba simpan ulang template.',
            ]);
        }
    }

    /**
     * @throws ValidationException
     */
    private function storeUploadedTemplateFile(UploadedFile $file, string $directory): string
    {
        try {
            $path = $file->store($directory, 'local');
        } catch (Throwable) {
            throw ValidationException::withMessages([
                'template_files' => 'Gagal menyimpan file template. Coba upload ulang file.',
            ]);
        }

        if ($path === false) {
            throw ValidationException::withMessages([
                'template_files' => 'Gagal menyimpan file template. Coba upload ulang file.',
            ]);
        }

        return $path;
    }
}
