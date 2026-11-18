<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Termo de Necropsia Assinado - Prof. Atilio</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Outfit:wght@400;600;800&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['Inter', 'sans-serif'],
                        outfit: ['Outfit', 'sans-serif'],
                    },
                    colors: {
                        dark: {
                            50: '#f8fafc',
                            100: '#f1f5f9',
                            800: '#1e293b',
                            900: '#0f172a',
                            950: '#020617',
                        },
                        brand: {
                            teal: '#0d9488',
                            indigo: '#4f46e5',
                            violet: '#7c3aed',
                        }
                    }
                }
            }
        }
    </script>
    <style>
        body {
            background-color: #020617;
            background-image: 
                radial-gradient(at 0% 0%, rgba(13, 148, 136, 0.15) 0px, transparent 50%),
                radial-gradient(at 100% 100%, rgba(79, 70, 229, 0.15) 0px, transparent 50%);
        }
        .glass-card {
            background: rgba(15, 23, 42, 0.65);
            backdrop-filter: blur(16px);
            border: 1px rgba(255, 255, 255, 0.08) solid;
        }
    </style>
</head>
<body class="font-sans text-slate-200 min-h-screen flex items-center justify-center p-4 py-12">
    <div class="w-full max-w-2xl glass-card rounded-3xl p-8 sm:p-12 shadow-2xl shadow-brand-teal/5">
        
        <!-- Header -->
        <div class="text-center mb-8">
            <div class="w-16 h-16 rounded-full bg-brand-teal/10 border border-brand-teal/30 flex items-center justify-center mx-auto mb-4 text-brand-teal text-3xl font-bold">
                ✓
            </div>
            <h1 class="font-outfit font-extrabold text-3xl sm:text-4xl text-white tracking-tight bg-gradient-to-r from-brand-teal via-brand-indigo to-brand-violet bg-clip-text text-transparent">
                Assinatura Realizada!
            </h1>
            <p class="text-slate-400 mt-2 text-sm sm:text-base">
                O Termo de Autorização foi assinado e auditado com sucesso.
            </p>
        </div>

        <!-- Stepper -->
        <div class="flex items-center justify-between max-w-md mx-auto mb-8 text-xs sm:text-sm font-semibold tracking-wide uppercase">
            <div class="flex items-center gap-2 text-slate-400">
                <span class="w-6 h-6 rounded-full bg-white/5 border border-white/10 flex items-center justify-center text-xs">✓</span>
                <span>Orçamento</span>
            </div>
            <div class="flex-1 h-px bg-brand-teal mx-4"></div>
            <div class="flex items-center gap-2 text-slate-400">
                <span class="w-6 h-6 rounded-full bg-white/5 border border-white/10 flex items-center justify-center text-xs">✓</span>
                <span>Autorização</span>
            </div>
            <div class="flex-1 h-px bg-brand-teal mx-4"></div>
            <div class="flex items-center gap-2 text-brand-teal">
                <span class="w-6 h-6 rounded-full bg-brand-teal/20 border border-brand-teal flex items-center justify-center text-xs">3</span>
                <span>Conclusão</span>
            </div>
        </div>

        <div class="space-y-6">
            <!-- Informativo e-mail -->
            <div class="p-4 rounded-xl bg-white/5 border border-white/10 text-xs text-slate-300">
                <p class="font-semibold text-white mb-1">📢 O que acontece agora?</p>
                <p>1. Enviamos um e-mail de confirmação para <strong>{{ $necropsyRequest->requester_email }}</strong> com o termo PDF assinado em anexo.</p>
                <p class="mt-1">2. O Prof. Dr. Atilio Calefi foi notificado e já recebeu a sua autorização e dados do animal para o agendamento.</p>
            </div>

            <!-- Resumo e download -->
            <div class="bg-dark-950/80 border border-white/10 rounded-2xl p-6 space-y-4">
                <div class="flex items-center justify-between border-b border-white/5 pb-3">
                    <span class="text-xs font-semibold uppercase tracking-wider text-slate-400">Animal</span>
                    <span class="text-sm font-bold text-white">{{ $necropsyRequest->animal_name }} ({{ $necropsyRequest->species }})</span>
                </div>
                <div class="flex items-center justify-between border-b border-white/5 pb-3">
                    <span class="text-xs font-semibold uppercase tracking-wider text-slate-400">Responsável</span>
                    <span class="text-sm font-bold text-white">{{ $necropsyRequest->requester_name }}</span>
                </div>
                <div class="flex items-center justify-between border-b border-white/5 pb-3">
                    <span class="text-xs font-semibold uppercase tracking-wider text-slate-400">Valor Final</span>
                    <span class="text-sm font-bold text-brand-teal">R$ {{ number_format($necropsyRequest->final_price, 2, ',', '.') }}</span>
                </div>
                <div class="pt-2">
                    <a href="{{ route('necropsy.pdf', ['token' => $necropsyRequest->token]) }}"
                       class="w-full block py-3.5 rounded-xl bg-brand-teal text-white font-bold text-center shadow-md shadow-brand-teal/25 hover:bg-brand-teal/95 transition-all text-xs uppercase tracking-wider">
                        ⬇ Baixar Termo Assinado (PDF)
                    </a>
                </div>
            </div>

            <!-- Certificado Forense -->
            <div class="p-6 rounded-2xl bg-black/40 border border-white/5 space-y-3">
                <h4 class="font-outfit font-bold text-sm text-slate-300 uppercase tracking-wider">Certificado de Auditoria Forense</h4>
                
                <div class="space-y-1">
                    <span class="block text-[10px] text-slate-500 uppercase font-semibold">Código de Integridade (Hash SHA-256)</span>
                    <code class="block text-[11px] text-brand-teal bg-dark-950/90 p-2 rounded-lg border border-white/5 break-all">
                        {{ $necropsyRequest->forensic_hash }}
                    </code>
                </div>

                <div class="grid grid-cols-2 gap-4 pt-1">
                    <div>
                        <span class="block text-[10px] text-slate-500 uppercase font-semibold">Endereço IP</span>
                        <span class="text-xs font-bold text-white">{{ $necropsyRequest->forensic_ip }}</span>
                    </div>
                    <div>
                        <span class="block text-[10px] text-slate-500 uppercase font-semibold">Data e Hora</span>
                        <span class="text-xs font-bold text-white">{{ $necropsyRequest->forensic_timestamp }}</span>
                    </div>
                </div>

                <div class="pt-1">
                    <span class="block text-[10px] text-slate-500 uppercase font-semibold">User-Agent</span>
                    <span class="text-[10px] text-slate-400 block break-words leading-relaxed">{{ $necropsyRequest->forensic_user_agent }}</span>
                </div>
            </div>
            
            <!-- Voltar home -->
            <div class="text-center pt-2">
                <a href="/" class="text-xs text-brand-teal hover:underline font-semibold">
                    ← Voltar ao site principal
                </a>
            </div>
        </div>
    </div>
    @include('partials.whatsapp_widget')
</body>
</html>
