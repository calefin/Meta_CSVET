<x-filament-panels::page>
    <div class="space-y-6">

        @php
            $cfg = $this->getSystemConfig();
            $activeSem = $cfg['active_semester'] ?? '2026.2';
            $semestersStatus = $cfg['semesters_status'] ?? [];
            $allSemesters = $this->getAvailableSemesters();
            $packages = $this->getPackages();
            $campuses = $this->getCampusesList();
            $turnos = $this->getTurnosList();
            $disciplines = $this->getDisciplinesList();
            $allGabs = $this->getOfficialAnswerKeys();
        @endphp

        <!-- Cabeçalho de Ações Principais do Professor / Administrador -->
        <div class="p-6 rounded-2xl bg-gradient-to-r from-teal-950 via-gray-900 to-indigo-950 border border-teal-500/30 shadow-md flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <div class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-teal-500/15 text-teal-400 font-bold text-xs uppercase tracking-wider mb-2">
                    <span>🛡️</span> Zero-Knowledge · LGPD (Sem Armazenamento de Dados Pessoais)
                </div>
                <h2 class="text-xl sm:text-2xl font-black text-white tracking-tight">
                    Gestão de Devolutivas &amp; Espelhos de Prova
                </h2>
                <p class="text-xs text-gray-300 mt-1 max-w-2xl leading-relaxed">
                    Navegação integrada de gabaritos oficiais comentados, vista de prova individual por RGM e gestão de publicação para <strong>Empreendedorismo e Inovação</strong>, <strong>Medicina dos Animais Silvestres</strong> e <strong>Fisiologia Animal II</strong> nos campi <strong>PTA — Paulista</strong>, <strong>SM</strong> e <strong>GRU</strong>.
                </p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <button type="button" wire:click="openCreatePackage()"
                        class="inline-flex items-center gap-1.5 px-4 py-2.5 rounded-xl bg-teal-600 hover:bg-teal-500 text-white font-bold text-xs shadow-lg shadow-teal-900/30 transition active:scale-95">
                    <span>➕</span> Novo Pacote / Disciplina
                </button>
                <a href="{{ route('exam.mirror') }}" target="_blank"
                   class="inline-flex items-center gap-1.5 px-4 py-2.5 rounded-xl bg-white/10 hover:bg-white/15 text-white font-bold text-xs border border-white/15 transition active:scale-95">
                    <span>↗</span> Abrir Consulta do Aluno
                </a>
            </div>
        </div>

        <!-- ============================================================== -->
        <!-- BARRA DE NAVEGAÇÃO POR ABAS (TABS UX REFINADA)                 -->
        <!-- ============================================================== -->
        <div class="flex flex-wrap items-center gap-2 p-1.5 rounded-2xl bg-gray-100 dark:bg-gray-800/80 border border-gray-200 dark:border-gray-700/80 shadow-inner">
            <button type="button" wire:click="setTab('gabaritos')"
                    class="flex items-center gap-2 px-4 py-2.5 rounded-xl text-xs font-bold transition {{ $activeTab === 'gabaritos' ? 'bg-teal-600 text-white shadow-md' : 'text-gray-600 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-700' }}">
                <span>📑</span> Gabaritos Oficiais &amp; Questões Comentadas
            </button>
            <button type="button" wire:click="setTab('vista')"
                    class="flex items-center gap-2 px-4 py-2.5 rounded-xl text-xs font-bold transition {{ $activeTab === 'vista' ? 'bg-teal-600 text-white shadow-md' : 'text-gray-600 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-700' }}">
                <span>🎓</span> Vista de Prova &amp; Aluno
            </button>
            <button type="button" wire:click="setTab('pacotes')"
                    class="flex items-center gap-2 px-4 py-2.5 rounded-xl text-xs font-bold transition {{ $activeTab === 'pacotes' ? 'bg-teal-600 text-white shadow-md' : 'text-gray-600 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-700' }}">
                <span>📦</span> Devolutivas &amp; Turmas Publicadas ({{ count($packages) }})
            </button>
            <button type="button" wire:click="setTab('semestres')"
                    class="flex items-center gap-2 px-4 py-2.5 rounded-xl text-xs font-bold transition {{ $activeTab === 'semestres' ? 'bg-teal-600 text-white shadow-md' : 'text-gray-600 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-700' }}">
                <span>📅</span> Semestres &amp; Bloqueio
            </button>
        </div>

        <!-- ============================================================== -->
        <!-- ABA 1: GABARITOS OFICIAIS & QUESTÕES COMENTADAS (NOVO UX)      -->
        <!-- ============================================================== -->
        @if ($activeTab === 'gabaritos')
            <div class="space-y-5">
                <!-- Seletor Rápido de Disciplina / Avaliação -->
                <div class="p-5 rounded-2xl bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 shadow-sm space-y-4">
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-gray-200 dark:border-gray-800 pb-3">
                        <div>
                            <span class="text-xs font-bold uppercase tracking-wider text-teal-600 dark:text-teal-400">1. Selecione a Disciplina</span>
                            <h3 class="text-base font-black text-gray-900 dark:text-white mt-0.5">
                                Catálogo de Gabaritos Comentados
                            </h3>
                        </div>
                        <span class="text-xs text-gray-500 dark:text-gray-400">
                            {{ count($allGabs) }} avaliações cadastradas
                        </span>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        @foreach ($allGabs as $gKey => $gMeta)
                            @php
                                $isSelectedDisc = ($selectedGabaritoDiscipline === $gKey);
                            @endphp
                            <button type="button" wire:click="selectGabarito('{{ $gKey }}')"
                                    class="p-4 rounded-xl text-left border-2 transition flex items-start justify-between gap-3 {{ $isSelectedDisc ? 'border-teal-500 bg-teal-50/40 dark:bg-teal-950/20 shadow-sm' : 'border-gray-200 dark:border-gray-800 bg-gray-50/50 dark:bg-gray-800/40 hover:border-gray-300 dark:hover:border-gray-700' }}">
                                <div class="space-y-1">
                                    <div class="flex items-center gap-2">
                                        <span class="px-2 py-0.5 rounded text-[0.68rem] font-bold uppercase tracking-wider {{ !empty($gMeta['tem_discursiva']) ? 'bg-indigo-500/15 text-indigo-700 dark:text-indigo-300' : 'bg-teal-500/15 text-teal-700 dark:text-teal-300' }}">
                                            {{ !empty($gMeta['tem_discursiva']) ? 'Obj + Discursiva' : 'Objetiva Pura' }}
                                        </span>
                                        <span class="text-xs text-gray-400 font-semibold">{{ $gMeta['semestre'] ?? '2026.2' }}</span>
                                    </div>
                                    <h4 class="font-bold text-sm text-gray-900 dark:text-white">
                                        {{ $gMeta['disciplina'] }}
                                    </h4>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">
                                        {{ $gMeta['avaliacao'] }} · {{ count($gMeta['turmas'] ?? []) }} turmas
                                    </p>
                                    <p class="text-[0.7rem] text-teal-600 dark:text-teal-400 font-medium">
                                        {{ $gMeta['escala'] ?? '' }}
                                    </p>
                                </div>
                                <span class="text-lg">{{ $isSelectedDisc ? '✅' : '👉' }}</span>
                            </button>
                        @endforeach
                    </div>

                    @php
                        $currDiscData = $allGabs[$selectedGabaritoDiscipline] ?? null;
                        $turmasList = $currDiscData['turmas'] ?? [];
                        $currTurmaData = $turmasList[$selectedGabaritoTurma] ?? (reset($turmasList) ?: []);
                        $versoesList = $currTurmaData['versoes'] ?? [];
                        $currVersaoData = $versoesList[$selectedGabaritoVersao] ?? (reset($versoesList) ?: []);
                        $questoes = $currVersaoData['questoes'] ?? [];
                    @endphp

                    @if ($currDiscData)
                        <!-- Seleção de Turma & Versão da Prova -->
                        <div class="pt-3 border-t border-gray-200 dark:border-gray-800 space-y-3">
                            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                                <span class="text-xs font-bold uppercase tracking-wider text-gray-700 dark:text-gray-300">
                                    2. Escolha o Campus / Turma:
                                </span>
                                <div class="flex flex-wrap items-center gap-1.5">
                                    @foreach ($turmasList as $tCode => $tObj)
                                        <button type="button" wire:click="selectGabaritoTurma('{{ $tCode }}')"
                                                class="px-3 py-1.5 rounded-lg text-xs font-bold transition {{ $selectedGabaritoTurma === $tCode ? 'bg-teal-600 text-white shadow-sm' : 'bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-700' }}">
                                            {{ $tObj['turma'] ?? $tCode }}
                                        </button>
                                    @endforeach
                                </div>
                            </div>

                            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                                <span class="text-xs font-bold uppercase tracking-wider text-gray-700 dark:text-gray-300">
                                    3. Escolha o Caderno / Versão de Prova:
                                </span>
                                <div class="flex flex-wrap items-center gap-1.5">
                                    @foreach ($versoesList as $vCode => $vObj)
                                        <button type="button" wire:click="selectGabaritoVersao('{{ $vCode }}')"
                                                class="px-3.5 py-1.5 rounded-lg text-xs font-bold transition flex items-center gap-1.5 {{ $selectedGabaritoVersao === $vCode ? 'bg-indigo-600 text-white shadow-sm ring-2 ring-indigo-400/40' : 'bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-700' }}">
                                            <span>🐾</span>
                                            <span>{{ $vObj['label'] ?? $vCode }}</span>
                                        </button>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    @endif
                </div>

                @if ($currVersaoData)
                    <!-- Barra de Pesquisa & Filtros de Questões -->
                    <div class="p-4 rounded-2xl bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 shadow-sm flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                        <div class="flex items-center gap-3">
                            <span class="text-xs font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                Filtrar Alternativa Oficial:
                            </span>
                            <div class="inline-flex rounded-xl p-1 bg-gray-100 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 text-xs font-bold">
                                @foreach (['ALL' => 'Todas', 'A' => 'A', 'B' => 'B', 'C' => 'C', 'D' => 'D', 'E' => 'E'] as $fOpt => $fLabel)
                                    <button type="button" wire:click="$set('filterAlternative', '{{ $fOpt }}')"
                                            class="px-2.5 py-1 rounded-lg transition {{ $filterAlternative === $fOpt ? 'bg-teal-600 text-white shadow-sm' : 'text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white' }}">
                                        {{ $fLabel }}
                                    </button>
                                @endforeach
                            </div>
                        </div>

                        <div class="relative w-full sm:w-72">
                            <input type="text" wire:model.debounce.300ms="searchQuestion"
                                   placeholder="Buscar por tema ou palavra..."
                                   class="w-full pl-8 pr-3 py-1.5 text-xs rounded-xl border border-gray-300 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 text-gray-900 dark:text-white focus:ring-2 focus:ring-teal-500 font-medium">
                            <span class="absolute left-2.5 top-2 text-gray-400 text-xs">🔍</span>
                        </div>
                    </div>

                    <!-- Resumo da Versão Atual -->
                    <div class="p-5 rounded-2xl bg-gradient-to-br from-teal-900/20 via-gray-900 to-indigo-900/20 border border-teal-500/30 shadow-sm flex flex-wrap items-center justify-between gap-4">
                        <div class="space-y-1">
                            <div class="flex items-center gap-2">
                                <span class="px-2.5 py-0.5 rounded-full bg-teal-500/20 text-teal-400 font-bold text-xs uppercase tracking-wider">
                                    {{ $currVersaoData['codinome'] ?? $currVersaoData['label'] }}
                                </span>
                                <span class="text-xs text-gray-400">
                                    {{ $currTurmaData['turma'] ?? '' }} · {{ $currTurmaData['data'] ?? '' }}
                                </span>
                            </div>
                            <h3 class="text-lg font-black text-white">
                                {{ $currDiscData['disciplina'] }} — {{ $currDiscData['avaliacao'] }}
                            </h3>
                            <p class="text-xs text-gray-300">
                                {{ $currVersaoData['total_questoes'] ?? count($questoes) }} questões objetivas ({{ number_format((float) ($currVersaoData['valor_questao'] ?? 0.1), 3, ',', '.') }} pt cada)
                            </p>
                        </div>
                        <div class="flex items-center gap-3">
                            <div class="text-right">
                                <span class="text-[0.68rem] uppercase font-bold text-gray-400 block">Escala da Prova</span>
                                <span class="font-outfit font-black text-xl text-teal-400">
                                    {{ $currDiscData['escala'] ?? '3,50 pts' }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Caso Clínico e Rubrica Discursiva (quando houver) -->
                    @if (!empty($currVersaoData['discursiva']) && !empty($currVersaoData['discursiva']['titulo']))
                        @php $discInfo = $currVersaoData['discursiva']; @endphp
                        <div class="p-6 rounded-2xl bg-white dark:bg-gray-900 border-2 border-indigo-500/40 shadow-sm space-y-4">
                            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 border-b border-gray-200 dark:border-gray-800 pb-3">
                                <div>
                                    <span class="text-xs font-bold uppercase tracking-wider text-indigo-600 dark:text-indigo-400">Gabarito Oficial da Questão Discursiva (1,00 pt)</span>
                                    <h4 class="text-base font-black text-gray-900 dark:text-white mt-0.5">
                                        {{ $discInfo['titulo'] }}
                                    </h4>
                                </div>
                                <span class="px-3 py-1 rounded-xl bg-indigo-500/15 text-indigo-700 dark:text-indigo-300 font-bold text-xs">
                                    Rubrica de Correção 3 Itens (a, b, c)
                                </span>
                            </div>

                            @if (!empty($discInfo['rubrica']))
                                <div class="grid grid-cols-1 gap-3">
                                    @foreach ($discInfo['rubrica'] as $rKey => $rDesc)
                                        <div class="p-4 rounded-xl bg-gray-50 dark:bg-gray-800/70 border border-gray-200 dark:border-gray-700 space-y-1">
                                            <div class="flex items-center justify-between text-xs font-bold text-indigo-600 dark:text-indigo-400 uppercase tracking-wider">
                                                <span>Item {{ $rKey }})</span>
                                                <span class="text-gray-500 dark:text-gray-400 font-mono">
                                                    {{ $rKey === 'a' ? '0,25 pt' : ($rKey === 'b' ? '0,55 pt' : '0,20 pt') }}
                                                </span>
                                            </div>
                                            <p class="text-xs sm:text-sm text-gray-800 dark:text-gray-200 font-medium leading-relaxed whitespace-pre-line">
                                                {{ $rDesc }}
                                            </p>
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    @endif

                    <!-- Grade de Questões Objetivas Comentadas -->
                    <div class="space-y-3">
                        <div class="flex items-center justify-between px-1">
                            <h4 class="text-xs font-bold uppercase tracking-wider text-gray-700 dark:text-gray-300">
                                Questões Objetivas Resolvidas e Comentadas
                            </h4>
                            <span class="text-xs text-gray-400">
                                Mostrando {{ count($questoes) }} questões
                            </span>
                        </div>

                        <div class="grid grid-cols-1 gap-3">
                            @foreach ($questoes as $qItem)
                                @php
                                    $qNum = $qItem['q'] ?? ($loop->index + 1);
                                    $gabLet = strtoupper(trim($qItem['gab'] ?? ''));
                                    $tema = $qItem['tema'] ?? '';
                                    $eixo = $qItem['eixo'] ?? '';
                                    $comm = $qItem['comentario'] ?? '';

                                    // Filtro de busca
                                    if ($searchQuestion !== '') {
                                        $needle = mb_strtolower($searchQuestion);
                                        $haystack = mb_strtolower("{$qNum} {$tema} {$eixo} {$comm}");
                                        if (!str_contains($haystack, $needle)) {
                                            continue;
                                        }
                                    }

                                    // Filtro de alternativa
                                    if ($filterAlternative !== 'ALL' && $gabLet !== $filterAlternative) {
                                        continue;
                                    }

                                    $badgeColors = [
                                        'A' => 'bg-emerald-500 text-white ring-2 ring-emerald-300 dark:ring-emerald-700',
                                        'B' => 'bg-blue-600 text-white ring-2 ring-blue-300 dark:ring-blue-700',
                                        'C' => 'bg-amber-500 text-white ring-2 ring-amber-300 dark:ring-amber-700',
                                        'D' => 'bg-purple-600 text-white ring-2 ring-purple-300 dark:ring-purple-700',
                                        'E' => 'bg-rose-600 text-white ring-2 ring-rose-300 dark:ring-rose-700',
                                    ];
                                    $badgeColor = $badgeColors[$gabLet] ?? 'bg-gray-600 text-white';
                                @endphp

                                <div class="p-4 sm:p-5 rounded-2xl bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 shadow-sm hover:border-teal-500/50 transition space-y-2.5">
                                    <div class="flex flex-wrap items-center justify-between gap-2 border-b border-gray-100 dark:border-gray-800/80 pb-2.5">
                                        <div class="flex items-center gap-2.5">
                                            <span class="w-8 h-8 rounded-xl bg-gray-100 dark:bg-gray-800 text-gray-900 dark:text-white font-outfit font-black text-sm flex items-center justify-center border border-gray-200 dark:border-gray-700">
                                                {{ sprintf('%02d', $qNum) }}
                                            </span>
                                            <div class="flex items-center gap-1.5">
                                                <span class="text-xs text-gray-400 font-bold">Gabarito:</span>
                                                <span class="w-7 h-7 rounded-lg {{ $badgeColor }} font-outfit font-black text-sm flex items-center justify-center shadow-sm">
                                                    {{ $gabLet }}
                                                </span>
                                            </div>
                                            @if ($eixo)
                                                <span class="px-2 py-0.5 rounded text-[0.68rem] font-bold bg-teal-500/10 text-teal-700 dark:text-teal-300 uppercase tracking-wider">
                                                    {{ $eixo }}
                                                </span>
                                            @endif
                                        </div>
                                        <span class="text-[0.7rem] text-gray-400 font-medium">
                                            Valor: {{ number_format((float) ($currVersaoData['valor_questao'] ?? 0.1), 3, ',', '.') }} pt
                                        </span>
                                    </div>

                                    <div>
                                        <h5 class="text-xs sm:text-sm font-bold text-gray-900 dark:text-white">
                                            {{ $tema }}
                                        </h5>
                                        @if ($comm)
                                            <div class="mt-2 p-3 rounded-xl bg-gray-50 dark:bg-gray-800/60 border border-gray-200/60 dark:border-gray-700/60 text-xs sm:text-sm text-gray-700 dark:text-gray-300 font-medium leading-relaxed">
                                                <strong class="text-teal-600 dark:text-teal-400 uppercase tracking-wider text-[0.68rem] block mb-0.5">💡 Justificativa / Comentário Pedagógico:</strong>
                                                {{ $comm }}
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>
        @endif

        <!-- ============================================================== -->
        <!-- ABA 2: VISTA DE PROVA & ALUNO INDIVIDUAL (UX MELHORADA)        -->
        <!-- ============================================================== -->
        @if ($activeTab === 'vista')
            <div class="p-6 rounded-2xl bg-white dark:bg-gray-900 border-2 border-teal-500 shadow-xl space-y-6">
                <div class="flex items-center justify-between border-b border-gray-200 dark:border-gray-800 pb-3">
                    <div>
                        <span class="text-xs font-bold uppercase tracking-wider text-teal-600 dark:text-teal-400">Atendimento Docente &amp; Revisão</span>
                        <h3 class="text-lg font-black text-gray-900 dark:text-white mt-0.5">
                            Vista de Prova · Consulta e Ajuste Individual de Aluno
                        </h3>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                            Descriptografa o espelho completo do aluno na hora, compara respostas com o gabarito oficial e permite ajustar nota e parecer.
                        </p>
                    </div>
                </div>

                <!-- Formulário de Localização -->
                <div class="p-4 rounded-xl bg-gray-50 dark:bg-gray-800/60 border border-gray-200 dark:border-gray-700 space-y-3">
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                        <div>
                            <label class="block text-xs font-bold uppercase tracking-wider text-gray-700 dark:text-gray-300 mb-1">
                                Selecione a Avaliação
                            </label>
                            <select wire:model="vistaPackage"
                                    class="w-full px-3 py-2 rounded-xl border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 text-gray-900 dark:text-white text-xs font-bold">
                                <option value="">-- Escolha a Prova --</option>
                                @foreach ($packages as $pId => $pData)
                                    <option value="{{ $pData['filename'] }}">{{ $pData['disciplina'] }} — {{ $pData['avaliacao'] }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-bold uppercase tracking-wider text-gray-700 dark:text-gray-300 mb-1">
                                RGM do Aluno
                            </label>
                            <input type="text" wire:model.defer="vistaRgm" placeholder="Ex: 33735395 ou 47605839"
                                   class="w-full px-3 py-2 rounded-xl border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 text-gray-900 dark:text-white text-xs font-bold">
                        </div>
                        <div class="flex items-end">
                            <button type="button" wire:click="searchStudentVista"
                                    class="w-full px-4 py-2 rounded-xl bg-teal-600 hover:bg-teal-500 text-white font-bold text-xs shadow-md transition flex items-center justify-center gap-1.5"
                                    wire:loading.attr="disabled">
                                <span wire:loading.remove wire:target="searchStudentVista">🔍 Abrir Espelho do Aluno</span>
                                <span wire:loading wire:target="searchStudentVista">Localizando e Descriptografando...</span>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Detalhes do Aluno Localizado & Espelho Completo -->
                @if ($vistaStudent)
                    <div class="p-5 rounded-xl bg-slate-50 dark:bg-slate-900 border border-teal-500/40 space-y-5">
                        <!-- Header do Aluno -->
                        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-200 dark:border-gray-800 pb-3">
                            <div>
                                <span class="text-xs font-bold text-teal-600 dark:text-teal-400 uppercase tracking-wider">
                                    {{ $vistaStudent['turma'] ?? '-' }} · {{ $vistaStudent['data_prova'] ?? '' }}
                                </span>
                                <h4 class="text-base font-black text-gray-900 dark:text-white">
                                    RGM: {{ $vistaRgm }} · Versão: {{ $vistaStudent['versao'] ?? '-' }} ({{ $vistaStudent['versao_label'] ?? '' }})
                                </h4>
                                @if (!empty($vistaStudent['observacao']))
                                    <p class="text-xs text-sky-600 dark:text-sky-400 font-semibold mt-0.5">
                                        ℹ️ {{ $vistaStudent['observacao'] }}
                                    </p>
                                @endif
                            </div>
                            <div class="flex items-center gap-2">
                                <div class="p-2.5 rounded-xl bg-teal-500/15 border border-teal-500/30 text-right">
                                    <span class="text-[0.65rem] uppercase font-bold text-teal-700 dark:text-teal-300 block">Nota Atual</span>
                                    <span class="font-outfit font-black text-xl text-teal-700 dark:text-teal-300">
                                        {{ number_format((float) ($vistaStudent['nota_final'] ?? 0), 2, ',', '.') }} pts
                                    </span>
                                </div>
                            </div>
                        </div>

                        <!-- Grade de Resumo de Pontuação -->
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 text-xs">
                            <div class="p-3.5 rounded-xl bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700">
                                <span class="text-gray-400 block font-semibold mb-0.5">Desempenho Objetivo</span>
                                <span class="font-bold text-sm text-gray-900 dark:text-white">
                                    {{ $vistaStudent['acertos_obj'] ?? 0 }} de {{ $vistaStudent['total_obj'] ?? 0 }} acertos
                                </span>
                                <span class="text-teal-600 dark:text-teal-400 block font-bold">
                                    Nota: {{ number_format((float) ($vistaStudent['nota_obj'] ?? 0), 2, ',', '.') }} pt
                                </span>
                            </div>

                            @if (!empty($vistaStudent['tem_discursiva']) && !empty($vistaStudent['discursiva']))
                                <div class="p-3.5 rounded-xl bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700">
                                    <span class="text-gray-400 block font-semibold mb-0.5">Questão Discursiva</span>
                                    <span class="font-bold text-sm text-gray-900 dark:text-white">
                                        {{ number_format((float) ($vistaStudent['discursiva']['nota_total'] ?? 0), 2, ',', '.') }} / 1,00 pt
                                    </span>
                                    <span class="text-gray-400 block text-[0.7rem]">
                                        a: {{ $vistaStudent['discursiva']['nota_a'] ?? 0 }} | b: {{ $vistaStudent['discursiva']['nota_b'] ?? 0 }} | c: {{ $vistaStudent['discursiva']['nota_c'] ?? 0 }}
                                    </span>
                                </div>
                            @else
                                <div class="p-3.5 rounded-xl bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700">
                                    <span class="text-gray-400 block font-semibold mb-0.5">Aproveitamento</span>
                                    <span class="font-bold text-sm text-teal-600 dark:text-teal-400">
                                        {{ round((($vistaStudent['acertos_obj'] ?? 0) / max(1, ($vistaStudent['total_obj'] ?? 1))) * 100) }}% da prova
                                    </span>
                                </div>
                            @endif

                            <div class="p-3.5 rounded-xl bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700">
                                <span class="text-gray-400 block font-semibold mb-0.5">Status de Criptografia</span>
                                <span class="font-mono text-[0.68rem] text-gray-500 dark:text-gray-400 block truncate" title="{{ $vistaHash }}">
                                    Hash: {{ substr($vistaHash, 0, 16) }}...
                                </span>
                                <span class="text-emerald-600 dark:text-emerald-400 font-bold text-[0.7rem]">
                                    ✓ Zero-Knowledge OK
                                </span>
                            </div>
                        </div>

                        <!-- Espelho das Questões Objetivas do Aluno (Comparativo) -->
                        @if (!empty($vistaStudent['itens_objetivos']))
                            <div class="space-y-2">
                                <div class="flex items-center justify-between">
                                    <span class="text-xs font-bold uppercase tracking-wider text-gray-700 dark:text-gray-300">
                                        Gabarito Comparativo da Prova do Aluno
                                    </span>
                                </div>
                                <div class="p-3 rounded-xl bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 overflow-x-auto">
                                    <div class="grid grid-cols-5 sm:grid-cols-10 gap-2 text-center text-xs">
                                        @foreach ($vistaStudent['itens_objetivos'] as $it)
                                            @php
                                                $hit = !empty($it['acertou']);
                                            @endphp
                                            <div class="p-2 rounded-lg border {{ $hit ? 'bg-emerald-50 dark:bg-emerald-950/30 border-emerald-300 dark:border-emerald-800' : 'bg-rose-50 dark:bg-rose-950/30 border-rose-300 dark:border-rose-800' }}"
                                                 title="Q{{ $it['q_num'] }}: Aluno '{{ $it['resposta_aluno'] }}' vs Gab '{{ $it['gabarito_oficial'] }}' - {{ $it['tema'] ?? '' }}">
                                                <span class="block text-[0.65rem] font-bold text-gray-400">Q{{ $it['q_num'] }}</span>
                                                <span class="font-outfit font-black text-sm block {{ $hit ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-600 dark:text-rose-400' }}">
                                                    {{ $it['resposta_aluno'] }}
                                                </span>
                                                <span class="text-[0.65rem] text-gray-500 dark:text-gray-400 block">
                                                    Gab: {{ $it['gabarito_oficial'] }}
                                                </span>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            </div>
                        @endif

                        <!-- Painel da Discursiva do Aluno (quando houver) -->
                        @if (!empty($vistaStudent['tem_discursiva']) && !empty($vistaStudent['discursiva']['resumo_respostas']))
                            @php $discStud = $vistaStudent['discursiva']; @endphp
                            <div class="space-y-2">
                                <span class="text-xs font-bold uppercase tracking-wider text-indigo-600 dark:text-indigo-400">
                                    Respostas Manuscritas Avaliadas da Discursiva
                                </span>
                                <div class="grid grid-cols-1 gap-2 text-xs">
                                    <div class="p-3 rounded-lg bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700">
                                        <div class="flex items-center justify-between font-bold mb-1">
                                            <span class="text-indigo-600 dark:text-indigo-400">a) Diagnóstico</span>
                                            <span class="text-gray-900 dark:text-white">{{ $discStud['nota_a'] ?? 0 }} / 0,25</span>
                                        </div>
                                        <p class="text-gray-700 dark:text-gray-300">{{ $discStud['resumo_respostas']['a'] ?? '—' }}</p>
                                    </div>
                                    <div class="p-3 rounded-lg bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700">
                                        <div class="flex items-center justify-between font-bold mb-1">
                                            <span class="text-indigo-600 dark:text-indigo-400">b) Prescrição</span>
                                            <span class="text-gray-900 dark:text-white">{{ $discStud['nota_b'] ?? 0 }} / 0,55</span>
                                        </div>
                                        <p class="text-gray-700 dark:text-gray-300">{{ $discStud['resumo_respostas']['b'] ?? '—' }}</p>
                                    </div>
                                    <div class="p-3 rounded-lg bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700">
                                        <div class="flex items-center justify-between font-bold mb-1">
                                            <span class="text-indigo-600 dark:text-indigo-400">c) Manejo</span>
                                            <span class="text-gray-900 dark:text-white">{{ $discStud['nota_c'] ?? 0 }} / 0,20</span>
                                        </div>
                                        <p class="text-gray-700 dark:text-gray-300">{{ $discStud['resumo_respostas']['c'] ?? '—' }}</p>
                                    </div>
                                </div>
                            </div>
                        @endif

                        <!-- Edição da Discursiva / Feedback / Ajuste de Nota -->
                        <div class="pt-3 border-t border-gray-200 dark:border-gray-800 space-y-4">
                            <span class="text-xs font-bold uppercase tracking-wider text-teal-600 dark:text-teal-400 block">
                                Ajuste de Pontuação &amp; Parecer Docente
                            </span>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-xs font-bold uppercase tracking-wider text-gray-700 dark:text-gray-300 mb-1">
                                        Nota da Questão Discursiva / Trabalho
                                    </label>
                                    <input type="number" step="0.01" min="0" max="5" wire:model.defer="vistaForm.nota_disc"
                                           class="w-full px-3.5 py-2.5 rounded-xl border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-900 dark:text-white text-sm font-bold">
                                </div>
                                <div>
                                    <label class="block text-xs font-bold uppercase tracking-wider text-gray-700 dark:text-gray-300 mb-1">
                                        Nota Final da Avaliação
                                    </label>
                                    <input type="number" step="0.01" min="0" max="5" wire:model.defer="vistaForm.nota_final"
                                           class="w-full px-3.5 py-2.5 rounded-xl border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-900 dark:text-white text-sm font-black text-emerald-600 dark:text-emerald-400">
                                </div>
                            </div>

                            <div>
                                <label class="block text-xs font-bold uppercase tracking-wider text-gray-700 dark:text-gray-300 mb-1">
                                    Comentário Pedagógico / Feedback Discursiva / Motivos de Desconto
                                </label>
                                <textarea wire:model.defer="vistaForm.feedback" rows="3"
                                          placeholder="Digite o parecer, detalhes da correção ou motivos de eventual ajuste de nota..."
                                          class="w-full px-3.5 py-2.5 rounded-xl border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-900 dark:text-white text-xs leading-relaxed"></textarea>
                            </div>

                            <div>
                                <label class="block text-xs font-bold uppercase tracking-wider text-gray-700 dark:text-gray-300 mb-1">
                                    Observação Adicional da Prova (Opcional)
                                </label>
                                <input type="text" wire:model.defer="vistaForm.obs" placeholder="Ex: Nota revisada presencialmente em vista de prova dia 05/11."
                                       class="w-full px-3.5 py-2.5 rounded-xl border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-900 dark:text-white text-xs">
                            </div>

                            <div class="flex items-center justify-end gap-3 pt-2">
                                <button type="button" wire:click="cancelVista"
                                        class="px-4 py-2 rounded-xl bg-gray-200 dark:bg-gray-700 text-gray-800 dark:text-gray-200 font-bold text-xs">
                                    Fechar
                                </button>
                                <button type="button" wire:click="saveStudentVista"
                                        class="px-5 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white font-bold text-xs shadow-md transition flex items-center gap-1.5"
                                        wire:loading.attr="disabled">
                                    <span wire:loading.remove wire:target="saveStudentVista">💾 Salvar Alterações na Prova do Aluno</span>
                                    <span wire:loading wire:target="saveStudentVista">Gravando e Criptografando...</span>
                                </button>
                            </div>
                        </div>
                    </div>
                @endif
            </div>
        @endif

        <!-- ============================================================== -->
        <!-- ABA 3: DEVOLUTIVAS & PACOTES PUBLICADOS (GESTÃO DE TURMAS)     -->
        <!-- ============================================================== -->
        @if ($activeTab === 'pacotes')
            <div class="space-y-6">
                <!-- Card de Upload de Novo Pacote JSON Criptografado -->
                <div class="p-6 rounded-2xl bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 shadow-sm space-y-4">
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                        <div>
                            <h3 class="text-base font-bold text-gray-900 dark:text-white">
                                Publicar / Atualizar Pacote de Devolutiva (.json)
                            </h3>
                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                                Envie o arquivo <code>.json</code> gerado para Provas ou Trabalhos. Os pacotes são 100% anonimizados via chave derivada (LGPD — Art. 12).
                            </p>
                        </div>
                    </div>

                    <form wire:submit.prevent="uploadPackage" class="flex flex-col sm:flex-row items-start sm:items-center gap-3 pt-2">
                        <input type="file" wire:model="jsonFile" accept=".json,application/json"
                               class="block w-full sm:w-auto text-xs text-gray-700 dark:text-gray-300 file:mr-3 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-gray-100 dark:file:bg-gray-800 file:text-gray-800 dark:file:text-gray-200 hover:file:bg-gray-200" />
                        <button type="submit"
                                class="px-4 py-2 rounded-lg bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-bold transition disabled:opacity-50"
                                wire:loading.attr="disabled">
                            <span wire:loading.remove wire:target="uploadPackage">Carregar &amp; Publicar Pacote</span>
                            <span wire:loading wire:target="uploadPackage">Publicando...</span>
                        </button>
                    </form>
                </div>

                <!-- Lista de Pacotes Publicados com Ações CRUD -->
                <div class="p-6 rounded-2xl bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 shadow-sm space-y-4">
                    <div class="flex items-center justify-between border-b border-gray-200 dark:border-gray-800 pb-3">
                        <div>
                            <h3 class="text-base font-bold text-gray-900 dark:text-white">
                                Disciplinas &amp; Devolutivas Publicadas no Portal
                            </h3>
                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                                Consulte, ajuste metadados ou remova pacotes publicados.
                            </p>
                        </div>
                        <span class="px-2.5 py-1 rounded-lg bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300 font-bold text-xs">
                            {{ count($packages) }} pacotes ativos
                        </span>
                    </div>

                    @if (empty($packages))
                        <div class="p-8 text-center text-gray-500 space-y-2">
                            <p class="text-sm font-semibold">Nenhum pacote publicado no momento.</p>
                            <p class="text-xs">Clique no botão "Novo Pacote / Disciplina" acima ou envie um arquivo JSON criptografado.</p>
                        </div>
                    @else
                        <div class="space-y-4">
                            @foreach ($packages as $pkgId => $pkg)
                                @php
                                    $isTrab = (($pkg['tipo'] ?? '') === 'trabalho');
                                    $pkgClosed = !empty($pkg['is_encerrado']);
                                @endphp
                                <div class="p-5 rounded-2xl border border-gray-200 dark:border-gray-800 bg-gray-50/70 dark:bg-gray-800/40 space-y-4 hover:border-teal-500/40 transition">
                                    <div class="flex flex-wrap items-start justify-between gap-3">
                                        <div class="space-y-1">
                                            <div class="flex flex-wrap items-center gap-2">
                                                <span class="px-2.5 py-0.5 rounded text-[0.68rem] font-bold uppercase tracking-wider {{ $isTrab ? 'bg-amber-500/20 text-amber-700 dark:text-amber-300' : 'bg-teal-500/20 text-teal-700 dark:text-teal-300' }}">
                                                    {{ $isTrab ? '📁 Trabalho Acadêmico' : '📝 Avaliação / Prova' }}
                                                </span>
                                                <span class="px-2.5 py-0.5 rounded text-[0.68rem] font-bold bg-indigo-500/15 text-indigo-700 dark:text-indigo-300 uppercase tracking-wider">
                                                    Campus: {{ $pkg['campus'] ?? 'PTA — Paulista' }}
                                                </span>
                                                <span class="text-xs font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                                    {{ $pkg['semestre'] ?? '' }} (Ano {{ $pkg['ano'] ?? '' }})
                                                </span>
                                                @if ($pkgClosed)
                                                    <span class="px-2 py-0.5 rounded text-[0.68rem] font-bold bg-red-500/15 text-red-600 dark:text-red-400">
                                                        🔒 Semestre Encerrado
                                                    </span>
                                                @else
                                                    <span class="px-2 py-0.5 rounded text-[0.68rem] font-bold bg-emerald-500/15 text-emerald-600 dark:text-emerald-400">
                                                        ✓ Liberado
                                                    </span>
                                                @endif
                                            </div>
                                            <h4 class="text-base font-black text-gray-900 dark:text-white">
                                                {{ $pkg['disciplina'] ?? '' }} — {{ $pkg['avaliacao'] ?? $pkgId }}
                                            </h4>
                                            <p class="text-xs text-gray-500 dark:text-gray-400">
                                                Arquivo: <code>{{ $pkg['filename'] }}</code> · Atualizado: {{ $pkg['updated_at'] ?? '-' }} ·
                                                <strong>{{ $pkg['vault_count'] ?? 0 }}</strong> cofres anônimos (LGPD Art. 12)
                                            </p>
                                        </div>

                                        <!-- Botões de Ação do Pacote (CRUD) -->
                                        <div class="flex flex-wrap items-center gap-1.5">
                                            <button type="button" wire:click="openImport('{{ $pkg['filename'] }}')"
                                                    class="px-3 py-1.5 rounded-lg bg-emerald-600/15 hover:bg-emerald-600/25 text-emerald-700 dark:text-emerald-300 text-xs font-bold transition flex items-center gap-1">
                                                <span>📥</span> Importar Notas (CSV/TSV)
                                            </button>
                                            <button type="button" wire:click="openAddStudent('{{ $pkg['filename'] }}')"
                                                    class="px-3 py-1.5 rounded-lg bg-indigo-600/15 hover:bg-indigo-600/25 text-indigo-700 dark:text-indigo-300 text-xs font-bold transition flex items-center gap-1">
                                                <span>👤</span> + Aluno
                                            </button>
                                            <button type="button" wire:click="viewPackage('{{ $pkg['filename'] }}')"
                                                    class="px-3 py-1.5 rounded-lg bg-gray-200 dark:bg-gray-700 hover:bg-gray-300 dark:hover:bg-gray-600 text-gray-800 dark:text-gray-200 text-xs font-bold transition flex items-center gap-1">
                                                <span>👁️</span> Inspecionar
                                            </button>
                                            <button type="button" wire:click="openVista('{{ $pkg['filename'] }}')"
                                                    class="px-3 py-1.5 rounded-lg bg-teal-600/15 hover:bg-teal-600/25 text-teal-700 dark:text-teal-300 text-xs font-bold transition flex items-center gap-1">
                                                <span>🔍</span> Vista de Prova
                                            </button>
                                            <button type="button" wire:click="editPackage('{{ $pkg['filename'] }}')"
                                                    class="px-3 py-1.5 rounded-lg bg-teal-600/15 hover:bg-teal-600/25 text-teal-700 dark:text-teal-300 text-xs font-bold transition flex items-center gap-1">
                                                <span>✏️</span> Editar
                                            </button>
                                            <a href="{{ route('exam.mirror', ['ano' => $pkg['ano'] ?? '2026', 'avaliacao' => $pkgId]) }}" target="_blank"
                                               class="px-3 py-1.5 rounded-lg bg-indigo-600/15 hover:bg-indigo-600/25 text-indigo-700 dark:text-indigo-300 text-xs font-bold transition flex items-center gap-1">
                                                <span>↗</span> Ver no Site
                                            </a>
                                            <button type="button" wire:click="confirmDelete('{{ $pkg['filename'] }}', '{{ addslashes($pkg['disciplina'] . ' — ' . $pkg['avaliacao']) }}')"
                                                    class="px-3 py-1.5 rounded-lg bg-rose-600/10 hover:bg-rose-600/20 text-rose-600 dark:text-rose-400 text-xs font-bold transition flex items-center gap-1">
                                                <span>🗑️</span> Remover
                                            </button>
                                        </div>
                                    </div>

                                    <!-- Grade de Turmas Formatada sem números de oferta -->
                                    @if (!empty($pkg['turmas']))
                                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-2 pt-2 border-t border-gray-200/60 dark:border-gray-700/60">
                                            @foreach ($pkg['turmas'] as $tKey => $tInfo)
                                                <div class="px-3 py-2.5 rounded-xl bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700/70 flex items-center justify-between text-xs shadow-sm hover:border-amber-500/50 transition">
                                                    <div>
                                                        <span class="font-bold text-gray-900 dark:text-gray-100 block">
                                                            {{ $tInfo['turma'] ?? $tKey }}
                                                        </span>
                                                        <span class="text-gray-400 text-[0.68rem] block">
                                                            {{ $tInfo['data'] ? 'Data: ' . $tInfo['data'] : 'Data a definir' }}
                                                        </span>
                                                    </div>
                                                    <div class="flex items-center gap-1.5">
                                                        @if (($tInfo['status'] ?? '') === 'publicada')
                                                            <span class="px-2 py-0.5 rounded-lg bg-emerald-500/15 text-emerald-600 dark:text-emerald-400 font-bold text-[0.68rem]">
                                                                {{ $tInfo['total_provas'] ?? 0 }} {{ $isTrab ? 'alunos' : 'provas' }}
                                                            </span>
                                                            <button type="button" wire:click="openTurmaLoader('{{ $pkg['filename'] }}', '{{ $tKey }}')"
                                                                    title="Atualizar ou recarregar provas desta turma"
                                                                    class="px-1.5 py-0.5 rounded text-[0.68rem] text-teal-600 hover:text-teal-500 font-bold">
                                                                🔄
                                                            </button>
                                                        @else
                                                            <button type="button" wire:click="openTurmaLoader('{{ $pkg['filename'] }}', '{{ $tKey }}')"
                                                                    class="px-2 py-1 rounded-lg bg-amber-500/15 hover:bg-amber-500/25 text-amber-700 dark:text-amber-300 font-bold text-[0.68rem] transition flex items-center gap-1">
                                                                <span>📥</span> Carregar Notas
                                                            </button>
                                                        @endif
                                                    </div>
                                                </div>
                                            @endforeach
                                        </div>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>
        @endif

        <!-- ============================================================== -->
        <!-- ABA 4: GESTÃO DE SEMESTRES & BLOQUEIO DE ACESSO                -->
        <!-- ============================================================== -->
        @if ($activeTab === 'semestres')
            <div class="p-6 rounded-2xl bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 shadow-sm space-y-4">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-gray-200 dark:border-gray-800 pb-3">
                    <div>
                        <h3 class="text-base font-bold text-gray-900 dark:text-white flex items-center gap-2">
                            <span>📅</span> Gestão de Semestres &amp; Bloqueio de Acesso
                        </h3>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                            Defina o semestre vigente e bloqueie visualizações de semestres anteriores após o encerramento do período letivo.
                        </p>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="text-xs text-gray-500 dark:text-gray-400">Semestre Ativo no Portal:</span>
                        <span class="px-2.5 py-1 rounded-lg bg-teal-500/15 text-teal-700 dark:text-teal-300 font-bold text-xs">
                            {{ $activeSem }}
                        </span>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3 pt-1">
                    @foreach ($allSemesters as $sem)
                        @php
                            $st = $semestersStatus[$sem] ?? ($sem === $activeSem ? 'aberto' : 'encerrado');
                            $isClosed = ($st === 'encerrado');
                            $isCurrent = ($sem === $activeSem);
                        @endphp
                        <div class="p-4 rounded-xl border {{ $isClosed ? 'border-red-200 dark:border-red-900/50 bg-red-50/50 dark:bg-red-950/20' : 'border-emerald-200 dark:border-emerald-900/50 bg-emerald-50/50 dark:bg-emerald-950/20' }} flex flex-col justify-between space-y-3">
                            <div class="flex items-center justify-between">
                                <div>
                                    <span class="font-bold text-sm text-gray-900 dark:text-white">{{ $sem }}</span>
                                    @if ($isCurrent)
                                        <span class="ml-1.5 px-2 py-0.5 rounded text-[0.68rem] font-bold bg-teal-600 text-white">VIGENTE</span>
                                    @endif
                                </div>
                                <span class="px-2 py-0.5 rounded-full text-xs font-bold {{ $isClosed ? 'bg-red-500/15 text-red-700 dark:text-red-400' : 'bg-emerald-500/15 text-emerald-700 dark:text-emerald-400' }}">
                                    {{ $isClosed ? '🔒 Encerrado (Bloqueado)' : '🔓 Aberto para Consulta' }}
                                </span>
                            </div>

                            <div class="flex items-center gap-2 pt-1 border-t border-gray-200/60 dark:border-gray-800/60 text-xs">
                                @if (!$isCurrent)
                                    <button type="button" wire:click="setActiveSemester('{{ $sem }}')"
                                            class="px-2.5 py-1 rounded bg-gray-200 dark:bg-gray-700 hover:bg-gray-300 dark:hover:bg-gray-600 font-medium text-gray-800 dark:text-gray-200 transition">
                                        Definir como Vigente
                                    </button>
                                @endif
                                <button type="button" wire:click="toggleSemesterStatus('{{ $sem }}')"
                                         class="px-2.5 py-1 rounded font-semibold transition {{ $isClosed ? 'bg-emerald-600 hover:bg-emerald-500 text-white' : 'bg-red-600 hover:bg-red-500 text-white' }}">
                                    {{ $isClosed ? 'Reabrir Semestre' : 'Encerrar &amp; Bloquear' }}
                                </button>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        <!-- ============================================================== -->
        <!-- MODAL: EDIÇÃO DE METADADOS & TURMAS (UPDATE)                   -->
        <!-- ============================================================== -->
        @if ($isEditing)
            <div class="p-6 rounded-2xl bg-white dark:bg-gray-900 border-2 border-teal-500 shadow-xl space-y-6">
                <div class="flex items-center justify-between border-b border-gray-200 dark:border-gray-800 pb-3">
                    <div>
                        <span class="text-xs font-bold uppercase tracking-wider text-teal-600 dark:text-teal-400">Edição de Pacote Publicado</span>
                        <h3 class="text-lg font-black text-gray-900 dark:text-white mt-0.5">
                            Ajustar Metadados &amp; Turmas · {{ $editingFilename }}
                        </h3>
                    </div>
                    <button type="button" wire:click="cancelEdit()" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 text-sm font-bold">
                        ✕ Fechar
                    </button>
                </div>

                <form wire:submit.prevent="savePackageEdits" class="space-y-5">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-bold uppercase tracking-wider text-gray-700 dark:text-gray-300 mb-1">
                                Disciplina
                            </label>
                            <input type="text" wire:model.defer="editForm.disciplina" required
                                   class="w-full px-3.5 py-2.5 rounded-xl border border-gray-300 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 text-gray-900 dark:text-white text-sm font-bold focus:ring-2 focus:ring-teal-500">
                        </div>
                        <div>
                            <label class="block text-xs font-bold uppercase tracking-wider text-gray-700 dark:text-gray-300 mb-1">
                                Campus Atribuído
                            </label>
                            <input type="text" wire:model.defer="editForm.campus" required
                                   class="w-full px-3.5 py-2.5 rounded-xl border border-gray-300 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 text-gray-900 dark:text-white text-sm font-bold focus:ring-2 focus:ring-teal-500">
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <div class="sm:col-span-2">
                            <label class="block text-xs font-bold uppercase tracking-wider text-gray-700 dark:text-gray-300 mb-1">
                                Nome da Avaliação
                            </label>
                            <input type="text" wire:model.defer="editForm.avaliacao" required
                                   class="w-full px-3.5 py-2.5 rounded-xl border border-gray-300 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 text-gray-900 dark:text-white text-sm font-bold focus:ring-2 focus:ring-teal-500">
                        </div>
                        <div>
                            <label class="block text-xs font-bold uppercase tracking-wider text-gray-700 dark:text-gray-300 mb-1">
                                Tipo
                            </label>
                            <select wire:model.defer="editForm.tipo" required
                                    class="w-full px-3.5 py-2.5 rounded-xl border border-gray-300 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 text-gray-900 dark:text-white text-sm font-bold focus:ring-2 focus:ring-teal-500">
                                <option value="avaliacao">📝 Avaliação / Prova Óptica</option>
                                <option value="trabalho">📁 Trabalho Acadêmico</option>
                            </select>
                        </div>
                    </div>

                    <div class="flex items-center justify-end gap-3 pt-3 border-t border-gray-200 dark:border-gray-800">
                        <button type="button" wire:click="cancelEdit()"
                                class="px-4 py-2 rounded-xl bg-gray-200 dark:bg-gray-700 text-gray-800 dark:text-gray-200 font-bold text-xs">
                            Cancelar
                        </button>
                        <button type="submit"
                                class="px-5 py-2.5 rounded-xl bg-teal-600 hover:bg-teal-500 text-white font-bold text-xs shadow-md transition">
                            Salvar Ajustes no Pacote
                        </button>
                    </div>
                </form>
            </div>
        @endif

        <!-- ============================================================== -->
        <!-- MODAL: IMPORTAÇÃO DIRETA DE NOTAS (SEM SCRIPTS)                -->
        <!-- ============================================================== -->
        @if ($isImporting)
            <div class="p-6 rounded-2xl bg-white dark:bg-gray-900 border-2 border-emerald-500 shadow-xl space-y-6">
                <div class="flex items-center justify-between border-b border-gray-200 dark:border-gray-800 pb-3">
                    <div>
                        <span class="text-xs font-bold uppercase tracking-wider text-emerald-600 dark:text-emerald-400">Importação de Planilha</span>
                        <h3 class="text-lg font-black text-gray-900 dark:text-white mt-0.5">
                            Carregar Notas para o Pacote: {{ $importPackage }}
                        </h3>
                    </div>
                    <button type="button" wire:click="cancelImport()" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 text-sm font-bold">
                        ✕ Cancelar
                    </button>
                </div>

                <div class="space-y-4">
                    <div>
                        <label class="block text-xs font-bold uppercase tracking-wider text-gray-700 dark:text-gray-300 mb-1">
                            Selecione a Turma
                        </label>
                        <select wire:model="importTurma" required
                                class="w-full px-3.5 py-2.5 rounded-xl border border-gray-300 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 text-gray-900 dark:text-white text-sm font-bold">
                            @foreach ($importTurmasList as $tKey => $tVal)
                                <option value="{{ $tKey }}">{{ $tVal['turma'] ?? $tKey }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="p-4 rounded-xl bg-gray-50 dark:bg-gray-800/60 border border-gray-200 dark:border-gray-700 space-y-2">
                        <label class="block text-xs font-bold uppercase tracking-wider text-gray-800 dark:text-gray-200">
                            Opção 1: Enviar Arquivo CSV / TSV exportado do Excel ou ZipGrade
                        </label>
                        <input type="file" wire:model="importFile" accept=".csv,.txt,.tsv"
                               class="block w-full text-xs text-gray-700 dark:text-gray-300 file:mr-3 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-teal-600 file:text-white hover:file:bg-teal-500" />
                    </div>

                    <div class="space-y-1.5">
                        <label class="block text-xs font-bold uppercase tracking-wider text-gray-700 dark:text-gray-300">
                            Opção 2: Ou Cole as Linhas Direto da Planilha (Ctrl+C / Ctrl+V)
                        </label>
                        <textarea wire:model.defer="importRawText" rows="4"
                                  placeholder="RGM,Versao,Q1,Q2,Q3,Acertos,Nota_Final&#10;34096167,B,A,A,D,13,2.30"
                                  class="w-full px-3.5 py-2.5 rounded-xl border border-gray-300 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 text-gray-900 dark:text-white font-mono text-xs"></textarea>
                    </div>

                    <div class="flex items-center justify-end gap-3 pt-3 border-t border-gray-200 dark:border-gray-800">
                        <button type="button" wire:click="cancelImport()"
                                class="px-4 py-2 rounded-xl bg-gray-200 dark:bg-gray-700 text-gray-800 dark:text-gray-200 font-bold text-xs">
                            Cancelar
                        </button>
                        <button type="button" wire:click="processImport"
                                class="px-5 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white font-bold text-xs shadow-md transition">
                            Criptografar &amp; Salvar Notas
                        </button>
                    </div>
                </div>
            </div>
        @endif

        <!-- ============================================================== -->
        <!-- MODAL: INICIALIZAR NOVO PACOTE                                 -->
        <!-- ============================================================== -->
        @if ($isCreating)
            <div class="p-6 rounded-2xl bg-white dark:bg-gray-900 border-2 border-indigo-500 shadow-xl space-y-6">
                <div class="flex items-center justify-between border-b border-gray-200 dark:border-gray-800 pb-3">
                    <div>
                        <span class="text-xs font-bold uppercase tracking-wider text-indigo-600 dark:text-indigo-400">Novo Cadastro</span>
                        <h3 class="text-lg font-black text-gray-900 dark:text-white mt-0.5">
                            Inicializar Pacote de Disciplina / Avaliação
                        </h3>
                    </div>
                    <button type="button" wire:click="cancelCreate()" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 text-sm font-bold">
                        ✕ Cancelar
                    </button>
                </div>

                <form wire:submit.prevent="storeNewPackage" class="space-y-4">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-bold uppercase tracking-wider text-gray-700 dark:text-gray-300 mb-1">Disciplina</label>
                            <input type="text" wire:model.defer="createForm.disciplina" required class="w-full px-3.5 py-2 rounded-xl border border-gray-300 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 text-xs font-bold">
                        </div>
                        <div>
                            <label class="block text-xs font-bold uppercase tracking-wider text-gray-700 dark:text-gray-300 mb-1">Campus</label>
                            <input type="text" wire:model.defer="createForm.campus" required class="w-full px-3.5 py-2 rounded-xl border border-gray-300 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 text-xs font-bold">
                        </div>
                    </div>

                    <div class="flex items-center justify-end gap-3 pt-3 border-t border-gray-200 dark:border-gray-800">
                        <button type="button" wire:click="cancelCreate()" class="px-4 py-2 rounded-xl bg-gray-200 dark:bg-gray-700 text-xs font-bold">Cancelar</button>
                        <button type="submit" class="px-5 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white font-bold text-xs shadow-md">Criar Pacote</button>
                    </div>
                </form>
            </div>
        @endif

        <!-- ============================================================== -->
        <!-- MODAL: CARGA DE TURMA ESPECÍFICA                               -->
        <!-- ============================================================== -->
        @if ($isTurmaLoading)
            <div class="p-6 rounded-2xl bg-white dark:bg-gray-900 border-2 border-amber-500 shadow-xl space-y-6">
                <div class="flex items-center justify-between border-b border-gray-200 dark:border-gray-800 pb-3">
                    <div>
                        <span class="text-xs font-bold uppercase tracking-wider text-amber-600 dark:text-amber-400">Carga Direta</span>
                        <h3 class="text-lg font-black text-gray-900 dark:text-white mt-0.5">
                            Carregar Notas da Turma: {{ $turmaLoadingInfo['turma'] ?? $turmaLoadingKey }}
                        </h3>
                    </div>
                    <button type="button" wire:click="cancelTurmaLoader()" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 text-sm font-bold">✕ Cancelar</button>
                </div>

                <div class="space-y-4">
                    <div class="p-4 rounded-xl bg-gray-50 dark:bg-gray-800/60 border border-gray-200 dark:border-gray-700 space-y-2">
                        <label class="block text-xs font-bold uppercase tracking-wider text-gray-800 dark:text-gray-200">
                            Enviar Arquivo JSON da Turma (Ex: gerado pelo corretor óptico)
                        </label>
                        <input type="file" wire:model="turmaLoadingJsonFile" accept=".json,application/json" class="block w-full text-xs text-gray-700 dark:text-gray-300" />
                        <button type="button" wire:click="processTurmaJsonUpload" class="px-4 py-2 rounded-lg bg-teal-600 hover:bg-teal-500 text-white font-bold text-xs">
                            Processar &amp; Mesclar no Pacote
                        </button>
                    </div>

                    <div class="flex items-center justify-end gap-3 pt-3 border-t border-gray-200 dark:border-gray-800">
                        <button type="button" wire:click="cancelTurmaLoader()" class="px-4 py-2 rounded-xl bg-gray-200 dark:bg-gray-700 text-xs font-bold">Fechar</button>
                    </div>
                </div>
            </div>
        @endif

        <!-- ============================================================== -->
        <!-- MODAL: AUDITORIA / INSPEÇÃO DETALHADA                          -->
        <!-- ============================================================== -->
        @if ($viewingPackage)
            <div class="p-6 rounded-2xl bg-white dark:bg-gray-900 border-2 border-brand-teal shadow-xl space-y-4">
                <div class="flex items-center justify-between border-b border-gray-200 dark:border-gray-800 pb-3">
                    <div>
                        <span class="text-xs font-bold uppercase tracking-wider text-teal-600 dark:text-teal-400">Auditoria &amp; Inspeção</span>
                        <h3 class="text-lg font-black text-gray-900 dark:text-white mt-0.5">
                            {{ $viewingPackage['disciplina'] ?? '' }} ({{ $viewingPackage['campus'] ?? '' }})
                        </h3>
                    </div>
                    <button type="button" wire:click="closeViewPackage()" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 text-sm font-bold">✕ Fechar</button>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 text-xs">
                    <div class="p-3 rounded-xl bg-gray-50 dark:bg-gray-800">
                        <span class="text-gray-400 block font-semibold">Avaliação</span>
                        <span class="font-bold text-gray-900 dark:text-white">{{ $viewingPackage['avaliacao'] ?? '' }}</span>
                    </div>
                    <div class="p-3 rounded-xl bg-gray-50 dark:bg-gray-800">
                        <span class="text-gray-400 block font-semibold">Período</span>
                        <span class="font-bold text-gray-900 dark:text-white">{{ $viewingPackage['semestre'] ?? '' }}</span>
                    </div>
                    <div class="p-3 rounded-xl bg-gray-50 dark:bg-gray-800">
                        <span class="text-gray-400 block font-semibold">Cofres (Alunos)</span>
                        <span class="font-bold text-teal-600 dark:text-teal-400">{{ $viewingPackage['vault_count'] ?? 0 }} anônimos</span>
                    </div>
                </div>
            </div>
        @endif

        <!-- ============================================================== -->
        <!-- MODAL: CONFIRMAÇÃO DE EXCLUSÃO DE PACOTE                       -->
        <!-- ============================================================== -->
        @if ($confirmingDeletion)
            <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm">
                <div class="bg-white dark:bg-gray-900 rounded-2xl border-2 border-rose-500 shadow-2xl max-w-lg w-full p-6 space-y-5">
                    <div class="flex items-start gap-4">
                        <div class="w-12 h-12 rounded-xl bg-rose-500/15 text-rose-600 dark:text-rose-400 flex items-center justify-center text-2xl flex-shrink-0">⚠️</div>
                        <div class="space-y-1">
                            <h3 class="text-base font-black text-gray-900 dark:text-white">Confirmar Exclusão</h3>
                            <p class="text-xs text-gray-500 dark:text-gray-400 leading-relaxed">Deseja remover permanentemente este pacote do servidor?</p>
                        </div>
                    </div>
                    <div class="p-3 rounded-xl bg-rose-500/10 border border-rose-500/20 text-xs text-rose-700 dark:text-rose-300 font-bold">
                        {{ $deletingPackageLabel }} ({{ $deletingFilename }})
                    </div>
                    <div class="flex items-center justify-end gap-3 pt-2">
                        <button type="button" wire:click="cancelDelete()" class="px-4 py-2 rounded-xl bg-gray-200 dark:bg-gray-700 text-xs font-bold">Cancelar</button>
                        <button type="button" wire:click="executeDelete()" class="px-5 py-2 rounded-xl bg-rose-600 hover:bg-rose-500 text-white font-bold text-xs shadow-md">Sim, Excluir</button>
                    </div>
                </div>
            </div>
        @endif

    </div>
</x-filament-panels::page>
