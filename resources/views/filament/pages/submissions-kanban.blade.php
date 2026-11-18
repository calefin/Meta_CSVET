<x-filament-panels::page>
    {{--
        Classes utilitárias do Tailwind escritas direto no Blade não são compiladas
        no bundle deste painel (AdminPanelProvider não registra tema Vite customizado
        para páginas custom), então todo o visual abaixo usa CSS próprio via <style>,
        que sempre é aplicado independente de build. Ver memória "filament-custom-pages-no-css".
    --}}
    <style>
        .kb-toolbar {
            display: flex; flex-wrap: wrap; align-items: center; gap: .75rem;
            background: #fff; border: 1px solid #e2e8f0; border-radius: .875rem;
            padding: .875rem 1rem; margin-bottom: 1.25rem;
        }
        .dark .kb-toolbar { background: #1e293b; border-color: rgba(255,255,255,.08); }

        .kb-search { position: relative; flex: 1 1 260px; min-width: 200px; }
        .kb-search svg {
            position: absolute; left: .75rem; top: 50%; transform: translateY(-50%);
            width: 1rem; height: 1rem; color: #94a3b8; pointer-events: none;
        }
        .kb-search input {
            width: 100%; padding: .5rem .75rem .5rem 2.25rem; font-size: .8125rem;
            border: 1px solid #cbd5e1; border-radius: .5rem; background: #f8fafc; color: #0f172a;
        }
        .dark .kb-search input { background: #0f172a; border-color: #334155; color: #f1f5f9; }
        .kb-search input:focus { outline: none; border-color: #0d9488; box-shadow: 0 0 0 3px rgba(13,148,136,.15); }

        .kb-select select {
            padding: .5rem 2rem .5rem .75rem; font-size: .8125rem; border: 1px solid #cbd5e1;
            border-radius: .5rem; background: #f8fafc; color: #0f172a; min-width: 200px;
        }
        .dark .kb-select select { background: #0f172a; border-color: #334155; color: #f1f5f9; }
        .kb-select select:focus { outline: none; border-color: #0d9488; }

        .kb-clear {
            padding: .5rem .875rem; font-size: .8125rem; font-weight: 600; border-radius: .5rem;
            border: 1px solid #cbd5e1; background: transparent; color: #475569; cursor: pointer;
        }
        .kb-clear:hover { background: #f1f5f9; }
        .dark .kb-clear { border-color: #334155; color: #cbd5e1; }
        .dark .kb-clear:hover { background: #334155; }

        .kb-legend { display: flex; align-items: center; gap: 1rem; margin-bottom: 1rem; flex-wrap: wrap; }
        .kb-legend-item { display: flex; align-items: center; gap: .375rem; font-size: .75rem; color: #64748b; }
        .dark .kb-legend-item { color: #94a3b8; }
        .kb-legend-dot { width: .5rem; height: .5rem; border-radius: 9999px; flex-shrink: 0; }

        /* Board: colunas lado a lado com rolagem horizontal, no estilo Trello/Linear/Jira */
        .kb-board {
            display: flex; align-items: flex-start; gap: 1rem;
            overflow-x: auto; padding-bottom: .75rem;
        }
        .kb-board::-webkit-scrollbar { height: 10px; }
        .kb-board::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 9999px; }
        .dark .kb-board::-webkit-scrollbar-thumb { background: #334155; }

        .kb-column {
            flex: 0 0 320px; width: 320px; display: flex; flex-direction: column;
            background: #f8fafc; border: 1px solid #e2e8f0; border-radius: .875rem;
            max-height: calc(100vh - 280px); min-height: 420px;
            border-top: 3px solid var(--kb-accent);
            transition: background-color .15s ease, box-shadow .15s ease;
        }
        .dark .kb-column { background: rgba(15, 23, 42, .5); border-color: rgba(255,255,255,.08); border-top-color: var(--kb-accent); }
        .kb-column.is-dragover { background: var(--kb-accent-soft); box-shadow: inset 0 0 0 2px var(--kb-accent); }

        .kb-column-header {
            display: flex; align-items: center; justify-content: space-between; gap: .5rem;
            padding: .875rem 1rem; border-bottom: 1px solid #e2e8f0;
        }
        .dark .kb-column-header { border-color: rgba(255,255,255,.08); }
        .kb-column-title { display: flex; align-items: center; gap: .5rem; min-width: 0; }
        .kb-column-title h4 { font-size: .875rem; font-weight: 700; color: #0f172a; white-space: nowrap; }
        .dark .kb-column-title h4 { color: #f1f5f9; }
        .kb-dot { width: .625rem; height: .625rem; border-radius: 9999px; background: var(--kb-accent); flex-shrink: 0; }
        .kb-count {
            font-size: .6875rem; font-weight: 800; padding: .125rem .5rem; border-radius: 9999px;
            background: var(--kb-accent-soft); color: var(--kb-accent); flex-shrink: 0;
        }

        .kb-column-body { flex: 1; overflow-y: auto; padding: .75rem; display: flex; flex-direction: column; gap: .625rem; }
        .kb-column-body::-webkit-scrollbar { width: 8px; }
        .kb-column-body::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 9999px; }
        .dark .kb-column-body::-webkit-scrollbar-thumb { background: #334155; }

        .kb-empty {
            display: flex; flex-direction: column; align-items: center; justify-content: center;
            color: #94a3b8; font-size: .75rem; padding: 2.5rem 1rem; gap: .5rem; text-align: center;
        }
        .kb-empty svg { width: 2rem; height: 2rem; stroke-width: 1.5; }

        .kb-card {
            background: #fff; border: 1px solid #e2e8f0; border-left: 3px solid var(--kb-type-color);
            border-radius: .625rem; padding: .75rem .875rem; cursor: grab;
            box-shadow: 0 1px 2px rgba(15, 23, 42, .04);
            display: flex; flex-direction: column; gap: .5rem;
            transition: box-shadow .15s ease, transform .15s ease, opacity .15s ease;
        }
        .kb-card:hover { box-shadow: 0 4px 14px rgba(15, 23, 42, .1); transform: translateY(-1px); }
        .kb-card:active { cursor: grabbing; }
        .kb-card.is-dragging { opacity: .4; }
        .dark .kb-card { background: #1e293b; border-color: rgba(255,255,255,.08); }

        .kb-card-top { display: flex; align-items: flex-start; justify-content: space-between; gap: .5rem; }
        .kb-process-id {
            font-family: ui-monospace, monospace; font-size: .6875rem; font-weight: 700; color: #334155;
            background: #f1f5f9; border: 1px solid #e2e8f0; border-radius: .375rem; padding: .0625rem .375rem;
        }
        .dark .kb-process-id { background: #0f172a; border-color: rgba(255,255,255,.08); color: #cbd5e1; }

        .kb-type-badge {
            font-size: .625rem; font-weight: 700; padding: .125rem .5rem; border-radius: 9999px;
            background: var(--kb-type-soft); color: var(--kb-type-color); white-space: nowrap;
        }

        .kb-card-title {
            font-size: .8125rem; font-weight: 600; color: #0f172a; line-height: 1.35;
            display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;
        }
        .dark .kb-card-title { color: #f1f5f9; }

        .kb-card-student { display: flex; align-items: center; gap: .375rem; font-size: .75rem; color: #64748b; font-weight: 500; }
        .dark .kb-card-student { color: #94a3b8; }
        .kb-card-student svg { width: .875rem; height: .875rem; flex-shrink: 0; }

        .kb-card-footer {
            display: flex; align-items: center; justify-content: space-between; gap: .5rem;
            border-top: 1px solid #f1f5f9; padding-top: .5rem; margin-top: .125rem;
        }
        .dark .kb-card-footer { border-color: rgba(255,255,255,.06); }

        .kb-badges { display: flex; align-items: center; gap: .375rem; flex-wrap: wrap; }
        .kb-badge-ia {
            display: inline-flex; align-items: center; gap: .25rem; font-size: .625rem; font-weight: 700;
            color: #059669;
        }
        .dark .kb-badge-ia { color: #34d399; }
        .kb-pulse { position: relative; width: .5rem; height: .5rem; }
        .kb-pulse span:first-child {
            position: absolute; inset: 0; border-radius: 9999px; background: #34d399; opacity: .6;
            animation: kb-ping 1.6s cubic-bezier(0,0,.2,1) infinite;
        }
        .kb-pulse span:last-child { position: relative; display: block; width: .5rem; height: .5rem; border-radius: 9999px; background: #10b981; }
        @keyframes kb-ping { 75%, 100% { transform: scale(2.2); opacity: 0; } }

        .kb-badge-hash {
            font-family: ui-monospace, monospace; font-size: .5625rem; font-weight: 800; letter-spacing: .03em;
            padding: .0625rem .3125rem; border-radius: .25rem; background: #ccfbf1; color: #0d9488;
        }
        .dark .kb-badge-hash { background: rgba(13,148,136,.2); color: #2dd4bf; }

        .kb-badge-age {
            display: inline-flex; align-items: center; gap: .25rem; font-size: .625rem; font-weight: 700;
            padding: .0625rem .375rem; border-radius: 9999px;
        }
        .kb-badge-age.is-ok { color: #94a3b8; }
        .kb-badge-age.is-warning { color: #b45309; background: #fef3c7; }
        .dark .kb-badge-age.is-warning { color: #fbbf24; background: rgba(180,83,9,.2); }
        .kb-badge-age.is-danger { color: #be123c; background: #ffe4e6; }
        .dark .kb-badge-age.is-danger { color: #fb7185; background: rgba(190,18,60,.2); }
        .kb-badge-age svg { width: .6875rem; height: .6875rem; }

        .kb-actions { display: flex; align-items: center; gap: .375rem; }
        .kb-actions a {
            display: inline-flex; align-items: center; justify-content: center; width: 1.5rem; height: 1.5rem;
            border-radius: .375rem; color: #64748b; transition: background-color .15s ease, color .15s ease;
        }
        .dark .kb-actions a { color: #94a3b8; }
        .kb-actions a:hover { background: #f1f5f9; color: #0d9488; }
        .dark .kb-actions a:hover { background: #334155; color: #2dd4bf; }
        .kb-actions a.is-success:hover { color: #059669; }
        .kb-actions a svg { width: 1rem; height: 1rem; }
    </style>

    <div>
        @php
            $typeColors = [
                'TCC' => ['#4f46e5', '#e0e7ff'],
                'Concepção de TCC' => ['#4f46e5', '#e0e7ff'],
                'Objetivos de TCC' => ['#4f46e5', '#e0e7ff'],
                'Introdução de TCC' => ['#4f46e5', '#e0e7ff'],
                'Materiais e Métodos de TCC' => ['#4f46e5', '#e0e7ff'],
                'Resultados de TCC' => ['#4f46e5', '#e0e7ff'],
                'Discussão de TCC' => ['#4f46e5', '#e0e7ff'],
                'Conclusão de TCC' => ['#4f46e5', '#e0e7ff'],
                'Iniciação Científica' => ['#d97706', '#fef3c7'],
                'Termo de Aceite de IC' => ['#d97706', '#fef3c7'],
                'Relatório' => ['#0284c7', '#e0f2fe'],
                'Artigo Científico' => ['#0284c7', '#e0f2fe'],
                'Relato de Caso' => ['#0284c7', '#e0f2fe'],
                'Trabalho de Congresso' => ['#0284c7', '#e0f2fe'],
                'Resumo para Evento' => ['#0284c7', '#e0f2fe'],
                'Banner para Apresentação' => ['#0284c7', '#e0f2fe'],
                'Plano de Estágio' => ['#059669', '#d1fae5'],
                'Projeto de TCC (Assinatura)' => ['#059669', '#d1fae5'],
                'TCC Final (Assinatura)' => ['#059669', '#d1fae5'],
                'Termo de Aceite de Orientação' => ['#059669', '#d1fae5'],
                'Avaliações de Estágio' => ['#059669', '#d1fae5'],
                'Relatório de Necropsia' => ['#059669', '#d1fae5'],
            ];
            $defaultTypeColor = ['#64748b', '#f1f5f9'];
        @endphp

        <!-- Barra de Filtros e Busca -->
        <div class="kb-toolbar">
            <div class="kb-search">
                <svg fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                </svg>
                <input type="text" wire:model.live.debounce.300ms="search" placeholder="Buscar por ID, aluno ou título..." />
            </div>

            <div class="kb-select">
                <select wire:model.live="filterType">
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
                    <option value="Projeto de TCC (Assinatura)">Projeto de TCC (somente assinatura)</option>
                    <option value="TCC Final (Assinatura)">TCC Final (somente assinatura)</option>
                    <option value="Trabalho de Congresso">Trabalho de Congresso</option>
                </select>
            </div>

            @if($search || $filterType)
                <button type="button" class="kb-clear" wire:click="$set('search', ''); $set('filterType', '')">
                    Limpar Filtros
                </button>
            @endif
        </div>

        @php
            $stages = $this->getStages();
            $submissionsGrouped = $this->submissions;
        @endphp

        <!-- Legenda de urgência (mesmo padrão do widget "Ações Urgentes") -->
        <div class="kb-legend">
            <span class="kb-legend-item"><span class="kb-legend-dot" style="background:#94a3b8"></span> Normal</span>
            <span class="kb-legend-item"><span class="kb-legend-dot" style="background:#d97706"></span> Parado ≥ 3 dias</span>
            <span class="kb-legend-item"><span class="kb-legend-dot" style="background:#e11d48"></span> Parado ≥ 7 dias</span>
        </div>

        <!-- Kanban Board (colunas horizontais, com rolagem lateral) -->
        <div class="kb-board">
            @foreach($stages as $stageKey => $stageInfo)
                @php
                    $subs = $submissionsGrouped->get($stageKey, collect());
                    $count = $subs->count();
                @endphp

                <div
                    class="kb-column"
                    style="--kb-accent: {{ $stageInfo['color'] }}; --kb-accent-soft: {{ $stageInfo['colorSoft'] }};"
                    ondragover="event.preventDefault(); this.classList.add('is-dragover')"
                    ondragleave="this.classList.remove('is-dragover')"
                    ondrop="this.classList.remove('is-dragover'); const id = event.dataTransfer.getData('text/plain'); @this.call('updateSubmissionStatus', id, '{{ $stageKey }}')"
                >
                    <div class="kb-column-header">
                        <div class="kb-column-title">
                            <span class="kb-dot"></span>
                            <h4 title="{{ $stageInfo['description'] }}">{{ $stageInfo['title'] }}</h4>
                        </div>
                        <span class="kb-count">{{ $count }}</span>
                    </div>

                    <div class="kb-column-body">
                        @if($count === 0)
                            <div class="kb-empty">
                                <svg fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                                </svg>
                                <span>Nenhum documento</span>
                            </div>
                        @else
                            @foreach($subs as $sub)
                                @php
                                    [$typeColor, $typeSoft] = $typeColors[$sub->document_type] ?? $defaultTypeColor;
                                    $daysStalled = $sub->updated_at ? (int) $sub->updated_at->diffInDays(now()) : 0;
                                    $studentName = $sub->student->user->name ?? 'N/A';
                                    $parts = explode(' ', trim($studentName));
                                    $simpleName = count($parts) > 1 ? $parts[0] . ' ' . end($parts) : $studentName;
                                @endphp

                                <div
                                    class="kb-card"
                                    style="--kb-type-color: {{ $typeColor }}; --kb-type-soft: {{ $typeSoft }};"
                                    draggable="true"
                                    ondragstart="event.dataTransfer.setData('text/plain', {{ $sub->id }}); this.classList.add('is-dragging')"
                                    ondragend="this.classList.remove('is-dragging')"
                                >
                                    <div class="kb-card-top">
                                        <span class="kb-process-id">{{ $sub->process_id }}</span>
                                        <span class="kb-type-badge">{{ $sub->document_type }}</span>
                                    </div>

                                    <div class="kb-card-title">{{ $sub->title }}</div>

                                    <div class="kb-card-student">
                                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                        </svg>
                                        <span>{{ $simpleName }}</span>
                                    </div>

                                    <div class="kb-card-footer">
                                        <div class="kb-badges">
                                            @if($sub->abnt_verified_at)
                                                <span class="kb-badge-ia" title="Auditado com IA">
                                                    <span class="kb-pulse"><span></span><span></span></span>
                                                    IA OK
                                                </span>
                                            @endif
                                            @if($sub->document_type === 'Termo de Aceite de IC' && isset($sub->metadata['forensic_hash']))
                                                <span class="kb-badge-hash" title="Assinatura Forense Ativa">HASH</span>
                                            @endif
                                            @if($stageKey !== 'completed')
                                                @if($daysStalled >= 7)
                                                    <span class="kb-badge-age is-danger" title="Parado há {{ $daysStalled }} dia(s)">
                                                        <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M17.657 18.657A8 8 0 016.343 7.343S7 9 9 10c0-2 .5-5 2.986-7C14 5 16.09 5.777 17.656 7.343A7.975 7.975 0 0120 13a7.975 7.975 0 01-2.343 5.657z" /></svg>
                                                        {{ $daysStalled }}d
                                                    </span>
                                                @elseif($daysStalled >= 3)
                                                    <span class="kb-badge-age is-warning" title="Parado há {{ $daysStalled }} dia(s)">
                                                        <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                                        {{ $daysStalled }}d
                                                    </span>
                                                @endif
                                            @endif
                                        </div>

                                        <div class="kb-actions">
                                            @if($sub->google_drive_file_id)
                                                <a href="{{ (is_string($sub->google_drive_file_id) && str_contains($sub->google_drive_file_id, 'http')) ? $sub->google_drive_file_id : route('documento.download', ['id' => $sub->id, 'type' => 'original']) }}" target="_blank" title="Abrir Arquivo Original">
                                                    <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                                    </svg>
                                                </a>
                                            @endif
                                            @if($sub->google_drive_returned_file_id)
                                                <a class="is-success" href="{{ (is_string($sub->google_drive_returned_file_id) && str_contains($sub->google_drive_returned_file_id, 'http')) ? $sub->google_drive_returned_file_id : route('documento.download', ['id' => $sub->id, 'type' => 'returned']) }}" target="_blank" title="Download Arquivo Retornado/Assinado">
                                                    <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                    </svg>
                                                </a>
                                            @endif
                                            <a href="{{ \App\Filament\Resources\DocumentSubmissionResource::getUrl('edit', ['record' => $sub->id]) }}" title="Editar Detalhes">
                                                <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
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
