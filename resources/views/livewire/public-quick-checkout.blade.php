<div class="mx-auto max-w-2xl px-4 py-8">
    <div class="overflow-hidden rounded-2xl border border-orisa-earth/20 bg-white shadow-xl">
        <!-- Header / Summary Block -->
        <div class="bg-sacred-sand p-6 border-b border-orisa-earth/15">
            <h1 class="font-display text-xl text-ifa-green font-bold">
                Resumo da Solicitação
            </h1>
            
            <div class="mt-4 space-y-2">
                <div class="flex justify-between items-center text-sm">
                    <span class="text-ebony/60">Serviço:</span>
                    <span class="font-bold text-ifa-green uppercase text-xs tracking-wider bg-ifa-green/10 px-2.5 py-1 rounded-full">
                        {{ str_replace('_', ' ', $quickPayment->type) }}
                    </span>
                </div>
                @if($quickPayment->notes)
                    <div class="flex justify-between text-sm">
                        <span class="text-ebony/60">Descrição:</span>
                        <span class="text-ebony font-medium text-right">{{ $quickPayment->notes }}</span>
                    </div>
                @endif
                <div class="border-t border-orisa-earth/10 my-3 pt-3 flex justify-between items-baseline">
                    <span class="text-sm font-semibold text-ebony">Preço Total:</span>
                    <span class="text-2xl font-display font-extrabold text-ifa-green">
                        @if ($selectedOptionIndex !== null)
                            R$ {{ number_format($quickPayment->options[$selectedOptionIndex]['total'], 2, ',', '.') }}
                        @elseif ($quickPayment->hasMultipleOptions())
                            <span class="text-base font-semibold">A definir — escolha uma opção abaixo</span>
                        @else
                            R$ {{ number_format($quickPayment->total, 2, ',', '.') }}
                        @endif
                    </span>
                </div>
                @if ($selectedOptionIndex !== null && !empty($quickPayment->options[$selectedOptionIndex]['comment']))
                    <p class="text-xs text-ebony/60 text-right">{{ $quickPayment->options[$selectedOptionIndex]['comment'] }}</p>
                @endif
                @if ($selectedOptionIndex !== null && ($quickPayment->options[$selectedOptionIndex]['linked_to_first'] ?? false))
                    <p class="text-xs text-ebony/50 italic text-right">Este valor já inclui a Opção 1 + os itens desta opção.</p>
                @endif
            </div>
        </div>

        <div class="p-6">
            @if (session()->has('success'))
                <div class="rounded-lg bg-ifa-green/10 border border-ifa-green/30 p-4 text-ifa-green text-sm mb-6 text-center">
                    {{ session('success') }}
                </div>
            @endif

            @if ($quickPayment->status === 'paid')
                <div class="rounded-lg bg-ifa-green/10 border border-ifa-green/30 p-4 text-ifa-green text-sm text-center">
                    Este pagamento já foi confirmado anteriormente. Nenhuma ação adicional é necessária.
                </div>
            @elseif ($quickPayment->status === 'cancelled')
                <div class="rounded-lg bg-danger/10 border border-danger/30 p-4 text-danger text-sm text-center">
                    Este link de pagamento foi cancelado. Entre em contato para receber um novo link.
                </div>
            @elseif ($quickPayment->hasMultipleOptions() && $selectedOptionIndex === null)
                <!-- Option Picker -->
                <div class="space-y-4">
                    <p class="text-sm text-ebony/70 text-center">Escolha uma das opções abaixo para continuar:</p>
                    @foreach ($quickPayment->filledOptions() as $i => $option)
                        @php
                            // Option 1 rarely has its own comment (it's the base
                            // package) — fall back to the shared "notes" text so it
                            // isn't left blank next to options 2/3, which usually
                            // do have a comment explaining what's different about them.
                            $optionDescription = $option['comment'] ?: ($i === 0 ? $quickPayment->notes : null);
                        @endphp
                        <div class="rounded-lg border border-orisa-earth/20 p-4 hover:border-ifa-green/40 transition-colors">
                            <div class="flex justify-between items-baseline">
                                <span class="font-display text-lg text-ifa-green font-bold">Opção {{ $i + 1 }}</span>
                                <span class="text-xl font-display font-extrabold text-ifa-green">R$ {{ number_format($option['total'], 2, ',', '.') }}</span>
                            </div>
                            @if (!empty($optionDescription))
                                <p class="mt-1 text-sm text-ebony/70">{{ $optionDescription }}</p>
                            @endif
                            @if ($option['linked_to_first'] ?? false)
                                <p class="mt-1 text-xs text-ebony/50 italic">Este valor já inclui a Opção 1 + os itens desta opção.</p>
                            @endif
                            <button type="button" wire:click="selectOption({{ $i }})" class="mt-3 w-full rounded-lg bg-ifa-green py-2 text-center text-sm font-semibold text-white hover:bg-ifa-green/90 transition-colors">
                                Escolher esta opção
                            </button>
                        </div>
                    @endforeach
                </div>
            @elseif(Auth::check())
                <!-- Logged In Checkout -->
                <div class="text-center py-6 space-y-4">
                    @if ($quickPayment->hasMultipleOptions())
                        <button type="button" wire:click="changeOption" class="text-xs text-orun-blue hover:underline">&larr; Trocar opção escolhida</button>
                    @endif
                    <p class="text-sm text-ebony/75">
                        Você está autenticado como <strong class="text-ebony">{{ Auth::user()->name }}</strong>.
                    </p>

                    @error('checkout')
                        <div class="rounded-lg bg-danger/10 border border-danger/30 p-4 text-danger text-sm">{{ $message }}</div>
                    @enderror

                    @if (!empty($this->requiredMaterials))
                        <div class="rounded-lg border border-warning/40 bg-warning/10 p-4 text-left text-sm">
                            <p class="font-semibold text-ebony">Você deve providenciar os seguintes itens:</p>
                            <ul class="mt-1 list-disc list-inside text-ebony/80">
                                @foreach ($this->requiredMaterials as $materialName)
                                    <li>{{ $materialName }}</li>
                                @endforeach
                            </ul>
                            <label class="mt-3 inline-flex items-start gap-2">
                                <input type="checkbox" wire:model="materialsConfirmed" class="mt-1 rounded text-ifa-green focus:ring-ifa-green">
                                <span class="text-xs text-ebony/70">Confirmo que vou fornecer os itens listados acima.</span>
                            </label>
                            @error('materialsConfirmed') <p class="mt-1 text-xs text-danger">{{ $message }}</p> @enderror
                        </div>
                    @endif

                    <button type="button" wire:click="processPayment" class="w-full max-w-md mx-auto block rounded-lg bg-ifa-green py-3 text-center text-sm font-semibold text-white hover:bg-ifa-green/90 transition-colors shadow-md">
                        Ir para o Pagamento Seguro (Mercado Pago) &rarr;
                    </button>
                </div>
            @else
                @if ($quickPayment->hasMultipleOptions())
                    <button type="button" wire:click="changeOption" class="mb-4 text-xs text-orun-blue hover:underline">&larr; Trocar opção escolhida</button>
                @endif

                @error('checkout')
                    <div class="mb-4 rounded-lg bg-danger/10 border border-danger/30 p-4 text-danger text-sm">{{ $message }}</div>
                @enderror

                @if (!empty($this->requiredMaterials))
                    <div class="mb-4 rounded-lg border border-warning/40 bg-warning/10 p-4 text-left text-sm">
                        <p class="font-semibold text-ebony">Você deve providenciar os seguintes itens:</p>
                        <ul class="mt-1 list-disc list-inside text-ebony/80">
                            @foreach ($this->requiredMaterials as $materialName)
                                <li>{{ $materialName }}</li>
                            @endforeach
                        </ul>
                        <label class="mt-3 inline-flex items-start gap-2">
                            <input type="checkbox" wire:model="materialsConfirmed" class="mt-1 rounded text-ifa-green focus:ring-ifa-green">
                            <span class="text-xs text-ebony/70">Confirmo que vou fornecer os itens listados acima.</span>
                        </label>
                        @error('materialsConfirmed') <p class="mt-1 text-xs text-danger">{{ $message }}</p> @enderror
                    </div>
                @endif

                @if ($quickPayment->user_id !== null)
                    <!-- Link pre-assigned to a known client: skip straight to login -->
                    <div class="mb-6 rounded border border-orisa-earth/20 bg-sacred-sand/40 p-3 text-sm text-ebony/80">
                        Este link é exclusivo para <strong lang="yo">{{ $quickPayment->user->name }}</strong>. Faça login abaixo para continuar.
                    </div>
                @else
                    <!-- Guest Registration or Login Tabs -->
                    <div class="mb-6 flex border-b border-orisa-earth/10">
                        <button type="button" wire:click="$set('activeTab', 'register')" class="flex-1 pb-3 text-center text-sm font-medium border-b-2 transition-colors {{ $activeTab === 'register' ? 'border-ifa-green text-ifa-green font-bold' : 'border-transparent text-ebony/40 hover:text-ebony/80' }}">
                            1. Não tenho cadastro
                        </button>
                        <button type="button" wire:click="$set('activeTab', 'login')" class="flex-1 pb-3 text-center text-sm font-medium border-b-2 transition-colors {{ $activeTab === 'login' ? 'border-ifa-green text-ifa-green font-bold' : 'border-transparent text-ebony/40 hover:text-ebony/80' }}">
                            2. Já tenho cadastro
                        </button>
                    </div>
                @endif

                @if($activeTab === 'register' && $quickPayment->user_id === null)
                    <!-- Registration Form -->
                    <form wire:submit="registerAndPay" class="space-y-4">
                        <div>
                            <label for="reg-name" class="block text-xs font-semibold text-ebony/70 uppercase">Nome Completo</label>
                            <input id="reg-name" type="text" wire:model="registerName" class="mt-1 w-full rounded border border-orisa-earth/30 px-3 py-2 text-sm focus:border-ifa-green focus:ring-ifa-green" required>
                            @error('registerName') <p class="mt-1 text-xs text-danger">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label for="reg-mother" class="block text-xs font-semibold text-ebony/70 uppercase">
                                Nome de solteira da mãe 
                                @if ($quickPayment->type === 'consulta')
                                    (para a consulta)
                                @elseif ($quickPayment->type === 'trabalhos_gerais')
                                    (para os trabalhos)
                                @elseif ($quickPayment->type === 'iniciacao')
                                    (para a iniciação)
                                @elseif ($quickPayment->type === 'aborisa')
                                    (para o aborisa)
                                @else
                                    (para o atendimento)
                                @endif
                            </label>
                            <input id="reg-mother" type="text" wire:model="registerMotherName" class="mt-1 w-full rounded border border-orisa-earth/30 px-3 py-2 text-sm focus:border-ifa-green focus:ring-ifa-green" required>
                            @error('registerMotherName') <p class="mt-1 text-xs text-danger">{{ $message }}</p> @enderror
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label for="reg-birth" class="block text-xs font-semibold text-ebony/70 uppercase">Sua Data de Nascimento (NÃO a da mãe)</label>
                                <input id="reg-birth" type="text" placeholder="DD/MM/AAAA" maxlength="10" wire:model="registerBirthdate" class="mt-1 w-full rounded border border-orisa-earth/30 px-3 py-2 text-sm focus:border-ifa-green focus:ring-ifa-green" required>
                                @error('registerBirthdate') <p class="mt-1 text-xs text-danger">{{ $message }}</p> @enderror
                            </div>

                            <div>
                                <label for="reg-country" class="block text-xs font-semibold text-ebony/70 uppercase">País</label>
                                <select id="reg-country" wire:model.live="registerCountry" class="mt-1 w-full rounded border border-orisa-earth/30 px-3 py-2 text-sm focus:border-ifa-green focus:ring-ifa-green">
                                    @foreach ($countries as $code => $label)
                                        <option value="{{ $code }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                                @error('registerCountry') <p class="mt-1 text-xs text-danger">{{ $message }}</p> @enderror
                            </div>
                        </div>

                        @if ($registerCountry === 'BR')
                            <div>
                                <label for="reg-cpf" class="block text-xs font-semibold text-ebony/70 uppercase">CPF</label>
                                <input id="reg-cpf" type="text" placeholder="000.000.000-00" wire:model="registerCpf" class="mt-1 w-full rounded border border-orisa-earth/30 px-3 py-2 text-sm focus:border-ifa-green focus:ring-ifa-green" required>
                                @error('registerCpf') <p class="mt-1 text-xs text-danger">{{ $message }}</p> @enderror
                            </div>
                        @endif

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label for="reg-email" class="block text-xs font-semibold text-ebony/70 uppercase">E-mail</label>
                                <input id="reg-email" type="email" wire:model="registerEmail" class="mt-1 w-full rounded border border-orisa-earth/30 px-3 py-2 text-sm focus:border-ifa-green focus:ring-ifa-green" required>
                                @error('registerEmail') <p class="mt-1 text-xs text-danger">{{ $message }}</p> @enderror
                            </div>

                            <div>
                                <label for="reg-phone" class="block text-xs font-semibold text-ebony/70 uppercase">Telefone / WhatsApp</label>
                                <input id="reg-phone" type="text" placeholder="(11) 99999-9999" wire:model="registerPhone" class="mt-1 w-full rounded border border-orisa-earth/30 px-3 py-2 text-sm focus:border-ifa-green focus:ring-ifa-green" required>
                                @error('registerPhone') <p class="mt-1 text-xs text-danger">{{ $message }}</p> @enderror
                            </div>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label for="reg-pass" class="block text-xs font-semibold text-ebony/70 uppercase">Definir Senha</label>
                                <input id="reg-pass" type="password" wire:model="registerPassword" class="mt-1 w-full rounded border border-orisa-earth/30 px-3 py-2 text-sm focus:border-ifa-green focus:ring-ifa-green" required>
                                @error('registerPassword') <p class="mt-1 text-xs text-danger">{{ $message }}</p> @enderror
                            </div>

                            <div>
                                <label for="reg-pass-conf" class="block text-xs font-semibold text-ebony/70 uppercase">Confirmar Senha</label>
                                <input id="reg-pass-conf" type="password" wire:model="registerPasswordConfirmation" class="mt-1 w-full rounded border border-orisa-earth/30 px-3 py-2 text-sm focus:border-ifa-green focus:ring-ifa-green" required>
                            </div>
                        </div>

                        <div class="mt-4">
                            <label class="inline-flex items-start gap-2">
                                <input type="checkbox" wire:model="registerConsent" class="mt-1 rounded text-ifa-green focus:ring-ifa-green" required>
                                <span class="text-xs text-ebony/70">Li e aceito os Termos de Consentimento e Política de Privacidade do site.</span>
                            </label>
                            @error('registerConsent') <p class="mt-1 text-xs text-danger">{{ $message }}</p> @enderror
                        </div>

                        <button type="submit" class="mt-6 w-full rounded-lg bg-ifa-green py-3 text-center text-sm font-semibold text-white hover:bg-ifa-green/90 transition-colors shadow-md">
                            Criar Cadastro e Ir para o Pagamento &rarr;
                        </button>
                    </form>
                @else
                    <!-- Login Form -->
                    <form wire:submit="loginAndPay" class="space-y-4">
                        <div>
                            <label for="log-email" class="block text-xs font-semibold text-ebony/70 uppercase">E-mail</label>
                            <input id="log-email" type="email" wire:model="loginEmail" @readonly($quickPayment->user_id !== null) class="mt-1 w-full rounded border border-orisa-earth/30 px-3 py-2 text-sm focus:border-ifa-green focus:ring-ifa-green {{ $quickPayment->user_id !== null ? 'bg-sacred-sand/40' : '' }}" required>
                            @error('loginEmail') <p class="mt-1 text-xs text-danger">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label for="log-pass" class="block text-xs font-semibold text-ebony/70 uppercase">Senha</label>
                            <input id="log-pass" type="password" wire:model="loginPassword" class="mt-1 w-full rounded border border-orisa-earth/30 px-3 py-2 text-sm focus:border-ifa-green focus:ring-ifa-green" required>
                            @error('loginPassword') <p class="mt-1 text-xs text-danger">{{ $message }}</p> @enderror
                        </div>

                        <button type="submit" class="mt-6 w-full rounded-lg bg-ifa-green py-3 text-center text-sm font-semibold text-white hover:bg-ifa-green/90 transition-colors shadow-md">
                            Entrar e Ir para o Pagamento &rarr;
                        </button>
                    </form>
                @endif
            @endif
        </div>
    </div>
</div>
