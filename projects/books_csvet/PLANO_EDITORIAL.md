# Plano editorial: livros e monografias da Editora CSVET

Guia de referência do portal **books.csvet.com.br** (OMP). Atualizado em 2026-10-08.

Legenda: ✅ feito/decidido · ⬜ a fazer · ⚠️ depende de você

---

## 0. Decisões editoriais (fechadas)

| Tema | Decisão | Onde está |
|---|---|---|
| Nome público | **Editora CSVET** (razão social CSVET Editora, Cursos e Treinamentos mantida em contratos e rodapé) | `press.json` |
| Licença | **CC BY 4.0** como padrão (compatível com DOAB, OAPEN e financiadores); **CC BY-NC-ND 4.0** só por pedido justificado, registrado na declaração e no contrato | declaração dos autores, contrato, Sobre |
| Custos | **Sem taxa** de submissão, avaliação ou publicação digital. Serviços opcionais (revisão de linguagem, tradução, impressos) orçados por escrito antes do contrato, sem influência na decisão | instruções, contrato, ética |
| Avaliação | Interna (Conselho, 15 dias) + externa duplo-anônima: 2 pareceristas (obras inéditas, coletâneas, manuais) ou 1 (monografias, dissertações e teses já aprovadas em banca) | Sobre, instruções |
| Créditos em monografias | Autor = quem escreveu a versão em livro. Orientador(a), coorientador(a) e banca têm papéis próprios na plataforma: aparecem na folha de rosto, na nota de origem e na página do livro, **sem** entrar como autoria nos metadados. Orientador só é coautor se também escreveu/revisou criticamente o livro (COPE/ICMJE). Pareceristas anônimos durante a avaliação; após a publicação, citados no expediente com titulação, se autorizarem | página `publicar-monografia`, instruções §4, tema (`AcademicCredits.php`) |
| Coletâneas | Organizador(a) propõe; editora convida os autores pela plataforma; autores entram no livro com o capítulo já cadastrado; cada capítulo é avaliado por pares; capítulos dos organizadores têm pareceristas independentes | página `coletaneas`, instruções §5, `scripts/invite.php` e `assign-chapter-authors.php` |
| Ética | COPE + ICMJE; página prática por perfil, com correções, retratações e recursos (15 dias úteis) | página `etica` |
| Formatos | **16×23 cm** (padrão: livros, monografias, coletâneas) e **21×28 cm** (manuais e atlas com muitas imagens); digital em PDF (PDF/A), EPUB opcional, impresso sob demanda | `content/editora/producao/` |
| Identidade das capas | Capa por coleção, com cor própria, Source Serif 4 + Roboto e logo CSVET | `branding/build_cover.py` |
| ISBN e ficha | **CBL** (você atribui), um ISBN por formato | `content/editora/documentos/controle-isbn.xlsx` |
| Modelos de publicação | **Aberto** (padrão), **aberto + impresso**, **comercial** (e-book e impresso pagos, cessão ou licença exclusiva por prazo, direitos autorais de 20% no e-book e 10% no impresso) e **híbrido** (pago por 12 a 24 meses, depois aberto). Definido no contrato, após o aceite | página `modelos-de-publicacao`, contrato |
| E-book | PDF (do Word ou do LaTeX) e **EPUB 3** gerado por `branding/build_epub.py` (pandoc), com capa, créditos e fontes | seção 9 |
| Aceite dos termos | Termo de submissão obrigatório no envio, gravado no histórico com usuário, data, IP, navegador e SHA-256 do texto; contrato assinado (gov.br) anexado como "Contrato de edição assinado" | plugin `csvetEditorial`, `content/editora/termo-submissao.*.html` |
| Obras dos próprios editores | Editor responsável independente do Conselho, 2 pareceres externos, editores-autores fora da decisão; produção pode ser feita por vocês; nota editorial no livro; até 20% dos títulos/ano | página `etica`, seção 9 |
| Uso das obras | Avisos de direitos (Lei 9.610/1998, CP art. 184) no rodapé, nos livros e no EPUB; robôs de treinamento de IA bloqueados no `robots.txt`; obras comerciais com `tdm-reservation` e `noai` | página `etica`, tema |
| E-mails | Todos os e-mails da plataforma no layout da editora (cabeçalho com logo, rodapé com dados e aviso de confidencialidade) | plugin `csvetEditorial` |
| DOI | Fase 2: filiação à Crossref compartilhada com o journals (seção 7) | este plano |

## 1. Infraestrutura ✅

- ✅ OMP 3.5 LTS no ar em https://books.csvet.com.br, deploy por `git pull`
- ✅ HTTPS forçado, HSTS, código interno e documentos internos bloqueados (403)
- ✅ Tema CSVET, SEO completo (seção 8), páginas de conteúdo e menu "Publique"
- ⚠️ E-mail SMTP, cron e backup (veja `PROXIMOS_PASSOS.md`)

## 2. Fluxo de uma obra no OMP

1. **Submissão** pelo autor ou organizador, na coleção certa, com os contribuidores nos papéis certos (Autor, Organizador(a), Autor de capítulo, Orientador(a), Coorientador(a), Membro da banca examinadora).
2. **Avaliação interna** pelo Conselho Editorial (etapa própria do OMP).
3. **Avaliação externa** duplo-anônima. Em coletâneas, o editor escolhe os arquivos de cada capítulo para cada parecerista.
4. **Aceite** → contrato de edição (`content/editora/documentos/contrato-edicao-minuta.docx`) → edição de texto.
5. **Produção** no modelo de diagramação (16×23 ou 21×28), capa com `build_cover.py`, provas aos autores.
6. **ISBN e ficha catalográfica pela CBL**; cole a ficha no verso da folha de rosto; anote em `controle-isbn.xlsx`.
7. **Publicação**: formato de publicação com código **ISBN-13 (15)**, PDF, capa; nas coletâneas, capítulos com autores e páginas.
8. **Depósito legal** na Biblioteca Nacional (Lei nº 10.994/2004) e divulgação.

### Coletâneas (convite de autores de capítulo)

```bash
# 1. a planilha do organizador (aba Convidados) salva como CSV UTF-8 em ~/convites/livro-<id>.csv
php scripts/invite.php ~/convites/livro-<id>.csv --user=SEU_USUARIO            # simula
php scripts/invite.php ~/convites/livro-<id>.csv --user=SEU_USUARIO --send     # envia
# 2. depois dos aceites (pode repetir quantas vezes quiser; nada é duplicado)
php scripts/assign-chapter-authors.php ~/convites/livro-<id>.csv --submission=<id>                    # simula
php scripts/assign-chapter-authors.php ~/convites/livro-<id>.csv --submission=<id> --apply --notify   # inclui e avisa
```

O `<id>` é o número da submissão no painel. O autor de capítulo participa do livro desde a submissão e entrega o texto pela área de Discussões.

## 3. Conselho Editorial ⚠️

- ⬜ 6 a 12 doutores de instituições diferentes (no máximo 1/3 da mesma; ao menos 1 do exterior), cobrindo as categorias
- ⬜ Editores de coleção (opcional no início): Monografias e Coletâneas são as que mais vão precisar
- ⬜ Banco de pareceristas por área
- Ferramentas prontas: `content/editora/documentos/conselho-editorial-modelo.xlsx` → CSV → `php scripts/invite.php ... --send`; após o aceite, `termo-adesao-voluntario.docx`

## 4. Documentos ✅ (revisão jurídica ⚠️)

Gerados por `python branding/build_docs.py` (edite os textos lá e gere de novo):

| Público (download nas instruções) | Interno (`content/editora/documentos/`) |
|---|---|
| proposta de livro · proposta de coletânea · planilha de convites de capítulos | minuta de contrato de edição |
| modelo de livro · modelo de capítulo | termo de adesão ao serviço voluntário |
| declaração dos autores · termo de autor de capítulo · anuência do orientador | planilha do Conselho · controle de ISBN |

- ⚠️ Revisão por advogado do contrato, da declaração, do termo de capítulo e do termo voluntário antes do primeiro uso.

## 5. ISBN e ficha catalográfica (CBL) ✅

- Um ISBN por formato (PDF, EPUB, impresso); nova edição = novo ISBN; capítulo não tem ISBN.
- Ficha pedida com o arquivo final; nas obras derivadas de trabalho acadêmico, a nota de origem vai no verso da folha de rosto.

## 6. Depósito legal ⬜

- Enviar cada obra à Biblioteca Nacional (Lei nº 10.994/2004), inclusive as digitais.

## 7. DOI (fase 2) ⬜

- Filiação à Crossref (um prefixo para a editora inteira, journals e books).
- No OMP: *Distribuição › DOIs* para livros e capítulos; o registro de livros é pelo formulário web da Crossref (o OMP 3.5 não traz plugin de depósito para livros).

## 8. E-books, impressos e vendas

**Produção do e-book (sem custo):**
1. Manuscrito final aprovado em Word (modelo da editora) ou LaTeX (`content/editora/producao/latex/`).
2. PDF: exportar do Word como PDF/A (diagramação 16×23) ou compilar o LaTeX (`latexmk livro.tex`).
3. EPUB: `python branding/build_epub.py --source final.docx --title ... --authors ... --isbn ... --cover capa.jpg --model aberto|comercial --out livro.epub` e conferir no Calibre/Thorium (validar com EPUBCheck).
4. No OMP, um formato de publicação por arquivo (PDF, EPUB, impresso), cada um com o seu ISBN; nas coletâneas, também os PDFs de cada capítulo (arquivos ligados ao capítulo, com DOI do capítulo).
5. **Obra comercial**: o PDF à venda deve ser **PDF/A-1b** (Word: *Salvar como PDF › Opções › Compatível com ISO 19005-1*; LaTeX: `pdfx` com `\pdfobjcompresslevel=0` e `\pdfminorversion=4`), porque cada download recebe a marca d'água do comprador (plugin csvetEditorial, `Watermark.php`) e a biblioteca gratuita não lê PDF com compressão moderna. Antes de pôr à venda: `php scripts/watermark.php --test=livro.pdf` (e `--test=livro.epub`) e conferir o arquivo `-marcado`. Exemplar vazado: `php scripts/watermark.php --verify=CÓDIGO` mostra comprador e pedido.

**Venda direta no portal (OMP):** em cada arquivo do formato, *Disponibilidade*: aberto (gratuito) ou venda direta (com preço). Pagamento por **Pix** (plugin de pagamento manual: o leitor recebe as instruções e envia o comprovante; a editora recebe um e-mail, confere o Pix no extrato e libera o download com `php scripts/payments.php --approve=ID`, que também avisa o comprador; `php scripts/payments.php` lista as pendentes e `--paid` as vendas) ou **PayPal** (cartão, liberação automática; exige conta PayPal Business e credenciais no OMP). Ativo, com a chave Pix CNPJ 32.645.724/0001-89.

**Sem pagar ninguém (ideias):**
- **Impressão sob demanda** (UICLAP, Clube de Autores, Amazon KDP): a gráfica imprime e envia a cada pedido, sem estoque nem custo antecipado; o link vai como formato "impresso" com URL remota na página do livro.
- **Lojas digitais** (Amazon Kindle, Google Play Livros, Apple Books): mesmas regras de ISBN; link na página do livro.
- **Plataformas de produto digital** (Hotmart, Eduzz, Kiwify): Pix, boleto e cartão com entrega automática, cobrando só comissão por venda; boas para e-books comerciais e pacotes com cursos da CSVET.
- **Pré-venda/financiamento coletivo** (Catarse) para obras comerciais grandes.
- **Edição patrocinada** por programa de pós-graduação, evento ou instituição, que custeia a edição aberta (com declaração de financiamento e sem interferência na avaliação).
- **Capítulos avulsos** de coletâneas comerciais e **pacotes** (livro + curso) com a CSVET Cursos.

## 9. Obras dos próprios editores (sem depender de terceiros pagos)

1. Vocês submetem como qualquer autor. Na submissão, em *Participantes*, retirem-se como editores e designem um membro do Conselho Editorial sem vínculo com a obra como editor responsável.
2. O editor responsável escolhe e convida os pareceristas (mínimo 2 externos), lê os pareceres e registra a decisão. Vocês não abrem a etapa de avaliação dessa obra.
3. Aprovada a obra, a produção (revisão técnica, diagramação, capa, EPUB, ISBN) pode ser feita por vocês mesmos.
4. A nota editorial (já prevista nos modelos Word e LaTeX) informa que os autores integram a editora e quem conduziu a avaliação.
5. Limite: até 20% dos títulos do ano. Tudo fica registrado na plataforma, o que vale como comprovação para CAPES, SciELO e DOAB.

## 10. Padrão das editoras universitárias (referência)

Levantamento das práticas da Editora UFPR, Editora Unesp, EDUFBA, critérios da CAPES para livros e do SciELO Livros:

| Prática | Editora CSVET |
|---|---|
| Originais sem identificação de autoria; parecer duplo-cego; decisão final do Conselho Editorial | ✅ |
| Trabalhos acadêmicos só se adaptados ao formato de livro | ✅ (instruções §3 e página de monografias) |
| Fluxo contínuo, com limite anual de títulos quando a capacidade for atingida | ✅ fluxo contínuo; ⬜ definir capacidade anual |
| Checagem técnica de imagens e direitos autorais antes da preparação do texto | ✅ triagem e termo de submissão |
| CAPES: ISBN, ficha catalográfica, **mínimo de 50 páginas**, conselho editorial, informações sobre os autores, **carta de revisão por pares**, **índice remissivo** | ✅ (declaração em `content/editora/documentos/declaracao-avaliacao-pares.docx`, "Sobre os autores" e índice nos modelos) |
| SciELO Livros: no mínimo 10 títulos aprovados para admitir a editora; conselho com diversidade institucional e geográfica | ⬜ meta para candidatura |
| Filiação à **ABEU** (Associação Brasileira das Editoras Universitárias) e catálogo/distribuição | ⬜ avaliar quando houver catálogo |

## 11. SEO e visibilidade

✅ No tema (`plugins/themes/csvet/SeoHeaders.php`):
- títulos com palavras-chave ("Editora CSVET | Livros e monografias em Medicina Veterinária e Zootecnia"), descrições por página
- dados estruturados (JSON-LD): Organization (mesma entidade de csvet.com.br, ligada ao journals), WebSite com caixa de busca, Book (ISBN por formato, coleção, assuntos, oferta gratuita, autores e, à parte, orientação/banca como contribuidores), Chapter, BookSeries, CollectionPage, FAQPage (páginas de ética, monografias e coletâneas) e BreadcrumbList em todas as páginas internas
- Open Graph e Twitter com capa do livro; `book:isbn`, `book:author`; robots com prévia grande de imagem
- sitemap com coleções, categorias, livros e páginas de conteúdo; hreflang pt/en/es (nativo do OMP)
- Google Acadêmico e Dublin Core sem orientador/banca como autores

⬜ Fora do site:
- Search Console: propriedade de domínio `csvet.com.br` e sitemap `https://books.csvet.com.br/index/pt_BR/sitemap`
- Google Play Livros (Programa de Parceiros), DOAB/OAPEN após as primeiras obras, Bing Webmaster Tools
- Lattes: orientar os autores a cadastrar com ISBN e editora "Editora CSVET"
- CAPES (avaliação de livros): manter os pareceres arquivados no OMP
