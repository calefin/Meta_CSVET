# Plano editorial: do lançamento ao ISSN, DOI e indexação

Guia de referência para a **Neotropical Veterinary and Animal Science (NVAS)**, revista de escopo amplo (artigos originais, relatos de caso, revisões e demais tipos), que substituiu em 2026-10-08 o projeto do Brazilian Journal of Veterinary Reviews (só revisões). Levantamento feito em 2026-10-07; confirme os requisitos de cada base antes de se candidatar.

Legenda: ✅ feito · ⬜ a fazer · ⚠️ bloqueante

---

## 0. Decisões que bloqueiam o resto

- ⚠️ **Nome definitivo da revista.** O ISSN é vinculado ao título, e mudar o título depois exige **novo ISSN**. O mesmo vale para o registro do título na Crossref, para o DOI do periódico e para as bases indexadoras. Decida o nome **antes** de publicar o 1º número.
  - "Brazilian Journal of Animal Science (Reviews)" conflita com a *Revista Brasileira de Zootecnia* (SBZ), cujo título em inglês é exatamente esse.
  - A sigla "BJVR" coincide com *Pesquisa Veterinária Brasileira* (Brazilian Journal of Veterinary Research) e se parece com BJVRAS (USP).
  - Antes de bater o martelo, confira no portal.issn.org, no DOAJ, no Google Scholar, no INPI (marca) e na disponibilidade de domínio.
- ✅ **Pasta × domínio.** Confirmado em 2026-10-20: a pasta no servidor é `~/journals.csvet.com.br` (plural), igual ao domínio.
- ⬜ **Periodicidade.** Recomendo **fluxo contínuo** (o artigo é publicado assim que fica pronto) com **volume anual**, mais rápido e aceito por todas as bases. Declare-a no expediente.

## 1. Infraestrutura ✅

- ✅ OJS 3.5 LTS com deploy via SSH + `git pull` + `scripts/install-ojs.sh`
- ✅ HTTPS forçado, código interno bloqueado, uploads sem execução de scripts
- ✅ Idiomas: inglês (principal), português e espanhol
- ✅ Instruções aos autores nos 3 idiomas (`content/nvas/`), com todos os DOIs conferidos no Crossref
- ✅ Logo e favicon (`branding/`)
- ✅ `robots.txt` com sitemap; compressão e cache para desempenho
- ⬜ **E-mail transacional (crítico).** Convites a revisores não podem cair em spam. Configure SMTP autenticado da HostGator no `config.inc.php` (`[email]`), um remetente do domínio (ex.: `editorial@csvet.com.br`) e **SPF, DKIM e DMARC** no DNS.
- ⬜ Cron horário de `tools/runScheduledTasks.php` (lembretes de prazo a revisores e estatísticas)
- ⬜ Backup automático: banco + `~/journal_files` + `public/` + `config.inc.php`

## 2. Páginas e políticas obrigatórias no site

São exigidas, em conjunto, pelo IBICT (ISSN), pelo DOAJ, pelo Latindex e pelo COPE. Cadastre cada uma no OJS (*Configurações > Revista / Fluxo de trabalho / Distribuição*) nos 3 idiomas.

| Página / política | Exigida por | Status |
|---|---|---|
| Foco e escopo (somente revisões; tipos aceitos) | DOAJ, Latindex, Scopus | ⬜ |
| **Expediente / corpo editorial**: nome, afiliação, país e ORCID de cada membro | IBICT, DOAJ, Latindex, Scopus | ⬜ |
| Editora responsável com **endereço completo** (CSVET Editora) | IBICT, DOAJ | ⬜ |
| Instruções aos autores | IBICT, DOAJ, Latindex | ✅ |
| Processo de avaliação por pares (duplo-anônimo, nº de revisores, prazos) | DOAJ, Latindex | ✅ (nas instruções); ⬜ página própria |
| Política de acesso aberto + licença CC BY 4.0 + direitos autorais com os autores | DOAJ | ✅ (nas instruções); ⬜ configurar licença no OJS |
| **Política de APC**, visível: "sem APC para artigos aceitos até 31/01/2027" | DOAJ | ✅ (nas instruções); ⬜ página própria |
| Ética e má conduta (COPE), correções e retratações | DOAJ, Scopus | ⬜ |
| Conflitos de interesse (autores, revisores, editores) | DOAJ, COPE | ⬜ |
| Detecção de plágio (Crossref Similarity Check, após virar membro) | DOAJ, Scopus | ⬜ |
| Política de uso de IA | boas práticas (COPE/ICMJE) | ✅ (nas instruções) |
| **Preservação digital** (PKP Preservation Network, gratuito, exige ISSN) | DOAJ | ⬜ |
| Autoarquivamento (os autores podem depositar qualquer versão) | DOAJ | ⬜ |
| Privacidade / LGPD | IBICT, DOAJ | ⬜ |
| Periodicidade declarada | IBICT, Latindex | ⬜ |
| Contato editorial com e-mail institucional | todas | ⬜ |

O OJS já atende aos links que o IBICT exige na página principal: Apresentação (Sobre), Expediente, Edição atual, Edições anteriores e Normas.

## 3. Equipe editorial (antes de convidar autores)

As bases avaliam a **credibilidade do corpo editorial**: pessoas reais, verificáveis, com ORCID e publicações na área.

- ⬜ **Editor-chefe** (você) e, se possível, um co-editor de outra instituição
- ⬜ **Editor de métodos** (síntese de evidências/estatística), essencial para uma revista de revisões sistemáticas
- ⬜ **Editores associados** por área (clínica de pequenos animais, grandes animais, patologia, saúde pública/One Health, produção, animais selvagens), de **várias instituições e, se possível, países**. A diversidade geográfica pesa no DOAJ, no Scopus e na SciELO.
- ⬜ Termo de aceite de cada membro e declaração de conflitos
- ⬜ **Endogenia:** limite artigos de editores e de autores da mesma instituição da editora, publique a regra e aplique-a desde o início (SciELO e Qualis medem isso)

## 4. Preparação para os revisores

- ⬜ **Formulário de avaliação** no OJS (*Fluxo de trabalho > Avaliação > Formulários*) específico para revisões. Perguntas baseadas no PRISMA 2020 e no AMSTAR 2 (sistemáticas) e no SANRA (narrativas), mais um campo de recomendação.
- ⬜ Diretrizes para revisores: prazo (ex.: 21 dias), confidencialidade, conflito de interesses, proibição de colocar o manuscrito em ferramentas de IA, tom construtivo
- ⬜ Modelos de e-mail personalizados (convite, lembrete, agradecimento) nos 3 idiomas
- ⬜ Reconhecimento: certificado de revisão emitido pela revista e crédito no **ORCID** (plugin ORCID do OJS)
- ⬜ Banco de revisores: comece pelos autores das revisões citadas nas suas áreas e por membros de sociedades científicas

## 5. Captação de manuscritos (1º número)

- ⬜ **Meta: no mínimo 5 artigos no 1º número completo.** É o que o IBICT costuma pedir para periódicos online (o CBISSN atribui o ISSN "após a disponibilização do primeiro número completo"). Mire em **6 a 8** para ter folga.
- ⬜ **Chamada de artigos** (anúncio no OJS + e-mail + redes) destacando: só revisões, avaliação rigorosa, DOI, CC BY, **sem APC até 31/01/2027**
- ⬜ **Convites diretos e personalizados** a especialistas para revisões narrativas e sistemáticas. Evite disparos em massa: e-mails genéricos em quantidade parecem prática predatória e prejudicam a reputação.
- ⬜ Modelo de manuscrito (.docx) com folha de rosto, estrutura e estilo Vancouver
- ⬜ Links diretos para os checklists PRISMA/PRISMA-ScR/PRIOR/SANRA na página de submissão
- ⬜ Nunca prometa aceite rápido ou garantido: a avaliação por pares tem de ser real e documentada no OJS

## 6. Sequência ISSN → DOI

1. ⬜ Concluir a filiação à **Crossref** (em andamento) e ativar o plugin **Crossref/DOI** do OJS (prefixo, usuário e senha de depósito).
2. ⬜ Publicar o **1º número completo** (≥5 artigos). Decisão do editor-chefe (15/11/2026): os DOIs só são depositados **depois do ISSN**, para o metadado já sair com ele.
3. ⬜ Solicitar o **ISSN eletrônico** ao IBICT ([formulário](https://cbissn.ibict.br/formulario/), gratuito), com prints da tela de título, do expediente (corpo editorial, periodicidade, nome e endereço completo da editora) e do sumário com a designação numérica (Vol. 1, Nº 1 / 2027). O ISSN é **retroativo** ao 1º número.
4. ⬜ Ao receber o ISSN: cadastrá-lo no OJS (*Configurações > Distribuição*), atualizar o registro do título na Crossref e reenviar os metadados dos artigos.
5. ⬜ Ativar o **PKP Preservation Network** (exige ISSN).

## 7. Indexação: o que fazer e quando

| Quando | Base | Requisito principal | Custo |
|---|---|---|---|
| Na publicação | **Crossref** (DOI) | filiação ativa | anuidade + taxa por DOI |
| Automático via Crossref | **OpenAlex, Dimensions, Lens** | DOIs depositados com metadados completos (resumo, ORCID, referências) | grátis |
| Logo após o 1º número | **Google Scholar** | o OJS já gera as meta tags; o PDF precisa trazer título e autores na 1ª página | grátis |
| Logo após o ISSN | **Miguilim** (IBICT, diretório de revistas brasileiras) e **Diadorim** (políticas de direitos autorais) | ISSN | grátis |
| Logo após o ISSN | **BASE**, via OAI-PMH (o OJS já expõe `/index/oai`) | URL OAI | grátis |
| Logo após o ISSN | **Sherpa Romeo / Open Policy Finder** | política de autoarquivamento | grátis |
| Após o ISSN | **CAB Abstracts / Global Health** (CABI), a base de referência em veterinária | ISSN, conteúdo revisado por pares | grátis para a revista |
| >1 ano **ou** ≥10 artigos | **DOAJ** | ISSN, licença CC, políticas públicas, APC transparente, ≥5 artigos/ano | grátis |
| Depois do DOAJ | **DOAJ Seal** | DOIs, preservação, licença nos metadados, autoarquivamento | grátis |
| 2 anos online | **Latindex Catálogo 2.0** | 7 critérios obrigatórios + 30 dos 38 indicadores | grátis |
| Contínuo | **Qualis/CAPES** | ISSN e artigos citados ou publicados por programas de pós-graduação | — |
| ~2–3 anos de histórico | **Scopus** | não há mais carência fixa de 2 anos (desde 08/2024), mas é preciso ter volume e regularidade; uma rejeição gera período de embargo | grátis |
| Longo prazo | **SciELO Brasil, Web of Science (ESCI), PubMed Central** | critérios rigorosos de volume, citação, endogenia e internacionalização: ver a Seção 10 | — |

## 8. Indicadores de excelência a perseguir desde o 1º artigo

- Tempo médio até a 1ª decisão e até a publicação, **publicado no site** (o OJS gera as estatísticas)
- Taxa de rejeição real (revistas de excelência rejeitam a maioria das submissões)
- 100% dos artigos com DOI, ORCID de todos os autores, resumo e referências depositados na Crossref
- Referências com DOI (já exigido nas instruções), o que aumenta a visibilidade no Crossref/OpenAlex
- Autores e revisores de várias instituições, estados e países
- Regularidade: publicar o ano todo, sem meses "vazios"
- Transparência: data de submissão, aceite e publicação em cada artigo; nomes dos editores responsáveis

## 9. Revista de relatos de caso (segunda revista)

- Mesmo OJS (multi-revista), com caminho próprio, **ISSN próprio** e Crossref sob o mesmo prefixo
- Escopo exclusivo: relatos de caso e séries de casos em medicina veterinária
- Diretriz de relato: **CARE** (adaptada à veterinária), sem CEUA para atendimento de rotina; termo do responsável legal (ou termo institucional, como o do hospital-escola) só quando o animal ou o responsável puder ser identificado
- Nomes a evitar: "Brazilian Journal of Case Reports" (já existe, ISSN 2763-583X, aceita casos veterinários); internacionalmente já existem *Case Reports in Veterinary Medicine* e *Veterinary Record Case Reports*

## 10. Meta: Scopus, PubMed e SciELO (levantamento de 15/11/2026)

Critérios lidos nas fontes oficiais (links no fim). Medicina veterinária entra em **Ciências Agrárias** na SciELO.

### 10.1 O que cada base exige

| Exigência | SciELO Brasil (critérios 09/2024) | PubMed Central (PMC) | MEDLINE | Scopus |
|---|---|---|---|---|
| Tempo mínimo | 1 ano de existência; só os últimos 2 anos são avaliados | 25 artigos primários publicados; a **editora** precisa de 2 anos de histórico em ciências da vida | 12 meses publicando e 40 artigos | "histórico de publicação" (sem número fixo) |
| Volume | mediana da área: **56 artigos/ano** (Agrárias); abaixo disso, ingresso condicionado | — | — | regularidade, sem atrasos ou interrupções |
| Idioma | **90%** dos artigos em inglês e/ou espanhol (média de Agrárias) | conteúdo primário majoritariamente em inglês (português não é exceção) | título e resumo em inglês em todo o conteúdo dos últimos 12 meses | título e resumo em inglês; página da revista em inglês |
| Internacionalização | **27%** dos artigos com afiliação no exterior (Agrárias); ≥25% (recomendado 35%) dos editores associados no exterior; ≥15% (recomendado 25%) dos manuscritos avaliados com editor ou parecerista do exterior | variedade de afiliações de autores e editores | idem | "ampla distribuição geográfica" de editores e de autores |
| Endogenia | concentração institucional ou geográfica de autores, editores ou pareceristas **limita a admissão**; é checada na pré-avaliação | triagem pode rejeitar por "autoria repetida ou de membros do conselho"; exige **política para artigos de editores** | idem | "política editorial convincente"; "prestígio do editor" |
| Corpo editorial | editores com ORCID e currículo on-line; ≥25% homens e ≥25% mulheres; **nome do editor responsável em cada artigo** | avaliado | avaliado | avaliado |
| Avaliação por pares | descrita publicamente; decisão em até 6 meses e publicação em até 12 meses da submissão | processo explícito; ética, conflitos de interesses, diretrizes de relato (CARE, ARRIVE) | idem | descrição pública obrigatória |
| Publicação | **contínua** (exigida); 6 meses sem publicar exclui | manter a periodicidade declarada | idem | sem atrasos |
| Técnica | XML **SciELO PS** (JATS); DOI Crossref com referências abertas; ORCID de **todos** os autores; preservação LOCKSS/Cariniana | XML **JATS** (não aceita HTML), PDF e figuras em alta; amostra de ~25 artigos; até 3 rodadas de correção | XML PubMed por fornecedor; preservação (Portico, CLOCKSS ou PMC) | texto completo on-line |
| Indexação prévia | **DOAJ obrigatório**; DOAJ Seal obrigatório para indexados até o fim de 2026; ISSN | ISSN registrado | ISSN | ISSN registrado no ISSN International Centre |
| Ciência aberta | formulário de conformidade com a ciência aberta na submissão; preprints aceitos; dados disponíveis; opção de publicar pareceres | — | — | política de IA recomendada |
| Citações | tendência crescente; H5 do Google Acadêmico como referência (mediana de Agrárias: 17) | — | "impacto" | citações dos artigos na Scopus |
| Após rejeição | — | 24 meses para reaplicar (12 se a falha for técnica), com ≥20 artigos novos | 24 a 36 meses | embargo (duração não publicada) |

PubMed: entrar pelo **PMC** primeiro (25 artigos). O MEDLINE vem depois (40 artigos, indexação MeSH). Uma rejeição no PMC por qualidade ou escopo bloqueia o MEDLINE por 2 anos: só aplicar quando tudo estiver pronto.

### 10.2 Onde a NVAS está hoje (15/11/2026)

- **Corpo editorial:** 2 pessoas, ambas da Universidade Cruzeiro do Sul (editor-chefe e coeditora-chefe), nenhuma no exterior. É o ponto mais fraco para as três bases.
- **Submissões ativas (11):**
  - 3 têm o editor-chefe como coautor (#5, #7, #16), ou 27%.
  - Cerca de metade tem autores da Cruzeiro do Sul.
  - Nenhuma tem autor do exterior.
  - Todas são de São Paulo, menos uma.
- **Tipos:**
  - Predominam revisões narrativas e relatos de caso.
  - Há só 1 artigo original (#13).
  - SciELO e PMC querem "predominantemente pesquisa original".
- **Já atende:**
  - Publicação contínua (volume anual) e acesso aberto com CC BY.
  - Avaliação duplo-anônima com política pública.
  - COPE e ICMJE; diretrizes EQUATOR por tipo (CARE, ARRIVE, PRISMA, SANRA).
  - Preprints permitidos; CRediT; política de IA.
  - Detecção de similaridade declarada.
  - Publicação em inglês: a revista traduz após o aceite.
  - Site com inglês como idioma principal.
- **Falta (técnico):**
  - ISSN; DOI ativo; DOAJ (e depois o Seal).
  - ORCID obrigatório para **todos** os autores (hoje é pedido na folha de rosto).
  - Produção de XML JATS / SciELO PS.
  - Preservação (PKP PN ou LOCKSS/Cariniana).
  - Nome do editor responsável impresso em cada artigo.
  - Formulário de ciência aberta.
  - Tempos médios de avaliação publicados no site.
- **Atenção:** a regra do parecer externo único (1 externo + parecer do editor) é pública e defensável, mas deve ser exceção rara e contada. Nos artigos com conflito do editor, não vale (ver 10.3).

### 10.3 Artigos do editor-chefe e do corpo editorial

A COPE aceita que editores publiquem na própria revista, desde que a avaliação seja independente e transparente. As bases olham duas coisas: a **política escrita** e a **proporção** desses artigos no período avaliado (últimos 2 anos).

Proposta:
1. **Editor independente de outra instituição.** O manuscrito com autor do corpo editorial vai para um editor sem vínculo com os autores. A coeditora atual é da mesma universidade, o que enfraquece a independência; o ideal é um editor associado de outra instituição para esses casos.
2. **Autor-editor fora do processo.** Ele não escolhe nem sugere avaliadores, não lê pareceres e não participa da decisão. O OJS deixa o gestor ver tudo, então a regra é escrita e cumprida, e o editor responsável registra no sistema.
3. **Dois pareceres externos sempre**, sem a exceção do parecer editorial.
4. **Transparência no artigo:** nome do editor responsável (a SciELO exige em todos os artigos) e a nota: "O editor-chefe, coautor, não participou da avaliação nem da decisão."
5. **Teto interno:** no máximo 1 em cada 10 artigos publicados por ano com autor do corpo editorial (regra da revista, não número das bases), relatado no balanço anual.
6. **Prioridade:** os trabalhos mais fortes do editor vão para revistas já indexadas; na NVAS, com seleção.

**Quem conduz cada artigo (decisão de 15/11/2026)**

| Situação | Quem conduz | Avaliadores |
|---|---|---|
| Editor-chefe é autor | Editor associado (Andressa Rorato desde 16/11/2026) | 2 pareceres externos completos (trava no OJS) |
| Editor-chefe não é autor, mas há orientando ou coautor recente dele | Coeditora-chefe | de fora da instituição dos autores |
| Colega da mesma instituição, sem vínculo direto | Editor-chefe, conflito declarado | os 2 de fora da instituição |
| Demais | Editor-chefe | regra geral |

O editor externo fica só com os artigos do editor-chefe (3 a 5 por ano). A triagem automática avisa autor do corpo editorial e da Cruzeiro do Sul e mostra a proporção da instituição nos últimos 12 meses (meta interna: no máximo cerca de um terço).

### 10.4 Plano de ação

**Agora (antes da 1ª publicação)**
- ✅ Política "Artigos de editores" (10.3) em Sobre e nas normas (15/11/2026). O teto de 1 em 10 fica só como regra interna.
- **Decisão do editor-chefe (15/11/2026):** até a 1ª edição e o ISSN, o corpo editorial fica como está (editor-chefe e coeditora; a coeditora conduz os artigos com o editor-chefe como autor). A ampliação vem depois, com ISSN e DOI já funcionando.
- ✅ **Mudança (16/11/2026):** criado o papel "Editor Associado" (OJS, id 22); Andressa Rorato assumiu os artigos com o editor-chefe como autor nº 5 (Callithrix) e 16 (osteossarcoma), no lugar da coeditora. O nº 7 (xenotransplante) fica sem editor, à espera de um segundo editor associado. ⬜ Falta gravar filiação e ORCID dela e incluí-la no expediente (`masthead` do journal.json).
- ✅ Página do artigo: datas de recebimento e aceite, editor responsável e nota quando há autor do corpo editorial (plugin csvetTransparency, 15/11/2026). ⬜ Falta o mesmo no PDF.
- ✅ ORCID obrigatório para todos os autores (15/11/2026): item da declaração, checklist e normas; o OJS só publica com o ORCID de todos verificado (o editor usa "Solicitar verificação" na aba Contribuidores).
- ✅ Formulário de ciência aberta dentro da declaração dos autores (15/11/2026): preprint, dados/códigos e autores do corpo editorial, registrados no histórico da submissão.

**Com a 1ª publicação**
- ⬜ Ampliar o corpo editorial (depois do ISSN):
  - editores associados de outras instituições e estados;
  - **≥25% no exterior** (América Latina, Portugal);
  - equilíbrio de gênero;
  - ORCID e currículo de todos no expediente;
  - um editor externo (de outra instituição) para os artigos do editor-chefe, que assume essa função no lugar da coeditora (decisão de 15/11/2026: troca logo depois do ISSN).
- ⬜ ISSN → DOI (Seção 6 e roteiro ISSN/DOI). Crossref com referências e resumos abertos.
- ⬜ Google Acadêmico; DOAJ assim que houver ISSN e artigos suficientes; preservação PKP PN.

**Ano 1 (2027)**
- ⬜ Captação fora da Cruzeiro do Sul e do exterior; priorizar **pesquisa original**.
- ⬜ Volume: mirar ≥56 artigos/ano, a mediana de Agrárias na SciELO.
- ⬜ ≥90% em inglês; ≥27% com autor do exterior.
- ⬜ Pareceristas do exterior em ≥15% dos manuscritos.
- ⬜ XML JATS / SciELO PS: escolher entre ferramenta e fornecedor, e testar no PMC Style Checker antes de aplicar.
- ⬜ DOAJ Seal. Balanço anual público (tempos, aceite, endogenia, internacionalização).

**Candidaturas (só com os números atingidos)**
- ⬜ **PMC:** ≥25 artigos de pesquisa em inglês, XML aprovado, políticas no ar.
- ⬜ **SciELO:** ≥1 ano, volume e percentuais da área, DOAJ.
- ⬜ **Scopus:** ISSN, histórico regular, corpo editorial e autores diversos, citações.
- ⬜ **MEDLINE:** depois do PMC (≥40 artigos, 12 meses).

## Fontes

- [CBISSN/IBICT: perguntas frequentes](https://cbissn.ibict.br/perguntas-frequentes-2/) e [formulário](https://cbissn.ibict.br/formulario/)
- [DOAJ: critérios e guia de candidatura](https://doaj.org/apply/guide)
- [Crossref: periódicos sem ISSN](https://community.crossref.org/t/registering-articles-for-a-journal-without-an-issn/3967) e [princípios para periódicos](https://www.crossref.org/documentation/principles-practices/journals/)
- [Latindex: metodologia do Catálogo 2.0](https://www.latindex.org/latindex/documentos/pdfs/Catalogo_2.0_metodologia_v5.pdf)
- [Scopus: mudanças na política de seleção (2024)](https://blog.scopus.com/scopus-content-policy-and-selection-changes/)
- [SciELO Brasil: critérios para admissão e permanência (setembro de 2024)](https://www.scielo.br/media/files/20240900-Criterios-SciELO-Brasil.pdf) e [página dos critérios](https://www.scielo.br/about/criterios-scielo-brasil)
- [PMC: como incluir um periódico](https://pmc.ncbi.nlm.nih.gov/pub/addjournal/), [diretrizes](https://pmc.ncbi.nlm.nih.gov/about/guidelines/) e [seleção](https://pmc.ncbi.nlm.nih.gov/pub/journalselect/)
- [MEDLINE: como incluir](https://www.nlm.nih.gov/medline/medline_how_to_include.html), [seleção](https://www.nlm.nih.gov/medline/medline_journal_selection.html) e [políticas](https://www.nlm.nih.gov/medline/medline_policies.html)
- [Scopus: política de conteúdo e seleção](https://www.elsevier.com/products/scopus/content/content-policy-and-selection)
