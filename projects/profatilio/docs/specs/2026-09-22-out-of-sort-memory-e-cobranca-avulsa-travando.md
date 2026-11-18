# Painel travando: "Out of sort memory" em document_submissions + Cobrança Avulsa síncrona

**Data:** 2026-09-22

## Sintomas
1. Dashboard do admin quebrava com `SQLSTATE[HY001]: 1038 Out of sort memory, consider increasing server sort buffer size`
   na query do `ActiveSubmissionsWidget` (`select * from document_submissions ... order by updated_at desc limit 11`).
2. A ação "Enviar Cobrança Agora" (`StalledProjectsWidget`) travava a tela ao confirmar.

## Causa
1. `document_submissions` não tinha índice em `updated_at`. O MySQL fazia filesort carregando a linha inteira —
   incluindo o JSON `metadata` (que guarda o texto redigido das etapas de TCC, `tcc_stage_text`) — e estourava o
   `sort_buffer_size` da HostGator (não configurável em hospedagem compartilhada). Piorou conforme os alunos
   passaram a redigir texto direto no editor.
2. A cobrança avulsa usava `Mail::to()->send()` síncrono via SMTP, com `timeout => null` no mailer, e sem try/catch.
   Se o SMTP demorava, a requisição Livewire ficava pendurada.

## Correção
- Migration `2026_09_22_000001_add_updated_at_index_to_document_submissions.php`: índice `doc_sub_updated_at_idx`
  em `updated_at` → o MySQL lê na ordem do índice com LIMIT, sem filesort.
- `StalledProjectsWidget`: cobrança agora vai por `->queue()` (a cron da fila entrega em ~1 min), com try/catch,
  log de erro e notificação de falha; nome/título escapados com `e()` no HTML do e-mail.
- `config/mail.php`: `smtp.timeout` = `env('MAIL_TIMEOUT', 20)` (antes ilimitado).

## Importação no prof-aid.com
1. Copiar a migration e rodar `php artisan migrate`.
2. Aplicar a mudança no widget de orientandos parados (queue + try/catch).
3. Ajustar o timeout SMTP em `config/mail.php`.

## Deploy (produção)
`git pull && php artisan migrate --force && php artisan config:cache`

## Complemento (mesmo dia) — o índice sozinho não bastou
O erro persistiu em produção. Reforços:
- `config/database.php` (conexão `mysql`): `PDO::MYSQL_ATTR_INIT_COMMAND` → `SET SESSION sort_buffer_size = 8MB`
  (ajustável via `DB_SORT_BUFFER_SIZE`). Variável de sessão, não exige privilégio no MySQL compartilhado;
  vale para todas as listas (inclusive a lista principal de documentos, que ordena `select *` por `created_at`).
- `ActiveSubmissionsWidget`: `select` só das colunas leves (`id, process_id, student_id, document_type, title,
  status, created_at, updated_at`), sem `metadata`/textos — a ordenação não carrega mais o JSON pesado.
- Deploy precisa de `php artisan config:cache` (ou `config:clear`) para a opção nova valer.
