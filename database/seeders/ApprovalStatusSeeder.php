<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ApprovalStatusSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $statuses = [
            'PENDING' => 'Dalam Review',
            'WAITING' => 'Menunggu',
            'APPROVED' => 'Disetujui',
            'REJECTED' => 'Ditolak',
            'TERMINATED' => 'Dihentikan',
        ];

        foreach ($statuses as $kode_status => $nama_status) {
            DB::table('m_approval_status')->updateOrInsert(
                ['kode_status' => $kode_status],
                ['nama_status' => $nama_status],
            );
        }
    }
}
