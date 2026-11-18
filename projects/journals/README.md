# journals.csvet.com.br

Portal de periódicos científicos da **CSVET Editora**, baseado no [Open Journal Systems (OJS)](https://pkp.sfu.ca/software/ojs/) 3.5 LTS.

| Revista | Caminho | Idiomas | Status |
|---|---|---|---|
| Neotropical Veterinary and Animal Science (NVAS): **escopo amplo** em medicina veterinária e zootecnia (artigos originais, relatos de caso, revisões, imagens clínicas, métodos, dados) | `/nvas` (antigo `/bjvr`, redirecionado) | en (principal), pt_BR | em configuração |
| Revista de relatos de caso em medicina veterinária: **somente relatos de caso** | a definir | — | planejada |

Próximos passos editoriais (ISSN, Crossref, indexação): veja **[PLANO_EDITORIAL.md](PLANO_EDITORIAL.md)**.

## Estrutura

A raiz do repositório **é** a raiz do site, no mesmo padrão do profatilio. O código do OJS **não é versionado**: ele é instalado por `scripts/install-ojs.sh`, que cumpre aqui o papel do `composer install` no Laravel. O `.gitignore` ignora tudo e libera só os arquivos do projeto:

```
.htaccess                  HTTPS, bloqueio do código interno, URLs limpas, cache (produção e local)
robots.txt                 SEO + sitemap
OJS_VERSION                versão do OJS instalada pelo script (fonte única)
scripts/install-ojs.sh     baixa/atualiza o OJS e cria os .htaccess de proteção (servidor e local)
scripts/setup.ps1          ambiente local completo (Docker) em um comando
content/nvas/              textos e configuração da revista (journal.json, instruções aos autores en/pt_BR, convites, corpo editorial)
branding/                  logo: gerador (build_logo.py) + fontes Source Serif 4 e Roboto
docker-compose.yml, docker/  ambiente local (OJS oficial + MySQL 8.0)
PLANO_EDITORIAL.md         roteiro para ISSN, DOI e indexação
```

Ficam fora do git: o código do OJS, o `config.inc.php` (senhas e `app_key`), o `public/` (uploads), o `.env` e o cache.

## Produção (HostGator / cPanel, via SSH)

**Pastas na conta:**

```
~/                                PRIVADO (fora da web)
├── journal_files/                arquivos privados do OJS: submissões, pareceres (files_dir)
├── backups/                      dumps do MySQL
└── journals.csvet.com.br/        PÚBLICO: raiz do subdomínio = este repositório
    ├── .htaccess                 versionado: libera só index.php, assets e public/
    ├── config.inc.php            criado pelo instalador; bloqueado pela web
    └── (código do OJS)           instalado por scripts/install-ojs.sh
```

### Primeira instalação

1. **cPanel:** o subdomínio `journals.csvet.com.br` já existe e aponta para `~/journals.csvet.com.br`; **não altere** o subdomínio. Só faça o seguinte:
   - crie o banco MySQL e o usuário (com o prefixo da conta, ex.: `atilio_ojs`), com todos os privilégios e collation `utf8mb4_unicode_ci`;
   - no **MultiPHP Manager**, confirme que o PHP de `journals.csvet.com.br` é **8.2 ou superior** (de preferência 8.3);
   - no **MultiPHP INI Editor**, use `memory_limit` 256M e `upload_max_filesize`/`post_max_size` 64M;
   - confirme que o SSL (AutoSSL) cobre `journals.csvet.com.br`.
2. **Acesso ao GitHub.** O repositório é **privado**. Use o mesmo método do profatilio ou crie uma *deploy key* (só leitura e só para este repositório):
   ```bash
   ssh-keygen -t ed25519 -f ~/.ssh/journals_deploy -N "" -C "deploy journals"
   cat ~/.ssh/journals_deploy.pub     # copie e cole em GitHub > calefin/journals > Settings > Deploy keys (sem "write access")
   printf 'Host github-journals\n  HostName github.com\n  User git\n  IdentityFile ~/.ssh/journals_deploy\n  IdentitiesOnly yes\n' >> ~/.ssh/config
   chmod 600 ~/.ssh/config
   ssh -T github-journals             # deve responder "Hi calefin/journals! ..."
   ```
3. **SSH.** A pasta já existe, então use `git init` em vez de `git clone`:
   ```bash
   cd ~/journals.csvet.com.br
   ls -la                       # veja o que já existe; mova ou apague o que não for necessário (ex.: well-known.zip)
   git init -b main
   git remote add origin github-journals:calefin/journals.git
   git fetch origin
   git checkout -f -t origin/main   # traz os arquivos do repositório (sobrescreve .htaccess/robots.txt da pasta)
   mkdir -p ~/journal_files ~/backups && chmod 750 ~/journal_files && chmod 700 ~/backups
   bash scripts/check-server.sh     # tudo deve dar OK
   bash scripts/install-ojs.sh      # baixa e instala o OJS (≈120 MB)
   ```
4. Abra `https://journals.csvet.com.br` e conclua o instalador:
   - idioma principal **English**; adicionais **Português (Brasil)** e **Español**; fuso **America/Sao_Paulo**
   - diretório de arquivos: `/home/<usuario>/journal_files`
   - banco: **MySQLi**, host `localhost`, com o banco e o usuário do passo 1
5. Ajuste o `config.inc.php` (o script faz backup em `~/backups` antes):
   ```bash
   bash scripts/configure-production.sh
   ```
   Ele define `base_url` com https, `restful_urls`, `allowed_hosts`, `force_ssl`, o fuso horário e desliga o beacon. Depois edite à mão o bloco `[email]` com o SMTP da conta de e-mail criada no cPanel (ex.: `editorial@csvet.com.br`):
   ```ini
   default = smtp
   smtp = On
   smtp_server = mail.csvet.com.br
   smtp_port = 465
   smtp_auth = ssl
   smtp_username = editorial@csvet.com.br
   smtp_password = "SENHA-DO-EMAIL"
   allow_envelope_sender = On
   default_envelope_sender = editorial@csvet.com.br
   force_default_envelope_sender = On
   force_dmarc_compliant_from = On
   ```
   Descomente as linhas (tire o `;`). O servidor e a porta SMTP exatos aparecem em cPanel > Contas de e-mail > Conectar dispositivos.
   Teste o envio com a recuperação de senha do próprio admin.
6. **Cron** (cPanel > Trabalhos Cron, a cada hora):
   `0 * * * * /usr/local/bin/php /home/<usuario>/journals.csvet.com.br/tools/runScheduledTasks.php >/dev/null 2>&1`

### Se o cPanel mexer no `.htaccess`

Ao mudar a versão do PHP, o MultiPHP Manager costuma inserir um bloco `# php -- BEGIN cPanel-generated handler` no `.htaccess`. Como o arquivo é versionado, o `git pull` passa a reclamar. Rode `git diff .htaccess` no servidor e me mande o bloco, que ele entra no `.htaccess` versionado. Até lá, use `git stash && git pull && git stash pop`.

### Deploy do dia a dia

```bash
cd ~/journals.csvet.com.br
git pull
```

Isso atualiza `.htaccess`, `robots.txt`, textos e scripts. O OJS em si só muda quando `OJS_VERSION` muda.

### Atualizar a versão do OJS

1. Localmente: mude `OJS_VERSION` (e `OJS_VERSION` no `.env`, que é a imagem Docker), rode `bash scripts/install-ojs.sh`, depois `docker compose exec app php tools/upgrade.php upgrade`, teste e faça o commit.
2. No servidor: **faça backup** (banco + `journal_files` + `public` + `config.inc.php`) e rode:
   ```bash
   git pull
   bash scripts/install-ojs.sh
   php tools/upgrade.php check && php tools/upgrade.php upgrade
   ```

### Segurança, já testada localmente

- HTTPS forçado (301) e `www.` redirecionado para o host canônico
- 403 em `config.inc.php`, `.git`, `.env`, pastas internas (`classes/`, `tools/`, `lib/pkp/classes/`…), pastas do projeto (`scripts/`, `docker/`, `content/`, `branding/`), `.sql`, `.zip`, `.md`, `.tpl` etc.
- Só o `index.php` executa PHP; `public/` não serve scripts
- Segunda camada: `.htaccess` de negação em cada pasta interna, criados pelo `install-ojs.sh`, como o `protect.php` do profatilio
- O OJS recusa hosts fora do `allowed_hosts`

### Backup

Faça backup de quatro itens: o banco MySQL, `~/journal_files/`, `public/` e `config.inc.php`. O `app_key` está no `config.inc.php`; sem ele, dados criptografados ficam ilegíveis.

## Desenvolvimento local (Windows + Docker Desktop)

```powershell
powershell -ExecutionPolicy Bypass -File scripts\setup.ps1
```

O script cria o `.env` com senhas aleatórias, instala o código do OJS (via `install-ojs.sh`), sobe o OJS e o MySQL 8.0 e roda o instalador. Acesse http://localhost:8080 com o usuário `admin`; a senha está em `OJS_ADMIN_PASSWORD` no `.env`.

```powershell
docker compose logs -f app       # logs do Apache/PHP
docker compose down              # para (mantém os dados)
docker compose exec app php tools/runScheduledTasks.php
```

O MySQL fica em `127.0.0.1:3307`. Para regenerar o logo após mudar o nome da revista, edite `NAME_TOP`/`NAME_MAIN`/`MONOGRAM` em `branding/build_logo.py` e rode `python branding/build_logo.py`.
