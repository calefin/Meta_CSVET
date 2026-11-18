<x-filament-panels::page>
    <div class="space-y-6">
        <!-- Barra de Filtros e Busca -->
        <div class="fi-card p-4 rounded-xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-850 shadow-sm flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div class="flex-1 flex flex-col sm:flex-row gap-3">
                <!-- Busca Textual -->
                <div class="relative flex-1">
                    <span class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                        <svg class="h-5 w-5 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                        </svg>
                    </span>
                    <input 
                        type="text" 
                        wire:model.live.debounce.300ms="search" 
                        placeholder="Buscar por ID, aluno ou título..." 
                        class="block w-full pl-10 pr-3 py-2 border border-slate-350 dark:border-slate-800 rounded-lg bg-slate-50 dark:bg-slate-950 text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-teal-500 focus:border-teal-500 sm:text-sm transition duration-200"
                    />
                </div>

                <!-- Filtro por Tipo de Documento -->
                <div class="w-full sm:w-64">
                    <select 
                        wire:model.live="filterType"
                        class="block w-full px-3 py-2 border border-slate-350 dark:border-slate-800 rounded-lg bg-slate-50 dark:bg-slate-950 text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-teal-500 focus:border-teal-500 sm:text-sm transition duration-200"
                    >
                        <option value="">Todos os tipos de documentos</option>
                        <option value="Artigo Científico">Artigo Científico</option>
                        <option value="Avaliações de Estágio">Avaliações de Estágio</option>
                        <option value="Outro">Outro</option>
                        <option value="Plano de Estágio">Plano de Estágio</option>
                        <option value="Iniciação Científica">Projeto de IC</option>
                        <option value="Protocolo CEUA/CEP">Protocolo CEUA/CEP</option>
                        <option value="Relato de Caso">Relato de Caso</option>
                        <option value="Relatório">Relatório</option>
                        <option value="Relatório de Aula">Relatório de Aula</option>
                        <option value="Relatório de Estágio">Relatório de Estágio</option>
                        <option value="Relatório de Necropsia">Relatório de Necropsia</option>
                        <option value="TCC">TCC</option>
                        <option value="Termo de Aceite de IC">Termo de Aceite de IC</option>
                        <option value="Termo de Aceite de Orientação">Termo de Aceite de Orientação</option>
                        <option value="Trabalho de Congresso">Trabalho de Congresso</option>
                    </select>
                </div>
            </div>

            @if($search || $filterType)
                <button 
                    wire:click="$set('search', ''); $set('filterType', '')" 
                    class="px-4 py-2 border border-slate-300 dark:border-slate-700 hover:bg-slate-100 dark:hover:bg-slate-800 text-slate-700 dark:text-slate-300 rounded-lg text-sm transition font-medium"
                >
                    Limpar Filtros
                </button>
            @endif
        </div>

        <!-- Kanban Board Grid -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
            @php
                $stages = $this->getStages();
                $submissionsGrouped = $this->submissions;
            @endphp

            @foreach($stages as $stageKey => $stageInfo)
                @php
                    $subs = $submissionsGrouped->get($stageKey, collect());
                    $count = $subs->count();
                @endphp

                <!-- Coluna Kanban -->
                <div 
                    class="rounded-xl border border-slate-200/80 dark:border-slate-800/80 bg-slate-50/50 dark:bg-slate-900/30 flex flex-col p-4 transition-all duration-350"
                    ondragover="event.preventDefault(); this.classList.add('bg-slate-100/80', 'dark:bg-slate-850/50', 'border-teal-500', 'scale-[1.01]')"
                    ondragleave="this.classList.remove('bg-slate-100/80', 'dark:bg-slate-850/50', 'border-teal-500', 'scale-[1.01]')"
                    ondrop="this.classList.remove('bg-slate-100/80', 'dark:bg-slate-850/50', 'border-teal-500', 'scale-[1.01]'); const id = event.dataTransfer.getData('text/plain'); @this.call('updateSubmissionStatus', id, '{{ $stageKey }}')"
                >
                    <!-- Header da Coluna -->
                    <div class="flex items-center justify-between pb-3 border-b border-slate-200 dark:border-slate-800 mb-4">
                        <div class="flex items-center gap-2">
                            <span class="inline-block w-3 h-3 rounded-full bg-{{ $stageInfo['color'] }}-500"></span>
                            <h4 class="font-semibold text-slate-850 dark:text-slate-100 text-sm md:text-base">
                                {{ $stageInfo['title'] }}
                            </h4>
                        </div>
                        <span class="px-2 py-0.5 rounded-full text-xs font-bold bg-slate-200 dark:bg-slate-800 text-slate-700 dark:text-slate-350">
                            {{ $count }}
                        </span>
                    </div>

                    <!-- Lista de Cards -->
                    <div class="flex-1 space-y-3 overflow-y-auto max-h-[700px] min-h-[400px]">
                        @if($count === 0)
                            <div class="flex flex-col items-center justify-center h-full text-slate-400 dark:text-slate-500 text-xs py-8">
                                <svg class="w-8 h-8 mb-2 stroke-current" fill="none" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                                </svg>
                                <span>Nenhum documento</span>
                            </div>
                        @else
                            @foreach($subs as $sub)
                                @php
                                    // Determinar cor do badge por tipo de documento
                                    $typeColor = match($sub->document_type) {
                                        'TCC' => 'bg-blue-55/15 text-blue-700 border-blue-200/30 dark:bg-blue-900/20 dark:text-blue-400',
                                        'Iniciação Científica', 'Termo de Aceite de IC', 'Protocolo CEUA/CEP' => 'bg-amber-55/15 text-amber-700 border-amber-200/30 dark:bg-amber-900/20 dark:text-amber-400',
                                        'Relatório', 'Artigo Científico', 'Relato de Caso', 'Trabalho de Congresso' => 'bg-sky-55/15 text-sky-700 border-sky-200/30 dark:bg-sky-900/20 dark:text-sky-400',
                                        'Plano de Estágio', 'Termo de Aceite de Orientação', 'Avaliações de Estágio', 'Relatório de Necropsia' => 'bg-emerald-55/15 text-emerald-700 border-emerald-200/30 dark:bg-emerald-900/20 dark:text-emerald-400',
                                        default => 'bg-slate-55/15 text-slate-700 border-slate-200/30 dark:bg-slate-900/20 dark:text-slate-400',
                                    };
                                @endphp

                                <!-- Card Kanban -->
                                <div 
                                    draggable="true"
                                    ondragstart="event.dataTransfer.setData('text/plain', {{ $sub->id }}); this.classList.add('opacity-40', 'border-teal-500')"
                                    ondragend="this.classList.remove('opacity-40', 'border-teal-500')"
                                    class="p-4 bg-white dark:bg-slate-900 rounded-xl border border-slate-250 dark:border-slate-800 shadow-sm cursor-grab active:cursor-grabbing hover:border-slate-400 dark:hover:border-slate-700 transition duration-200 group flex flex-col gap-2.5"
                                >
                                    <!-- ID Processo & Tipo -->
                                    <div class="flex items-start justify-between gap-2">
                                        <span class="font-bold text-xs text-slate-800 dark:text-slate-200 font-mono tracking-tight bg-slate-100 dark:bg-slate-850 px-2 py-0.5 rounded border border-slate-200 dark:border-slate-800">
                                            {{ $sub->process_id }}
                                        </span>
                                        <span class="text-[10px] font-semibold px-2 py-0.5 rounded-full border {{ $typeColor }}">
                                            {{ $sub->document_type }}
                                        </span>
                                    </div>

                                    <!-- Título do Trabalho -->
                                    <div class="text-sm font-semibold text-slate-800 dark:text-slate-150 line-clamp-2 leading-snug group-hover:text-teal-600 dark:group-hover:text-teal-400 transition">
                                        {{ $sub->title }}
                                    </div>

                                    <!-- Aluno / Orientando -->
                                    <div class="flex items-center gap-2 text-xs text-slate-500 dark:text-slate-400 mt-1">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                        </svg>
                                        <span class="truncate font-medium">
                                            @php
                                                $studentName = $sub->student->user->name ?? 'N/A';
                                                $parts = explode(' ', trim($studentName));
                                                $simpleName = count($parts) > 1 ? $parts[0] . ' ' . end($parts) : $studentName;
                                            @endphp
                                            {{ $simpleName }}
                                        </span>
                                    </div>

                                    <!-- Metadados / Auditorias / Arquivos -->
                                    <div class="flex flex-wrap gap-1.5 items-center justify-between border-t border-slate-100 dark:border-slate-850 pt-2.5 mt-1 text-[11px] text-slate-450 dark:text-slate-500">
                                        <!-- Badges de Status Técnico -->
                                        <div class="flex items-center gap-1">
                                            @if($sub->abnt_verified_at)
                                                <span class="flex h-2 w-2 relative" title="Auditado com IA">
                                                    <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                                                    <span class="relative inline-flex rounded-full h-2 w-2 bg-emerald-500"></span>
                                                </span>
                                                <span class="text-emerald-600 dark:text-emerald-400 font-medium">IA OK</span>
                                            @endif
                                            @if($sub->document_type === 'Termo de Aceite de IC' && isset($sub->metadata['forensic_hash']))
                                                <span class="px-1.5 py-0.2 bg-teal-50 dark:bg-teal-950/20 text-teal-600 dark:text-teal-400 border border-teal-200/20 rounded font-mono font-bold text-[9px]" title="Assinatura Forense Ativa">
                                                    HASH
                                                </span>
                                            @endif
                                        </div>

                                        <!-- Links Rápidos e Ações -->
                                        <div class="flex items-center gap-2">
                                            @if($sub->google_drive_file_id)
                                                <a 
                                                    href="{{ (is_string($sub->google_drive_file_id) && str_contains($sub->google_drive_file_id, 'http')) ? $sub->google_drive_file_id : route('documento.download', ['id' => $sub->id, 'type' => 'original']) }}"
                                                    target="_blank"
                                                    class="text-slate-500 hover:text-teal-600 dark:text-slate-400 dark:hover:text-teal-400 p-0.5 rounded transition"
                                                    title="Abrir Arquivo Original"
                                                >
                                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                                    </svg>
                                                </a>
                                            @endif

                                            @if($sub->google_drive_returned_file_id)
                                                <a 
                                                    href="{{ (is_string($sub->google_drive_returned_file_id) && str_contains($sub->google_drive_returned_file_id, 'http')) ? $sub->google_drive_returned_file_id : route('documento.download', ['id' => $sub->id, 'type' => 'returned']) }}"
                                                    target="_blank"
                                                    class="text-emerald-500 hover:text-emerald-600 p-0.5 rounded transition"
                                                    title="Download Arquivo Retornado/Assinado"
                                                >
                                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                    </svg>
                                                </a>
                                            @endif

                                            <!-- Link para Editar na Tela do Filament -->
                                            <a 
                                                href="{{ \App\Filament\Resources\DocumentSubmissionResource::getUrl('edit', ['record' => $sub->id]) }}"
                                                class="text-teal-600 hover:text-teal-700 dark:text-teal-400 dark:hover:text-teal-350 font-bold p-0.5 rounded transition"
                                                title="Editar Detalhes"
                                            >
                                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                                </svg>
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</x-filament-panels::page>
