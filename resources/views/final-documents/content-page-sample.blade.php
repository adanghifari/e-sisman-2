@php
    $paragraphCount = (int) ($sample['paragraph_count'] ?? 4);
    $rowCount = (int) ($sample['row_count'] ?? 6);
@endphp

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Sample Halaman Isi Final Document</title>
    <style>
        @page {
            margin: 45mm 9mm 20mm;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            color: #111111;
            font-family: "DejaVu Sans", Arial, sans-serif;
            font-size: 10pt;
            line-height: 1.45;
        }

        .content-header {
            position: fixed;
            top: -38mm;
            left: 0;
            right: 0;
            width: 100%;
            height: 30mm;
            border-collapse: collapse;
            table-layout: fixed;
            color: #767676;
        }

        .content-header td {
            border: 0.6pt solid #777777;
            padding: 0;
            vertical-align: middle;
        }

        .header-brand {
            width: 32.5%;
            text-align: center;
        }

        .header-logo {
            width: 60mm;
            height: auto;
            margin: 6pt auto 4pt;
        }

        .header-company {
            border-top: 0.6pt solid #777777;
            padding-top: 3.5pt;
            height: 7.5mm;
            font-size: 9.5pt;
            line-height: 1.1;
        }

        .header-title {
            width: 33%;
            text-align: center;
            font-weight: 700;
            line-height: 1.08;
        }

        .header-title-table {
            width: 100%;
            height: 30mm;
            border-collapse: collapse;
            table-layout: fixed;
        }

        .content-header .header-title-table td {
            border: 0;
            padding: 0 4pt;
            text-align: center;
            vertical-align: middle;
        }

        .content-header .header-title-table tr:first-child td {
            border-bottom: 0.6pt solid #777777;
            height: 15mm;
        }

        .content-header .header-title-table tr:last-child td {
            height: 15mm;
        }

        .header-document-type {
            font-size: 11pt;
        }

        .header-system-title {
            margin-top: 1pt;
            font-size: 10pt;
        }

        .header-document-name {
            font-size: 10.5pt;
            line-height: 1.12;
            word-wrap: break-word;
        }

        .header-meta-label {
            width: 13%;
            padding-left: 5pt !important;
            font-size: 10.5pt;
            line-height: 1.2;
        }

        .header-meta-value {
            width: 21.5%;
            padding-left: 5pt !important;
            font-size: 10.5pt;
            line-height: 1.2;
            word-wrap: break-word;
        }

        .content-footer {
            position: fixed;
            right: 22mm;
            bottom: -12mm;
            left: 22mm;
            text-align: center;
            color: #666666;
            font-size: 8pt;
            line-height: 1.2;
        }

        .footer-line {
            border-top: 0.45pt solid #4f5cff;
            height: 0;
            margin: 0 0 2pt;
        }

        .sample-content h1 {
            margin: 0 0 8pt;
            font-size: 14pt;
            line-height: 1.25;
        }

        .sample-content h2 {
            margin: 16pt 0 6pt;
            font-size: 11.5pt;
        }

        .sample-content p {
            margin: 0 0 7pt;
            text-align: justify;
        }

        .sample-table {
            width: 100%;
            margin-top: 8pt;
            border-collapse: collapse;
            table-layout: fixed;
        }

        .sample-table th,
        .sample-table td {
            border: 0.6pt solid #777777;
            padding: 4pt 5pt;
            vertical-align: top;
            word-wrap: break-word;
        }

        .sample-table th {
            background: #f1f1f1;
            font-weight: 700;
            text-align: center;
        }

        .sample-no {
            width: 10mm;
            text-align: center;
        }
    </style>
</head>
<body>
    @include('final-documents.partials.content-header', [
        'document' => $document,
        'page' => $page,
        'logoPath' => $logoPath,
    ])

    @include('final-documents.partials.content-footer')

    <main class="sample-content">
        <h1>Contoh Halaman Isi Dokumen</h1>
        <p>
            Halaman ini hanya digunakan untuk memeriksa ruang aman konten terhadap kop dan footer dokumen final.
            Konten asli dokumen pengguna belum digabungkan pada tahap ini.
        </p>

        @for ($paragraph = 1; $paragraph <= $paragraphCount; $paragraph++)
            <h2>Bagian {{ $paragraph }}</h2>
            <p>
                Sistem manajemen dokumen memastikan informasi terdokumentasi dibuat, diperiksa, disahkan,
                didistribusikan, dan dipelihara sesuai kebutuhan proses bisnis. Paragraf dummy ini sengaja
                cukup panjang agar wrapping dan jarak antar elemen dapat diperiksa pada ukuran kertas A4.
            </p>
            <p>
                Setiap bagian harus tetap berada di area konten tanpa menabrak kop di bagian atas maupun footer
                di bagian bawah. Pada tahap berikutnya, area ini akan digantikan oleh halaman isi dokumen aktual.
            </p>
        @endfor

        <table class="sample-table">
            <thead>
                <tr>
                    <th class="sample-no">No</th>
                    <th>Aktivitas</th>
                    <th>Keterangan</th>
                </tr>
            </thead>
            <tbody>
                @for ($row = 1; $row <= $rowCount; $row++)
                    <tr>
                        <td class="sample-no">{{ $row }}</td>
                        <td>Aktivitas pemeriksaan layout {{ $row }}</td>
                        <td>Baris dummy untuk memastikan tabel sederhana tetap berada di area aman konten.</td>
                    </tr>
                @endfor
            </tbody>
        </table>
    </main>
</body>
</html>
