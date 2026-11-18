<?php

namespace App\Support;

/**
 * Gabarito comentado do treino da triagem (selante de teto e mastite), por PMID.
 * Os alunos só veem depois que o treino fecha. "maybe" = caso de dúvida: não
 * conta acerto nem erro, é para discutir.
 */
class TrainingKey
{
    public const KEY = [
        // Incluir
        '12416803' => ['include', null, 'Selante vs. nenhum tratamento; desfecho de nova infecção e mastite.'],
        '17235153' => ['include', null, 'Antibiótico + selante vs. antibiótico sozinho: o comparador não tem selante.'],
        '20598329' => ['include', null, 'Antibiótico vs. selante; nova infecção e mastite clínica.'],
        '24351664' => ['include', null, 'Quartos com selante vs. quartos sem tratamento (úbere dividido).'],
        '42353384' => ['include', null, 'Selante vs. antibiótico em vacas de baixo risco, com saúde do úbere (prospectivo com coorte histórica).'],
        // Casos de dúvida
        '24580114' => ['maybe', 'E2?', 'Junta vacas (entram) e novilhas (E2). Na triagem, Talvez: vale ler o texto completo.'],
        '34316189' => ['maybe', 'E5?', 'O desfecho é cura de infecção já existente, não infecção nova.'],
        '35078119' => ['maybe', 'E4?', 'Os dois grupos recebem selante, então não há grupo sem selante.'],
        '36288172' => ['maybe', 'E5?', 'O resumo fala só de produção e CCS, mas o ensaio pode ter medido mastite.'],
        // Excluir
        '35379464' => ['exclude', 'E4', 'Compara dois selantes. Armadilha: o título fala de eficácia.'],
        '32278556' => ['exclude', 'E4', 'Dois selantes comerciais comparados entre si.'],
        '35633519' => ['exclude', 'E4', 'Antibiótico + selante vs. selante: os dois grupos têm selante.'],
        '40105284' => ['exclude', 'E4', 'Compara a técnica de inserção da cânula.'],
        '7440342' => ['exclude', 'E2', 'Vacas em lactação e película externa no teto (também E3).'],
        '6378997' => ['exclude', 'E3', 'Selante externo de látex.'],
        '34593233' => ['exclude', 'E2', 'Novilhas antes do primeiro parto.'],
        '17183089' => ['exclude', 'E2', 'Novilhas antes do parto.'],
        '32984415' => ['exclude', 'E5', 'Ensaio randomizado, mas o desfecho é a microbiota.'],
        '32475664' => ['exclude', 'E6', 'Bismuto in vitro.'],
        '40185671' => ['exclude', 'E1', 'Guia prático.'],
        '35931474' => ['exclude', 'E1', 'Revisão narrativa.'],
        '19528591' => ['exclude', 'E1', 'Metanálise.'],
        '32081124' => ['exclude', 'E1', 'Revisão sistemática com metanálise em rede.'],
        '30070621' => ['exclude', 'E1', 'Protocolo de revisão.'],
        '38908700' => ['exclude', 'E1', 'Modelo de simulação bioeconômico.'],
    ];

    public const LABELS = ['include' => 'Incluir', 'exclude' => 'Excluir', 'maybe' => 'Dúvida (discutir)'];

    /** @return array{decision: string, reason: ?string, why: string, label: string}|null */
    public static function for(?string $pmid): ?array
    {
        $k = self::KEY[trim((string) $pmid)] ?? null;

        return $k ? ['decision' => $k[0], 'reason' => $k[1], 'why' => $k[2], 'label' => self::LABELS[$k[0]]] : null;
    }

    /** Acerto pela regra da triagem: Talvez conta como "não excluir". Null = caso de dúvida (não pontua). */
    public static function isRight(array $key, string $decision): ?bool
    {
        if ($key['decision'] === 'maybe') {
            return null;
        }

        return ($key['decision'] === 'exclude') === ($decision === 'exclude');
    }

    /** "E4 — Sem grupo..." → "E4". */
    public static function reasonCode(?string $reason): ?string
    {
        return $reason && preg_match('/^\s*(E\d+)/u', $reason, $m) ? $m[1] : null;
    }
}
