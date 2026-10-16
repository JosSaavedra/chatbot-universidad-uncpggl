<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->string('receipt_number')->unique();
            $table->string('concept')->comment('inscripcion, mensualidad, matricula, otros');
            $table->string('description')->nullable();
            $table->string('period')->nullable()->comment('Ej.: Enero 2026, I-2026');
            $table->decimal('amount', 10, 2)->default(0);
            $table->string('currency', 8)->default('NIO');
            $table->string('payment_method')->nullable()->comment('efectivo, transferencia, tarjeta, deposito');
            $table->string('status')->default('pendiente')->comment('pagado, pendiente, vencido');
            $table->date('due_date')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->string('reference')->nullable();
            $table->timestamps();

            $table->index(['student_id', 'status']);
            $table->index('concept');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
