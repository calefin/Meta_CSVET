<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Solicitação de Atendimento & Orçamento Geral - Prof. Atilio</title>
    
    <!-- SEO Base -->
    <meta name="description" content="Solicite orçamentos para mentoria acadêmica de TCC e IC, revisão científica nas normas ABNT, laudos de necropsia e consultoria científica em medicina veterinária com o Prof. Dr. Atilio Sersun Calefi.">
    <meta name="keywords" content="orientação TCC, mentoria acadêmica, revisão ABNT, orçamento veterinária, consultoria científica, Prof Atilio Calefi, patologia veterinária, laudo necropsia">
    <meta name="author" content="Prof. Dr. Atilio Sersun Calefi">
    <meta name="robots" content="index, follow">
    <link rel="canonical" href="https://profatilio.com.br/solicitar-atendimento">
    <link class="favicon" rel="icon" type="image/png" href="{{ asset('images/favicon.png') }}">

    <!-- Open Graph / Facebook -->
    <meta property="og:type" content="website">
    <meta property="og:url" content="https://profatilio.com.br/solicitar-atendimento">
    <meta property="og:title" content="Solicitação de Atendimento & Orçamento Geral - Prof. Atilio">
    <meta property="og:description" content="Entre em contato para solicitar orçamentos de mentoria acadêmica, revisões ABNT, laudos e consultoria científica em medicina veterinária com o Prof. Atilio.">
    <meta property="og:image" content="{{ asset('images/og-share-default.jpg') }}">

    <!-- Twitter Cards -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:url" content="https://profatilio.com.br/solicitar-atendimento">
    <meta name="twitter:title" content="Solicitação de Atendimento & Orçamento Geral - Prof. Atilio">
    <meta name="twitter:description" content="Entre em contato para solicitar orçamentos de mentoria acadêmica, revisões ABNT, laudos e consultoria científica em medicina veterinária com o Prof. Atilio.">
    <meta name="twitter:image" content="{{ asset('images/og-share-default.jpg') }}">

    <!-- Schema.org Structured Data (JSON-LD) -->
    <script type="application/ld+json">
    {
      "@@context": "https://schema.org",
      "@@type": "ContactPage",
      "@@id": "https://profatilio.com.br/solicitar-atendimento#contactpage",
      "url": "https://profatilio.com.br/solicitar-atendimento",
      "name": "Solicitação de Atendimento & Orçamento Geral - Prof. Atilio",
      "description": "Formulário de contato e orçamento geral para consultorias acadêmicas, mentoria de TCC/IC, revisão ABNT, laudos de necropsia e cursos de medicina veterinária.",
      "isPartOf": {
        "@@type": "WebSite",
        "@@id": "https://profatilio.com.br/#website",
        "name": "Prof. Atilio",
        "url": "https://profatilio.com.br"
      },
      "about": {
        "@@type": "Person",
        "@@id": "https://profatilio.com.br/#person"
      },
      "mainEntity": {
        "@@type": "ProfessionalService",
        "@@id": "https://profatilio.com.br/#service",
        "name": "Prof. Dr. Atilio Sersun Calefi - Consultoria & Orientação Acadêmica",
        "image": "https://profatilio.com.br/images/profile.png",
        "email": "contato@profatilio.com.br",
        "url": "https://profatilio.com.br",
        "priceRange": "$$",
        "address": {
          "@@type": "PostalAddress",
          "addressCountry": "BR",
          "addressRegion": "SP"
        },
        "description": "Serviços especializados de mentoria acadêmica, revisão científica ABNT, elaboração de projetos e consultoria científica em medicina veterinária."
      }
    }
    </script>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Outfit:wght@400;600;800&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
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
    <div class="w-full max-w-2xl glass-card rounded-3xl p-8 sm:p-12 shadow-2xl shadow-brand-teal/5" x-data="contactForm()">
        
        <!-- Header -->
        <div class="text-center mb-8">
            <h1 class="font-outfit font-extrabold text-3xl sm:text-4xl text-white tracking-tight bg-gradient-to-r from-brand-teal via-brand-indigo to-brand-violet bg-clip-text text-transparent">
                Atendimento & Orçamento Geral
            </h1>
            <p class="text-slate-400 mt-2 text-sm sm:text-base font-light">
                Solicite orçamentos para revisões, consultoria científica ou mentoria acadêmica
            </p>
        </div>

        <!-- Success Screen -->
        <div x-show="submitted" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100" class="text-center py-8 space-y-6" style="display: none;">
            <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-emerald-950/50 border border-emerald-500/30 text-emerald-400 mb-4 animate-bounce">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-8 h-8" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                </svg>
            </div>
            <h2 class="text-2xl font-bold text-white font-outfit" x-text="successMessage"></h2>
            <p class="text-slate-400 max-w-md mx-auto text-sm sm:text-base leading-relaxed">
                Sua mensagem foi registrada com sucesso e os alertas de notificação foram enviados ao Prof. Atilio por e-mail e Telegram. Entraremos em contato em breve!
            </p>
            <div class="pt-6">
                <a href="/" class="px-6 py-3 rounded-xl bg-teal-800 hover:bg-teal-700 text-white font-semibold text-sm transition duration-200">
                    Voltar à Página Principal
                </a>
            </div>
        </div>

        <!-- Form Screen -->
        <form x-show="!submitted" @submit.prevent="submitForm" class="space-y-6" x-transition>
            <!-- Error Alert -->
            <div x-show="error" x-transition class="p-4 rounded-2xl bg-red-950/50 border border-red-500/30 text-red-200 text-sm" style="display: none;">
                <p class="font-semibold" x-text="errorMessage"></p>
            </div>

            <!-- Honeypot Field -->
            <div style="display: none;">
                <label for="secondary_address">Endereço Secundário (Não preencha se for humano)</label>
                <input type="text" id="secondary_address" x-model="formData.secondary_address" autocomplete="off">
            </div>

            <!-- Nome / Razão Social -->
            <div class="space-y-2">
                <label for="name" class="block text-xs font-semibold uppercase tracking-wider text-slate-300">Nome / Razão Social</label>
                <input type="text" id="name" required x-model="formData.name" placeholder="Seu nome completo ou instituição"
                       class="w-full px-4 py-3 rounded-xl bg-dark-950/80 border border-white/10 text-white focus:outline-none focus:border-brand-teal text-sm transition-all focus:ring-2 focus:ring-brand-teal/20">
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                <!-- E-mail -->
                <div class="space-y-2">
                    <label for="email" class="block text-xs font-semibold uppercase tracking-wider text-slate-300">E-mail para Retorno</label>
                    <input type="email" id="email" required x-model="formData.email" placeholder="nome@exemplo.com"
                           class="w-full px-4 py-3 rounded-xl bg-dark-950/80 border border-white/10 text-white focus:outline-none focus:border-brand-teal text-sm transition-all focus:ring-2 focus:ring-brand-teal/20">
                </div>

                <!-- Telefone -->
                <div class="space-y-2">
                    <label for="phone" class="block text-xs font-semibold uppercase tracking-wider text-slate-300">Telefone / WhatsApp</label>
                    <input type="text" id="phone" x-model="formData.phone" placeholder="Ex: (11) 99999-9999"
                           class="w-full px-4 py-3 rounded-xl bg-dark-950/80 border border-white/10 text-white focus:outline-none focus:border-brand-teal text-sm transition-all focus:ring-2 focus:ring-brand-teal/20">
                </div>
            </div>

            <!-- Tipo de Solicitação -->
            <div class="space-y-2">
                <label for="request_type" class="block text-xs font-semibold uppercase tracking-wider text-slate-300">Tipo de Solicitação / Assunto</label>
                <select id="request_type" required x-model="formData.request_type"
                        class="w-full px-4 py-3 rounded-xl bg-dark-950/80 border border-white/10 text-white focus:outline-none focus:border-brand-teal text-sm transition-all focus:ring-2 focus:ring-brand-teal/20">
                    <option value="" disabled selected>Selecione uma opção...</option>
                    <option value="Orçamento de Consultoria Acadêmica">Orçamento de Consultoria Acadêmica</option>
                    <option value="Orçamento de Revisão de Artigo / TCC">Orçamento de Revisão de Artigo / TCC</option>
                    <option value="Mentoria Acadêmica e Dúvidas">Mentoria Acadêmica e Dúvidas</option>
                    <option value="Parcerias de Pesquisa / Projetos">Parcerias de Pesquisa / Projetos</option>
                    <option value="Outros Assuntos">Outros Assuntos</option>
                </select>
            </div>

            <!-- Mensagem / Descrição da Demanda -->
            <div class="space-y-2">
                <label for="message" class="block text-xs font-semibold uppercase tracking-wider text-slate-300">Descrição da Proposta / Detalhes</label>
                <textarea id="message" required x-model="formData.message" rows="5" placeholder="Descreva os detalhes da sua demanda acadêmica ou científica..."
                          class="w-full px-4 py-3 rounded-xl bg-dark-950/80 border border-white/10 text-white focus:outline-none focus:border-brand-teal text-sm transition-all focus:ring-2 focus:ring-brand-teal/20"></textarea>
            </div>

            <!-- Submit Button -->
            <div class="pt-4">
                <button type="submit" :disabled="loading"
                        class="w-full flex items-center justify-center gap-2 px-6 py-4 rounded-xl text-white font-bold text-sm bg-gradient-to-r from-brand-teal to-brand-indigo hover:opacity-90 transition duration-200 disabled:opacity-50">
                    <span x-show="!loading">Enviar Solicitação</span>
                    <span x-show="loading" class="flex items-center gap-2" style="display: none;">
                        <svg class="animate-spin h-5 w-5 text-white" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        Processando...
                    </span>
                </button>
            </div>
        </form>

        <!-- Footer Back Link -->
        <div class="mt-8 pt-6 border-t border-white/5 text-center">
            <a href="/" class="text-xs text-slate-500 hover:text-teal-400 transition font-medium flex items-center justify-center gap-1.5">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18" />
                </svg>
                Voltar ao site principal
            </a>
        </div>

    </div>

    <script>
        function contactForm() {
            return {
                formData: {
                    name: '',
                    email: '',
                    phone: '',
                    request_type: '',
                    message: '',
                    secondary_address: ''
                },
                loading: false,
                submitted: false,
                error: false,
                errorMessage: '',
                successMessage: '',
                submitForm() {
                    this.loading = true;
                    this.error = false;
                    
                    fetch('{{ route("submit.consultation") }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}',
                            'Accept': 'application/json'
                        },
                        body: JSON.stringify(this.formData)
                    })
                    .then(response => {
                        if (!response.ok) {
                            throw new Error('Ocorreu um erro ao processar sua solicitação. Verifique os dados e tente novamente.');
                        }
                        return response.json();
                    })
                    .then(data => {
                        this.loading = false;
                        if (data.status === 'success') {
                            this.submitted = true;
                            this.successMessage = data.message;
                        } else {
                            this.error = true;
                            this.errorMessage = data.message || 'Erro inesperado.';
                        }
                    })
                    .catch(err => {
                        this.loading = false;
                        this.error = true;
                        this.errorMessage = err.message || 'Erro de rede. Verifique sua conexão.';
                    });
                }
            }
        }
    </script>
    @include('partials.whatsapp_widget')
</body>
</html>
