<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tickets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cliente_id')->constrained('clientes');
            $table->string('titulo');
            $table->text('descricao');
            $table->enum('status', ['novo', 'analise', 'orcado', 'aceito', 'recusado', 'fechado'])->default('novo');
            $table->enum('prioridade', ['baixa', 'media', 'alta', 'urgente'])->default('media');
            $table->decimal('valor_orcado', 10, 2)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tickets');
    }
};