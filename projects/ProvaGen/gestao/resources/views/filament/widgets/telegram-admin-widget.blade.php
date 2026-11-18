@php
    $bot = $this->getBotData();
    $stats = $this->getStats();
    $botUsername = $bot['username'] ?: ltrim(config('services.telegram.bot_username'), '@');
    $user = auth()->user();
    // telegram_link_token vive em owner_settings, não em users (users é do
    // ProvaGen, não tem essa coluna) — ver App\Models\OwnerSettings.
    $ownerSettings = $user ? \App\Models\OwnerSettings::firstOrCreate(['owner_user_id' => $user->id]) : null;
    if ($ownerSettings && empty($ownerSettings->telegram_link_token)) {
        $ownerSettings->telegram_link_token = \Illuminate\Support\Str::random(32);
        $ownerSettings->save();
    }
@endphp

<x-filament-widgets::widget>
    <div class="p-6 rounded-xl border border-teal-900/40 bg-teal-950/5 shadow-sm">
        <div class="grid grid-cols-1 lg:grid-cols-4 gap-6 items-center">
            
            <!-- Coluna 1: Status do Bot -->
            <div class="flex items-center gap-4 border-b lg:border-b-0 lg:border-r border-gray-700/40 pb-4 lg:pb-0 lg:pr-6">
                <div class="p-3 bg-teal-900/20 text-teal-400 rounded-lg flex-shrink-0">
                    <!-- Telegram Icon -->
                    <svg class="w-8 h-8 fill-current" viewBox="0 0 24 24" style="width:32px; height:32px;">
                        <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm4.64 6.8c-.15.82-.7 3.99-1.01 5.67-.13.72-.37.96-.63.98-.56.05-1.02-.37-1.57-.73-.85-.56-1.34-.91-2.17-1.46-.96-.63-.34-.98.21-1.55.14-.15 2.66-2.44 2.71-2.65.01-.03.01-.13-.05-.18-.06-.05-.15-.03-.21-.02-.1.02-1.63 1.04-4.61 3.06-.44.3-.83.45-1.18.44-.38-.01-1.12-.22-1.66-.4-.67-.22-1.2-.34-1.15-.72.03-.2.3-.4 1.15-.79 5.25-2.29 8.76-3.8 10.51-4.53.5-.2.81-.3.94-.3.08 0 .26.02.38.12.1.08.13.19.14.27 0 .04-.01.12-.02.2z"/>
                    </svg>
                </div>
                <div>
                    <h3 class="text-sm font-bold text-gray-400 uppercase tracking-wider">Integração Telegram</h3>
                    
                    @if($bot['status'] === 'online')
                        <div class="flex items-center gap-2 mt-1">
                            <span class="relative flex h-3 w-3">
                                <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                                <span class="relative inline-flex rounded-full h-3 w-3 bg-emerald-500"></span>
                            </span>
                            <span class="text-base font-bold text-emerald-400">Ativo e Operacional</span>
                        </div>
                        <p class="text-xs text-gray-400 mt-1">
                            Bot: <a href="https://t.me/{{ $botUsername }}" target="_blank" class="text-teal-400 font-semibold underline hover:text-teal-300">{{ '@' . $botUsername }}</a>
                        </p>
                    @elseif($bot['status'] === 'error')
                        <div class="flex items-center gap-2 mt-1">
                            <span class="relative flex h-3 w-3">
                                <span class="relative inline-flex rounded-full h-3 w-3 bg-amber-500"></span>
                            </span>
                            <span class="text-base font-bold text-amber-400">Erro de Conexão</span>
                        </div>
                        <p class="text-xs text-gray-400 mt-1">Sem resposta da API. Verifique a conexão do servidor.</p>
                    @else
                        <div class="flex items-center gap-2 mt-1">
                            <span class="relative flex h-3 w-3">
                                <span class="relative inline-flex rounded-full h-3 w-3 bg-rose-500"></span>
                            </span>
                            <span class="text-base font-bold text-rose-400">Desconfigurado</span>
                        </div>
                        <p class="text-xs text-gray-400 mt-1">Insira o token do bot no arquivo <code>.env</code>.</p>
                    @endif
                </div>
            </div>
 
            <!-- Coluna 2: Seu Vínculo (Admin/Professor) -->
            <div class="flex flex-col border-b lg:border-b-0 lg:border-r border-gray-700/40 pb-4 lg:pb-0 lg:pr-6">
                <h3 class="text-sm font-bold text-gray-400 uppercase tracking-wider">Seu Vínculo (Professor)</h3>
                
                @if(auth()->user()->telegram_chat_id)
                    <div class="flex items-center gap-2 mt-1">
                        <span class="relative flex h-3 w-3">
                            <span class="relative inline-flex rounded-full h-3 w-3 bg-emerald-500"></span>
                        </span>
                        <span class="text-base font-bold text-emerald-400">Vinculado</span>
                    </div>
                    <p class="text-xs text-gray-400 mt-1">
                        Você receberá alertas em tempo real no Telegram.
                    </p>
                    <button wire:click="unlinkAdminTelegram" class="text-left text-xs text-rose-400 font-semibold underline hover:text-rose-300 transition-colors mt-2" style="max-width: fit-content;">
                        Desvincular Telegram
                    </button>
                @else
                    <div class="flex items-center gap-2 mt-1">
                        <span class="relative flex h-3 w-3">
                            <span class="relative inline-flex rounded-full h-3 w-3 bg-amber-500 animate-pulse"></span>
                        </span>
                        <span class="text-base font-bold text-amber-400">Não Vinculado</span>
                    </div>
                    <p class="text-xs text-gray-400 mt-1">
                        Vincule para receber alertas de novas submissões.
                    </p>
                    @if(!empty($botUsername))
                        <a href="https://t.me/{{ $botUsername }}?start=admin_{{ $ownerSettings?->telegram_link_token }}" target="_blank" class="inline-flex items-center justify-center px-3 py-1.5 mt-2 text-xs font-bold text-white bg-teal-600 hover:bg-teal-500 rounded-lg transition-all" style="background-color:#0f766e; color:white; padding:6px 12px; border-radius:6px; text-decoration:none; max-width: fit-content;">
                            Vincular Telegram
                        </a>
                    @else
                        <p class="text-xs text-rose-400 mt-1">Bot não configurado.</p>
                    @endif
                @endif
            </div>

            <!-- Coluna 3: Estatísticas de Alunos Vinculados -->
            <div class="border-b lg:border-b-0 lg:border-r border-gray-700/40 pb-4 lg:pb-0 lg:pr-6">
                <div class="flex justify-between items-center mb-2">
                    <span class="text-sm font-medium text-gray-300">Alunos Vinculados ao Bot</span>
                    <span class="text-sm font-bold text-teal-400">{{ $stats['linked_students'] }} de {{ $stats['total_students'] }}</span>
                </div>
                <!-- Barra de progresso visual -->
                <div class="w-full bg-gray-700/40 rounded-full h-2">
                    @php
                        $percentage = $stats['total_students'] > 0 ? ($stats['linked_students'] / $stats['total_students']) * 100 : 0;
                    @endphp
                    <div class="bg-teal-500 h-2 rounded-full transition-all duration-500" style="width: {{ $percentage }}%"></div>
                </div>
                <p class="text-xs text-gray-400 mt-2">
                    Estudantes recebem alertas de novos envios e correções direto no chat.
                </p>
            </div>

            <!-- Coluna 4: Mensagens e Ações Rápidas -->
            <div class="flex flex-col sm:flex-row lg:flex-col xl:flex-row items-center gap-4 justify-between">
                <div>
                    <div class="flex items-center gap-2">
                        <span class="text-2xl font-black text-gray-200">{{ $stats['unread_messages'] }}</span>
                        @if($stats['unread_messages'] > 0)
                            <span class="flex h-2 w-2 rounded-full bg-rose-500 animate-pulse"></span>
                        @endif
                    </div>
                    <span class="text-xs text-gray-400 font-semibold uppercase tracking-wider">Mensagens Não Lidas</span>
                </div>
                
                <div class="flex flex-col sm:flex-row gap-2 w-full sm:w-auto xl:w-auto">
                    <a href="{{ url('/admin/telegram-messages') }}" class="inline-flex items-center justify-center gap-1.5 px-4 py-2 text-xs font-bold text-slate-200 bg-teal-700 hover:bg-teal-600 rounded-lg transition-all w-full sm:w-auto text-center" style="background-color:#0f766e; color:white; padding:8px 16px; border-radius:6px; text-decoration:none;">
                        Abrir Mural
                    </a>
                    @if(auth()->user()->telegram_chat_id)
                        <button wire:click="sendDailySummary" class="inline-flex items-center justify-center gap-1.5 px-4 py-2 text-xs font-bold text-white bg-amber-600 hover:bg-amber-500 rounded-lg transition-all w-full sm:w-auto cursor-pointer" style="background-color:#d97706; color:white; padding:8px 16px; border-radius:6px; border:none;">
                            Enviar Resumo
                        </button>
                    @endif
                </div>
            </div>

        </div>
    </div>
</x-filament-widgets::widget>
