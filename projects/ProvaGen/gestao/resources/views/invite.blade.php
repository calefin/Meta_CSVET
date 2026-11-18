<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ ($category ?? 'regular') === 'external' ? 'Cadastro de Aluno Externo' : 'Pré-Cadastro de Orientando' }} - Prof. Atilio</title>
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
    </style>
</head>
<body class="font-sans text-slate-200 min-h-screen flex flex-col justify-start py-6 px-4 sm:items-center sm:justify-center sm:py-12">
    <div class="w-full max-w-2xl bg-dark-900/60 backdrop-blur-xl border border-white/10 rounded-3xl p-6 sm:p-12 shadow-2xl shadow-brand-teal/5">
        
        <!-- Header -->
        @php $isExternal = ($category ?? 'regular') === 'external'; @endphp
        <div class="text-center mb-10">
            <h1 class="font-outfit font-extrabold text-3xl sm:text-4xl text-white tracking-tight bg-gradient-to-r from-brand-teal via-brand-indigo to-brand-violet bg-clip-text text-transparent">
                {{ $isExternal ? 'Cadastro de Aluno Externo' : 'Pré-Cadastro de Orientando' }}
            </h1>
            <p class="text-slate-400 mt-2 text-sm sm:text-base">
                {{ $isExternal ? 'Mentoria & Tutoria Acadêmica' : 'Plataforma de Orientação Acadêmica' }} — Prof. Dr. Atilio Calefi
            </p>
            @if($isExternal)
                <div class="mt-3 inline-flex items-center gap-2 px-3 py-1.5 rounded-full bg-amber-900/40 border border-amber-700/50 text-amber-300 text-xs font-semibold">
                    Aluno Externo / Mentoria
                </div>
            @endif
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
        <form action="/invite/register" method="POST" class="space-y-6">
            @csrf
            <input type="hidden" name="token" value="{{ $token }}">

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                <!-- Nome Completo -->
                <div class="space-y-2">
                    <label for="name" class="block text-xs font-semibold uppercase tracking-wider text-slate-300">Nome Completo</label>
                    <input type="text" id="name" name="name" required value="{{ old('name') }}" placeholder="Ex: João da Silva"
                           class="w-full px-4 py-3 rounded-xl bg-dark-950/80 border border-white/10 text-white focus:outline-none focus:border-brand-teal text-sm transition-all">
                </div>

                <!-- E-mail -->
                <div class="space-y-2">
                    <label for="email" class="block text-xs font-semibold uppercase tracking-wider text-slate-300">E-mail Acadêmico / Pessoal</label>
                    <input type="email" id="email" name="email" required value="{{ old('email') }}" placeholder="joao@exemplo.com"
                           class="w-full px-4 py-3 rounded-xl bg-dark-950/80 border border-white/10 text-white focus:outline-none focus:border-brand-teal text-sm transition-all">
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                <!-- Senha -->
                <div class="space-y-2">
                    <label for="password" class="block text-xs font-semibold uppercase tracking-wider text-slate-300">Senha de Acesso</label>
                    <input type="password" id="password" name="password" required placeholder="Mínimo 8 caracteres"
                           class="w-full px-4 py-3 rounded-xl bg-dark-950/80 border border-white/10 text-white focus:outline-none focus:border-brand-teal text-sm transition-all">
                </div>

                <!-- Confirmação de Senha -->
                <div class="space-y-2">
                    <label for="password_confirmation" class="block text-xs font-semibold uppercase tracking-wider text-slate-300">Confirmar Senha</label>
                    <input type="password" id="password_confirmation" name="password_confirmation" required placeholder="Repita a senha"
                           class="w-full px-4 py-3 rounded-xl bg-dark-950/80 border border-white/10 text-white focus:outline-none focus:border-brand-teal text-sm transition-all">
                </div>
            </div>

            @if($isExternal)
            <!-- CPF — obrigatório para alunos externos (consta no certificado) -->
            <div class="space-y-2">
                <label for="cpf" class="block text-xs font-semibold uppercase tracking-wider text-slate-300">
                    CPF <span class="text-amber-400">*</span>
                </label>
                <input type="text" id="cpf" name="cpf" required value="{{ old('cpf') }}"
                       placeholder="000.000.000-00"
                       maxlength="14"
                       class="w-full px-4 py-3 rounded-xl bg-dark-950/80 border border-amber-700/50 text-white focus:outline-none focus:border-amber-400 text-sm transition-all">
                <p class="text-xs text-amber-400/70">Obrigatório — será impresso no certificado exatamente como digitado.</p>
            </div>
            @endif

            <div class="grid grid-cols-1 {{ $isExternal ? 'sm:grid-cols-2' : 'sm:grid-cols-3' }} gap-6">
                @if(!$isExternal)
                <!-- Registro Geral de Matrícula (RGM) -->
                <div class="space-y-2">
                    <label for="registration" class="block text-xs font-semibold uppercase tracking-wider text-slate-300">Matrícula / RGM</label>
                    <input type="text" id="registration" name="registration" required value="{{ old('registration') }}" placeholder="RGM..."
                           class="w-full px-4 py-3 rounded-xl bg-dark-950/80 border border-white/10 text-white focus:outline-none focus:border-brand-teal text-sm transition-all">
                </div>
                @endif

                <!-- Instituição -->
                <div class="space-y-2">
                    <label for="institution" class="block text-xs font-semibold uppercase tracking-wider text-slate-300">
                        {{ $isExternal ? 'Instituição (Opcional)' : 'Universidade / Instituição' }}
                    </label>
                    <input type="text" id="institution" name="institution" {{ $isExternal ? '' : 'required' }} value="{{ old('institution') }}"
                           placeholder="{{ $isExternal ? 'Ex: CRMV, hospital, clínica...' : 'Ex: USP, UEL...' }}"
                           class="w-full px-4 py-3 rounded-xl bg-dark-950/80 border border-white/10 text-white focus:outline-none focus:border-brand-teal text-sm transition-all">
                </div>

                <!-- Campus -->
                <div class="space-y-2">
                    <label for="campus" class="block text-xs font-semibold uppercase tracking-wider text-slate-300">
                        {{ $isExternal ? 'Campus / Unidade (Opcional)' : 'Campus / Unidade' }}
                    </label>
                    <input type="text" id="campus" name="campus" {{ $isExternal ? '' : 'required' }} value="{{ old('campus') }}" placeholder="Ex: Butantã, Central..."
                           class="w-full px-4 py-3 rounded-xl bg-dark-950/80 border border-white/10 text-white focus:outline-none focus:border-brand-teal text-sm transition-all">
                </div>
            </div>

            <div class="grid grid-cols-1 {{ $isExternal ? 'sm:grid-cols-2' : 'sm:grid-cols-3' }} gap-6">
                <!-- Nível Acadêmico -->
                <div class="space-y-2">
                    <label for="academic_level" class="block text-xs font-semibold uppercase tracking-wider text-slate-300">Nível Acadêmico</label>
                    <select id="academic_level" name="academic_level" required
                            class="w-full max-w-full px-4 py-3 rounded-xl bg-dark-950/80 border border-white/10 text-slate-300 focus:outline-none focus:border-brand-teal text-sm transition-all">
                        <option value="Graduação" {{ old('academic_level') === 'Graduação' ? 'selected' : '' }}>Graduação</option>
                        <option value="Especialização" {{ old('academic_level') === 'Especialização' ? 'selected' : '' }}>Especialização</option>
                        <option value="Mestrado" {{ old('academic_level') === 'Mestrado' ? 'selected' : '' }}>Mestrado</option>
                        <option value="Doutorado" {{ old('academic_level') === 'Doutorado' ? 'selected' : '' }}>Doutorado</option>
                        @if($isExternal)
                        <option value="Outro" {{ old('academic_level') === 'Outro' ? 'selected' : '' }}>Outro / Não aplicável</option>
                        @endif
                    </select>
                </div>

                @if(!$isExternal)
                <!-- Período -->
                <div class="space-y-2">
                    <label for="period" class="block text-xs font-semibold uppercase tracking-wider text-slate-300">Período</label>
                    <select id="period" name="period" required
                            class="w-full max-w-full px-4 py-3 rounded-xl bg-dark-950/80 border border-white/10 text-slate-300 focus:outline-none focus:border-brand-teal text-sm transition-all">
                        <option value="">Selecione...</option>
                        <option value="Matutino" {{ old('period') === 'Matutino' ? 'selected' : '' }}>Matutino</option>
                        <option value="Vespertino" {{ old('period') === 'Vespertino' ? 'selected' : '' }}>Vespertino</option>
                        <option value="Noturno" {{ old('period') === 'Noturno' ? 'selected' : '' }}>Noturno</option>
                    </select>
                </div>
                @endif

                <!-- Telefone -->
                <div class="space-y-2">
                    <label for="phone" class="block text-xs font-semibold uppercase tracking-wider text-slate-300">Telefone / WhatsApp</label>
                    <input type="text" id="phone" name="phone" required value="{{ old('phone') }}" placeholder="Ex: (11) 99999-9999"
                           class="w-full px-4 py-3 rounded-xl bg-dark-950/80 border border-white/10 text-white focus:outline-none focus:border-brand-teal text-sm transition-all">
                </div>
            </div>

            @if(!$isExternal)
            <!-- Títulos Iniciais de TCC e IC — apenas para orientandos regulares -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                <!-- TCC Status & Title -->
                <div class="space-y-4">
                    <div class="space-y-2">
                        <label for="tcc_status" class="block text-xs font-semibold uppercase tracking-wider text-slate-300">Situação do TCC</label>
                        <select id="tcc_status" name="tcc_status" required onchange="handleTccStatusChange(this)"
                                class="w-full max-w-full px-4 py-3 rounded-xl bg-dark-950/80 border border-white/10 text-slate-300 focus:outline-none focus:border-brand-teal text-sm transition-all">
                            <option value="none" {{ old('tcc_status') === 'none' ? 'selected' : '' }}>Não se aplica (Não estou fazendo TCC)</option>
                            <option value="defined" {{ old('tcc_status') === 'defined' ? 'selected' : '' }}>Já tenho um tema definido e confirmado pelo professor</option>
                            <option value="needs_evaluation" {{ old('tcc_status', 'needs_evaluation') === 'needs_evaluation' ? 'selected' : '' }}>Preciso propor/confirmar um tema de TCC para avaliação</option>
                        </select>
                    </div>

                    <div id="tcc_title_container" class="space-y-2">
                        <label id="tcc_title_label" for="tcc_title" class="block text-xs font-semibold uppercase tracking-wider text-slate-300">Tema Proposto do TCC para Avaliação</label>
                        <input type="text" id="tcc_title" name="tcc_title" value="{{ old('tcc_title') }}" placeholder="Tema do seu TCC..."
                               class="w-full px-4 py-3 rounded-xl bg-dark-950/80 border border-white/10 text-white focus:outline-none focus:border-brand-teal text-sm transition-all">
                    </div>
                </div>

                <!-- IC Title -->
                <div class="space-y-2">
                    <label for="ic_title" class="block text-xs font-semibold uppercase tracking-wider text-slate-300">Título Inicial de IC (Opcional)</label>
                    <input type="text" id="ic_title" name="ic_title" value="{{ old('ic_title') }}" placeholder="Tema do seu projeto de IC..."
                           class="w-full px-4 py-3 rounded-xl bg-dark-950/80 border border-white/10 text-white focus:outline-none focus:border-brand-teal text-sm transition-all">
                </div>
            </div>
            @endif

            <!-- Consentimento LGPD -->
            <div class="p-4 sm:p-6 rounded-2xl border border-white/5 bg-white/5 space-y-4">
                <h4 class="font-outfit font-semibold text-white text-sm">Declaração de Consentimento (LGPD)</h4>
                <div class="flex items-start gap-3">
                    <input type="checkbox" id="terms_accepted" name="terms_accepted" required value="1"
                           class="mt-1 w-4 h-4 rounded text-brand-teal bg-dark-950 border border-white/10 focus:ring-brand-teal">
                    <label for="terms_accepted" class="text-xs sm:text-sm text-slate-300 leading-relaxed font-light">
                        Consinto que meus dados pessoais e acadêmicos (incluindo RGM, instituição e registros de acesso) serão tratados pela plataforma profatilio.com.br de forma confidencial, com a finalidade exclusiva de viabilizar a gestão, auditoria e fluxos de minha orientação acadêmica, em total conformidade com a <a href="https://www.planalto.gov.br/ccivil_03/_ato2015-2018/2018/lei/l13709.htm" target="_blank" class="text-brand-teal underline">LGPD (Lei nº 13.709/2018)</a>. Declaro ciência de que o Prof. Atilio terá acesso integral a estes dados para fins de validação institucional. Consulte nossa <a href="#" onclick="openLgpdModal(event)" class="text-brand-teal underline">Política de Privacidade</a>.
                    </label>
                </div>
            </div>

            <!-- Submit Button -->
            <button type="submit"
                    class="w-full py-4 bg-gradient-to-r from-brand-teal to-brand-indigo hover:brightness-110 active:scale-[0.99] text-white font-bold rounded-2xl shadow-lg shadow-brand-teal/20 transition-all">
                Concluir Pré-Cadastro
            </button>
        </form>
    </div>

    <!-- LGPD Privacy Policy Modal -->
    <div id="lgpd-modal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm hidden opacity-0 transition-opacity duration-300">
        <div class="bg-dark-900 border border-white/10 rounded-2xl max-w-2xl w-full max-h-[80vh] flex flex-col shadow-2xl overflow-hidden transform scale-95 transition-transform duration-300">
            <!-- Modal Header -->
            <div class="px-6 py-4 border-b border-white/5 flex items-center justify-between bg-dark-950">
                <h3 class="text-base font-bold text-white uppercase tracking-wider flex items-center gap-2">
                    🛡️ Declaração de Conformidade LGPD & Privacidade
                </h3>
                <button onclick="closeLgpdModal()" class="text-slate-400 hover:text-white transition-colors">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                </button>
            </div>
            <!-- Modal Body -->
            <div class="p-6 overflow-y-auto text-slate-350 text-sm space-y-4 leading-relaxed">
                <p>
                    A plataforma <strong>profatilio.com.br</strong>, sob responsabilidade do <strong>Prof. Dr. Atilio Sersun Calefi</strong>, adota esta Política para garantir o tratamento seguro e transparente de seus dados pessoais em total conformidade com a <strong>Lei Geral de Proteção de Dados Pessoais (LGPD - <a href="https://www.planalto.gov.br/ccivil_03/_ato2015-2018/2018/lei/l13709.htm" target="_blank" class="text-brand-teal underline">Lei nº 13.709/2018</a>)</strong>.
                </p>
                
                <div>
                    <h4 class="font-bold text-white text-xs uppercase tracking-wider text-brand-teal mb-2">1. Dados Pessoais Coletados</h4>
                    <p>Coletamos e armazenamos apenas os dados essenciais para o acompanhamento da orientação acadêmica e homologação de documentos:</p>
                    <ul class="list-disc pl-5 mt-2 space-y-1 text-slate-400">
                        <li><strong>Identificação e Contato:</strong> Nome completo, e-mail de cadastro, número de celular, matrícula acadêmica (RGM), instituição de ensino e campus.</li>
                        <li><strong>Dados de Auditoria (Forenses):</strong> Endereço IP de acesso, data/hora dos eventos, metadados do navegador/dispositivo, e hash SHA-256 das submissões de arquivos (PDF e DOCX).</li>
                    </ul>
                </div>

                <div>
                    <h4 class="font-bold text-white text-xs uppercase tracking-wider text-brand-teal mb-2">2. Finalidade do Tratamento</h4>
                    <p>Os seus dados são tratados estritamente para as seguintes finalidades:</p>
                    <ul class="list-disc pl-5 mt-2 space-y-1 text-slate-400">
                        <li>Viabilizar a correção, auditoria e revisão de TCC, Iniciação Científica, Relatórios, Artigos Científicos, Relatos de Caso e Protocolo CEUA/CEP;</li>
                        <li>Permitir a criação de assinaturas eletrônicas avançadas integradas com o sistema Gov.br (ITI);</li>
                        <li>Enviar notificações acadêmicas e alertas automáticos via e-mail e Telegram;</li>
                        <li>Garantir a integridade jurídica e auditoria das assinaturas de termos e relatórios de orientação.</li>
                    </ul>
                </div>

                <div>
                    <h4 class="font-bold text-white text-xs uppercase tracking-wider text-brand-teal mb-2">3. Direitos do Titular (Você)</h4>
                    <p>Conforme previsto no art. 18 da LGPD, você tem direito a:</p>
                    <ul class="list-disc pl-5 mt-2 space-y-1 text-slate-400">
                        <li>Confirmar a existência de tratamento e acessar seus dados cadastrados na plataforma;</li>
                        <li>Solicitar a correção de dados incompletos ou inexatos;</li>
                        <li>Revogar o seu consentimento a qualquer momento (o que resultará na inativação da conta);</li>
                        <li>Obter informações sobre compartilhamento de dados (compartilhado apenas com a Infraestrutura de Chaves Públicas e sistema Gov.br na ação de assinatura).</li>
                    </ul>
                </div>

                <div>
                    <h4 class="font-bold text-white text-xs uppercase tracking-wider text-brand-teal mb-2">4. Segurança e Retenção</h4>
                    <p>
                        Seus dados são protegidos por criptografia (SSL/TLS), firewalls de servidor e armazenamento seguro. A guarda de registros de acesso (IP e data/hora) obedece ao prazo legal obrigatório de 6 meses determinado pelo art. 15 do Marco Civil da Internet (Lei nº 12.965/2014).
                    </p>
                </div>

                <div>
                    <h4 class="font-bold text-white text-xs uppercase tracking-wider text-brand-teal mb-2">5. Contato e Encarregado</h4>
                    <p>
                        Para exercer qualquer um de seus direitos ou esclarecer dúvidas de privacidade, entre em contato diretamente com o Encarregado de Dados (DPO) através do e-mail: <a href="mailto:contato@profatilio.com.br" class="text-brand-teal underline">contato@profatilio.com.br</a>.
                    </p>
                </div>
            </div>
            <!-- Modal Footer -->
            <div class="px-6 py-4 border-t border-white/5 bg-dark-950 flex justify-end">
                <button onclick="closeLgpdModal()" class="px-5 py-2.5 bg-gradient-to-r from-brand-teal to-brand-indigo text-white rounded-xl text-xs font-bold transition-all shadow-md hover:brightness-110">
                    Entendido
                </button>
            </div>
        </div>
    </div>

    <!-- LGPD Scripts -->
    <script>
        function openLgpdModal(e) {
            if (e) e.preventDefault();
            const modal = document.getElementById('lgpd-modal');
            modal.classList.remove('hidden');
            setTimeout(() => {
                modal.classList.remove('opacity-0');
                modal.firstElementChild.classList.remove('scale-95');
            }, 50);
        }

        function closeLgpdModal() {
            const modal = document.getElementById('lgpd-modal');
            modal.classList.add('opacity-0');
            modal.firstElementChild.classList.add('scale-95');
            setTimeout(() => {
                modal.classList.add('hidden');
            }, 300);
        }

        // Controle de Exibição do TCC
        window.handleTccStatusChange = function(select) {
            const container = document.getElementById('tcc_title_container');
            const input = document.getElementById('tcc_title');
            const label = document.getElementById('tcc_title_label');
            if (!container || !input) return;
            
            if (select.value === 'none') {
                container.classList.add('hidden');
                input.required = false;
                input.value = '';
            } else {
                container.classList.remove('hidden');
                input.required = true;
                if (select.value === 'defined') {
                    if (label) label.textContent = 'Título do TCC Confirmado';
                    input.placeholder = 'Ex: Ocorrência de babesia em capivaras...';
                } else if (select.value === 'needs_evaluation') {
                    if (label) label.textContent = 'Tema Proposto do TCC para Avaliação';
                    input.placeholder = 'Ex: Possível tema sobre dermatologia canina...';
                }
            }
        };

        // Máscaras de entrada em tempo real para Telefone e CPF
        document.addEventListener('DOMContentLoaded', function() {
            const maskPhone = (value) => {
                if (!value) return "";
                const digits = value.replace(/\D/g, "");
                if (digits.length > 10) {
                    return `(${digits.slice(0, 2)}) ${digits.slice(2, 7)}-${digits.slice(7, 11)}`;
                } else if (digits.length > 6) {
                    return `(${digits.slice(0, 2)}) ${digits.slice(2, 6)}-${digits.slice(6, 10)}`;
                } else if (digits.length > 2) {
                    return `(${digits.slice(0, 2)}) ${digits.slice(2)}`;
                } else if (digits.length > 0) {
                    return `(${digits}`;
                }
                return "";
            };

            const maskCPF = (value) => {
                if (!value) return "";
                const digits = value.replace(/\D/g, "");
                if (digits.length > 9) {
                    return `${digits.slice(0, 3)}.${digits.slice(3, 6)}.${digits.slice(6, 9)}-${digits.slice(9, 11)}`;
                } else if (digits.length > 6) {
                    return `${digits.slice(0, 3)}.${digits.slice(3, 6)}.${digits.slice(6)}`;
                } else if (digits.length > 3) {
                    return `${digits.slice(0, 3)}.${digits.slice(3)}`;
                }
                return digits;
            };

            const phoneInput = document.getElementById('phone');
            if (phoneInput) {
                // Formata o valor inicial do banco/old se houver
                if (phoneInput.value) phoneInput.value = maskPhone(phoneInput.value);
                
                phoneInput.addEventListener('input', function(e) {
                    e.target.value = maskPhone(e.target.value);
                });
            }

            const cpfInput = document.getElementById('cpf');
            if (cpfInput) {
                // Formata o valor inicial do banco/old se houver
                if (cpfInput.value) cpfInput.value = maskCPF(cpfInput.value);
                
                cpfInput.addEventListener('input', function(e) {
                    e.target.value = maskCPF(e.target.value);
                });
            }

            // Inicializar TCC Status
            const tccStatusSelect = document.getElementById('tcc_status');
            if (tccStatusSelect) {
                handleTccStatusChange(tccStatusSelect);
            }
        });
    </script>
</body>
</html>
