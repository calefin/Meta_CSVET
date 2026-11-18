<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Private journal for the priest - consultations made for himself,
     * the house, his family, or external work for iniciados that he is
     * not necessarily sharing with them. Never exposed outside /admin.
     */
    public function up(): void
    {
        Schema::create('diary_entries', function (Blueprint $table) {
            $table->id();
            $table->date('entry_date');
            $table->enum('category', ['pessoal', 'casa', 'familia', 'iniciado_externo']);
            $table->foreignId('subject_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('odu_id')->nullable()->constrained('odus')->nullOnDelete();
            $table->string('title', 200);
            $table->text('content');
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('diary_entries');
    }
};
