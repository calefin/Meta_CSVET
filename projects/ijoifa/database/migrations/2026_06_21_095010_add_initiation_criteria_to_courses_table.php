<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            $table->string('required_orisa_name', 100)->nullable()->after('price');
            $table->foreignId('required_odu_id')->nullable()->after('required_orisa_name')->constrained('odus')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            $table->dropConstrainedForeignId('required_odu_id');
            $table->dropColumn('required_orisa_name');
        });
    }
};
