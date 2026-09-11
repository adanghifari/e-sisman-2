@php
    $documentType = trim((string) ($document['type'] ?? ($document['level']['document_name'] ?? '')));
    $documentName = trim((string) ($document['name'] ?? ''));
    $documentNumber = trim((string) ($document['number'] ?? ''));
    $revisionLabel = trim((string) ($document['revision_label'] ?? $document['revision'] ?? ''));
    $publishedAt = $document['published_at'] ?? null;
    $currentPage = $page['current_page'] ?? null;
    $totalPages = $page['total_pages'] ?? null;
    $usePageCounter = (bool) ($page['use_page_counter'] ?? false);
    $pageLabel = trim((string) ($page['label'] ?? ''));
    $formatDate = static function ($value): string {
        if (blank($value)) {
            return '-';
        }

        try {
            return \Illuminate\Support\Carbon::parse($value)->format('d - m - Y');
        } catch (\Throwable) {
            return (string) $value;
        }
    };
    $pageText = match (true) {
        $pageLabel !== '' => $pageLabel,
        ! $usePageCounter && filled($currentPage) && filled($totalPages) => "{$currentPage} dari {$totalPages}",
        default => '-',
    };
@endphp

<table class="content-header">
    <tr>
        <td class="header-brand" rowspan="4">
            @if (is_file($logoPath))
                <img class="header-logo" src="{{ $logoPath }}" alt="Krakatau International Port">
            @else
                <strong>KRAKATAU INTERNATIONAL PORT</strong>
            @endif
            <div class="header-company">PT KRAKATAU BANDAR SAMUDERA</div>
        </td>
        <td class="header-title" rowspan="4">
            <table class="header-title-table">
                <tr>
                    <td>
                        <div class="header-document-type">{{ \Illuminate\Support\Str::upper($documentType !== '' ? $documentType : '-') }}</div>
                        <div class="header-system-title">SISTEM MANAJEMEN KBS</div>
                    </td>
                </tr>
                <tr>
                    <td>
                        <div class="header-document-name">{{ \Illuminate\Support\Str::upper($documentName !== '' ? $documentName : '-') }}</div>
                    </td>
                </tr>
            </table>
        </td>
        <td class="header-meta-label">No. Dok.</td>
        <td class="header-meta-value">: {{ $documentNumber !== '' ? $documentNumber : '-' }}</td>
    </tr>
    <tr>
        <td class="header-meta-label">Revisi</td>
        <td class="header-meta-value">: {{ $revisionLabel !== '' ? $revisionLabel : '-' }}</td>
    </tr>
    <tr>
        <td class="header-meta-label">Tgl. Terbit</td>
        <td class="header-meta-value">: {{ $formatDate($publishedAt) }}</td>
    </tr>
    <tr>
        <td class="header-meta-label">Halaman</td>
        <td class="header-meta-value">
            @if ($usePageCounter)
                :<span class="header-page-counter"></span>
            @else
                : {{ $pageText }}
            @endif
        </td>
    </tr>
</table>
