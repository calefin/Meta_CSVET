<?php

namespace Tests\Concerns;

use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;

/**
 * Os testes rodam em SQLite, que aceita "GROUP BY a ORDER BY b". O MySQL da
 * produção (ONLY_FULL_GROUP_BY, modo strict do Laravel) recusa a consulta
 * inteira, mesmo sem dados. Foi o que derrubou a página das revisões
 * sistemáticas em 23/10/2026 (relação records() ordenada por seq + GROUP BY
 * no PRISMA). Este guarda falha o teste quando uma consulta assim é executada.
 */
trait GuardsMysqlGroupBy
{
    protected array $groupByViolations = [];

    protected function setUpGuardsMysqlGroupBy(): void
    {
        $this->groupByViolations = [];
        DB::listen(function (QueryExecuted $query) {
            foreach (self::onlyFullGroupByViolations($query->sql) as $column) {
                $this->groupByViolations[] = "ORDER BY {$column} fora do GROUP BY em: {$query->sql}";
            }
        });
        $this->beforeApplicationDestroyed(function () {
            $this->assertSame([], array_values(array_unique($this->groupByViolations)),
                'Consulta que o MySQL (ONLY_FULL_GROUP_BY) recusa; use reorder() antes do groupBy().');
        });
    }

    /** Colunas do ORDER BY que não estão no GROUP BY nem são agregadas/aliases do SELECT. */
    public static function onlyFullGroupByViolations(string $sql): array
    {
        $sql = strtolower(preg_replace('/\s+/', ' ', str_replace(['"', '`'], '', $sql)));
        $groupPos = strrpos($sql, ' group by ');
        if ($groupPos === false) {
            return [];
        }
        $orderPos = strpos($sql, ' order by ', $groupPos);
        if ($orderPos === false) {
            return [];
        }

        $groupClause = substr($sql, $groupPos + 10, $orderPos - $groupPos - 10);
        $groupClause = preg_split('/ having /', $groupClause)[0];
        $orderClause = preg_split('/ limit | offset |\)/', substr($sql, $orderPos + 10))[0];
        $selectClause = preg_match('/^select (.*?) from /', substr($sql, 0, $groupPos), $m) ? $m[1] : '';
        preg_match_all('/ as (\w+)/', $selectClause, $aliases);

        $bare = fn (string $c) => preg_replace('/^.*\./', '', trim($c));
        $allowed = array_merge(array_map($bare, explode(',', $groupClause)), $aliases[1]);

        $violations = [];
        foreach (explode(',', $orderClause) as $term) {
            $column = trim(preg_replace('/ (asc|desc)$/', '', trim($term)));
            if ($column === '' || str_contains($column, '(') || in_array($bare($column), $allowed, true)) {
                continue;
            }
            $violations[] = $column;
        }

        return $violations;
    }
}
