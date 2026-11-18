<x-filament-widgets::widget>
    <div class="p-6 rounded-xl border border-sky-900/50 bg-sky-950/20 shadow-sm flex flex-col md:flex-row items-center justify-between gap-4">
        <div class="flex items-start gap-4">
            <div class="p-3 bg-sky-900/40 text-sky-400 rounded-lg flex-shrink-0">
                <!-- Telegram Icon SVG -->
                <svg class="w-6 h-6 fill-current" viewBox="0 0 24 24" style="width: 24px; height: 24px; display: block;">
                    <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm4.64 6.8c-.15.82-.7 3.99-1.01 5.67-.13.72-.37.96-.63.98-.56.05-1.02-.37-1.57-.73-.85-.56-1.34-.91-2.17-1.46-.96-.63-.34-.98.21-1.55.14-.15 2.66-2.44 2.71-2.65.01-.03.01-.13-.05-.18-.06-.05-.15-.03-.21-.02-.1.02-1.63 1.04-4.61 3.06-.44.3-.83.45-1.18.44-.38-.01-1.12-.22-1.66-.4-.67-.22-1.2-.34-1.15-.72.03-.2.3-.4 1.15-.79 5.25-2.29 8.76-3.8 10.51-4.53.5-.2.81-.3.94-.3.08 0 .26.02.38.12.1.08.13.19.14.27 0 .04-.01.12-.02.2z"/>
                </svg>
            </div>
            <div>
                <h3 class="text-base font-bold text-sky-400">💬 Receber Notificações no Telegram</h3>
                <p class="text-sm text-gray-300 mt-1">
                    Você ainda não ativou os alertas automáticos no Telegram! Vincule o seu chat agora para receber notificações gratuitas em tempo real sobre correções de seus relatórios, TCCs e vigências de prazos.
                </p>
            </div>
        </div>
        <div class="flex-shrink-0 w-full md:w-auto">
            <a href="{{ $this->getInviteLink() }}" target="_blank" class="inline-flex items-center justify-center gap-2 px-5 py-2.5 text-sm font-semibold text-white bg-sky-600 hover:bg-sky-500 rounded-lg transition-all shadow-md w-full md:w-auto" style="background-color: #0284c7; color: white; padding: 10px 20px; border-radius: 8px; text-decoration: none; display: inline-flex; align-items: center; box-shadow: 0 4px 6px rgba(2, 132, 199, 0.2);">
                <svg class="w-4 h-4 fill-current" viewBox="0 0 24 24" style="width: 16px; height: 16px; margin-right: 4px;"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm4.64 6.8c-.15.82-.7 3.99-1.01 5.67-.13.72-.37.96-.63.98-.56.05-1.02-.37-1.57-.73-.85-.56-1.34-.91-2.17-1.46-.96-.63-.34-.98.21-1.55.14-.15 2.66-2.44 2.71-2.65.01-.03.01-.13-.05-.18-.06-.05-.15-.03-.21-.02-.1.02-1.63 1.04-4.61 3.06-.44.3-.83.45-1.18.44-.38-.01-1.12-.22-1.66-.4-.67-.22-1.2-.34-1.15-.72.03-.2.3-.4 1.15-.79 5.25-2.29 8.76-3.8 10.51-4.53.5-.2.81-.3.94-.3.08 0 .26.02.38.12.1.08.13.19.14.27 0 .04-.01.12-.02.2z"/></svg>
                Vincular Canal Telegram
            </a>
        </div>
    </div>
</x-filament-widgets::widget>
