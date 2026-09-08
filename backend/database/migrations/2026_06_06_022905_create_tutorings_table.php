<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('tutorings');
        Schema::create('tutorings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('subject_name');
            $table->string('tutor_name');
            $table->dateTime('scheduled_at');
            $table->string('status')->default('scheduled');
            $table->string('meeting_link_or_place');
            $table->decimal('cost', 8, 2)->default(10.00);
            $table->decimal('exam_cost', 8, 2)->default(10.00);
            $table->boolean('tutoring_paid')->default(false);
            $table->boolean('exam_paid')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tutorings');
    }
};