# Termo online antes da triagem, conversa registrada do projeto, mensagens do professor e lista de contatos

**Data:** 2026-10-22
**Status:** implementado no profatilio.com.br · pendente no prof-aid.com

## Problema

1. **Os alunos não tinham onde assinar o termo do projeto.** A "Triagem Cega" (onde está o termo de confidencialidade do projeto) só aparecia no menu depois de "Liberar triagem", semanas depois da aprovação.
2. **Não havia como o professor falar só com a dupla de um trabalho, com um aluno, ou registrar o diálogo do projeto.**
3. **O professor precisava da lista de nomes e e-mails das duplas.**
4. **Os avisos do professor iam para o e-mail geral do site,** e não para o e-mail de pesquisa.

## O que mudou

### Termo online e página do projeto (portal → Triagem Cega)

- **Menu desde o início:** o projeto aparece também com a revisão em preparação (`STATUS_PROTOCOL`). O badge do menu conta também os termos pendentes.
- **Lista de projetos:** mostra o código (PET/PAT) e o papel. Sem o termo, o título real fica oculto ("assine o termo para ver o título e o protocolo") e o botão diz "Ler e assinar o termo".
- **Depois de assinar, a tela "em preparação" mostra:**
  - o papel do aluno;
  - a dupla, com nome e e-mail, e o lembrete de não comentar decisões;
  - o termo assinado;
  - o **protocolo em PDF**, pela rota `systematic-review.my-protocol`, que só serve o próprio triador ativo com termo assinado (ou o admin);
  - a conversa do projeto.
- **E-mail de aprovação:** passa a orientar a assinar o termo online assim que o projeto for cadastrado.

### Conversa registrada do projeto

- **Tabela `sr_messages`** (migration `2026_10_22_000001`… e `2026_10_22_000002_create_sr_messages_table`) e model `SrMessage`.
- **Quem participa:** o admin e os triadores ativos **com termo assinado** (`SystematicReviewService::canChat`). Ninguém apaga nem edita.
- **Avisos (`postMessage`):** quem escreve gera e-mail e Telegram aos demais participantes. A mensagem do aluno também avisa o professor.
- **Portal (`partials/sr-chat.blade.php`, CSS próprio `.tc-chat-*`):**
  - aviso fixo "🔒 Revisão cega: não exponha nada da sua triagem ou extração", com exemplos do que é proibido;
  - confirmação a cada envio;
  - o e-mail de aviso repete a regra.
- **Painel:** aba "Conversa do projeto" (`MessagesRelationManager`) com "Escrever na conversa".
- **Termo v2026.10.7:** a cláusula 6 passa a citar a conversa registrada e proíbe expor estudos, decisões, contagens ou dados extraídos.

### Mensagens do professor

- **Revisão sistemática → Triadores:**
  - "Mensagem à dupla", que também entra na conversa;
  - "Mensagem" individual, só por e-mail e Telegram.
- **Seleção → Inscrições:**
  - "Mensagem a uma dupla" (escolhe o projeto; vai ao líder e ao revisor cego);
  - "Enviar mensagem" individual (menu Mais);
  - ação em massa "Enviar mensagem aos marcados" (ex.: todos da aba Selecionados), com e-mails individuais, sem expor os outros endereços;
  - `{nome}` vira o primeiro nome;
  - fica no histórico da inscrição.
- **"Contatos das duplas (.csv)":** dupla (os dois projetos), nome, e-mail, projeto que lidera e projeto que revisa, só dos aprovados.

### E-mail do professor da pesquisa

`config('services.research.professor_email')` (env `RESEARCH_PROFESSOR_EMAIL`, padrão **calefi@csvet.com.br**) recebe os avisos da seleção e das revisões sistemáticas. A revisão de artigos continua em `ArticleReviewService::PROFESSOR_EMAIL`.

## Arquivos

- Migration `2026_10_22_000002_create_sr_messages_table`; `app/Models/SrMessage.php`; `SystematicReview::messages()` e `selectionCode()`.
- `app/Services/SystematicReviewService.php`: `messageScreeners`, `chatScreener`, `canChat`, `postMessage` e o e-mail do professor.
- `app/Services/ResearchRecruitmentService.php`: `sendMessage`, `projectPair`, `projectsWithPairs`, `contactRows`, o texto do e-mail de aprovação e o e-mail do professor.
- `app/Filament/Pages/TriagemCega.php` + `resources/views/filament/pages/triagem-cega.blade.php` + `resources/views/filament/partials/sr-chat.blade.php`.
- `ScreenersRelationManager`, `MessagesRelationManager` (novo), `SystematicReviewResource::getRelations`.
- `ResearchApplicationResource` (`messageFields`, ação individual, ação em massa), `ListResearchApplications` (mensagem à dupla, contatos .csv).
- `SystematicReviewExportController::myProtocol` + rota.
- `config/services.php` (`research.professor_email`).
- `app/Models/SystematicReviewScreener.php` (termo v2026.10.7).
- `tests/Feature/SystematicReviewStudentAccessTest.php` (3 testes, 83 asserções). Suíte completa: 105 testes passando.

## Deploy

```
git pull
php artisan migrate --force
php artisan filament:optimize-clear
php artisan optimize:clear
php artisan config:clear
```

## Como importar para o prof-aid.com

- Copiar a migration, o model, o relation manager e o partial do chat.
- Trocar "Prof. Atilio" pelo dono do processo em `postMessage`, `messageScreeners` e nos textos do partial.
- Definir `RESEARCH_PROFESSOR_EMAIL` por instalação.
