<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Inscrições aprovadas antes de o aluno criar a conta ficaram com user_id nulo
 * (a ficha mostrava "Sem conta" mesmo com conta e termo assinado). Liga pelo e-mail,
 * só a contas de aluno/admin com e-mail validado.
 */
return new class extends Migration
{
    public function up(): void
    {
        $users = DB::table('users')->whereIn('role', ['student', 'admin'])->whereNotNull('email_verified_at')
            ->get(['id', 'email'])->keyBy(fn ($u) => mb_strtolower(trim((string) $u->email)));

        DB::table('research_applications')->whereNull('user_id')->orderBy('id')->get(['id', 'email'])
            ->each(function ($app) use ($users) {
                $user = $users->get(mb_strtolower(trim((string) $app->email)));
                if ($user) {
                    DB::table('research_applications')->where('id', $app->id)->update(['user_id' => $user->id]);
                }
            });
    }

    public function down(): void
    {
        // Sem volta: o vínculo é a informação correta.
    }
};
