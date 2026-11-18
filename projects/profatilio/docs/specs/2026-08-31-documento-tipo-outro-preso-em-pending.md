# Documentos de tipo "Outro" habilitados ficam presos em `pending` e somem do "Meu Despacho"

- **Data:** 2026-08-31
- **Origem:** o professor relatou 5 processos (`DOC-2026-0003` a `DOC-2026-0007`, tipo "Outro", parados entre 4 dias e 1 semana) que aparecem no widget "Ações Urgentes" do dashboard com botão "Avaliar", mas não aparecem na listagem principal ("Trabalhos") onde ele normalmente responde às pendências.
- **Status de importação para prof-aid.com:** pendente

## Causa raiz

`document_type = 'Outro'` é uma opção legítima e oferecida no formulário de
envio (`DocumentSubmissionResource.php`, Select de "Tipo de Documento"), mas
nunca foi incluída nos três `match ($submission->document_type)` de
`app/Models/DocumentSubmission.php` que decidem para qual status um processo
vai quando: (1) o aluno reenvia após correção, (2) o professor troca o tipo
do documento, e (3) o professor clica em "Habilitar" (interceptação do status
`'Habilitado'`). Nos três, `'Outro'` caía no `default => 'pending'`.

`'pending'` é o status interno de "aguardando auditoria automática de IA"
(rótulo "Aguardando Auditoria de IA") — mas nenhum job de auditoria é
disparado para documentos do tipo "Outro" (o único disparo de
`AuditDocumentWithAI` no código é uma action manual desativada,
`->visible(false)`). Ou seja: o processo fica **parado para sempre** em
`pending`, sem ninguém (IA ou professor) processá-lo.

Pior: `'pending'` está em `STUDENT_STATUSES` (bucket "a bola está com o
aluno"), então na listagem principal (`ListDocumentSubmissions`) esses
processos caem na aba "Aguardando o Aluno" — não em "Aguardando Meu
Despacho", onde o professor esperava encontrá-los para agir. Eles só
apareciam no widget "Ações Urgentes" porque `'pending'` também está
(corretamente, para o caso de uso original de auditoria de IA) na whitelist
desse widget — dando a falsa impressão de urgência sem um lugar de fato para
resolver.

Além disso, o dropdown "Novo Status do Processo" da action "Publicar
Despacho" (`despachar`) e o Select de status do formulário principal também
não tinham um `match` para `'Outro'`, então nem manualmente era possível
mover esses processos para um status de revisão do professor pela UI — o
dropdown só oferecia `pending`/`reviewing`/`approved`/`needs_corrections`/
`returned_signed`/`failed` (o fallback genérico `default`).

## Comportamento (antes/depois)

**Antes:** processo tipo "Outro" habilitado (ou reenviado, ou com tipo
alterado para "Outro") vai para `pending`, some do fluxo normal, fica preso
indefinidamente, e nem o dropdown de despacho manual oferece como avançá-lo.

**Depois:** `'Outro'` entra no mesmo grupo de `'Relatório'`, `'Artigo
Científico'`, `'Relato de Caso'`, `'Trabalho de Congresso'`, `'Relatório de
Estágio'`, `'Relatório de Aula'` → vai para `'Aguardando Revisão do
Professor'` (já mapeado corretamente em `PROFESSOR_STATUSES` e no
`UrgentActionsWidget`) nos três `match()` do model, e nos dois dropdowns de
status do `DocumentSubmissionResource.php`.

## Arquivos alterados

- `app/Models/DocumentSubmission.php` — adiciona `'Outro'` ao grupo `=> 'Aguardando Revisão do Professor'` nos 3 `match ($submission->document_type)` do hook `updating` (reenvio do aluno, troca de tipo, interceptação de `'Habilitado'`).
- `app/Filament/Resources/DocumentSubmissionResource.php` — adiciona `'Outro'` ao mesmo grupo nos 2 dropdowns de status (`Select::make('status')` do formulário principal, linha ~574, e da action `despachar`, linha ~1522).

## Como importar para o prof-aid.com

1. Copiar os 2 arquivos acima.
2. Nenhuma migration, config ou pacote novo.
3. **Corrige só código novo/futuro** — não migra dados retroativamente.
   Processos já presos em `pending` continuam presos até uma ação manual
   (ver "Remediação" abaixo).
4. Mesmo alerta dos dois fixes anteriores (28/08 e 31/08): qualquer
   `document_type` próprio do fork que não apareça em nenhum `match()` cai no
   `default => 'pending'`, com o mesmo problema. Revisar todos os
   `document_type` oferecidos no Select de "Tipo de Documento" contra os 3
   `match()` do model antes de assumir que está tudo coberto.

## Remediação dos 5 processos já presos

Sem acesso ao banco de produção neste ambiente. Depois do deploy, para cada
um dos processos (`DOC-2026-0003` a `DOC-2026-0007`): abrir o processo →
"Publicar Despacho" → selecionar "Aguardando Revisão do Professor" (agora
disponível no dropdown) → salvar. Um clique por processo, sem precisar de
comando artisan.

## Verificação local

- `php -l` limpo nos 2 arquivos (`C:\xampp\php\php.exe -l`).
- Sem dados de teste locais para reproduzir um processo tipo "Outro" preso;
  fix validado por leitura de código — confirmado que `'Outro'` é opção real
  do Select de tipo de documento (`DocumentSubmissionResource.php:157`) e que
  nenhum dos 3 `match()` do model nem os 2 dropdowns de status a cobriam
  antes desta mudança.
