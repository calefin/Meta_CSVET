<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('consultation_life_areas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('consultation_id')->constrained('consultations')->cascadeOnDelete();
            $table->enum('area', ['espiritual', 'trabalho', 'relacionamento', 'saude', 'dinheiro']);
            $table->foreignId('odu_1_id')->constrained('odus');
            $table->foreignId('odu_2_id')->nullable()->constrained('odus');
            $table->foreignId('odu_3_id')->nullable()->constrained('odus');
            $table->text('notes')->nullable();
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('consultation_life_areas');
    }
};
