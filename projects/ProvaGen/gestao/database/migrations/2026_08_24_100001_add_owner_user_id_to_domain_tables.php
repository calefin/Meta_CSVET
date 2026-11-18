<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tabelas de domínio deste app que passam a pertencer a um "owner" — o
     * professor dono da conta, que é uma linha da tabela `users` do ProvaGen
     * (mesmo banco, mesma linha usada para login/assinatura/plano lá).
     * `advisee_accounts` já nasce com owner_user_id na própria migration de
     * criação, por isso não está nesta lista. Fora também `taggables`
     * (pivot puro) e as tabelas de infraestrutura do Laravel (gestao_*).
     */
    private const OWNED_TABLES = [
        'advisees', 'document_submissions', 'clinical_records', 'tags',
        'budgets', 'consultation_requests', 'invite_tokens', 'academic_titles',
        'email_logs', 'login_logs', 'telegram_messages', 'error_reports', 'training_courses',
        'certificate_requests', 'necropsy_requests', 'student_semester_declarations',
        'course_modules', 'course_enrollments', 'course_module_completions',
        'course_module_questions', 'course_module_quiz_attempts', 'advisee_support_tickets',
        'support_ticket_messages', 'deadlines', 'notification_logs',
        'personal_activity_categories', 'personal_activities', 'class_groups',
        'class_diary_entries', 'necropsy_class_sessions', 'necropsy_class_participants',
        'necropsy_class_attendances', 'external_class_groups', 'external_class_participants',
        'external_document_submissions',
    ];

    public function up(): void
    {
        // users.id do ProvaGen é INT assinado (Drizzle), não BIGINT UNSIGNED
        // — foreignId()/constrained() força bigint unsigned e quebra a
        // constraint por incompatibilidade de tipo (visto na prática: MySQL
        // erro 3780 ao testar local), por isso a coluna é definida manualmente.
        foreach (self::OWNED_TABLES as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->integer('owner_user_id')->nullable()->after('id');
                $table->foreign('owner_user_id')->references('id')->on('users');
            });
        }

        // `name`/`slug` de tags eram únicos globalmente; dois professores
        // diferentes podem ter tags com o mesmo valor — a unicidade passa a
        // ser por owner.
        Schema::table('tags', function (Blueprint $table) {
            $table->dropUnique(['name']);
            $table->dropUnique(['slug']);
            $table->unique(['owner_user_id', 'name']);
            $table->unique(['owner_user_id', 'slug']);
        });

        Schema::table('personal_activity_categories', function (Blueprint $table) {
            $table->unique(['owner_user_id', 'name']);
        });

        // owner_user_id fica nullable no schema (sem NOT NULL — mudar a
        // nulidade de uma coluna existente exige doctrine/dbal, que não é
        // dependência deste projeto). A obrigatoriedade é garantida em
        // código pela trait BelongsToOwner, não pelo banco. Cada linha
        // precisa ser preenchida no momento da criação real (não há dado
        // legado a migrar aqui — profatilio.com.br continua com sua própria
        // base, intocada).
    }

    public function down(): void
    {
        Schema::table('personal_activity_categories', function (Blueprint $table) {
            $table->dropUnique(['owner_user_id', 'name']);
        });

        Schema::table('tags', function (Blueprint $table) {
            $table->dropUnique(['owner_user_id', 'name']);
            $table->dropUnique(['owner_user_id', 'slug']);
            $table->unique('name');
            $table->unique('slug');
        });

        foreach (self::OWNED_TABLES as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->dropForeign([$tableName . '_owner_user_id_foreign']);
                $table->dropColumn('owner_user_id');
            });
        }
    }
};
