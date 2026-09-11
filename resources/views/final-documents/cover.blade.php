@php
    $documentName = trim((string) ($document['name'] ?? ''));
    $documentType = trim((string) ($document['type'] ?? ($document['level']['document_name'] ?? '')));
    $documentNumber = trim((string) ($document['number'] ?? ''));
    $revisionLabel = trim((string) ($document['revision_label'] ?? $document['revision'] ?? ''));
    $levelName = trim((string) ($document['level']['name'] ?? ''));
    $levelCode = trim((string) ($document['level']['code'] ?? ''));
    $publishedAt = $document['published_at'] ?? null;
    $preparers = collect($preparers)->values();
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
    $levelDisplay = static function (?string $name, ?string $code): string {
        $source = trim((string) ($code ?: $name));

        if ($source === '') {
            return '-';
        }

        if (preg_match('/(\d+)/', $source, $matches) === 1) {
            return 'LEVEL '.$matches[1];
        }

        return strtoupper($source);
    };
@endphp

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Cover Final Document</title>
    <style>
        @page {
            margin: 20mm 18mm 18mm;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            color: #111111;
            font-family: "DejaVu Sans", Arial, sans-serif;
            font-size: 10.5pt;
            line-height: 1.35;
        }

        .cover {
            width: 100%;
            min-height: 254mm;
            position: relative;
        }

        .side-icon {
            position: absolute;
            right: -18mm;
            top: 56mm;
            width: 14mm;
            height: auto;
        }

        .top-table,
        .identity-table,
        .preparer-table,
        .bottom-table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }

        .top-table {
            margin-bottom: 50mm;
        }

        .brand-cell {
            width: 58%;
            vertical-align: top;
        }

        .brand-logo {
            width: 60mm;
            height: auto;
        }

        .company-box-cell {
            width: 42%;
            text-align: right;
            vertical-align: top;
        }

        .company-box {
            display: inline-block;
            min-width: 60mm;
            border: 0.8pt solid #ff4d4d;
            padding: 8pt 12pt;
            font-size: 12pt;
            font-style: italic;
            font-weight: 700;
            text-align: center;
        }

        .identity-table {
            margin: 0 auto 18mm;
            text-align: center;
        }

        .document-title {
            padding: 0 10mm 9mm;
            font-size: 24pt;
            font-weight: 700;
            line-height: 1.18;
            text-transform: uppercase;
            word-wrap: break-word;
        }

        .document-type,
        .document-level,
        .document-number {
            padding-bottom: 3.5mm;
            font-size: 15pt;
            font-weight: 700;
            line-height: 1.25;
            text-transform: uppercase;
            word-wrap: break-word;
        }

        .document-number {
            font-size: 14pt;
        }

        .divider {
            border-top: 1pt solid #111111;
            margin: 0 0 7mm;
            height: 0;
        }

        .preparer-label {
            margin: 0 0 4mm;
            font-size: 11pt;
            font-weight: 700;
        }

        .preparer-table th,
        .preparer-table td {
            border: 0.8pt solid #111111;
            padding: 5pt 6pt;
            vertical-align: top;
            word-wrap: break-word;
        }

        .preparer-table th {
            font-weight: 700;
            text-align: center;
        }

        .preparer-no {
            width: 10mm;
            text-align: center;
        }

        .preparer-name {
            width: 52mm;
        }

        .preparer-position {
            width: 54mm;
        }

        .preparer-department {
            width: auto;
        }

        .bottom-meta {
            position: absolute;
            right: 0;
            bottom: 0;
            width: 75mm;
        }

        .bottom-table td {
            border: 0.8pt solid #111111;
            padding: 4pt 6pt;
            font-size: 10pt;
            font-weight: 700;
            line-height: 1.25;
            white-space: nowrap;
        }

        .bottom-label {
            width: 28mm;
        }

        .bottom-value {
            width: 47mm;
        }
    </style>
</head>
<body>
    <main class="cover">
        @if (is_file($sideIconPath))
            <img class="side-icon" src="{{ $sideIconPath }}" alt="">
        @endif

        <table class="top-table">
            <tr>
                <td class="brand-cell">
                    @if (is_file($logoPath))
                        <img class="brand-logo" src="{{ $logoPath }}" alt="Krakatau International Port">
                    @else
                        <strong>KRAKATAU INTERNATIONAL PORT</strong>
                    @endif
                </td>
                <td class="company-box-cell">
                    <div class="company-box">DOKUMEN PERUSAHAAN</div>
                </td>
            </tr>
        </table>

        <table class="identity-table">
            <tr>
                <td class="document-title">{{ $documentName !== '' ? \Illuminate\Support\Str::upper($documentName) : '-' }}</td>
            </tr>
            <tr>
                <td class="document-type">{{ $documentType !== '' ? \Illuminate\Support\Str::upper($documentType) : '-' }}</td>
            </tr>
            <tr>
                <td class="document-level">{{ $levelDisplay($levelName, $levelCode) }}</td>
            </tr>
            <tr>
                <td class="document-number">{{ $documentNumber !== '' ? $documentNumber : '-' }}</td>
            </tr>
        </table>

        <div class="divider"></div>

        <p class="preparer-label">Disusun oleh:</p>

        <table class="preparer-table">
            <thead>
                <tr>
                    <th class="preparer-no">No</th>
                    <th class="preparer-name">Nama</th>
                    <th class="preparer-position">Jabatan</th>
                    <th class="preparer-department">Department</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($preparers as $preparer)
                    <tr>
                        <td class="preparer-no">{{ $loop->iteration }}</td>
                        <td>{{ $preparer['name'] ?? '-' }}</td>
                        <td>{{ $preparer['position'] ?? '-' }}</td>
                        <td>{{ $preparer['department'] ?? '-' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td class="preparer-no">1</td>
                        <td>-</td>
                        <td>-</td>
                        <td>-</td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        <div class="bottom-meta">
            <table class="bottom-table">
                <tr>
                    <td class="bottom-label">REVISI</td>
                    <td class="bottom-value">: {{ $revisionLabel !== '' ? $revisionLabel : '-' }}</td>
                </tr>
                <tr>
                    <td class="bottom-label">TGL TERBIT</td>
                    <td class="bottom-value">: {{ $formatDate($publishedAt) }}</td>
                </tr>
            </table>
        </div>
    </main>
</body>
</html>
