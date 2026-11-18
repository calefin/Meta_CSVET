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
        // 2. Students Profile
        Schema::create('students', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('institution');
            $table->string('academic_level');
            $table->string('research_topic');
            $table->timestamps();
        });

        // 3. Document Submissions
        Schema::create('document_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->onDelete('cascade');
            $table->string('title');
            $table->string('google_drive_file_id')->nullable();
            $table->integer('file_size_kb');
            $table->string('status'); 
            $table->boolean('student_abnt_statement')->default(false);
            $table->json('ai_abnt_feedback_json')->nullable(); // JSON nativo suportado no MySQL
            $table->text('ai_executive_summary')->nullable();
            $table->timestamp('abnt_verified_at')->nullable();
            $table->timestamps();
        });

        // 4. Clinical & Diagnostics Records
        Schema::create('clinical_records', function (Blueprint $table) {
            $table->id();
            $table->string('record_type'); 
            $table->string('legal_responsible_name'); // Nomenclatura obrigatória (não utilizar tutor)
            $table->json('animal_data'); // JSON nativo MySQL
            $table->text('clinical_history_or_macroscopy');
            $table->text('microscopic_interpretation')->nullable();
            $table->text('final_diagnosis');
            $table->string('google_drive_folder_id')->nullable();
            $table->string('status'); 
            $table->timestamps();
        });

        // 5. Polymorphic Tagging Tables
        Schema::create('tags', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('slug')->unique();
            $table->timestamps();
        });

        Schema::create('taggables', function (Blueprint $table) {
            $table->foreignId('tag_id')->constrained()->onDelete('cascade');
            $table->morphs('taggable'); 
        });

        // 6. CSVET Budgets
        Schema::create('budgets', function (Blueprint $table) {
            $table->id();
            $table->string('client_name');
            $table->string('client_email');
            $table->string('service_type');
            $table->decimal('amount', 10, 2);
            $table->string('status');
            $table->date('valid_until');
            $table->string('google_drive_file_id')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('budgets');
        Schema::dropIfExists('taggables');
        Schema::dropIfExists('tags');
        Schema::dropIfExists('clinical_records');
        Schema::dropIfExists('document_submissions');
        Schema::dropIfExists('students');
    }
};
