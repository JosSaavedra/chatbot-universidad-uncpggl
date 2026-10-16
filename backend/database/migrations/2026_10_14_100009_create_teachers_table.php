<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('teachers', function (Blueprint $table) {
            $table->id();
            $table->string('full_name');
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            // Área / facultad a la que pertenece el docente.
            $table->string('area')->nullable();
            // Tratamiento (Dr., Dra., Ing., Lic., …).
            $table->string('title')->nullable();
            // Los docentes de ejemplo se marcan como datos de demostración.
            $table->boolean('is_demo')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('teachers');
    }
};
