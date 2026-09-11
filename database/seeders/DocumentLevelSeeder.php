<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DocumentLevelSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $levels = [
            [
                'kode' => 'level-1',
                'nama_level' => 'Level I',
                'nama_dokumen' => 'Dokumen Level I : Manual SKMBS',
                'prefix' => 'SM',
                'description' => 'Manual sistem manajemen dan dokumen induk level perusahaan.',
                'sort_order' => 1,
            ],
            [
                'kode' => 'level-2',
                'nama_level' => 'Level II',
                'nama_dokumen' => 'Dokumen Level II : Prosedur SKMBS',
                'prefix' => 'PS',
                'description' => 'Prosedur lintas fungsi yang menjadi turunan dokumen level I.',
                'sort_order' => 2,
            ],
            [
                'kode' => 'level-3',
                'nama_level' => 'Level III',
                'nama_dokumen' => 'Dokumen Level III : Instruksi Kerja',
                'prefix' => 'IK',
                'description' => 'Instruksi kerja teknis untuk pelaksanaan kegiatan internal departemen maupun lintas department.',
                'sort_order' => 3,
            ],
            [
                'kode' => 'level-4',
                'nama_level' => 'Level IV',
                'nama_dokumen' => 'Dokumen Level IV : Form / Lembar Revisi',
                'prefix' => 'FM',
                'description' => 'Form pengajuan revisi dokumen master sebelum perubahan disahkan.',
                'sort_order' => 4,
            ],
        ];

        foreach ($levels as $level) {
            DB::table('m_document_levels')->updateOrInsert(
                ['kode' => $level['kode']],
                $level + ['is_active' => true],
            );
        }
    }
}
