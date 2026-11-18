# "Habilitar" etapa de TCC não reconhecia conteúdo do aluno já preenchido (só olhava arquivo anexado)

- **Data:** 2026-09-15
- **Origem:** o professor relatou que envios de Objetivos, Materiais e Métodos, Introdução e afins apareciam para habilitar, mas depois de habilitados não eram listados em "Aguardando Meu Despacho" — só em "Processos Acadêmicos em Andamento" com status "Aguardando X do Aluno", mesmo o aluno já tendo escrito o conteúdo ao enviar.
- **Status de importação para prof-aid.com:** pendente

## Causa raiz

Em 2026-09-14 (commit anterior a este) foi corrigido um bug parecido: o
interceptor de `"Habilitado"` no hook `static::updating` de
`app/Models/DocumentSubmission.php` decidia se a bola ficava com o professor
ou voltava para o aluno olhando **só** `google_drive_file_id` (arquivo
anexado). Isso cobria o caso do aluno montar a submissão com um arquivo
pronto, mas não cobria as etapas que aceitam **texto digitado direto no
formulário**, sem exigir arquivo:

- Introdução/Materiais e Métodos/Resultados/Discussão/Conclusão de TCC usam
  `metadata.tcc_stage_text` (RichEditor).
- Objetivos de TCC usa `metadata.tcc_general_objective` +
  `metadata.tcc_specific_objectives` (Textarea).
- Concepção de TCC usa `metadata.tcc_conception_area`/`_species`/`_type`/`_ideas`.

Resultado: aluno escreve o texto (sem anexar arquivo) e envia para
habilitar → o professor habilita → como `google_drive_file_id` está vazio, o
interceptor manda de volta para `"Aguardando X do Aluno"` em vez de
`"Aguardando Correção de X"` → processo nunca aparece em "Aguardando Meu
Despacho", só no widget "Processos Acadêmicos em Andamento".

Esta correção generaliza a checagem para todas as 7 etapas do pipeline
(Concepção + Objetivos + as 5 de redação), cada uma com suas próprias chaves
de metadata.

## Comportamento (antes/depois)

**Antes:** habilitar uma etapa de TCC só reconhecia conteúdo já entregue se
houvesse arquivo anexado; texto digitado sozinho era ignorado e o processo
voltava para "aguardando aluno".

**Depois:** habilitar uma etapa de TCC reconhece tanto arquivo anexado
quanto texto preenchido nos campos próprios de cada categoria (nova
constante `DocumentSubmission::TCC_STAGE_CONTENT_KEYS`). Se qualquer um dos
dois estiver preenchido, o processo nasce diretamente em
"Aguardando Correção de X" (bola com o professor).

Nenhum status novo foi introduzido — reaproveita os mesmos valores de
`TCC_STAGE_STATUS_PAIRS` já existentes em `PROFESSOR_STATUSES`.

## Arquivos alterados

- `app/Models/DocumentSubmission.php` — interceptor de `"Habilitado"` no hook `updating` passa a checar `google_drive_file_id` OU qualquer chave de `TCC_STAGE_CONTENT_KEYS[$document_type]`; nova constante `TCC_STAGE_CONTENT_KEYS` (ao lado de `TCC_STAGE_STATUS_PAIRS`).
- `app/Console/Commands/FixStuckAwaitingStudentStatuses.php` — refatorado para reutilizar `TCC_STAGE_STATUS_PAIRS`/`TCC_STAGE_CONTENT_KEYS` do model em vez de duplicar a mesma lista num `MAP` próprio (evita as duas listas saírem de sincronia).
- `tests/Feature/EditDocumentSubmissionCrashTest.php` — 2 testes novos: habilitar Materiais e Métodos só com texto (`tcc_stage_text`), e habilitar Objetivos só com texto (`tcc_general_objective`/`tcc_specific_objectives`).

## Como importar para o prof-aid.com

1. Copiar o interceptor de `"Habilitado"` e a constante `TCC_STAGE_CONTENT_KEYS` de `app/Models/DocumentSubmission.php`.
2. Se o fork usa `FixStuckAwaitingStudentStatuses`, copiar a versão refatorada também (ou reaplicar a mesma lógica manualmente).
3. Nenhuma migration, config ou pacote novo.

## Remediação dos processos já presos (produção)

O comando `app:fix-stuck-awaiting-student-statuses` (criado em 2026-09-01,
ver spec `2026-09-01-etapas-tcc-criadas-pelo-aluno-presas-aguardando-aluno.md`)
já cobre esse cenário — ele detecta qualquer processo em `"Aguardando X do
Aluno"` com conteúdo real preenchido, independente de ter sido criado direto
pelo aluno (bug de 01/09) ou habilitado pelo professor (bug desta spec).
Depois do deploy (`git pull` + passos de sempre), rodar via SSH:

```bash
php artisan app:fix-stuck-awaiting-student-statuses --dry-run
```

Conferir a lista e, se bater, rodar sem `--dry-run` para aplicar de verdade,
depois `php artisan queue:restart`. Idempotente.

## Verificação local

- `php -l` limpo no model e no comando (`C:\xampp\php\php.exe -l`).
- Suite `tests/Feature/EditDocumentSubmissionCrashTest.php` rodada localmente
  (sqlite `:memory:` do `phpunit.xml`, não depende do MySQL do XAMPP): 7
  testes, 20 assertions, todos passando — incluindo os 2 novos cenários
  (Materiais e Métodos e Objetivos habilitados só com texto).
- Não foi possível rodar `app:fix-stuck-awaiting-student-statuses --dry-run`
  contra produção neste ambiente (MySQL local do XAMPP não estava no ar; SSH
  para o servidor não tem chave/senha configurada nesta máquina) — o
  professor vai rodar manualmente via SSH e conferir a lista.
