# Erro 500 ao abrir as revisões sistemáticas (MySQL, GROUP BY do PRISMA)

**Data:** 2026-10-23
**Status:** corrigido no profatilio.com.br · pendente no prof-aid.com

## Problema

Em produção, a página de **todas** as revisões sistemáticas (`/admin/revisoes-sistematicas/{id}`, "Abrir" na lista e na Central das Metanálises) dava 500. A lista, a edição e a Central abriam normalmente. Os downloads "Relatório", "Contagens PRISMA" e "Pacote para o OSF" também quebravam. O portal do aluno não usa o PRISMA e não era afetado.

## Causa

`SystematicReviewService::prisma()` conta os motivos de exclusão com `GROUP BY ft_reason` / `GROUP BY ta_reason` a partir de `$review->records()`. Essa relação já vem com `orderBy('seq')`, e o SQL final ficava:

```sql
select ta_reason, count(*) as n from systematic_review_records
where ... group by ta_reason order by seq asc, n desc
```

O Laravel liga o modo strict do MySQL (`ONLY_FULL_GROUP_BY`), e o MySQL recusa a consulta inteira (erro 1055), **mesmo sem nenhum registro importado**. Por isso, quebrava em todos os projetos. A consulta de `ta_reason` entrou no commit 890e03d. Os testes usam SQLite, e o MariaDB local do XAMPP aceita essa consulta, então nada acusava o erro antes da produção.

## O que mudou

- `app/Services/SystematicReviewService.php`: `->reorder()` nas duas consultas agrupadas do `prisma()`.
- `tests/Concerns/GuardsMysqlGroupBy.php` (novo), usado pelo `tests/TestCase.php` em **toda** a suíte. Ele examina cada SQL executado no teste e reprova `GROUP BY` com `ORDER BY` de coluna que não está agrupada, não é agregada e não é alias do SELECT. É a regra do MySQL que o SQLite não aplica. Com o guarda, a suíte inteira passou sem outro caso.
- `tests/fixtures/pipeline_v11_estrutura_real.json` (novo): a estrutura do `pipeline_P01.json` v1.1 real, com todos os textos específicos do protocolo trocados por `synthetic_N`. Só sobram os valores comuns a 15 ou mais dos 20 protocolos.
- `tests/Feature/SystematicReviewImportedPagesTest.php` (novo):
  - **Admin:** a dupla é aprovada na seleção antes da importação do JSON, como em produção. O admin abre a lista, a página da revisão, a edição, a Central, todas as abas (relation managers) e os downloads do PRISMA, antes e depois de a dupla assinar o termo e conversar.
  - **Aluno da dupla:** vê os dois projetos (líder num, revisor no outro), assina o termo, vê o título e o papel e escreve na conversa. Recebe 403 no painel do professor.
  - Teste unitário do guarda.

## Como importar para o prof-aid.com

1. Aplicar o `reorder()` em `prisma()`, se o código for o mesmo.
2. Copiar o trait `GuardsMysqlGroupBy` e usá-lo no `TestCase` base. Rodar a suíte e corrigir o que ele acusar: relações com `orderBy`/`latest` padrão usadas em `groupBy` são as suspeitas.
3. Sem migration.
