# Central das Metanálises: projetos, conversas, atividades e mensagens enviadas numa tela só

**Data:** 2026-10-22
**Status:** implementado no profatilio.com.br · pendente no prof-aid.com

## Problema

Para acompanhar os 20 projetos do programa, o professor precisava abrir revisão por revisão: os termos assinados e a conversa ficavam na aba Triadores e na aba Conversa de cada projeto, e o histórico no fim da página. Ele também não tinha onde ver as mensagens que já havia enviado aos alunos.

## O que mudou

Nova página do admin: **Pesquisa & Publicações › Central das Metanálises** (`/admin/central-metanalises`, `App\Filament\Pages\CentralMetanalises`). Só o admin acessa (`canAccess`).

- **Indicadores:**
  - projetos aguardando resposta;
  - termos assinados (líder + revisor);
  - mensagens dos últimos 7 dias;
  - protocolos congelados;
  - projetos com link do OSF.
- **Tabela de projetos.** Cada linha mostra:
  - o código PET/PAT e o PNN;
  - a etapa e o nº de registros;
  - o líder e o revisor, com o termo (✓ data ou ⏳ pendente) e o nº de decisões de título e resumo;
  - se o protocolo está congelado e o link do OSF;
  - a conversa: total de mensagens, a última, e o selo "aguardando você" quando a última mensagem é de aluno (linha destacada);
  - os links "Abrir" e "Escrever".
- **Aba Conversas:**
  - mensagens de todos os projetos, das mais novas para as mais antigas (até 100);
  - filtro por projeto e "só mensagens de alunos";
  - caixa "Escrever na conversa do projeto", que usa `SystematicReviewService::postMessage` (mesma regra, registro e aviso por e-mail da aba Conversa da revisão);
  - "Responder" já seleciona o projeto.
- **Aba Atividades:** o `history` de todas as revisões juntos (termo assinado, protocolo congelado, checagem aleatória, etc.), com filtro por projeto (até 150).
- **Aba Enviadas por mim:**
  - **e-mails:** os enviados aos participantes do programa (inscritos na seleção e triadores), lidos de `email_logs`. Envios em massa com o mesmo assunto em até 30 min viram um item só, com a lista de destinatários e a pré-visualização do e-mail em `iframe sandbox`;
  - **conversas:** as mensagens do professor nas conversas;
  - **avisos automáticos:** só com "incluir os avisos automáticos do site". "Escrito pelo professor" é o assunto terminado em ` — Prof. Atilio`, menos os convites;
  - os avisos de conversa ("Nova mensagem no projeto…") ficam de fora, porque já estão na aba Conversas.
- **Badge do menu:** nº de projetos cuja última mensagem é de aluno (`awaitingReplyIds`).

Sem migration.

## Arquivos

- `app/Filament/Pages/CentralMetanalises.php` (novo)
- `resources/views/filament/pages/central-metanalises.blade.php` (novo, CSS próprio `cm-*`)
- `tests/Feature/SystematicReviewStudentAccessTest.php`: teste `test_central_shows_all_projects_conversations_activity_and_sent_messages_to_admin_only`

## Importar no prof-aid.com

Copiar os dois arquivos. Depende de `SrMessage`, de `SystematicReviewService::postMessage` (spec `2026-10-22-termo-online-conversa-e-mensagens.md`) e de `EmailLog` (registrado pelo listener `MessageSent` do `AppServiceProvider`). Ajustar `isWrittenMessage()` ao sufixo de assunto do professor no fork.
