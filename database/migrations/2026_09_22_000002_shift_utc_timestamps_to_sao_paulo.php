<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Colunas com horário de relógio digitado pelo usuário (DateTimePicker) —
     * já estão em horário de Brasília e não podem ser deslocadas.
     */
    protected array $skip = [
        'blog_posts.published_at',
        'invite_tokens.expires_at',
    ];

    /**
     * config('app.timezone') passou de UTC para America/Sao_Paulo. O Laravel grava
     * datas como texto no fuso da aplicação, então tudo o que foi gravado até
     * aqui (created_at, updated_at, sent_at...) está em UTC e passaria a ser lido
     * como se fosse horário de Brasília (3h adiantado). Converte uma única vez.
     * Brasil sem horário de verão desde 2019: deslocamento fixo de 3h.
     */
    public function up(): void
    {
        $this->shift('-');
    }

    public function down(): void
    {
        $this->shift('+');
    }

    protected function shift(string $sign): void
    {
        if (!in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            return;
        }

        // Só as tabelas do banco atual: sem o schema, o Laravel 12 lista as
        // tabelas de TODOS os bancos da conta, e a mesma tabela seria deslocada
        // várias vezes.
        foreach (Schema::getTableListing(DB::connection()->getDatabaseName(), false) as $table) {
            $columns = collect(Schema::getColumns($table))
                ->filter(fn ($c) => in_array(strtolower($c['type_name']), ['timestamp', 'datetime'], true))
                ->pluck('name')
                ->reject(fn ($c) => in_array("{$table}.{$c}", $this->skip, true))
                ->values();

            if ($columns->isEmpty()) {
                continue;
            }

            $sets = $columns
                ->map(fn ($c) => "`{$c}` = IF(`{$c}` IS NULL, NULL, `{$c}` {$sign} INTERVAL 3 HOUR)")
                ->implode(', ');

            DB::statement("UPDATE `{$table}` SET {$sets}");
        }
    }
};
