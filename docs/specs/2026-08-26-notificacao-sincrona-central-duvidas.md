# Notificação síncrona (e-mail + Telegram) na Central de Dúvidas

- **Data:** 2026-08-26
- **Origem:** o professor relatou nunca ter recebido nenhuma notificação (nem e-mail, nem Telegram) quando um aluno manda mensagem na Central de Dúvidas — apesar de o código já enviar essas notificações automaticamente desde antes. A notificação por e-mail dependia de `->queue()`, ou seja, do cron `queue:work` rodando a cada minuto; o mesmo padrão de bug já causado antes pelo `Filament\Notifications\Auth\ResetPassword` (ver `docs/specs/` de sessões anteriores e [[feedback_sync_notifications]]).
- **Status de importação para prof-aid.com:** pendente

## Comportamento

1. `app/Models/SupportTicketMessage.php` (hook `created()`) já disparava e-mail + Telegram para quem não escreveu a mensagem (aluno → notifica o professor; professor → notifica o aluno). Isso não mudou.
2. O que mudou: o envio do e-mail deixou de ser `Mail::to(...)->queue(...)` e passou a ser `Mail::to(...)->send(...)` — síncrono, dentro da própria requisição que cria a mensagem, sem depender do cron da fila.
3. Envio de e-mail e envio de Telegram agora estão em blocos `try/catch` **separados** — se o SMTP falhar, isso não impede a tentativa de Telegram (e vice-versa). Antes, os dois estavam no mesmo bloco.
4. **Isso não resolve, por si só, a falta de notificação por Telegram** se a conta do professor nunca foi vinculada ao bot: o código só envia Telegram se `telegram_chat_id` estiver preenchido no usuário admin. O professor precisa clicar em "Vincular Telegram" no widget do Dashboard (`app/Filament/Widgets/TelegramAdminWidget.php` / `resources/views/filament/widgets/telegram-admin-widget.blade.php`) para isso funcionar.
5. Também vale confirmar que `contato@profatilio.com.br` (endereço fixo usado em todo o sistema para notificar o admin — não só aqui) é uma caixa de e-mail real que o professor de fato verifica; se não for, as notificações continuarão "funcionando" no código mas chegando a um lugar que ninguém olha.

## Arquivos alterados

- `app/Models/SupportTicketMessage.php` — troca `->queue()` por `->send()` no e-mail de notificação; separa os `try/catch` de e-mail e Telegram.

## Como importar para o prof-aid.com

1. Copiar o arquivo acima.
2. Nenhuma migration, config ou pacote novo.

## Ações pendentes do usuário (não são mudanças de código)

- [ ] Confirmar/realizar o vínculo do Telegram do professor no Dashboard admin (widget "Integração Telegram" → botão "Vincular Telegram").
- [ ] Confirmar que `contato@profatilio.com.br` é uma caixa que o professor realmente acessa.
