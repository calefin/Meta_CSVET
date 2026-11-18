# Status órfão sumindo da lista de trabalhos + rede de segurança contra recorrência

- **Data:** 2026-08-28
- **Origem:** o professor relatou que vários trabalhos ficavam presos "aguardando verificação da IA" sem aparecer na lista de correções ("Aguardando Meu Despacho") nem no widget "Ações Urgentes" do dashboard — perdendo o fluxo de continuidade.
- **Status de importação para prof-aid.com:** pendente

## Causa raiz

`app/Jobs/AuditDocumentWithAI.php` define dois status para o `DocumentSubmission`
que nunca foram referenciados em nenhum outro lugar do código:

- `'auditing'` (linha 49) — enquanto o job está processando.
- `'reviewed'` (linha 204) — auditoria concluída sem apontar desvio de escopo,
  aguardando o parecer do professor.

Toda tela que classifica processos por status usa listas `whereIn('status', [...])`
hardcoded (`ListDocumentSubmissions::PROFESSOR_STATUSES`/`STUDENT_STATUSES`, e a
query do `UrgentActionsWidget`). Como `'reviewed'`/`'auditing'` nunca foram
adicionados a nenhuma delas, esses processos só apareciam na aba "Todos os Envios"
(sem filtro) — e ainda com o status bruto em inglês, sem tradução.

## Comportamento (antes/depois)

**Antes:** processo com status `reviewed`/`auditing` não aparece em "Aguardando
Meu Despacho", nem no widget "Ações Urgentes" do dashboard, nem tem rótulo
traduzido — some do radar do professor.

**Depois:**
1. `reviewed` e `auditing` entram em `PROFESSOR_STATUSES` e na query do
   `UrgentActionsWidget` — voltam a aparecer nos lugares certos.
2. Rótulos traduzidos ("Em Auditoria pela IA" / "Auditoria de IA Concluída -
   Aguardando Parecer") no histórico do processo, em `MeuHistorico` e na coluna
   de status da tabela principal.
3. **Rede de segurança nova**, para este tipo de bug não se repetir de forma
   silenciosa com o próximo status esquecido:
   - `ListDocumentSubmissions::applyUnclassifiedScope()`/`unclassifiedQuery()` —
     query para qualquer status fora de todos os buckets conhecidos
     (professor/aluno/concluído/triagem). Vira a aba **"⚠️ Não Classificado"**,
     que só aparece quando há algo nela, e passa a ser a aba padrão ao abrir a
     página (prioridade sobre "Para Habilitar"), porque um status não
     reconhecido é sinal de bug, não de rotina.
   - Widget novo no dashboard admin, `UnclassifiedStatusWidget`
     (`canView()` só é `true` quando a query acima encontra algo) — fica
     invisível no dia a dia e só aparece quando algo realmente escapou do mapa.
4. **SLA visual** no `UrgentActionsWidget`: a coluna "Aguardando Desde" fica
   âmbar com ≥3 dias parado no mesmo status, e vermelho + ícone de fogo com ≥7
   dias — para distinguir "chegou ontem" de "esquecido há semanas".

## Arquivos alterados

- `app/Jobs/AuditDocumentWithAI.php` — nenhuma mudança (só a origem do bug).
- `app/Filament/Resources/DocumentSubmissionResource/Pages/ListDocumentSubmissions.php` — adiciona `'reviewed'`/`'auditing'` a `PROFESSOR_STATUSES`; `STUDENT_STATUSES`/`CONCLUDED_STATUSES` viram `public`; nova constante `TRIAGE_STATUS`; novos métodos `applyUnclassifiedScope()`/`unclassifiedQuery()`; nova aba "Não Classificado"; `getDefaultActiveTab()` prioriza ela.
- `app/Filament/Widgets/UrgentActionsWidget.php` — adiciona `'reviewed'`/`'auditing'` à query; SLA visual (âmbar/vermelho+ícone) na coluna "Aguardando Desde".
- `app/Filament/Widgets/UnclassifiedStatusWidget.php` — **novo arquivo**, widget de dashboard só visível quando há status não classificado.
- `app/Filament/Pages/MeuHistorico.php` — rótulos `'auditing'`/`'reviewed'` em `getStatusLabel()`.
- `app/Filament/Resources/DocumentSubmissionResource.php` — rótulos e cor de badge para `'auditing'`/`'reviewed'` (coluna de status principal e timeline de histórico do processo); filtro dropdown "Status" da tabela ganhou as opções que faltavam (`reviewed`, `auditing`, `needs_corrections`, `returned_signed`, `Cancelado`, `Assinatura Realizada e Auditada`, `Em análise final para assinatura institucional`, `Aguardando Triagem`, `Aguardando Ajustes no Laudo pelo Aluno`) — mesmo padrão de drift do bug principal, mas nesse filtro opcional.
- `app/Filament/Widgets/ActiveSubmissionsWidget.php` — cor de badge para `'reviewed'`/`'auditing'` na coluna "Etapa Atual" (antes caíam no cinza genérico do `default`).
- `tests/Feature/ExampleTest.php` — reativa o trait `RefreshDatabase` (estava comentado desde o commit inicial do projeto). Sem ele, o teste batia em `/` num SQLite `:memory:` sem nenhuma migration rodada; funcionava por acaso enquanto a home não dependia do banco, e passou a falhar (`no such table: courses`) quando `welcome.blade.php` ganhou a seção de cursos. Não é causado por nenhuma mudança desta spec — só corrigido junto por ter sido encontrado durante a verificação.

## Como importar para o prof-aid.com

1. Copiar os 9 arquivos acima (8 alterados + 1 novo).
2. Nenhuma migration, config ou pacote novo.
3. `UnclassifiedStatusWidget` é auto-descoberto via `discoverWidgets()` no
   `AdminPanelProvider` — não precisa registrar manualmente, a menos que o fork
   tenha uma lista explícita de widgets sem discovery automático (conferir).
4. Se o fork já tiver status próprios adicionados que não existem aqui, revisar
   se algum cai na aba "Não Classificado" incorretamente — ajustar os arrays
   `PROFESSOR_STATUSES`/`STUDENT_STATUSES`/`CONCLUDED_STATUSES` conforme o fluxo
   local antes de assumir que é bug.

## Verificação local

- `php -l` limpo nos 7 arquivos (PHP do XAMPP não está no PATH — ver [[local_dev_windows]]).
- `ListDocumentSubmissions::unclassifiedQuery()->count()` executado via `php artisan tinker` contra o MySQL local sem erro de SQL.
- `php artisan serve` local respondeu 200 em `/admin/login` e 302 (redirect de login, sem 500) em `/admin` — confirma que o boot do Filament aceita a widget nova.
- Banco local estava vazio (0 `DocumentSubmission`), então não foi possível conferir visualmente uma linha `reviewed` presa antes/depois — só a validade da query e do boot.
- `php artisan test`: inicialmente 20 passaram / 1 falhou (`tests/Feature/ExampleTest.php`, `SQLSTATE... no such table: courses`) — falha pré-existente e não relacionada às mudanças acima, causada pelo trait `RefreshDatabase` estar comentado nesse teste desde o commit inicial do projeto. Corrigida junto (ver arquivo acima). Suíte completa final: **21 passaram, 0 falharam** (124 assertions).
