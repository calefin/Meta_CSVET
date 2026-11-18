# Bot do Telegram — perfil no BotFather (D-75)

Arquivos desta pasta:
- `foto-do-bot.png` (640×640) → BotFather `/setuserpic`. O Telegram corta em círculo; o logo já tem margem.
- `imagem-da-descricao.png` (640×360) → BotFather `/setdescription`… depois **Edit Description Picture** (aparece no chat vazio, antes do "Iniciar").

Os textos em português são o padrão (BotFather). Os em inglês o próprio site registra pela API para quem usa o Telegram em inglês.

## /setabouttext (até 120 caracteres — aparece no perfil do bot)

```
Bot oficial do BagParser: boletim diário do Tibia, avisos dos personagens, ranking da guild e suporte. bagparser.com
```

## /setdescription (até 512 caracteres — aparece no chat vazio, antes do "Iniciar")

```
Oi! Eu sou o bot do BagParser 🎒

📅 Boletim diário do Tibia: boosted, Rashid, Dream Scar, Yasir, bosses raros mortos e notícias, no privado ou no grupo da sua guild
📈 Evolução dos seus personagens e avisos na hora: level, morte, meta, cooldown de boss
🏆 Ranking de XP da guild no grupo
💬 Suporte: mande sua dúvida aqui
💚 /apoiar com Stars ou Pix

Ligue sua conta em bagparser.com → Minha conta → Telegram.
```

## /setcommands

O site registra os comandos sozinho quando a API inicia (português por padrão, inglês para quem usa o Telegram em inglês). Só use o BotFather se quiser mudar algo à mão:

```
boletim - O Tibia de hoje (on/off para o diário)
hunts - Suas últimas hunts
mundo - Mundo do boletim
hora - Horário do boletim
config - Ver suas escolhas
yasir - Ver ou relatar o Yasir
avisos - Avisos na hora (Pro)
guild - Ranking de XP da guild (Pro)
apoiar - Apoiar o BagParser 💚
suporte - Falar com o suporte
parar - Parar de receber mensagens
```

## Pagamentos
As doações com Telegram Stars não precisam de configuração no BotFather (moeda XTR, sem provedor). O comando /paysupport existe (exigência do Telegram para bots que recebem pagamentos).

## Outras opções do BotFather
- `/setjoingroups` → **Enable** (para entrar nos grupos das guilds).
- `/setprivacy` → deixe **Enable** (no grupo o bot só lê comandos, nunca as conversas).
