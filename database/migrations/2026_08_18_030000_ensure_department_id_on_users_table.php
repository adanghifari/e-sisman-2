<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('users', 'm_department_id')) {
            return;
        }

        Schema::table('users', function (Blueprint $table): void {
            $table->foreignId('m_department_id')
                ->nullable()
                ->after('id')
                ->constrained('departments')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        //
    }
};
