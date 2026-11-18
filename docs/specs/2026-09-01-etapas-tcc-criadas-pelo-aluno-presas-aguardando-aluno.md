# Concepção/Objetivos/etapas de redação do TCC criadas pelo aluno ficam presas em "Aguardando ... do Aluno"

- **Data:** 2026-09-01
- **Origem:** o professor relatou processos (`CONCL-TCC-2026-0001`, `DISC-TCC-2026-0001`, `MM-TCC-2026-0001`, `MM-TCC-2026-0002`, `OBJ-TCC-2026-0016`, `OBJ-TCC-2026-0018`, `OBJ-TCC-2026-0019`) que só apareciam no widget "Processos Acadêmicos em Andamento" do dashboard, mesmo já tendo conteúdo/arquivo enviado pelo aluno — não aparecendo em "Trabalhos dos Alunos" (aba "Aguardando Meu Despacho") como pendência de correção sua.
- **Status de importação para prof-aid.com:** pendente

## Causa raiz

Para os tipos de documento que pulam a triagem manual (`Concepção de TCC`,
`Objetivos de TCC`, `Introdução de TCC`, `Materiais e Métodos de TCC`,
`Resultados de TCC`, `Discussão de TCC`, `Conclusão de TCC`), o hook
`static::creating` de `app/Models/DocumentSubmission.php` sempre definia o
status inicial como `"Aguardando X do Aluno"` — independentemente de quem
estava criando o registro ou se ele já vinha com conteúdo.

Isso é correto para o registro-placeholder **vazio** criado pelo comando
`app:backfill-tcc-stages` (`BackfillTccStagesForActiveProjects`): nesse caso
o aluno ainda precisa abrir o processo depois e preencher/enviar.

Mas o formulário "Enviar Novo Documento" (`CreateDocumentSubmission`) permite
ao próprio aluno criar diretamente um desses tipos **já com o conteúdo
obrigatório preenchido** (texto da etapa em `metadata.tcc_stage_text`,
objetivos em `metadata.tcc_general_objective`/`tcc_specific_objectives`,
etc. — campos `required()` no formulário). Quando o aluno usa esse caminho
(em vez de editar um placeholder pré-existente), o registro nasce via
`create()`, não via `update()` — e só o hook `static::updating` sabe fazer a
transição de `"Aguardando X do Aluno"` para `"Aguardando Correção de X"`
(`canStudentEdit()` + `!is_draft_save`). Um `create()` nunca passa por
`updating`, então o processo **nasce e fica preso para sempre** no status
"aguardando aluno", mesmo com o conteúdo já entregue — ficando fora de
`PROFESSOR_STATUSES` (não aparece em "Aguardando Meu Despacho") e fora da
badge de navegação do menu.

## Comportamento (antes/depois)

**Antes:** aluno cria uma submissão nova de uma dessas 7 categorias já
preenchida → nasce em `"Aguardando X do Aluno"` → nunca migra sozinha para
revisão do professor → só aparece no widget de dashboard, sem contar para a
badge/aba de "Aguardando Meu Despacho".

**Depois:** o hook `creating` verifica se quem está criando é o próprio
aluno autenticado (`auth()->user()->role === 'student'`). Se for, o status
inicial já nasce como o de correção do professor (reaproveita os mesmos
valores que `updating` já usa para essas categorias: `Aguardando Definição
de Tema`, `Aguardando Correção dos Objetivos`, `Aguardando Correção da
Introdução`, `Aguardando Correção de Materiais e Métodos`, `Aguardando
Correção dos Resultados`, `Aguardando Correção da Discussão`, `Aguardando
Correção da Conclusão`). Criação automática/sem usuário autenticado (backfill,
ou um professor criando um placeholder para o aluno) continua caindo no
status `"Aguardando X do Aluno"` de antes.

Nenhum status novo foi introduzido — só passou a usar, na criação, os mesmos
valores que já existiam em `PROFESSOR_STATUSES`, nas cores do
`ActiveSubmissionsWidget`, nas colunas do `SubmissionsKanban` e nas abas de
`ListDocumentSubmissions`. Por isso não há risco do "status órfão" já visto
antes (ver specs de 28/08 e 31/08).

## Arquivos alterados

- `app/Models/DocumentSubmission.php` — hook `static::creating`: o `match ($submission->document_type)` que define o status inicial agora bifurca por `$isStudentSubmittingContent = auth()->check() && auth()->user()->role === 'student'` para as 7 categorias citadas.
- `app/Console/Commands/FixStuckAwaitingStudentStatuses.php` (novo) — comando de remediação para os processos já presos em produção (ver abaixo).

## Como importar para o prof-aid.com

1. Copiar `app/Models/DocumentSubmission.php` (hook `creating`) e, se houver processos presos na base do fork, também `app/Console/Commands/FixStuckAwaitingStudentStatuses.php`.
2. Nenhuma migration, config ou pacote novo.
3. Roda pelo comando abaixo depois do deploy; não precisa de correção manual processo a processo.

## Remediação dos processos já presos (produção)

Sem acesso ao banco de produção neste ambiente. Depois do deploy (`git pull` + o de sempre), rodar via SSH na raiz do projeto:

```bash
php artisan app:fix-stuck-awaiting-student-statuses --dry-run
```

Confere a lista (mostra `PROCESS_ID (Nome do Aluno): 'status antigo' -> 'status novo'`
para todo processo das 7 categorias que estiver em `"Aguardando X do Aluno"`
mas já tiver arquivo ou o texto/objetivo obrigatório preenchido em
`metadata`). Se a lista bater com o esperado, rodar de verdade (sem
`--dry-run`):

```bash
php artisan app:fix-stuck-awaiting-student-statuses
```

O comando usa `->save()` no Eloquent (não SQL bruto), então dispara
normalmente o hook `updating` — grava no histórico do processo e envia o
e-mail/Telegram de "status atualizado" para o aluno, como qualquer mudança
de status feita pelo professor. É idempotente: rodar de novo não duplica nem
reverte nada, porque só mexe em registros que ainda estejam exatamente no
status "aguardando aluno".

## Verificação local

- `php -l` limpo no model e no novo comando (`C:\xampp\php\php.exe -l`).
- Não foi possível rodar contra os processos reais citados pelo professor — o
  MySQL local (XAMPP) não estava no ar neste ambiente e os dados são de
  produção (HostGator). Fix e comando de remediação validados por leitura de
  código: confirmado que os campos de conteúdo (`tcc_stage_text`,
  `tcc_general_objective`/`tcc_specific_objectives`,
  `tcc_conception_area`/`tcc_conception_species`/`tcc_conception_type`/`tcc_conception_ideas`)
  são `required()` no formulário de criação para essas categorias
  (`DocumentSubmissionResource.php`), então sua presença em `metadata` é um
  sinal confiável de que o aluno realmente enviou conteúdo (não é um
  placeholder vazio de backfill).
