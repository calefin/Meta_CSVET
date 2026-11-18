# Passos pendentes no servidor

## O que já está funcionando
- `TELEGRAM_BOT_TOKEN` configurado ✅
- `TELEGRAM_ADMIN_CHAT_ID=1203045744` configurado ✅
- Mensagem diária das 06h (`ose:daily-message`) funcionando ✅
- `TELEGRAM_WEBHOOK_SECRET` gerado e configurado ✅
- Webhook registrado em `https://ijoifa.com/api/webhooks/telegram` ✅
- Comandos do bot funcionando (`/hoje`, `/ciclo`, `/agenda`, `/consulta`) ✅
- Menu de comandos registrado no BotFather ✅

---

## 1. Puxar as alterações novas

```bash
git pull origin main
```

---

## 2. Gerar e configurar o secret do webhook

```bash
openssl rand -hex 32
```

Copie o valor gerado e adicione ao `.env`:

```env
TELEGRAM_WEBHOOK_SECRET=<valor_gerado>
```

---

## 3. Registrar o webhook no Telegram

Substitua `<TOKEN>` e `<SECRET>` pelos valores do `.env`:

```bash
curl -X POST "https://api.telegram.org/bot<TOKEN>/setWebhook" \
  -d "url=https://ijoifa.com/api/webhooks/telegram" \
  -d "secret_token=<SECRET>"
```

Resposta esperada: `{"ok":true,"result":true}`

---

## 4. Limpar cache de configuração

```bash
php artisan config:clear
```

---

## 5. Testar os comandos

Mande pelo seu Telegram pessoal para `@ijoifa_bot`:

- `/hoje` — deve retornar o dia Yorùbá e Ọ̀sẹ̀
- `/ciclo` — posição no ciclo de 17 dias
- `/agenda` — próximos eventos
- `/consulta` — link para solicitar consulta
- `/ose 2026-07-04` — relatório de data específica (só funciona no seu chat)

Mande uma mensagem livre (texto sem `/`) — deve chegar no seu Telegram como notificação encaminhada.

---

## 6. Registrar os comandos no BotFather (opcional, mas recomendado)

Abra o `@BotFather` no Telegram, mande `/mybots` → `@ijoifa_bot` → **Edit Bot** → **Edit Commands** e cole:

```
hoje - Semana Yorùbá e Ọ̀sẹ̀ de hoje
ciclo - Posição no ciclo de 17 dias
agenda - Próximos eventos da casa
consulta - Link para solicitar consulta oracular
ajuda - Lista de comandos
```

Isso faz o menu de comandos aparecer no teclado do bot para qualquer usuário.
