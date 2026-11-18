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
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
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
