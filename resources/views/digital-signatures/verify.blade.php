@php
    $respondedAt = $approval->responded_at;
    $publishedAt = $document->tanggal_terbit;
    $departments = $document->departments->pluck('nama_department')->filter()->implode(', ');

    $rows = [
        'Nama Penanda Tangan' => $approval->approver_name_snapshot ?: $approval->approver?->name,
        'Jabatan' => $approval->approver_position_snapshot ?: $approval->approver?->jabatan,
        'Departemen' => $approval->approver_department_snapshot ?: $approval->approver?->department?->nama_department,
        'Tahap Tanda Tangan' => $approval->stage_name_snapshot ?: $approval->stages,
        'Waktu Tanda Tangan' => $respondedAt?->translatedFormat('d F Y H:i:s'),
        'Status Approval' => $approval->status?->nama_status,
        'Nama Dokumen' => $document->nama_dokumen,
        'Nomor Dokumen' => $document->nomor_dokumen,
        'Jenis Dokumen' => $document->documentType?->nama_types,
        'Level Dokumen' => $document->documentLevel?->nama_level,
        'Revisi' => $document->formatted_revision,
        'Tanggal Terbit' => $publishedAt?->translatedFormat('d F Y'),
        'Proses Bisnis' => $document->businessProcess?->nama_proses_bisnis,
        'Fungsi Proses' => $document->businessFunction?->nama_proses_fungsi,
        'Departemen Terkait' => $departments ?: null,
    ];
@endphp

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Tanda Tangan Digital Terverifikasi</title>
    <style>
        :root {
            --blue: #0077b6;
            --cyan: #00a6d6;
            --green: #16a34a;
            --ink: #17212b;
            --muted: #607080;
            --line: #d8e3ea;
            --panel: #ffffff;
            --page: #f5f8fb;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            min-height: 100vh;
            background:
                linear-gradient(145deg, rgba(0, 166, 214, .12), transparent 34%),
                linear-gradient(315deg, rgba(22, 163, 74, .10), transparent 32%),
                var(--page);
            color: var(--ink);
            font-family: ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
        }

        .shell {
            width: min(100% - 32px, 860px);
            margin: 0 auto;
            padding: 44px 0;
        }

        .verification-panel {
            overflow: hidden;
            border: 1px solid var(--line);
            border-radius: 8px;
            background: var(--panel);
            box-shadow: 0 22px 60px rgba(23, 33, 43, .10);
        }

        .hero {
            display: grid;
            justify-items: center;
            gap: 14px;
            padding: 36px 24px 30px;
            text-align: center;
            background: linear-gradient(180deg, #ffffff, #eef9f3);
            border-bottom: 1px solid var(--line);
        }

        .checkmark {
            width: 84px;
            height: 84px;
            border-radius: 50%;
            display: grid;
            place-items: center;
            background: var(--green);
            box-shadow: 0 16px 38px rgba(22, 163, 74, .28);
            animation: pop .56s cubic-bezier(.2, 1.25, .35, 1) both;
        }

        .checkmark svg {
            width: 48px;
            height: 48px;
            stroke: white;
            stroke-width: 7;
            fill: none;
            stroke-linecap: round;
            stroke-linejoin: round;
        }

        .checkmark path {
            stroke-dasharray: 52;
            stroke-dashoffset: 52;
            animation: draw .55s ease .22s forwards;
        }

        h1 {
            margin: 0;
            font-size: clamp(1.8rem, 5vw, 2.65rem);
            line-height: 1.1;
            letter-spacing: 0;
        }

        .subtitle {
            max-width: 620px;
            margin: 0;
            color: var(--muted);
            font-size: 1rem;
            line-height: 1.55;
        }

        .content {
            padding: 26px;
        }

        .section-title {
            margin: 0 0 14px;
            font-size: 1.05rem;
            letter-spacing: 0;
        }

        .data-grid {
            display: grid;
            grid-template-columns: minmax(170px, 240px) 1fr;
            border: 1px solid var(--line);
            border-radius: 8px;
            overflow: hidden;
        }

        .label,
        .value {
            padding: 13px 16px;
            border-bottom: 1px solid var(--line);
            line-height: 1.45;
        }

        .label {
            background: #f7fafc;
            color: #415365;
            font-weight: 700;
        }

        .value {
            background: white;
            overflow-wrap: anywhere;
        }

        .label:last-of-type,
        .value:last-of-type {
            border-bottom: 0;
        }

        .footer-note {
            margin: 18px 0 0;
            color: var(--muted);
            font-size: .92rem;
            line-height: 1.55;
        }

        @keyframes pop {
            from {
                opacity: 0;
                transform: scale(.72);
            }
            to {
                opacity: 1;
                transform: scale(1);
            }
        }

        @keyframes draw {
            to {
                stroke-dashoffset: 0;
            }
        }

        @media (max-width: 640px) {
            .shell {
                width: min(100% - 20px, 860px);
                padding: 18px 0;
            }

            .hero {
                padding: 30px 18px 24px;
            }

            .content {
                padding: 18px;
            }

            .data-grid {
                grid-template-columns: 1fr;
            }

            .label {
                padding-bottom: 4px;
                border-bottom: 0;
            }

            .value {
                padding-top: 4px;
            }
        }
    </style>
</head>
<body>
    <main class="shell">
        <section class="verification-panel">
            <header class="hero">
                <div class="checkmark" aria-hidden="true">
                    <svg viewBox="0 0 64 64">
                        <path d="M18 33.5 27.2 43 47 22"></path>
                    </svg>
                </div>
                <h1>Tanda Tangan Digital Terverifikasi</h1>
                <p class="subtitle">
                    Data ini berasal dari catatan approval E-SISMAN dan telah dicocokkan dengan tautan verifikasi resmi.
                </p>
            </header>

            <div class="content">
                <h2 class="section-title">Bukti Tanda Tangan Sah</h2>
                <div class="data-grid">
                    @foreach ($rows as $label => $value)
                        <div class="label">{{ $label }}</div>
                        <div class="value">{{ filled($value) ? $value : '-' }}</div>
                    @endforeach
                </div>

                <p class="footer-note">
                    ID verifikasi: TTD-{{ str_pad((string) $approval->id, 8, '0', STR_PAD_LEFT) }}
                </p>
            </div>
        </section>
    </main>
</body>
</html>
