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
        Schema::table('advisees', function (Blueprint $table) {
            $table->string('phone')->nullable()->after('research_topic');
            $table->string('registration')->nullable()->after('phone'); // Matrícula ou Registro Acadêmico
            $table->boolean('terms_accepted')->default(false)->after('registration'); // Aceite de Termos de Livre Vontade e LGPD
            $table->timestamp('terms_accepted_at')->nullable()->after('terms_accepted'); // Data e hora do aceite
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('advisees', function (Blueprint $table) {
            $table->dropColumn(['phone', 'registration', 'terms_accepted', 'terms_accepted_at']);
        });
    }
};
