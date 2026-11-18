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
            $table->text('google_drive_file_id')->nullable()->change();
            $table->text('google_drive_returned_file_id')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('document_submissions', function (Blueprint $table) {
            $table->string('google_drive_file_id', 255)->nullable()->change();
            $table->string('google_drive_returned_file_id', 255)->nullable()->change();
        });
    }
};
