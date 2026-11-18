# Envio "somente assinatura": Projeto de TCC e TCC Final

**Data:** 2026-11-03

## Problema

No formulário "Enviar Novo Documento", o checkbox **"Este envio é apenas para
assinatura"** filtrava o "Tipo de Documento" para só 3 opções (Termo de Aceite de
Orientação, Plano de Estágio, Termo de Aceite de IC). O aluno que precisava só da
assinatura do professor no **Projeto de TCC** ou no **TCC final** não achava a opção
e mandava como **"Outro"** — que entra no fluxo de *revisão* ("Aguardando Revisão do
Professor"), não no de assinatura.

## Solução

Dois tipos novos de `document_type`, só de assinatura:

| Valor gravado | Rótulo | Prefixo do processo |
|---|---|---|
| `Projeto de TCC (Assinatura)` | Projeto de TCC (somente assinatura) | `PROJ-TCC-ASS` |
| `TCC Final (Assinatura)` | TCC Final (somente assinatura) | `TCC-FINAL-ASS` |

- Constante central `DocumentSubmission::SIGNATURE_ONLY_TYPES` (valor → rótulo), com
  os 2 novos + os 3 antigos.
- **O checkbox foi trocado por 3 botões no topo** (`ToggleButtons` `submission_purpose`,
  não salvo no banco), na pergunta "O que você precisa com este envio?":

  | Botão | Tipos de documento oferecidos |
  |---|---|
  | ✍️ **Para assinar** | `SIGNATURE_ONLY_TYPES`, mais "Documento Solicitado" para o professor |
  | 📝 **Para corrigir** | todos os demais |
  | 🗂️ **Só para registro** | `RECORD_ONLY_TYPES`: Avaliações / Notas de Estágio e Certificado de Apresentação em Evento |

  - A lógica fica em `DocumentSubmission::typesForPurpose()` e `purposeForType()`.
  - O "Tipo de Documento" só aparece depois da escolha e não vem mais pré-selecionado em "TCC".
  - Trocar de botão limpa um tipo que seja de outro grupo.
  - Link com `?document_type=` (ex.: CEUA no QuickActions) já marca o botão certo, em `CreateDocumentSubmission::mount()`.
- Os 2 tipos novos só aparecem na criação em "Para assinar", mas aparecem no Select ao
  editar/visualizar registros existentes.
- Fluxo igual ao do Plano de Estágio: criação → `Aguardando Triagem` → Habilitar →
  `Aguardando Assinatura do Professor` → devolução com arquivo assinado →
  `Processo Concluído - Documento Assinado Disponível`. Nenhum status novo.
- Arquivo do aluno obrigatório; aceita PDF ou DOCX.
- Não vincula a `AcademicTitle` (evita mexer no progresso do TCC, que usa o envio
  mais recente do título).

## Arquivos alterados

- `app/Models/DocumentSubmission.php` — constante `SIGNATURE_ONLY_TYPES`; os 3
  `match()` do hook `updating` (reenvio do aluno, troca de tipo, Habilitado); auto-
  conclusão ao anexar arquivo devolvido; prefixos do `process_id`.
- `app/Filament/Resources/DocumentSubmissionResource.php` — checkbox/Select de tipo,
  DOCX permitido, cor do badge, filtro de tipo, 2 dropdowns de status.
- `app/Http/Controllers/DocumentAuditController.php` — `getConcludedStatus()`.
- `app/Filament/Pages/SubmissionsKanban.php` + `resources/views/filament/pages/submissions-kanban.blade.php`.
- Badges/filtros "Termos": `UrgentActionsWidget`, `ActiveSubmissionsWidget`,
  `MyLastSubmissionsWidget`, `StudentResource\...\SubmissionsRelationManager`,
  `AcademicProjectResource\...\SubmissionsRelationManager`.
- Teste: `tests/Feature/SignatureOnlyTccTypesTest.php`.

## Importação no prof-aid.com

1. Copiar a constante e acrescentar os 2 tipos em todos os `match ($document_type)`
   listados acima (senão caem em `pending` e somem das listas).
2. Sem migration.

## Pós-deploy

Envios já feitos como "Outro" que eram só assinatura continuam como "Outro" — tratar
manualmente ("Publicar Despacho" com o arquivo assinado → "Processo Concluído -
Documento Assinado Disponível").
