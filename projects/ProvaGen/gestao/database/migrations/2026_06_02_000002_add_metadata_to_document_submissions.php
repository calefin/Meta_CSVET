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
        Schema::table('document_submissions', function (Blueprint $table) {
            $table->string('process_id')->nullable()->unique()->after('student_id');
            $table->json('metadata')->nullable()->after('ai_abnt_feedback_json');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('document_submissions', function (Blueprint $table) {
            $table->dropColumn(['process_id', 'metadata']);
        });
    }
};
