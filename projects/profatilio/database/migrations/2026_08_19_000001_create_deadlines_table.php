<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('deadlines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('academic_title_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->date('due_date');
            $table->unsignedSmallInteger('recurrence_days')->nullable();
            $table->string('status')->default('pending'); // pending, fulfilled, cancelled
            $table->timestamp('last_charged_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('deadlines');
    }
};
