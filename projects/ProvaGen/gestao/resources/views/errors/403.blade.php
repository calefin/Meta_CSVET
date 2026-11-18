<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Acesso Negado - Prof. Atilio</title>
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
                }
            }
        }
    </script>
    <style>
        body {
            background-color: #020617;
            background-image:
                radial-gradient(at 0% 0%, rgba(239, 68, 68, 0.08) 0px, transparent 50%),
                radial-gradient(at 100% 100%, rgba(79, 70, 229, 0.12) 0px, transparent 50%);
        }
    </style>
</head>
<body class="font-sans text-slate-200 min-h-screen flex items-center justify-center p-4">

    @php
        $user       = auth()->user();
        $student    = $user?->student;
        $status     = $student?->status;
        $botRaw     = config('services.telegram.bot_username', '');
        $botUser    = ltrim($botRaw, '@');
        $supportUrl = $botUser ? "https://t.me/{$botUser}" : null;
    @endphp

    <div class="w-full max-w-lg space-y-5">

        {{-- Card principal --}}
        <div class="bg-slate-900/60 backdrop-blur-xl border border-white/10 rounded-3xl p-8 sm:p-10 shadow-2xl text-center space-y-5">

            {{-- Ícone --}}
            <div class="flex justify-center">
                @if($status === 'awaiting_email_validation')
                    {{-- Envelope --}}
                    <div class="w-16 h-16 rounded-full bg-sky-500/10 border border-sky-500/30 flex items-center justify-center text-sky-400">
                        <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                        </svg>
                    </div>
                @elseif($status === 'awaiting_advisor_approval')
                    {{-- Relógio --}}
                    <div class="w-16 h-16 rounded-full bg-amber-500/10 border border-amber-500/30 flex items-center justify-center text-amber-400">
                        <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                    </div>
                @else
                    {{-- Cadeado --}}
                    <div class="w-16 h-16 rounded-full bg-red-500/10 border border-red-500/30 flex items-center justify-center text-red-400">
                        <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                        </svg>
                    </div>
                @endif
            </div>

            {{-- Título e mensagem por status --}}
            @if($status === 'awaiting_email_validation')

                <h1 class="font-outfit font-extrabold text-2xl sm:text-3xl text-white">
                    Confirme seu E-mail
                </h1>
                <p class="text-slate-400 text-sm sm:text-base leading-relaxed">
                    Seu cadastro foi recebido, mas você ainda
                    <strong class="text-slate-200">não clicou no link de ativação</strong>
                    enviado para o seu e-mail.
                </p>
                <div class="rounded-2xl border border-sky-500/25 bg-sky-500/5 p-4 text-left space-y-2 text-sm">
                    <p class="text-sky-400 font-semibold">O que fazer agora:</p>
                    <ol class="text-slate-400 space-y-1 list-decimal list-inside">
                        <li>Abra sua caixa de entrada (verifique o spam também).</li>
                        <li>Procure o e-mail com assunto <em>"Ativação de Conta"</em>.</li>
                        <li>Clique no botão <strong class="text-slate-300">Validar E-mail</strong>.</li>
                        <li>Após validar, aguarde a aprovação do Prof. Atilio.</li>
                    </ol>
                </div>

            @elseif($status === 'awaiting_advisor_approval')

                <h1 class="font-outfit font-extrabold text-2xl sm:text-3xl text-white">
                    Aguardando Aprovação
                </h1>
                <p class="text-slate-400 text-sm sm:text-base leading-relaxed">
                    Seu e-mail foi confirmado, mas o acesso à plataforma ainda
                    <strong class="text-slate-200">não foi liberado</strong>.
                    O Prof. Atilio precisa aprovar manualmente o seu cadastro.
                </p>
                <div class="rounded-2xl border border-amber-500/25 bg-amber-500/5 p-4 text-left space-y-2 text-sm">
                    <p class="text-amber-400 font-semibold">O que vai acontecer:</p>
                    <ul class="text-slate-400 space-y-1 list-disc list-inside">
                        <li>O Prof. Atilio será notificado sobre o seu cadastro.</li>
                        <li>Assim que aprovado, você receberá um <strong class="text-slate-300">e-mail de boas-vindas</strong>.</li>
                        <li>Não precisa ficar tentando entrar — você será avisado.</li>
                    </ul>
                </div>

            @else

                <h1 class="font-outfit font-extrabold text-2xl sm:text-3xl text-white">
                    Acesso Negado
                </h1>
                <p class="text-slate-400 text-sm sm:text-base leading-relaxed">
                    Sua conta não tem permissão para acessar esta área.
                    @if($user)
                        Isso pode acontecer se o seu cadastro ainda não foi aprovado
                        ou se você está usando o link incorreto.
                    @else
                        Faça login com sua conta de aluno para continuar.
                    @endif
                </p>

            @endif

        </div>

        {{-- Card de suporte via Telegram --}}
        @if($supportUrl)
        <div class="bg-slate-900/40 border border-white/8 rounded-2xl p-5 flex items-start gap-4">
            {{-- Ícone Telegram --}}
            <div class="flex-shrink-0 w-10 h-10 rounded-xl bg-sky-500/15 border border-sky-500/25 flex items-center justify-center text-sky-400 mt-0.5">
                <svg class="w-5 h-5" viewBox="0 0 24 24" fill="currentColor">
                    <path d="M11.944 0A12 12 0 0 0 0 12a12 12 0 0 0 12 12 12 12 0 0 0 12-12A12 12 0 0 0 12 0a12 12 0 0 0-.056 0zm4.962 7.224c.1-.002.321.023.465.14a.506.506 0 0 1 .171.325c.016.093.036.306.02.472-.18 1.898-.96 6.502-1.36 8.627-.168.9-.499 1.201-.82 1.23-.696.065-1.225-.46-1.9-.902-1.056-.693-1.653-1.124-2.678-1.8-1.185-.78-.417-1.21.258-1.91.177-.184 3.247-2.977 3.307-3.23.007-.032.014-.15-.056-.212s-.174-.041-.249-.024c-.106.024-1.793 1.14-5.061 3.345-.48.33-.913.49-1.302.48-.428-.008-1.252-.241-1.865-.44-.752-.245-1.349-.374-1.297-.789.027-.216.325-.437.893-.663 3.498-1.524 5.83-2.529 6.998-3.014 3.332-1.386 4.025-1.627 4.476-1.635z"/>
                </svg>
            </div>
            <div class="flex-1 min-w-0">
                <p class="text-sm text-slate-300 font-semibold mb-1">Problema ou dúvida? Fale pelo Telegram</p>
                <p class="text-xs text-slate-500 leading-relaxed mb-3">
                    Se você acredita que deveria ter acesso ou precisa de ajuda,
                    entre em contato diretamente pelo bot do Prof. Atilio.
                </p>
                <a href="{{ $supportUrl }}" target="_blank" rel="noopener"
                   class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-sky-500/15 border border-sky-500/30 hover:bg-sky-500/25 text-sky-400 hover:text-sky-300 text-xs font-semibold transition-all">
                    <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="currentColor">
                        <path d="M11.944 0A12 12 0 0 0 0 12a12 12 0 0 0 12 12 12 12 0 0 0 12-12A12 12 0 0 0 12 0a12 12 0 0 0-.056 0zm4.962 7.224c.1-.002.321.023.465.14a.506.506 0 0 1 .171.325c.016.093.036.306.02.472-.18 1.898-.96 6.502-1.36 8.627-.168.9-.499 1.201-.82 1.23-.696.065-1.225-.46-1.9-.902-1.056-.693-1.653-1.124-2.678-1.8-1.185-.78-.417-1.21.258-1.91.177-.184 3.247-2.977 3.307-3.23.007-.032.014-.15-.056-.212s-.174-.041-.249-.024c-.106.024-1.793 1.14-5.061 3.345-.48.33-.913.49-1.302.48-.428-.008-1.252-.241-1.865-.44-.752-.245-1.349-.374-1.297-.789.027-.216.325-.437.893-.663 3.498-1.524 5.83-2.529 6.998-3.014 3.332-1.386 4.025-1.627 4.476-1.635z"/>
                    </svg>
                    Abrir conversa no Telegram
                </a>
            </div>
        </div>
        @endif

        {{-- Voltar --}}
        <div class="text-center">
            <a href="/portal/login"
               class="inline-block px-6 py-2.5 bg-white/5 border border-white/10 hover:bg-white/10 text-slate-300 text-sm font-semibold rounded-xl transition-all">
                Voltar para o Login
            </a>
        </div>

    </div>
</body>
</html>
