<div class="mx-auto max-w-xl px-4 py-10 xs:px-4 md:px-8">
    <div class="rounded-lg bg-white p-6 shadow-sm border border-orisa-earth/10">
        <h1 class="mb-2 font-display text-2xl text-ifa-green">Solicitar Consulta</h1>
        <p class="mb-6 text-sm text-ebony/70">Preencha o formulário abaixo com os seus dados para registrar sua solicitação de consulta. Ao enviar, você receberá por e-mail o link de pagamento — <strong>a consulta só é realizada após a confirmação do pagamento.</strong></p>

        @if ($submitted)
            <div class="rounded-lg border border-success bg-success/10 p-5 text-success space-y-3">
                <div class="flex items-center gap-2 font-semibold">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-6 h-6">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <span>Solicitação Enviada com Sucesso!</span>
                </div>
                <p class="text-sm">Seus dados e a solicitação foram registrados no sistema. Enviamos por e-mail o link de pagamento da consulta — <strong>a consulta só será realizada após a confirmação do pagamento.</strong> Assim que o pagamento for confirmado, o Babalawo fará o atendimento e enviará os áudios com a análise dos Odus via WhatsApp para o número cadastrado.</p>
                <p class="text-xs text-success/80">Não recebeu o e-mail com o link de pagamento? Verifique a caixa de spam ou entre em contato pelo WhatsApp (11) 99211-4308.</p>
                <p class="text-xs text-success/80">Caso queira acessar a plataforma no futuro para ver seu histórico, você poderá usar a tela de entrada e solicitar uma nova senha através da opção "Esqueci minha senha" informando o seu e-mail cadastrado.</p>
            </div>
            <div class="mt-6 flex justify-between gap-4">
                <a href="{{ route('home') }}" class="text-sm text-ifa-green hover:underline">&larr; Voltar ao início</a>
                <a href="{{ route('login') }}" class="rounded bg-ifa-green px-4 py-2 text-xs font-semibold text-white hover:bg-ifa-green/90">Acessar a plataforma</a>
            </div>
        @else
            <form
                x-data
                x-on:submit.prevent="
                    if (window.grecaptcha) {
                        grecaptcha.ready(() => {
                            grecaptcha.execute('{{ config('services.recaptcha.site_key') }}', { action: 'public_consultation' }).then((token) => {
                                $wire.set('recaptchaToken', token).then(() => $wire.submit());
                            });
                        });
                    } else {
                        $wire.submit();
                    }
                "
                class="space-y-4" autocomplete="off"
            >
                @error('recaptcha') <p class="rounded bg-danger/10 p-2 text-sm text-danger">{{ $message }}</p> @enderror

                <div>
                    <h3 class="font-display text-sm font-semibold uppercase tracking-wider text-orisa-earth border-b border-orisa-earth/10 pb-1 mb-3">Seus Dados Pessoais</h3>
                </div>

                <div>
                    <label for="name" class="block text-sm font-medium">Nome completo</label>
                    <input id="name" type="text" wire:model="name" lang="yo" autocorrect="off" autocapitalize="none" spellcheck="false"
                        class="mt-1 w-full rounded border border-orisa-earth/30 px-3 py-2 text-sm focus:border-ifa-green focus:ring-ifa-green">
                    @error('name') <p class="mt-1 text-sm text-danger">{{ $message }}</p> @enderror
                    <x-yoruba-keyboard target="name" />
                </div>

                <div>
                    <label for="mother_maiden_name" class="block text-sm font-medium">Nome de solteira da mãe (para a consulta)</label>
                    <input id="mother_maiden_name" type="text" wire:model="mother_maiden_name" lang="yo" autocorrect="off" autocapitalize="none" spellcheck="false"
                        class="mt-1 w-full rounded border border-orisa-earth/30 px-3 py-2 text-sm focus:border-ifa-green focus:ring-ifa-green">
                    @error('mother_maiden_name') <p class="mt-1 text-sm text-danger">{{ $message }}</p> @enderror
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label for="birthdate" class="block text-sm font-medium">Data de Nascimento da pessoa (sua própria data de nascimento, NÃO a de sua mãe)</label>
                        <input id="birthdate" type="text" placeholder="DD/MM/AAAA" maxlength="10" wire:model="birthdate" class="mt-1 w-full rounded border border-orisa-earth/30 px-3 py-2 text-sm focus:border-ifa-green focus:ring-ifa-green">
                        @error('birthdate') <p class="mt-1 text-sm text-danger">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="country" class="block text-sm font-medium">País</label>
                        <select id="country" wire:model.live="country" class="mt-1 w-full rounded border border-orisa-earth/30 px-3 py-2 text-sm focus:border-ifa-green focus:ring-ifa-green">
                            @foreach ($countries as $code => $label)
                                <option value="{{ $code }}">{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('country') <p class="mt-1 text-sm text-danger">{{ $message }}</p> @enderror
                    </div>
                </div>

                @if ($country === 'BR')
                    <div>
                        <label for="cpf" class="block text-sm font-medium">CPF (obrigatório para residentes no Brasil)</label>
                        <input id="cpf" type="text" wire:model="cpf" placeholder="000.000.000-00"
                            class="mt-1 w-full rounded border border-orisa-earth/30 px-3 py-2 text-sm focus:border-ifa-green focus:ring-ifa-green">
                        @error('cpf') <p class="mt-1 text-sm text-danger">{{ $message }}</p> @enderror
                    </div>
                @endif

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label for="email" class="block text-sm font-medium">E-mail</label>
                        <input id="email" type="email" wire:model="email" placeholder="seu-email@exemplo.com"
                            class="mt-1 w-full rounded border border-orisa-earth/30 px-3 py-2 text-sm focus:border-ifa-green focus:ring-ifa-green">
                        @error('email') <p class="mt-1 text-sm text-danger">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="phone" class="block text-sm font-medium">WhatsApp (com DDD)</label>
                        <input id="phone" type="text" wire:model="phone" placeholder="(11) 99999-9999"
                            class="mt-1 w-full rounded border border-orisa-earth/30 px-3 py-2 text-sm focus:border-ifa-green focus:ring-ifa-green">
                        @error('phone') <p class="mt-1 text-sm text-danger">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div class="pt-2">
                    <h3 class="font-display text-sm font-semibold uppercase tracking-wider text-orisa-earth border-b border-orisa-earth/10 pb-1 mb-3">Detalhes da Consulta</h3>
                </div>

                <div>
                    <label for="description" class="block text-sm font-medium">Qual o motivo da consulta ou principais dúvidas?</label>
                    <textarea id="description" wire:model="description" rows="4" placeholder="Descreva aqui o que motivou a sua busca pela consulta..."
                        class="mt-1 w-full rounded border border-orisa-earth/30 px-3 py-2 text-sm focus:border-ifa-green focus:ring-ifa-green"></textarea>
                    @error('description') <p class="mt-1 text-sm text-danger">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="attachment" class="block text-sm font-medium">Foto ou arquivo anexo (opcional)</label>
                    <input id="attachment" type="file" wire:model="attachment"
                        class="mt-1 block w-full text-xs text-ebony file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-xs file:font-semibold file:bg-sacred-sand file:text-ifa-green hover:file:bg-sacred-sand/80">
                    <div wire:loading wire:target="attachment" class="mt-1 text-xs text-ebony/50">Carregando arquivo...</div>
                    @error('attachment') <p class="mt-1 text-sm text-danger">{{ $message }}</p> @enderror
                </div>

                <div class="flex items-start gap-2 pt-2">
                    <input id="consent" type="checkbox" wire:model="consent" class="mt-1 rounded text-ifa-green focus:ring-ifa-green">
                    <label for="consent" class="text-xs text-ebony/80 leading-relaxed">
                        Declaro que aceito a coleta e tratamento dos meus dados para fins de realização da consulta oracular, de acordo com a <a href="{{ route('privacy') }}" target="_blank" class="text-ifa-green hover:underline">Política de Privacidade</a> e em conformidade com a LGPD.
                    </label>
                </div>
                @error('consent') <p class="text-sm text-danger">{{ $message }}</p> @enderror

                <button type="submit" wire:loading.attr="disabled" class="w-full rounded bg-ifa-green px-4 py-2.5 font-semibold text-white hover:bg-ifa-green/90 transition-colors shadow-sm focus:ring-2 focus:ring-ifa-green/55 focus:outline-none flex justify-center items-center gap-2">
                    <span wire:loading wire:target="submit" class="animate-spin rounded-full h-4 w-4 border-2 border-white border-t-transparent"></span>
                    Enviar Solicitação de Consulta
                </button>
            </form>
        @endif
    </div>
</div>
