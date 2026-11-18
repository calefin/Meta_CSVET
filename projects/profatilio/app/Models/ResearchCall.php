<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Chamada pública de seleção de alunos para os projetos de pesquisa
 * (página /selecao/{slug}). Os projetos aparecem só pelo código e pela área
 * "blindada": o título real só é revelado depois do termo de confidencialidade.
 */
class ResearchCall extends Model
{
    public const STATUS_DRAFT = 'draft';
    public const STATUS_OPEN = 'open';
    public const STATUS_CLOSED = 'closed';

    public const STATUS_LABELS = [
        self::STATUS_DRAFT => 'Rascunho (não aparece no site)',
        self::STATUS_OPEN => 'Inscrições abertas',
        self::STATUS_CLOSED => 'Inscrições encerradas',
    ];

    public const COURSES = [
        'Medicina Veterinária',
        'Ciências Biológicas',
        'Zootecnia',
        'Biomedicina',
        'Outro curso',
    ];

    protected $fillable = [
        'title',
        'slug',
        'status',
        'headline',
        'intro',
        'benefits',
        'requirements',
        'commitments',
        'steps',
        'faq',
        'axes',
        'projects',
        'interview_questions',
        'min_semester',
        'eligible_courses',
        'min_weekly_hours',
        'closes_at',
    ];

    protected $casts = [
        'benefits' => 'array',
        'requirements' => 'array',
        'commitments' => 'array',
        'steps' => 'array',
        'faq' => 'array',
        'axes' => 'array',
        'projects' => 'array',
        'interview_questions' => 'array',
        'eligible_courses' => 'array',
        'closes_at' => 'date',
        'min_semester' => 'integer',
        'min_weekly_hours' => 'integer',
    ];

    protected static function booted(): void
    {
        static::saving(function (ResearchCall $call) {
            $call->slug = Str::slug($call->slug ?: $call->title) ?: 'selecao';
        });
    }

    /** Natureza da participação (vai na página, no termo de adesão e no termo do portal). */
    public const NATURE_TEXT = 'A participação é voluntária, gratuita e de natureza exclusivamente educacional: trata-se de um processo de mentoria e orientação científica conduzido pelo Prof. Dr. Atilio Sersun Calefi. Não há bolsa, salário, remuneração, ajuda de custo ou ressarcimento de despesas, nem cobrança de qualquer valor do participante. Não há jornada, horário fixo ou local de trabalho: o participante organiza livremente os seus horários e cumpre apenas os prazos acadêmicos das etapas (a estimativa de horas semanais é só uma referência de dedicação). A participação não configura relação de emprego (arts. 2º e 3º da CLT), estágio (Lei nº 11.788/2008) nem prestação de serviços, e não gera direitos trabalhistas, previdenciários ou indenizatórios; aplica-se, no que couber, a Lei nº 9.608/1998 (serviço voluntário).';

    /** Aviso de proteção de dados (LGPD). */
    public const LGPD_TEXT = 'Os dados pessoais são tratados conforme a Lei nº 13.709/2018 (LGPD). Controlador: Prof. Dr. Atilio Sersun Calefi (contato@profatilio.com.br). Dados: nome, e-mail, telefone, formação, respostas do formulário e os dados de comprovação do aceite (data e hora, endereço IP, navegador e impressão digital SHA-256 do texto aceito). Finalidades: seleção, organização do projeto, comunicação com o participante, registro de autoria e comprovação do aceite. Bases legais: consentimento (art. 7º, I) e exercício regular de direitos (art. 7º, VI). A seleção dos candidatos é feita pessoalmente pelo Prof. Atilio, sem decisão automatizada nem uso de inteligência artificial (art. 20). Não são coletados dados sensíveis; os dados não são vendidos nem compartilhados, exceto com os provedores de hospedagem e e-mail da plataforma e, quanto ao nome e à filiação, com as revistas na publicação. O titular pode pedir confirmação, acesso, correção, anonimização, portabilidade ou eliminação dos dados e revogar o consentimento (art. 18) pelo e-mail acima, ressalvados os registros necessários para comprovar o aceite e a autoria (art. 16). Dados de quem não for selecionado são guardados por até 2 anos e podem ser excluídos antes, a pedido.';

    /**
     * Termo de adesão aceito no envio da inscrição. O texto exato (com nome,
     * e-mail e data) é gravado na inscrição junto com IP, navegador e SHA-256.
     */
    public function adhesionText(string $name, string $email, string $acceptedAt): string
    {
        $items = collect($this->texts('commitments'))->map(fn ($t, $i) => ($i + 1) . '. ' . $t)->implode("\n");

        return "TERMO DE ADESÃO À SELEÇÃO\n{$this->title}\n"
            . "Orientador: Prof. Dr. Atilio Sersun Calefi\n"
            . "Candidato(a): {$name} ({$email})\n"
            . "Data e hora do aceite: {$acceptedAt}\n\n"
            . "Ao enviar a minha inscrição, declaro que li, entendi e aceito as condições abaixo.\n\n"
            . "NATUREZA DA PARTICIPAÇÃO\n" . self::NATURE_TEXT . "\n\n"
            . "COMPROMISSOS\n{$items}\n\n"
            . "PROTEÇÃO DE DADOS (LGPD)\n" . self::LGPD_TEXT . "\n\n"
            . 'Este aceite é registrado com data e hora, endereço IP, navegador e a impressão digital SHA-256 deste texto. Se for selecionado(a), assinarei no Portal do Aluno o termo de confidencialidade, propriedade intelectual e participação, que detalha estas condições.';
    }

    public function applications()
    {
        return $this->hasMany(ResearchApplication::class);
    }

    public function isOpen(): bool
    {
        return $this->status === self::STATUS_OPEN
            && (!$this->closes_at || $this->closes_at->endOfDay()->isFuture());
    }

    public function publicUrl(): string
    {
        return route('research-call.show', $this->slug);
    }

    /** Projetos indexados pelo código (PET-01 => [...]). */
    public function projectsByCode(): array
    {
        return collect($this->projects ?? [])
            ->filter(fn ($p) => filled($p['code'] ?? null))
            ->keyBy('code')
            ->all();
    }

    /** [código => "PET-01 — área"] para selects. */
    public function projectOptions(): array
    {
        return collect($this->projectsByCode())
            ->map(fn ($p, $code) => $code . ' — ' . ($p['area'] ?? ''))
            ->all();
    }

    /**
     * Quem já foi atribuído a cada projeto (aprovados): [código => ['lead' => nome, 'reviewer' => nome]].
     */
    public function assignments(): array
    {
        $out = [];
        foreach ($this->applications()->where('status', ResearchApplication::STATUS_APPROVED)->get(['name', 'lead_project_code', 'reviewer_project_code']) as $a) {
            if ($a->lead_project_code) {
                $out[$a->lead_project_code]['lead'] = $a->name;
            }
            if ($a->reviewer_project_code) {
                $out[$a->reviewer_project_code]['reviewer'] = $a->name;
            }
        }

        return $out;
    }

    /**
     * O projeto sai da lista de candidatura quando a dupla está completa (líder e revisor aprovados)
     * ou quando o professor o marca como fechado. Com só um dos dois, continua na lista com "1 vaga".
     */
    public function isProjectOpen(array $project, ?array $assignments = null): bool
    {
        if (!empty($project['closed'])) {
            return false;
        }
        $a = ($assignments ?? $this->assignments())[$project['code'] ?? ''] ?? [];

        return empty($a['lead']) || empty($a['reviewer']);
    }

    /** Projetos ainda abertos à candidatura, por código. */
    public function openProjectsByCode(): array
    {
        $assignments = $this->assignments();

        return collect($this->projectsByCode())->filter(fn ($p) => $this->isProjectOpen($p, $assignments))->all();
    }

    /** Projetos agrupados por eixo, na ordem dos eixos cadastrados. Com $onlyOpen, some o que já tem dupla completa. */
    public function projectsByAxis(bool $onlyOpen = false): array
    {
        $assignments = $onlyOpen ? $this->assignments() : [];
        $grouped = collect($this->projects ?? [])
            ->when($onlyOpen, fn ($c) => $c->filter(fn ($p) => filled($p['code'] ?? null) && $this->isProjectOpen($p, $assignments))
                ->map(fn ($p) => $p + ['half' => !empty($assignments[$p['code']]['lead']) || !empty($assignments[$p['code']]['reviewer'])]))
            ->groupBy(fn ($p) => $p['axis'] ?? 'Outros');
        $out = [];
        foreach ($this->axes ?? [] as $axis) {
            $projects = $grouped->pull($axis['name'], collect())->values()->all();
            if ($onlyOpen && !$projects) {
                continue; // eixo sem vaga não aparece
            }
            $out[$axis['name']] = ['axis' => $axis, 'projects' => $projects];
        }
        foreach ($grouped as $name => $projects) {
            $out[$name] = ['axis' => ['name' => $name, 'description' => null], 'projects' => $projects->all()];
        }

        return $out;
    }

    public function axisNames(): array
    {
        return collect($this->axes ?? [])->pluck('name')->filter()->values()->all();
    }

    public function eligibleCourses(): array
    {
        return array_values(array_filter($this->eligible_courses ?? []));
    }

    /** Lista de textos de um campo de lista simples (aceita também [{text: ...}]). */
    public function texts(string $field): array
    {
        return collect($this->{$field} ?? [])
            ->map(fn ($i) => is_array($i) ? trim((string) ($i['text'] ?? '')) : trim((string) $i))
            ->filter()
            ->values()
            ->all();
    }

    /** Mensagem pronta para WhatsApp/grupos (sem títulos reais). */
    public function shareText(): string
    {
        $axes = collect($this->axes ?? [])->map(function ($a) {
            $n = collect($this->projects ?? [])->where('axis', $a['name'])->count();

            return '• *' . $a['name'] . '*' . ($n ? " ({$n} projetos)" : '');
        })->implode("\n");

        $req = collect($this->texts('requirements'))->map(fn ($t) => '✔️ ' . $t)->implode("\n");

        return "🚨 *{$this->title}* 🚨\n"
            . "Orientação: *Prof. Dr. Atilio Sersun Calefi*\n\n"
            . ($this->headline ? $this->headline . "\n\n" : '')
            . "💚 *Sem nenhum custo para o aluno.* O que conta é o seu compromisso: dedicação semanal, prazos e sigilo.\n"
            . "📌 Mentoria científica voluntária: sem bolsa, sem horário fixo e sem vínculo empregatício. Participação pessoal e intransferível; escrita do artigo (em inglês, revista internacional) obrigatória; triagem sem IA.\n\n"
            . ($axes ? "🔬 *Eixos:*\n{$axes}\n\n" : '')
            . ($req ? "📋 *Pré-requisitos:*\n{$req}\n\n" : '')
            . ($this->closes_at ? '⏳ Inscrições até *' . $this->closes_at->format('d/m/Y') . "*\n\n" : '')
            . "👉 Leia as condições, o passo a passo e inscreva-se: {$this->publicUrl()}";
    }

    /**
     * Conteúdo-padrão da chamada das 20 metanálises (do guia de captação do
     * laboratório). Tudo é editável no painel.
     */
    public static function defaults(): array
    {
        $axes = [
            'pets' => 'Eixo 1 — Pets não convencionais',
            'onco' => 'Eixo 2 — Oncopatologia e biomarcadores',
            'immuno' => 'Eixo 3 — Imunopatologia, doenças infecciosas e grandes animais',
        ];

        $project = fn (string $code, string $axis, string $area) => [
            'code' => $code, 'axis' => $axes[$axis], 'area' => $area, 'systematic_review_id' => null,
        ];

        return [
            'title' => 'Seleção 2026/2027 — Projetos de Pesquisa e Produção de Artigos (Revisões Sistemáticas e Metanálises)',
            'slug' => 'metanalises-2026',
            'status' => self::STATUS_DRAFT,
            'headline' => 'Participe de projetos de revisão sistemática e metanálise em Medicina Veterinária, com produção de artigos científicos: sem nenhum custo para o aluno, trabalho 100% remoto, em dupla, com orientação semanal e meta de submissão a revistas internacionais indexadas.',
            'intro' => "O laboratório tem 20 projetos de revisão sistemática com metanálise prontos para começar, com protocolo escrito e registro prospectivo.\n\n"
                . "A participação não tem nenhum custo para o aluno: não há taxa de inscrição, mensalidade nem cobrança de qualquer tipo. O que o projeto exige é o seu compromisso: dedicação semanal, cumprimento dos prazos e sigilo.\n\n"
                . "Trata-se de um processo de mentoria científica, voluntário e de natureza educacional, orientado pelo Prof. Dr. Atilio Sersun Calefi: não há bolsa nem remuneração, não há horário fixo a cumprir (você organiza os seus horários e cumpre os prazos das etapas) e a participação não configura vínculo empregatício nem estágio.\n\n"
                . "Cada aluno lidera um projeto (1º autor) e é o revisor cego de outro projeto (coautor). Assim, cada aluno participa de 2 artigos.\n\n"
                . 'O trabalho é feito no computador, sem bancada nem uso de animais, com tarefas semanais guiadas passo a passo pelo Portal do Aluno.',
            'benefits' => self::wrap([
                'Participação em 2 artigos: 1º autor no projeto que você lidera e coautor no projeto em que você é revisor cego.',
                'Experiência real em pesquisa e produção científica para o currículo (Residência, Mestrado e Doutorado).',
                'Treinamento prático em metodologia internacional: protocolo PRISMA-P, triagem cega em dupla, extração de dados, risco de viés e PRISMA 2020.',
                'Introdução à bioestatística no R: você acompanha a análise da metanálise feita pelo Prof. Atilio.',
                'Orientação direta e acompanhamento semanal.',
            ]),
            'requirements' => self::wrap([
                'Estar cursando Medicina Veterinária ou Ciências Biológicas, a partir do 4º semestre.',
                'Ter, em média, de 4 a 6 horas por semana para o projeto, em horários livres que você escolhe (remoto, sem horário fixo).',
                'Conseguir ler artigos científicos em inglês (pode usar tradutor).',
                'Ter acesso à internet para usar o Portal do Aluno (no computador ou no celular; a leitura dos artigos completos é mais confortável no computador).',
            ]),
            'commitments' => self::wrap([
                'Ter o Prof. Dr. Atilio Sersun Calefi como orientador do projeto e de todos os trabalhos dele derivados.',
                'Entender que os projetos foram concebidos e delimitados pelo Prof. Atilio e que a minha participação é pessoal e intransferível: não posso repassar a minha vaga, o tema, o protocolo ou os dados a outra pessoa, nem usá-los com outro orientador ou para outro fim. O uso indevido leva ao desligamento imediato, à perda da autoria, à comunicação do fato à minha instituição de ensino e às revistas, e às penalidades previstas na legislação (Código Civil, Lei de Direitos Autorais e demais normas aplicáveis).',
                'Estar ciente de que a participação é voluntária, gratuita e de natureza educacional (mentoria científica): não há bolsa, remuneração nem horário fixo a cumprir, e ela não configura vínculo empregatício (arts. 2º e 3º da CLT), estágio (Lei nº 11.788/2008) nem prestação de serviços.',
                'Reservar, em média, de 4 a 6 horas por semana, nos horários que eu escolher, para cumprir as metas e os prazos de cada etapa. A triagem é em dupla: se eu atrasar, a minha dupla fica parada.',
                'Assinar o termo de confidencialidade no Portal do Aluno e não divulgar o tema, as buscas nem os resultados até a publicação.',
                'Seguir o protocolo registrado e fazer a triagem cega sozinho(a), sem combinar votos com a minha dupla.',
                'Fazer pessoalmente a seleção dos estudos (triagem), a extração de dados e a avaliação de risco de viés: essas tarefas não podem ser delegadas a ferramentas de inteligência artificial (ChatGPT, Gemini, Copilot, triagem automática ou similares) nem a outra pessoa, e os dados do projeto não podem ser colocados nessas ferramentas.',
                'Ler o artigo e participar da escrita e da revisão do texto: o artigo será submetido a uma revista científica internacional, em inglês, e a leitura e a participação na escrita são obrigatórias para ser autor.',
                'Estar ciente de que atrasos na entrega das etapas, prazos descumpridos sem justificativa, falta de resposta aos contatos ou o não atendimento ao objetivo da pesquisa apresentada podem levar ao meu desligamento do projeto, a critério do professor e comunicado por e-mail: a minha parte passa para outro aluno e eu não fico como autor das etapas que não concluí.',
                'Entender que a autoria segue as regras do ICMJE: só é autor quem cumpre todas as suas funções. Quem abandona o projeto ou é desligado(a) perde a autoria.',
                'Responder aos contatos do professor em até 2 dias úteis e avisar com antecedência qualquer impedimento.',
                'Autorizar o tratamento dos meus dados pessoais para esta seleção, a organização do projeto e o registro de autoria, nos termos da LGPD (Lei nº 13.709/2018), sabendo que posso pedir acesso, correção ou exclusão pelo e-mail contato@profatilio.com.br.',
            ]),
            'steps' => [
                ['phase' => 'Seleção', 'title' => 'Inscrição', 'when' => 'Agora', 'text' => 'Preencha o formulário desta página. Você recebe por e-mail a confirmação e um link para acompanhar a sua inscrição.'],
                ['phase' => 'Seleção', 'title' => 'Análise dos requisitos', 'when' => 'Até 7 dias', 'text' => 'O Prof. Atilio confere a sua inscrição. Se faltar algum requisito, você é avisado(a) por e-mail.'],
                ['phase' => 'Seleção', 'title' => 'Entrevista rápida', 'when' => '5 a 10 minutos', 'text' => 'Conversa online para confirmar a sua disponibilidade, a leitura em inglês, o que você espera do projeto e o compromisso com os prazos. A data e o link chegam por e-mail.'],
                ['phase' => 'Seleção', 'title' => 'Resultado e alocação', 'when' => 'Após a entrevista', 'text' => 'Você recebe o código do projeto que vai liderar (1º autor) e o código do projeto em que será revisor(a) cego(a) (coautor).'],
                ['phase' => 'Projeto', 'title' => 'Login no Portal do Aluno', 'when' => 'Semana 1', 'text' => 'Crie o seu login pelo link do e-mail. Se você já tem conta no portal, use a mesma.'],
                ['phase' => 'Projeto', 'title' => 'Termo de confidencialidade', 'when' => 'Semana 1', 'text' => 'Leia e assine o termo no portal. Só depois disso o tema real do projeto aparece para você.'],
                ['phase' => 'Projeto', 'title' => 'Treinamento e piloto', 'when' => 'Semana 2', 'text' => 'Você aprende a usar a Triagem Cega e faz um piloto curto para calibrar com a sua dupla.'],
                ['phase' => 'Projeto', 'title' => 'Triagem de títulos e resumos', 'when' => 'Semanas 3 a 5', 'text' => 'Você e a sua dupla avaliam os artigos encontrados nas buscas, cada um sem ver o voto do outro. As discordâncias são resolvidas pelo professor.'],
                ['phase' => 'Projeto', 'title' => 'Leitura completa e extração de dados', 'when' => 'Semanas 6 a 8', 'text' => 'Leitura do texto completo dos artigos selecionados, extração dos dados na planilha padronizada e avaliação do risco de viés, também em dupla.'],
                ['phase' => 'Projeto', 'title' => 'Análise estatística', 'when' => 'Semana 9', 'text' => 'O Prof. Atilio faz a metanálise no R e explica os gráficos e os resultados para você.'],
                ['phase' => 'Projeto', 'title' => 'Redação do artigo', 'when' => 'Semanas 10 a 12', 'text' => 'O líder escreve o manuscrito em inglês (padrão PRISMA 2020) junto com o professor e o revisor cego revisa o texto. Ler e participar da escrita é obrigatório para todos os autores.'],
                ['phase' => 'Projeto', 'title' => 'Submissão', 'when' => 'Semana 13 em diante', 'text' => 'Envio a uma revista científica internacional e resposta aos pareceristas, com a sua participação obrigatória na revisão do texto até a decisão final.'],
            ],
            'faq' => [
                ['q' => 'Tem algum custo para participar?', 'a' => 'Não. A participação é gratuita: não há taxa de inscrição, mensalidade nem cobrança de qualquer tipo, em nenhuma etapa. O que o projeto exige é o seu compromisso com a dedicação semanal, os prazos e o sigilo. A continuidade no projeto depende disso.'],
                ['q' => 'Quem é o orientador?', 'a' => 'O Prof. Dr. Atilio Sersun Calefi é o orientador e o coordenador de todos os projetos desta seleção e dos trabalhos derivados deles.'],
                ['q' => 'Posso passar o projeto para outra pessoa ou levá-lo para outro orientador?', 'a' => 'Não. Os projetos foram concebidos e delimitados pelo Prof. Atilio, e a participação é pessoal e intransferível. Repassar a vaga, o tema, o protocolo ou os dados a outra pessoa, ou usá-los com outro orientador ou para outro fim, leva ao desligamento imediato, à perda da autoria, à comunicação do fato à sua instituição de ensino e às revistas, e às penalidades previstas na legislação. Isso está no termo que você assina no portal.'],
                ['q' => 'Tem bolsa? É emprego ou estágio? Tenho horário fixo?', 'a' => 'Não há bolsa nem remuneração, e não há horário fixo: você organiza os seus horários e só precisa cumprir os prazos de cada etapa. É um processo de mentoria científica, voluntário e de natureza educacional, que não configura vínculo empregatício (arts. 2º e 3º da CLT), estágio (Lei nº 11.788/2008) nem prestação de serviços. Isso também está no termo assinado no portal.'],
                ['q' => 'Como os meus dados são tratados?', 'a' => 'Conforme a LGPD (Lei nº 13.709/2018). Os dados da inscrição são usados só para a seleção, a organização do projeto e o registro de autoria, não são vendidos nem compartilhados com terceiros (exceto os serviços de hospedagem e e-mail da plataforma) e não incluem dados sensíveis. Você pode pedir acesso, correção ou exclusão pelo contato@profatilio.com.br.'],
                ['q' => 'Posso usar inteligência artificial na triagem?', 'a' => 'Não. A seleção dos estudos (triagem), a extração de dados e a avaliação de risco de viés são feitas por você, pessoalmente. Elas não podem ser delegadas a ferramentas de IA (ChatGPT, Gemini, Copilot, triagem automática ou similares) nem a outra pessoa, e os dados do projeto não podem ser colocados nessas ferramentas. A seleção dos candidatos também é feita pessoalmente pelo Prof. Atilio, sem IA.'],
                ['q' => 'Preciso escrever o artigo?', 'a' => 'Sim. O artigo será submetido a uma revista científica internacional, em inglês. Ler o manuscrito e participar da escrita e da revisão (inclusive nas respostas aos pareceristas) é obrigatório para ser autor. O professor orienta a redação.'],
                ['q' => 'Preciso saber inglês fluente?', 'a' => 'Não. Você precisa conseguir ler artigos científicos, e pode usar tradutor. O artigo é escrito em inglês com o apoio do professor, mas a sua leitura e participação na escrita são obrigatórias.'],
                ['q' => 'Preciso saber estatística ou programar em R?', 'a' => 'Não. A análise é feita pelo Prof. Atilio, que explica os resultados. Quem quiser aprender pode acompanhar o script.'],
                ['q' => 'A publicação é garantida?', 'a' => 'Nenhuma publicação é garantida, porque depende do aceite da revista e dos pareceristas. O compromisso do laboratório é conduzir o projeto até a submissão e responder às revisões.'],
                ['q' => 'O que acontece se eu atrasar ou não cumprir o que foi combinado?', 'a' => 'Os prazos são combinados no início de cada etapa. Atrasos sem aviso ou repetidos, falta de resposta ou o não atendimento ao objetivo da pesquisa apresentada podem levar ao desligamento do projeto, que é comunicado por e-mail com o motivo. A sua parte passa para outro aluno e você não fica como autor das etapas que não concluiu. Isso está nos compromissos da inscrição e no termo que você assina no portal.'],
                ['q' => 'Por que não aparece o título dos projetos?', 'a' => 'Para proteger as ideias antes da publicação. O título e o protocolo completo aparecem no portal depois que você assina o termo de confidencialidade.'],
                ['q' => 'Posso escolher o projeto?', 'a' => 'Você indica os eixos e os projetos de preferência. A alocação final considera a sua preferência, a entrevista e o equilíbrio das duplas.'],
                ['q' => 'E se eu precisar sair do projeto?', 'a' => 'Avise o professor o quanto antes. Quem sai antes de cumprir as suas funções não fica como autor, mas continua obrigado(a) a manter o sigilo do termo.'],
            ],
            'axes' => [
                ['name' => $axes['pets'], 'description' => 'Clínica, cirurgia, anestesia e doenças de coelhos, roedores, furões, aves e répteis.'],
                ['name' => $axes['onco'], 'description' => 'Patologia diagnóstica e prognóstica de tumores em cães e em pequenos mamíferos: marcadores, graduação histológica e sobrevida.'],
                ['name' => $axes['immuno'], 'description' => 'Imunopatologia, patologia de órgãos e doenças infecciosas em cães, gatos, equinos e pequenos mamíferos.'],
            ],
            'projects' => [
                $project('PET-01', 'pets', 'Farmacologia clínica em lagomorfos'),
                $project('PET-02', 'pets', 'Oncologia em lagomorfos'),
                $project('PET-03', 'pets', 'Farmacologia anestésica em animais exóticos'),
                $project('PET-04', 'pets', 'Clínica médica de roedores e lagomorfos'),
                $project('PET-05', 'pets', 'Epidemiologia clínica em animais exóticos'),
                $project('PET-06', 'pets', 'Clínica médica e cirúrgica de furões'),
                $project('PET-07', 'pets', 'Anestesiologia de aves'),
                $project('PET-08', 'pets', 'Doenças infecciosas de aves'),
                $project('PET-09', 'pets', 'Manejo e clínica de répteis'),
                $project('PET-10', 'pets', 'Saúde Única em animais não convencionais'),
                $project('PAT-01', 'onco', 'Oncopatologia de pequenos mamíferos'),
                $project('PAT-05', 'onco', 'Prognóstico oncológico em cães'),
                $project('PAT-07', 'onco', 'Diagnóstico imuno-histoquímico em cães'),
                $project('PAT-09', 'onco', 'Graduação histológica e sobrevida em cães'),
                $project('PAT-02', 'immuno', 'Imunopatologia felina'),
                $project('PAT-06', 'immuno', 'Doenças infecciosas de pequenos mamíferos'),
                $project('PAT-03', 'immuno', 'Nefropatologia em cães'),
                $project('PAT-04', 'immuno', 'Dermatopatologia equina'),
                $project('PAT-08', 'immuno', 'Patologia reprodutiva equina'),
                $project('PAT-10', 'immuno', 'Hepatopatologia canina'),
            ],
            'interview_questions' => self::wrap([
                'Disponibilidade real de 4 a 6 horas por semana no computador?',
                'Lê artigos em inglês (com tradutor, se precisar)?',
                'O que espera do projeto (currículo, publicação, pós-graduação)?',
                'Entende que a dupla depende dos prazos dele(a) e que atrasos podem levar ao desligamento?',
                'Concorda em assinar o termo de confidencialidade e compromisso autoral?',
            ]),
            'min_semester' => 4,
            'eligible_courses' => ['Medicina Veterinária', 'Ciências Biológicas'],
            'min_weekly_hours' => 4,
        ];
    }

    /** Listas simples (Repeater::simple no painel) ficam como array de textos. */
    protected static function wrap(array $texts): array
    {
        return array_values($texts);
    }
}
