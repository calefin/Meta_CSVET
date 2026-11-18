<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    @if($direct)
    <title>Autorização de Necropsia - Prof. Atilio</title>
    <meta name="robots" content="noindex, follow">
    @else
    <title>Necropsia Veterinária: Orçamento Online | Prof. Dr. Atilio Calefi</title>
    <meta name="description" content="Calcule na hora o valor da necropsia do seu animal com o Prof. Dr. Atilio Calefi, médico veterinário (FMVZ-USP). Inclui relatório macroscópico, histopatológico e fotodocumentação. Atendimento na Grande São Paulo.">
    <link rel="canonical" href="https://profatilio.com.br/solicitar-necropsia">
    <meta property="og:type" content="website">
    <meta property="og:url" content="https://profatilio.com.br/solicitar-necropsia">
    <meta property="og:title" content="Necropsia Veterinária com o Prof. Dr. Atilio Calefi — orçamento online">
    <meta property="og:description" content="Valor calculado na hora pelo peso do animal; termo de autorização assinado online. Fale direto com o Prof. Atilio.">
    <meta property="og:image" content="{{ asset('images/og-cover.jpg') }}">
    <meta property="og:locale" content="pt_BR">
    @endif
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
    <div class="w-full max-w-2xl glass-card rounded-3xl p-8 sm:p-12 shadow-2xl shadow-brand-teal/5">
        
        <!-- Header -->
        <div class="text-center mb-6">
            <h1 class="font-outfit font-extrabold text-3xl sm:text-4xl text-white tracking-tight bg-gradient-to-r from-brand-teal via-brand-indigo to-brand-violet bg-clip-text text-transparent">
                {{ $direct ? 'Autorização de Necropsia' : 'Orçamento de Exame Necroscópico' }}
            </h1>
            <p class="text-slate-400 mt-2 text-sm sm:text-base">
                @if($direct)
                    Para quem já combinou a necropsia com o Prof. Dr. Atilio Calefi: preencha os dados do animal e siga para o termo de autorização.
                @else
                    Simulação de valores e emissão de termo de autorização — Prof. Dr. Atilio Calefi
                @endif
            </p>
        </div>

        <!-- Inclusions Info Card -->
        <div class="mb-8 p-4 rounded-2xl bg-brand-teal/5 border border-brand-teal/20 text-center text-xs sm:text-sm text-slate-300">
            📌 <strong>Incluso em todas as necropsias:</strong> Relatório macroscópico, realização de exame histopatológico e fotodocumentação.
        </div>

        <!-- Stepper -->
        <div class="flex items-center justify-between max-w-md mx-auto mb-10 text-xs sm:text-sm font-semibold tracking-wide uppercase">
            <div class="flex items-center gap-2 text-brand-teal">
                <span class="w-6 h-6 rounded-full bg-brand-teal/20 border border-brand-teal flex items-center justify-center text-xs">1</span>
                <span>{{ $direct ? 'Dados' : 'Orçamento' }}</span>
            </div>
            <div class="flex-1 h-px bg-white/10 mx-4"></div>
            <div class="flex items-center gap-2 text-slate-500">
                <span class="w-6 h-6 rounded-full bg-white/5 border border-white/10 flex items-center justify-center text-xs">2</span>
                <span>Autorização</span>
            </div>
            <div class="flex-1 h-px bg-white/10 mx-4"></div>
            <div class="flex items-center gap-2 text-slate-500">
                <span class="w-6 h-6 rounded-full bg-white/5 border border-white/10 flex items-center justify-center text-xs">3</span>
                <span>Conclusão</span>
            </div>
        </div>

        @if($errors->any())
            <div class="mb-6 p-4 rounded-2xl bg-red-950/50 border border-red-500/30 text-red-200 text-sm">
                <ul class="list-disc pl-5 space-y-1">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <!-- Form -->
        <form action="{{ route('necropsy.orcamento.store') }}" method="POST" class="space-y-6" x-data="{ hasCremation: false, isVet: false }">
            @csrf
            @if($direct)
                <input type="hidden" name="direct" value="1">
            @endif

            <!-- Honeypot anti-robô: invisível para pessoas -->
            <div style="position:absolute;left:-9999px;top:-9999px;" aria-hidden="true">
                <label for="website">Site</label>
                <input type="text" id="website" name="website" tabindex="-1" autocomplete="off">
            </div>

            <!-- Seção 0: Contato -->
            <div class="space-y-4">
                <h3 class="font-outfit font-semibold text-lg text-white border-b border-white/5 pb-2">1. Seu Contato</h3>
                <p class="text-xs text-slate-400">{{ $direct ? 'Para o Prof. Atilio identificar o seu caso.' : 'Para que o Prof. Atilio possa retornar o quanto antes com as orientações.' }}</p>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                    <div class="space-y-2 sm:col-span-2">
                        <label for="requester_name" class="block text-xs font-semibold uppercase tracking-wider text-slate-300">Seu Nome *</label>
                        <input type="text" id="requester_name" name="requester_name" required value="{{ old('requester_name') }}" placeholder="Ex: Maria de Oliveira" autocomplete="name"
                               class="w-full px-4 py-3 rounded-xl bg-dark-950/80 border border-white/10 text-white focus:outline-none focus:border-brand-teal text-sm transition-all">
                    </div>

                    <div class="space-y-2">
                        <label for="requester_phone" class="block text-xs font-semibold uppercase tracking-wider text-slate-300">WhatsApp / Telefone (com DDD) *</label>
                        <input type="tel" id="requester_phone" name="requester_phone" required value="{{ old('requester_phone') }}" placeholder="Ex: (11) 99999-9999" autocomplete="tel"
                               class="w-full px-4 py-3 rounded-xl bg-dark-950/80 border border-white/10 text-white focus:outline-none focus:border-brand-teal text-sm transition-all">
                    </div>

                    <div class="space-y-2">
                        <label for="requester_email" class="block text-xs font-semibold uppercase tracking-wider text-slate-300">E-mail</label>
                        <input type="email" id="requester_email" name="requester_email" value="{{ old('requester_email') }}" placeholder="maria@exemplo.com" autocomplete="email"
                               class="w-full px-4 py-3 rounded-xl bg-dark-950/80 border border-white/10 text-white focus:outline-none focus:border-brand-teal text-sm transition-all">
                    </div>
                </div>
            </div>

            <!-- Seção 1: Dados do Animal -->
            <div class="space-y-4 pt-4">
                <h3 class="font-outfit font-semibold text-lg text-white border-b border-white/5 pb-2">2. Dados do Animal</h3>
                
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                    <!-- Espécie -->
                    <div class="space-y-2">
                        <label for="species" class="block text-xs font-semibold uppercase tracking-wider text-slate-300">Espécie *</label>
                        <select id="species" name="species" required
                                class="w-full px-4 py-3 rounded-xl bg-dark-950/80 border border-white/10 text-white focus:outline-none focus:border-brand-teal text-sm transition-all">
                            <option value="">Selecione...</option>
                            <option value="Cão" {{ old('species') == 'Cão' ? 'selected' : '' }}>Cão</option>
                            <option value="Gato" {{ old('species') == 'Gato' ? 'selected' : '' }}>Gato</option>
                            <option value="Ave" {{ old('species') == 'Ave' ? 'selected' : '' }}>Ave</option>
                            <option value="Réptil" {{ old('species') == 'Réptil' ? 'selected' : '' }}>Réptil</option>
                            <option value="Roedor" {{ old('species') == 'Roedor' ? 'selected' : '' }}>Roedor</option>
                            <option value="Outro" {{ old('species') == 'Outro' ? 'selected' : '' }}>Outro</option>
                        </select>
                    </div>

                    <!-- Peso -->
                    <div class="space-y-2">
                        <label for="weight" class="block text-xs font-semibold uppercase tracking-wider text-slate-300">Peso do Animal (kg) *</label>
                        <input type="number" step="0.01" min="0.05" id="weight" name="weight" required value="{{ old('weight') }}" placeholder="Ex: 12.5"
                               class="w-full px-4 py-3 rounded-xl bg-dark-950/80 border border-white/10 text-white focus:outline-none focus:border-brand-teal text-sm transition-all">
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-6">
                    <!-- Raça -->
                    <div class="space-y-2">
                        <label for="breed" class="block text-xs font-semibold uppercase tracking-wider text-slate-300">Raça</label>
                        <input type="text" id="breed" name="breed" value="{{ old('breed') }}" placeholder="Ex: Labrador"
                               class="w-full px-4 py-3 rounded-xl bg-dark-950/80 border border-white/10 text-white focus:outline-none focus:border-brand-teal text-sm transition-all">
                    </div>

                    <!-- Idade -->
                    <div class="space-y-2">
                        <label for="age" class="block text-xs font-semibold uppercase tracking-wider text-slate-300">Idade Estimada</label>
                        <input type="text" id="age" name="age" value="{{ old('age') }}" placeholder="Ex: 5 anos"
                               class="w-full px-4 py-3 rounded-xl bg-dark-950/80 border border-white/10 text-white focus:outline-none focus:border-brand-teal text-sm transition-all">
                    </div>

                    <!-- Sexo -->
                    <div class="space-y-2">
                        <label for="sex" class="block text-xs font-semibold uppercase tracking-wider text-slate-300">Sexo</label>
                        <select id="sex" name="sex"
                                class="w-full px-4 py-3 rounded-xl bg-dark-950/80 border border-white/10 text-white focus:outline-none focus:border-brand-teal text-sm transition-all">
                            <option value="Indeterminado">Indeterminado</option>
                            <option value="Macho" {{ old('sex') == 'Macho' ? 'selected' : '' }}>Macho</option>
                            <option value="Fêmea" {{ old('sex') == 'Fêmea' ? 'selected' : '' }}>Fêmea</option>
                        </select>
                    </div>
                </div>

                <div class="grid grid-cols-1 gap-6">
                    <!-- Pelagem -->
                    <div class="space-y-2">
                        <label for="pelage" class="block text-xs font-semibold uppercase tracking-wider text-slate-300">Pelagem (Cor / Padrão)</label>
                        <input type="text" id="pelage" name="pelage" value="{{ old('pelage') }}" placeholder="Ex: Curta preta, longa dourada"
                               class="w-full px-4 py-3 rounded-xl bg-dark-950/80 border border-white/10 text-white focus:outline-none focus:border-brand-teal text-sm transition-all">
                    </div>
                </div>
            </div>

            <!-- Seção 2: Detalhes Clínicos e do Serviço -->
            <div class="space-y-4 pt-4">
                <h3 class="font-outfit font-semibold text-lg text-white border-b border-white/5 pb-2">3. Informações do Serviço</h3>
                
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                    <!-- Cremacão Plan -->
                    <div class="space-y-2">
                        <label class="block text-xs font-semibold uppercase tracking-wider text-slate-300">Possui plano de cremação/funerário? *</label>
                        <div class="flex items-center gap-6 py-2">
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input type="radio" name="has_cremation_plan" value="1" @click="hasCremation = true" {{ old('has_cremation_plan') == '1' ? 'checked' : '' }}
                                       class="w-4 h-4 text-brand-teal bg-dark-950 border-white/10 focus:ring-0">
                                <span>Sim</span>
                            </label>
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input type="radio" name="has_cremation_plan" value="0" @click="hasCremation = false" {{ old('has_cremation_plan') == '0' || is_null(old('has_cremation_plan')) ? 'checked' : '' }}
                                       class="w-4 h-4 text-brand-teal bg-dark-950 border-white/10 focus:ring-0">
                                <span>Não</span>
                            </label>
                        </div>
                    </div>

                    <!-- Euthanasia -->
                    <div class="space-y-2">
                        <label class="block text-xs font-semibold uppercase tracking-wider text-slate-300">Foi realizado eutanásia? *</label>
                        <div class="flex items-center gap-6 py-2">
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input type="radio" name="is_euthanasia" value="1" {{ old('is_euthanasia') == '1' ? 'checked' : '' }}
                                       class="w-4 h-4 text-brand-teal bg-dark-950 border-white/10 focus:ring-0">
                                <span>Sim</span>
                            </label>
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input type="radio" name="is_euthanasia" value="0" {{ old('is_euthanasia') == '0' || is_null(old('is_euthanasia')) ? 'checked' : '' }}
                                       class="w-4 h-4 text-brand-teal bg-dark-950 border-white/10 focus:ring-0">
                                <span>Não</span>
                            </label>
                        </div>
                    </div>
                </div>

                <!-- Empresa de Cremação (Exibido se Sim) -->
                <div class="space-y-2" x-show="hasCremation" x-transition>
                    <label for="cremation_company" class="block text-xs font-semibold uppercase tracking-wider text-slate-300">Nome da Empresa de Cremação *</label>
                    <input type="text" id="cremation_company" name="cremation_company" value="{{ old('cremation_company') }}" placeholder="Ex: Pet Memorial, CremaPet"
                           class="w-full px-4 py-3 rounded-xl bg-dark-950/80 border border-white/10 text-white focus:outline-none focus:border-brand-teal text-sm transition-all">
                </div>

                <div class="grid grid-cols-1 gap-6 pt-2">
                    <!-- Solicitado por Veterinário -->
                    <div class="space-y-2">
                        <label class="block text-xs font-semibold uppercase tracking-wider text-slate-300">A necropsia foi solicitada por Médico Veterinário? *</label>
                        <div class="flex items-center gap-6 py-2">
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input type="radio" name="requested_by_vet" value="1" @click="isVet = true" {{ old('requested_by_vet') == '1' ? 'checked' : '' }}
                                       class="w-4 h-4 text-brand-teal bg-dark-950 border-white/10 focus:ring-0">
                                <span>Sim (Aplica desconto de 10%)</span>
                            </label>
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input type="radio" name="requested_by_vet" value="0" @click="isVet = false" {{ old('requested_by_vet') == '0' || is_null(old('requested_by_vet')) ? 'checked' : '' }}
                                       class="w-4 h-4 text-brand-teal bg-dark-950 border-white/10 focus:ring-0">
                                <span>Não (Solicitação do tutor/Responsável)</span>
                            </label>
                        </div>
                    </div>
                </div>

                <!-- Campos do Veterinário (Exibido se Sim) -->
                <div class="space-y-4 border border-white/5 bg-white/5 rounded-2xl p-4 sm:p-6" x-show="isVet" x-transition>
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-6">
                        <!-- CRMV -->
                        <div class="space-y-2">
                            <label for="vet_crmv" class="block text-xs font-semibold uppercase tracking-wider text-slate-300">Número do CRMV *</label>
                            <input type="text" id="vet_crmv" name="vet_crmv" value="{{ old('vet_crmv') }}" placeholder="Ex: 12345"
                                   class="w-full px-4 py-3 rounded-xl bg-dark-950/80 border border-white/10 text-white focus:outline-none focus:border-brand-teal text-sm transition-all">
                        </div>

                        <!-- Estado CRMV -->
                        <div class="space-y-2">
                            <label for="vet_state" class="block text-xs font-semibold uppercase tracking-wider text-slate-300">Estado (UF) *</label>
                            <input type="text" id="vet_state" name="vet_state" value="{{ old('vet_state') }}" placeholder="Ex: SP" maxlength="2"
                                   class="w-full px-4 py-3 rounded-xl bg-dark-950/80 border border-white/10 text-white focus:outline-none focus:border-brand-teal text-sm transition-all uppercase">
                        </div>

                        <!-- Clínica/Estabelecimento -->
                        <div class="space-y-2">
                            <label for="vet_establishment" class="block text-xs font-semibold uppercase tracking-wider text-slate-300">Nome da Clínica / Lab</label>
                            <input type="text" id="vet_establishment" name="vet_establishment" value="{{ old('vet_establishment') }}" placeholder="Ex: Hospital Vet S.O.S."
                                   class="w-full px-4 py-3 rounded-xl bg-dark-950/80 border border-white/10 text-white focus:outline-none focus:border-brand-teal text-sm transition-all">
                        </div>
                    </div>
                </div>
            </div>

            <!-- Botão Submit -->
            <div class="pt-6">
                <button type="submit"
                        class="w-full py-4 rounded-xl bg-gradient-to-r from-brand-teal to-brand-indigo text-white font-bold text-center shadow-lg shadow-brand-teal/20 hover:brightness-110 active:scale-95 transition-all text-sm uppercase tracking-wider">
                    {{ $direct ? 'Prosseguir para o Termo' : 'Calcular Orçamento & Prosseguir' }}
                </button>
            </div>
        </form>

        <!-- Voltar home -->
        <div class="text-center pt-6 border-t border-white/5 mt-6">
            <a href="/" class="text-xs text-brand-teal hover:underline font-semibold">
                ← Voltar ao site principal
            </a>
        </div>
    </div>
    @include('partials.whatsapp_widget')
</body>
</html>
