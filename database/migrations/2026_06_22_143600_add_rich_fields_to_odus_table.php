<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('odus', function (Blueprint $table) {
            $table->text('alternative_names')->nullable()->after('name');
            $table->text('summary')->nullable()->after('description');
            $table->text('ebos')->nullable()->after('summary');
            $table->text('orisas')->nullable()->after('ebos');
            $table->string('binary_mark', 50)->nullable()->after('orisas');
        });
    }

    public function down(): void
    {
        Schema::table('odus', function (Blueprint $table) {
            $table->dropColumn([
                'alternative_names',
                'summary',
                'ebos',
                'orisas',
                'binary_mark',
            ]);
        });
    }
};
