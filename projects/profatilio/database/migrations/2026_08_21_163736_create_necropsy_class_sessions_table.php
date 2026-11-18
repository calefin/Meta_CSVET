<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('necropsy_class_sessions', function (Blueprint $table) {
            $table->id();
            $table->string('token')->unique();
            $table->date('class_date');
            $table->time('start_time')->default('13:00:00');
            $table->time('end_time')->default('17:00:00');
            $table->string('location')->default('Complexo Veterinário - UNICSUL');
            $table->text('animals')->nullable();
            $table->longText('diary')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('cancellation_reason')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('necropsy_class_sessions');
    }
};
