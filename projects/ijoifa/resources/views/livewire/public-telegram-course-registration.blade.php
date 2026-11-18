<div class="mx-auto max-w-4xl px-4 py-8 xs:px-4 md:px-8">
    <div class="mb-8 text-center">
        <span class="inline-block rounded-full bg-ifa-green/10 px-3.5 py-1 text-xs font-semibold uppercase tracking-wider text-ifa-green">
            Formação Contínua no Telegram
        </span>
        <h1 class="mt-3 font-display text-3xl font-bold text-ebony md:text-4xl">
            Inscrição no Curso de Estudo Tradicional
        </h1>
        <p class="mt-2 text-base text-ebony/70">
            Acompanhamento contínuo no Telegram com aulas semanais e encontros práticos presenciais.
        </p>
        <p class="mt-3 inline-block rounded-full bg-ifa-green/10 px-4 py-1.5 text-sm font-semibold text-ifa-green">
            Início da primeira turma: 15/08
        </p>

        <div class="mt-4">
            <button type="button" wire:click="toggleRecoverForm" class="text-xs font-medium text-ifa-green underline hover:text-ifa-green/80">
                Já se inscreveu e perdeu o link de pagamento? Clique aqui para recebê-lo de novo por e-mail
            </button>

            @if ($showRecoverForm)
                <form wire:submit.prevent="resendPaymentLink" class="mx-auto mt-3 flex max-w-md flex-col gap-2 sm:flex-row">
                    <input
                        type="email"
                        wire:model.defer="recoverEmail"
                        placeholder="Seu e-mail de inscrição"
                        class="w-full rounded-lg border border-orisa-earth/30 px-3.5 py-2 text-sm focus:border-ifa-green focus:outline-none focus:ring-1 focus:ring-ifa-green"
                    />
                    <button
                        type="submit"
                        wire:loading.attr="disabled"
                        class="flex-shrink-0 rounded-lg bg-ifa-green px-4 py-2 text-sm font-semibold text-white hover:bg-ifa-green/90 disabled:opacity-50"
                    >
                        Reenviar por E-mail
                    </button>
                </form>
                @error('recoverEmail') <span class="mt-1 block text-xs text-rose-600">{{ $message }}</span> @enderror
            @endif
        </div>
    </div>

    @if (session()->has('info'))
        <div class="mb-6 rounded-lg border border-emerald-300 bg-emerald-50 p-4 text-emerald-800">
            <p class="font-medium">{{ session('info') }}</p>
            @if ($checkoutUrl)
                <div class="mt-3">
                    <a href="{{ $checkoutUrl }}" target="_blank" class="inline-flex items-center gap-2 rounded-lg bg-ifa-green px-5 py-2.5 font-semibold text-white hover:bg-ifa-green/90">
                        <span>Ir para Pagamento (Mercado Pago)</span>
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3" />
                        </svg>
                    </a>
                </div>
            @endif
        </div>
    @endif

    <div class="grid gap-8 md:grid-cols-12">
        <!-- Detalhes do Curso -->
        <div class="space-y-6 md:col-span-5">
            <div class="rounded-xl border border-orisa-earth/20 bg-white p-6 shadow-sm">
                <h2 class="font-display text-xl font-bold text-ifa-green">Estrutura do Curso</h2>
                <ul class="mt-4 space-y-3.5 text-sm text-ebony/80">
                    <li class="flex items-start gap-3">
                        <span class="mt-0.5 flex h-6 w-6 flex-shrink-0 items-center justify-center rounded-full bg-ifa-green/10 text-ifa-green font-bold">1</span>
                        <div>
                            <strong>2 Aulas por Semana:</strong> Transmitidas e organizadas no grupo exclusivo do Telegram.
                        </div>
                    </li>
                    <li class="flex items-start gap-3">
                        <span class="mt-0.5 flex h-6 w-6 flex-shrink-0 items-center justify-center rounded-full bg-ifa-green/10 text-ifa-green font-bold">2</span>
                        <div>
                            <strong>1 Encontro Presencial Mensal:</strong> Realização prática de ritos e fundamentos aprendidos.
                        </div>
                    </li>
                    <li class="flex items-start gap-3">
                        <span class="mt-0.5 flex h-6 w-6 flex-shrink-0 items-center justify-center rounded-full bg-ifa-green/10 text-ifa-green font-bold">3</span>
                        <div>
                            <strong>Investimento Mensal:</strong> R$ 180,00 / mês (pagamento à vista, sem parcelamento) via Mercado Pago (Pix, Cartão ou Boleto). Vencimento fixo todo dia 15.
                        </div>
                    </li>
                </ul>

                <div class="mt-6 border-t border-orisa-earth/10 pt-4 text-xs text-ebony/60">
                    <p class="flex items-center gap-1.5 font-medium text-ebony/80">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-ifa-green" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                        </svg>
                        Acesso Liberado Automaticamente
                    </p>
                    <p class="mt-1">Após a confirmação do pagamento mensal, o link do grupo do Telegram é disponibilizado no seu painel.</p>
                </div>
            </div>

            @if ($subscription && $subscription->isActive())
                <div class="rounded-xl border border-emerald-300 bg-emerald-50 p-6 text-emerald-900">
                    <h3 class="font-bold text-lg">Sua inscrição já está ATIVA!</h3>
                    <p class="mt-1 text-sm">Vencimento da mensalidade atual: {{ $subscription->expires_at?->format('d/m/Y') }}</p>
                    <div class="mt-4">
                        <a href="{{ route('dashboard') }}" class="inline-block rounded-lg bg-emerald-700 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-800">
                            Acessar Meu Painel
                        </a>
                    </div>
                </div>
            @endif
        </div>

        <!-- Formulário de Cadastro e Termos -->
        <div class="md:col-span-7">
            <form wire:submit.prevent="register" class="rounded-xl border border-orisa-earth/20 bg-white p-6 shadow-sm">
                <h2 class="font-display text-xl font-bold text-ebony">Dados do Aluno</h2>
                <p class="mt-1 text-xs text-ebony/60">Preencha seus dados para vincular à mensalidade e ao grupo do Telegram.</p>

                <div class="mt-4 space-y-4">
                    <div>
                        <label for="full_name" class="block text-sm font-medium text-ebony">Nome Completo *</label>
                        <input
                            type="text"
                            id="full_name"
                            wire:model.defer="full_name"
                            class="mt-1 block w-full rounded-lg border border-orisa-earth/30 px-3.5 py-2 text-sm focus:border-ifa-green focus:outline-none focus:ring-1 focus:ring-ifa-green"
                            placeholder="Seu nome completo"
                        />
                        @error('full_name') <span class="mt-1 block text-xs text-rose-600">{{ $message }}</span> @enderror
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label for="email" class="block text-sm font-medium text-ebony">E-mail *</label>
                            <input
                                type="email"
                                id="email"
                                wire:model.defer="email"
                                class="mt-1 block w-full rounded-lg border border-orisa-earth/30 px-3.5 py-2 text-sm focus:border-ifa-green focus:outline-none focus:ring-1 focus:ring-ifa-green"
                                placeholder="seu@email.com"
                            />
                            @error('email') <span class="mt-1 block text-xs text-rose-600">{{ $message }}</span> @enderror
                        </div>

                        <div>
                            <label for="phone" class="block text-sm font-medium text-ebony">WhatsApp / Celular</label>
                            <input
                                type="text"
                                id="phone"
                                wire:model.defer="phone"
                                class="mt-1 block w-full rounded-lg border border-orisa-earth/30 px-3.5 py-2 text-sm focus:border-ifa-green focus:outline-none focus:ring-1 focus:ring-ifa-green"
                                placeholder="(11) 99999-9999"
                            />
                            @error('phone') <span class="mt-1 block text-xs text-rose-600">{{ $message }}</span> @enderror
                        </div>
                    </div>

                    <div>
                        <label for="telegram_username" class="block text-sm font-medium text-ebony">Usuário do Telegram *</label>
                        <div class="relative mt-1">
                            <input
                                type="text"
                                id="telegram_username"
                                wire:model.defer="telegram_username"
                                class="block w-full rounded-lg border border-orisa-earth/30 px-3.5 py-2 text-sm focus:border-ifa-green focus:outline-none focus:ring-1 focus:ring-ifa-green"
                                placeholder="@seu_usuario_telegram ou telefone"
                            />
                        </div>
                        <p class="mt-1 text-xs text-ebony/60">Utilizaremos este identificador para validar seu acesso ao grupo do curso no Telegram.</p>
                        @error('telegram_username') <span class="mt-1 block text-xs text-rose-600">{{ $message }}</span> @enderror
                    </div>
                </div>

                <!-- Termos e Isenções de Responsabilidade -->
                <div class="mt-8 border-t border-orisa-earth/15 pt-6">
                    <h3 class="font-display text-lg font-bold text-ebony">Termos e Condições de Participação</h3>
                    <p class="mt-1 text-xs text-ebony/60">Leia atentamente e marque todas as declarações obrigatórias para prosseguir.</p>

                    <div class="mt-4 space-y-3.5 text-xs text-ebony/80">
                        <label class="flex items-start gap-3 rounded-lg border border-orisa-earth/15 bg-sacred-sand/30 p-3 hover:bg-sacred-sand/60">
                            <input type="checkbox" wire:model.defer="accept_no_tefa_odu" class="mt-0.5 rounded border-orisa-earth/40 text-ifa-green focus:ring-ifa-green" />
                            <span>
                                <strong>Escopo de Ensinamento:</strong> Declaro estar ciente de que o curso <u>não ensinará magias e ritos que necessitem de Tefa Odù</u>.
                            </span>
                        </label>
                        @error('accept_no_tefa_odu') <span class="block text-xs text-rose-600">{{ $message }}</span> @enderror

                        <label class="flex items-start gap-3 rounded-lg border border-orisa-earth/15 bg-sacred-sand/30 p-3 hover:bg-sacred-sand/60">
                            <input type="checkbox" wire:model.defer="accept_initiation_requirements" class="mt-0.5 rounded border-orisa-earth/40 text-ifa-green focus:ring-ifa-green" />
                            <span>
                                <strong>Requisito de Iniciação:</strong> Compreendo que a realização de algumas atividades práticas propostas no curso pode exigir iniciação prévia.
                            </span>
                        </label>
                        @error('accept_initiation_requirements') <span class="block text-xs text-rose-600">{{ $message }}</span> @enderror

                        <label class="flex items-start gap-3 rounded-lg border border-orisa-earth/15 bg-sacred-sand/30 p-3 hover:bg-sacred-sand/60">
                            <input type="checkbox" wire:model.defer="accept_aborisa_variable_effect" class="mt-0.5 rounded border-orisa-earth/40 text-ifa-green focus:ring-ifa-green" />
                            <span>
                                <strong>Público Aborisa:</strong> Reconheço que algumas práticas ensinadas podem ser feitas por Aborisa, contudo a efetividade dos resultados é variável entre indivíduos.
                            </span>
                        </label>
                        @error('accept_aborisa_variable_effect') <span class="block text-xs text-rose-600">{{ $message }}</span> @enderror

                        <label class="flex items-start gap-3 rounded-lg border border-orisa-earth/15 bg-sacred-sand/30 p-3 hover:bg-sacred-sand/60">
                            <input type="checkbox" wire:model.defer="accept_format_and_frequency" class="mt-0.5 rounded border-orisa-earth/40 text-ifa-green focus:ring-ifa-green" />
                            <span>
                                <strong>Formato e Frequência:</strong> Estou de acordo com a dinâmica de 2 aulas semanais via Telegram e 1 encontro presencial mensal para práticas.
                            </span>
                        </label>
                        @error('accept_format_and_frequency') <span class="block text-xs text-rose-600">{{ $message }}</span> @enderror

                        <label class="flex items-start gap-3 rounded-lg border border-orisa-earth/15 bg-sacred-sand/30 p-3 hover:bg-sacred-sand/60">
                            <input type="checkbox" wire:model.defer="accept_materials_responsibility" class="mt-0.5 rounded border-orisa-earth/40 text-ifa-green focus:ring-ifa-green" />
                            <span>
                                <strong>Insumos e Materiais:</strong> Os materiais necessários para a realização das atividades práticas nos encontros presenciais são de inteira responsabilidade do aluno.
                            </span>
                        </label>
                        @error('accept_materials_responsibility') <span class="block text-xs text-rose-600">{{ $message }}</span> @enderror

                        <label class="flex items-start gap-3 rounded-lg border border-orisa-earth/15 bg-sacred-sand/30 p-3 hover:bg-sacred-sand/60">
                            <input type="checkbox" wire:model.defer="accept_presential_sacrifices" class="mt-0.5 rounded border-orisa-earth/40 text-ifa-green focus:ring-ifa-green" />
                            <span>
                                <strong>Ritos de Ativação / Sacrifício:</strong> Entendo que eventuais ritos que necessitem de sacrifício para ativação serão realizados estritamente durante os encontros presenciais.
                            </span>
                        </label>
                        @error('accept_presential_sacrifices') <span class="block text-xs text-rose-600">{{ $message }}</span> @enderror
                    </div>
                </div>

                <div class="mt-6 flex flex-col gap-3">
                    <button
                        type="submit"
                        wire:loading.attr="disabled"
                        class="flex w-full items-center justify-center gap-2 rounded-xl bg-ifa-green px-6 py-3.5 font-bold text-white shadow-md transition hover:bg-ifa-green/90 disabled:opacity-50"
                    >
                        <span wire:loading.remove>Aceitar Termos e Ir para Pagamento (R$ 180,00)</span>
                        <span wire:loading class="flex items-center gap-2">
                            <svg class="h-5 w-5 animate-spin text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            Processando inscrição...
                        </span>
                    </button>
                    <p class="text-center text-xs text-ebony/60">
                        Pagamento seguro via Mercado Pago (Pix, Cartão de Crédito ou Boleto)
                    </p>
                </div>
            </form>
        </div>
    </div>
</div>
