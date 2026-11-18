# books.csvet.com.br

Portal de livros e monografias da **Editora CSVET**, baseado no [Open Monograph Press (OMP)](https://pkp.sfu.ca/software/omp/) 3.5 LTS. É o irmão do [journals.csvet.com.br](https://github.com/calefin/journals) (OJS) e segue a mesma estrutura, o mesmo deploy e a mesma identidade visual.

| Editora | Caminho | Idiomas | Status |
|---|---|---|---|
| Editora CSVET: livros técnico-científicos, coletâneas, manuais, monografias (TCC, especialização, residência), dissertações, teses e anais | `/editora` (a raiz `books.csvet.com.br` redireciona para ela) | pt_BR (principal), en, es | em configuração |

Coleções (séries do OMP): Livros Técnico-Científicos, Coletâneas, Manuais e Guias Práticos, Monografias, Dissertações e Teses, Anais e Resumos de Eventos. Páginas para o público: [Publicar TCC, dissertação ou tese](https://books.csvet.com.br/editora/publicar-monografia), [Organize uma coletânea](https://books.csvet.com.br/editora/coletaneas) e [Ética e integridade (COPE)](https://books.csvet.com.br/editora/etica). Próximos passos editoriais (ISBN, ficha catalográfica, depósito legal, DOI, indexação): veja **[PLANO_EDITORIAL.md](PLANO_EDITORIAL.md)** e **[PROXIMOS_PASSOS.md](PROXIMOS_PASSOS.md)**.

## Estrutura

A raiz do repositório **é** a raiz do site. O código do OMP **não é versionado**: ele é instalado por `scripts/install-omp.sh` (o equivalente ao `composer install`). O `.gitignore` ignora tudo e libera só os arquivos do projeto:

```
.htaccess                    HTTPS, bloqueio do código interno, URLs limpas, cache (produção e local)
robots.txt                   SEO + sitemap
OMP_VERSION                  versão do OMP instalada pelo script (fonte única)
scripts/install-omp.sh       baixa/atualiza o OMP e cria os .htaccess de proteção (servidor e local)
scripts/setup.ps1            ambiente local completo (Docker) em um comando
scripts/configure-press.php  aplica content/editora/press.json: textos, políticas, coleções, categorias, papéis,
                             páginas (ética, monografias, coletâneas), menu Publique, imagens
scripts/invite.php           convites em lote pela plataforma: Conselho, editores de coleção, pareceristas, autores de capítulo
scripts/assign-chapter-authors.php  inclui no livro os autores de capítulo que aceitaram, com o capítulo cadastrado
scripts/payments.php         compras por Pix: lista as pendentes, libera o download (--approve) e avisa o comprador
scripts/configure-email.php  SMTP autenticado no config.inc.php (testa o login antes de gravar)
scripts/diagnose.php         diagnóstico de e-mail, fila de tarefas, ROR e ORCID
content/editora/             press.json, instruções aos autores, páginas, convites, imagens
  documentos/                contrato (minuta), termo voluntário, planilha do Conselho, controle de ISBN (internos, 403 na web)
  producao/                  diagramação: Word 16x23 e 21x28 cm e LaTeX (latex/, classe csvet-livro.cls)
plugins/themes/csvet/        tema CSVET: identidade visual, SEO (JSON-LD), créditos acadêmicos, modelos públicos em docs/
plugins/generic/csvetEditorial/  termo de submissão obrigatório com registro de aceite, créditos fora da autoria, e-mails em HTML
branding/                    logos, fontes e geradores: build_images.py (logo/OG), build_docs.py (documentos),
                             build_cover.py (capas), build_epub.py (e-book EPUB 3 via pandoc)
docker-compose.yml, docker/  ambiente local (OMP oficial + MySQL 8.0)
```

Ficam fora do git: o código do OMP, o `config.inc.php` (senhas e `app_key`), o `public/` (uploads), o `.env` e o cache.

## Produção (HostGator / cPanel, via SSH)

Mesmo servidor do journals. **Pastas na conta:**

```
~/                                PRIVADO (fora da web)
├── books_files/                  arquivos privados do OMP: originais, pareceres (files_dir)
├── backups/                      dumps do MySQL e cópias do config.inc.php (compartilhado com o journals)
└── books.csvet.com.br/           PÚBLICO: raiz do subdomínio = este repositório
    ├── .htaccess                 versionado: libera só index.php, assets e public/
    ├── config.inc.php            criado pelo instalador; bloqueado pela web
    └── (código do OMP)           instalado por scripts/install-omp.sh
```

### Primeira instalação

1. **cPanel:** o subdomínio `books.csvet.com.br` já existe e aponta para `~/books.csvet.com.br`; **não altere** o subdomínio. Só faça o seguinte:
   - banco MySQL **`atilio12_omp`** e usuário **`atilio12_ompadmin`** (próprios do OMP; **não reaproveite o banco do OJS**). Em *Bancos de dados MySQL*, adicione o usuário ao banco com **todos os privilégios**; a collation deve ser `utf8mb4_unicode_ci`;
   - no **MultiPHP Manager**, confirme que o PHP de `books.csvet.com.br` é **8.2 ou superior** (o mesmo do journals);
   - no **MultiPHP INI Editor**, use `memory_limit` 256M e `upload_max_filesize`/`post_max_size` 64M (livros com imagens são arquivos grandes; se precisar, suba para 128M);
   - confirme que o SSL (AutoSSL) cobre `books.csvet.com.br`.
2. **Acesso ao GitHub.** Se o repositório for **privado**, crie uma *deploy key* só para ele (a do journals não serve: o GitHub não aceita a mesma chave em dois repositórios):
   ```bash
   ssh-keygen -t ed25519 -f ~/.ssh/books_deploy -N "" -C "deploy books"
   cat ~/.ssh/books_deploy.pub     # cole em GitHub > calefin/books_csvet > Settings > Deploy keys (sem "write access")
   printf 'Host github-books\n  HostName github.com\n  User git\n  IdentityFile ~/.ssh/books_deploy\n  IdentitiesOnly yes\n' >> ~/.ssh/config
   chmod 600 ~/.ssh/config
   ssh -T github-books             # deve responder "Hi calefin/books_csvet! ..."
   ```
   (Se o repositório for público, use `https://github.com/calefin/books_csvet.git` no lugar de `github-books:calefin/books_csvet.git` abaixo.)
3. **SSH.** A pasta já existe, então use `git init` em vez de `git clone`:
   ```bash
   cd ~/books.csvet.com.br
   ls -la                       # veja o que já existe; mova ou apague o que não for necessário
   git init -b main
   git remote add origin github-books:calefin/books_csvet.git
   git fetch origin
   git checkout -f -t origin/main   # traz os arquivos do repositório (sobrescreve .htaccess/robots.txt da pasta)
   mkdir -p ~/books_files ~/backups && chmod 750 ~/books_files && chmod 700 ~/backups
   bash scripts/check-server.sh     # tudo deve dar OK
   bash scripts/install-omp.sh      # baixa e instala o OMP (≈110 MB)
   ```
4. Abra `https://books.csvet.com.br` e conclua o instalador:
   - idioma principal **Português (Brasil)**; adicionais **English** e **Español**; fuso **America/Sao_Paulo**
   - diretório de arquivos: `/home2/<usuario>/books_files` (o caminho completo aparece em `echo ~/books_files`)
   - banco: **MySQLi**, host `localhost`, usuário `atilio12_ompadmin`, senha definida no cPanel, banco `atilio12_omp`
   - desmarque o envio de estatísticas (beacon)
5. Ajuste o `config.inc.php` (o script faz backup em `~/backups` antes):
   ```bash
   bash scripts/configure-production.sh
   ```
   Ele define `base_url` com https, `restful_urls`, `allowed_hosts`, `force_ssl`, o fuso horário e desliga o beacon.
6. **Editora e identidade visual** (cria a editora `/editora`, aplica textos, políticas, coleções, categorias, logo, favicon e o tema CSVET, e faz a raiz redirecionar para a editora):
   ```bash
   php scripts/configure-press.php --create
   ```
   Depois, entre como admin e confira em *Configurações › Editora*.
7. **E-mail (SMTP).** Use a mesma conta do journals ou crie `noreply@books.csvet.com.br` no cPanel (com SPF e DKIM do subdomínio):
   ```bash
   php scripts/configure-email.php noreply@books.csvet.com.br --server=books.csvet.com.br --port=465 --auth=ssl
   php scripts/diagnose.php --mail=seu-email@gmail.com
   ```
8. **Cron** (cPanel > Trabalhos Cron, a cada hora):
   `0 * * * * cd /home2/<usuario>/books.csvet.com.br && php tools/runScheduledTasks.php >/dev/null 2>&1`

### Se o cPanel mexer no `.htaccess`

Ao mudar a versão do PHP, o MultiPHP Manager costuma inserir um bloco `# php -- BEGIN cPanel-generated handler` no `.htaccess`. Como o arquivo é versionado, o `git pull` passa a reclamar. Rode `git diff .htaccess` no servidor e traga o bloco para o `.htaccess` versionado. Até lá, use `git stash && git pull && git stash pop`.

### Deploy do dia a dia

```bash
cd ~/books.csvet.com.br
git pull
php scripts/configure-press.php     # só se content/editora/ mudou
```

O OMP em si só muda quando `OMP_VERSION` muda.

### Atualizar a versão do OMP

1. Localmente: mude `OMP_VERSION` (e `OMP_VERSION` no `.env`, que é a imagem Docker), rode `bash scripts/install-omp.sh`, depois `docker compose exec app php tools/upgrade.php upgrade`, teste e faça o commit.
2. No servidor: **faça backup** (banco + `books_files` + `public` + `config.inc.php`) e rode:
   ```bash
   git pull
   bash scripts/install-omp.sh
   php tools/upgrade.php check && php tools/upgrade.php upgrade
   ```

### Segurança, já testada localmente

- HTTPS forçado (301) e `www.` redirecionado para o host canônico
- 403 em `config.inc.php`, `.git`, `.env`, pastas internas (`classes/`, `tools/`, `lib/pkp/classes/`…), pastas do projeto (`scripts/`, `docker/`, `content/`, `branding/`), `.sql`, `.zip`, `.md`, `.tpl` etc.
- Só o `index.php` executa PHP; `public/` não serve scripts
- Segunda camada: `.htaccess` de negação em cada pasta interna, criados pelo `install-omp.sh`
- O OMP recusa hosts fora do `allowed_hosts`

### Backup

Faça backup de quatro itens: o banco MySQL, `~/books_files/`, `public/` e `config.inc.php`. O `app_key` está no `config.inc.php`; sem ele, dados criptografados ficam ilegíveis.

## Desenvolvimento local (Windows + Docker Desktop)

```powershell
powershell -ExecutionPolicy Bypass -File scripts\setup.ps1
docker compose exec app php scripts/configure-press.php --create
```

O script cria o `.env` com senhas aleatórias, instala o código do OMP (via `install-omp.sh`), sobe o OMP e o MySQL 8.0 e roda o instalador. Acesse http://localhost:8081 com o usuário `admin`; a senha está em `OMP_ADMIN_PASSWORD` no `.env`. As portas (8081 e 3308) são diferentes das do journals (8080 e 3307), então os dois ambientes rodam juntos.

```powershell
docker compose logs -f app       # logs do Apache/PHP
docker compose down              # para (mantém os dados)
docker compose exec app php tools/runScheduledTasks.php
```

Para regenerar as imagens (logo, favicon, imagem de compartilhamento), edite `OG_*` em `branding/build_images.py` e rode `python branding/build_images.py` (requer Pillow). Depois rode `configure-press.php` de novo: as imagens ganham nome novo (hash) e o cache antigo dos navegadores e redes sociais deixa de valer.
