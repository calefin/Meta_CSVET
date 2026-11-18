<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // `users` NÃO é criada aqui: é a tabela compartilhada do ProvaGen (mesmo
        // banco, professor/dono da conta paga) — ver App\Models\Owner. Autenticar
        // como professor é sempre via SSO vindo do prof-aid, nunca senha local.
        //
        // password_reset_tokens/sessions ganham prefixo "gestao_" para nunca
        // colidir com uma tabela que o ProvaGen (Node/Drizzle) venha a ter no
        // futuro com o mesmo nome genérico, já que agora dividem o mesmo banco.
        Schema::create('gestao_password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('gestao_sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('gestao_password_reset_tokens');
        Schema::dropIfExists('gestao_sessions');
    }
};
