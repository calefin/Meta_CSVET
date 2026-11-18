<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Login dos orientandos/alunos do módulo de gestão — sempre por
     * email+senha própria (decisão do projeto: só o professor entra via SSO
     * do prof-aid, o aluno não tem conta lá). Chamada "advisee_accounts" (e
     * não "users") porque a tabela `users` do banco compartilhado já
     * pertence ao ProvaGen (ver App\Models\Owner) — dois professores
     * diferentes podem ter orientandos com o mesmo e-mail, por isso a
     * unicidade de email é por owner_user_id, não global.
     *
     * Consolida aqui os campos que no profatilio original foram adicionados
     * em migrations avulsas depois da criação de "users" (telegram_chat_id,
     * telegram_link_token, profile_photo_path, metadata,
     * must_change_password), para não precisar recriar cada uma delas
     * apontando para uma tabela que muda de nome.
     */
    public function up(): void
    {
        Schema::create('advisee_accounts', function (Blueprint $table) {
            $table->id();
            // users.id do ProvaGen é INT assinado (Drizzle), não BIGINT
            // UNSIGNED — foreignId()/constrained() força bigint unsigned e
            // quebra a constraint por incompatibilidade de tipo, por isso a
            // coluna é definida manualmente aqui.
            $table->integer('owner_user_id');
            $table->foreign('owner_user_id')->references('id')->on('users');
            $table->string('name');
            $table->string('email');
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->boolean('must_change_password')->default(false);
            $table->string('role')->default('student'); // mantido para minimizar mudança no código PHP existente
            $table->rememberToken();
            $table->string('telegram_chat_id')->nullable();
            $table->string('telegram_link_token')->nullable()->unique();
            $table->string('profile_photo_path')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['owner_user_id', 'email']);
            $table->unique(['owner_user_id', 'telegram_chat_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('advisee_accounts');
    }
};
