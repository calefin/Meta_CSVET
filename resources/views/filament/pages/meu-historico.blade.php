<x-filament-panels::page>
    @php
        $entries  = $this->getTimelineEntries();
        $isAdmin  = auth()->user()?->role === 'admin';
    @endphp

    <div class="space-y-5">
        <style>
            .hist-card {
                background: #ffffff;
                border: 1px solid #e2e8f0;
                border-radius: 0.75rem;
                transition: border-color .2s, box-shadow .2s;
            }
            .hist-card:hover {
                border-color: #0d9488;
                box-shadow: 0 2px 8px rgba(13,148,136,.08);
            }
            .type-badge {
                display: inline-flex;
                align-items: center;
                padding: .15rem .55rem;
                border-radius: 9999px;
                font-size: .65rem;
                font-weight: 700;
                letter-spacing: .04em;
                border: 1px solid;
            }
            .proc-badge {
                font-family: 'Courier New', monospace;
                font-size: .7rem;
                font-weight: 700;
                color: #64748b;
                background: #f1f5f9;
                border: 1px solid #e2e8f0;
                border-radius: .35rem;
                padding: .1rem .45rem;
            }
            .hist-status { color: #0f172a; }
            .hist-meta   { color: #64748b; }
            .hist-comment {
                background: #f8fafc;
                border: 1px solid #e2e8f0;
                border-radius: .5rem;
                color: #334155;
            }
            .hist-connector { background: #e2e8f0; }
            .hist-counter   { color: #64748b; }
        </style>

        @if(empty($entries))
            <div class="text-center py-16 text-slate-400 text-sm">
                <div class="text-4xl mb-3">📋</div>
                <p class="font-semibold text-slate-300">Nenhum histórico encontrado.</p>
                <p class="font-light mt-1">As atualizações de status aparecerão aqui.</p>
            </div>
        @else
            <!-- Counter -->
            <div class="flex items-center gap-3 text-xs hist-counter font-light">
                <span class="w-2 h-2 rounded-full bg-teal-500 inline-block"></span>
                {{ count($entries) }} registro(s) — mais recente primeiro
            </div>

            <!-- Timeline -->
            <div class="relative">
                @foreach($entries as $i => $e)
                    @php
                        $dot      = $this->getDotColor($e['status']);
                        $typeCls  = $this->getTypeBadgeColor($e['document_type']);
                        $isLast   = $loop->last;
                    @endphp

                    <div class="relative flex gap-4 group">
                        {{-- Connector column --}}
                        <div class="flex flex-col items-center w-6 flex-shrink-0 pt-1">
                            <div class="w-3 h-3 rounded-full {{ $dot }} z-10 flex-shrink-0"></div>
                            @if(!$isLast)
                                <div class="w-px flex-1 hist-connector mt-1"></div>
                            @endif
                        </div>

                        {{-- Entry card --}}
                        <div class="hist-card p-4 flex-1 mb-3 min-w-0">
                            {{-- Header row --}}
                            <div class="flex items-start justify-between gap-3 flex-wrap mb-2">
                                <div class="flex items-center gap-2 flex-wrap min-w-0">
                                    <span class="proc-badge">{{ $e['process_id'] }}</span>
                                    <span class="type-badge {{ $typeCls }}">{{ $e['document_type'] }}</span>
                                </div>
                                <span class="text-xs hist-meta font-mono whitespace-nowrap flex-shrink-0">
                                    {{ $e['timestamp'] }}
                                </span>
                            </div>

                            {{-- Status --}}
                            <p class="text-sm font-semibold hist-status leading-snug">
                                {{ $this->getStatusLabel($e['status']) }}
                            </p>

                            {{-- Sub-info --}}
                            <div class="flex flex-wrap items-center gap-x-3 gap-y-0.5 mt-1">
                                @if($e['title'])
                                    <span class="text-xs hist-meta font-light truncate max-w-xs" title="{{ $e['title'] }}">
                                        {{ Str::limit($e['title'], 55) }}
                                    </span>
                                @endif
                                @if($isAdmin && $e['student_name'])
                                    <span class="text-xs hist-meta">· {{ $e['student_name'] }}</span>
                                @endif
                                <span class="text-xs hist-meta">· por {{ $e['actor'] }}</span>
                            </div>

                            {{-- Comment --}}
                            @if($e['comments'])
                                <div class="mt-2.5 p-2.5 hist-comment text-xs leading-relaxed">
                                    {!! $e['comments'] !!}
                                </div>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</x-filament-panels::page>
