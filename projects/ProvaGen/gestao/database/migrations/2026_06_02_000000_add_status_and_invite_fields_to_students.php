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
            $table->string('campus')->nullable()->after('institution');
            $table->string('status')->default('awaiting_email_validation')->after('terms_accepted_at'); // awaiting_email_validation, awaiting_advisor_approval, active
            $table->string('activation_token')->nullable()->after('status');
            $table->timestamp('activated_at')->nullable()->after('activation_token');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('advisees', function (Blueprint $table) {
            $table->dropColumn(['campus', 'status', 'activation_token', 'activated_at']);
        });
    }
};
