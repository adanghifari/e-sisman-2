<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class BusinessFunctionSeeder extends Seeder
{
    public function run(): void
    {
        $businessFunctions = [
            ['kode' => 'BIT', 'nama_proses_fungsi' => 'Perencanaan Bisnis, Teknik & Teknologi Informasi'],
            ['kode' => 'SMR', 'nama_proses_fungsi' => 'Sistem Manajemen & Resiko'],
            ['kode' => 'MRI', 'nama_proses_fungsi' => 'Pengukuran, Peninjauan & Inovasi'],
            ['kode' => 'PMS', 'nama_proses_fungsi' => 'Pemasaran'],
            ['kode' => 'OPS', 'nama_proses_fungsi' => 'Kepelabuhanan & Marine Services'],
            ['kode' => 'PGD', 'nama_proses_fungsi' => 'Pengadaan'],
            ['kode' => 'HCM', 'nama_proses_fungsi' => 'Pengelolaan SDM'],
            ['kode' => 'KEU', 'nama_proses_fungsi' => 'Pengendalian Keuangan'],
            ['kode' => 'PIN', 'nama_proses_fungsi' => 'Pengelolaan Infrastruktur'],
            ['kode' => 'KLK', 'nama_proses_fungsi' => 'Pengelolaan K3LH & Keamanan'],
            ['kode' => 'HMK', 'nama_proses_fungsi' => 'Humas & Korporasi'],
        ];

        foreach ($businessFunctions as $businessFunction) {
            DB::table('m_proses_fungsi')->updateOrInsert(
                ['kode' => $businessFunction['kode']],
                [
                    'nama_proses_fungsi' => $businessFunction['nama_proses_fungsi'],
                    'is_active' => true,
                ],
            );
        }

        DB::table('m_proses_fungsi')
            ->whereNotIn('kode', array_column($businessFunctions, 'kode'))
            ->delete();
    }
}
