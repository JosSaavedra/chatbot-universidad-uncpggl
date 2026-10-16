<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table) {
            // Grupo del estudiante (p. ej. "A", "B"). Se usa para ubicar su horario.
            $table->string('group_code')->nullable()->after('year_of_study');
        });
    }

    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->dropColumn('group_code');
        });
    }
};
