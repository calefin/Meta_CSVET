# Ìjọ Ifá Èjìogbè

Sistema web da casa de Ifá Ìjọ Ifá Èjìogbè. Laravel 12 + Livewire v3 + Tailwind v3 + MySQL 8.
Dominio de producao: **ijoifa.com**.

> Este repositorio cobre a Fase 1 (fundacao): scaffold, schema completo, autenticacao com
> aprovacao de admin, design system, i18n (pt-BR/en/es) e suporte a caracteres yorùbá.
> O roteiro das fases seguintes (ciclo oracular, catalogos, agenda, financeiro, cursos,
> mensageria, LGPD, backups) esta descrito no documento de requisitos do projeto.

## Ambiente de desenvolvimento local (Docker)

Esta maquina nao tem PHP/Composer/MySQL nativos, entao o projeto roda via Docker
(Laravel Sail). Como o script `sail` nao funciona dentro do Git Bash/MINGW no Windows,
use `docker compose` diretamente:

```bash
# subir os containers (app + mysql)
docker compose up -d

# rodar comandos artisan
docker compose exec laravel.test php artisan migrate
docker compose exec laravel.test php artisan tinker

# instalar/atualizar dependencias PHP
docker compose exec laravel.test composer install

# build de assets (Tailwind/Vite) - roda direto no host, Node ja esta instalado
npm install
npm run build   # produção
npm run dev      # desenvolvimento com hot reload
```

A aplicacao fica disponivel em **http://localhost:8080** (porta 80 do host estava
ocupada por outro projeto, por isso `APP_PORT=8080` no `.env`).

### Usuario admin inicial

Criado pelo seeder `AdminUserSeeder` (`php artisan migrate:fresh --seed`):

- E-mail: `admin@ijoifa.com`
- Senha: `troque-esta-senha` — **troque imediatamente em qualquer ambiente real.**

### Problema conhecido: erro `tempnam()` / permissao em storage

Como os arquivos do projeto foram criados via container Docker rodando como root
(nao havia Composer nativo no Windows para gerar o scaffold), as pastas `storage/`
e `bootstrap/cache` podem ficar com dono `root` enquanto o PHP roda como usuario
`sail` dentro do container, causando erro 500 "tempnam(): file created in the
system's temporary directory". Se isso ocorrer novamente (ex.: apos recriar o
container do zero), corrija com:

```bash
docker compose exec laravel.test sh -c "chown -R sail:sail storage bootstrap/cache && chmod -R 775 storage bootstrap/cache"
```

## Deploy em hospedagem compartilhada (HostGator)

A pasta `public/` do Laravel **nao** recebe apontamento direto de dominio. Toda a
aplicacao (incluindo `app/`, `vendor/`, etc.) e enviada para a raiz exposta
(`public_html` ou equivalente), e o `.htaccess` da raiz redireciona as
requisicoes para `public/`. Esse arquivo ja esta no repositorio.

Passos:

1. **Build local antes do upload** — o HostGator nao roda `composer install` nem
   `npm run build` facilmente em plano compartilhado:
   ```bash
   docker compose exec laravel.test composer install --no-dev --optimize-autoloader
   npm run build
   ```
2. Copie todo o projeto (exceto `.git`, `node_modules`, `.env` local) para o servidor.
3. Crie o `.env` de producao no servidor a partir do `.env.example`, preenchendo:
   - `APP_KEY` (gerar com `php artisan key:generate --show` localmente e colar)
   - Credenciais do banco MySQL fornecidas pelo cPanel
   - `APP_URL=https://ijoifa.com`
   - Credenciais do Mercado Pago quando disponiveis (`MP_ACCESS_TOKEN`, `MP_PUBLIC_KEY`)
4. Rode as migrations no servidor (via SSH ou terminal do cPanel):
   ```bash
   php artisan migrate --force
   php artisan config:cache
   php artisan route:cache
   php artisan view:cache
   ```
5. Confirme que `.htaccess` da raiz esta presente e que o dominio aponta para essa
   pasta raiz (nao para `public/`).
6. Configure o cron job diario de backup no cPanel (Cron Jobs), apontando para:
   ```
   0 2 * * * cd /home/SEU_USUARIO/ijoifa.com && php artisan backup:run >> /dev/null 2>&1
   ```
   Preencha no `.env`:
   - `ADMIN_EMAIL` para receber alerta por e-mail em caso de falha
   - `RCLONE_REMOTE` (ex.: `gdrive:ijoifa-backups`) se quiser copia externa via
     [rclone](https://rclone.org/) (precisa do binario `rclone` configurado no
     servidor — sem isso o backup local ainda funciona, apenas sem copia externa)
   - O job aplica retencao automatica: ultimos 30 backups diarios + 1 por mes
     nos ultimos 12 meses.
7. Configure um cron job para processar a fila (e-mails de cadastro/orcamento sao
   sincronos, mas o envio em massa pelo admin e enfileirado para nao travar a
   requisicao):
   ```
   * * * * * cd /home/SEU_USUARIO/ijoifa.com && php artisan queue:work --stop-when-empty >> /dev/null 2>&1
   ```
   Sem isso, os e-mails enviados pela tela "Enviar e-mail" (admin) ficam parados
   na tabela `jobs` e nunca saem.

### E-mail: evitar caixa de spam

Quem entrega o e-mail (o servidor SMTP, configurado em `MAIL_HOST`/`MAIL_USERNAME`
no `.env`) precisa estar autorizado a enviar como `@ijoifa.com`, ou provedores como
Gmail/Outlook vao marcar como spam ou rejeitar. Configure no DNS do dominio
`ijoifa.com` (no painel onde o dominio foi registrado, nao no HostGator
necessariamente):

- **SPF**: registro TXT autorizando o servidor de envio, ex.:
  `v=spf1 include:_spf.<seu-provedor-de-email> ~all` (o HostGator geralmente
  fornece o include exato — confira no cPanel > Email Deliverability).
- **DKIM**: o cPanel do HostGator costuma gerar a chave DKIM automaticamente
  (cPanel > Email Deliverability mostra o status e o registro a copiar).
- **DMARC**: registro TXT em `_dmarc.ijoifa.com`, ex.:
  `v=DMARC1; p=quarantine; rua=mailto:contato@ijoifa.com`.
- **`MAIL_FROM_ADDRESS`** precisa ser um e-mail real do dominio `ijoifa.com`
  (ex.: `contato@ijoifa.com`), nunca um e-mail de outro dominio — isso por si
  só já reprova SPF/DMARC em muitos provedores.

Considere tambem usar um servico transacional dedicado (ex.: Mailgun, Resend,
Postmark, Amazon SES) em vez do SMTP padrao do HostGator — IPs de hospedagem
compartilhada sao alvo frequente de blacklists, o que prejudica a entrega
mesmo com SPF/DKIM/DMARC corretos. Para usar um desses, basta trocar
`MAIL_MAILER` e as credenciais correspondentes no `.env`.

Os e-mails ja sao enviados em HTML + texto puro (multipart), o que tambem
ajuda no score anti-spam.

## Stack

- PHP 8.2+ / Laravel 12 (o documento original previa Laravel 11, mas essa versao
  ja estaria fora do suporte de seguranca na data deste projeto — seguimos com a
  versao atual com suporte ativo)
- Livewire v3
- Tailwind CSS v3
- MySQL 8 (utf8mb4 / utf8mb4_unicode_ci em todas as tabelas)
- cocur/slugify (locale `yo`), Intervention Image, barryvdh/laravel-dompdf
