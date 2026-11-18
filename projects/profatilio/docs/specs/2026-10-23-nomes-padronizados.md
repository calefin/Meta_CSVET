# Nomes padronizados na seleção e nas revisões

**Data:** 2026-10-23
**Status:** implementado no profatilio.com.br · pendente no prof-aid.com

## Problema

O cadastro de usuários já padronizava o nome (`User::setNameAttribute`). A inscrição da seleção (`research_applications`) gravava o nome como foi digitado, e a aprovação copiava esse nome para o triador (`systematic_review_screeners`). Por isso, a Central, as mensagens (`{nome}`) e o CSV de contatos mostravam "JULIA ZAGOLIN COSTA" e "Carvalho pinto".

## O que mudou

- **Mutators de `name`** com `User::normalizeName` em `ResearchApplication` e em `SystematicReviewScreener`. A conferência do nome na inscrição (`name_confirmation`) e a assinatura do termo já comparavam sem diferenciar maiúsculas, então não mudam.
- **`User::normalizeName`:** os algarismos romanos II, III e IV ficam em maiúsculas.
- **`php artisan app:normalizar-nomes`:**
  - lista o que mudaria em inscrições, triadores e usuários;
  - com `--aplicar`, grava por update direto (sem mexer em `updated_at` e sem disparar avisos);
  - é idempotente.
- **Textos assinados não mudam:** `terms_text`, `terms_signature` e o texto de adesão continuam como foram assinados. O SHA-256 continua conferindo, e o PDF do termo mostra o nome da assinatura.

## Como importar para o prof-aid.com

Copiar os dois mutators e o comando, e rodar o comando primeiro sem `--aplicar`. Sem migration.
