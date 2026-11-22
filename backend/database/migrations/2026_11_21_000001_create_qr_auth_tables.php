<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // La sesión temporal del teléfono necesita almacenamiento persistente.
        if (!Schema::hasTable('sessions')) {
            Schema::create('sessions', function (Blueprint $table) {
                $table->string('id')->primary();
                $table->foreignId('user_id')->nullable()->index();
                $table->string('ip_address', 45)->nullable();
                $table->text('user_agent')->nullable();
                $table->longText('payload');
                $table->integer('last_activity')->index();
            });
        }

        // Solicitudes efímeras del navegador. No contienen tokens Sanctum ni Google.
        Schema::create('qr_auth_requests', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('student_id');
            $table->string('public_id', 32)->unique();
            $table->string('qr_token_hash', 64)->unique();
            $table->string('client_hash', 64);
            $table->string('confirm_code', 6);
            $table->string('carnet', 40);
            $table->string('status', 24)->default('pending');
            $table->string('google_sub', 255)->nullable();
            $table->string('google_email_hash', 64)->nullable();
            $table->timestamp('expires_at');
            $table->timestamp('decided_at')->nullable();
            $table->timestamp('consumed_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'expires_at']);
        });

        // Preautorizaciones administradas: un Google verificado puede representar
        // dos carnés ficticios, pero nunca carnés de otra persona.
        Schema::create('google_preauthorizations', function (Blueprint $table) {
            $table->id();
            $table->string('email_hash', 64)->unique();
            $table->string('owner_label', 120)->nullable();
            $table->json('carnets');
            $table->string('google_sub', 255)->nullable()->unique();
            $table->timestamp('linked_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
        });

        // Evita el reuso del código de autorización de Google aunque el proveedor
        // externo tardara en invalidarlo.
        Schema::create('qr_oauth_codes', function (Blueprint $table) {
            $table->id();
            $table->string('code_hash', 64)->unique();
            $table->unsignedBigInteger('request_id');
            $table->timestamp('used_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        // No se ejecuta en despliegues de la demo: conservamos el histórico.
        Schema::dropIfExists('qr_oauth_codes');
        Schema::dropIfExists('google_preauthorizations');
        Schema::dropIfExists('qr_auth_requests');
    }
};
