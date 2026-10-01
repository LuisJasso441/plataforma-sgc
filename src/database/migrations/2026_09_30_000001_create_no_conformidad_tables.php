<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Catálogo PROCESOS
        Schema::create('nc_processes', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150)->unique();
            $table->boolean('active')->default(true);
            $table->unsignedSmallInteger('order')->default(0);
            $table->timestamps();
        });

        // Catálogo SUBPROCESOS (independiente de PROCESOS)
        Schema::create('nc_subprocesses', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150)->unique();
            $table->boolean('active')->default(true);
            $table->unsignedSmallInteger('order')->default(0);
            $table->timestamps();
        });

        // Consecutivo de folios por año (evita duplicados con bloqueo de fila)
        Schema::create('nc_folio_sequences', function (Blueprint $table) {
            $table->unsignedSmallInteger('year')->primary();
            $table->unsignedInteger('last_number')->default(0);
        });

        // No Conformidades (cada fila = un renglón de la bitácora)
        Schema::create('non_conformities', function (Blueprint $table) {
            $table->id();

            // Folio AC-YY-NN
            $table->string('folio', 20)->unique();
            $table->unsignedSmallInteger('folio_year');
            $table->unsignedInteger('folio_number');
            $table->unique(['folio_year', 'folio_number']);

            // NC de origen cuando ésta nace de una "No efectiva"
            $table->foreignId('parent_id')->nullable()
                ->constrained('non_conformities')->nullOnDelete();

            // 1. Creación de acción
            $table->foreignId('issued_by')->constrained('users')->restrictOnDelete();          // Quién emite
            $table->foreignId('department_id')->constrained('departments')->restrictOnDelete(); // Departamento responsable
            $table->foreignId('nc_process_id')->constrained('nc_processes')->restrictOnDelete(); // Proceso donde se genera
            $table->foreignId('nc_subprocess_id')->nullable()
                ->constrained('nc_subprocesses')->nullOnDelete();                              // Sub-proceso (opcional)
            $table->foreignId('leader_id')->nullable()
                ->constrained('users')->nullOnDelete();                                        // Líder de solución
            $table->string('origin', 40);                                                      // Origen de acción

            // 2. Información de la acción
            $table->text('initial_description');       // La que escribe el emisor
            $table->text('description')->nullable();   // La actualizada por el líder tras la reunión

            // Flujo interno y estatus de bitácora
            $table->string('stage', 30)->index();
            $table->string('status', 20)->index();

            // 3. Seguimiento de fechas
            $table->date('trigger_date')->nullable();       // Fecha de disparo (reunión)
            $table->date('commitment_date')->nullable();    // Implementación = última fecha compromiso
            $table->date('verification_date')->nullable();  // commitment_date + 1 mes
            $table->date('actual_close_date')->nullable();  // Última fecha real validada

            // 4 y 5. Cierre y lecciones aprendidas
            $table->boolean('is_effective')->nullable();
            $table->text('observations')->nullable();
            $table->text('lessons_learned')->nullable();

            // Trazabilidad de decisiones de Calidad
            $table->foreignId('accepted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('accepted_at')->nullable();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('closed_at')->nullable();

            $table->timestamps();
        });

        // Acciones definitivas (Paso 7 del formato)
        Schema::create('nc_actions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('non_conformity_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('number');
            $table->text('activity');
            $table->string('responsible', 150);
            $table->date('commitment_date');
            $table->date('actual_end_date')->nullable(); // Se llena cuando Calidad valida
            $table->string('status', 20)->default('pendiente')->index();
            $table->text('review_comment')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->unique(['non_conformity_id', 'number']);
        });

        // Adjuntos: reporte Excel (en la NC) y evidencias (en cada acción)
        Schema::create('nc_attachments', function (Blueprint $table) {
            $table->id();
            $table->morphs('attachable');
            $table->string('type', 20)->index(); // reporte | evidencia | solicitud
            $table->string('original_name');
            $table->string('path');
            $table->string('disk', 30)->default('local');
            $table->string('mime_type', 150)->nullable();
            $table->unsignedBigInteger('size')->default(0);
            $table->foreignId('uploaded_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });

        // Procesos a los que afecta (varios)
        Schema::create('nc_affected_process', function (Blueprint $table) {
            $table->foreignId('non_conformity_id')->constrained()->cascadeOnDelete();
            $table->foreignId('nc_process_id')->constrained('nc_processes')->cascadeOnDelete();
            $table->primary(['non_conformity_id', 'nc_process_id']);
        });

        // Línea de tiempo por NC
        Schema::create('nc_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('non_conformity_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete(); // null = sistema
            $table->string('event', 50);
            $table->string('from_stage', 30)->nullable();
            $table->string('to_stage', 30)->nullable();
            $table->text('comment')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['non_conformity_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nc_logs');
        Schema::dropIfExists('nc_affected_process');
        Schema::dropIfExists('nc_attachments');
        Schema::dropIfExists('nc_actions');
        Schema::dropIfExists('non_conformities');
        Schema::dropIfExists('nc_folio_sequences');
        Schema::dropIfExists('nc_subprocesses');
        Schema::dropIfExists('nc_processes');
    }
};