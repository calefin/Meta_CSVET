<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Termo de adesão aceito no envio da inscrição (texto exato + SHA-256 + data)
 * e confirmação do e-mail pelo link de acompanhamento.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('research_applications', function (Blueprint $table) {
            $table->longText('adhesion_text')->nullable()->after('declarations');
            $table->string('adhesion_sha256', 64)->nullable()->after('adhesion_text');
            $table->timestamp('adhesion_accepted_at')->nullable()->after('adhesion_sha256');
            $table->timestamp('email_verified_at')->nullable()->after('email');
        });
    }

    public function down(): void
    {
        Schema::table('research_applications', function (Blueprint $table) {
            $table->dropColumn(['adhesion_text', 'adhesion_sha256', 'adhesion_accepted_at', 'email_verified_at']);
        });
    }
};
