<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Validador & Verificador de Assinatura Digital - profatilio.com.br</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['Plus Jakarta Sans', 'sans-serif'],
                        outfit: ['Outfit', 'sans-serif'],
                    },
                    colors: {
                        dark: {
                            950: '#030712',
                            900: '#0b0f19',
                            800: '#1f2937',
                            700: '#374151',
                        },
                        brand: {
                            teal: '#14b8a6',
                            indigo: '#6366f1',
                            violet: '#8b5cf6',
                        }
                    }
                }
            }
        }
    </script>
    <style>
        .glass {
            background: rgba(15, 23, 42, 0.65);
            backdrop-filter: blur(16px);
            border: 1px rgba(255, 255, 255, 0.08) solid;
        }
        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background-color: #030712;
        }
    </style>
</head>
<body class="text-slate-150 min-h-screen flex flex-col justify-between overflow-x-hidden relative">

    <!-- Background Grid -->
    <div class="absolute inset-0 bg-[linear-gradient(to_right,#1f2937_1px,transparent_1px),linear-gradient(to_bottom,#1f2937_1px,transparent_1px)] bg-[size:4rem_4rem] [mask-image:radial-gradient(ellipse_60%_50%_at_50%_0%,#000_70%,transparent_100%)] opacity-[0.12] pointer-events-none"></div>

    <!-- Header -->
    <header class="w-full h-20 glass border-b border-white/5 flex items-center relative z-10">
        <div class="max-w-7xl mx-auto px-6 w-full flex items-center justify-between">
            <a href="https://profatilio.com.br" class="flex items-center gap-3">
                <span class="font-outfit font-bold text-xl tracking-tight bg-gradient-to-r from-white via-slate-200 to-slate-400 bg-clip-text text-transparent font-extrabold">profatilio.com.br</span>
            </a>
            <a href="https://profatilio.com.br" class="text-xs font-semibold text-slate-400 hover:text-white transition-colors">Voltar ao Portal</a>
        </div>
    </header>

    <!-- Main -->
    <main class="flex-grow flex items-center justify-center py-16 px-6 relative z-10">
        <div class="max-w-2xl w-full">
            
            @if(session('error'))
                <div class="mb-6 p-4 rounded-xl bg-red-950/30 border border-red-500/20 text-red-400 text-xs">
                    ⚠️ {{ session('error') }}
                </div>
            @endif

            @if(isset($checked) && $checked)
                <!-- RESULT STATE -->
                <div class="glass p-8 sm:p-10 rounded-3xl border border-white/5 shadow-2xl space-y-6">
                    <div class="text-center space-y-2">
                        <span class="text-xs font-bold text-brand-teal uppercase tracking-widest font-outfit">Resultado da Auditoria</span>
                        <h2 class="font-outfit font-extrabold text-2xl text-white">Verificação de Assinatura</h2>
                    </div>

                    @if($submission)
                        <!-- CASE 1: Signed and Registered in the system database -->
                        <div class="p-6 rounded-2xl bg-emerald-950/20 border border-emerald-500/20 space-y-4">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 rounded-full bg-emerald-500/10 border border-emerald-500/20 flex items-center justify-center text-emerald-400 text-lg font-bold">✓</div>
                                <div>
                                    <h4 class="font-outfit font-bold text-white text-base">Documento Homologado no Sistema</h4>
                                    <p class="text-xs text-emerald-400 font-semibold">Integridade & Autenticidade Confirmadas</p>
                                </div>
                            </div>
                            <p class="text-xs text-slate-400 leading-relaxed">
                                Este arquivo foi localizado em nossa base de dados oficial. A assinatura digital e os registros forenses coincidem perfeitamente.
                            </p>
                        </div>

                        <!-- DB Details -->
                        <div class="bg-dark-900/60 rounded-2xl border border-white/5 p-5 space-y-3 text-xs leading-relaxed">
                            <div class="flex justify-between border-b border-white/5 pb-2.5">
                                <span class="text-slate-500 font-medium">Código do Processo</span>
                                <span class="text-white font-bold">{{ $submission->process_id }}</span>
                            </div>
                            <div class="flex justify-between border-b border-white/5 pb-2.5">
                                <span class="text-slate-500 font-medium">Tipo de Documento</span>
                                <span class="text-white font-semibold">{{ $submission->document_type }}</span>
                            </div>
                            <div class="flex justify-between border-b border-white/5 pb-2.5">
                                <span class="text-slate-500 font-medium">Título</span>
                                <span class="text-white font-semibold">{{ $submission->title }}</span>
                            </div>
                            <div class="flex justify-between border-b border-white/5 pb-2.5">
                                <span class="text-slate-500 font-medium">Signatário / Responsável</span>
                                <span class="text-white font-semibold">
                                    @if($submission->student)
                                        {{ $submission->student->user->name }} (Aluno)
                                    @else
                                        Dr. Atilio Sersun Calefi (Professor/Orientador)
                                    @endif
                                </span>
                            </div>
                            <div class="flex justify-between border-b border-white/5 pb-2.5">
                                <span class="text-slate-500 font-medium">Data da Homologação</span>
                                <span class="text-white font-semibold">{{ $submission->metadata['forensic_timestamp'] ?? $submission->created_at->format('d/m/Y - H:i:s') }}</span>
                            </div>
                            <div class="flex justify-between border-b border-white/5 pb-2.5">
                                <span class="text-slate-500 font-medium">Endereço IP Origem</span>
                                <span class="text-slate-400 font-mono">{{ $submission->metadata['forensic_ip'] ?? 'N/A' }}</span>
                            </div>
                            <div class="flex flex-col gap-1 pt-1">
                                <span class="text-slate-500 font-medium">Hash SHA-256 Registrado</span>
                                <span class="text-brand-teal font-mono break-all font-bold text-[11px] bg-teal-950/20 p-2 rounded-lg border border-teal-900/30">{{ $hash }}</span>
                            </div>
                        </div>

                    @elseif($isSigned)
                        <!-- CASE 2: Structurally Signed, but not registered in the system (External Doc) -->
                        <div class="p-6 rounded-2xl bg-sky-950/20 border border-sky-500/20 space-y-4">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 rounded-full bg-sky-500/10 border border-sky-500/20 flex items-center justify-center text-sky-400 text-lg font-bold">i</div>
                                <div>
                                    <h4 class="font-outfit font-bold text-white text-base">Documento Assinado (Externo)</h4>
                                    <p class="text-xs text-sky-400 font-semibold">Assinatura Estruturalmente Íntegra</p>
                                </div>
                            </div>
                            <p class="text-xs text-slate-400 leading-relaxed">
                                O arquivo PDF analisado contém uma assinatura digital criptográfica ativa e não sofreu nenhuma alteração desde a sua assinatura. No entanto, <strong>ele não está cadastrado</strong> no banco de dados da nossa plataforma de orientação.
                            </p>
                        </div>

                        <!-- Forensic metadata extracted -->
                        <div class="bg-dark-900/60 rounded-2xl border border-white/5 p-5 space-y-3 text-xs leading-relaxed">
                            <div class="flex flex-col gap-2 border-b border-white/5 pb-3">
                                <span class="text-slate-500 font-medium">Signatários Detectados no PDF:</span>
                                @if(!empty($signers))
                                    <div class="flex flex-wrap gap-2">
                                        @foreach($signers as $signer)
                                            <span class="px-2.5 py-1 bg-white/5 border border-white/10 rounded-lg text-white font-bold font-outfit text-xs">{{ $signer }}</span>
                                        @endforeach
                                    </div>
                                @else
                                    <span class="text-slate-400 italic">Estrutura de assinatura detectada, mas não foi possível ler os metadados do signatário diretamente.</span>
                                @endif
                            </div>
                            <div class="flex flex-col gap-1 pt-1">
                                <span class="text-slate-500 font-medium">SHA-256 Hash do Arquivo</span>
                                <span class="text-sky-400 font-mono break-all font-bold text-[11px] bg-sky-950/20 p-2 rounded-lg border border-sky-900/30">{{ $hash }}</span>
                            </div>
                        </div>

                    @else
                        <!-- CASE 3: Not Signed -->
                        <div class="p-6 rounded-2xl bg-red-950/20 border border-red-500/20 space-y-4">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 rounded-full bg-red-500/10 border border-red-500/20 flex items-center justify-center text-red-400 text-lg font-bold">✗</div>
                                <div>
                                    <h4 class="font-outfit font-bold text-white text-base">Sem Assinatura Digital</h4>
                                    <p class="text-xs text-red-400 font-semibold">Nenhuma Assinatura Criptográfica Detectada</p>
                                </div>
                            </div>
                            <p class="text-xs text-slate-400 leading-relaxed">
                                Este documento <strong>não contém assinaturas digitais válidas (ICP-Brasil/PAdES)</strong> ou a assinatura foi completamente removida/corrompida. Não é possível atestar o não repúdio nem a integridade das informações contidas nele.
                            </p>
                        </div>

                        <div class="bg-dark-900/60 rounded-2xl border border-white/5 p-5 text-xs">
                            <div class="flex flex-col gap-1">
                                <span class="text-slate-500 font-medium">SHA-256 Hash do Arquivo</span>
                                <span class="text-red-400 font-mono break-all font-bold text-[11px] bg-red-950/20 p-2 rounded-lg border border-red-900/30">{{ $hash }}</span>
                            </div>
                        </div>
                    @endif

                    <div class="pt-4 flex justify-center">
                        <a href="{{ route('documento.verificador') }}" class="px-6 py-3 rounded-xl bg-gradient-to-r from-brand-indigo to-brand-teal text-white font-bold transition-all shadow-lg hover:brightness-110 active:scale-95 text-xs flex items-center gap-2">
                            Verificar Outro Documento
                        </a>
                    </div>
                </div>
            @else
                <!-- UPLOAD STATE -->
                <div class="glass p-8 sm:p-10 rounded-3xl border border-white/5 shadow-2xl space-y-8">
                    <div class="text-center space-y-2">
                        <span class="text-xs font-bold text-brand-teal uppercase tracking-widest font-outfit">Auditoria Digital Gratuita</span>
                        <h2 class="font-outfit font-extrabold text-3xl text-white">Validador de Assinaturas</h2>
                        <p class="text-sm text-slate-400 font-light">Arraste um PDF para verificar a validade das assinaturas digitais ICP-Brasil e a integridade do arquivo.</p>
                    </div>

                    <form action="{{ route('documento.verificador.post') }}" method="POST" enctype="multipart/form-data" class="space-y-6">
                        @csrf
                        
                        <!-- Drag and Drop Area -->
                        <div id="drop-zone" class="border-2 border-dashed border-white/10 hover:border-brand-teal/50 rounded-2xl p-8 text-center cursor-pointer transition-all bg-dark-900/30 flex flex-col items-center justify-center gap-4 group">
                            <input type="file" name="pdf_file" id="pdf_file" accept="application/pdf" class="hidden" required onchange="handleFileSelect(this)">
                            
                            <div class="w-16 h-16 rounded-full bg-white/5 border border-white/10 group-hover:border-brand-teal/20 group-hover:bg-brand-teal/5 flex items-center justify-center text-slate-350 group-hover:text-brand-teal transition-all">
                                <svg class="w-7 h-7" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 16.5V9.75m0 0l3 3m-3-3l-3 3M6.75 19.5a4.5 4.5 0 01-1.41-8.775 5.25 5.25 0 0110.233-2.33 3 3 0 013.758 3.848A3.752 3.752 0 0118 19.5H6.75z"></path>
                                </svg>
                            </div>

                            <div class="space-y-1">
                                <p class="text-sm font-bold text-white" id="file-name-placeholder">Escolha um arquivo PDF ou arraste-o aqui</p>
                                <p class="text-xs text-slate-500">Tamanho máximo de arquivo: 50MB</p>
                            </div>
                        </div>

                        <button type="submit" class="w-full py-4 bg-gradient-to-r from-brand-indigo to-brand-teal text-white rounded-xl font-bold transition-all shadow-lg hover:brightness-110 active:scale-95 text-sm flex items-center justify-center gap-2">
                            Verificar Documento
                        </button>
                    </form>
                </div>
            @endif

        </div>
    </main>

    <!-- Footer -->
    <footer class="w-full py-6 glass border-t border-white/5 text-center text-xs text-slate-500 relative z-10">
        © 2026 profatilio.com.br. Todos os direitos reservados. Validação em conformidade com a MP 2.200-2/2001.
    </footer>

    <script>
        const dropZone = document.getElementById('drop-zone');
        const fileInput = document.getElementById('pdf_file');
        const placeholder = document.getElementById('file-name-placeholder');

        if (dropZone) {
            dropZone.addEventListener('click', () => fileInput.click());

            dropZone.addEventListener('dragover', (e) => {
                e.preventDefault();
                dropZone.classList.add('border-brand-teal/50', 'bg-brand-teal/5');
            });

            ['dragleave', 'dragend'].forEach(type => {
                dropZone.addEventListener(type, () => {
                    dropZone.classList.remove('border-brand-teal/50', 'bg-brand-teal/5');
                });
            });

            dropZone.addEventListener('drop', (e) => {
                e.preventDefault();
                dropZone.classList.remove('border-brand-teal/50', 'bg-brand-teal/5');
                
                if (e.dataTransfer.files.length) {
                    fileInput.files = e.dataTransfer.files;
                    handleFileSelect(fileInput);
                }
            });
        }

        function handleFileSelect(input) {
            if (input.files && input.files.length) {
                const file = input.files[0];
                if (file.type !== 'application/pdf') {
                    alert('Por favor, envie apenas arquivos em formato PDF.');
                    input.value = '';
                    placeholder.innerText = 'Escolha um arquivo PDF ou arraste-o aqui';
                    return;
                }
                placeholder.innerText = `Selecionado: ${file.name} (${(file.size / 1024 / 1024).toFixed(2)} MB)`;
                placeholder.classList.add('text-brand-teal');
            }
        }
    </script>
    @include('partials.whatsapp_widget')
</body>
</html>
