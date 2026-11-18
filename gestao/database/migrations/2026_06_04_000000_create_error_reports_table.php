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
        Schema::create('error_reports', function (Blueprint $table) {
            $table->id();
            // Polimórfico (professor Owner ou aluno AdviseeAccount, ver
            // user_type na migration 2026_08_24_100002) — sem FK de banco
            // porque pode apontar pra duas tabelas diferentes, com PKs de
            // tipos diferentes (int em users, bigint em advisee_accounts).
            $table->unsignedBigInteger('user_id');
            $table->string('category'); // e.g., 'Upload de Arquivos', 'Telegram', 'Design/Visual', 'Outro Erro'
            $table->text('description');
            $table->string('url')->nullable(); // Page where the error happened
            $table->string('screenshot_path')->nullable(); // Visual proof
            $table->string('status')->default('pending'); // pending, investigating, resolved, closed
            $table->text('admin_notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('error_reports');
    }
};
