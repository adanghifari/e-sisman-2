<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('m_document_levels', function (Blueprint $table): void {
            $table->id();
            $table->string('kode', 50)->unique();
            $table->string('nama_level');
            $table->string('nama_dokumen');
            $table->string('prefix', 20)->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);

            $table->index(['is_active', 'sort_order']);
        });

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
            DB::table('m_document_levels')->insert($level + ['is_active' => true]);
        }

        Schema::table('m_approval_flows', function (Blueprint $table): void {
            $table->foreignId('m_document_level_id')
                ->nullable()
                ->after('id')
                ->constrained('m_document_levels')
                ->restrictOnDelete();
        });

        $levelIds = DB::table('m_document_levels')->pluck('id', 'kode');

        $documentTypeLevelMap = [
            'Manual' => 'level-1',
            'Prosedur' => 'level-2',
            'IK' => 'level-3',
        ];

        foreach ($documentTypeLevelMap as $documentTypeName => $levelKey) {
            DB::table('m_approval_flows')
                ->join('m_document_types', 'm_approval_flows.m_document_types_id', '=', 'm_document_types.id')
                ->where('m_document_types.nama_types', $documentTypeName)
                ->update(['m_approval_flows.m_document_level_id' => $levelIds[$levelKey] ?? null]);
        }

        $unmappedFlowIds = DB::table('m_approval_flows')
            ->whereNull('m_document_level_id')
            ->pluck('id');

        if ($unmappedFlowIds->isNotEmpty()) {
            DB::table('m_approval_flow_stages')
                ->whereIn('m_approval_flow_id', $unmappedFlowIds)
                ->delete();

            DB::table('m_approval_flows')
                ->whereIn('id', $unmappedFlowIds)
                ->delete();
        }

        Schema::table('m_approval_flows', function (Blueprint $table): void {
            $table->dropForeign(['m_document_types_id']);
            $table->dropUnique(['m_document_types_id']);
            $table->dropColumn('m_document_types_id');
            $table->unsignedBigInteger('m_document_level_id')->nullable(false)->change();
            $table->unique('m_document_level_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('m_approval_flows', function (Blueprint $table): void {
            $table->foreignId('m_document_types_id')
                ->nullable()
                ->after('id')
                ->constrained('m_document_types')
                ->restrictOnDelete();
        });

        $documentTypeIds = DB::table('m_document_types')->pluck('id', 'nama_types');

        $levelDocumentTypeMap = [
            'level-1' => 'Manual',
            'level-2' => 'Prosedur',
            'level-3' => 'IK',
        ];

        foreach ($levelDocumentTypeMap as $levelKey => $documentTypeName) {
            DB::table('m_approval_flows')
                ->join('m_document_levels', 'm_approval_flows.m_document_level_id', '=', 'm_document_levels.id')
                ->where('m_document_levels.kode', $levelKey)
                ->update(['m_approval_flows.m_document_types_id' => $documentTypeIds[$documentTypeName] ?? null]);
        }

        Schema::table('m_approval_flows', function (Blueprint $table): void {
            $table->dropForeign(['m_document_level_id']);
            $table->dropUnique(['m_document_level_id']);
            $table->dropColumn('m_document_level_id');
            $table->unique('m_document_types_id');
        });

        Schema::dropIfExists('m_document_levels');
    }
};
