<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('university_events');
        Schema::create('university_events', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description')->nullable();
            $table->dateTime('start_date');
            $table->dateTime('end_date')->nullable();
            $table->string('category');
            $table->unsignedBigInteger('career_id')->nullable();
            $table->string('campus')->nullable();
            $table->string('academic_period')->nullable();
            $table->string('location')->nullable();
            $table->string('organizer')->nullable();
            $table->enum('target_scope', ['all', 'specific'])->default('all');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('career_id')->references('id')->on('careers')->onDelete('set null');
            $table->index(['start_date', 'end_date', 'category']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('university_events');
        Schema::create('university_events', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('location')->nullable();
            $table->datetime('event_date');
            $table->string('organizer')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }
};