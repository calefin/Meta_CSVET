<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ewo (taboos/restrictions) tied to the initiated Orisa - a free-form
     * list since they vary per house/lineage and per Orisa.
     */
    public function up(): void
    {
        Schema::create('initiation_ewos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('initiation_id')->constrained('initiations')->cascadeOnDelete();
            $table->string('description', 255);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('initiation_ewos');
    }
};
