<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Polimórfico (professor Owner ou aluno AdviseeAccount) — sem FK de
        // banco, ver error_reports. App novo, sem dado legado pra backfill.
        Schema::table('personal_activities', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id')->nullable()->after('id');
        });
    }

    public function down(): void
    {
        Schema::table('personal_activities', function (Blueprint $table) {
            $table->dropColumn('user_id');
        });
    }
};
