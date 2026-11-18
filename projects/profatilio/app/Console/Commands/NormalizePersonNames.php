<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class NormalizePersonNames extends Command
{
    protected $signature = 'app:normalizar-nomes {--aplicar : Grava as mudanças (sem isto, só lista o que mudaria)}';

    protected $description = 'Padroniza os nomes de pessoas (inscrições da seleção, triadores das revisões e usuários) como no cadastro: "JULIA ZAGOLIN COSTA" → "Julia Zagolin Costa". Não altera textos assinados (termo, adesão, assinatura). Idempotente.';

    /** Tabelas e coluna do nome. Textos assinados (terms_text, adhesion_text, terms_signature) ficam como foram assinados. */
    protected const TABLES = [
        'research_applications' => 'Inscrições da seleção',
        'systematic_review_screeners' => 'Triadores das revisões',
        'users' => 'Usuários',
    ];

    public function handle(): int
    {
        $apply = (bool) $this->option('aplicar');
        $total = 0;

        foreach (self::TABLES as $table => $label) {
            $rows = DB::table($table)->select(['id', 'name'])->orderBy('id')->get()
                ->map(fn ($r) => [$r->id, $r->name, User::normalizeName($r->name)])
                ->filter(fn ($r) => $r[1] !== null && $r[1] !== $r[2])
                ->values();

            $this->info("{$label} ({$table}): " . $rows->count() . ' para padronizar');
            if ($rows->isNotEmpty()) {
                $this->table(['id', 'atual', 'padronizado'], $rows->all());
            }
            if ($apply) {
                // Update direto: não mexe em updated_at nem dispara avisos.
                foreach ($rows as [$id, , $normalized]) {
                    DB::table($table)->where('id', $id)->update(['name' => $normalized]);
                }
            }
            $total += $rows->count();
        }

        $this->newLine();
        $this->line($apply ? "{$total} nome(s) padronizado(s)." : "{$total} nome(s) mudariam. Confira a lista e rode de novo com --aplicar.");

        return self::SUCCESS;
    }
}
