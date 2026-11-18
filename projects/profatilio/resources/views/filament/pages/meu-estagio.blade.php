<x-filament-panels::page>
    {{-- Tailwind não é compilado para páginas custom — CSS próprio abaixo. --}}
    <style>
        .me-empty {
            padding: 2.5rem 1rem; text-align: center; color: #64748b;
            background: #f8fafc; border: 1px dashed #cbd5e1; border-radius: .875rem;
        }
        .dark .me-empty { color: #94a3b8; background: #0f172a; border-color: #334155; }
        .me-card {
            background: #fff; border: 1px solid #e2e8f0; border-radius: .875rem;
            padding: 1rem 1.125rem; margin-bottom: 1rem;
        }
        .dark .me-card { background: #1e293b; border-color: rgba(255,255,255,.08); }
        .me-head { display: flex; flex-wrap: wrap; align-items: baseline; gap: .5rem 1.5rem; margin-bottom: .75rem; }
        .me-title { font-size: 1rem; font-weight: 700; color: #0f172a; }
        .dark .me-title { color: #f1f5f9; }
        .me-stat { font-size: .8125rem; color: #475569; }
        .dark .me-stat { color: #cbd5e1; }
        .me-stat strong { font-size: 1.125rem; color: #0f766e; }
        .dark .me-stat strong { color: #5eead4; }
        .me-table-wrap { overflow-x: auto; }
        .me-table { width: 100%; border-collapse: collapse; font-size: .8125rem; white-space: nowrap; }
        .me-table th { text-align: left; color: #64748b; font-weight: 600; font-size: .75rem; padding: .375rem .625rem; border-bottom: 1px solid #e2e8f0; }
        .dark .me-table th { color: #94a3b8; border-color: #334155; }
        .me-table td { padding: .5rem .625rem; border-bottom: 1px solid #f1f5f9; }
        .dark .me-table td { color: #e2e8f0; border-color: rgba(255,255,255,.06); }
        .me-note { font-size: .75rem; color: #64748b; margin-top: .25rem; }
    </style>

    @php($semesters = $this->getSemesters())

    @if (empty($semesters))
        <div class="me-empty">
            Nenhuma ficha de avaliação de estágio homologada até o momento.
        </div>
    @else
        @foreach ($semesters as $semester => $data)
            <div class="me-card">
                <div class="me-head">
                    <span class="me-title">Semestre {{ $semester }}</span>
                    <span class="me-stat">Locais de estágio: <strong>{{ $data['locations'] }}</strong></span>
                    <span class="me-stat">CH homologada: <strong>{{ number_format($data['hours'], 0, ',', '.') }}h</strong></span>
                </div>
                <div class="me-table-wrap">
                    <table class="me-table">
                        <thead>
                            <tr>
                                <th>Processo</th>
                                <th>Disciplina</th>
                                <th>Locais</th>
                                <th>CH</th>
                                <th>Enviada em</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($data['records'] as $record)
                                <tr>
                                    <td>{{ $record['process_id'] ?? '—' }}</td>
                                    <td>{{ $record['internship_stage'] }}</td>
                                    <td>{{ $record['locations'] ?? '—' }}</td>
                                    <td>{{ $record['hours'] !== null ? number_format($record['hours'], 0, ',', '.') . 'h' : '—' }}</td>
                                    <td>{{ $record['sent_at'] ?? '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endforeach
        <p class="me-note">Somente fichas já homologadas pelo professor entram nesta soma.</p>
    @endif
</x-filament-panels::page>
