<x-layouts.app :title="__('Template Dokumen')">
    <x-document-templates.builder
        :document-levels="config('document-levels')"
        :templates="$templates"
        :can-edit="auth()->user()->hasAnyPermission(['document-templates.edit', 'document-templates.manage'])"
        :can-delete="auth()->user()->hasAnyPermission(['document-templates.delete', 'document-templates.manage'])"
        :upload-limits="config('document-templates.upload')"
        :active-level="request('level', old('document_level', session('active_template_level')))"
    />
</x-layouts.app>
