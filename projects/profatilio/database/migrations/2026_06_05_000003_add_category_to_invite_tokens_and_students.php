<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invite_tokens', function (Blueprint $table) {
            $table->string('category')->default('regular')->after('email');
        });

        Schema::table('students', function (Blueprint $table) {
            $table->string('category')->default('regular')->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('invite_tokens', function (Blueprint $table) {
            $table->dropColumn('category');
        });

        Schema::table('students', function (Blueprint $table) {
            $table->dropColumn('category');
        });
    }
};
