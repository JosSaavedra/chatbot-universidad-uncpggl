<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('schedules', function (Blueprint $table) {
            // Relación con el docente (se conserva teacher_name como respaldo).
            $table->foreignId('teacher_id')->nullable()->after('career_id')
                ->constrained('teachers')->nullOnDelete();
            // Grupo (p. ej. "A", "B").
            $table->string('group_code')->nullable()->after('year_of_study');
            // Sede / edificio y período académico.
            $table->foreignId('sede_id')->nullable()->after('year_of_study')
                ->constrained('sedes')->nullOnDelete();
            $table->string('building')->nullable()->after('classroom');
            $table->string('academic_period')->nullable()->after('building');
            // Marca los horarios simulados del prototipo (no oficiales).
            $table->boolean('is_demo')->default(false)->after('academic_period');

            $table->index(['career_id', 'year_of_study', 'group_code'], 'schedules_group_index');
        });
    }

    public function down(): void
    {
        Schema::table('schedules', function (Blueprint $table) {
            $table->dropIndex('schedules_group_index');
            $table->dropConstrainedForeignId('teacher_id');
            $table->dropConstrainedForeignId('sede_id');
            $table->dropColumn(['group_code', 'building', 'academic_period', 'is_demo']);
        });
    }
};
