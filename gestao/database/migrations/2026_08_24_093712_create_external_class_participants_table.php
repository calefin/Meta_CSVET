<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('external_class_participants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('external_class_group_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('rgm');
            $table->string('email');
            $table->timestamps();

            $table->unique(['external_class_group_id', 'rgm']);
            $table->index('email');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('external_class_participants');
    }
};
