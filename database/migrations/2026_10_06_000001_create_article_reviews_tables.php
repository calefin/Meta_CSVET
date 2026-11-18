<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('article_reviews', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('short_name', 60);
            $table->string('journal')->nullable();
            $table->text('instructions')->nullable();
            $table->string('original_file_path');
            $table->string('original_filename')->nullable();
            $table->unsignedSmallInteger('default_review_days')->default(7);
            $table->string('status', 30)->default('draft');
            $table->string('final_file_path')->nullable();
            $table->string('final_filename')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('reviews_completed_at')->nullable();
            $table->date('submitted_at')->nullable();
            $table->text('notes')->nullable();
            $table->json('history')->nullable();
            $table->timestamps();
        });

        Schema::create('article_reviewers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('article_review_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('position')->default(0);
            $table->string('name');
            $table->string('email');
            $table->unsignedSmallInteger('review_days')->nullable();
            $table->string('status', 20)->default('waiting');
            // Só o hash SHA-256 do token vai para o banco; o link em claro
            // existe apenas no e-mail enviado ao revisor.
            $table->string('token_hash', 64)->nullable()->unique();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('due_at')->nullable();
            $table->timestamp('first_opened_at')->nullable();
            $table->timestamp('last_reminder_at')->nullable();
            $table->boolean('overdue_notified')->default(false);
            $table->timestamp('returned_at')->nullable();
            $table->unsignedSmallInteger('version_number')->nullable();
            $table->string('returned_file_path')->nullable();
            $table->string('returned_filename')->nullable();
            $table->text('comments')->nullable();
            $table->text('suggestions')->nullable();
            $table->timestamp('participation_accepted_at')->nullable();
            $table->text('participation_statement')->nullable();
            $table->json('forensic')->nullable();
            $table->timestamps();

            $table->index(['article_review_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('article_reviewers');
        Schema::dropIfExists('article_reviews');
    }
};
