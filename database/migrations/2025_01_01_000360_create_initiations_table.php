<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Initiation record for an iniciado (not in the original spec data
     * dictionary - added per casa's operational need): the Odu received
     * at initiation, the date, and free-form notes. Visible to the
     * iniciado themselves (unlike admin_notes, which are deliberately
     * hidden from their target).
     */
    public function up(): void
    {
        Schema::create('initiations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('odu_id')->constrained('odus');
            $table->date('initiation_date');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('initiations');
    }
};
