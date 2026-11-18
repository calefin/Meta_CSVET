<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Pós-doutorado do professor: dados do estágio, formalização, cronograma, documentos e a revisão do programa ligada a ele. */
class PostdocProject extends Model
{
    public const STATUS = [
        'preparacao' => 'Em preparação',
        'enviado' => 'Enviado ao PPG',
        'homologado' => 'Homologado',
        'em_andamento' => 'Em andamento',
        'concluido' => 'Concluído',
    ];

    protected $fillable = [
        'title', 'institution', 'program', 'research_line', 'supervisor', 'supervisor_email', 'modality',
        'starts_at', 'ends_at', 'status', 'protocol_code', 'registration_doi', 'checklist', 'timeline', 'documents', 'deliverables', 'notes',
    ];

    protected $casts = [
        'starts_at' => 'date',
        'ends_at' => 'date',
        'checklist' => 'array',
        'timeline' => 'array',
        'documents' => 'array',
        'deliverables' => 'array',
    ];

    /** Revisão sistemática do programa que é o objeto do pós-doc (P19). */
    public function review(): ?SystematicReview
    {
        return $this->protocol_code ? SystematicReview::where('protocol_code', $this->protocol_code)->where('is_training', false)->first() : null;
    }

    /** O registro único; criado com os dados do projeto aprovado (UNIP, 2026) na primeira abertura. */
    public static function current(): self
    {
        return static::query()->first() ?? static::create(static::defaults());
    }

    public static function defaults(): array
    {
        $check = fn (string $item) => ['item' => $item, 'done_at' => null, 'note' => null];
        $act = fn (string $activity, string $months) => ['activity' => $activity, 'months' => $months, 'status' => 'pendente'];

        return [
            'title' => 'Valor prognóstico da graduação histopatológica e contagem mitótica na sobrevida e recidiva do meningioma intracraniano canino: revisão sistemática e metanálise como modelo translacional em neuropatologia comparada',
            'institution' => 'Universidade Paulista (UNIP)',
            'program' => 'Programa de Pós-Graduação Stricto Sensu em Patologia Ambiental e Experimental',
            'research_line' => 'Patologia Integrada e Translacional',
            'supervisor' => 'Prof. Dr. Eduardo Fernandes Bondan',
            'modality' => 'Pós-doutorado voluntário (12 meses)',
            'status' => 'preparacao',
            'protocol_code' => 'P19',
            'registration_doi' => '10.17605/OSF.IO/PCGE7',
            'checklist' => [
                $check('Projeto revisado (v2 de 13/11/2026, alinhado ao protocolo registrado P19)'),
                $check('Projeto enviado ao Prof. Bondan para conferência'),
                $check('Carta de aceite do supervisor'),
                $check('Documentação enviada à secretaria do PPG (ficha, projeto, memorial, Lattes, diploma, declaração de propriedade intelectual)'),
                $check('Homologação no colegiado do PPG'),
                $check('Cadastro na Plataforma Sucupira (pós-doutorando)'),
                $check('E-mail institucional UNIP'),
                $check('Afiliação UNIP no ORCID e no Lattes'),
                $check('Relatório semestral de atividades'),
                $check('Workshop de metanálise para os pós-graduandos'),
                $check('Artigo submetido (com afiliação UNIP e DOI do registro OSF)'),
                $check('Relatório final'),
            ],
            'timeline' => [
                $act('Homologação no colegiado e cadastro na Sucupira', 'M1'),
                $act('Protocolo registrado no OSF (27/10/2026, DOI 10.17605/OSF.IO/PCGE7); desvios e emendas', 'M1'),
                $act('Buscas nas 4 bases (executadas em nov/2026) e atualização antes da análise', 'M1, M7'),
                $act('Conclusão da triagem cega de títulos e resumos', 'M1–M2'),
                $act('Leitura de textos integrais e seleção', 'M2–M3'),
                $act('Extração de dados e digitalização das curvas de Kaplan-Meier', 'M3–M5'),
                $act('Risco de viés (QUIPS) em dupla', 'M4–M5'),
                $act('Metanálise em R', 'M6–M7'),
                $act('Certeza da evidência (GRADE)', 'M7–M8'),
                $act('Workshop de metanálise para os pós-graduandos', 'M8–M9'),
                $act('Redação do manuscrito (PRISMA 2020)', 'M8–M10'),
                $act('Submissão e relatório final', 'M11–M12'),
            ],
            'deliverables' => [],
        ];
    }
}
