<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recommended_works', function (Blueprint $table) {
            $table->id();
            $table->foreignId('consultation_id')->constrained('consultations')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('ritual_work_id')->constrained('ritual_works');
            $table->text('notes')->nullable();
            $table->enum('status', [
                'recommended', 'budget_requested', 'approved', 'in_progress', 'completed', 'cancelled',
            ])->default('recommended');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recommended_works');
    }
};
