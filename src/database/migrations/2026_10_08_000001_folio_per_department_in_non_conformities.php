<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // El folio deja de ser único global: lo único es depto + año + número
        Schema::table('non_conformities', function (Blueprint $table) {
            $table->dropUnique(['folio']);
            $table->dropUnique(['folio_year', 'folio_number']);

            $table->index('folio');
            $table->unique(['department_id', 'folio_year', 'folio_number'], 'nc_folio_department_unique');
        });

        // Consecutivo por departamento y año
        Schema::dropIfExists('nc_folio_sequences');
        Schema::create('nc_folio_sequences', function (Blueprint $table) {
            $table->foreignId('department_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('year');
            $table->unsignedInteger('last_number')->default(0);

            $table->primary(['department_id', 'year']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nc_folio_sequences');
        Schema::create('nc_folio_sequences', function (Blueprint $table) {
            $table->unsignedSmallInteger('year')->primary();
            $table->unsignedInteger('last_number')->default(0);
        });

        Schema::table('non_conformities', function (Blueprint $table) {
            $table->dropUnique('nc_folio_department_unique');
            $table->dropIndex(['folio']);

            $table->unique('folio');
            $table->unique(['folio_year', 'folio_number']);
        });
    }
};