<?php

return [
    'level-1' => [
        'badge' => 'Level I',
        'name' => 'Dokumen Level I : Manual SKMBS',
        'create_description' => 'Dokumen utama yang umumnya menjelaskan dan memberikan informasi tentang ruang lingkup Sistem Manajemen terintegrasi sesuai persyaratan ISO dan PP No. 50 tahun 2012, proses bisnis, diagram hubungan antar fungsi dan penjelasan fungsi.',
        'approval_description' => 'Manual sistem manajemen dan dokumen induk level perusahaan.',
        'default_stages' => ['Dibuat oleh', 'Diperiksa oleh', 'Disahkan oleh'],
    ],
    'level-2' => [
        'badge' => 'Level II',
        'name' => 'Dokumen Level II : Prosedur SKMBS',
        'create_description' => 'Dokumen yang menjabarkan proses didalam context diagram yang lebih jelas terhadap pemenuhan persyaratan Sistem Manajemen KBS yang terkait dengan fungsi-fungsi kegiatan bisnis Perusahaan dalam dokumen level I (Manual).',
        'approval_description' => 'Prosedur lintas fungsi yang menjadi turunan dokumen level I.',
        'default_stages' => ['Dibuat oleh', 'Diperiksa oleh', 'Disetujui oleh', 'Disahkan oleh'],
    ],
    'level-3' => [
        'badge' => 'Level III',
        'name' => 'Dokumen Level III : Instruksi Kerja',
        'create_description' => 'Dokumen yang menguraikan secara rinci tahapan teknis dalam pelaksanaan suatu kegiatan yang tertuang didalam dokumen level II dan tidak hanya garis besar saja namun menjelaskan instruksi pekerjaan dari awal sampai akhir.',
        'approval_description' => 'Instruksi kerja teknis untuk pelaksanaan kegiatan internal departemen maupun lintas department.',
        'default_stages' => ['Dibuat oleh', 'Diperiksa oleh', 'Disetujui oleh'],
    ],
    'level-4' => [
        'badge' => 'Level IV',
        'name' => 'Dokumen Level IV : Form / Lembar Revisi',
        'create_description' => 'Form atau lembar revisi yang dibuat dari dokumen master sebagai pengajuan perubahan dokumen.',
        'approval_description' => 'Form pengajuan revisi dokumen master sebelum perubahan disahkan.',
        'default_stages' => ['Dibuat oleh', 'Diperiksa oleh', 'Disetujui oleh'],
    ],
];
