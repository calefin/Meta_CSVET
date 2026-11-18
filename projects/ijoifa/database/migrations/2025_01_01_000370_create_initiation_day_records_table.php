<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Odu recorded for each day of the initiation ritual (ebo and the
     * following days), one row per day.
     */
    public function up(): void
    {
        Schema::create('initiation_day_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('initiation_id')->constrained('initiations')->cascadeOnDelete();
            $table->string('day_label', 100);
            $table->foreignId('odu_id')->constrained('odus');
            $table->text('notes')->nullable();
            $table->unsignedInteger('order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('initiation_day_records');
    }
};
