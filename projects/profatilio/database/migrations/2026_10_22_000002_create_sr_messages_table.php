<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Conversa registrada de cada projeto (professor + dupla). Nada é apagado:
 * o histórico serve de registro das combinações do projeto.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sr_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('systematic_review_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('author_name', 255);
            $table->string('author_role', 20); // professor | aluno
            $table->text('body');
            $table->timestamps();
            $table->index(['systematic_review_id', 'created_at'], 'sr_messages_review_created_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sr_messages');
    }
};
