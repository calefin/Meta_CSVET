<div
    id="pwa-install-banner"
    class="hidden mx-4 mt-4 rounded-xl border border-ifa-green/20 bg-white p-4 shadow-md transition-all duration-300 md:mx-8"
    x-data
    x-init="window.IjoifaPwaInstall.init($el)"
>
    <div class="flex items-start gap-4">
        <!-- App Icon / Install Badge -->
        <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-ifa-green/10 text-ifa-green shadow-inner">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-6 h-6" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 17.25v1.007a3 3 0 0 1-.879 2.122L7.5 21h9l-.621-.621A3 3 0 0 1 15 18.257V17.25m6-12V15a2.25 2.25 0 0 1-2.25 2.25H5.25A2.25 2.25 0 0 1 3 15V5.25m18 0A2.25 2.25 0 0 0 18.75 3H5.25A2.25 2.25 0 0 0 3 5.25m18 0V12a2.25 2.25 0 0 1-2.25 2.25H5.25A2.25 2.25 0 0 1 3 12V5.25" />
            </svg>
        </div>

        <!-- Text Content -->
        <div class="flex-1 min-w-0">
            <div class="flex items-start justify-between gap-2">
                <h4 class="font-display text-base font-bold text-ebony">Aplicativo da Casa Ìjọ Ifá</h4>
                <button type="button" data-action="dismiss" class="text-ebony/40 hover:text-ebony/70 transition-colors p-1" aria-label="Fechar">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-5 h-5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <!-- Content for Android & Desktop -->
            <div data-platform-content="android" class="hidden mt-1">
                <p class="text-sm text-ebony/75">Instale o aplicativo no seu celular ou computador para acessar suas consultas com apenas 1 toque na tela inicial!</p>
                <div class="mt-3 flex flex-wrap gap-2">
                    <button
                        type="button"
                        data-action="install"
                        class="rounded-lg bg-ifa-green px-4 py-2 text-xs font-bold text-white shadow-sm hover:bg-ifa-green/90 transition-all duration-150 transform active:scale-95"
                    >
                        Instalar Aplicativo (Grátis)
                    </button>
                    <button
                        type="button"
                        data-action="dismiss"
                        class="rounded-lg border border-orisa-earth/20 px-3 py-2 text-xs font-semibold text-ebony/70 hover:bg-sacred-sand transition-all duration-150"
                    >
                        Agora não
                    </button>
                </div>
            </div>

            <!-- Content for iOS/Apple devices -->
            <div data-platform-content="ios" class="hidden mt-1 text-sm text-ebony/75 leading-relaxed">
                <p>Para adicionar o aplicativo ao seu iPhone ou iPad:</p>
                <ol class="mt-2 space-y-2 list-decimal list-inside text-xs font-medium text-ebony/70">
                    <li>
                        Toque no botão de <strong>Compartilhar</strong> 
                        <span class="inline-flex items-center justify-center p-1 rounded bg-sacred-sand text-orun-blue" aria-hidden="true">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 8.25H7.5a2.25 2.25 0 0 0-2.25 2.25v9a2.25 2.25 0 0 0 2.25 2.25h9a2.25 2.25 0 0 0 2.25-2.25v-9a2.25 2.25 0 0 0-2.25-2.25H15M9 12l3 3m0 0 3-3m-3 3V2.25" />
                            </svg>
                        </span> (geralmente fica na parte inferior do navegador).
                    </li>
                    <li>
                        Role a lista para baixo e toque em <strong>"Adicionar à Tela de Início"</strong> 
                        <span class="inline-flex items-center justify-center px-1.5 py-0.5 rounded bg-sacred-sand text-ebony font-bold" aria-hidden="true">+</span>.
                    </li>
                </ol>
            </div>
        </div>
    </div>
</div>
