<!DOCTYPE html>
<html lang="pt-BR" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="theme-color" content="#030712">
    <meta name="robots" content="noindex, nofollow">
    <title>Devolutivas de Trabalhos &amp; Avaliações — Prof. Dr. Atilio Calefi</title>
    <meta name="description" content="Consulta individual anônima e segura (Zero-Knowledge / LGPD) de devolutivas de trabalhos acadêmicos, espelhos de provas e gabaritos comentados.">
    <link class="favicon" rel="icon" type="image/png" sizes="96x96" href="{{ asset('images/logo-mark-96.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('images/pwa-icon-192.png') }}">

    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@500;600;700;800;900&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <!-- CSS compilado (Tailwind 4 via Vite) -->
    @vite('resources/css/site.css')
    <style>
        .grid-cols-6 { grid-template-columns: repeat(6, minmax(0, 1fr)); }
        .grid-cols-3 { grid-template-columns: repeat(3, minmax(0, 1fr)); }
        .grid-cols-1 { grid-template-columns: repeat(1, minmax(0, 1fr)); }
        .min-h-\[46px\] { min-height: 46px; }
        .min-h-screen { min-height: 100vh; }
        .flex-grow { flex-grow: 1; }
        @media (min-width: 640px) {
            .sm\:grid-cols-2 { grid-template-columns: repeat(2, minmax(0, 1fr)); }
            .sm\:grid-cols-3 { grid-template-columns: repeat(3, minmax(0, 1fr)); }
            .sm\:pt-28 { padding-top: 7rem; }
            .sm\:p-7 { padding: 1.75rem; }
        }
    </style>

    <!-- Alpine.js -->
    <script defer src="https://cdn.jsdelivr.net/npm/@alpinejs/collapse@3.x.x/dist/cdn.min.js"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
</head>
<body class="bg-dark-950 text-slate-100 font-sans antialiased overflow-x-hidden min-h-screen flex flex-col justify-between"
      x-data="examMirrorApp()">

    <!-- Fundo de Grade e Luzes Neon Sutis -->
    <div class="fixed inset-0 bg-[linear-gradient(to_right,#1f2937_1px,transparent_1px),linear-gradient(to_bottom,#1f2937_1px,transparent_1px)] bg-[size:4rem_4rem] [mask-image:radial-gradient(ellipse_60%_50%_at_50%_0%,#000_70%,transparent_100%)] opacity-20 pointer-events-none"></div>
    <div class="absolute -top-40 -left-40 w-96 h-96 bg-brand-indigo/15 rounded-full blur-[130px] pointer-events-none"></div>
    <div class="absolute top-1/3 -right-40 w-[500px] h-[500px] bg-brand-teal/10 rounded-full blur-[150px] pointer-events-none"></div>

    @include('partials.site-nav')

    <main id="conteudo" class="relative z-10 flex-grow pt-24 sm:pt-28 pb-16 px-4 sm:px-6">
        <div class="max-w-4xl mx-auto space-y-6 sm:space-y-7">

            <!-- Cabeçalho com Alto Contraste -->
            <div class="text-center space-y-3">
                <div class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full bg-brand-teal/10 border border-brand-teal/30 text-brand-teal text-xs font-bold uppercase tracking-wider font-outfit shadow-sm">
                    <span class="w-2 h-2 rounded-full bg-brand-teal animate-pulse"></span>
                    <span>🔒 Acesso Seguro Zero-Knowledge · LGPD</span>
                </div>
                <h1 class="font-outfit font-black text-2xl sm:text-4xl text-white tracking-tight leading-tight">
                    Devolutivas de Trabalhos &amp; Avaliações
                </h1>
                <p class="text-xs sm:text-sm text-slate-300 font-normal max-w-2xl mx-auto leading-relaxed">
                    Consulte sua nota detalhada, feedback pedagógico de trabalhos práticos e o espelho de correção de avaliações.
                    <span class="text-slate-100 font-semibold block mt-1">Nenhum dado pessoal fica salvo no banco deste servidor: sua consulta é processada em memória com privacidade total.</span>
                </p>
            </div>

            <!-- DISCLAIMER OBRIGATÓRIO: NOTA OFICIAL E FINALIDADE EDUCACIONAL -->
            <div class="p-4 sm:p-5 rounded-2xl bg-amber-500/10 border border-amber-500/35 text-amber-100 text-xs sm:text-sm flex items-start gap-3.5 shadow-xl shadow-amber-950/20 backdrop-blur-sm">
                <span class="text-xl leading-none mt-0.5 shrink-0">⚠️</span>
                <div class="space-y-1">
                    <strong class="font-outfit font-black text-amber-300 text-sm tracking-wide block uppercase">
                        Aviso Importante · Nota Oficial &amp; Finalidade Educacional
                    </strong>
                    <p class="leading-relaxed text-amber-200/95 font-normal">
                        A <strong>nota oficial e definitiva</strong> é exclusivamente aquela lançada na <strong>Área do Aluno</strong> da universidade e sempre deve ser consultada por lá.
                        Este site possui finalidade <strong>meramente educacional e formativa</strong>, disponibilizado pelo docente para consulta pedagógica individual de espelhos de correção, gabaritos comentados e critérios de avaliação.
                    </p>
                </div>
            </div>

            @if ($errors->any())
                <div class="p-4 rounded-2xl bg-rose-950/50 border border-rose-500/40 text-rose-200 text-xs sm:text-sm flex items-start gap-3 shadow-lg shadow-rose-950/30 backdrop-blur-sm">
                    <span class="text-lg leading-none mt-0.5 shrink-0">⚠️</span>
                    <div class="space-y-1 font-medium">
                        @foreach ($errors->all() as $error)
                            <p class="leading-relaxed">{{ $error }}</p>
                        @endforeach
                    </div>
                </div>
            @endif

            @if ($result)
                @php $isTrabalhoResult = (($result['tipo'] ?? '') === 'trabalho'); @endphp

                @if ($isTrabalhoResult)
                    <!-- ============================================================== -->
                    <!-- ESTADO DESBLOQUEADO: DEVOLUTIVA DE TRABALHO ACADÊMICO          -->
                    <!-- ============================================================== -->
                    <div class="space-y-5">
                        <!-- Barra de Contexto Superior -->
                        <div class="flex flex-wrap items-center justify-between gap-3 p-4 rounded-2xl bg-dark-900/90 border border-white/10 shadow-xl shadow-black/40 backdrop-blur-sm">
                            <div class="flex items-center gap-3 min-w-0">
                                <span class="w-10 h-10 rounded-xl bg-brand-teal/15 border border-brand-teal/30 flex items-center justify-center text-brand-teal font-bold text-lg shrink-0">📁</span>
                                <div class="min-w-0">
                                    <p class="text-xs sm:text-sm font-bold text-white truncate">
                                        {{ $result['disciplina'] }} · {{ $result['avaliacao'] }}
                                    </p>
                                    <p class="text-xs text-slate-400 font-medium truncate">
                                        Turma {{ $result['turma'] }} · Semestre {{ $result['semestre'] ?? '2026.2' }}
                                    </p>
                                </div>
                            </div>
                            <a href="{{ route('exam.mirror', ['ano' => $selectedYear, 'avaliacao' => $selectedPackage]) }}"
                               class="w-full sm:w-auto text-center px-4 py-2 rounded-xl bg-white/5 hover:bg-white/10 border border-white/10 text-xs font-bold text-slate-200 transition active:scale-95 shadow-sm">
                                ← Consultar outra atividade
                            </a>
                        </div>

                        <!-- Card de Identificação do Projeto / Grupo -->
                        <div class="p-5 sm:p-6 rounded-2xl bg-dark-900/90 border border-white/10 shadow-xl shadow-black/40 backdrop-blur-sm flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                            <div>
                                <span class="text-xs uppercase tracking-wider font-extrabold text-brand-teal">Projeto / Modelo Avaliado</span>
                                <h2 class="font-outfit font-black text-xl sm:text-2xl text-white mt-1">
                                    {{ $result['projeto_grupo'] ?? 'Projeto Prático' }}
                                </h2>
                                <p class="text-xs text-slate-400 font-medium mt-1">
                                    Turma {{ $result['turma'] }} · Devolutiva oficial da etapa A2
                                </p>
                            </div>
                            <span class="inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-xl bg-emerald-500/15 border border-emerald-500/30 text-emerald-300 text-xs font-black shrink-0 self-start sm:self-auto shadow-sm">
                                <span>✓</span> Devolutiva Concluída
                            </span>
                        </div>

                        <!-- DISCLAIMER EDUCACIONAL (RESULTADO TRABALHO) -->
                        <div class="p-3.5 sm:p-4 rounded-xl bg-amber-500/10 border border-amber-500/30 text-amber-100 text-xs sm:text-sm flex items-start gap-2.5 shadow-md">
                            <span class="text-base shrink-0 mt-0.5">⚠️</span>
                            <p class="leading-relaxed font-normal">
                                <strong class="text-amber-300 font-bold">Lembrete Institucional:</strong> A nota oficial e definitiva é exclusivamente a que será lançada na <strong>Área do Aluno</strong> da universidade e sempre deve ser consultada por lá. As informações disponibilizadas neste portal têm finalidade <strong>meramente educacional e formativa</strong>.
                            </p>
                        </div>

                        <!-- Cards de Resumo de Notas (Trabalho) -->
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3.5">
                            <!-- Card 1: Média Final A2 -->
                            <div class="p-5 rounded-2xl bg-gradient-to-br from-brand-teal/20 via-dark-900 to-dark-950 border border-brand-teal/40 shadow-xl shadow-black/40 flex flex-col justify-between">
                                <span class="text-xs uppercase tracking-wider font-bold text-brand-teal">Média Final da Etapa (A2)</span>
                                <div class="mt-2.5 flex items-baseline gap-1.5">
                                    <span class="font-outfit font-black text-3xl sm:text-4xl text-white">
                                        {{ number_format((float) $result['nota_final'], 2, ',', '.') }}
                                    </span>
                                    <span class="text-xs sm:text-sm text-slate-400 font-bold">
                                        / {{ number_format((float) $result['max_final'], 2, ',', '.') }} pts
                                    </span>
                                </div>
                                <p class="mt-1.5 text-xs text-slate-400 font-medium">
                                    Trabalho Prático (4,0) + P-BB Online (1,0)
                                </p>
                            </div>

                            <!-- Card 2: Nota do Trabalho -->
                            <div class="p-5 rounded-2xl bg-dark-900/90 border border-white/10 shadow-xl shadow-black/40 flex flex-col justify-between">
                                <span class="text-xs uppercase tracking-wider font-bold text-slate-300">Trabalho Escrito / Projeto</span>
                                <div class="mt-2.5 flex items-baseline gap-2">
                                    <span class="font-outfit font-black text-2xl sm:text-3xl text-white">
                                        {{ number_format((float) $result['nota_trabalho'], 2, ',', '.') }}
                                    </span>
                                    <span class="text-xs text-slate-400 font-bold">
                                        / {{ number_format((float) $result['max_trabalho'], 2, ',', '.') }} pts
                                    </span>
                                </div>
                                <p class="mt-1.5 text-xs text-brand-teal font-medium">
                                    Proposta de Valor, Canvas e 5W2H
                                </p>
                            </div>

                            <!-- Card 3: Avaliação Online (P-BB) -->
                            <div class="p-5 rounded-2xl bg-dark-900/90 border border-white/10 shadow-xl shadow-black/40 flex flex-col justify-between">
                                <span class="text-xs uppercase tracking-wider font-bold text-slate-300">Avaliação Teórica (P-BB)</span>
                                <div class="mt-2.5 flex items-baseline gap-2">
                                    <span class="font-outfit font-black text-2xl sm:text-3xl text-white">
                                        {{ number_format((float) $result['nota_pbb'], 2, ',', '.') }}
                                    </span>
                                    <span class="text-xs text-slate-400 font-bold">
                                        / {{ number_format((float) $result['max_pbb'], 2, ',', '.') }} pt
                                    </span>
                                </div>
                                <p class="mt-1.5 text-xs text-slate-400 font-medium">
                                    Avaliação online no Blackboard
                                </p>
                            </div>
                        </div>

                        <!-- Seção dos Critérios Avaliativos do Trabalho -->
                        @if (!empty($result['criterios']))
                            <section class="bg-dark-900/90 rounded-2xl p-5 sm:p-6 border border-white/10 shadow-xl shadow-black/40 backdrop-blur-sm space-y-4">
                                <div class="border-b border-white/10 pb-3">
                                    <span class="text-xs font-bold uppercase tracking-wider text-brand-teal">Critérios Oficiais do Plano de Ensino</span>
                                    <h3 class="font-outfit font-black text-lg text-white mt-0.5">Detalhamento dos Critérios do Trabalho</h3>
                                </div>
                                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3.5">
                                    @foreach ($result['criterios'] as $crit)
                                        <div class="p-4 rounded-xl bg-dark-950/80 border border-white/10 flex flex-col justify-between space-y-3">
                                            <div>
                                                <div class="flex items-center justify-between gap-2 mb-1.5">
                                                    <span class="text-xs font-bold text-white">{{ $crit['criterio'] }}</span>
                                                    <span class="text-xs font-black text-brand-teal px-2 py-0.5 rounded-lg bg-brand-teal/15 border border-brand-teal/30 shrink-0">
                                                        {{ number_format((float) $crit['nota'], 2, ',', '.') }} / {{ number_format((float) $crit['max'], 2, ',', '.') }}
                                                    </span>
                                                </div>
                                                <p class="text-xs text-slate-300 font-normal leading-relaxed">
                                                    {{ $crit['descricao'] ?? '' }}
                                                </p>
                                            </div>
                                            <!-- Barra de progresso visível -->
                                            @php $pct = min(100, max(0, round(($crit['nota'] / max(0.1, $crit['max'])) * 100))); @endphp
                                            <div class="w-full bg-slate-800 rounded-full h-2 overflow-hidden">
                                                <div class="bg-gradient-to-r from-brand-teal to-teal-400 h-full rounded-full transition-all duration-500" style="width: {{ $pct }}%"></div>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </section>
                        @endif

                        <!-- Seção de Feedback Formativo do Professor (Pontos Fortes e Pontos a Melhorar) -->
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <!-- Pontos Fortes -->
                            <div class="p-5 sm:p-6 rounded-2xl bg-emerald-950/25 border border-emerald-500/30 text-emerald-100 space-y-3 shadow-xl shadow-black/20 backdrop-blur-sm">
                                <div class="flex items-center gap-2 text-xs font-bold uppercase tracking-wider text-emerald-400">
                                    <span class="text-base">🌟</span> Destaques Positivos &amp; Pontos Fortes
                                </div>
                                @if (!empty($result['pontos_fortes']))
                                    <ul class="space-y-2.5 text-xs sm:text-sm text-emerald-200/90 leading-relaxed font-normal">
                                        @foreach ($result['pontos_fortes'] as $pf)
                                            <li class="flex items-start gap-2">
                                                <span class="text-emerald-400 font-bold shrink-0 mt-0.5">✓</span>
                                                <span>{{ $pf }}</span>
                                            </li>
                                        @endforeach
                                    </ul>
                                @else
                                    <p class="text-xs text-emerald-300/80 font-medium">Trabalho com boa articulação dos conceitos exigidos.</p>
                                @endif
                            </div>

                            <!-- Oportunidades de Melhoria -->
                            <div class="p-5 sm:p-6 rounded-2xl bg-amber-950/25 border border-amber-500/30 text-amber-100 space-y-3 shadow-xl shadow-black/20 backdrop-blur-sm">
                                <div class="flex items-center gap-2 text-xs font-bold uppercase tracking-wider text-amber-400">
                                    <span class="text-base">💡</span> Recomendações &amp; Oportunidades de Melhoria
                                </div>
                                @if (!empty($result['pontos_falhos']))
                                    <ul class="space-y-2.5 text-xs sm:text-sm text-amber-200/90 leading-relaxed font-normal">
                                        @foreach ($result['pontos_falhos'] as $pfalha)
                                            <li class="flex items-start gap-2">
                                                <span class="text-amber-400 font-bold shrink-0 mt-0.5">→</span>
                                                <span>{{ $pfalha }}</span>
                                            </li>
                                        @endforeach
                                    </ul>
                                @else
                                    <p class="text-xs text-amber-300/80 font-medium">Sem falhas estruturais identificadas. Excelente execução!</p>
                                @endif
                            </div>
                        </div>
                    </div>

                @else
                    <!-- ============================================================== -->
                    <!-- ESTADO DESBLOQUEADO: ESPELHO DE AVALIAÇÃO / PROVA ÓPTICA       -->
                    <!-- ============================================================== -->
                    <div class="space-y-5" x-data="{ filterObj: 'all', showRubric: false }">

                        <!-- Barra Superior -->
                        <div class="flex flex-wrap items-center justify-between gap-3 p-4 rounded-2xl bg-dark-900/90 border border-white/10 shadow-xl shadow-black/40 backdrop-blur-sm">
                            <div class="flex items-center gap-3 min-w-0">
                                <span class="w-10 h-10 rounded-xl bg-brand-teal/15 border border-brand-teal/30 flex items-center justify-center text-brand-teal font-bold text-lg shrink-0">📝</span>
                                <div class="min-w-0">
                                    <p class="text-xs sm:text-sm font-bold text-white truncate">
                                        {{ $result['disciplina'] }} · {{ $result['avaliacao'] }}
                                    </p>
                                    <p class="text-xs text-slate-400 font-medium truncate">
                                        {{ $result['turma'] }} ({{ $result['data_prova'] }}) · {{ $result['versao_label'] }}
                                    </p>
                                </div>
                            </div>
                            <a href="{{ route('exam.mirror', ['ano' => $selectedYear, 'avaliacao' => $selectedPackage, 'turma' => $selectedOferta, 'versao' => $selectedVersao]) }}"
                               class="w-full sm:w-auto text-center px-4 py-2 rounded-xl bg-white/5 hover:bg-white/10 border border-white/10 text-xs font-bold text-slate-200 transition active:scale-95 shadow-sm">
                                ← Consultar outra avaliação
                            </a>
                        </div>

                        <!-- DISCLAIMER EDUCACIONAL (RESULTADO PROVA) -->
                        <div class="p-3.5 sm:p-4 rounded-xl bg-amber-500/10 border border-amber-500/30 text-amber-100 text-xs sm:text-sm flex items-start gap-2.5 shadow-md">
                            <span class="text-base shrink-0 mt-0.5">⚠️</span>
                            <p class="leading-relaxed font-normal">
                                <strong class="text-amber-300 font-bold">Lembrete Institucional:</strong> A nota oficial e definitiva é exclusivamente a que será lançada na <strong>Área do Aluno</strong> da universidade e sempre deve ser consultada por lá. As informações disponibilizadas neste portal têm finalidade <strong>meramente educacional e formativa</strong>.
                            </p>
                        </div>

                        <!-- Observação de Sala Especial / Aluno em Trânsito -->
                        @if (!empty($result['observacao']))
                            <div class="p-4 rounded-xl bg-sky-950/35 border border-sky-500/40 text-sky-200 text-xs sm:text-sm flex items-start gap-3 shadow-md">
                                <span class="text-base shrink-0 mt-0.5">ℹ️</span>
                                <div>
                                    <strong class="font-bold text-sky-300 uppercase tracking-wide text-xs block mb-0.5">Registro Especial / Aluno em Trânsito</strong>
                                    <p class="leading-relaxed text-slate-200 font-medium">{{ $result['observacao'] }}</p>
                                </div>
                            </div>
                        @endif

                        <!-- Feedback Geral Pedagógico -->
                        @if (!empty($result['feedback_geral']))
                            <div class="p-4 sm:p-5 rounded-2xl bg-teal-950/30 border border-brand-teal/40 space-y-1.5 shadow-md">
                                <div class="text-xs font-bold uppercase tracking-wider text-brand-teal flex items-center gap-1.5">
                                    <span>📝</span> Comentário Geral &amp; Feedback Formativo
                                </div>
                                <p class="text-xs sm:text-sm text-slate-200 font-medium leading-relaxed">
                                    {{ $result['feedback_geral'] }}
                                </p>
                            </div>
                        @endif

                        <!-- Cards de Resumo de Pontuação (Avaliação) -->
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3.5">
                            <!-- Card 1: Nota Final -->
                            <div class="p-5 rounded-2xl bg-gradient-to-br from-brand-teal/20 via-dark-900 to-dark-950 border border-brand-teal/40 shadow-xl shadow-black/40 flex flex-col justify-between">
                                <span class="text-xs uppercase tracking-wider font-bold text-brand-teal">Nota Total na Avaliação</span>
                                <div class="mt-2.5 flex items-baseline gap-1.5">
                                    <span class="font-outfit font-black text-3xl sm:text-4xl text-white">
                                        {{ number_format((float) $result['nota_final'], 2, ',', '.') }}
                                    </span>
                                    <span class="text-xs sm:text-sm text-slate-400 font-bold">
                                        / {{ number_format((float) $result['max_final'], 2, ',', '.') }} pts
                                    </span>
                                </div>
                                <p class="mt-1.5 text-xs text-slate-400 font-medium">
                                    Escala oficial (0 a {{ number_format((float) $result['max_final'], 2, ',', '.') }})
                                </p>
                            </div>

                            @if (!empty($result['tem_discursiva']))
                                <!-- Card 2: Questões Objetivas (Com Discursiva) -->
                                <div class="p-5 rounded-2xl bg-dark-900/90 border border-white/10 shadow-xl shadow-black/40 flex flex-col justify-between">
                                    <span class="text-xs uppercase tracking-wider font-bold text-slate-300">Questões Objetivas</span>
                                    <div class="mt-2.5 flex items-baseline gap-2">
                                        <span class="font-outfit font-black text-2xl sm:text-3xl text-white">
                                            {{ number_format((float) $result['nota_obj'], 2, ',', '.') }}
                                        </span>
                                        <span class="text-xs text-slate-400 font-bold">
                                            / {{ number_format((float) $result['max_obj'], 2, ',', '.') }} {{ (float) $result['max_obj'] > 1.0 ? 'pts' : 'pt' }}
                                        </span>
                                    </div>
                                    <p class="mt-1.5 text-xs text-brand-teal font-medium">
                                        {{ $result['acertos_obj'] }} de {{ $result['total_obj'] }} questões corretas ({{ round(($result['acertos_obj'] / max(1, $result['total_obj'])) * 100) }}%)
                                    </p>
                                </div>

                                <!-- Card 3: Questão Discursiva -->
                                @if (!empty($result['discursiva']))
                                    @php $disc = $result['discursiva']; @endphp
                                    <div class="p-5 rounded-2xl bg-dark-900/90 border border-white/10 shadow-xl shadow-black/40 flex flex-col justify-between">
                                        <span class="text-xs uppercase tracking-wider font-bold text-slate-300">Questão Discursiva</span>
                                        <div class="mt-2.5 flex items-baseline gap-2">
                                            <span class="font-outfit font-black text-2xl sm:text-3xl text-white">
                                                {{ number_format((float) $disc['nota_total'], 2, ',', '.') }}
                                            </span>
                                            <span class="text-xs text-slate-400 font-bold">
                                                / {{ number_format((float) $disc['max_total'], 2, ',', '.') }} {{ (float) $disc['max_total'] > 1.0 ? 'pts' : 'pt' }}
                                            </span>
                                        </div>
                                        <div class="mt-2 flex flex-wrap gap-1.5 text-xs font-bold">
                                            <span class="px-2 py-0.5 rounded-md bg-dark-950 border border-white/10 text-slate-300">
                                                a) {{ number_format((float) ($disc['nota_a'] ?? 0), 2, ',', '.') }}/0,25
                                            </span>
                                            <span class="px-2 py-0.5 rounded-md bg-dark-950 border border-white/10 text-slate-300">
                                                b) {{ number_format((float) ($disc['nota_b'] ?? 0), 2, ',', '.') }}/0,55
                                            </span>
                                            <span class="px-2 py-0.5 rounded-md bg-dark-950 border border-white/10 text-slate-300">
                                                c) {{ number_format((float) ($disc['nota_c'] ?? 0), 2, ',', '.') }}/0,20
                                            </span>
                                        </div>
                                    </div>
                                @endif
                            @else
                                <!-- Card 2: Aproveitamento Objetivo (Sem Discursiva) -->
                                <div class="p-5 rounded-2xl bg-dark-900/90 border border-white/10 shadow-xl shadow-black/40 flex flex-col justify-between">
                                    <span class="text-xs uppercase tracking-wider font-bold text-slate-300">Aproveitamento Objetivo</span>
                                    <div class="mt-2.5 flex items-baseline gap-2">
                                        <span class="font-outfit font-black text-2xl sm:text-3xl text-white">
                                            {{ $result['acertos_obj'] }} / {{ $result['total_obj'] }}
                                        </span>
                                        <span class="text-xs text-brand-teal font-bold uppercase tracking-wider">
                                            acertos
                                        </span>
                                    </div>
                                    <p class="mt-1.5 text-xs text-brand-teal font-medium">
                                        {{ round(($result['acertos_obj'] / max(1, $result['total_obj'])) * 100) }}% de aproveitamento na prova
                                    </p>
                                </div>

                                <!-- Card 3: Caderno / Versão -->
                                <div class="p-5 rounded-2xl bg-dark-900/90 border border-white/10 shadow-xl shadow-black/40 flex flex-col justify-between">
                                    <span class="text-xs uppercase tracking-wider font-bold text-slate-300">Caderno / Versão</span>
                                    <div class="mt-2 font-outfit font-black text-base sm:text-lg text-white leading-snug">
                                        {{ $result['versao_label'] }}
                                    </div>
                                    <p class="mt-1.5 text-xs text-slate-400 font-medium">
                                        {{ $result['turma'] }} · {{ $result['data_prova'] }}
                                    </p>
                                </div>
                            @endif
                        </div>

                        <!-- Seção da Discursiva (quando houver) -->
                        @if (!empty($result['tem_discursiva']) && !empty($result['discursiva']))
                            @php $disc = $result['discursiva']; @endphp
                            <section class="bg-dark-900/90 rounded-2xl p-5 sm:p-6 border border-white/10 shadow-xl shadow-black/40 backdrop-blur-sm space-y-4">
                                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 border-b border-white/10 pb-3">
                                    <div>
                                        <span class="text-xs font-bold uppercase tracking-wider text-brand-teal">Espelho da Questão Discursiva (1,00 pt)</span>
                                        <h2 class="font-outfit font-black text-lg sm:text-xl text-white mt-0.5">
                                            {{ $disc['titulo_caso'] ?? 'Caso Clínico' }}
                                        </h2>
                                    </div>
                                    <span class="inline-flex items-center px-3 py-1 rounded-xl bg-brand-teal/15 border border-brand-teal/30 text-brand-teal font-outfit font-extrabold text-sm w-fit shadow-sm">
                                        Nota: {{ number_format((float) $disc['nota_total'], 2, ',', '.') }} / 1,00 pt
                                    </span>
                                </div>

                                <div class="p-4 rounded-xl bg-amber-950/25 border border-amber-500/30 space-y-1 text-amber-100">
                                    <div class="text-xs font-bold uppercase tracking-wider text-amber-400 flex items-center gap-1.5">
                                        <span>📝</span> Comentário da Correção &amp; Feedback Formativo
                                    </div>
                                    <p class="text-xs sm:text-sm text-slate-200 font-medium leading-relaxed">
                                        {{ $disc['feedback_professor'] ?: 'Sem descontos (pontuação máxima).' }}
                                    </p>
                                </div>

                                @if (!empty($disc['resumo_respostas']))
                                    <div class="space-y-2.5">
                                        <h3 class="text-xs font-bold uppercase tracking-wider text-slate-300">
                                            Síntese da resposta manuscrita avaliada
                                        </h3>
                                        <div class="grid grid-cols-1 gap-2.5 text-xs sm:text-sm">
                                            <div class="p-3.5 rounded-xl bg-dark-950/80 border border-white/10">
                                                <div class="flex items-center justify-between mb-1">
                                                    <span class="font-bold text-brand-teal">Item a) Diagnóstico e achados do caso</span>
                                                    <span class="font-bold text-white">{{ number_format((float) $disc['nota_a'], 2, ',', '.') }} / 0,25 pt</span>
                                                </div>
                                                <p class="text-slate-300 leading-relaxed font-normal">{{ $disc['resumo_respostas']['a'] ?? '—' }}</p>
                                            </div>
                                            <div class="p-3.5 rounded-xl bg-dark-950/80 border border-white/10">
                                                <div class="flex items-center justify-between mb-1">
                                                    <span class="font-bold text-brand-teal">Item b) Prescrição médico-veterinária</span>
                                                    <span class="font-bold text-white">{{ number_format((float) $disc['nota_b'], 2, ',', '.') }} / 0,55 pt</span>
                                                </div>
                                                <p class="text-slate-300 leading-relaxed font-normal">{{ $disc['resumo_respostas']['b'] ?? '—' }}</p>
                                            </div>
                                            <div class="p-3.5 rounded-xl bg-dark-950/80 border border-white/10">
                                                <div class="flex items-center justify-between mb-1">
                                                    <span class="font-bold text-brand-teal">Item c) Manejo ambiental / sanitário</span>
                                                    <span class="font-bold text-white">{{ number_format((float) $disc['nota_c'], 2, ',', '.') }} / 0,20 pt</span>
                                                </div>
                                                <p class="text-slate-300 leading-relaxed font-normal">{{ $disc['resumo_respostas']['c'] ?? '—' }}</p>
                                            </div>
                                        </div>
                                    </div>
                                @endif

                                @if (!empty($disc['rubrica_oficial']))
                                    <div class="border-t border-white/10 pt-3">
                                        <button type="button" @click="showRubric = !showRubric"
                                                class="text-xs font-bold text-brand-teal hover:text-teal-300 flex items-center gap-1.5 transition">
                                            <span x-text="showRubric ? '▲ Ocultar' : '▼ Ver'"></span>
                                            <span>Gabarito Esperado &amp; Rubrica Oficial da Discursiva</span>
                                        </button>
                                        <div x-show="showRubric" x-collapse class="mt-3 p-4 rounded-xl bg-dark-950 border border-white/10 text-xs text-slate-300 space-y-2 whitespace-pre-line leading-relaxed font-normal">
                                            @if (is_array($disc['rubrica_oficial']))
                                                @foreach ($disc['rubrica_oficial'] as $kRub => $vRub)
                                                    <p><strong class="text-brand-teal uppercase">Item {{ $kRub }}):</strong> {{ $vRub }}</p>
                                                @endforeach
                                            @else
                                                {{ $disc['rubrica_oficial'] }}
                                            @endif
                                        </div>
                                    </div>
                                @endif
                            </section>
                        @endif

                        <!-- Seção Questões Objetivas -->
                        <section class="bg-dark-900/90 rounded-2xl p-5 sm:p-6 border border-white/10 shadow-xl shadow-black/40 backdrop-blur-sm space-y-4">
                            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-white/10 pb-3">
                                <div>
                                    <span class="text-xs font-bold uppercase tracking-wider text-brand-teal">Gabarito Comentado</span>
                                    <h2 class="font-outfit font-black text-lg sm:text-xl text-white mt-0.5">
                                        Questões Objetivas ({{ $result['acertos_obj'] }} acertos de {{ $result['total_obj'] }})
                                    </h2>
                                </div>

                                <div class="inline-flex rounded-xl p-1 bg-dark-950 border border-white/10 text-xs font-bold">
                                    <button type="button" @click="filterObj = 'all'"
                                            :class="filterObj === 'all' ? 'bg-white/15 text-white shadow-sm' : 'text-slate-400 hover:text-white'"
                                            class="px-3.5 py-1.5 rounded-lg transition">
                                        Todas ({{ $result['total_obj'] }})
                                    </button>
                                    <button type="button" @click="filterObj = 'correct'"
                                            :class="filterObj === 'correct' ? 'bg-emerald-600 text-white shadow-sm' : 'text-slate-400 hover:text-emerald-400'"
                                            class="px-3.5 py-1.5 rounded-lg transition">
                                        Acertos ({{ $result['acertos_obj'] }})
                                    </button>
                                    <button type="button" @click="filterObj = 'wrong'"
                                            :class="filterObj === 'wrong' ? 'bg-rose-600 text-white shadow-sm' : 'text-slate-400 hover:text-rose-400'"
                                            class="px-3.5 py-1.5 rounded-lg transition">
                                        Erros ({{ $result['total_obj'] - $result['acertos_obj'] }})
                                    </button>
                                </div>
                            </div>

                            <div class="space-y-3">
                                @foreach ($result['itens_objetivos'] as $item)
                                    <article x-show="filterObj === 'all' || (filterObj === 'correct' && {{ $item['acertou'] ? 'true' : 'false' }}) || (filterObj === 'wrong' && {{ !$item['acertou'] ? 'true' : 'false' }})"
                                             class="p-4 rounded-xl border transition-all duration-200 {{ $item['acertou'] ? 'bg-emerald-950/20 border-emerald-500/30' : 'bg-rose-950/20 border-rose-500/30' }}">
                                        <div class="flex flex-wrap items-center justify-between gap-2 mb-2">
                                            <div class="flex items-center gap-2">
                                                <span class="w-7 h-7 rounded-lg flex items-center justify-center font-outfit font-black text-xs {{ $item['acertou'] ? 'bg-emerald-500/20 border border-emerald-500/40 text-emerald-300' : 'bg-rose-500/20 border border-rose-500/40 text-rose-300' }}">
                                                    {{ str_pad($item['q_num'], 2, '0', STR_PAD_LEFT) }}
                                                </span>
                                                <span class="font-bold text-xs sm:text-sm text-white">
                                                    {{ $item['tema'] ?: 'Questão ' . $item['q_num'] }}
                                                </span>
                                            </div>

                                            <div class="flex items-center gap-2 text-xs font-bold">
                                                <span class="text-slate-400">Marcada:</span>
                                                <span class="px-2 py-0.5 rounded-md {{ $item['acertou'] ? 'bg-emerald-500/20 text-emerald-300 border border-emerald-500/30' : 'bg-rose-500/20 text-rose-300 border border-rose-500/30 line-through' }}">
                                                    {{ $item['resposta_aluno'] }}
                                                </span>
                                                <span class="text-slate-400">Gabarito:</span>
                                                <span class="px-2 py-0.5 rounded-md bg-emerald-500/30 text-emerald-200 border border-emerald-500/40">
                                                    {{ $item['gabarito_oficial'] }}
                                                </span>
                                                @if ($item['acertou'])
                                                    <span class="px-2 py-1 rounded-lg bg-emerald-500 text-dark-950 font-black">
                                                        ✓ +{{ number_format((float) $item['pontos'], 2, ',', '.') }}
                                                    </span>
                                                @else
                                                    <span class="px-2 py-1 rounded-lg bg-rose-500/20 text-rose-300 border border-rose-500/30 font-black">
                                                        ✗ 0,00
                                                    </span>
                                                @endif
                                            </div>
                                        </div>

                                        @if (!empty($item['comentario']))
                                            <p class="text-xs sm:text-sm text-slate-300 leading-relaxed pl-3 border-l-2 border-brand-teal/40 mt-2 font-normal">
                                                {{ $item['comentario'] }}
                                            </p>
                                        @endif
                                    </article>
                                @endforeach
                            </div>
                        </section>
                    </div>
                @endif

            @else
                <!-- ============================================================== -->
                <!-- FORMULÁRIO DE ACESSO CRIPTOGRAFADO (ALTO CONTRASTE)            -->
                <!-- ============================================================== -->
                <div class="bg-dark-900/90 rounded-2xl p-5 sm:p-8 border border-white/10 shadow-2xl shadow-black/50 backdrop-blur-sm space-y-6">

                    <!-- SELETOR EM DESTAQUE: ANO / SEMESTRE LETIVO -->
                    <div class="p-4 rounded-xl bg-dark-950/80 border border-white/10 space-y-2">
                        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-1">
                            <label for="ano_filtro" class="text-xs font-bold uppercase tracking-wider text-slate-200 flex items-center gap-1.5">
                                <span>📅</span> Ano Letivo / Semestre
                            </label>
                            <span class="text-xs text-slate-400 font-medium">Selecione o período acadêmico</span>
                        </div>
                        <div class="flex flex-wrap gap-2 pt-1">
                            @foreach ($semesters as $sem)
                                @php $isSemClosed = \App\Http\Controllers\ExamMirrorController::isSemesterClosed($sem); @endphp
                                <button type="button" @click="setSemester('{{ $sem }}')"
                                        :class="currentSemester === '{{ $sem }}'
                                            ? 'bg-gradient-to-r from-brand-teal to-brand-indigo text-white shadow-md shadow-brand-teal/20 font-black border border-transparent'
                                            : 'bg-white/5 text-slate-300 border border-white/10 hover:bg-white/10 hover:text-white font-bold'"
                                        class="px-4 py-2 rounded-xl text-xs sm:text-sm font-outfit transition active:scale-95 flex items-center gap-1.5">
                                    <span>{{ $sem }}</span>
                                    @if ($isSemClosed)
                                        <span class="text-[0.65rem] px-1.5 py-0.5 rounded bg-rose-500/20 text-rose-300 border border-rose-500/30 font-bold">Encerrado</span>
                                    @endif
                                </button>
                            @endforeach
                        </div>
                    </div>

                    <!-- Aviso se o semestre selecionado estiver oficialmente encerrado -->
                    <template x-if="currentPackage() && currentPackage().is_encerrado">
                        <div class="p-4 sm:p-5 rounded-xl bg-rose-950/40 border border-rose-500/40 text-rose-200 text-xs sm:text-sm space-y-1.5 shadow-lg font-medium">
                            <div class="font-bold flex items-center gap-2 text-rose-300 text-sm">
                                <span class="text-base">🔒</span> Visualização Bloqueada — Semestre Encerrado
                            </div>
                            <p class="leading-relaxed text-rose-200/90">
                                O período oficial de consulta aos espelhos e devolutivas deste semestre anterior foi encerrado pelo docente. Conforme o calendário acadêmico, apenas as avaliações do semestre letivo vigente permanecem liberadas para visualização.
                            </p>
                        </div>
                    </template>

                    <form method="POST" action="{{ route('exam.mirror.consult') }}" class="space-y-5">
                        @csrf

                        {{-- Honeypot antibot --}}
                        <div style="display: none !important;" aria-hidden="true">
                            <input type="text" name="secondary_address" tabindex="-1" autocomplete="off">
                        </div>

                        <!-- PASSO 1: Seleção da Atividade -->
                        <div>
                            <label for="package_id" class="block text-xs font-bold uppercase tracking-wider text-slate-200 mb-2">
                                1. Escolha a Atividade ou Disciplina
                            </label>
                            <select id="package_id" name="package_id" x-model="pkgId" @change="onPackageChange()"
                                    class="w-full px-4 py-3 rounded-xl bg-dark-950 border border-slate-700 text-white text-sm sm:text-base font-bold focus:outline-none focus:border-brand-teal focus:ring-2 focus:ring-brand-teal/20 shadow-inner">
                                @foreach ($catalog as $pkgKey => $pkgItem)
                                    <option value="{{ $pkgKey }}" x-show="!currentSemester || '{{ $pkgItem['semestre'] }}' === currentSemester" class="bg-dark-950 text-white">
                                        {{ $pkgItem['display_label'] ?? ((($pkgItem['tipo'] ?? '') === 'trabalho' ? '📁 [TRABALHO] ' : '📝 [AVALIAÇÃO] ') . ($pkgItem['disciplina'] ?? '') . ($pkgItem['campus'] ? ' (' . $pkgItem['campus'] . ')' : '') . ' — ' . ($pkgItem['avaliacao'] ?? '')) }}
                                    </option>
                                @endforeach
                            </select>
                            <div class="mt-2 flex items-center justify-between gap-2">
                                <p class="text-xs text-slate-400 font-medium" x-text="currentPackage()?.descricao_escala || ''"></p>
                                <span class="px-2.5 py-1 rounded-md text-xs font-bold shrink-0 uppercase tracking-wider"
                                      :class="isTrabalho() ? 'bg-amber-500/15 text-amber-300 border border-amber-500/30' : 'bg-brand-teal/15 text-brand-teal border border-brand-teal/30'"
                                      x-text="isTrabalho() ? 'Trabalho Acadêmico' : 'Avaliação / Prova Óptica'">
                                </span>
                            </div>
                        </div>

                        <!-- PASSO 2: Turma / Turno (Sem códigos técnicos) -->
                        <div class="grid grid-cols-1 gap-3.5" :class="isTrabalho() ? '' : 'sm:grid-cols-2'">
                            <div>
                                <label for="oferta" class="block text-xs font-bold uppercase tracking-wider text-slate-200 mb-2">
                                    2. Sua Turma / Turno
                                </label>
                                <select id="oferta" name="oferta" x-model="oferta" @change="onOfertaChange()"
                                        class="w-full px-3.5 py-3 rounded-xl bg-dark-950 border border-slate-700 text-white text-sm font-bold focus:outline-none focus:border-brand-teal focus:ring-2 focus:ring-brand-teal/20 shadow-inner">
                                    <template x-for="(tObj, tKey) in (currentPackage()?.turmas || {})" :key="tKey">
                                        <option :value="tKey" class="bg-dark-950 text-white"
                                                x-text="tObj.turma + (tObj.data ? ' (' + tObj.data + ')' : '') + (tObj.status === 'publicada' ? '' : ' — Aguardando')">
                                        </option>
                                    </template>
                                </select>
                            </div>

                            <template x-if="!isTrabalho()">
                                <div>
                                    <label for="versao" class="block text-xs font-bold uppercase tracking-wider text-slate-200 mb-2">
                                        Versão / Animal da Prova
                                    </label>
                                    <select id="versao" name="versao" x-model="versao"
                                            class="w-full px-3.5 py-3 rounded-xl bg-dark-950 border border-slate-700 text-white text-sm font-bold focus:outline-none focus:border-brand-teal focus:ring-2 focus:ring-brand-teal/20 shadow-inner">
                                        <template x-for="(vObj, vKey) in (currentTurma()?.versoes || {})" :key="vKey">
                                            <option :value="vKey" class="bg-dark-950 text-white" x-text="vObj.label"></option>
                                        </template>
                                    </select>
                                </div>
                            </template>
                        </div>

                        <!-- PASSO 3: RGM -->
                        <div>
                            <label for="rgm" class="block text-xs font-bold uppercase tracking-wider text-slate-200 mb-2">
                                3. Seu RGM / Matrícula (apenas números)
                            </label>
                            <input type="text" id="rgm" name="rgm" required
                                   inputmode="numeric" pattern="[0-9]*" autocomplete="off"
                                   value="{{ old('rgm') }}"
                                   placeholder="Digite os números do seu RGM"
                                   class="w-full px-4 py-3 rounded-xl bg-dark-950 border border-slate-700 text-white text-base font-bold tracking-wider placeholder-slate-500 focus:outline-none focus:border-brand-teal focus:ring-2 focus:ring-brand-teal/20 shadow-inner">
                        </div>

                        <!-- PASSO 4 CONDICIONAL: TRABALHO -> PRIMEIRO NOME -->
                        <template x-if="isTrabalho()">
                            <div class="space-y-2 p-4 rounded-xl bg-brand-teal/10 border border-brand-teal/30 shadow-sm">
                                <label for="primeiro_nome" class="block text-xs font-bold uppercase tracking-wider text-brand-teal">
                                    4. Validação de Segurança: Seu Primeiro Nome
                                </label>
                                <p class="text-xs text-slate-300 leading-relaxed font-normal">
                                    Para proteger seu resultado sem senhas (LGPD), digite seu <strong>primeiro nome</strong> como consta na chamada oficial da faculdade (ex.: se seu nome for <em>Ana Carolina</em>, digite <em>Ana</em>).
                                </p>
                                <input type="text" id="primeiro_nome" name="primeiro_nome"
                                       value="{{ old('primeiro_nome') }}"
                                       autocomplete="off"
                                       placeholder="Digite seu primeiro nome (ex.: Maria, Lucas, Abigail)"
                                       class="w-full px-4 py-3 rounded-xl bg-dark-950 border border-slate-700 text-white text-base font-bold placeholder-slate-500 focus:outline-none focus:border-brand-teal focus:ring-2 focus:ring-brand-teal/20 shadow-inner">
                            </div>
                        </template>

                        <!-- PASSO 4 CONDICIONAL: AVALIAÇÃO -> Q1, Q2, Q3 -->
                        <template x-if="!isTrabalho()">
                            <div class="space-y-3 pt-1">
                                <div class="border-b border-white/10 pb-2">
                                    <span class="block text-xs font-bold uppercase tracking-wider text-slate-200">
                                        4. Validação da sua Folha: Respostas das Questões 1, 2 e 3
                                    </span>
                                    <p class="text-xs text-slate-400 mt-0.5 font-medium">
                                        Toque nas alternativas que você assinalou na sua folha de respostas:
                                    </p>
                                </div>

                                <!-- Seletor Q1 -->
                                <div class="p-3.5 rounded-xl bg-dark-950/80 border border-white/10 space-y-2">
                                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-1">
                                        <span class="text-xs sm:text-sm font-bold text-white">Questão 01</span>
                                        <span class="text-xs text-brand-teal font-bold"
                                              x-text="getHint(0) || 'Selecione a alternativa marcada na Q1'"></span>
                                    </div>
                                    <input type="hidden" name="q1" :value="q1">
                                    <div class="grid grid-cols-6 gap-1.5 sm:gap-2">
                                        @foreach (['A' => 'A', 'B' => 'B', 'C' => 'C', 'D' => 'D', 'E' => 'E', 'X' => 'Branco'] as $val => $lbl)
                                            <button type="button" @click="q1 = '{{ $val }}'"
                                                    :class="q1 === '{{ $val }}'
                                                        ? 'bg-brand-teal text-dark-950 font-black border-brand-teal shadow-lg shadow-brand-teal/25 scale-[1.02]'
                                                        : 'bg-dark-900 border-slate-700 text-slate-300 hover:bg-slate-800 hover:text-white hover:border-slate-500 font-bold shadow-sm'"
                                                    class="min-h-[46px] rounded-xl border font-outfit text-xs sm:text-sm transition active:scale-95 flex items-center justify-center">
                                                {{ $lbl }}
                                            </button>
                                        @endforeach
                                    </div>
                                </div>

                                <!-- Seletor Q2 -->
                                <div class="p-3.5 rounded-xl bg-dark-950/80 border border-white/10 space-y-2">
                                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-1">
                                        <span class="text-xs sm:text-sm font-bold text-white">Questão 02</span>
                                        <span class="text-xs text-brand-teal font-bold"
                                              x-text="getHint(1) || 'Selecione a alternativa marcada na Q2'"></span>
                                    </div>
                                    <input type="hidden" name="q2" :value="q2">
                                    <div class="grid grid-cols-6 gap-1.5 sm:gap-2">
                                        @foreach (['A' => 'A', 'B' => 'B', 'C' => 'C', 'D' => 'D', 'E' => 'E', 'X' => 'Branco'] as $val => $lbl)
                                            <button type="button" @click="q2 = '{{ $val }}'"
                                                    :class="q2 === '{{ $val }}'
                                                        ? 'bg-brand-teal text-dark-950 font-black border-brand-teal shadow-lg shadow-brand-teal/25 scale-[1.02]'
                                                        : 'bg-dark-900 border-slate-700 text-slate-300 hover:bg-slate-800 hover:text-white hover:border-slate-500 font-bold shadow-sm'"
                                                    class="min-h-[46px] rounded-xl border font-outfit text-xs sm:text-sm transition active:scale-95 flex items-center justify-center">
                                                {{ $lbl }}
                                            </button>
                                        @endforeach
                                    </div>
                                </div>

                                <!-- Seletor Q3 -->
                                <div class="p-3.5 rounded-xl bg-dark-950/80 border border-white/10 space-y-2">
                                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-1">
                                        <span class="text-xs sm:text-sm font-bold text-white">Questão 03</span>
                                        <span class="text-xs text-brand-teal font-bold"
                                              x-text="getHint(2) || 'Selecione a alternativa marcada na Q3'"></span>
                                    </div>
                                    <input type="hidden" name="q3" :value="q3">
                                    <div class="grid grid-cols-6 gap-1.5 sm:gap-2">
                                        @foreach (['A' => 'A', 'B' => 'B', 'C' => 'C', 'D' => 'D', 'E' => 'E', 'X' => 'Branco'] as $val => $lbl)
                                            <button type="button" @click="q3 = '{{ $val }}'"
                                                    :class="q3 === '{{ $val }}'
                                                        ? 'bg-brand-teal text-dark-950 font-black border-brand-teal shadow-lg shadow-brand-teal/25 scale-[1.02]'
                                                        : 'bg-dark-900 border-slate-700 text-slate-300 hover:bg-slate-800 hover:text-white hover:border-slate-500 font-bold shadow-sm'"
                                                    class="min-h-[46px] rounded-xl border font-outfit text-xs sm:text-sm transition active:scale-95 flex items-center justify-center">
                                                {{ $lbl }}
                                            </button>
                                        @endforeach
                                    </div>
                                </div>
                            </div>
                        </template>

                        <!-- Botão de Desbloqueio -->
                        <button type="submit"
                                :disabled="currentPackage() && currentPackage().is_encerrado"
                                :class="currentPackage() && currentPackage().is_encerrado
                                    ? 'opacity-40 cursor-not-allowed bg-slate-800 text-slate-500 border border-slate-700'
                                    : 'bg-gradient-to-r from-brand-teal to-brand-indigo hover:brightness-110 text-white shadow-xl shadow-brand-teal/25 active:scale-[0.99]'"
                                class="w-full py-4 rounded-xl font-outfit font-black text-base transition flex items-center justify-center gap-2">
                            <span x-text="currentPackage() && currentPackage().is_encerrado
                                ? '🔒 Visualização Encerrada pelo Docente'
                                : (isTrabalho() ? '🔓 Desbloquear Devolutiva do Trabalho' : '🔓 Desbloquear Meu Espelho & Gabarito')"></span>
                        </button>
                    </form>

                    <!-- Nota de Conformidade LGPD -->
                    <div class="pt-3 border-t border-white/10 flex items-start gap-2.5 text-xs text-slate-400 font-normal leading-relaxed">
                        <span class="text-sm shrink-0">🛡️</span>
                        <p>
                            <strong class="text-slate-200 font-bold">Conformidade LGPD (Lei nº 13.709/2018 — Art. 12):</strong>
                            Os dados deste sistema utilizam anonimização e chave derivada irreversível (HMAC-SHA256 + AES-256-CBC).
                            Nenhum nome civil, e-mail ou matrícula RGM em texto aberto é armazenado no banco de dados deste portal.
                        </p>
                    </div>
                </div>
            @endif

        </div>
    </main>

    @include('partials.site-footer')

    <script>
        function examMirrorApp() {
            const catalog = @json($catalog);
            const initialSemester = @json($selectedSemester);
            const initialPkg = @json(old('package_id', $selectedPackage));
            const initialOferta = @json(old('oferta', $selectedOferta));
            const initialVersao = @json(old('versao', $selectedVersao ?: 'A'));

            return {
                mobileMenu: false,
                catalog: catalog,
                currentSemester: initialSemester || '2026.2',
                pkgId: initialPkg || Object.keys(catalog)[0] || '',
                oferta: initialOferta || '',
                versao: initialVersao || 'A',
                q1: @json(old('q1', $queriedAnswers['q1'] ?? 'A')),
                q2: @json(old('q2', $queriedAnswers['q2'] ?? 'A')),
                q3: @json(old('q3', $queriedAnswers['q3'] ?? 'A')),

                init() {
                    this.ensureValidSelection();
                },
                setSemester(sem) {
                    this.currentSemester = sem;
                    const filtered = this.filteredPackages();
                    const keys = Object.keys(filtered);
                    if (!filtered[this.pkgId] && keys.length > 0) {
                        this.pkgId = keys[0];
                        this.onPackageChange();
                    }
                },
                filteredPackages() {
                    const result = {};
                    for (const [k, v] of Object.entries(this.catalog)) {
                        if (!this.currentSemester || v.semestre === this.currentSemester || v.ano === this.currentSemester.split('.')[0]) {
                            result[k] = v;
                        }
                    }
                    return Object.keys(result).length > 0 ? result : this.catalog;
                },
                currentPackage() {
                    return this.catalog[this.pkgId] || null;
                },
                isTrabalho() {
                    return this.currentPackage()?.tipo === 'trabalho';
                },
                currentTurma() {
                    const p = this.currentPackage();
                    return (p && p.turmas && p.turmas[this.oferta]) ? p.turmas[this.oferta] : null;
                },
                ensureValidSelection() {
                    const p = this.currentPackage();
                    if (!p) {
                        const keys = Object.keys(this.filteredPackages());
                        if (keys.length > 0) {
                            this.pkgId = keys[0];
                        }
                    }
                    const turmas = this.currentPackage()?.turmas || {};
                    if (!turmas[this.oferta]) {
                        this.oferta = Object.keys(turmas)[0] || '';
                    }
                    const versoes = this.currentTurma()?.versoes || {};
                    if (!versoes[this.versao]) {
                        this.versao = Object.keys(versoes)[0] || 'A';
                    }
                },
                onPackageChange() {
                    const turmas = this.currentPackage()?.turmas || {};
                    this.oferta = Object.keys(turmas)[0] || '';
                    this.onOfertaChange();
                },
                onOfertaChange() {
                    const versoes = this.currentTurma()?.versoes || {};
                    this.versao = Object.keys(versoes)[0] || 'A';
                },
                getHint(idx) {
                    const t = this.currentTurma();
                    if (!t || t.status !== 'publicada') return '';
                    const v = t.versoes?.[this.versao];
                    return (v && Array.isArray(v.q_hints) && v.q_hints[idx]) ? v.q_hints[idx] : '';
                }
            };
        }
    </script>
</body>
</html>
