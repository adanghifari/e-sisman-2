<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $statuses = [
            'PENDING' => 'Dalam Review',
            'WAITING' => 'Menunggu',
            'APPROVED' => 'Disetujui',
            'REJECTED' => 'Ditolak',
            'TERMINATED' => 'Dihentikan',
        ];

        foreach ($statuses as $kode_status => $nama_status) {
            DB::table('m_approval_status')
                ->where('kode_status', $kode_status)
                ->update(['nama_status' => $nama_status]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach (['PENDING', 'WAITING', 'APPROVED', 'REJECTED', 'TERMINATED'] as $kode_status) {
            DB::table('m_approval_status')
                ->where('kode_status', $kode_status)
                ->update(['nama_status' => $kode_status]);
        }
    }
};
