# Datas/horas em descompasso com o horário de Brasília

**Data:** 2026-09-22

## Sintoma
Horários exibidos no painel (colunas `dateTime`, "Última Atividade", PDFs, e-mails, verificador de documento)
apareciam 3h adiantados.

## Causa
`config/app.php` tinha `'timezone' => 'UTC'`. O Laravel grava as datas como texto no fuso da aplicação, e
o Filament/Carbon exibem nesse mesmo fuso: tudo saía em UTC. Só ~15 pontos convertiam manualmente com
`->timezone('America/Sao_Paulo')`. O agendador também rodava em UTC (resumo "08:00" saía às 05:00 BRT).

## Correção
- `config/app.php`: `'timezone' => env('APP_TIMEZONE', 'America/Sao_Paulo')`.
- Migration `2026_09_22_000002_shift_utc_timestamps_to_sao_paulo.php`: desloca −3h todas as colunas
  `timestamp`/`datetime` do banco atual (dados antigos gravados em UTC), exceto campos de relógio digitados
  pelo usuário (`blog_posts.published_at`, `invite_tokens.expires_at`). `down()` desfaz (+3h). Só MySQL/MariaDB.
  - Atenção: `Schema::getTableListing()` sem schema lista tabelas de TODOS os bancos da conta no Laravel 12 —
    usar `getTableListing($database, false)`, senão a mesma tabela é deslocada várias vezes (pego em teste).
- Testado em MariaDB local: 15:00 UTC → 12:00 (up) e volta com rollback; `published_at` do blog intacto.

## Efeitos colaterais esperados
- Agendamentos (`routes/console.php`) passam a disparar no horário de Brasília escrito (08:00, 07:30, 09:00).

## Deploy
Em modo manutenção, para nenhuma gravação cair entre a troca de config e a migration:
`php artisan down && git pull && php artisan config:cache && php artisan migrate --force && php artisan up`
Se o `.env` de produção tiver `APP_TIMEZONE=UTC`, remover ou trocar para `America/Sao_Paulo`.

## Importação no prof-aid.com
Mesma config + migration (rodar uma única vez por banco).
