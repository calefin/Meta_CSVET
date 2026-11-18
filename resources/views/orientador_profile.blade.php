<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Prof. Dr. Atilio Calefi — Orientador Acadêmico</title>
    <meta name="description" content="Perfil público do Prof. Dr. Atilio Sersun Calefi — orientador acadêmico especializado em TCC, IC, Artigos e Estágio em Medicina Veterinária.">
    <meta name="robots" content="index, follow">
    <link rel="canonical" href="https://profatilio.com.br/orientador">

    <!-- Open Graph -->
    <meta property="og:type" content="profile">
    <meta property="og:url" content="https://profatilio.com.br/orientador">
    <meta property="og:title" content="Prof. Dr. Atilio Calefi — Orientador Acadêmico">
    <meta property="og:description" content="Orientação de TCC, IC, Artigos Científicos e Estágio em Medicina Veterinária. Plataforma digital com auditoria por IA e assinatura Gov.br.">
    <meta property="og:image" content="https://profatilio.com.br/images/profile.png">
    <meta property="og:locale" content="pt_BR">
    <meta property="og:site_name" content="profatilio.com.br">
    <meta property="profile:first_name" content="Atilio">
    <meta property="profile:last_name" content="Sersun Calefi">

    <!-- Twitter Card -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:site" content="@ascalefi">
    <meta name="twitter:title" content="Prof. Dr. Atilio Calefi — Orientador Acadêmico">
    <meta name="twitter:description" content="Orientação de TCC, IC, Artigos e Estágio em Medicina Veterinária. Plataforma acadêmica com IA e assinatura digital.">
    <meta name="twitter:image" content="https://profatilio.com.br/images/profile.png">

    <!-- Schema.org ProfilePage -->
    <script type="application/ld+json">
    {
      "@@context": "https://schema.org",
      "@@type": "ProfilePage",
      "url": "https://profatilio.com.br/orientador",
      "name": "Perfil do Orientador — Prof. Dr. Atilio Sersun Calefi",
      "mainEntity": {
        "@@type": "Person",
        "@@id": "https://profatilio.com.br/#person",
        "name": "Atilio Sersun Calefi",
        "honorificPrefix": "Prof. Dr.",
        "jobTitle": "Professor Universitário e Orientador Acadêmico",
        "url": "https://profatilio.com.br",
        "image": "https://profatilio.com.br/images/profile.png",
        "email": "contato@profatilio.com.br",
        "sameAs": [
          "http://lattes.cnpq.br/4438211760493798",
          "https://www.instagram.com/ascalefi",
          "https://profatilio.com.br"
        ]
      }
    }
    </script>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;600;700;800&family=Inter:wght@300;400;500&display=swap" rel="stylesheet">
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
                        brand: { teal: '#0d9488', indigo: '#4f46e5' }
                    }
                }
            }
        }
    </script>
    <style>
        body {
            background: linear-gradient(135deg, #020617 0%, #0f172a 50%, #020617 100%);
            min-height: 100vh;
        }
        .stat-card {
            background: rgba(255,255,255,0.04);
            border: 1px solid rgba(255,255,255,0.07);
            border-radius: 1rem;
            padding: 1.5rem;
            text-align: center;
            transition: transform 0.2s, border-color 0.2s;
        }
        .stat-card:hover {
            transform: translateY(-2px);
            border-color: rgba(13,148,136,0.4);
        }
    </style>
    @include('partials.adsense')
</head>
<body class="font-sans text-slate-200">

    <!-- Header -->
    <header class="border-b border-white/5 bg-black/20 backdrop-blur-sm">
        <div class="max-w-5xl mx-auto px-4 py-4 flex items-center justify-between">
            <a href="/" class="flex items-center gap-2 opacity-80 hover:opacity-100 transition">
                <img src="{{ asset('images/logo.png') }}" alt="profatilio" class="h-8 w-auto">
            </a>
            <a href="/portal" class="text-xs font-semibold text-brand-teal hover:text-teal-300 transition">
                Acessar Portal →
            </a>
        </div>
    </header>

    <main class="max-w-5xl mx-auto px-4 py-12">

        <!-- Perfil -->
        <div class="flex flex-col sm:flex-row items-center sm:items-start gap-8 mb-14">
            <img src="{{ asset('images/logo.png') }}" alt="Prof. Atilio"
                 class="w-28 h-28 rounded-full object-cover border-2 border-brand-teal/40 shadow-xl shadow-brand-teal/10">
            <div>
                <p class="text-brand-teal text-sm font-semibold uppercase tracking-widest mb-1">Orientador Acadêmico</p>
                <h1 class="font-outfit font-extrabold text-3xl sm:text-4xl text-white tracking-tight mb-2">
                    Prof. Dr. Atilio Sersun Calefi
                </h1>
                <p class="text-slate-400 text-sm sm:text-base max-w-2xl leading-relaxed">
                    Médico Veterinário, Doutor em Ciências. Professor orientador de TCC, Iniciação Científica,
                    Artigos Científicos e Estágio. Especialista em clínica e diagnóstico veterinário.
                </p>
                <div class="flex flex-wrap gap-2 mt-4">
                    @foreach(['Medicina Veterinária','Iniciação Científica','TCC','Artigo Científico','Estágio Supervisionado'] as $tag)
                    <span class="px-3 py-1 rounded-full text-xs font-medium bg-brand-teal/10 border border-brand-teal/20 text-brand-teal">
                        {{ $tag }}
                    </span>
                    @endforeach
                </div>
            </div>
        </div>

        <!-- Estatísticas -->
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-14">
            <div class="stat-card">
                <p class="font-outfit font-bold text-3xl text-white">{{ $stats['total_students'] }}</p>
                <p class="text-xs text-slate-400 mt-1">Orientandos</p>
            </div>
            <div class="stat-card">
                <p class="font-outfit font-bold text-3xl text-brand-teal">{{ $stats['concluded'] }}</p>
                <p class="text-xs text-slate-400 mt-1">Processos Concluídos</p>
            </div>
            <div class="stat-card">
                <p class="font-outfit font-bold text-3xl text-indigo-400">{{ $stats['document_types'] }}</p>
                <p class="text-xs text-slate-400 mt-1">Tipos de Trabalho</p>
            </div>
            <div class="stat-card">
                <p class="font-outfit font-bold text-3xl text-purple-400">{{ $stats['years_active'] }}</p>
                <p class="text-xs text-slate-400 mt-1">Anos em Atividade</p>
            </div>
        </div>

        <!-- Tipos de trabalho orientados -->
        @if($stats['by_type']->count())
        <div class="mb-14">
            <h2 class="font-outfit font-bold text-xl text-white mb-6">Trabalhos Orientados por Área</h2>
            <div class="space-y-3">
                @foreach($stats['by_type'] as $type)
                <div class="flex items-center gap-3">
                    <span class="text-sm text-slate-300 w-52 shrink-0">{{ $type->document_type }}</span>
                    <div class="flex-1 bg-white/5 rounded-full h-2 overflow-hidden">
                        <div class="h-2 rounded-full bg-gradient-to-r from-brand-teal to-indigo-500"
                             style="width: {{ min(100, ($type->total / max(1, $stats['by_type']->max('total'))) * 100) }}%">
                        </div>
                    </div>
                    <span class="text-xs text-slate-400 w-8 text-right">{{ $type->total }}</span>
                </div>
                @endforeach
            </div>
        </div>
        @endif

        <!-- CTA -->
        <div class="rounded-2xl border border-brand-teal/20 bg-brand-teal/5 p-8 text-center">
            <h2 class="font-outfit font-bold text-xl text-white mb-2">Quer iniciar uma orientação?</h2>
            <p class="text-slate-400 text-sm mb-6">
                Solicite seu convite de acesso diretamente com o Prof. Atilio.
                Alunos de graduação, pós-graduação e mentoria são bem-vindos.
            </p>
            <a href="mailto:contato@profatilio.com.br"
               class="inline-flex items-center gap-2 px-6 py-3 rounded-xl bg-brand-teal text-white font-semibold text-sm hover:bg-teal-600 transition">
                Entrar em Contato
            </a>
        </div>

    </main>

    <footer class="border-t border-white/5 mt-16 py-8 text-center text-xs text-slate-600">
        © {{ date('Y') }} Prof. Dr. Atilio Sersun Calefi — profatilio.com.br
    </footer>

    @include('partials.whatsapp_widget')
</body>
</html>
