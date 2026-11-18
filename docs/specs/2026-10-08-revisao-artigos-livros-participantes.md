# Revisão de Artigos/Livros — participantes, ficha CRediT, gestão da fila, auditoria e Área do Revisor

Data: 2026-10-08 · Amplia o módulo de [2026-10-06](2026-10-06-revisao-de-artigos-e-avaliacoes-de-estagio.md).

## Motivação
O professor precisava de um processo completo e auditável para revisar artigos **e livros** com coautores:
- ver em todo momento com quem o trabalho está;
- trocar, incluir, remover ou reordenar pessoas durante o processo (quem já entregou não pode ser removido);
- coletar de cada pessoa a ficha de autoria (nome, filiação, ORCID, telefone e participação CRediT), com aceite e ciência do conteúdo;
- mandar o link por WhatsApp quando a pessoa não recebe o e-mail;
- ter um link de cadastro do processo para quem ainda não tem e-mail;
- proteger contra erros de envio, com versões restauráveis e detecção de modificações;
- oferecer senha opcional e uma Área do Revisor separada do portal dos orientandos;
- registrar o envio à revista e fechar o processo com a versão final.

## Comportamento

### Fase 1 — núcleo
- **Tipo de trabalho** (`work_type`): artigo, livro, capítulo, resumo ou outro. O menu agora se chama "Revisão de Artigos/Livros" e o botão "Iniciar" virou **"Enviar despacho"**.
- **Participantes:** o e-mail passa a ser opcional. Foram incluídos telefone e **ordem de autoria** (`author_order`), que é independente da ordem de revisão (`position`).
- **Ficha do participante** (página pública, seção `#ficha`):
  - nome completo, nome para citação, e-mail e telefone;
  - filiação completa: instituição, departamento, cidade, estado e país;
  - ORCID com validação do dígito verificador (ISO 7064 11-2), ou a opção "não possuo";
  - tipo de participação em **CRediT**, com 14 cartões clicáveis e descrição curta; a declaração ("Fulano: Investigação, Metodologia.") se monta ao vivo;
  - campo "outra participação" e observações (conflito de interesses, financiamento);
  - **checklist** de 7 itens e os aceites "aceito participar" e "ciente do conteúdo e de que a composição/ordem pode mudar". Ficam registrados a data e o IP.
  - Dá para preencher a ficha **a qualquer momento**, inclusive antes da vez e em rascunho. **O envio do arquivo só é liberado depois da ficha confirmada.**
- **Só quem está na vez baixa** o arquivo e os documentos complementares (decisão do professor).
- **Na vez de quem:** a página de cada participante mostra um banner ("É a sua vez!" ou "Agora está com: X desde dd/mm"), a tabela da fila (recebeu, devolveu, ficha OK/pendente) e o **histórico público**. O histórico público mostra só o título dos eventos marcados `public`, sem IP nem e-mail.
- **Aviso (disclaimer)** em todas as páginas e e-mails: "pessoas que hoje não aparecem podem ser incluídas por determinação do Prof. Atilio e a ordem de revisão pode ser alterada; a ordem de revisão não é a de autoria".
- **Andamento para todos:** cada passagem de vez, a conclusão das revisões e o envio à revista mandam e-mail a todos os participantes com e-mail, cada um com o seu link. Pode ser desligado no processo (`notify_all`).
- **Link persistente:** o banco guarda o hash SHA-256 (para busca) e uma cópia **criptografada com a APP_KEY** (`token_encrypted`). Com isso, "Copiar link" e os e-mails reaproveitam o mesmo link. "Gerar link novo" invalida o anterior.
- **Painel (página do processo):**
  - banner "Na vez: X desde dd/mm (n dias), prazo, ATRASADO / ainda não abriu", com a contagem de fichas;
  - **por participante:** lembrete (clique no nome); **Copiar link**, com mensagem pronta e botão "Abrir no WhatsApp"; Gerar link novo; Editar dados (nome, e-mail, telefone, prazo, ordem de autoria — fica no histórico); Remover (só quem está aguardando; vira "Removido", nunca é apagado);
  - **menu Participantes:** Incluir participante, Reordenar a fila (arrastar), Trocar quem está na vez (passa para alguém da fila ou para uma pessoa nova; a atual volta para o fim ou sai), Prorrogar, Pular, Link de cadastro;
  - **Registrar envio à revista e fechar:** revista, data e versão final; avisa todos e desativa o link de cadastro.
- **Guia de edição** na página: como ligar o Controlar Alterações no Word e no LibreOffice, como comentar, não aceitar/rejeitar as alterações dos outros, evitar o Google Docs, salvar como .docx, e lembrar que as versões ficam guardadas.

### Fase 2 — auditoria e proteção contra erros
- **`DocxInspector`:** lê o .docx/.odt sem executar nada e compara a versão recebida com a devolvida. Apura: inserções/exclusões controladas, comentários, parágrafos e palavras +/−, semelhança e até 40 trechos alterados. Alertas possíveis:
  - `identical`: arquivo idêntico ao baixado;
  - `no_changes`;
  - `untracked`: texto mudou sem marcas de revisão;
  - `different_document`: semelhança abaixo de 50%;
  - `much_shorter`.
  Os alertas `identical`, `different_document` e `much_shorter` **pedem confirmação explícita** antes de aceitar o envio. O resultado fica em `forensic.changes`, aparece no painel ("Ver trechos alterados") e vai para o histórico e para o e-mail do professor.
- **Restaurar versão:** qualquer versão anterior vira a nova base, como uma cópia com número novo (`_vN_restaurada-vM`), guardada em `version_restores`. Nada é apagado. Quem está na vez é avisado para baixar de novo.
- **Documentos complementares** (`article_review_files`): o professor anexa até 10 por vez; o participante, até 5 junto com a revisão. Os formatos aceitos são PDF, Office/LibreOffice, CSV, TXT e PNG/JPG/TIF. O sistema confere a assinatura do arquivo (não só a extensão) e recusa macros.
- **Bloco de autores para a submissão:** texto pronto, na ordem de autoria, com filiações numeradas, ORCID, CRediT e contatos.
- **Dossiê de auditoria em PDF** (`article-review.admin.dossier`): fichas, aceites com IP, versões, SHA-256, alterações detectadas, removidos e o registro completo.

### Fase 3 — contas e cadastro rápido
- **Link de cadastro do processo** (`/revisao-artigo/cadastro/{token}`), ativado e desativado no painel e guardado criptografado. Quem abre:
  - escolhe o próprio nome entre os participantes **sem e-mail**, ou entra como pessoa nova no fim da fila (`self_registered`);
  - informa e-mail e telefone e cai direto na página pessoal para completar a ficha.
  O professor é avisado por e-mail e Telegram. E-mail repetido no processo é recusado.
- **Senha opcional:** o participante pode criar senha pela própria página. Com **"Exigir senha"** (`require_login`) ligado no processo, o link sozinho não abre nada: no 1º acesso a pessoa cria a senha e, depois, entra com ela.
  - A conta é criada com `role = 'reviewer'`.
  - Processos com o mesmo e-mail são vinculados automaticamente (`user_id`), inclusive os de alunos que já têm conta.
- **Área do Revisor** (painel Filament `revisor`, `/revisor`): login, recuperação de senha e perfil. A página "Meus processos" mostra a situação, quem está na vez e o botão "Abrir processo", que leva à rota autenticada `/revisao-artigo/conta/{id}` (só o dono acessa).
  - O revisor **não** acessa `/admin` nem `/portal`.
  - O login do portal de alunos explica que a conta de revisor entra por `/revisor`.
  - O e-mail de redefinição de senha manda `reviewer` para o painel `revisor`.

### Ajustes pedidos depois da 1ª entrega
- **Excluir processo** (página do processo, edição e lista), em qualquer situação, para cadastro ou envio errado. Exige digitar EXCLUIR. Apaga participantes, fichas, versões, documentos, histórico e os arquivos do disco. Opcionalmente avisa os participantes por e-mail ("processo cancelado", com motivo). Se alguém já entregou revisão, o modal sugere baixar antes o dossiê. A exclusão fica no log (`Log::info`).
- **Cadastro pré-preenchido:**
  - caixa "Como funciona" no topo;
  - instruções padrão aos participantes (`ArticleReview::DEFAULT_INSTRUCTIONS`), que também aparecem para processos antigos com o campo vazio;
  - nome curto do arquivo gerado a partir do título (`suggestShortName`);
  - prazo de 7 dias, tipo "artigo", andamento para todos ligado e senha desligada;
  - textos de ajuda em cada campo; só o nome do participante é obrigatório.

- **Puxar de um orientando:** nos formulários de participante (cadastro, "Incluir participante" e "Editar dados") há uma busca por nome, e-mail ou RGM. Ela preenche nome, e-mail, telefone e instituição do aluno. O e-mail vincula a conta dele (`user_id`), então o aluno vê o processo na Área do Revisor com a senha que já usa.
- **Para não atrasar o processo:**
  - os e-mails de andamento a todos e os convites do despacho saem **depois da resposta** (`defer`): quem devolve a revisão ou envia o despacho não espera o SMTP de N e-mails, sem risco de tempo limite;
  - a comparação por LCS ficou limitada a 250 mil células; acima disso usa a comparação por conjunto, para não estourar o `memory_limit` no envio de livros grandes;
  - o número no menu soma os processos que voltaram para o professor, os **com prazo vencido** e os em que a pessoa da vez **não abriu o link em 2 dias** (tooltip detalha; vermelho se houver atraso);
  - a lista mostra "não abriu o link". Os lembretes continuam manuais.

- **Notificações com "com quem está":**
  - o professor recebe e-mail no despacho, a cada passagem de vez ("Revisão devolvida por X — agora com Y") e no fim, sempre com a fila completa;
  - os e-mails de convite e de andamento para os participantes trazem "Agora está com: X" e a ordem de revisão, com quem entregou, quem está com o arquivo e o prazo;
  - quem acabou de devolver não recebe o "andamento" duplicado;
  - os avisos ao professor vão para `contato@profatilio.com.br` (`ArticleReviewService::PROFESSOR_EMAIL`), a mesma caixa do resto do sistema. Antes iam para o e-mail do 1º usuário admin.
- **Painel mais limpo:**
  - título curto ("Processo de revisão", com o nome do trabalho embaixo);
  - Editar, Cancelar e Excluir agrupados em "Mais";
  - cada participante virou um cartão compacto (view `filament/article-review/participant-card`) com contato, situação, datas, versão e ficha, e os botões logo abaixo;
  - o botão "Enviar lembrete" substitui o clique no nome;
  - instruções padrão visíveis na página do processo;
  - histórico sem linhas em branco.
- O nome passou a ser escrito "Prof. Atilio", sem acento, em todos os textos.

- **Telegram:** todo aviso a participante passa por `notifyParticipant()`, que manda e-mail (se houver) e Telegram (se vinculado).
  - O chat vem de `article_reviewers.telegram_chat_id`, vinculado pelo bot com `/start rev_<token>` a partir do botão "Receber avisos no Telegram" na página do participante. Se não houver, vem da conta (`users.telegram_chat_id`) ou do aluno (`students.telegram_chat_id`): **orientandos já vinculados recebem sem fazer nada**.
  - Quem não tem e-mail, mas tem Telegram, conta como "alcançável" (`isReachable()`) e recebe a vez por lá.
  - O professor recebe no Telegram todos os avisos que vão por e-mail, enviados ao admin que tem `telegram_chat_id`.
  - O webhook (`TelegramWebhookController`) trata `rev_` e vincula também os outros processos com o mesmo e-mail.
  - O painel mostra a marca "📲 Telegram" no cartão de quem tem Telegram vinculado.
  - Migration `2026_10_08_000002_add_telegram_to_article_reviewers.php`.

- **Portal do aluno:** o orientando vinculado a um processo (pelo e-mail ou pelo "Puxar de um orientando") vê "Meus Trabalhos → **Revisão de Artigos**" no próprio menu, com o selo "Sua vez" quando o arquivo está com ele. A mesma página `App\Filament\Revisor\Pages\MeusProcessos` (slug `processos-de-revisao`) está registrada nos painéis `portal` e `revisor`. No portal, o item só aparece para quem participa de algum processo.

### Correções incluídas
- **Participantes não eram salvos no cadastro** (bug desde 2026-10-06): o registro recém-criado ainda não tinha `status` no objeto (o default vinha do banco). A regra "lista travada depois de iniciar" considerava isso como "não é rascunho" e desabilitava o Repeater na hora de salvar os relacionamentos; o Filament não salva campos desabilitados. Agora status vazio conta como rascunho, e os models têm `$attributes` com o status padrão. O modal "Enviar despacho" avisa quando não há participantes. **Processos cadastrados antes desta correção estão sem participantes:** inclua-os em "Participantes → Incluir participante" ou em "Editar".
- **Rate limit:** o `throttle:N,1` do Laravel conta **todas** as rotas do mesmo IP juntas. Abrir a página, baixar o arquivo e salvar a ficha esgotava o limite do envio (erro 429). As rotas passaram a usar limitadores nomeados com contador por rota (`article-review-read|download|write|auth`, em `AppServiceProvider`).

## Arquivos
- Migration `database/migrations/2026_10_08_000001_expand_article_reviews_participants.php`:
  - `article_reviews`: `work_type`, `require_login`, `notify_all`, `signup_*`, `version_restores`;
  - `article_reviewers`: `email` passa a nullable; `user_id`, `phone`, `author_order`, `token_encrypted` e os campos da ficha, `self_registered`, `removed_*`;
  - tabela nova `article_review_files`.
- Models: `ArticleReview`, `ArticleReviewer` (CRediT, checklist, ORCID, link, status `removed`) e o novo `ArticleReviewFile`.
- Services: `ArticleReviewService` (reescrito: fila, ficha, cadastro, restauração, arquivos, fechamento, andamento) e o novo `DocxInspector`.
- `ArticleReviewController` (rotas por token e por conta, ficha, acesso/senha, cadastro, complementares).
- Views:
  - `resources/views/article-review/show.blade.php` (reescrita);
  - `signup.blade.php`, `dossier.blade.php` e `partials/create-account.blade.php` (novas).
- Filament:
  - `ArticleReviewResource` e `Pages/ViewArticleReview` (reescritos);
  - `app/Providers/Filament/ReviewerPanelProvider.php`, `app/Filament/Revisor/Pages/MeusProcessos.php` e `resources/views/filament/revisor/meus-processos.blade.php` (novos).
- Outros: `bootstrap/providers.php`, `app/Models/User.php` (`canAccessPanel` revisor), `app/Notifications/ResetPasswordNotification.php`, `app/Filament/Pages/Auth/StudentLogin.php`, `app/Providers/AppServiceProvider.php` (rate limiters), `routes/web.php`, `app/Console/Commands/SendArticleReviewReminders.php` (ignora quem não tem e-mail).
- Testes: `tests/Feature/ArticleReviewFlowTest.php`, com 6 testes cobrindo o fluxo completo, o cadastro, a senha/Área do Revisor, a detecção de alterações e as ações do painel.

## Deploy
`git pull` → `php artisan migrate --force` (3 migrations) → `php artisan filament:optimize-clear && php artisan optimize:clear` (há **painel Filament novo**: `revisor`) → `php artisan queue:restart`.
A `APP_KEY` do servidor **não pode mudar**: ela descriptografa os links guardados. Se mudar, basta usar "Gerar link novo".

## Como importar para o prof-aid.com
1. Copiar os arquivos acima e rodar a migration (depende da de 2026-10-06).
2. Registrar `ReviewerPanelProvider` em `bootstrap/providers.php`. No multi-tenant, filtrar `ArticleReview` e a Área do Revisor pelo tenant.
3. Trocar "Prof. Atilio" (disclaimer, e-mails, páginas) pelo nome do dono da conta. O `notifyProfessor` hoje pega o 1º admin; no SaaS, usar o dono do processo.
4. Conferir se o papel `reviewer` não conflita com papéis do prof-aid, e ajustar `canAccessPanel` e a notificação de reset de senha.
