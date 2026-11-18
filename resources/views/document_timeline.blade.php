<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Linha do Tempo - Processo {{ $submission->process_id }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Outfit:wght@400;500;600;750;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-hsl: 174, 75%, 38%;
            --primary: hsl(var(--primary-hsl));
            --primary-hover: hsl(174, 75%, 33%);
            --primary-light: hsl(174, 75%, 95%);
            
            --bg-gradient: linear-gradient(135deg, #f4f7f6 0%, #eef2f3 100%);
            --card-bg: #ffffff;
            --text-main: #1e293b;
            --text-muted: #64748b;
            --border-color: #e2e8f0;
            
            --success: #10b981;
            --success-light: #ecfdf5;
            --warning: #f59e0b;
            --warning-light: #fffbeb;
            --danger: #ef4444;
            --danger-light: #fef2f2;
            --info: #3b82f6;
            --info-light: #eff6ff;
        }

        .dark-mode {
            --card-bg: #0f172a;
            --text-main: #f8fafc;
            --text-muted: #94a3b8;
            --border-color: #334155;
            --primary-light: hsl(174, 75%, 12%);
            --bg-gradient: linear-gradient(135deg, #020617 0%, #0f172a 100%);
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: var(--bg-gradient);
            color: var(--text-main);
            min-height: 100vh;
            padding: 2rem 1rem;
            display: flex;
            justify-content: center;
            align-items: flex-start;
            transition: background 0.3s, color 0.3s;
        }

        .container {
            width: 100%;
            max-width: 800px;
            background: var(--card-bg);
            border-radius: 20px;
            border: 1px solid var(--border-color);
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.05);
            padding: 2.5rem;
            position: relative;
            overflow: hidden;
            transition: background 0.3s, border-color 0.3s;
        }

        /* Glassmorphic Accent Header */
        .container::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 6px;
            background: linear-gradient(90deg, var(--primary) 0%, #06b6d4 100%);
        }

        header {
            margin-bottom: 2.5rem;
        }

        .process-badge-container {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .process-id {
            font-family: 'Outfit', sans-serif;
            font-size: 1.75rem;
            font-weight: 750;
            color: var(--primary);
            letter-spacing: -0.5px;
        }

        .status-badge {
            padding: 0.5rem 1rem;
            border-radius: 9999px;
            font-size: 0.85rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            border: 1px solid transparent;
        }

        .status-badge::before {
            content: '';
            display: inline-block;
            width: 8px;
            height: 8px;
            border-radius: 50%;
        }

        /* Badge status styling classes */
        .badge-pending { background: var(--warning-light); color: #b45309; border-color: #fde68a; }
        .badge-pending::before { background: var(--warning); }
        
        .badge-reviewing { background: var(--info-light); color: #1d4ed8; border-color: #bfdbfe; }
        .badge-reviewing::before { background: var(--info); }
        
        .badge-completed { background: var(--success-light); color: #047857; border-color: #a7f3d0; }
        .badge-completed::before { background: var(--success); }
        
        .badge-failed { background: var(--danger-light); color: #b91c1c; border-color: #fca5a5; }
        .badge-failed::before { background: var(--danger); }

        .process-title {
            font-family: 'Outfit', sans-serif;
            font-size: 1.35rem;
            font-weight: 600;
            line-height: 1.4;
            margin-bottom: 1.25rem;
            color: var(--text-main);
        }

        .meta-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.25rem;
            padding: 1.25rem;
            background: var(--primary-light);
            border-radius: 12px;
            border: 1px solid var(--border-color);
            margin-bottom: 2.5rem;
        }

        .meta-item {
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
        }

        .meta-label {
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: var(--text-muted);
        }

        .meta-value {
            font-size: 0.95rem;
            font-weight: 600;
            color: var(--text-main);
        }

        /* TIMELINE GRAPHICS */
        .timeline {
            position: relative;
            padding-left: 2rem;
            margin-bottom: 3rem;
        }

        /* Line */
        .timeline::before {
            content: '';
            position: absolute;
            top: 0.5rem;
            bottom: 0.5rem;
            left: 7.5px;
            width: 3px;
            background: var(--border-color);
            border-radius: 2px;
        }

        .timeline-item {
            position: relative;
            margin-bottom: 2.5rem;
            transition: all 0.2s;
        }

        .timeline-item:last-child {
            margin-bottom: 0;
        }

        /* Circle Node */
        .timeline-node {
            position: absolute;
            left: -2rem;
            top: 0.25rem;
            width: 18px;
            height: 18px;
            border-radius: 50%;
            background: var(--card-bg);
            border: 3px solid var(--border-color);
            z-index: 2;
            transition: all 0.3s;
        }

        .timeline-item.active .timeline-node {
            border-color: var(--primary);
            box-shadow: 0 0 0 5px var(--primary-light);
        }

        .timeline-item:hover .timeline-node {
            transform: scale(1.2);
        }

        .timeline-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 0.5rem;
            margin-bottom: 0.5rem;
        }

        .timeline-actor-container {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .timeline-actor {
            font-weight: 700;
            font-size: 0.95rem;
        }

        .actor-role {
            font-size: 0.7rem;
            font-weight: 700;
            padding: 0.2rem 0.5rem;
            border-radius: 4px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .role-admin { background: #fee2e2; color: #991b1b; }
        .role-student { background: #dcfce7; color: #166534; }
        .role-system { background: #f1f5f9; color: #475569; }

        .timeline-time {
            font-size: 0.8rem;
            color: var(--text-muted);
            font-weight: 500;
        }

        .timeline-status {
            font-weight: 600;
            font-size: 1.05rem;
            color: var(--text-main);
            margin-bottom: 0.75rem;
        }

        .timeline-comments {
            background: var(--bg-gradient);
            border: 1px solid var(--border-color);
            border-radius: 10px;
            padding: 1rem;
            font-size: 0.9rem;
            line-height: 1.5;
            color: var(--text-main);
            position: relative;
            box-shadow: inset 0 1px 3px rgba(0,0,0,0.01);
        }

        .timeline-comments::after {
            content: '';
            position: absolute;
            top: -6px;
            left: 20px;
            border-width: 0 6px 6px;
            border-style: solid;
            border-color: var(--border-color) transparent;
            display: block;
            width: 0;
        }

        .footer-actions {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-top: 1px solid var(--border-color);
            padding-top: 1.5rem;
            margin-top: 2rem;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .btn {
            padding: 0.75rem 1.5rem;
            border-radius: 10px;
            font-weight: 600;
            text-decoration: none;
            font-size: 0.9rem;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            cursor: pointer;
            transition: all 0.2s;
            border: 1px solid transparent;
        }

        .btn-primary {
            background: var(--primary);
            color: white;
        }

        .btn-primary:hover {
            background: var(--primary-hover);
        }

        .btn-secondary {
            background: transparent;
            color: var(--text-muted);
            border-color: var(--border-color);
        }

        .btn-secondary:hover {
            background: var(--bg-gradient);
            color: var(--text-main);
        }

        .theme-switch {
            background: transparent;
            border: none;
            color: var(--text-muted);
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            transition: background 0.2s;
        }

        .theme-switch:hover {
            background: var(--bg-gradient);
        }

        @media (max-width: 600px) {
            .container {
                padding: 1.5rem;
            }
            .meta-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>

    @php
        $isConcluded = $submission->isConcluded();
        
        // Mapeamento de status para classes CSS
        $badgeClass = 'badge-pending';
        if (str_contains($submission->status, 'Concluído') || in_array($submission->status, ['approved', 'returned_signed', 'Assinatura Realizada e Auditada'])) {
            $badgeClass = 'badge-completed';
        } elseif (str_contains($submission->status, 'Análise') || str_contains($submission->status, 'Revisão') || $submission->status === 'reviewing') {
            $badgeClass = 'badge-reviewing';
        } elseif ($submission->status === 'failed') {
            $badgeClass = 'badge-failed';
        }
    @endphp

    <div class="container">
        <header>
            <div class="process-badge-container">
                <div class="process-id">{{ $submission->process_id }}</div>
                <span class="status-badge {{ $badgeClass }}">
                    {{ $submission->status }}
                </span>
            </div>
            <h1 class="process-title">{{ $submission->title }}</h1>
            
            <div class="meta-grid">
                <div class="meta-item">
                    <span class="meta-label">Estudante</span>
                    <span class="meta-value">{{ $submission->student && $submission->student->user ? $submission->student->user->name : 'N/A' }}</span>
                </div>
                <div class="meta-item">
                    <span class="meta-label">Tipo de Processo</span>
                    <span class="meta-value">{{ $submission->document_type }}</span>
                </div>
                <div class="meta-item">
                    <span class="meta-label">Situação</span>
                    <span class="meta-value">{{ $isConcluded ? '🔒 Processo Encerrado' : '⏳ Em Andamento' }}</span>
                </div>
            </div>
        </header>

        <main>
            <h2 style="font-family: 'Outfit', sans-serif; font-size: 1.25rem; font-weight: 600; margin-bottom: 1.5rem; color: var(--text-main);">
                Histórico de Atualizações
            </h2>

            <div class="timeline">
                @foreach(array_reverse($history) as $index => $log)
                    @php
                        $isCurrent = $index === 0;
                        $actor = $log['actor'] ?? 'Sistema';
                        $roleClass = 'role-system';
                        $roleLabel = 'Orientador';
                        
                        if ($actor === 'Sistema') {
                            $roleClass = 'role-system';
                            $roleLabel = 'Auto';
                        } elseif ($submission->student && $submission->student->user && $submission->student->user->name === $actor) {
                            $roleClass = 'role-student';
                            $roleLabel = 'Estudante';
                        }
                    @endphp
                    <div class="timeline-item {{ $isCurrent ? 'active' : '' }}">
                        <div class="timeline-node"></div>
                        <div class="timeline-header">
                            <div class="timeline-actor-container">
                                <span class="timeline-actor">{{ $actor }}</span>
                                <span class="actor-role {{ $roleClass }}">{{ $roleLabel }}</span>
                            </div>
                            <span class="timeline-time">{{ $log['timestamp'] ?? 'N/A' }}</span>
                        </div>
                        <div class="timeline-status">
                            {{ $log['status'] }}
                        </div>
                        @if(!empty($log['comments']))
                            <div class="timeline-comments">
                                {!! nl2br(e($log['comments'])) !!}
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>
        </main>

        <div class="footer-actions">
            <button onclick="window.close()" class="btn btn-secondary">
                Fechar Janela
            </button>
            <div style="display: flex; align-items: center; gap: 0.5rem;">
                <button onclick="document.body.classList.toggle('dark-mode')" class="theme-switch" title="Alternar Modo Escuro">
                    <svg style="width: 20px; height: 20px; fill: currentColor;" viewBox="0 0 24 24">
                        <path d="M12 3c.132 0 .263 0 .393.007a7.5 7.5 0 0 0 7.92 12.446A9 9 0 1 1 12 3zm0 2a7 7 0 1 0 5.48 11.31 9.5 9.5 0 0 1-5.11-8.23 9.47 9.47 0 0 1 3.23-5.07c-.19-.01-.39-.01-.6-.01z"/>
                    </svg>
                </button>
                @if(auth()->user()->role === 'admin')
                    <a href="{{ url('/admin/document-submissions/' . $submission->id . '/edit') }}" class="btn btn-primary">
                        Editar no Painel
                    </a>
                @else
                    <a href="{{ url('/admin') }}" class="btn btn-primary">
                        Painel do Aluno
                    </a>
                @endif
            </div>
        </div>
    </div>

</body>
</html>
