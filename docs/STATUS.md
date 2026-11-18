# BagParser — Implementation Status

Update this file whenever a requirement changes state. Legend: ✅ done · 🚧 in progress · ⏳ pending · 🔜 later.

_Last update: 2026-11-06 (noite) — v0.4.0 no ar; D-53 (metas semanais, estatística por local), D-54 (segurança, site mais leve, es/pl), D-55 (blindagem da cobrança Stripe) prontos e testados localmente._

## ▶ Resume here (next steps, in order)
000000000000000000000. **D-84 (2026-11-08) — imagens de compartilhamento novas (enviado à main; falta publicar com `bash deploy.sh`).** Depois do deploy, testar um link no WhatsApp: ele guarda a prévia antiga de links já enviados, então use um link novo ou em outra conversa.
00000000000000000000. **D-82 e D-83 (2026-11-08) — nome da criatura sempre abre a ficha, resumo na Busca de Bestiário, todos os sprites, novidade publicada em /news e post mais recente na página principal (enviado à main; falta publicar com `bash deploy.sh`).** Verificado: frontend 90 testes, backend 142, scripts 18, build, Docker local, Chrome normal em 390/1280 (0 sprites vazios na lista, bosses, fichas e local). **Para testar sprites use o Chrome em modo normal:** o servidor de imagens da wiki recusa o modo headless (`ERR_BLOCKED_BY_RESPONSE`), e aí toda imagem da wiki parece quebrada sem estar.
0000000000000000000. **D-80 (2026-11-08) — ficha de criatura, hunts por vocação, nomes sem tradução, todos os bosses (publicada).** Verificado: frontend 90 testes, backend 142, scripts 13, Docker local (400 bosses novos no start, 445 no total; `/api/creature-info`; drop de teste no Boss Tracker), telas /creatures, ficha, /hunting, local e /bossledger em 390/1280 sem rolagem lateral nem erro de console. **Não dá para reproduzir aqui:** o tradutor automático do Chrome; depois do deploy, abrir em pt-BR e conferir "Mitmah Seer". **Depois do deploy:** na primeira subida a API acrescenta os bosses sozinha (log `bosses: N added from the wiki`). **D-81:** a tabela agora vem dos títulos do YouTube + guias (sem site de terceiros); atualizar com `node scripts/import-hunt-videos.mjs` e `node scripts/build-hunt-table.mjs`. **Pendências conhecidas:** XP/lucro mostram o que cada criador informa (mundo, set e bônus variam) e **var** quando ninguém informou; locais com lista de criaturas muito misturada na wiki têm recomendação menos precisa (Deeper Banuta); quantidade média por drop do Boss Tracker não existe (só contamos em quantas kills o item caiu).
000000000000000000. **Pendências de 2026-11-07 (noite), em ordem:** (1) **Publicar a v0.14.2** (`bash deploy.sh`; a VPS ficou na 0.14.1 depois de uma recriação manual da API). (2) **E-mail (D-79, 2026-11-08):** decidido manter a HostGator (`.env` da VPS já com mail.csvet.app:465 e noreply@csvet.app; Gmail descartado). O 250 sem entrega foi resolvido com `name: 'bagparser.com'` no `mailer.ts` (`c4de2bc`): **publicado e confirmado em produção, "Enviar e-mail de teste" em /admin chegou ao Gmail.** Lição: "Enviado" só significa que o SMTP aceitou; o teste só vale quando a mensagem chega. **Vetrank:** a mesma correção foi enviada para `calefin/vetrank_new` (`8f38f5b`); falta publicar na VPS, testar com "Esqueci minha senha" e avisar os alunos afetados (o dono parou de receber e-mails dele; verificação de conta e redefinição de senha podem ter falhado). Depois, com calma, remetente @bagparser.com. (3) **Token do bot do Telegram:** apareceu na conversa; confirmar que foi trocado no BotFather (/revoke) e que o .env tem o novo (o `.env.backup` da VPS ainda tem o antigo: apagar depois). (4) Criar o cupom TIBIA30 em /admin → Promoções e divulgar bagparser.com/cupom/TIBIA30 (mensagens de WhatsApp prontas na sessão). (5) Lição: nunca Ctrl+C durante `docker compose up` (deixou a API fora, 502).
00000000000000000. **D-78 (2026-11-07) — v0.14.2: link de cupom (/cupom/CODIGO aplica sozinho, testado 7/7 no navegador) e diagnóstico de e-mail no admin.** E-mail em produção: resolvido depois, ver D-79. **Divulgação:** criar em /admin → Promoções o código de teste (ex.: TIBIA30, 30 dias) e divulgar bagparser.com/cupom/TIBIA30.
0000000000000000. **D-77 (2026-11-07) — v0.14.1: SEO das páginas novas feito** (/news com dados estruturados dos posts e card próprio, /support no sitemap, /pricing com descrição e card novos, /account e /retro noindex). **v0.14.1 (2026-11-07):** página de planos (/pricing) e textos de Personagens corrigidos para o que o código realmente dá (Pro: Server Log completo, até 10 personagens com rastreio automático, avisos na hora, evolução no boletim, ranking da guild, até 5.000 hunts, sem anúncios; saíram promessas que não existiam: personagens ilimitados, prioridade de alertas de drops, acesso antecipado). **Plano Guilda:** hoje não tem nada além do Pro (a página agora diz só isso); decidir com o dono se tira o plano ou se cria os benefícios de membros. **Foto do bot:** no BotFather, /setuserpic → @bagparser_bot → mandar a imagem como FOTO (com compressão), não como arquivo; arquivos em `docs/telegram/`.
000000000000000. **D-76 (2026-11-07) — v0.14.0: avisos na hora (Pro), soul core do boosted, Yasir pelo bot, apoio com Stars/Pix, ranking de XP da guild (Pro), retrospectiva do mês.** Verificado: backend 138 testes, frontend 83, build, Docker local com Telegram falso (E2E D-76 29/29: Yasir pelo bot e aviso de confirmado, /avisos, /guild com guild real da TibiaData e bloqueio para grátis/não-admin, ranking no boletim, soul core do boosted, Stars com pre-checkout e pagamento contado uma vez, nome público no rodapé, aviso de cooldown de boss só para Pro, aviso de level após recarga de personagem real, /api/me/retro; E2E D-75 35/35 de novo), telas /retro e Minha conta em 390/1280 sem rolagem. **No ar (2026-11-07):** v0.14.0 publicada; bot ligado em produção (`/api/telegram/info` → enabled, log `telegram: webhook` ok: true) depois de acrescentar `TELEGRAM_BOT_TOKEN` ao .env da VPS e recriar a API. **BotFather:** textos novos em `docs/telegram/README.md`. **Não testado com dinheiro real:** Stars e Pix de doação (o Pix usa o mesmo fluxo já testado do Pro).
00000000000000. **D-75 (2026-11-07) — v0.13.0: bot do Telegram, boletim diário (Telegram e Discord), evolução do personagem, suporte pelo Telegram.** Verificado: backend 128 testes, frontend 83, build, Docker local com Telegram falso (E2E 35/35: vínculo de conta, código de uso único, /mundo /hora /config, boletim, suporte com resposta do admin no Telegram e no painel, ticket do site avisando no Telegram, grupo com bloqueio de não-admin, job diário sem repetir, chat bloqueado, desvincular), telas 390/1280 (corrigida a rolagem do cabeçalho do admin em 390px). **Para ligar em produção:** (1) no .env da VPS acrescentar `TELEGRAM_BOT_TOKEN` (sed + `cat >> .env`, conferir o tamanho com awk); (2) `bash deploy.sh`; (3) no log da API deve aparecer `telegram: webhook` com `ok: true`; (4) ligar a conta de admin ao bot em /account → Telegram (para receber os tickets); (5) BotFather: foto, about, descrição e comandos (textos prontos na sessão). **Próximos:** ideias (a)–(f) feitas no D-76. Login com Telegram: só "entrar" para contas que já ligaram o Telegram (sem cadastro novo por ele), se o dono pedir.
0000000000000. **D-74 (2026-11-07) — v0.12.0: troca de conta limpa, plano no cabeçalho, nova home, gráfico por minuto em linhas.** Verificado: frontend 83 testes, backend 112, build, Docker local, navegador 390/1280 (conta A Pro → sair → conta B Grátis na mesma aba: B não vê nada de A, selo certo, API pessoal com `no-store`; gráfico novo com balão). **Deploy:** `cd /opt/apps/bagparser && bash deploy.sh`. Se o teste de e-mail em /admin disser que a sessão não é de admin, sair e entrar com o e-mail de `ADMIN_EMAILS`. **Publicado e conferido (2026-11-07):** health 0.12.0 e `/api/billing/pix` com `enabled:true` (Access Token de produção com 74 caracteres e assinatura secreta com 64 no .env da VPS). **Dono vai testar:** simular notificação no painel de Webhooks do MP (deve aparecer em /admin → Pagamentos → Mercado Pago), botão Pix em /pricing, Pix real de R$ 9,90 (opcional, depois devolver), e o teste de e-mail logado como admin.
000000000000. **D-69b + D-73 (2026-11-07) — Pix por Orders, avisos do Mercado Pago registrados, v0.11.2.** Verificado: backend 112 testes, E2E com MP falso (Pix 18 checagens; avisos 13: reclamação e contestação ligadas à conta/Pix e no log, erro de leitura gravado, tópico desconhecido guardado, reenvio não duplica, assinatura errada recusada). **Para o Pix aparecer no site:** na VPS acrescentar `MP_ACCESS_TOKEN` (Access Token de **produção**, não a Public Key) e `MP_WEBHOOK_SECRET` (assinatura secreta da tela de Webhooks) com os comandos `read -s` abaixo e rodar `bash deploy.sh`; conferir `curl https://bagparser.com/api/billing/pix` → `"enabled":true`. O botão Pix só aparece com preço em R$ (site em português ou moeda BRL escolhida). Webhook no MP: `https://bagparser.com/api/billing/pix/webhook` (modo produção), tópicos à vontade (Order, Pagamentos, Reclamações, Contestações…).
00000000000. **D-68..D-72 (2026-11-07) — backup, Pix, reportar problema, alertas, resumo no Discord, comparar armas, v0.11.0.** Verificado: backend 108 testes, frontend 79, build, Docker local, backup feito e conferido, E2E Pix com Mercado Pago falso (18), E2E integrado (reportar, alerta, Discord, armas, backup; 11), telas em 390/1280px.
   **Deploy:** `cd /opt/apps/bagparser && bash deploy.sh` (sobe também o container `bagparser_backup`; o primeiro backup sai na hora). Conferir `ls -la backups/` e o card "Backup do banco" em /admin.
   **Pix (quando a aplicação do Mercado Pago estiver criada):** `read -s -p "Access Token: " T && printf 'MP_ACCESS_TOKEN=%s\n' "$T" >> .env && unset T; echo` e `read -s -p "Assinatura secreta: " S && printf 'MP_WEBHOOK_SECRET=%s\n' "$S" >> .env && unset S; echo`, depois `bash deploy.sh`. No MP (aplicação BagParser → Checkout Transparente → **API de Orders**): webhook de produção `https://bagparser.com/api/billing/pix/webhook`, evento **Order (Mercado Pago)**; use o **Access Token** (o longo), não a Public Key; conta com chave Pix cadastrada. Testar com uma compra real de R$ 9,90 e devolver pelo painel do MP.
   **Cópia do backup fora da VPS (no WSL do seu PC, de vez em quando):** `rsync -av meu-vps:/opt/apps/bagparser/backups/ ~/bagparser-backups/`.
   **Monitor de queda:** criar um monitor grátis no UptimeRobot para `https://bagparser.com/api/health` (avisa por e-mail se o site cair).
   **Próximos:** Telegram para o resumo semanal (criar bot no @BotFather e me passar só o nome do bot; o token vai no .env); ler o código do WoD.
0000000000. **D-67 (2026-11-07) — v0.10.1.** Yasir no Loot Parser pelos relatos do mundo (sem checkbox, cidade real no roteiro). Verificado: frontend 77 testes, navegador 390/1280px. **Fila do dono, em ordem:** (1) **backup diário do banco** (não existe hoje; pg_dump 14 dias + cópia fora da VPS); (2) botão "Reportar problema" em todas as páginas (para os testers); (3) alertas por e-mail para o admin (site fora, erro novo, pagamento falho, ticket); (4) **Pix via Mercado Pago** alinhado com o Stripe — Pix compra tempo de Pro pré-pago (30 dias / 1 ano, sem renovação automática); bloqueado enquanto houver assinatura Stripe ativa; quem tem tempo pré-pago e assina no Stripe começa a pagar só quando o tempo acaba (`trial_end` = fim do Pro atual); pagamentos MP entram no mesmo `billing_payments` e no Financeiro; precisa do Access Token e do segredo do webhook do MP só no `.env`; (5) resumo semanal por Discord (webhook do canal) ou Telegram (bot); (6) comparador de DPS entre armas (Server Log + set salvo). Descartado: troca de e-mail. Feito pelo dono: www → bagparser.com no NPM.
000000000. **D-64..D-66 (2026-11-07) — segurança, e-mail, anúncios, v0.10.0.** Verificado: backend 99 testes, frontend 76, build, Docker local (Node 22, Nginx 1.28, Fastify 5), E2E de cobrança (Stripe falso, as duas suítes), pagamentos de party, hunt session, admin/erros, e-mail (22 verificações com `MAIL_LOG=1`), telas em 390/1280px (barra de confirmação, esqueci a senha, anúncio só para grátis, Dream Scar).
   **Deploy (na VPS, nesta ordem):**
   1. Corrigir o endereço público (o painel avisou `PUBLIC_URL=http://localhost:3080`): `cd /opt/apps/bagparser && sed -i 's|^PUBLIC_URL=.*|PUBLIC_URL=https://bagparser.com|' .env && grep ^PUBLIC_URL .env`
   2. Acrescentar o e-mail ao `.env` (só acrescenta, não apaga nada; a senha é digitada por você, sem aparecer na tela): `printf 'SMTP_HOST=mail.csvet.app\nSMTP_PORT=465\nSMTP_USER=noreply@csvet.app\nSUPPORT_EMAIL=support@csvet.app\n' >> .env` e depois `read -s -p "Senha do noreply: " P && printf 'SMTP_PASS=%s\n' "$P" >> .env && unset P` (`MAIL_FROM` é opcional: sem ele sai "BagParser" <noreply@csvet.app>). Conferir sem mostrar a senha: `grep -E "^(SMTP_HOST|SMTP_PORT|SMTP_USER|SUPPORT_EMAIL)=" .env`.
   3. `bash deploy.sh` (baixa as imagens base novas; demora um pouco mais desta vez).
   4. Conferir: `curl https://bagparser.com/api/health` → versão 0.10.0; em /admin o aviso de PUBLIC_URL some; card E-mail "ligado" → "Enviar e-mail de teste" e ver se chegou na **caixa de entrada** (Gmail e Outlook); testar "Esqueci minha senha" com a sua conta; em /admin/finance "Sincronizar com o Stripe" uma vez.
   5. Anúncios em duas etapas: (a) o ID do editor (ca-pub-8311618945249138) já vem no código: meta tag `google-adsense-account` em todas as páginas e `/ads.txt`; depois do deploy conferir https://bagparser.com/ads.txt e, no AdSense (Sites → bagparser.com), escolher verificar por **meta tag** ou **ads.txt** (não precisa colar o snippet de script); (b) depois da aprovação: ligar a mensagem de consentimento do Google (Privacidade e mensagens → regulamentações europeias), bloquear categorias sensíveis (Controles de bloqueio), criar um bloco de anúncio de display responsivo e colocar o número em `ADSENSE_SLOT=…`, `bash deploy.sh`. Nginx: nada a mudar (o /ads.txt e a CSP já vêm no container web).
   6. Se possível, no DNS de csvet.app subir o DMARC de `p=none` para `p=quarantine` depois de 1–2 semanas sem problemas (melhora a reputação dos e-mails).
   **Próximos passos de produto:** testers da guilda (1 ano de Pro pela aba Pagamentos → "Dar Pro por período", 365 dias); juntar as críticas deles em tickets; publicar uma notícia em /news sobre a v0.10; redirecionar www → bagparser.com no Nginx Proxy Manager; ler o código do WoD (pendente, formato binário).
00000000. **D-63 (2026-11-07) — Assinantes, financeiro, testers, SEO, v0.9.0 (D-59..D-63).** Reativar assinatura e assinar de novo, log de eventos do Stripe, aba Financeiro, Pro por período para testers na aba Pagamentos, retorno do Stripe nunca para localhost, cards de compartilhamento e SEO atualizados. Verificado: backend 92 testes, frontend 74, build (468 páginas), Docker local, E2E de cobrança com Stripe falso (26 + suíte antiga), telas do admin e da conta em 390/1280px. **Depois do deploy:** (1) em /admin conferir se aparece aviso de `PUBLIC_URL` (se aparecer: no `.env` da VPS trocar só essa linha para `PUBLIC_URL=https://bagparser.com` e rodar `bash deploy.sh` de novo); (2) em /admin/finance clicar "Sincronizar com o Stripe" uma vez para importar pagamentos antigos; (3) testar compartilhar um link no WhatsApp/Discord (cache das prévias pode levar um tempo); (4) opcional: no Nginx Proxy Manager redirecionar `www.bagparser.com` → `bagparser.com` (hoje os dois respondem 200, conteúdo duplicado).
0000000. **D-62 (2026-11-07) — Painel do admin em abas + log de erros, v0.8.0 (D-59..D-62 não publicados).** `/admin` com Visão geral, Usuários, Pagamentos, Tickets, Erros, Moderação, Promoções, Log; botão 🛡️ Admin com contador na barra. Erros do navegador e 5xx da API agrupados (sem IP). Hunt Analyser: comparar dois personagens, CSV, aviso de hunt curta, marcadores no minimapa, dano recebido por criatura × tipo (salvo com a hunt, com símbolos dos elementos e imagens das criaturas) e somado por personagem. Verificado: backend 88 testes, frontend 74, build, Docker local, E2E do admin (não-admin 403, ticket → notas → respondido, erro agrupado e reaberto, ruído ignorado, token removido do caminho, CSRF 415) e telas em 390px e 1280px sem rolagem lateral nem erro de console. **Depois do deploy:** abrir /admin e conferir as abas; o log de erros começa vazio e enche sozinho.
000000. **D-61 (2026-11-07) — Hunt Analyser fase 2 completa, minimapa próprio, Yasir com mundo primeiro, v0.7.0 (D-59..D-61 não publicados).** Relatório de comparação (períodos, filtros, agrupamento), desempenho do personagem (períodos, gráficos, skills do Server Log, bestiário), análise de criaturas + estimativa dos charms, meus itens, melhores da semana, minimapa em blocos servidos pelo site, relato do Yasir só depois de escolher o mundo. Verificado: backend 85 testes, frontend 69, build, Docker local, telas em 390px e 1280px sem rolagem lateral nem erro de console (Minhas hunts, desempenho, detalhe, públicas, editor de set, /yasir, /hunting/darashia-dragon-lair). **Ideias para depois:** ler o código do WoD; Input Analyser por criatura; comparar dois personagens; exportar relatório CSV; mapa com marcadores (markers.json do tibiamaps) e rota desenhada; avisar no detalhe quando a hunt teve pouco tempo para comparar.
00000. **D-60 (2026-11-07) — Distribuição de pagamentos, v0.6.1.** Aba Pagamentos: várias party hunts → menor número de transferências (comando `transfer`), juntar personagens do mesmo jogador, arredondar, salvar, marcar como pago, compartilhar com amigos (eles marcam o que pagaram). Verificado: backend 85 testes, frontend 62, build, Docker local, E2E da API (não-amigo 404, amigo só marca pago, hunt de outro recusada, desfazer amizade tira o acesso, CSRF 415) e fluxo no navegador em 390px e 1280px sem rolagem lateral nem erro de console. **Próximo da fase 2:** (2) relatório de comparação com filtros e modo agrupado.
0000. **D-59 (2026-11-07) — Hunt session v2, v0.6.0 (não publicado ainda).** Nova hunt em 3 etapas (sobre + gastos extras + amigos; um campo por analisador; personagem criado na hora + set com stats por fonte + WoD + charms + preys + stances), detalhe com build e lucro final, aba Configurar (amigos, templates, sets, WoDs), compartilhadas comigo. Verificado: backend 82 testes, frontend 55, build, Docker local, E2E da API (amigo vê, não-amigo 404, compartilhamento removido = 404, template upsert, CSRF) e fluxo completo no navegador em 390px e 1280px sem rolagem lateral nem erro de console. **Próximos para superar o hunt-analyser (fase 2):** (1) ✅ Distribuição de pagamentos (D-60); (2) Relatório de comparação com filtros (vocação, tamanho da party, level, período com atalhos hoje/semana/mês) e modo agrupado; (3) Desempenho do personagem (horas de hunt, lucro, XP por período, melhores hunts, metas) — juntar com /characters; (4) Contribuição estimada dos charms (kills × chance × % de vida da criatura; Gut/Scavenge em itens), usando HP das criaturas; (5) Análise de criaturas (resistências, HP, XP, melhor charm); (6) "Meus itens" (itens com imbuements reutilizáveis entre sets) e progressão de skills; (7) Melhores da semana na lateral do feed público; (8) ler o código do WoD do cliente (formato binário — precisa engenharia reversa do planner).
000. **D-53..D-55 (2026-11-06 noite):** testes backend 71 + frontend 47, build ok, Docker local ok, 23 páginas sem erro/rolagem em 390px, parser offline ok, metas e estatística por local testadas via API, cobrança testada com Stripe falso (20 cenários: clique duplo, duplicata reembolsada, evento atrasado, conta errada, cliente desconhecido, cliente da CSVET ignorado, exclusão cancela). **Depois do deploy:** testar uma assinatura real; conferir `/admin → Alertas de cobrança`. ✅ D-56: tutoriais por vocação e 5 vocações em /hunting. ✅ D-57: sprites de criaturas (oficial + TibiaWiki) e mapa sob demanda. ✅ D-58: "Onde dropar" (/drops) para delivery tasks. Pendente: vários locais sem coordenadas de mapa (ex.: Cobra Bastion) — completar em shared/hunting-places.overrides.json. Próximos: Plano Guilda no Stripe, Discord para guildas.
00. **Sessão 2026-11-06 (D-49..D-52) — PUBLICADO (v0.4.0).** Produção conferida: health 0.4.0, criaturas juntadas (625/1085 ligadas), `/api/billing/status` enabled, webhook recusa evento sem assinatura (whsec ok), páginas 200. Stripe: conta CSVET (CNPJ que cobra vários projetos; dados públicos dela não foram alterados), webhook `bagparser-assinaturas` com os 5 eventos, portal do cliente já configurado (cancela no fim do período).
   - **PRÓXIMO (dono vai testar):** assinatura real com conta não-Premium em /pricing → conta vira Pro em /account → Entregas de eventos com 200 no Stripe → cancelar pelo portal (Pro segue até o fim do período) → reembolsar no Stripe se quiser.
   - Feito e verificado: testes (frontend 47, backend 63), `npm run build`, Docker local (migrações, API, rotas), fluxo E2E de conta de teste (salvar hunt pública/anônima, duplicata, ranking, personagem por nome, rastreio Pro bloqueado no Free, checkout sem chave → 503), telas em 1536px e 390px sem rolagem horizontal nem erros de console.
   - (feito) Para publicar: commit + push; na VPS acrescentar ao `.env` (sem sobrescrever): `STRIPE_PUBLISHABLE_KEY`, `STRIPE_SECRET_KEY`, `STRIPE_WEBHOOK_SECRET`; no Stripe criar o endpoint `https://bagparser.com/api/billing/webhook` (eventos: checkout.session.completed, customer.subscription.created/updated/deleted, invoice.paid) e salvar a configuração do Customer Portal; depois `bash deploy.sh`. Testar uma assinatura real e o cancelamento pelo portal.
   - **Pendente:** (1) ✅ D-56: tutoriais reescritos; (2) ✅ D-54: traduções es/pl; (3) ✅ D-52: erro `creatures_race_key` corrigido (duplicatas juntadas no start da API); (4) Plano Guilda ainda por e-mail (não está no Stripe); (5) `client.log` do cliente (tempo online por personagem) — avaliar como recurso Pro, cuidado com GUIDs do BattlEye (não guardar).
0. ✅ **Done 2026-11-05 (Night Session — D-47 & D-48):**
   - **Hunt Analyser Tracker (`/hunt-analyser`, `/hunt-tracker`):** Layout em tabela com filtros no topo (personagem, hunting spot, ordenação, busca de texto). Comparador lado a lado (Hunt A vs Hunt B) e projeção de tempo para próximo level.
   - **Verificação de Char tolerante:** `commentHasCode` normalizado com `/[^A-Z0-9]/g` (sem sensibilidade a espaços, hifens ou maiúsculas/minúsculas). Timeout aumentado para 25s contra lentidão de scraping.
   - **Limite Free:** Estrito em 1 personagem ativo (`CHARACTER_LIMIT = { free: 1, premium: 10 }`) com callout e link para `/pricing`.
   - **Dream Scar Boss do Dia:** Exibe o sprite animado real do boss sorteado para o dia no card da Home.
   - **Soul Cores Tracker (`/soulcores`):** Catálogo completo (824 criaturas) sempre visível, com identificação do usuário logado e fallback local seguro.
   - **Boss Kill Tracker (`/bossledger`):** Aba pessoal para acompanhar cooldown de 20h e registrar drops colando do Server Log (`Loot of ...`).
   - **Página de Novidades (`/news`):** Blog de novidades e changelog multilíngue em conformidade com o Fansite Programme da CipSoft.
   - **Navbar com Ícones:** Todos os itens de navegação ganharam ícones estilizados.

0a. **PRÓXIMA SESSÃO — METAS DE MONETIZAÇÃO E PRODUTO (D-48):**
   1. **Integração de Pagamentos:**
      - **Mercado Pago (Brasil):** Checkout / link de assinatura para BRL com suporte nativo a Pix Copia e Cola instantâneo e cartão de crédito nacional.
      - **Stripe (Internacional):** Checkout / Customer Portal para USD (cartão internacional, Apple Pay, Google Pay).
      - **Doação Rápida "Pague uma Mana Potion":** Componente de doação rápida (R$ 5 via Pix / $2 via PayPal/Stripe) no rodapé do Loot Parser e Hunt Tracker.
      - **Google AdSense:** Configuração de slots não-intrusivos no layout para usuários do plano Free (desativado automaticamente para usuários Pro).
   2. **Gatilhos de Conversão Pro (Item 2 completo):**
      - **Webhooks do Discord para Guildas:** Envio automático no Discord da guilda de alertas diários (Rashid, Yasir, Dream Scar) e resumo consolidado de hunts.
      - **Link Compartilhável de Hunt (`/h/:id`):** Geração de link público limpo e otimizado para WhatsApp/Discord com detalhes da hunt e party split (expira em 24h no Free; permanente no Pro).
      - **Gráficos de Evolução e Metas (Hunt Tracker Pro):** Visualização gráfica de XP/h, evolução semanal/mensal e balanço financeiro acumulado.
      - **Gestão de Múltiplos Personagens:** Monitoramento de até 10 personagens para rotas de bosses e makers no plano Pro.
   3. **Conformidade CipSoft:** Proibição estrita de parcerias com revendedores não oficiais de moedas (como Rei dos Coins ou MoedasTibia) para resguardar o status de fansite.

0b. **Deployed in Production (v0.3.0 baseline):**
   - Healthcheck: `https://bagparser.com/api/health` → `{"ok":true,"redis":"ready","version":"0.3.0"}`.
   - **Anti-bot & Segurança:** Cloudflare Turnstile widget (`0x4AAAAAAF1L8qttgfKwIY_H`) + Honeypot em `/login` e `/register` com validação server-side siteverify (`accounts.ts`).
   - **Categorias de Usuário:** Free, Apoiador Pro (VIP Individual) e Apoiador Guilda (Guild VIP) em `/pricing` e `/account`. Usuário de teste `atilio_calefi@yahoo.com.br` ativo como VIP Pro vitalício (2099).
   - **Conformidade Fansite Agreement:** Removidos links externos para TibiaWiki/Fandom; apenas `tibia.com` e `cipsoft.com` são linkados.
   - **Acesso SSH:** VPS `129.121.37.243` na porta `22022` configurada no `~/.ssh/config` (`ssh meu-vps`). Deploy key `/root/.ssh/bagparser_deploy`.
   - **Identidade e Contato:** Atilio Sersun Calefi registrado como Jogador & Criador; e-mail de contato e suporte `calefi@csvet.com.br` no `SITE`, página de sobre e rodapé.
   - **Monetização Multimoeda (`/pricing`):** Suporte financeiro para sustentar o site com seletor BRL (Pix & Cartão nacional) e USD (Stripe & PayPal), plano mensal e anual com 20% OFF.
   - **Canal de Suporte e Erros (`/support`):** Formulário direto para envio de problemas, bugs ou dúvidas com gravação técnica e rate-limiting (`support_messages`).
   - **Import de Loot de Boss & Server Log:** Suporte a upload de arquivo `.txt` do Server Log, detecção e limpeza de `(Double bonus)` de bônus diários/boss points, regex compatível com timestamps e horários, e auto-cadastro de novos bosses da Bosstiary do Tibia (evita erro de `unknown_boss`).
   - **Login e Redirecionamento:** Sanitização no `oauthRoutes.ts` para nunca desviar acessos via domínio público (`bagparser.com`) para `localhost`.
   - **Idioma:** Inglês definido como primário padrão, e novo seletor visual estilizado no cabeçalho com bandeiras (🇺🇸, 🇧🇷, 🇪🇸, 🇵🇱).
   - **Navbar:** Termos crípticos/variáveis substituídos por nomes descritivos claros e padronizados (`Loot Analyser`, `Hunting Spawns`, `Bestiary Spawns`, `Boss Drops`, `Hunt Tutorials`, `Characters`).
   - **Tibiadrome:** Correção da tradução ("Rotação do Tibiadrome", em vez de "Rodada").
   - **Diagnóstico SSL:** Identificado que o DNS `bagparser.com` apontou para o IP da HostGator (`208.91.112.55`) com certificado auto-assinado em vez do IP da VPS (`129.121.37.243`), gerando `ERR_CERT_AUTHORITY_INVALID`, somado a eventual interceptação de proxy Fortinet corporativo.

0a. **Google/Discord login (D-39) — code done, waiting for credentials:** Google Cloud (consent screen *External*, scopes openid/email/profile, published; OAuth client *Web application*) and Discord Developer Portal. Redirect URIs: `https://bagparser.com/api/auth/oauth/<google|discord>/callback` and `http://localhost:3080/api/auth/oauth/<google|discord>/callback`. Put the ids/secrets in `.env` (local: `PUBLIC_URL=http://localhost:3080` and open the site on **localhost**, not 127.0.0.1). Then test end to end: new account, sign-in again, link from `/account` with a password account, unlink, `email_exists` case.
0a2. **Done 2026-11-04 (evening, unreleased):** Google + Discord login tested end to end by the owner (D-39); Yasir sightings with city, weights, consensus, public `/yasir` and admin moderation (D-40); item sprites on every "Today in Tibia" card (Dream Scar uses an item: those bosses are not boostable, CipSoft has no image); sample-log buttons removed from the parser (samples stay for tests).
0a4. **Done 2026-11-04 (late, unreleased):** soul cores tracker `/soulcores` (D-41); hunting places directory `/hunting` + SpawnRank rewrite with "My tasks" (D-42). Deploy: see CLAUDE.md (API seeds the hunting places on first start, ~442 places).
0a6. **Done (unreleased, needs deploy):** D-43 SEO — share card per tool (`frontend/public/og/*.png`, generated by `node scripts/og-images.mjs` with local Chrome), og:locale, JSON-LD (WebSite/WebApplication/WebPage + BreadcrumbList), 442 pre-rendered `/hunting/<slug>` pages (458 pages, 454 in sitemap); removed `?lang=` hreflang alternates (they conflicted with the canonical).
0a7. **Started, not done:** security/optimisation — add to `frontend/nginx.conf` (via a snippet included in every location, because `add_header` in a location drops server-level ones): CSP (img: self data static.tibia.com static.wikia.nocookie.net i.ytimg.com; frame: tibiamaps.io www.youtube-nocookie.com; script/connect/worker: self; object-src none), X-Frame-Options SAMEORIGIN, nosniff, Referrer-Policy, Permissions-Policy, HSTS, `server_tokens off`; gzip for xml/json + gzip_vary; cache /og/ and images 7 days. Then lazy-load Hunting/SoulCores/Yasir pages (main bundle is 636 KB; move Stars/QuestBadge to components first). Test every page for CSP errors in the console.
0a8. **Owner asks:** Google Search Console (domain property via DNS TXT in HostGator, submit sitemap.xml, monitor coverage/CWV); share buttons on /hunting/:slug, /soulcores, /yasir; careful analysis of ALL inspiration sites (hunt-analyser, tibiadraptor, tibiacacau, tibiapal, tibiaroute, guildstats) for features to adopt; real per-language URLs (/pt, /es, /pl, /de) with translated pre-rendered titles.
0a5. **NEXT SESSION — queue from the owner, in this order:**
   1. Home page: an easy tool chooser (categories such as Hunting / Character / Trading / Bosses / Community, with search) — the tool list is getting long.
   2. German (de) translation: add `de` to the i18n detector and `translations.json` (all keys), legal pages fall back to English; check SEO `?lang=de` and hreflang.
   3. Research for features/structure (inspiration only, never copy): https://tibiaroute.com/ and https://guildstats.eu/ (very high traffic) — compare with hunt-analyser, tibiadraptor, tibiacacau, tibiapal notes in 0a3.
   4. BossLedger 2.0: paste the boss loot chat message → personal history per character, number of players in the fight, public drop statistics per boss and party size, compared with the character's boss points (Bosstiary) — highscores have boss points (see 0f "Skills/charm/boss points").
   5. YouTube (D-38): admin search + title parser (EK/ED/MS/RP/MK, "400+", place name) → suggest a hunting place; approved videos embedded on `/hunting/:slug` ("Community videos" placeholder is already there). Key is in the home PC `.env`.
   6. Character page: more charts, statistics and projections (tables + visuals).
   7. Hunt sessions: save Hunt / Party Hunt / Input Analyser pastes per character; party box only opens with a checkbox; build in compact list mode with a graphic toggle. Sample with chat prefixes in `docs/samples/2026-11-04-hunt-and-input-analyser.txt` (the parser already ignores the `[date] Name:` prefix; Input Analyser = received damage by type/source, not parsed yet).
0a3. **Owner backlog 2026-11-04 (evening) — ideas from hunt-analyser.com, tibiadraptor.com, tibiacacau.com.br and tibiapal.com (inspiration only: never copy text, data or layout; Fansite Agreement forbids plagiarism):**
   - **Hunt sessions (hunt-analyser style), improved:** save sessions per character (solo / party), public or private, history with XP/h, raw XP/h, balance; "best of the week" ranking from public hunts; compare two hunts side by side; hunt templates. Owner's UX asks: the **Party Hunt box only opens when a "party" checkbox is ticked**; character build (gear, charms, preys, wheel, stances) in a **compact list mode by default with a switch to the graphic mode**.
   - **Payment distributions in bulk** (group transfers by character; "merge characters" of the same player — tibiacacau), defence prey / extra costs deducted from party profit.
   - ✅ (D-42, basic version) **Hunting places directory (tibiapal-like structure, own data):** tabs per vocation + team hunts; filter by level and weapon type; per place: level range, expected raw XP/h and loot/h per level band, weapons/equipment, main damage types, route drawn on a tibiamaps.io map (MIT) and the community's YouTube videos embedded. Videos found by title parsing: vocation abbreviations (EK, ED, MS, RP, MK…), level ("400+"), place name → suggested match for admin approval (extends D-38). Our numbers can come from aggregated public hunt sessions (above) — that is our own data, not tibiapal's.
   - ✅ (D-42) **SpawnRank vs weekly/bounty tasks:** today SpawnRank is a general "best places per creature" with only sample zones (the owner found it confusing). Plan: fill it from the hunting places import and add "for my tasks" mode — pick a verified character and show places for this week's creature tasks (D-37) and the bounty task.
   - ✅ (D-41, 2026-11-04) **Soul cores tracker per character:** search/choose the character, tick the soul cores it already has (Soulpit), see how many are done and which are missing (filters by difficulty/class, search). Needs the full bestiary creature list (TibiaWiki import, CC BY-SA). Public share link per character (opt-in).
   - **Progress trackers (tibiadraptor):** bestiary, bosstiary, achievements, mounts, outfits, quests, titles; up to N characters; compare 2 characters; event countdowns (e.g. The Lightbearer); challenges with evidence + prizes.
   - **Calculators (tibiacacau):** boss sign-up lists (5/10/15 slots by vocation, share link, paste a message), charm optimizer from a session, critical vs elemental pierce, exercise weapons, imbuement cost, stamina recovery, Alfa vs Omega Strike.
   - **Monetisation (hunt-analyser model):** freemium — free account keeps core features, Pro raises limits and unlocks extras; monthly/annual with a big annual discount; regional prices (BRL and USD); pay by **Stripe or Tibia Coins** (check the Tibia Rules / Fansite Agreement before accepting Tibia Coins); early-adopter perks; milestones and lotteries to keep the community engaged. Still needs the decisions in 0c.
0b. Fansite application: follow `docs/FANSITE.md` (checklist + what to send to fansites@tibia.com).
0c. Monetisation still to decide: what Premium unlocks, price, payment provider (e.g. Mercado Pago / Stripe) and e-mail delivery (SMTP) for self-service password reset. Today Premium is granted by hand in `/admin`.
0d. **Owner backlog (2026-11-04) — done:** character tracking (D-28), identity + light theme (D-29), home "Today in Tibia" (D-30), share buttons (D-31), CHANGELOG/LGPD/terms/export (D-32), worlds registry + rename/transfer history (D-33).
0e. **Done after v0.2.0 (unreleased):** item sprites + loot grid (D-35), SEO pages/sitemap (D-36), weekly tasks with drop sources (D-37), coins/weekly fixes (D-34), LICENSE + NOTICE.md.
0f0. **IN PROGRESS — YouTube tutorials directory (D-38, after lunch 2026-11-04):** helpers done in `backend/src/youtube.ts` (+ `youtube.test.ts`, 3 tests). To do:
   - ✅ Env `YOUTUBE_API_KEY` wired in `docker-compose.yml` / `.env.example`; the owner's key is in the home PC `.env` (restrict it to YouTube Data API v3 in the Console). Without the key the site works and `/admin` shows a notice.
   - Table `videos` (youtube_id PK, title, channel_id/title, published_at, duration_s, thumbnail_url, view_count, language, category hunt/quest/boss/guide/other, topic text, status pending/approved/rejected, added_by, fetched_at).
   - Admin: search (`search.list`, 100 quota units each; cap per day) or paste URL → queue; approve with category/topic; reject.
   - Public `/videos`: approved videos, filters (category, language, text), thumbnail → player `youtube-nocookie.com` on click, channel credit + "via YouTube".
   - Daily refresh with `videos.list` (1 unit / 50 ids) — update metadata, delete removed/private/non-embeddable videos (API policy: refresh or delete within 30 days).
   - Privacy Policy: YouTube API Services + links to YouTube Terms and Google Privacy Policy; bump `LEGAL_VERSION` (frontend `legal.ts` + backend `auth.ts`).
   - Later: related videos on QuickHunts/SpawnRank by topic; "Sources" field in QuickHunts articles.
0f. **Next, in this order:**
   - ✅ Item images checked at home (2026-11-04): static.tibia.com loads (20/20 on the parser sample, 2/2 on the home page). The TibiaWiki CDN answers **every** file with a 300×171 grey WebP placeholder (status 200, even opened directly), so `onError` never fired; `ItemSprite` now treats non-sprite sizes as a failure and leaves the empty square. Only 5 of 2,391 items lack an official id. Consider dropping the wiki fallback (and its mention in the Privacy Policy) if it stays like this.
   - ✅ (D-42) Import TibiaWiki Hunting Places (463, CC BY-SA: city, levels per vocation, exp/loot stars, creatures per floor, `Mapper Coords`) into SpawnRank zones instead of the 4 samples; density estimated from floors/order. Do NOT copy tibiapal.com (Fansite Agreement forbids plagiarism; ask the owner for permission if needed).
   - Mini-map from the coordinates using tibia-map-data (MIT) + CipSoft credit.
   - Login hardening: Turnstile still to do (Google/Discord done — see 0a).
   - Skills/charm/boss points from highscores; automatic daily snapshot (Premium perk).
   - Confirm contact e-mail (`frontend/src/site.ts`), then fansite application (`docs/FANSITE.md`).
1. Review/replace the two draft QuickHunts articles in `frontend/src/content/quickhunts/` (marked as drafts).
2. Production is live at https://bagparser.com (D-23). To update: push to `main`, then on the VPS `cd /opt/apps/bagparser && bash deploy.sh` (details in CLAUDE.md). Check that `git pull` with the deploy key works again (it failed during a GitHub incident on 2026-11-04).
3. Optional: re-run `node scripts/import-tibiawiki.mjs` after Tibia updates (prices/NPCs change); consider a monthly reminder.
4. Optional: the frontend bundle is ~480 KB (~107 KB gzip), mostly the item catalog; it is cached by the service worker after the first visit.
5. Later items: FR-QH-03 (pre-render), FR-TD-05 (creature details).

## PWA & mobile
| Item | Status | Notes |
|---|---|---|
| Manifest, icons (any + maskable), iOS metas | ✅ | Chrome reports no installability errors |
| Service worker: offline parser, cached API data, update prompt | ✅ | Tested offline with Playwright (D-21) |
| Install button, offline banner | ✅ | |
| Mobile layout (390px): buyers under item, no unit column, 16px inputs, 40px touch targets | ✅ | |
| Sell route (cities to visit), paste button, remembered log, copy all transfers | ✅ | D-22 |

## Accounts & admin (D-24..D-27)
| Item | Status | Notes |
|---|---|---|
| Register / login / logout / profile / change password / delete account | ✅ | `/login`, `/register`, `/account`; tested end to end (curl + Playwright 390px and 1280px) |
| Sessions, CSRF, rate limits, ban kills sessions | ✅ | `backend/src/accounts.ts`, `auth.ts` (9 unit tests) |
| Admin panel: stats, users, Premium, roles, ban, reset link, sign-out, delete, audit log | ✅ | `/admin` (lazy chunk) |
| Anonymous traffic counter + monthly report | ✅ | `POST /api/pv`; report in `/admin` |
| About / contact / monetisation / privacy / rules page; CipSoft credit with links | ✅ | `/about`; contact e-mail is a placeholder (`frontend/src/site.ts`) |
| Payment provider, self-service password reset by e-mail | ⏳ | Needs decisions (0c) |
| Sign in with Google / Discord, linked logins in `/account` | 🚧 | D-39; `backend/src/oauth.ts` (5 tests) + `oauthRoutes.ts`; tested with a fake client id (start redirect, state cookie, PKCE, bad state) and password-less account flows via curl; UI checked at 1280px and 390px. Needs real credentials for the end-to-end test |
| Privacy Policy / Terms (versioned), consent record, data export, retention prune | ✅ | D-32 |
| Worlds registry, character rename/transfer history | ✅ | D-33 |
| Home "Today in Tibia", share buttons, identity/light theme | ✅ | D-29..D-31 |
| Character tracking: code verification, manual update, snapshots, deaths, goals, charts, suggestions | ✅ | D-28; `/characters`; backend `characterRoutes.ts`, `characters.ts` (8 tests); frontend `core/progress.ts` (7 tests); checked at 390px |
| Skills / other highscore data (charm, boss points) | 🔜 | Only top 1000 per world in highscores; needs a shared daily scan per world |
| Automatic daily snapshot (no click) | 🔜 | Good Premium perk; would make XP/day exact |

## Infrastructure
| Item | Status | Notes |
|---|---|---|
| Repo structure, `.gitignore`, `.dockerignore`, `.env.example` | ✅ | |
| `docker-compose.yml` (NFR-01..04) | ✅ | Tested with `docker compose up --build` |
| `init.sql` schema + sample data | ✅ | includes `creatures.race/image_url` (D-14) |
| Frontend Dockerfile + `nginx.conf` | ✅ | |
| Backend Dockerfile | ✅ | |
| README | ✅ | dev setup, Docker, deploy (NPM or host Nginx) |
| `docker-compose.npm.yml` + `deploy.sh` (NPM on the VPS) | ✅ | Deployed 2026-11-04: site, `/api/health`, `/api/items`, `/api/boosted`, `www`, HTTP→HTTPS and `sw.js` no-cache checked over HTTPS |

## BagParser Core
| Req | Status | Notes |
|---|---|---|
| FR-CORE-01..09, 13 parser logic | ✅ | `parser.ts`, `items.ts`, `party.ts`; 15 Vitest tests passing |
| FR-CORE-11..12 Rashid / Yasir logic | ✅ | `npcSchedule.ts` (10:00 Berlin day boundary) |
| FR-CORE-14 sample logs | ✅ | `samples.ts` |
| FR-CORE-10, UI for all of the above | ✅ | `pages/ParserPage.tsx`, `components/CopyText.tsx`; buyer NPC + city per item; checked in Chrome at 1280px and 390px |

## Backend API (backend/src)
| Item | Status |
|---|---|
| Routes: `/api/health`, `/api/items`, `/api/boosted`, `/api/creatures`, `/api/spawnrank?creature_id=`, `/api/bosses`, `/api/bosses/:id/stats`, `POST /api/bosses/drops` | ✅ |
| Item seed from `shared/items.json` on start | ✅ |
| Unit tests (boss log parsing, hash, score, TibiaData helpers): 7 passing; `tsc` clean | ✅ |
| Tested end to end in Docker: health, item seed, TibiaData sync (9/9), boosted, spawnrank, boss submit (recorded / duplicate / rate_limited / unknown_boss / invalid_format), spoofed `X-Forwarded-For` ignored | ✅ |

## SpawnRank
| Req | Status |
|---|---|
| FR-SR-01 score (API) | ✅ |
| FR-SR-02..03 UI | ✅ |

## BossLedger
| Req | Status |
|---|---|
| FR-BL-01..07 (API) | ✅ |
| FR-BL-06 UI | ✅ |

## TibiaData
| Req | Status |
|---|---|
| FR-TD-01..04 (API side) | ✅ |
| FR-TD-01..02 UI banner | ✅ |
| FR-TD-05 | 🔜 |

## QuickHunts
| Req | Status |
|---|---|
| FR-QH-01..02 | ✅ (2 draft articles) |
| FR-QH-03 (pre-render) | 🔜 |

## i18n
| Req | Status | Notes |
|---|---|---|
| FR-I18N-01..03 | ✅ | `src/i18n/` (en, pt-BR, es, pl), selector in `App.tsx` |
| Missing keys (boosted, status_*) | ✅ | 83 keys in each language |

## Data
| Req | Status | Notes |
|---|---|---|
| DATA-01 catalog file | ✅ | 2,393 items + 179 buyer NPCs with city (D-20) |
| DATA-02 verify against TibiaWiki | ✅ | Whole catalog generated from TibiaWiki (D-20) |
| DATA-04 TibiaWiki import script | ✅ | `scripts/import-tibiawiki.mjs` (~1.5 min) |

## Open questions
- Domain/subdomain for production and which VPS reverse proxy is in use.
- Will Yasir status ever come from an external source (community API)?
