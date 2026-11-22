<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('qr_auth_requests', function (Blueprint $table) {
            $table->string('method', 10)->default('google');
            $table->string('email_token_hash', 64)->nullable()->unique();
            $table->string('email_binding_hash', 64)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('qr_auth_requests', function (Blueprint $table) {
            $table->dropUnique(['email_token_hash']);
            $table->dropColumn(['method', 'email_token_hash', 'email_binding_hash']);
        });
    }
};
