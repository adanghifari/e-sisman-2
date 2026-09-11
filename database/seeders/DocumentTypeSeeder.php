<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DocumentTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        foreach (['Prosedur', 'IK', 'Form', 'Manual', 'Pedoman', 'Revisi'] as $nama_types) {
            DB::table('m_document_types')->updateOrInsert(
                ['nama_types' => $nama_types],
                ['nama_types' => $nama_types],
            );
        }
    }
}
