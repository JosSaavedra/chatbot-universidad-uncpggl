<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('career_sede')) {
            return;
        }

        Schema::create('career_sede', function (Blueprint $table) {
            $table->id();
            $table->foreignId('career_id')->constrained('careers')->cascadeOnDelete();
            $table->foreignId('sede_id')->constrained('sedes')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['career_id', 'sede_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('career_sede');
    }
};
