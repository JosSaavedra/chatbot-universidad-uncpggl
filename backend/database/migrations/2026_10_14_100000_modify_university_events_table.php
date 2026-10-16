<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('university_events', function (Blueprint $table) {
            if (!Schema::hasColumn('university_events', 'start_date')) {
                $table->dateTime('start_date')->nullable()->after('description');
            }
            if (!Schema::hasColumn('university_events', 'end_date')) {
                $table->dateTime('end_date')->nullable()->after('start_date');
            }
            if (!Schema::hasColumn('university_events', 'category')) {
                $table->string('category')->nullable()->after('end_date');
            }
            if (!Schema::hasColumn('university_events', 'career_id')) {
                $table->unsignedBigInteger('career_id')->nullable()->after('category');
            }
            if (!Schema::hasColumn('university_events', 'campus')) {
                $table->string('campus')->nullable()->after('career_id');
            }
            if (!Schema::hasColumn('university_events', 'academic_period')) {
                $table->string('academic_period')->nullable()->after('campus');
            }
            if (!Schema::hasColumn('university_events', 'target_scope')) {
                $table->string('target_scope')->default('all')->after('academic_period');
            }
        });

        // Migra los registros existentes que usaban event_date hacia start_date.
        if (Schema::hasColumn('university_events', 'event_date')
            && Schema::hasColumn('university_events', 'start_date')) {
            \DB::table('university_events')
                ->whereNull('start_date')
                ->update(['start_date' => \DB::raw('event_date')]);

            Schema::table('university_events', function (Blueprint $table) {
                $table->dropColumn('event_date');
            });
        }

        // Clave foránea hacia careers (solo si no existe ya).
        try {
            Schema::table('university_events', function (Blueprint $table) {
                $table->foreign('career_id')->references('id')->on('careers')->onDelete('set null');
            });
        } catch (\Throwable $e) {
            // La clave foránea ya existe.
        }

        // Índice compuesto para acelerar las consultas por rango de fechas.
        try {
            Schema::table('university_events', function (Blueprint $table) {
                $table->index(['start_date', 'end_date', 'category'], 'ue_start_end_category_idx');
            });
        } catch (\Throwable $e) {
            // El índice ya existe.
        }
    }

    public function down(): void
    {
        Schema::table('university_events', function (Blueprint $table) {
            if (Schema::hasColumn('university_events', 'career_id')) {
                $table->dropForeign(['career_id']);
            }
            $table->dropColumn([
                'start_date',
                'end_date',
                'category',
                'career_id',
                'campus',
                'academic_period',
                'target_scope',
            ]);
        });
    }
};