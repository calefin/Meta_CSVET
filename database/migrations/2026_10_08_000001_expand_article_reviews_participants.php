<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Revisão de artigos/livros — fase 2: ficha do participante (filiação, ORCID,
 * CRediT, aceites), telefone, e-mail opcional, gestão da fila, conta opcional
 * do revisor, link de cadastro do processo, restauração de versões e
 * documentos complementares.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('article_reviews', function (Blueprint $table) {
            $table->string('work_type', 20)->default('article')->after('title');
            $table->boolean('require_login')->default(false)->after('default_review_days');
            $table->boolean('notify_all')->default(true)->after('require_login');
            $table->boolean('signup_enabled')->default(false)->after('notify_all');
            $table->string('signup_token_hash', 64)->nullable()->unique()->after('signup_enabled');
            $table->text('signup_token_encrypted')->nullable()->after('signup_token_hash');
            $table->json('version_restores')->nullable()->after('final_filename');
        });

        Schema::table('article_reviewers', function (Blueprint $table) {
            $table->string('email')->nullable()->change();
            $table->foreignId('user_id')->nullable()->after('article_review_id')->constrained()->nullOnDelete();
            $table->string('phone', 40)->nullable()->after('email');
            $table->unsignedSmallInteger('author_order')->nullable()->after('position');
            $table->text('token_encrypted')->nullable()->after('token_hash');

            // Ficha do participante
            $table->string('citation_name', 120)->nullable();
            $table->string('institution')->nullable();
            $table->string('department')->nullable();
            $table->string('city', 120)->nullable();
            $table->string('state', 120)->nullable();
            $table->string('country', 120)->nullable();
            $table->string('orcid', 19)->nullable();
            $table->boolean('no_orcid')->default(false);
            $table->json('contribution_roles')->nullable();
            $table->string('contribution_other')->nullable();
            $table->text('profile_notes')->nullable();
            $table->json('consent')->nullable();
            $table->timestamp('profile_confirmed_at')->nullable();

            $table->boolean('self_registered')->default(false);
            $table->timestamp('removed_at')->nullable();
            $table->string('removed_reason')->nullable();
        });

        Schema::create('article_review_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('article_review_id')->constrained()->cascadeOnDelete();
            $table->foreignId('article_reviewer_id')->nullable()->constrained()->nullOnDelete();
            $table->string('path');
            $table->string('filename');
            $table->string('description')->nullable();
            $table->unsignedInteger('size_kb')->default(0);
            $table->string('sha256', 64)->nullable();
            $table->string('uploaded_by')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('article_review_files');

        Schema::table('article_reviewers', function (Blueprint $table) {
            $table->dropConstrainedForeignId('user_id');
            $table->dropColumn([
                'phone', 'author_order', 'token_encrypted', 'citation_name', 'institution', 'department',
                'city', 'state', 'country', 'orcid', 'no_orcid', 'contribution_roles', 'contribution_other',
                'profile_notes', 'consent', 'profile_confirmed_at', 'self_registered', 'removed_at', 'removed_reason',
            ]);
        });

        Schema::table('article_reviews', function (Blueprint $table) {
            $table->dropUnique(['signup_token_hash']);
            $table->dropColumn([
                'work_type', 'require_login', 'notify_all', 'signup_enabled', 'signup_token_hash',
                'signup_token_encrypted', 'version_restores',
            ]);
        });
    }
};
