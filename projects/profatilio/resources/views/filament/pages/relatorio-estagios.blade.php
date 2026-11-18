<x-filament-panels::page>
    {{--
        Tailwind não é compilado para páginas custom neste painel — ver memória
        "filament-custom-pages-no-css". Todo o visual usa CSS próprio abaixo.
    --}}
    <style>
        .re-toolbar {
            display: flex; flex-wrap: wrap; align-items: center; gap: .75rem;
            background: #fff; border: 1px solid #e2e8f0; border-radius: .875rem;
            padding: .875rem 1rem; margin-bottom: 1.25rem;
        }
        .dark .re-toolbar { background: #1e293b; border-color: rgba(255,255,255,.08); }

        .re-toolbar label {
            font-size: .8125rem; font-weight: 600; color: #475569; margin-right: .5rem;
        }
        .dark .re-toolbar label { color: #cbd5e1; }

        .re-toolbar select {
            padding: .5rem 2rem .5rem .75rem; font-size: .8125rem; border: 1px solid #cbd5e1;
            border-radius: .5rem; background: #f8fafc; color: #0f172a; min-width: 160px;
        }
        .dark .re-toolbar select { background: #0f172a; border-color: #334155; color: #f1f5f9; }
        .re-toolbar select:focus { outline: none; border-color: #0d9488; }

        .re-empty {
            padding: 2.5rem 1rem; text-align: center; color: #64748b;
            background: #f8fafc; border: 1px dashed #cbd5e1; border-radius: .875rem;
        }
        .dark .re-empty { color: #94a3b8; background: #0f172a; border-color: #334155; }

        .re-table-wrap {
            overflow-x: auto; border: 1px solid #e2e8f0; border-radius: .875rem; background: #fff;
        }
        .dark .re-table-wrap { border-color: rgba(255,255,255,.08); background: #1e293b; }

        .re-table { width: 100%; border-collapse: collapse; font-size: .8125rem; white-space: nowrap; }
        .re-table th, .re-table td { padding: .625rem .875rem; text-align: left; }
        .re-table thead th {
            background: #f1f5f9; color: #475569; font-weight: 600; font-size: .75rem;
            text-transform: uppercase; letter-spacing: .03em;
            border-bottom: 1px solid #e2e8f0;
        }
        .dark .re-table thead th { background: #0f172a; color: #94a3b8; border-color: #334155; }
        .re-table tbody tr { border-bottom: 1px solid #f1f5f9; }
        .dark .re-table tbody tr { border-color: rgba(255,255,255,.06); }
        .re-table tbody tr:last-child { border-bottom: none; }
        .dark .re-table td { color: #e2e8f0; }

        .re-badge {
            display: inline-block; padding: .125rem .5rem; border-radius: 9999px;
            font-size: .6875rem; font-weight: 600; background: #e0f2fe; color: #0369a1;
        }
        .dark .re-badge { background: rgba(2,132,199,.18); color: #7dd3fc; }

        .re-toggle {
            border: 1px solid #cbd5e1; background: #f8fafc; color: #0f766e; font-weight: 600;
            font-size: .75rem; padding: .25rem .625rem; border-radius: .5rem; cursor: pointer;
        }
        .dark .re-toggle { background: #0f172a; border-color: #334155; color: #5eead4; }
        .re-detail td { background: #f8fafc; padding: .75rem 1rem 1rem 2rem; }
        .dark .re-detail td { background: #0f172a; }
        .re-sub { width: 100%; border-collapse: collapse; font-size: .78rem; }
        .re-sub th { text-align: left; color: #64748b; font-weight: 600; padding: .375rem .625rem; border-bottom: 1px solid #e2e8f0; }
        .dark .re-sub th { color: #94a3b8; border-color: #334155; }
        .re-sub td { padding: .375rem .625rem; background: transparent !important; }
        .re-link { color: #0d9488; font-weight: 600; text-decoration: none; margin-right: .75rem; }
        .re-link:hover { text-decoration: underline; }
        .dark .re-link { color: #5eead4; }

        .re-grade { font-weight: 700; }
        .re-grade-low { color: #dc2626; }
        .dark .re-grade-low { color: #f87171; }
        .re-toolbar input {
            padding: .5rem .75rem; font-size: .8125rem; border: 1px solid #cbd5e1;
            border-radius: .5rem; background: #f8fafc; color: #0f172a; min-width: 220px;
        }
        .dark .re-toolbar input { background: #0f172a; border-color: #334155; color: #f1f5f9; }
        .re-toolbar input:focus { outline: none; border-color: #0d9488; }
        .re-hint { font-size: .75rem; color: #64748b; margin-left: auto; }
        .dark .re-hint { color: #94a3b8; }

        .re-section-title {
            font-size: 1rem; font-weight: 700; color: #0f172a; margin: 1.5rem 0 .625rem;
            display: flex; align-items: center; gap: .5rem;
        }
        .dark .re-section-title { color: #f1f5f9; }
        .re-section-title small { font-size: .75rem; font-weight: 500; color: #64748b; }

        .re-muted { color: #94a3b8; font-size: .75rem; }
        .re-status {
            display: inline-block; padding: .0625rem .5rem; border-radius: 9999px;
            font-size: .6875rem; font-weight: 600; background: #fef3c7; color: #92400e;
        }
        .re-status-ok { background: #dcfce7; color: #166534; }
        .dark .re-status { background: rgba(217,119,6,.18); color: #fcd34d; }
        .dark .re-status-ok { background: rgba(22,163,74,.18); color: #86efac; }

    </style>

    <div class="re-toolbar">
        <div>
            <label for="re-semester">Semestre</label>
            <select id="re-semester" wire:model.live="semester">
                @foreach ($this->getSemesterOptions() as $value => $label)
                    <option value="{{ $value }}">{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="re-search">Aluno</label>
            <input id="re-search" type="search" wire:model.live.debounce.400ms="search" placeholder="Nome ou RGM">
        </div>
        <span class="re-hint">Inclui fichas ainda não homologadas · média e CH até o momento</span>
    </div>

    @php($sections = $this->getSections())

    @if (empty($sections))
        <div class="re-empty">
            Nenhuma ficha de avaliação de estágio encontrada{{ $semester !== 'todos' ? ' no semestre ' . $semester : '' }}.
        </div>
    @else
        @foreach ($sections as $sectionSemester => $rows)
            <div class="re-section-title">
                Semestre {{ $sectionSemester }}
                <small>{{ count($rows) }} {{ count($rows) === 1 ? 'aluno' : 'alunos' }}</small>
            </div>

            <div class="re-table-wrap" style="margin-bottom: 1rem;">
                <table class="re-table">
                    <thead>
                        <tr>
                            <th>Aluno</th>
                            <th>RGM</th>
                            <th>Campus</th>
                            <th>Período</th>
                            <th>Disciplina de Estágio</th>
                            <th>Fichas</th>
                            <th>CH Total</th>
                            <th>Média até o momento</th>
                            <th></th>
                        </tr>
                    </thead>
                    @foreach ($rows as $row)
                        <tbody x-data="{ open: false }" wire:key="re-{{ $sectionSemester }}-{{ md5($row['student_name'] . $row['registration']) }}">
                            <tr>
                                <td>{{ $row['student_name'] }}</td>
                                <td>{{ $row['registration'] ?? '—' }}</td>
                                <td>{{ $row['campus'] ?? '—' }}</td>
                                <td>{{ $row['period'] ?? '—' }}</td>
                                <td><span class="re-badge">{{ $row['internship_stage'] }}</span></td>
                                <td>
                                    {{ $row['submissions_count'] }}
                                    <span class="re-muted">({{ $row['homologated_count'] }} homolog.)</span>
                                </td>
                                <td>{{ number_format($row['total_hours'], 0, ',', '.') }}h</td>
                                <td>
                                    @if ($row['average_grade'] === null)
                                        —
                                    @else
                                        <span @class(['re-grade', 're-grade-low' => $row['average_grade'] < 6])>
                                            {{ number_format($row['average_grade'], 2, ',', '.') }}
                                        </span>
                                        <span class="re-muted">({{ $row['graded_count'] }} {{ $row['graded_count'] === 1 ? 'nota' : 'notas' }})</span>
                                    @endif
                                </td>
                                <td>
                                    <button type="button" class="re-toggle" x-on:click="open = !open"
                                        x-text="open ? 'Ocultar fichas' : 'Ver fichas'">Ver fichas</button>
                                </td>
                            </tr>
                            <tr class="re-detail" x-show="open" x-cloak>
                                <td colspan="9">
                                    <table class="re-sub">
                                        <thead>
                                            <tr>
                                                <th>Processo</th>
                                                <th>Situação</th>
                                                <th>Disciplina</th>
                                                <th>Locais</th>
                                                <th>CH</th>
                                                <th>Nota</th>
                                                <th>Enviada em</th>
                                                <th></th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach ($row['records'] as $ficha)
                                                <tr>
                                                    <td>
                                                        {{ $ficha['process_id'] ?? '—' }}
                                                        @if ($ficha['manual'])
                                                            <span class="re-muted">(manual)</span>
                                                        @endif
                                                    </td>
                                                    <td>
                                                        <span @class(['re-status', 're-status-ok' => $ficha['homologated']])>
                                                            {{ $ficha['homologated'] ? 'Homologada' : 'Pendente' }}
                                                        </span>
                                                    </td>
                                                    <td>{{ $ficha['internship_stage'] }}</td>
                                                    <td>{{ $ficha['locations_count'] ?? '—' }}</td>
                                                    <td>{{ $ficha['total_hours'] !== null ? number_format($ficha['total_hours'], 0, ',', '.') . 'h' : '—' }}</td>
                                                    <td>{{ $ficha['final_grade'] !== null ? number_format($ficha['final_grade'], 2, ',', '.') : '—' }}</td>
                                                    <td>{{ $ficha['sent_at'] ?? '—' }}</td>
                                                    <td><a class="re-link" href="{{ $ficha['edit_url'] }}" target="_blank">Abrir ficha</a></td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </td>
                            </tr>
                        </tbody>
                    @endforeach
                </table>
            </div>
        @endforeach
    @endif
</x-filament-panels::page>
