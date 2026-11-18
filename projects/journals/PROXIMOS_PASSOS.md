# Próximos passos: NVAS e journals.csvet.com.br

Atualizado em 2026-10-17. Revista: **Neotropical Veterinary and Animal Science (NVAS)**, `https://journals.csvet.com.br/nvas`.
Comandos rodam no **Terminal do cPanel**, dentro de `~/journals.csvet.com.br`.

## 1. Deploy pendente (agora)

```bash
cd ~/journals.csvet.com.br
git pull
php scripts/configure-journal.php --dry-run   # só mostra o que faria
php scripts/configure-journal.php
```

Deploy de 2026-10-19 (login caindo, filiação/contribuidores sem gravar, "erro inesperado"):
```bash
git pull
bash scripts/patch-ojs.sh             # erro 1615 corrigido já na conexão, antes da sessão
bash scripts/configure-production.sh  # session_check_ip = Off (não desloga quando o IP muda)
php scripts/diagnose.php              # confere os dois itens e mostra erros recentes do error_log
php scripts/configure-journal.php     # cópia oculta de todos os e-mails para bccEmails (csvetemailplugin)
```
Depois todos precisam entrar de novo (a sessão antiga cai uma vez). Conferir: salvar filiação e contribuidores numa submissão e navegar no painel por alguns minutos sem cair.

Deploy de 2026-10-19, à tarde (filiação dos contribuidores em qualquer idioma, plugin `generic/csvetAffiliation`):
```bash
git pull
php scripts/configure-journal.php     # registra o plugin
```
Conferir: numa submissão em inglês, com a interface em português, adicionar um contribuidor com filiação digitada só em português (sem escolher na lista): deve gravar, e o último passo não deve acusar filiação incompleta. (Testado em 2026-10-19 no rascunho nº 6: ok.)

**E-mails do site descartados pela HostGator (corrigido e confirmado em 2026-10-19):** o OJS se apresentava ao SMTP como `EHLO [127.0.0.1]` e o filtro antispam da HostGator (router `fightspamHG`) mandava tudo para `/dev/null`, sem erro no site. O `csvetEmail` agora usa o nome do servidor no EHLO. Conferir com `php scripts/diagnose.php --mail-ojs=voce@gmail.com` (deve mostrar `EHLO sh00122...`) e, para ver o destino de cada e-mail, `php scripts/diagnose.php --mail-variants=voce@gmail.com` ou cPanel › Rastrear entrega.
- [x] Convites perdidos reenviados em 2026-10-19: coeditora (Adriana, manual) e avaliadores da submissão nº 3 (Andressa e Tarso, pelo Lembrete ao Avaliador › Reenvio do convite); entrega confirmada no cPanel.
- Cobrar ou reenviar a avaliadores: submissão › Avaliação › botão "Enviar lembrete / link" na linha do avaliador (o OJS só mostra "Enviar lembrete" com prazo vencido; o plugin `csvetReviewReminder` põe o botão também antes) › Modelo da mensagem.
- Link do avaliador para WhatsApp: na mesma janela, campo "Mensagem com o link" › Copiar mensagem (gestores e editores de seção). Para mandar só pelo WhatsApp, feche a janela sem enviar o e-mail. Não abra o link logado como editor.
- Link do avaliador com 404 (Andressa, nº 3, 2026-10-20): o OJS 3.5 apagava os links anteriores a cada link novo (lembrete, reenvio, abrir a janela do lembrete). Corrigido pelo patch 2 do `scripts/patch-ojs.sh` (rodar no servidor depois do git pull). Os links apagados antes do patch não voltam: mandar um novo.
- Coeditora (Adriana, 2026-10-20) viu "Você não está autorizado para acessar o recurso solicitado" ao aceitar o convite, mas o aceite já tinha gravado o papel: era o segundo clique no botão final, com o convite já aceito. Corrigido pelo patch 3 do `scripts/patch-ojs.sh` (rodar no servidor depois do git pull). Conferir em Usuários e Papéis se ela ficou com uma conta só (Autor e Coeditor na mesma linha).
- [ ] Trocar a senha de `noreply@journals.csvet.com.br` (apareceu em uma saída do diagnose) e gravar com `php scripts/configure-email.php noreply@journals.csvet.com.br --server=journals.csvet.com.br --port=465 --auth=ssl`.
- [x] Apagar a submissão de teste nº 6 (apagada em 2026-10-19).
- O convite de papel do OJS 3.5 ignora os campos Cc/Cco da janela; a cópia para o editor vem do `bccEmails`.

Deploy de 2026-10-17 (triagem das submissões):
- Plugin `generic/csvetScreening`: o envio só conclui com os arquivos obrigatórios do tipo de artigo (`rules.php`); e-mail **[NVAS] Nova submissão #id** com a triagem para os gestores e `notifyEmails`; painel **Triagem editorial** no topo da etapa Submissão; página `/nvas/screening/view/<id>`.
- Tipos de arquivo novos no envio (folha de rosto, carta, checklist, figuras com/sem marcações, ética, termo, autorização) e tipos padrão confusos desativados (`genres` no `journal.json`).
- Declaração dos autores com links do modelo/formulário em cada item e dois itens novos (normas e arquivos).
- E-mails: confirmação ao autor (`SUBMISSION_ACK`), recusa (`EDITOR_DECISION_INITIAL_DECLINE`) e a opção **Devolução técnica (normas ou arquivos)** na decisão Recusar submissão; assinatura "Dr. Atilio Sersun Calefi".
- Modelo de termo de consentimento (.docx) e seção "qual checklist enviar" na página Modelos para autores.
- Conferir após o deploy: (1) fazer uma submissão de teste sem folha de rosto: o último passo deve listar o que falta; (2) completar e enviar: chega o e-mail de triagem; (3) abrir a submissão no painel › Submissão: aparece o quadro Triagem editorial; (4) Recusar submissão › Usar modelo: aparece "Devolução técnica"; (5) excluir a submissão de teste.

Deploy de 2026-10-09 (além do que está abaixo):
- Plugin `generic/csvetLocaleUrl`: as páginas em português (`/nvas/pt_BR/...`) deixam de entrar em loop de redirecionamento para quem não guarda cookie (Google, prévias de WhatsApp/LinkedIn). Conferir: `curl -sI https://journals.csvet.com.br/nvas/pt_BR/about | head -1` deve dar `200`, não `302`.
- Formulário de avaliação **Avaliação padrão NVAS** (14 perguntas, pt/en), lembretes automáticos aos revisores e acesso do revisor com um clique pelo e-mail.
- Editor-chefe na página Equipe Editorial (procura a conta `calefi@csvet.com.br`; se a sua conta usar outro e-mail, troque em `masthead` no `journal.json`).
- Página inicial com o botão **Submeter manuscrito** e o passo a passo (inclusive o aviso aos estudantes).
- Integridade em pesquisa (CSE e *On Being a Scientist*) no Sobre, nas instruções aos autores e nas diretrizes de avaliação.

- Registra o plugin `generic/csvetEmail`: todos os e-mails do OJS passam a sair com o layout da revista (logo, credenciais e rodapé). Conferir no teste de "Esqueceu sua senha?" do item 2.
- Aplica o novo logo da NVAS (selo NVAS + título em serifa), o favicon e a miniatura com nome versionado (acaba com o cache das imagens antigas) e o logo da CSVET Editora no portal.
- Depois, abra o site com **Ctrl+F5**. O WhatsApp e o LinkedIn atualizam a prévia sozinhos em alguns dias; o LinkedIn pode ser forçado em https://www.linkedin.com/post-inspector/.

## 2. E-mail (em andamento)

- [x] SMTP configurado com `noreply@journals.csvet.com.br` (`journals.csvet.com.br:465`, SSL); SPF e DKIM do subdomínio ativos.
- [x] Teste do servidor: e-mail de teste entregue em 2026-10-08.
- [ ] Teste real pelo site: Login › Esqueceu sua senha? → o e-mail deve chegar de `... via Neotropical Veterinary and Animal Science <noreply@journals.csvet.com.br>`.
- [ ] Se cair no spam: no Gmail, ⋮ › Mostrar original e anotar SPF, DKIM e DMARC.
- [ ] Opcional: DMARC próprio do subdomínio (cPanel › Zone Editor, TXT `_dmarc.journals`): `v=DMARC1; p=none; rua=mailto:calefi@csvet.com.br`.

## 3. ORCID

- [ ] Criar credenciais gratuitas em https://orcid.org/developer-tools (Public API). Nome `CSVET Journals`; URL e Redirect URI `https://journals.csvet.com.br`.
- [ ] No OJS: Configurações › Usuários e Papéis › aba ORCID → ativar, API "Public", Client ID e Client secret.

## 4. Perfil de usuário

- [ ] Descobrir quais campos do perfil não salvam e o que acontece (mensagem de erro? volta ao valor antigo?). A base de instituições (ROR) já está carregada (144.717). Trocar o e-mail do perfil exige confirmação por e-mail, que depende do item 2.

## 5. Decisões editoriais pendentes

- [ ] Endereço público da editora: hoje só "Americana, SP, Brasil". Decidir se mostra o endereço completo (o IBICT pede o endereço completo no formulário do ISSN).
- [ ] Confirmar a política de taxas: isenção de APC até 31/01/2027 e valor/isenções depois disso.
- [ ] Revisão jurídica: instruções aos autores (ética, IA, licença), termo de adesão ao serviço voluntário e declaração/licença dos autores.

### Submissões em andamento

**Nº 3, Fukushima (relato de caso, carcinoma nasal em cão), em avaliação.** Faltam a folha de rosto e o checklist CARE; os 5 coautores estão cadastrados com e-mail, mas sem filiação (a carta de apresentação não as traz; o manuscrito está anonimizado). A carta pede que não avaliem: Esther Lopes Ricci, Maria Martha Bernardi e Helenice de Souza Spinosa.
- [ ] Quando os pareceres voltarem: enviar o pedido abaixo junto com a decisão (ou numa discussão: submissão › Avaliação › Discussões › Adicionar discussão, com o autor como participante).
- [ ] Para ele mesmo completar os coautores: submissão › Participantes › menu do autor › Editar › marcar "permitir alterar os metadados" (canChangeMetadata) › Salvar. Depois que ele completar, desmarcar.
- [ ] Com a folha de rosto em mãos, conferir as filiações (ou preenchê-las em Publicação › Contribuidores › Editar).

```
Assunto: Submissão nº 3: folha de rosto e dados dos coautores

Prezado Dr. Fukushima,

Agradecemos o envio do relato "Nasal carcinoma in a dog", que está em avaliação. Para concluir o cadastro, precisamos de:

1. Folha de rosto com todos os autores, na ordem de autoria: nome completo, filiação (departamento, instituição, cidade, país), e-mail e ORCID de cada um, indicando o autor correspondente e as contribuições (CRediT). Modelo em https://journals.csvet.com.br/nvas/pt_BR/templates.

2. Checklist CARE preenchido (modelo na mesma página).

Pode anexar os dois arquivos nesta discussão. Com eles, completamos as filiações dos coautores no sistema; os avaliadores não têm acesso à folha de rosto.

Atenciosamente,
Dr. Atilio Sersun Calefi
Editor-chefe, Neotropical Veterinary and Animal Science
```

**Nº 5, Spadin e Calefi (revisão narrativa, saguis).** O editor-chefe é autor: designar a coeditora (aba "Precisa de editor" › Participantes › Designar) e ficar fora da avaliação. Filiações dos dois autores já gravadas (19/10/2026).

## 6. Corpo editorial

- [ ] Preencher `D:\OneDrive\Documentos\CSVET Editora\NVAS - Corpo Editorial\Corpo Editorial NVAS.xlsx` (aba Convidados).
- [ ] Ativar a API para os convites (uma vez):
  ```bash
  SECRET=$(openssl rand -hex 32); sed -i "s/^api_key_secret = \"\"$/api_key_secret = \"$SECRET\"/" config.inc.php; unset SECRET; grep -c '^api_key_secret = ""' config.inc.php   # deve dar 0
  ```
  e gerar a chave em Perfil › Chave de API.
- [ ] Salvar a aba Convidados como CSV UTF-8, subir para `~/convites/corpo-editorial.csv` e rodar:
  ```bash
  php scripts/invite-editors.php ~/convites/corpo-editorial.csv --user=SEU_USUARIO          # simula
  php scripts/invite-editors.php ~/convites/corpo-editorial.csv --user=SEU_USUARIO --send   # envia
  ```
- [ ] Após cada aceite: enviar o termo de adesão ao serviço voluntário e atribuir editores às seções.
- [ ] Meta: coeditor-chefe, 1 a 2 editores de estatística e métodos, 8 a 12 editores associados (instituições diferentes, ao menos 1 do exterior), 8 a 15 no conselho editorial.

## 7. Operação do site

- [ ] Cron no cPanel (Trabalhos Cron, uma vez por hora):
  `0 * * * * cd /home2/atilio12/journals.csvet.com.br && php tools/runScheduledTasks.php >/dev/null 2>&1`
- [ ] Backup periódico: banco MySQL, `~/ojs_files`, `public/` e `config.inc.php` (o `app_key` não está no Git).
- [ ] Google Search Console: enviar `https://journals.csvet.com.br/index/en/sitemap` na propriedade de domínio `csvet.com.br`.

## 8. Captação e primeiro número

- [ ] Montar antes um núcleo do corpo editorial (coeditor-chefe + 3 ou 4 associados) para o expediente não aparecer vazio.
- [ ] E-mails de captação prontos em `content/nvas/emails/` (chamada de artigos; convites para revisão, para orientadores de pós-graduação e para residências/hospitais; PT e EN). Gerar com `python content/nvas/emails/build.py`, abrir `content/nvas/emails/prontos/index.html` e colar no Gmail, um por pessoa.
- [ ] Chamada de artigos também como anúncio no OJS e nas redes.
- [ ] Antes do 1º artigo publicado: concluir a Crossref, porque o site e os e-mails prometem DOI em todo artigo.
- [ ] Meta de **5 a 8 artigos** no primeiro número (requisito prático do IBICT para o ISSN).
- [x] Modelos (.docx) com orientação passo a passo: relato de caso, artigo original, revisão, folha de rosto e carta (PT e EN), na página `/nvas/<idioma>/templates` (`python content/nvas/templates/build_manuscript_templates.py`).
- [ ] **Tradução pela revista** (artigos aceitos em português, gratuita até 31/01/2027): anotar o esforço de cada uma para decidir, antes de 2027, se continua, entra na APC ou volta para os autores.

  | Submissão | Tipo | Palavras | Horas (tradução + revisão) | Ferramenta | Ajustes pedidos pelos autores |
  |---|---|---|---|---|---|
  | | | | | | |

## 9. Depois do primeiro número

- [ ] ISSN eletrônico no IBICT (formulário gratuito, retroativo ao 1º número).
- [ ] Adesão à Crossref (DOI) e ativação do plugin Crossref no OJS; Similarity Check.
- [ ] PKP Preservation Network (gratuito, exige ISSN).
- [ ] Indexadores: Google Scholar (automático), Miguilim/Diadorim, BASE, DOAJ (após publicar regularmente), Latindex (2 anos).

## Referência rápida

| O quê | Onde |
|---|---|
| Configuração da revista (textos, seções, categorias, imagens) | `content/nvas/journal.json` → `php scripts/configure-journal.php` |
| Instruções aos autores | `content/nvas/author-guidelines.en.html` e `.pt_BR.html` |
| Modelos de convite | `content/nvas/invitations.json` |
| Tema, SEO e imagem de compartilhamento | `plugins/themes/csvet/` |
| Declaração dos autores (no envio, sem formulário) | `plugins/generic/csvetDeclaration/strings.php` (textos pt/en) |
| Arquivos obrigatórios por tipo de artigo, triagem e e-mail ao editor | `plugins/generic/csvetScreening/rules.php`; tipos de arquivo em `genres` do `journal.json` |
| E-mails do OJS reescritos (confirmação, recusa, devolução técnica, convite) | `content/nvas/ojs-emails/` e `emailTemplates` do `journal.json` |
| Diagnóstico de e-mail, fila, ROR e ORCID | `php scripts/diagnose.php` |
| PDF do artigo (galé): prova do autor e versão publicada | nesta máquina: ficha JSON do artigo (modelo em `content/nvas/galley/ficha-exemplo.json`) → `python content/nvas/galley/build_galley.py ficha.json --proof` (manda ao autor pela discussão da etapa Produção) → aprovada: `--final` com a data de publicação → upload em Publicação › Galés. O OJS não gera o PDF. Posição e largura das figuras: `floats` na ficha |
| Plano editorial completo (indexação, políticas) | `PLANO_EDITORIAL.md` |
