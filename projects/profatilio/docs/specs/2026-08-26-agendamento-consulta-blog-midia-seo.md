# Agendamento de Consulta + Blog + Na Mídia + SEO

- **Data:** 2026-08-26
- **Origem:** o professor quer captar pacientes/clientes direto pelo site: formulário de solicitação de agendamento de consulta (foco clínico: imunologia veterinária e clínica de animais não convencionais), com notificação por e-mail + Telegram e um ticket gerenciável no admin. Junto, pediu para aumentar o SEO do site: formulário fácil de achar na home, um blog (infraestrutura agora, conteúdo depois) e uma página "Na Mídia" com reportagens em que ele já apareceu como fonte.
- **Status de importação para prof-aid.com:** pendente
- **Prontuário eletrônico evolutivo:** fora de escopo aqui — combinado explicitamente com o usuário para ficar para depois.

## Comportamento

### 1. Solicitação de Agendamento de Consulta
- Nova seção `#agendar-consulta` na home (`welcome.blade.php`), com formulário: nome, e-mail, telefone/WhatsApp, tipo de animal (Convencional/Não Convencional), espécie, queixa principal. Honeypot igual ao formulário de consultoria já existente.
- `POST /agendar-consulta` (`routes/web.php`) — valida, cria `AppointmentRequest`, notifica o professor por e-mail (`AppNotificationMail`, **síncrono** via `->send()`, com `->replyTo()` apontando para o e-mail do solicitante) e por Telegram (mesmo loop de admins com `telegram_chat_id` já usado em `/submit-consultation`), em try/catch separados.
- `AppointmentRequestResource` (Filament, grupo "Atendimento Clínico"): campos submetidos somente-leitura + status (Pendente/Contato Realizado/Agendado/Concluído/Cancelado) e notas internas editáveis.
- CTA "Agendar Consulta" também no nav (desktop/mobile) e na seção Hero, para fácil acesso.

### 2. Blog
- `BlogPost` (migration `blog_posts`): title, slug, excerpt, content (RichEditor/HTML), cover_image (**disco `public`**, não o disco padrão `google` — capa de post é conteúdo público), status (draft/published), published_at, seo_description.
- `BlogPostResource` (Filament, grupo "Site & Blog") — slug auto-gerado do título na criação, RichEditor, upload de capa, ação "Ver no Site".
- `BlogController@index`/`@show` + rotas `GET /blog`, `GET /blog/{slug}` — só published, 404 se não.
- Views standalone (`blog/index.blade.php`, `blog/show.blade.php`) seguindo a mesma convenção do resto do site (sem `@extends`/layout — nenhuma página pública do projeto usa isso). `show.blade.php` gera meta tags e JSON-LD `Article` por post.
- **Refinamento sobre o plano original:** nav e footer foram extraídos para `resources/views/partials/site-nav.blade.php` e `site-footer.blade.php`, reaproveitados via `@include` nas 3 páginas novas (blog index/show, na-mídia). Isso não é um layout compartilhado (o projeto já usa `@include` para widgets, ex: `partials.whatsapp_widget` em `welcome.blade.php`) — só evita triplicar ~80 linhas de nav idênticas. `welcome.blade.php` continua com sua própria cópia da nav (porque usa âncoras internas tipo `#bio`; as páginas novas usam `/#bio` para voltar à home).
- Sem posts reais ainda — infraestrutura pronta, aguardando os temas que o professor vai indicar. **Regra para quando isso acontecer** (pedido explícito do usuário): basear os artigos em publicações indexadas no PubMed, com dados reais e verificáveis.

### 3. Na Mídia
- `PressMention` (migration `press_mentions`): title, url, outlet, section, author, published_at, excerpt.
- `PressMentionResource` (Filament, grupo "Site & Blog") — CRUD simples.
- `database/seeders/PressMentionSeeder.php` — populado com as 10 matérias reais fornecidas pelo usuário (coluna "É o Bicho" e "Vida e Estilo", Metrópoles); título, autor e data conferidos na matéria original via fetch, não inventados.
- `GET /na-midia` + view standalone.

### 4. SEO
- Meta description/keywords da home atualizadas com termos de consulta veterinária/imunologia/animais não convencionais.
- Novo bloco JSON-LD `VeterinaryCare` na home (além dos 4 já existentes: Person/WebSite/Service/FAQPage), descrevendo a especialidade clínica.
- `sitemap.xml`: novas entradas estáticas `/blog` e `/na-midia`, e loop dinâmico sobre `BlogPost::published()`.
- `robots.txt`: `Allow` para `/blog`, `/blog/*`, `/na-midia`; `Disallow` para `/agendar-consulta` (POST-only, mesmo tratamento do `/submit-consultation`).

## Arquivos novos
- `database/migrations/2026_08_26_174331_create_appointment_requests_table.php`, `..._174332_create_blog_posts_table.php`, `..._174333_create_press_mentions_table.php`
- `app/Models/AppointmentRequest.php`, `BlogPost.php`, `PressMention.php`
- `app/Filament/Resources/AppointmentRequestResource.php`, `BlogPostResource.php`, `PressMentionResource.php` (+ Pages List/Create/Edit de cada)
- `app/Http/Controllers/BlogController.php`
- `database/seeders/PressMentionSeeder.php`
- `resources/views/blog/index.blade.php`, `resources/views/blog/show.blade.php`, `resources/views/na-midia.blade.php`
- `resources/views/partials/site-nav.blade.php`, `resources/views/partials/site-footer.blade.php`

## Arquivos alterados
- `routes/web.php` — rota `/agendar-consulta`, rotas de blog/na-mídia, novas entradas no sitemap.
- `resources/views/welcome.blade.php` — nova seção, nav (desktop+mobile), CTA no Hero, meta tags, JSON-LD `VeterinaryCare`.
- `public/robots.txt`.

## Como importar para o prof-aid.com

1. Copiar todos os arquivos novos/alterados acima para os mesmos caminhos.
2. Rodar `php artisan migrate --force`.
3. Rodar `php artisan storage:link` — **necessário** para a imagem de capa do blog funcionar (disco `public`; sem isso o arquivo é salvo mas a URL não resolve).
4. Rodar `php artisan db:seed --class=PressMentionSeeder --force` (idempotente, usa `updateOrCreate` por URL).
5. Sem mudança de config/pacote novo.

## Verificação realizada nesta sessão

- `php -l` em todos os arquivos PHP novos/alterados — sem erros.
- Todas as views Blade novas + `welcome.blade.php` compiladas via `BladeCompiler` e o PHP resultante lintado — sem erros.
- Migrations rodadas com sucesso contra o banco `profatilio` (MySQL local via XAMPP) e contra SQLite isolado.
- `PressMentionSeeder` rodado — 10 registros conferidos no banco.
- `php artisan route:list` confirmou todas as rotas novas (públicas e dos 3 resources Filament) registradas sem erro de boot.
- **Teste end-to-end via navegador** (servidor local `php artisan serve` + MySQL local):
  - Preenchimento e envio real do formulário "Agendar Consulta" na home — sucesso, registro criado no banco com os dados corretos, e-mail de notificação (via `AppNotificationMail`, log local) renderizado corretamente com todos os campos, sem nenhum erro no `laravel.log`.
  - `/blog` (vazio) e `/na-midia` (10 matérias) renderizados corretamente.
  - `/blog/{slug}` testado com um post temporário — título, conteúdo HTML (negrito, H2), data e navegação renderizados corretamente; testado sem imagem de capa (guarda condicional funciona).
  - `sitemap.xml` e `robots.txt` conferidos com as novas entradas.
  - Dados de teste (solicitação de consulta e post de blog) removidos do banco após a verificação.

## Não verificado (sem acesso de admin nesta sessão)

- Renderização visual das 3 novas telas no painel Filament (`AppointmentRequestResource`, `BlogPostResource`, `PressMentionResource`) via login real — a alteração de senha do usuário de teste local foi bloqueada pela política de segurança do ambiente. A validade estrutural desses resources foi confirmada indiretamente (registro de rotas sem erro de boot, que exige que as classes sejam válidas), mas não houve teste visual/interativo logado como admin.
