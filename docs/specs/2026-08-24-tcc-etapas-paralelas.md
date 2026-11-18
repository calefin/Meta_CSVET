# Etapas de redação do TCC liberadas em paralelo (sem ordem obrigatória)

- **Data:** 2026-08-24
- **Origem:** pedido do Prof. Atílio para reduzir a rigidez do pipeline de redação do TCC — em vez de obrigar o aluno a enviar Introdução → Materiais e Métodos → Resultados → Discussão → Conclusão nessa ordem estrita, o aluno deve poder enviar o segmento que quiser, quando quiser, e o orientador vai corrigindo cada um em paralelo. A versão final do TCC só é liberada quando todos os segmentos obrigatórios estiverem aprovados.
- **Depende de:** [[2026-08-19 — Pipeline de Etapas do TCC]] (implementação original das 5 etapas sequenciais, commit `7e08934`, ver `PROJECT_MEMORY.md`/memória do assistente).
- **Status de importação para prof-aid.com:** pendente

## Comportamento anterior (removido)

Cada etapa só podia ser criada depois que a etapa anterior estivesse com status
"Processo Concluído - X Aprovada(os)":
`Objetivos → Introdução → Materiais e Métodos → (Resultados | Discussão) → Conclusão → TCC`.
Resultados já era a única etapa "opcional" (não bloqueava a Discussão).

## Comportamento novo

1. **Gate único de entrada:** os Objetivos de TCC continuam precisando estar aprovados (`Processo Concluído - Objetivos Aprovados`) antes de liberar qualquer uma das 5 etapas de redação. Isso não mudou.
2. **Sem ordem entre si:** uma vez os Objetivos aprovados, o aluno pode enviar Introdução, Materiais e Métodos, Resultados, Discussão e Conclusão em qualquer ordem e a qualquer momento — inclusive todas ao mesmo tempo. Não existe mais checagem de "a etapa anterior precisa estar aprovada" entre esses 5 tipos.
3. **Resultados continua opcional** — não entra na trava do TCC final (mesma regra de antes, só que agora não bloqueia nem afeta nenhuma outra etapa).
4. **Gate único de saída (TCC final):** o tipo `TCC` (versão final) só pode ser criado quando Introdução, Materiais e Métodos, Discussão e Conclusão estiverem **todas** com status aprovado. Resultados fica de fora dessa checagem.
5. Se faltar alguma etapa obrigatória, a mensagem de erro no formulário já lista pelo nome quais faltam (ex.: "Faltam: Discussão, Conclusão").

## Fonte única de verdade (evita duplicar a lista de etapas)

Foram criados dois métodos estáticos em `App\Models\AcademicTitle`, usados tanto pela validação quanto pela UI:

- `AcademicTitle::buildTccWritingSegments(int $studentId): array` — monta os 5 segmentos com estado (`not_started` | `pending` | `done`), legenda e a submissão mais recente de cada tipo. O estado `done` compara o status exato de aprovação por etapa (ex.: `Processo Concluído - Introdução Aprovada`) — **não** usa `isConcluded()` puro, porque esse método também retorna `true` para status `Cancelado`, o que contaria um envio cancelado como aprovado por engano.
- `AcademicTitle::missingTccWritingStages(int $studentId): array` — retorna os rótulos das etapas obrigatórias ainda não aprovadas (vazio = pode solicitar o TCC final).

## Arquivos alterados

- `app/Models/AcademicTitle.php` — novos métodos `buildTccWritingSegments()` e `missingTccWritingStages()` (logo após `buildTccSteps()`).
- `app/Filament/Resources/DocumentSubmissionResource.php` — na regra (`->rules()`) do campo `document_type`, substituída a cadeia `$tccStagePrerequisites` (pré-requisito etapa-a-etapa) por:
  - checagem única de Objetivos aprovados para liberar qualquer uma das 5 etapas;
  - checagem de `AcademicTitle::missingTccWritingStages()` para liberar o tipo `TCC`.
- `app/Filament/Widgets/TccPipelineWidget.php` — novo método `getWritingSegments()` que expõe o checklist para a view.
- `resources/views/filament/widgets/tcc-pipeline-widget.blade.php` — abaixo do stepper de 3 macro-fases (Concepção/Objetivos/Redação, inalterado), passou a renderizar um checklist com os 5 segmentos (ícone de check quando aprovado, com legenda "(opcional)" em Resultados), visível assim que os Objetivos estiverem aprovados. A frase antiga "Cada etapa concluída libera automaticamente a próxima" foi removida do subtítulo por não ser mais verdade para as etapas de redação.
- `app/Models/DocumentSubmission.php` — o e-mail/Telegram automático enviado na primeira "Concepção de TCC" do aluno (`sendTccPipelineExplanationIfFirst`) foi reescrito para descrever 4 macro-passos (Concepção → Objetivos → Redação em paralelo → TCC final) em vez de 3, deixando explícito que as 5 etapas de redação liberam juntas e podem ser enviadas em qualquer ordem.

## Como importar para o prof-aid.com

1. Portar as mudanças dos 4 arquivos acima. Nenhuma migration é necessária — a mudança é só de regra de validação e apresentação; os `document_type`/`status` usados já existiam.
2. **Atenção ao nome dos status por etapa** se o prof-aid.com tiver traduzido/alterado os textos de status (`Processo Concluído - Introdução Aprovada`, etc.) — os métodos novos em `AcademicTitle` dependem desses strings baterem exatamente com os usados no `DocumentSubmissionResource` (nos selects de status do formulário/despacho). Se os textos forem diferentes lá, ajustar o array `$segments` dentro de `buildTccWritingSegments()`.
3. Se o prof-aid.com (multi-tenant) tiver mais de um professor/orientador com pipelines de TCC diferentes por tenant, avaliar se esse comportamento (paralelo, gate único de saída) deve ser configurável por tenant ou se vira padrão fixo — no profatilio.com.br é fixo porque há um único orientador.

## Notas / edge cases já tratados

- Uma etapa cancelada (`status = 'Cancelado'`) não conta como aprovada nem bloqueia o aluno de reenviar — aparece no checklist como "Cancelado — envie novamente" e o tipo continua disponível pra criação (a regra de duplicidade do formulário já impede duas submissões *ativas* do mesmo tipo simultaneamente, mas isso é anterior a esta mudança e não foi alterado).
- O checklist no Dashboard (`TccPipelineWidget`) só aparece depois que os Objetivos estiverem aprovados — antes disso o aluno só vê o stepper de 3 macro-fases, como já era.
