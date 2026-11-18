<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Subjects/topics in the restricted reference library (sec. "aba de
     * consulta" - not in the original spec). Access is granted per user
     * per category (see library_category_access), not automatically to
     * all iniciados - different teachings are revealed at different
     * stages of formation.
     */
    public function up(): void
    {
        Schema::create('library_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->string('slug', 170)->unique();
            $table->text('description')->nullable();
            $table->unsignedInteger('order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('library_categories');
    }
};
