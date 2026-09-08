<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('grades', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('career_id')->constrained()->onDelete('cascade');
            $table->string('subject_name');
            $table->decimal('first_partial', 4, 2)->nullable();
            $table->decimal('second_partial', 4, 2)->nullable();
            $table->decimal('final_exam', 4, 2)->nullable();
            $table->decimal('final_grade', 4, 2)->nullable();
            $table->string('status')->default('pending');
            $table->string('period');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('grades');
    }
};
