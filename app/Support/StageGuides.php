<?php

namespace App\Support;

use App\Models\SystematicReview;
use App\Services\SystematicReviewService;

/**
 * Textos de cada etapa da revisão sistemática: para o aluno (o que vai fazer,
 * por quê, o que fazer e não fazer para reduzir viés, materiais de estudo) e
 * para o professor (o que fazer agora, portão de qualidade, o que conferir).
 * Referências metodológicas indexadas, com DOI conferido.
 */
class StageGuides
{
    /** Referências de estudo (DOIs conferidos em 15/10/2026). */
    public const REFS = [
        'prisma2020' => ['Page MJ et al. The PRISMA 2020 statement: an updated guideline for reporting systematic reviews. BMJ. 2021;372:n71.', 'https://doi.org/10.1136/bmj.n71'],
        'prismap' => ['Shamseer L et al. PRISMA-P 2015: elaboration and explanation. BMJ. 2015;350:g7647.', 'https://doi.org/10.1136/bmj.g7647'],
        'prismas' => ['Rethlefsen ML et al. PRISMA-S: an extension to the PRISMA statement for reporting literature searches. Syst Rev. 2021;10:39.', 'https://doi.org/10.1186/s13643-020-01542-z'],
        'osf_form' => ['van den Akker OR et al. Increasing the transparency of systematic reviews: presenting a generalized registration form. Syst Rev. 2023;12:170.', 'https://doi.org/10.1186/s13643-023-02281-7'],
        'cochrane' => ['Higgins JPT et al. (eds). Cochrane Handbook for Systematic Reviews of Interventions (cap. 4: busca e seleção; cap. 5: extração; cap. 8: risco de viés; cap. 14: GRADE).', 'https://training.cochrane.org/handbook'],
        'kappa' => ['McHugh ML. Interrater reliability: the kappa statistic. Biochem Med (Zagreb). 2012;22(3):276-82.', 'https://doi.org/10.11613/BM.2012.031'],
        'polanin' => ['Polanin JR et al. Best practice guidelines for abstract screening large-evidence systematic reviews and meta-analyses. Res Synth Methods. 2019;10(3):330-42.', 'https://doi.org/10.1002/jrsm.1354'],
        'double' => ['Waffenschmidt S et al. Single screening versus conventional double screening for study selection in systematic reviews: a methodological systematic review. BMC Med Res Methodol. 2019;19:132.', 'https://doi.org/10.1186/s12874-019-0782-0'],
        'grade' => ['Guyatt GH et al. GRADE: an emerging consensus on rating quality of evidence and strength of recommendations. BMJ. 2008;336:924-6.', 'https://doi.org/10.1136/bmj.39489.470347.AD'],
        // Ferramentas de risco de viés (códigos do pipeline JSON).
        'SYRCLE' => ['Hooijmans CR et al. SYRCLE\'s risk of bias tool for animal studies. BMC Med Res Methodol. 2014;14:43.', 'https://doi.org/10.1186/1471-2288-14-43'],
        'RoB2' => ['Sterne JAC et al. RoB 2: a revised tool for assessing risk of bias in randomised trials. BMJ. 2019;366:l4898.', 'https://doi.org/10.1136/bmj.l4898'],
        'ROBINS-I' => ['Sterne JA et al. ROBINS-I: a tool for assessing risk of bias in non-randomised studies of interventions. BMJ. 2016;355:i4919.', 'https://doi.org/10.1136/bmj.i4919'],
        'QUADAS-2' => ['Whiting PF et al. QUADAS-2: a revised tool for the quality assessment of diagnostic accuracy studies. Ann Intern Med. 2011;155(8):529-36.', 'https://doi.org/10.7326/0003-4819-155-8-201110180-00009'],
        'QUIPS' => ['Hayden JA et al. Assessing bias in studies of prognostic factors. Ann Intern Med. 2013;158(4):280-6.', 'https://doi.org/10.7326/0003-4819-158-4-201302190-00009'],
        'JBI-prevalence' => ['Munn Z et al. The development of a critical appraisal tool for use in systematic reviews addressing questions of prevalence. Int J Health Policy Manag. 2014;3(3):123-8.', 'https://doi.org/10.15171/ijhpm.2014.71'],
        'JBI-case-series' => ['Munn Z et al. Methodological quality of case series studies: an introduction to the JBI critical appraisal tool. JBI Evid Synth. 2020;18(10):2127-33.', 'https://doi.org/10.11124/JBISRIR-D-19-00099'],
        'JBI-analytical-cross-sectional' => ['JBI Critical Appraisal Tools: checklist for analytical cross sectional studies.', 'https://jbi.global/critical-appraisal-tools'],
        'NOS' => ['Wells GA et al. The Newcastle-Ottawa Scale (NOS) for assessing the quality of nonrandomised studies in meta-analyses.', 'https://www.ohri.ca/programs/clinical_epidemiology/oxford.asp'],
        'prevalence_guide' => ['Munn Z et al. Methodological guidance for systematic reviews of observational epidemiological studies reporting prevalence and cumulative incidence data. Int J Evid Based Healthc. 2015;13(3):147-53.', 'https://doi.org/10.1097/XEB.0000000000000054'],
    ];

    /**
     * Guia do aluno para a etapa. $key: terms | pilot | title_abstract | full_text | waiting.
     *
     * @return array{title: string, what: array, why: string, do: array, dont: array, refs: array}
     */
    public static function student(string $key, SystematicReview $review): array
    {
        $kappaMin = number_format((float) data_get($review->pipeline, 'screening.stages.0.calibration.kappa_min', 0.6), 2, ',', '');
        $sample = (int) ($review->pilot_size ?: data_get($review->pipeline, 'screening.stages.0.calibration.sample', 0));

        return match ($key) {
            'terms' => [
                'title' => 'Antes de começar: o termo de confidencialidade',
                'what' => ['Ler o termo inteiro com calma.', 'Assinar digitando o seu nome completo, igual ao do cadastro.'],
                'why' => 'O tema e o protocolo deste projeto foram registrados antes de qualquer triagem, e são a garantia de que a pesquisa é original e de que você será autor(a) pelo que fizer. O termo protege as ideias até a publicação, define autoria pelos critérios internacionais (ICMJE) e registra que a sua participação é voluntária e protegida pela LGPD.',
                'do' => ['Ler o termo inteiro antes de assinar.', 'Tirar qualquer dúvida com o professor antes de começar.', 'Guardar o PDF do termo (botão "Ver o termo que assinei").'],
                'dont' => ['Comentar o tema, o protocolo ou os resultados com qualquer pessoa fora da equipe.', 'Assinar sem ler: as regras de desligamento e de autoria estão aqui.'],
                'refs' => ['prismap', 'osf_form'],
            ],
            // Projeto em preparação (protocolo ainda não liberado para triagem), termo já assinado.
            'preparing' => [
                'title' => 'Etapa atual: preparação (antes do registro no OSF)',
                'what' => [
                    'Assinar o termo de cada um dos seus projetos (líder em um, revisor(a) no outro): volte em "Triagem Cega" e confira se os dois estão assinados.',
                    'Criar o seu ORCID (orcid.org › Register), com o nome exatamente como vai assinar os artigos.',
                    'Criar a sua conta no OSF (osf.io › Sign up, ou "Sign in with ORCID") e conferir o nome em Settings › Profile.',
                    'Enviar ao professor, na "Conversa do projeto", nesta mesma página, o link do seu perfil do OSF (osf.io/xxxxx) e o seu ORCID.',
                    'Ler o protocolo assim que o PDF aparecer nesta página (ele é liberado quando o protocolo é congelado) e anotar as dúvidas para a reunião de abertura.',
                ],
                'why' => 'O seu nome entra no registro do protocolo no OSF, e o registro precisa existir antes de qualquer busca ou triagem: é o que prova que o método foi definido antes de olhar os dados. Sem o seu ORCID e o seu OSF, o projeto não pode ser registrado com você na equipe.',
                'do' => ['Mandar os links do ORCID e do OSF pela conversa do projeto (fica registrado).', 'Ler o protocolo inteiro antes da reunião de abertura.', 'Reservar na agenda o período da triagem da sua onda.'],
                'dont' => ['Começar buscas por conta própria: as buscas são feitas no mesmo dia, com a estratégia registrada.', 'Comentar o tema ou o protocolo fora da equipe.', 'Colocar o protocolo em ferramentas de inteligência artificial.'],
                'refs' => ['prismap', 'osf_form'],
            ],
            'pilot' => [
                'title' => 'Etapa atual: piloto de calibração',
                'what' => [
                    "Triar sozinho(a) a amostra-piloto ({$sample} trabalhos sorteados) pelo título e resumo.",
                    'Aplicar os critérios do protocolo (abra "Protocolo, critérios e instruções" no fim da página).',
                    'Anotar no campo de nota qualquer dúvida sobre os critérios: vamos discutir na reunião.',
                ],
                'why' => "A calibração mede se você e a sua dupla entendem os critérios do mesmo jeito. O sistema calcula o kappa de Cohen, que é a concordância descontado o acaso. O protocolo exige kappa ≥ {$kappaMin}. Se ficar abaixo, ninguém é reprovado: discutimos os critérios e fazemos uma nova amostra. É treino, e é isso que dá confiança aos números do artigo.",
                'do' => ['Ler o protocolo inteiro antes do primeiro trabalho.', 'Decidir sozinho(a), com base só no que está escrito no título e no resumo.', 'Usar "Talvez" quando o resumo não permitir decidir.', 'Registrar as dúvidas na nota.'],
                'dont' => ['Conversar com a sua dupla sobre os trabalhos antes de o professor encerrar a etapa.', 'Tentar adivinhar o que o professor "quer" que seja incluído.', 'Usar inteligência artificial ou pedir a outra pessoa para decidir.'],
                'refs' => ['kappa', 'polanin', 'cochrane'],
            ],
            'title_abstract' => $review->prioritisedScreening() ? [
                'title' => 'Etapa atual: triagem por título e resumo (priorizada)',
                'what' => [
                    'Ler o título e o resumo de cada trabalho, um por vez, e decidir: Incluir, Excluir ou Talvez.',
                    'Ao excluir, escolher o motivo (vai para o fluxograma PRISMA).',
                    'O sistema aprende só com as SUAS decisões e mostra primeiro os trabalhos mais prováveis. Quem decide é sempre você.',
                    'Concluir quando o painel "Regra de parada" mostrar os quatro critérios cumpridos (estudos de validação achados, 10% triado, triados ≥ 2× relevantes e 100 irrelevantes seguidos).',
                ],
                'why' => 'Esta etapa decide o que segue para a leitura completa. O protocolo registrado usa aprendizado ativo, declarado no OSF: os trabalhos mais parecidos com os que você incluiu aparecem antes, e a regra de parada é conservadora. Depois que os dois param, o seu colega confere uma amostra aleatória do que ninguém leu; se aparecer algo elegível, a triagem reabre. O modelo de cada um é separado, por isso a triagem continua cega. "Talvez" de qualquer revisor leva o trabalho ao texto completo.',
                'do' => ['Aplicar os critérios do protocolo mesmo que pareçam estranhos num caso específico (anote a dúvida).', 'Excluir só quando o título/resumo mostrar claramente que o trabalho não atende aos critérios.', 'Ler o resumo inteiro e manter um ritmo constante, com pausas.', 'Marcar "Talvez" na dúvida.'],
                'dont' => ['Excluir por suposição ou por falta de informação no resumo (isso é "Talvez").', 'Excluir rápido só para chegar aos 100 irrelevantes seguidos: o sistema mede o ritmo e a checagem aleatória reabre a triagem se algo escapar.', 'Combinar ou comparar decisões com a sua dupla.', 'Decidir pelo nome da revista, dos autores, do país ou pelo ano.', 'Colocar títulos ou resumos em outras ferramentas de inteligência artificial (a única IA permitida é a ordenação da plataforma).'],
                'refs' => ['cochrane', 'polanin', 'double', 'prisma2020'],
            ] : [
                'title' => 'Etapa atual: triagem por título e resumo',
                'what' => [
                    'Ler o título e o resumo de cada trabalho, um por vez, e decidir: Incluir, Excluir ou Talvez.',
                    'Ao excluir, escolher o motivo (vai para o fluxograma PRISMA).',
                    'Concluir a sua triagem quando todos estiverem decididos.',
                ],
                'why' => 'Esta etapa decide o que segue para a leitura completa. O objetivo é não perder nenhum estudo relevante: um estudo excluído aqui por engano some da revisão. Por isso "Talvez" leva o trabalho para o texto completo. Duas pessoas triando de forma independente encontram muito mais estudos relevantes do que uma só. A ordem dos trabalhos é diferente para cada um, para o cansaço não cair nos mesmos trabalhos.',
                'do' => ['Aplicar os critérios do protocolo mesmo que pareçam estranhos num caso específico (anote a dúvida).', 'Excluir só quando o título/resumo mostrar claramente que o trabalho não atende aos critérios.', 'Ler o resumo inteiro e manter um ritmo constante, com pausas.', 'Marcar "Talvez" na dúvida.'],
                'dont' => ['Excluir por suposição ou por falta de informação no resumo (isso é "Talvez").', 'Decidir pelo nome da revista, dos autores, do país ou pelo ano.', 'Combinar ou comparar decisões com a sua dupla.', 'Triar com pressa: o sistema mede o ritmo e avisa o professor.', 'Colocar títulos ou resumos em ferramentas de inteligência artificial.'],
                'refs' => ['cochrane', 'polanin', 'double', 'prisma2020'],
            ],
            'full_text' => [
                'title' => 'Etapa atual: leitura do texto completo (elegibilidade)',
                'what' => [
                    'Ler o artigo inteiro de cada trabalho que passou pela triagem.',
                    'Decidir Incluir ou Excluir. Para excluir, escolher UM código de exclusão (E1, E2…).',
                    'Se não conseguir o texto, usar "Texto completo não obtido" e avisar o professor.',
                ],
                'why' => 'Esta é a decisão definitiva sobre quais estudos entram na revisão. Cada exclusão vira um número no fluxograma PRISMA, e os pareceristas das revistas conferem se os números batem. O código de exclusão segue a ordem do protocolo, para que duas pessoas cheguem ao mesmo motivo de forma reprodutível.',
                'do' => ['Ler principalmente Métodos e Resultados.', 'Escolher o código do primeiro critério não atendido, na ordem do protocolo.', 'Registrar na nota a página ou tabela que justificou a decisão.', 'Avisar o professor se faltar o PDF.'],
                'dont' => ['Incluir por achar o estudo interessante, sem atender aos critérios.', 'Reinterpretar ou mudar um critério por conta própria (leve a dúvida à reunião).', 'Compartilhar PDFs fora da equipe ou colocá-los em ferramentas de IA.', 'Discutir as decisões com a sua dupla antes do encerramento da etapa.'],
                'refs' => ['prisma2020', 'cochrane'],
            ],
            'extraction' => [
                'title' => $review->inExtractionPilot() ? 'Etapa atual: piloto de extração e risco de viés' : 'Etapa atual: extração de dados e risco de viés',
                'what' => array_values(array_filter([
                    $review->inExtractionPilot() ? 'Extrair primeiro os estudos-piloto: o professor compara as duas extrações e ajusta só as definições do formulário antes de liberar o resto.' : null,
                    'Para cada estudo, abrir o artigo e preencher os dados e os resultados por desfecho, exatamente como aparecem no texto.',
                    'Anotar em cada número a página, tabela ou figura de onde ele saiu.',
                    'Avaliar o risco de viés pela ferramenta do protocolo: julgamento e trecho que o justifica, domínio por domínio.',
                    $review->llmAssistedExtraction() ? 'Revisor(a) 1 (líder): alguns estudos chegam pré-preenchidos por IA (aviso no topo). Confira CADA valor no PDF, anote onde está, corrija o que estiver errado e apague o que não estiver no artigo. Revisor(a) 2: você extrai sem nenhum pré-preenchimento.' : null,
                    'Enviar o estudo quando estiver completo (depois do envio, só o professor reabre).',
                ])),
                'why' => 'A metanálise inteira é feita com estes números: um erro de digitação muda o resultado. Por isso duas pessoas extraem de forma independente e o sistema compara as duas versões. Cada divergência é resolvida olhando o artigo, e a página anotada é o que permite conferir. O risco de viés mostra o quanto se pode confiar em cada estudo e entra na certeza da evidência (GRADE).',
                'do' => ['Copiar os valores brutos (n, médias, desvios, eventos), sem fazer contas.', 'Anotar a página/tabela/figura de cada número.', 'Marcar "lido de gráfico" quando tirar o número de uma figura.', 'Na dúvida sobre um campo, descrever na nota e levar à reunião.', 'Justificar cada julgamento de risco de viés com o trecho do artigo.'],
                'dont' => ['Calcular, converter ou arredondar valores na extração (isso é feito na análise).', 'Comparar a sua extração com a da sua dupla antes da reconciliação.', 'Preencher com o que "deveria" estar no artigo: se não está relatado, deixe em branco e anote.', 'Colocar o PDF ou os dados em ferramentas de inteligência artificial por conta própria (o único uso permitido é o pré-preenchimento do líder, feito pelo professor e declarado no protocolo).', 'Aceitar um valor pré-preenchido sem achá-lo no artigo.', 'Usar IA para julgar o risco de viés.', 'Enviar sem a página de origem dos números.'],
                'refs' => ['cochrane', 'prisma2020'],
            ],
            default => [
                'title' => 'Próximos passos',
                'what' => ['Aguardar o professor encerrar a etapa, conferir a concordância e resolver as discordâncias.', 'Você recebe um aviso por e-mail e no Telegram quando a próxima etapa abrir.'],
                'why' => 'As discordâncias entre você e a sua dupla são resolvidas pelo professor depois que os dois terminam, e o método de resolução fica registrado. É assim que o artigo pode afirmar que a seleção foi feita em dupla, de forma independente.',
                'do' => ['Revisar o seu calendário para a próxima etapa.', 'Ler os materiais da próxima fase (abaixo).'],
                'dont' => ['Perguntar à dupla como ela votou: a etapa ainda pode ser reaberta.'],
                'refs' => ['prisma2020', 'cochrane'],
            ],
        };
    }

    /** Referências das ferramentas de risco de viés do projeto (para a fase de extração). */
    public static function robRefs(SystematicReview $review): array
    {
        $codes = collect(data_get($review->pipeline, 'risk_of_bias.tools', []))->pluck('code')->filter(fn ($c) => isset(self::REFS[$c]))->values()->all();
        if ($review->review_type === 'prevalence') {
            $codes[] = 'prevalence_guide';
        }

        return $codes;
    }

    /**
     * Trilha do projeto para o aluno: fases, situação e o porquê de cada uma.
     *
     * @return array<int, array{title: string, why: string, state: string}>
     */
    public static function roadmap(SystematicReview $review): array
    {
        $ta = $review->isStageClosed(SystematicReview::STAGE_TA);
        $ft = $review->isStageClosed(SystematicReview::STAGE_FT);
        $screening = $review->status !== SystematicReview::STATUS_PROTOCOL;
        $phase = app(\App\Services\SystematicReviewExtractionService::class)->phase($review)['code'];
        $current = match (true) {
            in_array($phase, ['P14', 'P12–P13'], true) => 'submission',
            $phase === 'P10–P11' => 'writing',
            in_array($phase, ['P8', 'P9'], true) => 'analysis',
            str_starts_with($phase, 'P7') => 'extraction',
            // Sem o DOI do OSF o projeto ainda está no protocolo (P0–P2), não nas buscas.
            !$screening && blank($review->registration_id) => 'protocol',
            !$screening => 'search',
            $review->inPilot() => 'pilot',
            !$ta => 'ta',
            !$ft && $review->stage === SystematicReview::STAGE_FT => 'ft',
            default => 'extraction',
        };
        $order = ['protocol', 'search', 'pilot', 'ta', 'ft', 'extraction', 'analysis', 'writing', 'submission'];
        $pos = array_search($current, $order, true);
        $items = [
            'protocol' => ['Protocolo registrado no OSF', 'Congela critérios e análises antes de olhar os dados: é o que impede ajustar o método ao resultado.'],
            'search' => ['Buscas nas bases e remoção de duplicatas', 'Buscar em várias bases no mesmo dia, com a estratégia registrada, torna a revisão reproduzível.'],
            'pilot' => ['Calibração da dupla (kappa)', 'Garante que os dois entendem os critérios igual antes da triagem completa.'],
            'ta' => ['Triagem por título e resumo', 'Seleção ampla e cega em dupla: não perder estudo relevante.'],
            'ft' => ['Leitura do texto completo', 'Decisão definitiva de elegibilidade, com código de exclusão para o PRISMA.'],
            'extraction' => ['Extração de dados e risco de viés em dupla', 'Dois extraem de forma independente e registram a página de cada número; o risco de viés mostra o quanto confiar em cada estudo.'],
            'analysis' => ['Metanálise e certeza da evidência (GRADE)', 'O professor roda a análise na base travada; outra pessoa reexecuta para conferir. GRADE diz o quanto confiar no resultado.'],
            'writing' => ['Redação do artigo (PRISMA 2020)', 'Todos leem e escrevem: autoria exige participação na escrita e aprovação da versão final.'],
            'submission' => ['Submissão à revista internacional', 'Dados, scripts e decisões ficam públicos no OSF, para quem quiser conferir.'],
        ];

        return collect($order)->map(fn ($key, $i) => [
            'title' => $items[$key][0],
            'why' => $items[$key][1],
            'state' => $i < $pos ? 'done' : ($i === $pos ? 'now' : 'next'),
        ])->all();
    }

    /**
     * Guia do professor: o que fazer agora, portão de qualidade e o que conferir.
     *
     * @return array{phase: string, todo: array, gate: ?string, checks: array}
     */
    public static function professor(SystematicReview $review): array
    {
        $service = app(SystematicReviewService::class);
        $kappaMin = number_format((float) data_get($review->pipeline, 'screening.stages.0.calibration.kappa_min', 0.6), 2, ',', '');
        $records = $review->records()->count();
        $screeners = $review->activeScreeners()->get();
        $signed = $screeners->filter->hasSignedTerms()->count();
        $validation = collect($review->validationSet())->map(fn ($v) => ($v['citation'] ?? $v['key'] ?? '') . (filled($v['pmid'] ?? null) ? " (PMID {$v['pmid']})" : ''))->all();

        if ($review->status === SystematicReview::STATUS_PROTOCOL) {
            if (!$review->pipeline && blank($review->protocol['inclusion_criteria'] ?? null)) {
                return ['phase' => 'P0 — Preparação', 'todo' => ['Importe o pipeline_PXX.json (Protocolo e OSF) ou preencha o protocolo em Editar.'],
                    'gate' => 'JSON validado e itens do protocolo conferidos.', 'checks' => []];
            }
            if ($review->pipeline && !$review->isFrozen()) {
                return ['phase' => 'P0 → P1 — Conferir e congelar o protocolo', 'todo' => [
                    'Confira os campos importados (Editar) e os códigos de exclusão E1….',
                    'Congele o protocolo enviando o PDF que vai para o OSF (Protocolo e OSF → Congelar).',
                ], 'gate' => 'Nenhum registro triado; SHA-256 do protocolo e do PDF gravados.', 'checks' => ['Os alunos ainda não devem ver o projeto.']];
            }
            if (blank($review->registration_id) && $review->protocol_code) {
                return ['phase' => 'P2 — Registro no OSF', 'todo' => [
                    'Crie o projeto privado no OSF com os 6 componentes (01_Protocolo_e_Registro … 06_Manuscrito).',
                    'Suba no componente 01 o PDF, a ficha (Baixar → Ficha OSF) e o modelo/dicionário de extração.',
                    'Registre (template Generalized Systematic Review Registration), escolha embargo ou público e grave o DOI aqui.',
                ], 'gate' => 'DOI gravado antes de qualquer triagem; equipe ainda não triou nada.', 'checks' => ['Deixe só você como contribuidor no registro; os alunos entram depois.']];
            }
            if ($records === 0) {
                return ['phase' => 'P3–P4 — Buscas e deduplicação', 'todo' => [
                    'Rode as estratégias de todas as bases no MESMO dia (Editar → Search strategy) e exporte os RIS.',
                    'Importe o resultado (Importar registros); o sistema remove duplicatas por DOI, PMID e título.',
                    'Suba no OSF (componente 02) o log das buscas e os RIS.',
                ], 'gate' => 'Todas as bases rodadas no mesmo dia; conjunto de validação recuperado em cada base; duplicatas conferidas.',
                    'checks' => $validation ? array_merge(['Confira se as buscas recuperam os estudos de validação (não mostre esta lista aos alunos):'], $validation) : []];
            }
            if ($screeners->count() < 2) {
                return ['phase' => 'Montar a dupla', 'todo' => [
                    'Inclua o líder (Revisor 1) e o revisor cruzado (Revisor 2) em Triadores (ou pela aprovação na Seleção de Alunos).',
                    'Faça a reunião de treinamento: leitura do protocolo, critérios e regras de cegamento.',
                ], 'gate' => 'Dois triadores incluídos (' . $screeners->count() . ' de 2).', 'checks' => ['Ninguém pode ser líder e revisor no mesmo projeto.']];
            }

            return ['phase' => 'Pronto para liberar a calibração', 'todo' => [
                'Clique em "Liberar triagem". Só então os alunos veem o projeto no portal.',
                'No primeiro acesso, cada aluno assina o termo (' . $signed . ' de ' . $screeners->count() . ' já assinaram); ninguém tria antes de assinar.',
                'Os alunos começam pela amostra-piloto' . ($review->pilot_size ? " ({$review->pilot_size} registros)" : '') . '.',
            ], 'gate' => $service->startProblems($review) ? implode(' ', $service->startProblems($review)) : 'Tudo pronto.', 'checks' => []];
        }

        if ($review->inPilot()) {
            return ['phase' => 'P5a — Calibração (piloto)', 'todo' => [
                'Acompanhe o progresso dos dois em Triadores; não abra as decisões individuais.',
                'Quando os dois concluírem, confira o kappa em "Confiabilidade entre triadores".',
                "Kappa (3 categorias ou binário OSF) ≥ {$kappaMin}: libere a triagem completa. Abaixo de 0,60 em ambos: reunião, discutir os critérios (sem mudar o protocolo) e nova amostra; justifique se liberar mesmo assim.",
            ], 'gate' => "Kappa ≥ {$kappaMin} na amostra-piloto.", 'checks' => ['Registre como desvio qualquer mudança de interpretação dos critérios.']];
        }

        if (in_array($review->status, [SystematicReview::STATUS_SCREENING, SystematicReview::STATUS_CONFLICTS], true)) {
            $ft = $review->stage === SystematicReview::STAGE_FT;
            $conflicts = $service->conflictsQuery($review)->count();

            return ['phase' => $ft ? 'P6 — Texto completo' : 'P5b — Triagem título/resumo', 'todo' => array_values(array_filter([
                'Acompanhe o ritmo de cada aluno (alerta de triagem rápida demais) e mande lembretes se atrasarem.',
                $ft ? 'Garanta o acesso aos PDFs; registros sem texto entram como "Texto completo não obtido" depois do pedido ao autor.' : null,
                $review->status === SystematicReview::STATUS_CONFLICTS ? "Resolva os {$conflicts} conflito(s): sobrepor um consenso exige justificativa." : 'Os conflitos só aparecem depois que os dois concluírem: decidir antes quebra o cegamento.',
                'Encerre a etapa e suba no OSF (componente 03) as decisões fechadas e as contagens PRISMA.',
            ])), 'gate' => $ft ? '100% com 2 decisões; conflitos resolvidos; avaliados = incluídos + excluídos por código.'
                : ($review->prioritisedScreening()
                    ? 'Regra de parada cumprida pelos dois (validação achada, ≥ 10% triado, triados ≥ 2× relevantes, 100 irrelevantes seguidos); checagem aleatória do Revisor 2 (10%, mín. 100) sem elegíveis; kappa nos registros que os dois leram.'
                    : '100% com 2 decisões; conflitos resolvidos; kappa e concordância calculados.'),
                'checks' => $review->prioritisedScreening() && !$ft
                    ? ['Relevante ou "talvez" para qualquer revisor vai ao texto completo automaticamente (regra do protocolo v1.1).',
                        'Se a checagem aleatória achar algo elegível, o sistema reabre a triagem dos dois sozinho.',
                        'Não lidos por ninguém após a checagem entram no PRISMA como "excluídos pela regra de parada" e no texto de Métodos.']
                    : ['"Talvez" de qualquer revisor vai para o texto completo automaticamente (regra do protocolo).']];
        }

        $ex = app(\App\Services\SystematicReviewExtractionService::class);
        $code = $ex->phase($review)['code'];
        $studies = $review->activeStudies()->with('extractions')->get();
        $pending = $studies->filter(fn ($s) => $s->submittedCount() < 2)->count();
        $toReconcile = $studies->filter(fn ($s) => $s->submittedCount() >= 2 && !$s->isReconciled())->count();
        $lock = $review->latestLock();

        return match (true) {
            $code === 'P7' => ['phase' => 'P7 — Abrir a extração', 'todo' => [
                'Suba no OSF as decisões fechadas e as contagens PRISMA (componente 03), se ainda não subiu.',
                'Clique em "Extração e análise → Abrir extração (P7)": o sistema cria um estudo por registro incluído e libera o piloto (3 estudos).',
                'Se dois registros forem relatos do mesmo estudo, mescle-os em Estudos antes de os alunos extraírem.',
            ], 'gate' => 'Triagem encerrada; dois revisores ativos.', 'checks' => []],
            $code === 'P7a' => ['phase' => 'P7a — Piloto de extração', 'todo' => [
                'Aguarde os dois enviarem os estudos-piloto; compare as divergências em Estudos → Reconciliar.',
                'Ajuste só definições e instruções (sem mudar os campos registrados) e libere a extração completa.',
            ], 'gate' => 'Concordância discutida; versão do formulário registrada.', 'checks' => ['Mudança de campo ou desfecho registrado = emenda (antes de seguir).']],
            $code === 'P7b–c' => ['phase' => 'P7b–c — Extração em duplicata, reconciliação e trava', 'todo' => array_values(array_filter([
                $pending ? "{$pending} estudo(s) ainda sem as duas extrações enviadas: acompanhe e mande lembretes." : null,
                $toReconcile ? "{$toReconcile} estudo(s) prontos para reconciliar (Estudos → Reconciliar): resolva cada divergência olhando o artigo." : null,
                'Registre os pedidos de dados a autores em "Contatos com autores" (lembretes em 2 e 4 semanas).',
                'Com tudo reconciliado e as checagens automáticas OK, trave a base (Extração e análise → Travar base).',
            ])), 'gate' => 'Zero divergências abertas; checagens automáticas OK (eventos ≤ n, DP > 0, proporção 0–1, página de origem); base travada com SHA-256.', 'checks' => array_slice($ex->lockProblems($review), 0, 6)],
            $code === 'P8' => ['phase' => 'P8 — Análise estatística', 'todo' => [
                "Rode o script R sobre a base travada v{$lock?->version} (Baixar → Extração travada) e registre a análise com o script, as saídas e o sessionInfo.",
                'Peça a outra pessoa para reexecutar e confirmar o resultado idêntico (Análises → Confirmar reexecução).',
            ], 'gate' => 'Reexecução independente com resultado idêntico.', 'checks' => ['Mínimos do protocolo: pooling k ≥ 3; metarregressão k ≥ 10 por covariável; viés de publicação k ≥ 10. Abaixo disso: SWiM.', 'Análise não prevista = post hoc (vira desvio automaticamente).']],
            $code === 'P9' => ['phase' => 'P9 — GRADE e tabela de resumo dos achados', 'todo' => [
                'Em GRADE, crie as linhas a partir dos desfechos do protocolo e preencha k, n, efeito, IC, intervalo de predição e a certeza com as razões de rebaixamento.',
            ], 'gate' => 'Todos os desfechos primários com certeza justificada.', 'checks' => []],
            $code === 'P10–P11' => ['phase' => 'P10–P11 — Redação e revisão interna', 'todo' => [
                'Crie o processo de redação do artigo (Gerenciar → Criar artigo) e marque o checklist de submissão em "Extração e análise → Manuscrito e submissão".',
                'Encerre o embargo do OSF ou gere o link de visualização para os revisores.',
            ], 'gate' => 'Checklist PRISMA 2020 completo; desvios declarados; aprovação escrita de todos os coautores.', 'checks' => []],
            default => ['phase' => $code . ' — ' . $ex->phase($review)['label'], 'todo' => [
                'Deposite no OSF (CC BY 4.0) a base travada, os scripts, as saídas e as decisões, e torne os componentes públicos.',
                'Registre a decisão da revista e, ao publicar, o DOI do artigo (Manuscrito e submissão).',
            ], 'gate' => 'Links cruzados OSF ↔ artigo; Lattes/ORCID atualizados.', 'checks' => ['Reanálises pedidas pelos revisores: só na base travada, com nova versão registrada.']],
        };
    }
}
