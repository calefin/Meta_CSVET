<?php

namespace App\Support;

use App\Models\ArticleReview;
use App\Models\SystematicReview;

/**
 * Pipeline de submissão por tipo de artigo: diretriz de relato, limites de
 * referência e checklist (ética, declarações e arquivos). Limites seguem a
 * NVAS (journals.csvet.com.br/nvas) como referência — confira sempre as
 * normas da revista alvo.
 */
class ArticleTypes
{
    protected const EQUATOR = 'https://www.equator-network.org/?post_type=eq_guidelines&s=';

    /**
     * limits: [palavras do texto, palavras do resumo, referências, figuras+tabelas]
     */
    public const TYPES = [
        'original_experimental' => [
            'label' => 'Artigo original — estudo experimental com animais',
            'guideline' => 'ARRIVE 2.0', 'search' => 'ARRIVE',
            'abstract' => 'estruturado', 'limits' => [6000, 300, 60, 8],
            'items' => ['ceua', 'three_rs', 'owner_consent', 'sisbio', 'sisgen', 'mapa', 'stats'],
        ],
        'original_observational' => [
            'label' => 'Artigo original — estudo observacional (coorte, caso-controle, transversal, retrospectivo)',
            'guideline' => 'STROBE-Vet', 'search' => 'STROBE-Vet',
            'abstract' => 'estruturado', 'limits' => [6000, 300, 60, 8],
            'items' => ['ceua_or_waiver', 'owner_consent', 'cep', 'sisbio', 'sisgen', 'stats'],
        ],
        'original_rct' => [
            'label' => 'Artigo original — ensaio clínico randomizado',
            'guideline' => 'CONSORT 2010 (REFLECT para animais de produção)', 'search' => 'CONSORT',
            'abstract' => 'estruturado', 'limits' => [6000, 300, 60, 8],
            'items' => ['ceua', 'three_rs', 'owner_consent', 'randomization', 'mapa', 'production', 'stats'],
        ],
        'original_diagnostic' => [
            'label' => 'Artigo original — acurácia diagnóstica',
            'guideline' => 'STARD 2015', 'search' => 'STARD',
            'abstract' => 'estruturado', 'limits' => [6000, 300, 60, 8],
            'items' => ['ceua_or_waiver', 'owner_consent', 'reference_standard', 'stats'],
        ],
        'short_communication' => [
            'label' => 'Comunicação curta',
            'guideline' => 'diretriz do desenho do estudo (ARRIVE/STROBE-Vet/STARD)', 'search' => 'ARRIVE',
            'abstract' => 'não estruturado', 'limits' => [2500, 200, 25, 3],
            'items' => ['ceua_or_waiver', 'owner_consent', 'stats'],
        ],
        'case_report' => [
            'label' => 'Relato de caso',
            'guideline' => 'CARE', 'search' => 'CARE',
            'abstract' => 'não estruturado', 'limits' => [2500, 200, 25, 6],
            'items' => ['case_consent', 'case_timeline', 'case_diagnosis'],
        ],
        'case_series' => [
            'label' => 'Série de casos (3 ou mais)',
            'guideline' => 'CARE', 'search' => 'CARE',
            'abstract' => 'não estruturado', 'limits' => [3500, 250, 35, 6],
            'items' => ['case_consent', 'case_timeline', 'case_diagnosis'],
        ],
        'clinical_image' => [
            'label' => 'Imagem clínica',
            'guideline' => 'modelo da revista (imagem clínica)', 'search' => 'CARE',
            'abstract' => '1–2 frases', 'limits' => [500, 60, 5, 2],
            'items' => ['case_consent', 'image_versions', 'image_editing', 'image_message'],
        ],
        'systematic_review' => [
            'label' => 'Revisão sistemática / metanálise',
            'guideline' => 'PRISMA 2020 (+ PRISMA for Abstracts)', 'search' => 'PRISMA',
            'abstract' => 'estruturado (PRISMA for Abstracts)', 'limits' => [8000, 300, 120, 10],
            'items' => ['sr_registration', 'sr_databases', 'sr_search_date', 'sr_dual', 'sr_rob', 'sr_flow', 'sr_data'],
        ],
        'scoping_review' => [
            'label' => 'Revisão de escopo',
            'guideline' => 'PRISMA-ScR', 'search' => 'PRISMA-ScR',
            'abstract' => 'estruturado', 'limits' => [7000, 300, 150, 10],
            'items' => ['sr_registration', 'sr_databases', 'sr_search_date', 'sr_dual', 'sr_flow', 'sr_data'],
        ],
        'narrative_review' => [
            'label' => 'Revisão narrativa',
            'guideline' => 'SANRA (autoavaliação)', 'search' => 'SANRA',
            'abstract' => 'não estruturado', 'limits' => [6000, 250, 100, 6],
            'items' => ['sanra'],
        ],
        'mini_review' => [
            'label' => 'Mini-revisão',
            'guideline' => 'SANRA (autoavaliação)', 'search' => 'SANRA',
            'abstract' => 'não estruturado', 'limits' => [3000, 250, 50, 3],
            'items' => ['sanra'],
        ],
        'technical_note' => [
            'label' => 'Nota técnica / método',
            'guideline' => 'diretriz do desenho do estudo', 'search' => 'ARRIVE',
            'abstract' => 'não estruturado', 'limits' => [3500, 200, 30, 5],
            'items' => ['ceua_or_waiver', 'method_validation'],
        ],
        'data_article' => [
            'label' => 'Artigo de dados',
            'guideline' => 'FAIR + diretriz da coleta', 'search' => 'FAIR',
            'abstract' => 'não estruturado', 'limits' => [2500, 200, 20, 4],
            'items' => ['ceua_or_waiver', 'dataset_doi'],
        ],
        'perspective' => [
            'label' => 'Perspectiva / comentário',
            'guideline' => '—', 'search' => null,
            'abstract' => 'não estruturado', 'limits' => [2500, 150, 30, 2],
            'items' => [],
        ],
        'letter' => [
            'label' => 'Carta ao editor',
            'guideline' => '—', 'search' => null,
            'abstract' => 'sem resumo', 'limits' => [1000, 0, 10, 1],
            'items' => [],
        ],
    ];

    /** Itens que valem para todos os tipos. */
    public const COMMON_ITEMS = [
        'limits' => 'Dentro dos limites do tipo (palavras, resumo, referências, figuras + tabelas) da revista alvo',
        'guideline_checklist' => 'Checklist da diretriz de relato preenchido, com página/linha de cada item',
        'cover_letter' => 'Carta ao editor: escopo, novidade, tipo de artigo, originalidade, preprint e revisores sugeridos/excluídos',
        'title_page' => 'Página de título separada: título, título curto (≤ 60 caracteres), autores, filiações, ORCID, correspondente/garantidor, contagens',
        'anonymized' => 'Manuscrito anonimizado (sem nomes no texto, comentários ou propriedades do arquivo)',
        'graduate' => 'Ao menos um autor graduado como garantidor, com perfil verificável (Lattes, CRMV ou ORCID)',
        'orcid' => 'ORCID de todos os autores (obrigatório para o correspondente)',
        'credit' => 'Contribuições CRediT e critérios ICMJE de autoria (bloco de autores do sistema)',
        'conflicts' => 'Declaração de conflitos de interesse (ou de que não há)',
        'funding' => 'Financiamento e nº dos processos (CNPq, CAPES Código 001, FAPESP…)',
        'data_statement' => 'Data Availability Statement (em inglês) e dados depositados com DOI quando houver',
        'ai_statement' => 'Declaração de uso de IA generativa (ou "The authors did not use generative AI in the preparation of this manuscript.")',
        'language' => 'Manuscrito inteiro em um idioma; todo texto dentro das figuras em inglês',
        'figures' => 'Figuras: fonte sem serifa, barras de escala, resolução (300/600/1000 dpi), paleta para daltônicos, sem edição local',
        'references' => 'Referências no estilo da revista (Vancouver na NVAS), com DOI sempre que existir',
        'similarity' => 'Checagem de similaridade/plágio feita antes do envio',
    ];

    public const TYPE_ITEMS = [
        'ceua' => 'CEUA aprovada ANTES do estudo: comitê, nº do protocolo e data em Materiais e Métodos (Lei 11.794/2008, CONCEA)',
        'ceua_or_waiver' => 'CEUA (nº e data) ou dispensa formal do comitê descrita em Materiais e Métodos',
        'three_rs' => '3Rs, critérios de desfecho humanitário, anestesia, analgesia e eutanásia descritos',
        'owner_consent' => 'Consentimento por escrito do tutor/proprietário (animais com responsável legal)',
        'sisbio' => 'Autorização SISBIO/ICMBio (animais silvestres), se aplicável',
        'sisgen' => 'Cadastro SisGen (patrimônio genético/conhecimento tradicional), se aplicável',
        'mapa' => 'Autorização do MAPA para ensaio a campo de produto veterinário, se aplicável',
        'production' => 'Animais de produção: bem-estar, densidade, ambiente, abate e consentimento da propriedade (sem identificá-la)',
        'cep' => 'CEP/CAAE (Plataforma Brasil) e consentimento se houver questionário/entrevista com pessoas; dados anonimizados (LGPD)',
        'randomization' => 'Geração e ocultação da alocação, cegamento e fluxograma CONSORT dos animais',
        'reference_standard' => 'Padrão de referência, cegamento dos avaliadores e tabela 2×2 (sensibilidade/especificidade com IC 95%)',
        'stats' => 'Estatística: desenho, cálculo amostral, randomização, cegamento, software com versão, efeito com IC 95% e P exato',
        'case_consent' => 'Consentimento escrito se o material identificar animal/tutor; caso contrário anonimizado (sem nomes, prontuário, datas exatas ou local preciso)',
        'case_timeline' => 'Linha do tempo do caso (CARE) e desfecho/acompanhamento',
        'case_diagnosis' => 'Diagnóstico confirmado e justificativa do valor educativo/novidade',
        'image_versions' => 'Cada figura em duas versões (com e sem marcações); até 4 painéis',
        'image_editing' => 'Só ajustes de brilho/contraste/cor na imagem inteira (sem clonagem, retoque ou IA)',
        'image_message' => '"Mensagem para a prática", até 5 referências, até 4 autores, palavras-chave com espécie/sistema/modalidade',
        'sr_registration' => 'Protocolo registrado (OSF/PROSPERO/SYREAF) ANTES da extração; DOI citado no resumo e nos métodos',
        'sr_databases' => 'Pelo menos 2 bases bibliográficas + literatura cinzenta, com a sintaxe completa (PRISMA-S)',
        'sr_search_date' => 'Última busca com no máximo 12 meses na data de submissão',
        'sr_dual' => 'Triagem e extração em dupla independente, com a concordância relatada',
        'sr_rob' => 'Risco de viés com ferramenta validada (RoB 2, ROBINS-I, SYRCLE, QUADAS-2)',
        'sr_flow' => 'Fluxograma PRISMA com os números de cada etapa e motivos de exclusão do texto completo',
        'sr_data' => 'Planilha de extração, scripts e lista de excluídos como material suplementar ou com DOI',
        'sanra' => 'Autoavaliação SANRA anexada',
        'method_validation' => 'Validação do método (repetibilidade/reprodutibilidade) e comparação com o padrão atual',
        'dataset_doi' => 'Conjunto de dados depositado com DOI (FAIR), com dicionário de variáveis',
    ];

    public static function options(): array
    {
        return array_map(fn ($t) => $t['label'], self::TYPES);
    }

    public static function get(?string $type): ?array
    {
        return $type ? (self::TYPES[$type] ?? null) : null;
    }

    public static function guidelineUrl(?string $type): ?string
    {
        $search = self::get($type)['search'] ?? null;

        return $search ? self::EQUATOR . rawurlencode($search) : null;
    }

    /** Tipo de artigo sugerido para o tipo de revisão sistemática. */
    public static function forSystematicReview(SystematicReview $sr): string
    {
        return $sr->review_type === 'scoping' ? 'scoping_review' : 'systematic_review';
    }

    /** @return array<string, string> chave => rótulo */
    public static function checklist(?string $type): array
    {
        $def = self::get($type);
        if (!$def) {
            return [];
        }

        return self::COMMON_ITEMS + array_intersect_key(self::TYPE_ITEMS, array_flip($def['items']));
    }

    /**
     * Itens que o sistema comprova sozinho (projeto de revisão sistemática vinculado).
     *
     * @return array<string, string> chave => evidência
     */
    public static function autoEvidence(ArticleReview $review): array
    {
        $sr = $review->systematicReview;
        if (!$sr) {
            return [];
        }
        $done = [];
        if ($sr->registration_id) {
            $done['sr_registration'] = 'registro ' . $sr->registrationLine();
        }
        if (count($sr->search_counts['sources'] ?? []) >= 2 && $sr->checklistDone('grey_lit')) {
            $done['sr_databases'] = count($sr->search_counts['sources']) . ' bases + literatura cinzenta';
        }
        if ($sr->search_date && $sr->search_date->gte(now()->subMonths(12))) {
            $done['sr_search_date'] = 'última busca em ' . $sr->search_date->format('d/m/Y');
        }
        if ($sr->screeners()->whereNotNull('terms_accepted_at')->count() >= 2 && $sr->checklistDone('extraction')) {
            $done['sr_dual'] = 'triagem em dupla com κ registrado + extração marcada na esteira';
        }
        if ($sr->isStageClosed(SystematicReview::STAGE_TA)) {
            $done['sr_flow'] = 'números do PRISMA gerados pelo sistema';
        }
        if ($sr->checklistDone('rob')) {
            $done['sr_rob'] = 'marcado na esteira da revisão';
        }
        if ($sr->checklistDone('data_deposit')) {
            $done['sr_data'] = 'marcado na esteira da revisão';
        }

        return $done;
    }

    /**
     * Situação de cada item: done | na | auto | pending.
     *
     * @return array<string, array{label: string, state: string, evidence: ?string}>
     */
    public static function status(ArticleReview $review): array
    {
        $saved = $review->submission_checklist ?? [];
        $auto = self::autoEvidence($review);
        $out = [];
        foreach (self::checklist($review->article_type) as $key => $label) {
            $state = $saved[$key] ?? 'pending';
            if ($state === 'pending' && isset($auto[$key])) {
                $state = 'auto';
            }
            $out[$key] = ['label' => $label, 'state' => $state, 'evidence' => $auto[$key] ?? null];
        }

        return $out;
    }

    /** @return array<int, string> rótulos dos itens pendentes */
    public static function pending(ArticleReview $review): array
    {
        return collect(self::status($review))->where('state', 'pending')->pluck('label')->values()->all();
    }
}
