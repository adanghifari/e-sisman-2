<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class BusinessProcessSeeder extends Seeder
{
    public function run(): void
    {
        $businessProcesses = [
            ['kode' => 'Utama', 'nama_proses_bisnis' => 'Proses Inti / Utama'],
            ['kode' => 'Manajemen', 'nama_proses_bisnis' => 'Manajemen Strategis'],
            ['kode' => 'Pendukung', 'nama_proses_bisnis' => 'Proses Penunjang'],
        ];

        foreach ($businessProcesses as $businessProcess) {
            DB::table('m_proses_bisnis')->updateOrInsert(
                ['kode' => $businessProcess['kode']],
                [
                    'nama_proses_bisnis' => $businessProcess['nama_proses_bisnis'],
                    'is_active' => true,
                ],
            );
        }

        DB::table('m_proses_bisnis')
            ->whereNotIn('kode', array_column($businessProcesses, 'kode'))
            ->delete();
    }
}
