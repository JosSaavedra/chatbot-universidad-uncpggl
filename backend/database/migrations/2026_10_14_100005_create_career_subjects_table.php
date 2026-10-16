<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('career_subjects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('career_id')->constrained('careers')->cascadeOnDelete();
            $table->unsignedInteger('year')->comment('Año académico (1,2,3,4,5,6)');
            $table->string('period')->nullable()->comment('Ej.: I Semestre, II Semestre, Anual');
            $table->string('code')->nullable();
            $table->string('name');
            $table->unsignedInteger('credits')->default(0);
            $table->timestamps();
            $table->index(['career_id', 'year', 'period']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('career_subjects');
    }
};
