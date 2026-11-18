<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('necropsy_class_attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('necropsy_class_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('necropsy_class_participant_id')->constrained()->cascadeOnDelete();
            $table->timestamp('checked_in_at')->nullable();
            $table->string('ip')->nullable();
            $table->string('user_agent')->nullable();
            $table->boolean('added_manually')->default(false);
            $table->timestamps();

            $table->unique(['necropsy_class_session_id', 'necropsy_class_participant_id'], 'necropsy_attendance_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('necropsy_class_attendances');
    }
};
