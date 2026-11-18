# Necropsia: aviso imediato, link direto e SEO centrado no Prof. Atilio (2026-11-01)

## Contexto

O professor percebeu vários pedidos de orçamento de necropsia dos quais nunca
recebeu aviso. Ele pediu três coisas:

1. receber e-mail e Telegram para retornar o quanto antes;
2. ter um link fácil para quem já combinou a necropsia diretamente com ele;
3. revisar o SEO e atrair pedidos de necropsia pelo site.

**Restrição importante:** a necropsia é um serviço volante e esporádico. O site
pode divulgá-la, mas sempre centrada na pessoa do Prof. Atilio e no contato
direto com ele. Nunca deve aparecer como **laboratório** ou estabelecimento
(sem endereço, sem horário de funcionamento, sem schema `LocalBusiness`), para
não gerar fiscalização.

## Causa dos pedidos sem aviso

- O passo 1 (`/solicitar-necropsia`) criava o registro `quote_generated` **sem
  pedir contato e sem avisar ninguém**. Só o passo 2 (termo com CPF, RG,
  endereço e assinatura) disparava aviso. Quem parava no orçamento ficava
  invisível.
- No passo 2, os avisos estavam dentro do `try` da geração do PDF: se o PDF
  falhasse, nada era enviado. Os e-mails usavam `->queue()`, que dependia do
  cron.
- O formulário de edição no painel exigia CPF, RG, endereço e nome do animal,
  então não era possível salvar um orçamento.

## Comportamento novo

### Avisos
- O passo 1 pede **nome e WhatsApp** (obrigatórios) e e-mail (opcional). Tem
  ainda um honeypot `website`: se vier preenchido, nada é criado e ninguém é
  avisado.
- `App\Services\NecropsyNotificationService`:
  - `notifyNewQuote()` manda e-mail **síncrono** para `contato@profatilio.com.br`
    (com reply-to do interessado) e Telegram para todos os admins com chat
    vinculado. A mensagem traz contato, animal, valor, link do WhatsApp com a
    resposta pronta e link do painel.
  - `notifyAuthorized()` é chamado **fora** do `try` do PDF. Se o PDF falhar,
    o aviso traz um alerta.
  - Cada canal tem seu próprio try/catch. Entradas do usuário passam por
    `e()` no HTML do Telegram.
- O e-mail ao requisitante no passo 2 passou a ser síncrono.

### Retorno ao interessado (painel)
- Migration `2026_11_01_120000_add_contacted_at_to_necropsy_requests_table`
  cria a coluna `contacted_at`.
- Scope `awaitingReply()`: status `quote_generated`, sem `contacted_at` e com
  telefone. Pedidos antigos sem contato ficam de fora.
- Badge vermelho no menu, filtro "Aguardando meu retorno" e coluna "Retorno".
  Pedidos antigos aparecem como "Sem contato (pedido antigo)".
- Colunas de telefone e "Pedido Em". A lista abre ordenada do mais recente
  para o mais antigo.
- Ações de linha:
  - **WhatsApp**: abre a mensagem de retorno pronta;
  - **Link do termo**: mostra a mensagem com o link pessoal do passo 2, mais
    um botão para abrir no WhatsApp;
  - **Respondido**: um clique, e o botão some depois de usado.
- No formulário de edição, os campos do passo 2 só são obrigatórios com status
  `authorized`. Há também o campo `contacted_at`.
- O resumo diário do Telegram (`app:send-daily-summary`) lista os orçamentos
  sem retorno.

### Link fácil para necropsia já combinada
- **Link fixo** `GET /autorizar-necropsia` (`necropsy.direto`): o mesmo
  formulário em modo `direct`, com título "Autorização de Necropsia",
  `noindex` e campo oculto `direct=1`. O pedido entra com `contacted_at = now()`
  (não conta como pendente) e o aviso diz "Necropsia COMBINADA".
- Ações no topo do painel:
  - **Link direto (combinado)**: mensagem pronta com o link fixo;
  - **Termo com valor combinado**: modal com nome, WhatsApp, espécie, peso e
    valor combinado (em branco, usa a tabela). Cria o pedido e devolve a
    mensagem com o link pessoal do termo, mais o botão do WhatsApp.
    `price = max(tabela, combinado)` e `discount = tabela - combinado`.
- Helpers no model: `whatsappUrl(?text)`, `authorizationUrl()`,
  `authorizationMessage()`, `directLinkMessage()` e `formattedPrice()`.

### Termo de autorização
- No termo (`auth_form`) e no PDF (`auth_pdf`), "laboratório" foi trocado por
  "patologista responsável" nas cláusulas 5, 7 e 8. Vale só para termos novos.

### SEO
- Nova página `GET /necropsia-veterinaria` (`necropsy.landing`, view
  `necropsia.blade.php`). Tem H1 com o nome do professor, o que está incluso,
  tabela de valores gerada por `calculatePricing()`, passo a passo, bloco para
  veterinários, FAQ e CTAs (formulário e WhatsApp). JSON-LD com `Service`
  (provider `#person`, areaServed Grande São Paulo, `AggregateOffer`),
  `FAQPage` e `BreadcrumbList`. Nenhuma menção a laboratório; há teste para
  garantir isso.
- O menu de serviços e o rodapé apontam para a página nova. O card da home
  ganhou o link "Como funciona…". O `Person` da home ganhou uma `Offer` de
  necropsia.
- `/solicitar-necropsia` ganhou title, description, canonical e OG.
- Sitemap: entrou `/necropsia-veterinaria` (0.9). Saíram os recibos
  `/documento/validar/{hash}` (Termo de Aceite de IC, com nome e RGM de aluno
  e sem valor de SEO).
- Ganharam `noindex`: termo da necropsia (`auth_form`), tela de sucesso,
  `document_validate`, `estagio/turma_landing`, `invite` e
  `registration_status`.
- `/verificador` ganhou description, canonical, OG e um `<h1>`.
- `robots.txt` ganhou `Allow` para `/necropsia-veterinaria`,
  `/solicitar-necropsia` e `/solicitar-atendimento`.

## Arquivos

- `app/Services/NecropsyNotificationService.php` (novo)
- `app/Http/Controllers/NecropsyRequestController.php`
- `app/Models/NecropsyRequest.php`
- `app/Filament/Resources/NecropsyRequestResource.php`
- `app/Filament/Resources/NecropsyRequestResource/Pages/ListNecropsyRequests.php`
- `app/Console/Commands/SendDailySummaryToAdmin.php`
- `database/migrations/2026_11_01_120000_add_contacted_at_to_necropsy_requests_table.php` (novo)
- `routes/web.php` (rotas `necropsy.landing` e `necropsy.direto`, sitemap)
- `resources/views/necropsia.blade.php` (novo)
- `resources/views/necropsy/{quote_form,auth_form,auth_pdf,success}.blade.php`
- `resources/views/welcome.blade.php`
- `resources/views/partials/{site-nav,site-footer}.blade.php`
- `resources/views/{document_verifier,document_validate,invite,registration_status}.blade.php`
- `resources/views/estagio/turma_landing.blade.php`
- `public/robots.txt`
- `public/build/*` (CSS recompilado para a página nova)
- `tests/Feature/NecropsyRequestNotificationTest.php` (novo, 10 testes)

## Deploy

Local (o `public/build/` recompilado vai junto no commit):

```bash
git add -A
git commit -m "feat: necropsia com aviso imediato, link direto e página SEO"
git push
```

No servidor (SSH do cPanel), sem composer, porque não há pacote novo:

```bash
cd ~/profatilio.com.br
git pull
php artisan migrate --force
php artisan optimize:clear
php artisan filament:optimize-clear
php artisan queue:restart
```

`migrate` cria a coluna `contacted_at`. `optimize:clear` limpa config, rotas e
views (há rotas novas). `filament:optimize-clear` atualiza o cache de componentes
do painel. Depois:

- testar um pedido real em `/solicitar-necropsia` e conferir se chegam o e-mail
  e o Telegram;
- enviar `https://profatilio.com.br/sitemap.xml` no Google Search Console.

## Como importar para o prof-aid.com

1. Copiar o service, a migration, os helpers e o scope do model, e as mudanças
   do controller.
2. No multi-tenant, o e-mail fixo `contato@profatilio.com.br` e "admins com
   Telegram" viram dados do tenant. Parametrizar em `NecropsyNotificationService`.
3. As mensagens prontas citam "Prof. Atilio": usar o nome do profissional do
   tenant.
4. A landing `/necropsia-veterinaria` é específica do Prof. Atilio (texto,
   região e schema `#person`). No SaaS ela só faz sentido como template
   opcional.
5. A troca de "laboratório" no termo depende do texto jurídico de cada tenant.
