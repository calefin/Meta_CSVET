<x-filament-panels::page>
    {{--
        Classes utilitárias do Tailwind escritas direto no Blade não são
        compiladas no bundle deste painel (AdminPanelProvider não registra tema
        Vite customizado), então o visual abaixo usa CSS próprio via <style>,
        que sempre é aplicado independente do build.
    --}}
    <style>
        .aa-instructions {
            margin-top: 2rem; background: #f8fafc; border: 1px solid #99f6e4; border-radius: 1rem;
            padding: 1.5rem; line-height: 1.6; font-size: .875rem; color: #475569;
        }
        .dark .aa-instructions { background: rgba(15, 23, 42, .6); border-color: rgba(45, 212, 191, .25); color: #94a3b8; }

        .aa-instructions h4 { color: #0f172a; font-weight: 600; display: flex; align-items: center; gap: .5rem; margin-bottom: .75rem; }
        .dark .aa-instructions h4 { color: #fff; }
        .aa-instructions h4 svg { width: 1.25rem; height: 1.25rem; color: #0d9488; }
        .dark .aa-instructions h4 svg { color: #2dd4bf; }

        .aa-instructions p.intro { margin-bottom: 1rem; font-size: .75rem; }

        .aa-code {
            background: #e2e8f0; padding: .125rem .375rem; border-radius: .25rem; color: #0d9488; font-family: ui-monospace, monospace;
        }
        .dark .aa-code { background: #0f172a; color: #2dd4bf; }

        .aa-steps-grid { display: grid; grid-template-columns: 1fr; gap: 1.5rem; }
        @media (min-width: 768px) { .aa-steps-grid { grid-template-columns: repeat(3, 1fr); } }

        .aa-step-card { background: #fff; border: 1px solid #e2e8f0; border-radius: .75rem; padding: 1rem; }
        .dark .aa-step-card { background: rgba(15, 23, 42, .3); border-color: rgba(255, 255, 255, .05); }

        .aa-step-num {
            width: 1.5rem; height: 1.5rem; border-radius: 9999px; background: #ccfbf1; color: #0d9488;
            display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: .75rem; margin-bottom: .5rem;
        }
        .dark .aa-step-num { background: rgba(19, 78, 74, .6); color: #2dd4bf; }

        .aa-step-title { color: #0f172a; font-weight: 600; font-size: .75rem; text-transform: uppercase; letter-spacing: .05em; margin-bottom: .5rem; }
        .dark .aa-step-title { color: #fff; }
        .aa-step-text { font-size: .75rem; color: #64748b; line-height: 1.6; }
        .dark .aa-step-text { color: #94a3b8; }

        .aa-success-wrap { display: flex; flex-direction: column; gap: 1.5rem; }
        .aa-success-card {
            background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 1rem; padding: 1.5rem;
            text-align: center; display: flex; flex-direction: column; gap: .75rem; align-items: center;
        }
        .dark .aa-success-card { background: rgba(15, 23, 42, .5); border-color: rgba(16, 185, 129, .25); }

        .aa-success-icon {
            width: 3rem; height: 3rem; border-radius: 9999px; background: #dcfce7; color: #16a34a;
            display: flex; align-items: center; justify-content: center; border: 1px solid #bbf7d0;
        }
        .dark .aa-success-icon { background: #022c22; color: #34d399; border-color: rgba(16, 185, 129, .25); }
        .aa-success-icon svg { width: 1.5rem; height: 1.5rem; }

        .aa-success-title { font-size: 1.125rem; font-weight: 600; color: #0f172a; }
        .dark .aa-success-title { color: #fff; }
        .aa-success-text { font-size: .875rem; color: #64748b; max-width: 28rem; margin: 0 auto; }
        .dark .aa-success-text { color: #94a3b8; }

        .aa-files-card { background: #fff; border: 1px solid #e2e8f0; border-radius: 1rem; overflow: hidden; }
        .dark .aa-files-card { background: rgba(15, 23, 42, .4); border-color: rgba(255, 255, 255, .05); }
        .aa-files-table { width: 100%; text-align: left; border-collapse: collapse; }
        .aa-files-table thead tr { background: #f8fafc; border-bottom: 1px solid #e2e8f0; color: #475569; font-size: .75rem; font-weight: 600; text-transform: uppercase; letter-spacing: .05em; }
        .dark .aa-files-table thead tr { background: rgba(2, 6, 23, .5); border-color: rgba(255, 255, 255, .05); color: #cbd5e1; }
        .aa-files-table th, .aa-files-table td { padding: 1rem; }
        .aa-files-table th:last-child { text-align: right; color: #0d9488; }
        .dark .aa-files-table th:last-child { color: #2dd4bf; }
        .aa-files-table tbody tr { border-bottom: 1px solid #f1f5f9; font-size: .875rem; }
        .dark .aa-files-table tbody tr { border-color: rgba(255, 255, 255, .05); }
        .aa-files-table tbody tr:hover { background: #f8fafc; }
        .dark .aa-files-table tbody tr:hover { background: rgba(255, 255, 255, .03); }
        .aa-file-name { font-weight: 500; color: #0f172a; }
        .dark .aa-file-name { color: #fff; }
        .aa-file-label { font-size: .75rem; color: #64748b; }
        .dark .aa-file-label { color: #94a3b8; }

        .aa-download-btn {
            display: inline-flex; align-items: center; gap: .375rem; padding: .375rem .75rem; font-size: .75rem;
            font-weight: 600; color: #0d9488; background: #f0fdfa; border: 1px solid #99f6e4; border-radius: .5rem;
            cursor: pointer; transition: background .2s;
        }
        .aa-download-btn:hover { background: #ccfbf1; }
        .dark .aa-download-btn { color: #2dd4bf; background: rgba(19, 78, 74, .3); border-color: rgba(45, 212, 191, .25); }
        .dark .aa-download-btn:hover { background: rgba(19, 78, 74, .5); }
        .aa-download-btn svg { width: .875rem; height: .875rem; }
    </style>

    @if(empty(session('govbr_standalone_download_paths')))
        <form wire:submit.prevent="submit">
            {{ $this->form }}

            <div style="margin-top: 1.5rem; display: flex; align-items: center; justify-content: flex-end;">
                <x-filament::button type="submit" size="lg">
                    Validar e Homologar Lote
                </x-filament::button>
            </div>
        </form>

        <!-- Guia de Instruções PJeOffice / Adobe / Gov.br -->
        <div class="aa-instructions">
            <h4>
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                Instruções de Assinatura Offline (e-CPF A3/A1)
            </h4>

            <p class="intro">
                Devido a restrições de segurança do aplicativo PJeOffice Pro (bloqueio de conexões originadas fora de domínios governamentais <code class="aa-code">*.jus.br</code>, <code class="aa-code">*.gov.br</code>), o fluxo de assinatura direta via navegador não é permitido em portais privados.
                <br><br>
                <strong>Para assinar seus documentos de forma 100% gratuita, segura e em conformidade legal com o e-CPF A3, utilize o fluxo abaixo:</strong>
            </p>

            <div class="aa-steps-grid">
                <!-- Passo 1 -->
                <div class="aa-step-card">
                    <div class="aa-step-num">1</div>
                    <h5 class="aa-step-title">Assinar o PDF no PC</h5>
                    <p class="aa-step-text">
                        Abra o PDF no <strong>Adobe Acrobat Reader</strong> (ou use a opção "Assinar Arquivo Local" clicando com o botão direito no ícone do <strong>PJeOffice</strong> no relógio do computador) e assine o arquivo digitalmente com seu e-CPF A3.
                    </p>
                </div>

                <!-- Passo 2 -->
                <div class="aa-step-card">
                    <div class="aa-step-num">2</div>
                    <h5 class="aa-step-title">Fazer Upload do Lote</h5>
                    <p class="aa-step-text">
                        Arraste os arquivos PDF <strong>já assinados</strong> para a seção acima, preencha o título de referência e clique em <strong>"Validar e Homologar Lote"</strong>.
                    </p>
                </div>

                <!-- Passo 3 -->
                <div class="aa-step-card">
                    <div class="aa-step-num">3</div>
                    <h5 class="aa-step-title">Gerar Termo de Validação</h5>
                    <p class="aa-step-text">
                        O portal extrairá o hash da assinatura ICP-Brasil, auditará a integridade do e-CPF e emitirá um <strong>Termo de Homologação permanente</strong> com QR Code de verificação.
                    </p>
                </div>
            </div>
        </div>
    @else
        <!-- Tela de Sucesso: Downloads dos Termos -->
        <div class="aa-success-wrap">
            <div class="aa-success-card">
                <div class="aa-success-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>
                <h3 class="aa-success-title">Lote Processado com Sucesso!</h3>
                <p class="aa-success-text">
                    Os documentos foram auditados criptograficamente. O hash imutável SHA-256 e o e-CPF foram vinculados ao registro permanente de auditoria da plataforma.
                </p>
                <div>
                    <x-filament::button color="gray" wire:click="clearDownloadSession">
                        Homologar Novo Lote
                    </x-filament::button>
                </div>
            </div>

            <!-- Tabela de Arquivos Homologados -->
            <div class="aa-files-card">
                <div class="overflow-x-auto">
                <table class="aa-files-table">
                    <thead>
                        <tr>
                            <th>Documento Homologado</th>
                            <th>Download das Certidões</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach(session('govbr_standalone_download_paths') as $file)
                            <tr>
                                <td>
                                    <div class="aa-file-name">{{ $file['filename'] }}</div>
                                    <div class="aa-file-label">{{ $file['label'] }}</div>
                                </td>
                                <td style="text-align: right;">
                                    <button wire:click="downloadStandaloneFile('{{ $file['path'] }}', '{{ $file['filename'] }}')" class="aa-download-btn">
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                                        </svg>
                                        Baixar {{ $file['label'] }}
                                    </button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                </div>
            </div>
        </div>
    @endif
</x-filament-panels::page>
