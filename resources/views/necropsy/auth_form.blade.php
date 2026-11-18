<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Autorização de Exame Necroscópico - Prof. Atilio</title>
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
        canvas {
            touch-action: none;
        }
    </style>
</head>
<body class="font-sans text-slate-200 min-h-screen flex items-center justify-center p-4 py-12">
    <div class="w-full max-w-3xl glass-card rounded-3xl p-8 sm:p-12 shadow-2xl shadow-brand-teal/5">
        
        <!-- Header -->
        <div class="text-center mb-8">
            <h1 class="font-outfit font-extrabold text-3xl sm:text-4xl text-white tracking-tight bg-gradient-to-r from-brand-teal via-brand-indigo to-brand-violet bg-clip-text text-transparent">
                Autorização de Necropsia
            </h1>
            <p class="text-slate-400 mt-2 text-sm sm:text-base">
                Preencha os dados de autorização e assine o termo digitalmente.
            </p>
        </div>

        <!-- Stepper -->
        <div class="flex items-center justify-between max-w-md mx-auto mb-8 text-xs sm:text-sm font-semibold tracking-wide uppercase">
            <div class="flex items-center gap-2 text-slate-400">
                <span class="w-6 h-6 rounded-full bg-white/5 border border-white/10 flex items-center justify-center text-xs">✓</span>
                <span>Orçamento</span>
            </div>
            <div class="flex-1 h-px bg-brand-teal mx-4"></div>
            <div class="flex items-center gap-2 text-brand-teal">
                <span class="w-6 h-6 rounded-full bg-brand-teal/20 border border-brand-teal flex items-center justify-center text-xs">2</span>
                <span>Autorização</span>
            </div>
            <div class="flex-1 h-px bg-white/10 mx-4"></div>
            <div class="flex items-center gap-2 text-slate-500">
                <span class="w-6 h-6 rounded-full bg-white/5 border border-white/10 flex items-center justify-center text-xs">3</span>
                <span>Conclusão</span>
            </div>
        </div>

        <!-- Orcamento Card -->
        <div class="mb-8 p-6 rounded-2xl bg-gradient-to-r from-brand-teal/10 via-brand-indigo/10 to-transparent border border-brand-teal/20 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
            <div>
                <h4 class="font-outfit font-bold text-lg text-white">Resumo do Orçamento</h4>
                <p class="text-xs text-slate-400 mt-1">
                    Animal: {{ $necropsyRequest->species }} @if($necropsyRequest->breed) ({{ $necropsyRequest->breed }}) @endif | Peso: {{ $necropsyRequest->weight }} kg
                </p>
                @if($necropsyRequest->requested_by_vet)
                    <p class="text-xs text-brand-teal font-semibold mt-0.5">
                        ✓ Desconto de 10% aplicado (Solicitação de Veterinário)
                    </p>
                @endif
                <p class="text-[11px] text-brand-teal font-semibold mt-1">
                    ✓ Incluso: relatório macroscópico, exame histopatológico e fotodocumentação.
                </p>
            </div>
            <div class="text-left sm:text-right">
                <span class="block text-xs uppercase font-semibold text-slate-400 tracking-wider">Valor do Serviço</span>
                <span class="text-2xl sm:text-3xl font-outfit font-black text-brand-teal bg-gradient-to-r from-brand-teal to-white bg-clip-text text-transparent">
                    R$ {{ number_format($necropsyRequest->final_price, 2, ',', '.') }}
                </span>
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
        <form action="{{ route('necropsy.autorizar.store', ['token' => $necropsyRequest->token]) }}" method="POST" id="authForm" class="space-y-6" x-data="{ hasProsthesis: false, hadSurgery: false }">
            @csrf

            <!-- Nome do Animal (Completo) -->
            <div class="space-y-4">
                <h3 class="font-outfit font-semibold text-lg text-white border-b border-white/5 pb-2">1. Identificação do Animal</h3>
                
                <div class="space-y-2">
                    <label for="animal_name" class="block text-xs font-semibold uppercase tracking-wider text-slate-300">Nome do Animal *</label>
                    <input type="text" id="animal_name" name="animal_name" required value="{{ old('animal_name', $necropsyRequest->animal_name) }}" placeholder="Ex: Bob, Mel"
                           class="w-full px-4 py-3 rounded-xl bg-dark-950/80 border border-white/10 text-white focus:outline-none focus:border-brand-teal text-sm transition-all">
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-6 pt-2">
                    <!-- Prosthesis -->
                    <div class="space-y-2">
                        <label class="block text-xs font-semibold uppercase tracking-wider text-slate-300">O animal possui próteses? *</label>
                        <div class="flex items-center gap-6 py-2">
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input type="radio" name="has_prosthesis" value="1" @click="hasProsthesis = true" {{ old('has_prosthesis') == '1' ? 'checked' : '' }}
                                       class="w-4 h-4 text-brand-teal bg-dark-950 border-white/10 focus:ring-0">
                                <span>Sim</span>
                            </label>
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input type="radio" name="has_prosthesis" value="0" @click="hasProsthesis = false" {{ old('has_prosthesis') == '0' || is_null(old('has_prosthesis')) ? 'checked' : '' }}
                                       class="w-4 h-4 text-brand-teal bg-dark-950 border-white/10 focus:ring-0">
                                <span>Não</span>
                            </label>
                        </div>
                    </div>

                    <!-- Surgery -->
                    <div class="space-y-2">
                        <label class="block text-xs font-semibold uppercase tracking-wider text-slate-300">O animal já realizou cirurgia? *</label>
                        <div class="flex items-center gap-6 py-2">
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input type="radio" name="had_surgery" value="1" @click="hadSurgery = true" {{ old('had_surgery') == '1' ? 'checked' : '' }}
                                       class="w-4 h-4 text-brand-teal bg-dark-950 border-white/10 focus:ring-0">
                                <span>Sim</span>
                            </label>
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input type="radio" name="had_surgery" value="0" @click="hadSurgery = false" {{ old('had_surgery') == '0' || is_null(old('had_surgery')) ? 'checked' : '' }}
                                       class="w-4 h-4 text-brand-teal bg-dark-950 border-white/10 focus:ring-0">
                                <span>Não</span>
                            </label>
                        </div>
                    </div>
                </div>

                <!-- Detalhes de Próteses -->
                <div class="space-y-2" x-show="hasProsthesis" x-transition>
                    <label for="prosthesis_details" class="block text-xs font-semibold uppercase tracking-wider text-slate-300">Localização e tipo de prótese *</label>
                    <input type="text" id="prosthesis_details" name="prosthesis_details" value="{{ old('prosthesis_details') }}" placeholder="Ex: Placa de titânio no fêmur esquerdo"
                           class="w-full px-4 py-3 rounded-xl bg-dark-950/80 border border-white/10 text-white focus:outline-none focus:border-brand-teal text-sm transition-all">
                </div>

                <!-- Detalhes de Cirurgia -->
                <div class="space-y-2" x-show="hadSurgery" x-transition>
                    <label for="surgery_details" class="block text-xs font-semibold uppercase tracking-wider text-slate-300">Tipo e local da cirurgia *</label>
                    <input type="text" id="surgery_details" name="surgery_details" value="{{ old('surgery_details') }}" placeholder="Ex: Castração, OSH, Retirada de nódulo esplênico"
                           class="w-full px-4 py-3 rounded-xl bg-dark-950/80 border border-white/10 text-white focus:outline-none focus:border-brand-teal text-sm transition-all">
                </div>
            </div>

            <!-- Dados do Requisitante -->
            <div class="space-y-4 pt-4">
                <h3 class="font-outfit font-semibold text-lg text-white border-b border-white/5 pb-2">2. Dados do Requisitante (Responsável Legal)</h3>
                
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                    <!-- Nome -->
                    <div class="space-y-2">
                        <label for="requester_name" class="block text-xs font-semibold uppercase tracking-wider text-slate-300">Nome Completo *</label>
                        <input type="text" id="requester_name" name="requester_name" required value="{{ old('requester_name', $necropsyRequest->requester_name) }}" placeholder="Ex: Maria de Oliveira"
                               class="w-full px-4 py-3 rounded-xl bg-dark-950/80 border border-white/10 text-white focus:outline-none focus:border-brand-teal text-sm transition-all">
                    </div>

                    <!-- E-mail -->
                    <div class="space-y-2">
                        <label for="requester_email" class="block text-xs font-semibold uppercase tracking-wider text-slate-300">E-mail *</label>
                        <input type="email" id="requester_email" name="requester_email" required value="{{ old('requester_email', $necropsyRequest->requester_email) }}" placeholder="maria@exemplo.com"
                               class="w-full px-4 py-3 rounded-xl bg-dark-950/80 border border-white/10 text-white focus:outline-none focus:border-brand-teal text-sm transition-all">
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-6">
                    <!-- Telefone -->
                    <div class="space-y-2">
                        <label for="requester_phone" class="block text-xs font-semibold uppercase tracking-wider text-slate-300">Telefone / WhatsApp *</label>
                        <input type="text" id="requester_phone" name="requester_phone" required value="{{ old('requester_phone', $necropsyRequest->requester_phone) }}" placeholder="Ex: (11) 99999-9999"
                               class="w-full px-4 py-3 rounded-xl bg-dark-950/80 border border-white/10 text-white focus:outline-none focus:border-brand-teal text-sm transition-all">
                    </div>

                    <!-- CPF -->
                    <div class="space-y-2">
                        <label for="requester_cpf" class="block text-xs font-semibold uppercase tracking-wider text-slate-300">CPF *</label>
                        <input type="text" id="requester_cpf" name="requester_cpf" required value="{{ old('requester_cpf') }}" placeholder="Ex: 123.456.789-00"
                               class="w-full px-4 py-3 rounded-xl bg-dark-950/80 border border-white/10 text-white focus:outline-none focus:border-brand-teal text-sm transition-all">
                    </div>

                    <!-- RG -->
                    <div class="space-y-2">
                        <label for="requester_rg" class="block text-xs font-semibold uppercase tracking-wider text-slate-300">RG *</label>
                        <input type="text" id="requester_rg" name="requester_rg" required value="{{ old('requester_rg') }}" placeholder="Ex: 12.345.678-9"
                               class="w-full px-4 py-3 rounded-xl bg-dark-950/80 border border-white/10 text-white focus:outline-none focus:border-brand-teal text-sm transition-all">
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-6">
                    <!-- CNPJ (opcional) -->
                    <div class="space-y-2 sm:col-span-1">
                        <label for="requester_cnpj" class="block text-xs font-semibold uppercase tracking-wider text-slate-300">CNPJ (Opcional)</label>
                        <input type="text" id="requester_cnpj" name="requester_cnpj" value="{{ old('requester_cnpj') }}" placeholder="Ex: 00.000.000/0001-00"
                               class="w-full px-4 py-3 rounded-xl bg-dark-950/80 border border-white/10 text-white focus:outline-none focus:border-brand-teal text-sm transition-all">
                    </div>

                    <!-- Endereço -->
                    <div class="space-y-2 sm:col-span-2">
                        <label for="requester_address" class="block text-xs font-semibold uppercase tracking-wider text-slate-300">Endereço (Rua, Número) *</label>
                        <input type="text" id="requester_address" name="requester_address" required value="{{ old('requester_address') }}" placeholder="Ex: Av. Paulista, 1000"
                               class="w-full px-4 py-3 rounded-xl bg-dark-950/80 border border-white/10 text-white focus:outline-none focus:border-brand-teal text-sm transition-all">
                    </div>
                </div>

                <div class="grid grid-cols-2 sm:grid-cols-4 gap-6">
                    <!-- Bairro -->
                    <div class="space-y-2 col-span-2 sm:col-span-1">
                        <label for="requester_neighborhood" class="block text-xs font-semibold uppercase tracking-wider text-slate-300">Bairro *</label>
                        <input type="text" id="requester_neighborhood" name="requester_neighborhood" required value="{{ old('requester_neighborhood') }}" placeholder="Bela Vista"
                               class="w-full px-4 py-3 rounded-xl bg-dark-950/80 border border-white/10 text-white focus:outline-none focus:border-brand-teal text-sm transition-all">
                    </div>

                    <!-- Complemento -->
                    <div class="space-y-2 col-span-2 sm:col-span-1">
                        <label for="requester_complement" class="block text-xs font-semibold uppercase tracking-wider text-slate-300">Complemento</label>
                        <input type="text" id="requester_complement" name="requester_complement" value="{{ old('requester_complement') }}" placeholder="Apto 42"
                               class="w-full px-4 py-3 rounded-xl bg-dark-950/80 border border-white/10 text-white focus:outline-none focus:border-brand-teal text-sm transition-all">
                    </div>

                    <!-- CEP -->
                    <div class="space-y-2 col-span-1">
                        <label for="requester_zip" class="block text-xs font-semibold uppercase tracking-wider text-slate-300">CEP *</label>
                        <input type="text" id="requester_zip" name="requester_zip" required value="{{ old('requester_zip') }}" placeholder="01310-100"
                               class="w-full px-4 py-3 rounded-xl bg-dark-950/80 border border-white/10 text-white focus:outline-none focus:border-brand-teal text-sm transition-all">
                    </div>

                    <!-- Cidade / Estado -->
                    <div class="space-y-2 col-span-1 flex gap-2">
                        <div class="w-2/3">
                            <label for="requester_city" class="block text-xs font-semibold uppercase tracking-wider text-slate-300">Cidade *</label>
                            <input type="text" id="requester_city" name="requester_city" required value="{{ old('requester_city') }}" placeholder="São Paulo"
                                   class="w-full px-2 py-3 rounded-xl bg-dark-950/80 border border-white/10 text-white focus:outline-none focus:border-brand-teal text-sm transition-all">
                        </div>
                        <div class="w-1/3">
                            <label for="requester_state" class="block text-xs font-semibold uppercase tracking-wider text-slate-300">UF *</label>
                            <input type="text" id="requester_state" name="requester_state" required value="{{ old('requester_state') }}" placeholder="SP" maxlength="2"
                                   class="w-full px-2 py-3 rounded-xl bg-dark-950/80 border border-white/10 text-white focus:outline-none focus:border-brand-teal text-sm transition-all uppercase">
                        </div>
                    </div>
                </div>
            </div>

            <!-- Testemunhas (Opcional) -->
            <div class="space-y-4 pt-4">
                <h3 class="font-outfit font-semibold text-lg text-white border-b border-white/5 pb-2">3. Testemunhas (Opcional)</h3>
                
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                    <!-- Testemunha 1 -->
                    <div class="space-y-4 border border-white/5 bg-white/5 rounded-2xl p-4">
                        <h4 class="text-xs font-semibold uppercase tracking-wider text-brand-teal">Testemunha 1</h4>
                        <div class="space-y-2">
                            <label class="block text-xs text-slate-400">Nome Completo</label>
                            <input type="text" name="witness1_name" value="{{ old('witness1_name') }}" placeholder="Nome da testemunha 1"
                                   class="w-full px-3 py-2.5 rounded-lg bg-dark-950/85 border border-white/10 text-white focus:outline-none focus:border-brand-teal text-xs transition-all">
                        </div>
                        <div class="space-y-2">
                            <label class="block text-xs text-slate-400">CPF</label>
                            <input type="text" name="witness1_cpf" value="{{ old('witness1_cpf') }}" placeholder="000.000.000-00"
                                   class="w-full px-3 py-2.5 rounded-lg bg-dark-950/85 border border-white/10 text-white focus:outline-none focus:border-brand-teal text-xs transition-all">
                        </div>
                    </div>

                    <!-- Testemunha 2 -->
                    <div class="space-y-4 border border-white/5 bg-white/5 rounded-2xl p-4">
                        <h4 class="text-xs font-semibold uppercase tracking-wider text-brand-teal">Testemunha 2</h4>
                        <div class="space-y-2">
                            <label class="block text-xs text-slate-400">Nome Completo</label>
                            <input type="text" name="witness2_name" value="{{ old('witness2_name') }}" placeholder="Nome da testemunha 2"
                                   class="w-full px-3 py-2.5 rounded-lg bg-dark-950/85 border border-white/10 text-white focus:outline-none focus:border-brand-teal text-xs transition-all">
                        </div>
                        <div class="space-y-2">
                            <label class="block text-xs text-slate-400">CPF</label>
                            <input type="text" name="witness2_cpf" value="{{ old('witness2_cpf') }}" placeholder="000.000.000-00"
                                   class="w-full px-3 py-2.5 rounded-lg bg-dark-950/85 border border-white/10 text-white focus:outline-none focus:border-brand-teal text-xs transition-all">
                        </div>
                    </div>
                </div>
            </div>

            <!-- Termo e Assinatura -->
            <div class="space-y-4 pt-4">
                <h3 class="font-outfit font-semibold text-lg text-white border-b border-white/5 pb-2">4. Termo de Consentimento e Assinatura</h3>

                <!-- Cláusulas do Termo -->
                <div class="p-6 rounded-2xl bg-dark-950/90 border border-white/10 text-slate-300 text-xs leading-relaxed max-h-60 overflow-y-auto space-y-4">
                    <h4 class="font-bold text-center text-white text-sm uppercase">AUTORIZAÇÃO PARA EXAME NECROSCÓPICO</h4>
                    <p>
                        Como requisitante do exame necroscópico do animal acima identificado, autorizo a realização de necropsia compreendendo a emissão de relatório macroscópico, realização de exame histopatológico e fotodocumentação.
                    </p>
                    <p>
                        Declaro estar ciente que, no caso da necropsia cosmética, devo retirar o cadáver até 5 dias úteis após o exame. No caso de este prazo ter sido ultrapassado, e na necropsia documentada ou diagnóstica, o cadáver receberá a destinação estipulada pelas normas sanitárias vigentes no município, estando ciente que o mesmo não poderá ser recuperado posteriormente.
                    </p>
                    <p>
                        Em caso de cremação por empresa externa, a remoção e procedimentos são de responsabilidade de contrato entre o solicitante e empresa terceirizada.
                    </p>
                    <p>
                        Declaro estar ciente que: o prazo para entrega do relatório final é de 30 dias após a realização da necropsia, podendo ser estendido devido à complexidade do caso e necessidade de testes complementares;
                    </p>
                    <p>
                        Para manutenção de matrizes biológicas destinadas a outros exames, que não solicitadas pelo patologista responsável, será cobrada taxa adicional para a sua manutenção;
                    </p>
                    <p>
                        O histórico clínico fornecido é de responsabilidade do Médico Veterinário e Responsável Legal, sendo o mesmo transcrito tal como está na requisição de exame, não sendo passível de alteração posterior;
                    </p>
                    <p>
                        As alterações decorrentes do processo de congelamento ou acondicionamento anterior a entrega do animal ao patologista responsável são de responsabilidade do Responsável Legal e/ou Médico Veterinário;
                    </p>
                    <p>
                        Não é de responsabilidade do patologista responsável a ausência de informações no histórico clínico encaminhado;
                    </p>
                    <p>
                        O esclarecimento de dúvidas somente será realizado mediante agendamento prévio via email (ascalefi@gmail.com), comunicação por outras vias serão desconsideradas devido a necessidade de agendamento de horário condizente com a agenda do patologista;
                    </p>
                    <p>
                        A realização de assessoria técnica Médico-Veterinária destinada a propositura judicial não está contemplada no exame necroscópico, para tal deve ser realizada solicitação para encaminhamento de orçamento.
                    </p>
                    <p>
                        No caso de o requisitante ser médico veterinário, este declara que o proprietário autorizou este exame e descarte no estabelecimento médico-veterinário de origem.
                    </p>
                </div>

                <!-- Check de Aceite -->
                <div class="space-y-3 py-2">
                    <label class="flex items-start gap-3 cursor-pointer text-xs sm:text-sm text-slate-300">
                        <input type="checkbox" name="terms_accepted" required value="1"
                               class="w-4.5 h-4.5 rounded bg-dark-950 border-white/10 text-brand-teal focus:ring-0 mt-0.5">
                        <span>
                            Consinto e declaro ciência de todas as cláusulas do termo acima, incluindo prazos de laudo, destinação de cadáver e responsabilidade sobre o histórico clínico do animal.
                        </span>
                    </label>
                </div>

                <!-- Assinatura Extenso -->
                <div class="space-y-2 pt-2">
                    <label for="signature_name" class="block text-xs font-semibold uppercase tracking-wider text-slate-300">Nome Completo do Assinante (Por Extenso) *</label>
                    <input type="text" id="signature_name" name="signature_name" required value="{{ old('signature_name') }}" placeholder="Digite seu nome completo exatamente como consta nos documentos"
                           class="w-full px-4 py-3 rounded-xl bg-dark-950/80 border border-white/10 text-white focus:outline-none focus:border-brand-teal text-sm transition-all">
                </div>

                <!-- Assinatura Pad (Canvas) -->
                <div class="space-y-2 pt-2">
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-300">Assinatura Digital Desenhada (Utilize o Mouse ou Tela Touch) *</label>
                    
                    <div class="w-full bg-slate-900 border border-white/10 rounded-2xl p-4 flex flex-col items-center">
                        <canvas id="sigCanvas" class="border border-dashed border-white/20 bg-dark-950 rounded-xl cursor-crosshair max-w-full" width="600" height="200"></canvas>
                        
                        <div class="flex items-center gap-4 mt-3">
                            <button type="button" id="clearBtn"
                                    class="px-4 py-2 rounded-lg bg-white/5 border border-white/10 hover:bg-white/10 text-xs font-semibold transition-all">
                                Limpar Desenho
                            </button>
                            <span class="text-[10px] text-slate-500">
                                Sua assinatura será vinculada forensicamente ao seu IP, data e hora.
                            </span>
                        </div>
                    </div>
                    <!-- Hidden input to store image data -->
                    <input type="hidden" name="signature_image" id="sigVal">
                </div>
            </div>

            <!-- Botões de Ação -->
            <div class="flex flex-col sm:flex-row gap-4 pt-6">
                <a href="{{ route('necropsy.orcamento') }}"
                   class="w-full sm:w-1/3 py-4 rounded-xl border border-white/10 hover:bg-white/5 text-slate-300 font-bold text-center transition-all text-sm uppercase tracking-wider">
                    Voltar / Alterar
                </a>
                <button type="submit" id="submitBtn"
                        class="w-full sm:w-2/3 py-4 rounded-xl bg-gradient-to-r from-brand-teal to-brand-indigo text-white font-bold text-center shadow-lg shadow-brand-teal/20 hover:brightness-110 active:scale-95 transition-all text-sm uppercase tracking-wider">
                    Confirmar & Assinar Digitalmente
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

    <!-- Canvas Drawing Script -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const canvas = document.getElementById('sigCanvas');
            const ctx = canvas.getContext('2d');
            const clearBtn = document.getElementById('clearBtn');
            const sigVal = document.getElementById('sigVal');
            const form = document.getElementById('authForm');

            // Setup styling for line drawing
            ctx.strokeStyle = '#0d9488'; // Teal
            ctx.lineWidth = 3;
            ctx.lineCap = 'round';

            let drawing = false;
            let lastX = 0;
            let lastY = 0;
            let drawCount = 0;

            function getMousePos(canvasDom, touchOrMouseEvent) {
                const rect = canvasDom.getBoundingClientRect();
                const clientX = touchOrMouseEvent.touches ? touchOrMouseEvent.touches[0].clientX : touchOrMouseEvent.clientX;
                const clientY = touchOrMouseEvent.touches ? touchOrMouseEvent.touches[0].clientY : touchOrMouseEvent.clientY;
                
                // Adjust for CSS scaling
                return {
                    x: (clientX - rect.left) * (canvasDom.width / rect.width),
                    y: (clientY - rect.top) * (canvasDom.height / rect.height)
                };
            }

            function startDrawing(e) {
                drawing = true;
                const pos = getMousePos(canvas, e);
                lastX = pos.x;
                lastY = pos.y;
                drawCount++;
            }

            function draw(e) {
                if (!drawing) return;
                e.preventDefault();
                const pos = getMousePos(canvas, e);
                
                ctx.beginPath();
                ctx.moveTo(lastX, lastY);
                ctx.lineTo(pos.x, pos.y);
                ctx.stroke();
                
                lastX = pos.x;
                lastY = pos.y;
            }

            function stopDrawing() {
                drawing = false;
            }

            // Mouse Events
            canvas.addEventListener('mousedown', startDrawing);
            canvas.addEventListener('mousemove', draw);
            window.addEventListener('mouseup', stopDrawing);

            // Touch Events (Mobile)
            canvas.addEventListener('touchstart', startDrawing);
            canvas.addEventListener('touchmove', draw);
            canvas.addEventListener('touchend', stopDrawing);

            // Clear Button
            clearBtn.addEventListener('click', function() {
                ctx.clearRect(0, 0, canvas.width, canvas.height);
                sigVal.value = '';
                drawCount = 0;
            });

            // Populate base64 value before submission
            form.addEventListener('submit', function(e) {
                if (drawCount === 0) {
                    alert('Por favor, faça a sua assinatura no quadro desenhado.');
                    e.preventDefault();
                    return;
                }
                
                // Save canvas content to input value
                const dataURL = canvas.toDataURL('image/png');
                sigVal.value = dataURL;
            });
        });
    </script>
    @include('partials.whatsapp_widget')
</body>
</html>
