<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('library_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained('library_categories')->cascadeOnDelete();
            $table->string('title', 200);
            $table->enum('language', ['pt-BR', 'en', 'yo'])->default('pt-BR');
            $table->string('file_path');
            $table->text('description')->nullable();
            $table->unsignedInteger('order')->default(0);
            $table->foreignId('uploaded_by')->constrained('users');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('library_documents');
    }
};
