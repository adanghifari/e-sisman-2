<?php

use App\Http\Controllers\Dashboard\DashboardController;
use App\Http\Controllers\DigitalSignatureVerificationController;
use App\Http\Controllers\DocumentManagement\DocumentApprovalController;
use App\Http\Controllers\DocumentManagement\DocumentController;
use App\Http\Controllers\DocumentManagement\DocumentInboxController;
use App\Http\Controllers\DocumentManagement\DocumentMasterController;
use App\Http\Controllers\DocumentManagement\DocumentObsoleteController;
use App\Http\Controllers\DocumentManagement\DocumentTemplateController;
use App\Http\Controllers\DocumentManagement\ImportedExistingDocumentController;
use App\Http\Controllers\Log\ActivityLogController;
use App\Http\Controllers\Log\ActivityLogExportController;
use App\Http\Controllers\Reporting\OverviewController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::get('ttd-digital/{approval}', DigitalSignatureVerificationController::class)
    ->name('digital-signatures.verify');

Route::middleware(['auth', 'verified', 'route.permission'])->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');

    Route::get('documents/inbox', DocumentInboxController::class)->name('documents.inbox');
    Route::get('documents/inbox/{document}', [DocumentApprovalController::class, 'show'])->name('documents.approval.show');
    Route::get('documents/inbox/{document}/assign', fn ($document) => redirect()->route('documents.approval.show', $document));
    Route::post('documents/inbox/{document}', [DocumentApprovalController::class, 'assign'])->name('documents.approval.assign');
    Route::post('documents/inbox/{document}/update-submitted', [DocumentApprovalController::class, 'updateSubmitted'])->name('documents.approval.update-submitted');
    Route::post('documents/inbox/{document}/approve', [DocumentApprovalController::class, 'approve'])->name('documents.approval.approve');
    Route::post('documents/inbox/{document}/reject', [DocumentApprovalController::class, 'reject'])->name('documents.approval.reject');
    Route::post('documents/inbox/{document}/assign', fn ($document) => redirect()
        ->route('documents.approval.show', $document)
        ->withErrors(['stage_approvers' => 'Halaman assign sudah diperbarui. Silakan simpan ulang dari detail dokumen.']));
    Route::get('documents/inbox/{document}/files/{file}', [DocumentApprovalController::class, 'file'])->name('documents.approval.files.show');
    Route::get('documents/inbox/{document}/files/{file}/preview', [DocumentApprovalController::class, 'preview'])->name('documents.approval.files.preview');
    Route::get('documents/inbox/{document}/generated', [DocumentApprovalController::class, 'generatedFile'])->name('documents.approval.generated.show');

    Route::get('documents/create', [DocumentController::class, 'index'])->name('documents.create');
    Route::get('documents/drafts', [DocumentController::class, 'drafts'])->name('documents.create.drafts');
    Route::get('documents/drafts/{document}/edit', [DocumentController::class, 'editDraft'])->name('documents.create.drafts.edit');
    Route::get('documents/rejected/{document}/resubmit', [DocumentController::class, 'resubmitRejected'])->name('documents.rejected.resubmit');
    Route::delete('documents/drafts/{document}', [DocumentController::class, 'destroyDraft'])->name('documents.create.drafts.destroy');
    Route::get('documents/create/{level}', [DocumentController::class, 'create'])
        ->where('level', implode('|', array_keys(config('document-levels'))))
        ->name('documents.create.level');
    Route::post('documents/create/{level}', [DocumentController::class, 'store'])
        ->where('level', implode('|', array_keys(config('document-levels'))))
        ->name('documents.store');
    Route::post('documents/create/{level}/autosave', [DocumentController::class, 'autosave'])
        ->where('level', implode('|', array_keys(config('document-levels'))))
        ->name('documents.autosave');

    Route::get('documents/master', DocumentMasterController::class)->name('documents.master');
    Route::get('documents/master/imports/create', [ImportedExistingDocumentController::class, 'createMaster'])->name('documents.master.imports.create');
    Route::get('documents/master/imports/create/{level}', [ImportedExistingDocumentController::class, 'createMasterLevel'])
        ->where('level', 'level-1|level-2|level-3')
        ->name('documents.master.imports.create.level');
    Route::post('documents/master/imports', [ImportedExistingDocumentController::class, 'storeMaster'])->name('documents.master.imports.store');
    Route::post('documents/master/imports/{level}', [ImportedExistingDocumentController::class, 'storeMasterLevel'])
        ->where('level', 'level-1|level-2|level-3')
        ->name('documents.master.imports.store.level');

    Route::get('documents/obsolete', DocumentObsoleteController::class)->name('documents.obsolete');
    Route::get('documents/obsolete/imports/create', fn () => redirect()->route('documents.obsolete.imports.create'));
    Route::get('documents/obsolete/imports/create/legacy', fn () => redirect()->route('documents.obsolete.imports.create.legacy'));
    Route::get('documents/obsolete/imports', [ImportedExistingDocumentController::class, 'createObsolete'])->name('documents.obsolete.imports.create');
    Route::get('documents/obsolete/imports/legacy', [ImportedExistingDocumentController::class, 'createObsoleteLegacy'])->name('documents.obsolete.imports.create.legacy');
    Route::get('documents/obsolete/imports/{level}', [ImportedExistingDocumentController::class, 'createObsoleteLevel'])
        ->where('level', 'level-1|level-2|level-3')
        ->name('documents.obsolete.imports.create.level');
    Route::post('documents/obsolete/imports', [ImportedExistingDocumentController::class, 'storeObsolete'])->name('documents.obsolete.imports.store');
    Route::post('documents/obsolete/imports/{level}', [ImportedExistingDocumentController::class, 'storeObsoleteLevel'])
        ->where('level', 'level-1|level-2|level-3')
        ->name('documents.obsolete.imports.store.level');

    Route::get('documents/existing/imports', [ImportedExistingDocumentController::class, 'index'])->name('documents.existing.imports.index');
    Route::post('documents/existing/imports/numbering-setups', [ImportedExistingDocumentController::class, 'storeNumberingSetup'])->name('documents.existing.imports.numbering-setups.store');
    Route::post('documents/existing/imports/number-reuse-check', [ImportedExistingDocumentController::class, 'numberReuseCheck'])->name('documents.existing.imports.number-reuse-check');
    Route::delete('documents/existing/imports/{document}', [ImportedExistingDocumentController::class, 'destroy'])->name('documents.existing.imports.destroy');
    Route::get('documents/existing/imports/{document}', [ImportedExistingDocumentController::class, 'show'])->name('documents.existing.imports.show');
    Route::get('documents/existing/imports/{document}/files/{file}', [ImportedExistingDocumentController::class, 'file'])->name('documents.existing.imports.files.show');
    Route::get('documents/existing/imports/{document}/files/{file}/preview', [ImportedExistingDocumentController::class, 'preview'])->name('documents.existing.imports.files.preview');

    Route::get('documents/obsolete/{document}', [DocumentObsoleteController::class, 'show'])->name('documents.obsolete.show');
    Route::post('documents/obsolete/{document}/restore', [DocumentObsoleteController::class, 'restore'])->name('documents.obsolete.restore');
    Route::get('documents/obsolete/{document}/files/{file}', [DocumentObsoleteController::class, 'file'])->name('documents.obsolete.files.show');
    Route::get('documents/obsolete/{document}/files/{file}/preview', [DocumentObsoleteController::class, 'preview'])->name('documents.obsolete.files.preview');
    Route::get('documents/obsolete/{document}/generated', [DocumentObsoleteController::class, 'generatedFile'])->name('documents.obsolete.generated.show');

    Route::get('document-templates', [DocumentTemplateController::class, 'index'])->name('document-templates.index');
    Route::post('document-templates', [DocumentTemplateController::class, 'store'])->name('document-templates.store');
    Route::get('document-templates/files/{file}', [DocumentTemplateController::class, 'file'])->name('document-templates.files.show');

    Route::get('documents/master/imported/{document}', [DocumentMasterController::class, 'showImported'])->name('documents.master.imported.show');
    Route::get('documents/master/imported/{document}/edit', [ImportedExistingDocumentController::class, 'editMaster'])->name('documents.master.imports.edit');
    Route::match(['put', 'patch', 'post'], 'documents/master/imported/{document}', [ImportedExistingDocumentController::class, 'updateMaster'])->name('documents.master.imports.update');
    Route::post('documents/master/imported/{document}/obsolete', [DocumentMasterController::class, 'obsoleteImported'])->name('documents.master.imported.obsolete');
    Route::get('documents/master/{document}', [DocumentMasterController::class, 'show'])->name('documents.master.show');
    Route::post('documents/master/{document}/obsolete', [DocumentMasterController::class, 'obsolete'])->name('documents.master.obsolete');
    Route::post('documents/master/{document}/restore', [DocumentMasterController::class, 'restore'])->name('documents.master.restore');
    Route::get('documents/master/{document}/files/{file}', [DocumentMasterController::class, 'file'])->name('documents.master.files.show');
    Route::get('documents/master/{document}/files/{file}/preview', [DocumentMasterController::class, 'preview'])->name('documents.master.files.preview');
    Route::get('documents/master/{document}/generated', [DocumentMasterController::class, 'generatedFile'])->name('documents.master.generated.show');

    Route::get('reports', OverviewController::class)->name('reports.index');
    Route::get('reports/export', [OverviewController::class, 'export'])->name('reports.export');
    Route::get('activity-log/export', ActivityLogExportController::class)->name('activity-log.export');
    Route::get('activity-log', ActivityLogController::class)->name('activity-log.index');
});