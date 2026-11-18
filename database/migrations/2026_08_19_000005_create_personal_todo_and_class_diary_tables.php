<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('personal_activity_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('color', 7)->default('#0d9488');
            $table->timestamps();
        });

        Schema::create('personal_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->nullable()->constrained('personal_activity_categories')->nullOnDelete();
            $table->string('title');
            $table->text('notes')->nullable();
            $table->string('status')->default('pending'); // pending, in_progress, done
            $table->date('date');
            $table->timestamp('started_at')->nullable();
            $table->unsignedInteger('total_seconds')->default(0);
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('class_groups', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('semester');
            $table->string('shift')->nullable();
            $table->boolean('active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('class_diary_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('class_group_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->text('content');
            $table->text('progress_notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('class_diary_entries');
        Schema::dropIfExists('class_groups');
        Schema::dropIfExists('personal_activities');
        Schema::dropIfExists('personal_activity_categories');
    }
};
