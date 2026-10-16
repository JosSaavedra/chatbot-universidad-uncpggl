<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admission_items', function (Blueprint $table) {
            $table->id();
            // requisitos | documentos | pasos
            $table->string('section');
            $table->text('content');
            $table->unsignedInteger('sort_order')->default(0);
            // Marca los datos simulados del prototipo (no oficiales).
            $table->boolean('is_demo')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admission_items');
    }
};
