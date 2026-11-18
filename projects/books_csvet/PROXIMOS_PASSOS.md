# Próximos passos: books.csvet.com.br

Atualizado em 2026-10-09. Editora: **Editora CSVET**, `https://books.csvet.com.br/editora` (a raiz redireciona para ela).
Comandos rodam no **Terminal do cPanel**, dentro de `~/books.csvet.com.br`. Detalhes no [README](README.md) e no [plano editorial](PLANO_EDITORIAL.md).

## 0. Deploy pendente (2026-10-09)

```bash
cd ~/books.csvet.com.br
git pull
php scripts/configure-press.php --dry-run
php scripts/configure-press.php
```

- Plugin `generic/csvetLocaleUrl`: as páginas em inglês (`/editora/en/...`) deixam de entrar em loop de redirecionamento para o Google e as prévias de redes sociais. Conferir: `curl -sI https://books.csvet.com.br/editora/en | head -1` deve dar `200`.
- Papéis **Editor-chefe** e **Coeditor-chefe** no topo da Equipe Editorial; perfil do editor-chefe gravado (nome, afiliação, Lattes, biografia). Se a saída disser "usuário calefi@csvet.com.br não encontrado", ajuste o e-mail em `masthead` no `press.json`.
- Painel no celular (menu em gaveta pelo botão ☰) e app instalável (PWA "CSVET Livros"): `manifest.webmanifest`, `sw.js`, `offline.html`.
- Normas de e-book (Instruções aos Autores, seção 8, e Modelos de publicação): várias versões (PDF completo, EPUB, PDF por capítulo, leitura on-line) e regras de acessibilidade.
- **Marca d'água nos e-books vendidos** (plugin csvetEditorial): nome, e-mail, pedido, data e código do comprador no rodapé do PDF e nos créditos do EPUB. Antes de pôr uma obra à venda: `php scripts/watermark.php --test=livro.pdf`; o PDF à venda deve ser PDF/A-1b. Exemplar vazado: `php scripts/watermark.php --verify=CÓDIGO`.
- DOI: prefixo **10.54547** (ABEC, o mesmo da revista) com sufixos `csvet.book.%m` (livro), `.c%c` (capítulo), `.f%f` (formato), `.s%s` (arquivo). A revista usa `nvas.`, então não há colisão. DOI segue **desligado** (`enableDois: false`) até haver ISBN e o depósito na Crossref.

## 1. Deploy desta versão ✅ (feito em 2026-10-08, até o commit db094ee)

```bash
cd ~/books.csvet.com.br
git pull
php scripts/configure-press.php
rm -f cache/*.css
```

Isso aplica:
- o menu **Publique**, com Como submeter, TCC/dissertação/tese, Organize uma coletânea e Ética e COPE;
- as páginas `/editora/publicar-monografia`, `/editora/coletaneas` e `/editora/etica`;
- os papéis Organizador(a), Orientador(a), Coorientador(a) e Membro da banca examinadora;
- o autor de capítulo participando desde a submissão;
- os modelos para download nas instruções;
- a citação sem orientação nem banca;
- o SEO novo;
- o plugin **csvetEditorial** (registrado e ativado pelo próprio script): termo de submissão obrigatório com registro de aceite e e-mails em HTML com a identidade da editora;
- as páginas `/editora/como-submeter` e `/editora/modelos-de-publicacao`, os avisos de direitos no rodapé e o bloqueio de robôs de IA no `robots.txt`;
- os tipos de arquivo "Contrato de edição assinado" e "Declarações e termos assinados".

Depois, abra o site com **Ctrl+F5**. Teste o termo fazendo uma submissão de teste com um usuário autor e veja o registro em *Histórico de atividades* da submissão.

## 2. Infraestrutura

- [ ] **E-mail** (crítico para os convites):
  `php scripts/configure-email.php noreply@books.csvet.com.br --server=books.csvet.com.br --port=465 --auth=ssl` e `php scripts/diagnose.php --mail=ascalefi@gmail.com`
- [ ] **Cron** a cada hora (sem parênteses, linha a linha):
  ```bash
  crontab -l > ~/cron.txt
  echo "0 * * * * cd $HOME/books.csvet.com.br && php tools/runScheduledTasks.php >/dev/null 2>&1" >> ~/cron.txt
  crontab ~/cron.txt && crontab -l && rm ~/cron.txt
  ```
- [ ] **Convites pela API** (uma vez): definir `api_key_secret` no `config.inc.php` e gerar a chave em *Perfil › Chave de API*:
  ```bash
  SECRET=$(openssl rand -hex 32); sed -i "s/^api_key_secret = \"\"$/api_key_secret = \"$SECRET\"/" config.inc.php; unset SECRET; grep -c '^api_key_secret = ""' config.inc.php   # deve dar 0
  ```
- [ ] **Backup**: banco `atilio12_omp`, `~/books_files`, `public/` e `config.inc.php`.
- [ ] **ORCID**: mesma Public API do journals, com `https://books.csvet.com.br` nas Redirect URIs.
- [ ] **Search Console**: propriedade de domínio `csvet.com.br` (TXT no DNS) e sitemap `https://books.csvet.com.br/index/pt_BR/sitemap`.

## 3. Editorial

- [ ] **Conselho Editorial**:
  1. Preencha uma cópia de `content/editora/documentos/conselho-editorial-modelo.xlsx` (fora do Git).
  2. Salve a aba Convidados como CSV em `~/convites/conselho.csv`.
  3. Rode `php scripts/invite.php ~/convites/conselho.csv --user=SEU_USUARIO --send`.
  4. Após cada aceite, envie o termo de adesão ao serviço voluntário.
- [ ] **Revisão jurídica**: termo de submissão (`content/editora/termo-submissao.pt_BR.html`), contrato de edição (aberto/comercial/híbrido), declaração dos autores, termo de autor de capítulo e termo voluntário.
- [ ] **Vendas**: decidir os percentuais de direitos autorais do contrato (sugestão: 20% e-book, 10% impresso) e o canal de venda (Pix no portal, PayPal, Hotmart, lojas). Pix no portal já ativo (chave CNPJ 32.645.724/0001-89).
- [ ] **Impressão sob demanda**: criar conta gratuita na UICLAP ou no Clube de Autores para os impressos.
- [x] **Fontes de diagramação**: Source Serif 4 e Roboto instaladas no computador de diagramação (cópias em `branding/fonts/` para outras máquinas).
- [ ] Abrir os modelos `.docx` no Word e conferir a paginação (o modelo LaTeX foi compilado e conferido: `content/editora/producao/latex/exemplo-16x23.pdf`).

## 4. Mercado Pago (decidido em 2026-10-08)

- [ ] Criar a aplicação no painel de desenvolvedores do Mercado Pago (*Suas integrações › Criar aplicação › Checkout Pro*) e enviar o **Access Token de teste** para o desenvolvimento.
- [ ] Desenvolvimento do plugin de pagamento (Checkout Pro: Pix, cartão e boleto; notificação automática; download liberado na hora; e-mail de confirmação), testado no ambiente local.
- [ ] Ativação no servidor com o **Access Token de produção**, gravado direto no servidor (nunca no chat nem no Git).
- Até lá, as compras por Pix são liberadas manualmente com `php scripts/payments.php --approve=ID`.

## 5. Primeiras obras

- [ ] 1 a 3 obras iniciais (sugestão: uma coletânea organizada por membro do Conselho e uma monografia).
- [ ] Para cada obra:
  - capa com `python branding/build_cover.py ...`;
  - ISBN e ficha pela CBL (um ISBN por formato);
  - formato de publicação com **ISBN-13 (15)**;
  - nota de origem, nas obras acadêmicas;
  - depósito legal na Biblioteca Nacional.
- [ ] Ao publicar uma obra com orientação/banca, confira em *Publicação › Permissões e divulgação* que o titular dos direitos traz só os autores. O tema corrige a exibição, mas o dado gravado deve ficar certo.

## Referência rápida

| O quê | Onde |
|---|---|
| Textos, políticas, coleções, categorias, papéis, páginas, menu | `content/editora/press.json` → `php scripts/configure-press.php` |
| Páginas de conteúdo (ética, monografias, coletâneas) | `content/editora/pages/*.html` |
| Instruções aos autores | `content/editora/author-guidelines.pt_BR.html` e `.en.html` |
| Convites (Conselho, editores, pareceristas, autores de capítulo) | `content/editora/invitations.json` + `scripts/invite.php` |
| Autores de capítulo dentro do livro | `scripts/assign-chapter-authors.php` |
| Modelos e documentos | `python branding/build_docs.py` → `plugins/themes/csvet/docs/` (públicos) e `content/editora/documentos/` e `content/editora/producao/` (internos) |
| Capas | `python branding/build_cover.py --help` |
| Tema, SEO, créditos acadêmicos | `plugins/themes/csvet/` |
| Compras por Pix: pendentes, aprovar, descartar, vendas | `php scripts/payments.php` (`--approve=ID`, `--reject=ID`, `--paid`) |
| Diagnóstico de e-mail, fila, ROR e ORCID | `php scripts/diagnose.php` |
