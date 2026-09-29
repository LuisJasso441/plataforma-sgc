<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('modules', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();          // identificador estable: 'no-conformidad'
            $table->string('name');                    // nombre visible: 'No Conformidad'
            $table->string('description')->nullable();
            $table->boolean('active')->default(true);
            $table->unsignedInteger('order')->default(0); // para ordenar en el menú
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('modules');
    }
};