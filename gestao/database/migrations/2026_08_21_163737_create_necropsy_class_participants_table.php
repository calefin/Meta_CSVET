<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('necropsy_class_participants', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('rgm')->nullable();
            $table->string('institution')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->foreignId('student_id')->nullable()->constrained('advisees')->nullOnDelete();
            $table->timestamps();

            $table->index('rgm');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('necropsy_class_participants');
    }
};
