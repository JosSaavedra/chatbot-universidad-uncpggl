<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('careers', function (Blueprint $table) {
            $table->string('modalidad')->nullable()->after('duration_years');
            $table->string('titulo_otorga')->nullable()->after('description');
            $table->text('perfil_profesional')->nullable()->after('titulo_otorga');
            $table->text('campo_laboral')->nullable()->after('perfil_profesional');
            $table->text('requisitos_ingreso')->nullable()->after('campo_laboral');
            $table->string('pensum_url')->nullable()->after('requisitos_ingreso');
            $table->string('imagen_url')->nullable()->after('pensum_url');
        });
    }

    public function down(): void
    {
        Schema::table('careers', function (Blueprint $table) {
            $table->dropColumn([
                'modalidad',
                'titulo_otorga',
                'perfil_profesional',
                'campo_laboral',
                'requisitos_ingreso',
                'pensum_url',
                'imagen_url',
            ]);
        });
    }
};
