# "Parado Há" (e demais datas relativas) aparecendo em inglês

**Data:** 2026-09-22

## Sintoma
Em produção, a coluna "Parado Há" da listagem de documentos (e outras colunas com `->since()`, como
UrgentActionsWidget, MyLastSubmissionsWidget, PendingSupportTicketsWidget, UnclassifiedStatusWidget,
DeadlinesRelationManager) mostrava "2 weeks ago" em vez de "há 2 semanas".

## Causa
O idioma do Carbon dependia do locale do ambiente (APP_LOCALE / config em cache no servidor). Localmente
estava pt_BR; em produção o Carbon ficou em inglês.

## Correção
`AppServiceProvider::boot()`: `Carbon::setLocale('pt_BR')` e `CarbonImmutable::setLocale('pt_BR')` fixos.
Testado simulando `APP_LOCALE=en`: continua "há 2 semanas".

## Importação no prof-aid.com
Copiar as duas linhas no `boot()` do AppServiceProvider (ou usar o locale do tenant, se houver multi-idioma).
