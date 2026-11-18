<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admin_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('admin_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('target_user_id')->constrained('users')->cascadeOnDelete();
            $table->enum('category', ['espiritual', 'financeiro', 'saude', 'comportamento', 'outro']);
            $table->text('body');
            $table->timestamp('created_at')->nullable();
            $table->dateTime('locked_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_notes');
    }
};
