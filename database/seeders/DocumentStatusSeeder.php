<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DocumentStatusSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        foreach (['DRAFT', 'PROPOSED', 'APPROVED', 'REJECTED', 'CANCELLED', 'OBSOLETE'] as $nama_status) {
            DB::table('m_status_document')->updateOrInsert(
                ['nama_status' => $nama_status],
                ['nama_status' => $nama_status],
            );
        }
    }
}
