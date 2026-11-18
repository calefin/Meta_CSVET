# Devolução ao aluno dando 404 (validação de assinatura depois do rename no Drive)

**Data:** 2026-10-02 · **Caso reportado:** processo `/admin/document-submissions/337` (Conclusão de TCC)

## Sintoma
Alunos abriam o arquivo devolvido pelo orientador (correção/assinado) e recebiam 404
("Arquivo físico não encontrado no servidor ou nuvem").

## Causa
No hook `updating` de `DocumentSubmission`, a devolução de arquivo único era:
1. renomeada no Drive (`returns/<aleatório>.pdf` → `returns/{processo} - {Aluno} - Assinado_Corrigido.pdf`);
2. **só depois** validada: todo status começando com `Processo Concluído` exigia PDF com
   assinatura digital ICP-Brasil — inclusive "Conclusão Aprovada", "Introdução Aprovada",
   "Versão Final Definida" etc., em que o orientador devolve um PDF apenas **corrigido**.

A exceção abortava o save, mas o arquivo já tinha sido renomeado. No salvamento seguinte
(ex.: trocando o status para outro), o formulário ainda carregava o caminho antigo, que não
existe mais; o rename era pulado e esse caminho inexistente ia para o banco → 404.

## Correção
- `DocumentSubmission::statusRequiresSignature()` — status de "correção aprovada"
  (`Aprovad*`, `Versão Final Definida`, `Tema Definido`, `Projeto Arquivado`) não exigem
  mais assinatura ICP-Brasil. Os de assinatura (Documento Assinado Disponível, Termo de
  Aceite Assinado, Homologado etc.) continuam exigindo.
- A validação roda **antes** do `move()` no Drive; falha não deixa mais o arquivo renomeado.
- Recibo forense continua sendo gerado sempre que o PDF devolvido estiver assinado.
- `DocumentSubmission::expectedReturnedFilePath()` — nome padrão da devolução renomeada.
- `DocumentAuditController::downloadFile()` — se o caminho gravado não existir, procura o
  nome renomeado do próprio processo, serve o arquivo e corrige o registro (auto-reparo).
- Comando `php artisan app:reparar-devolucoes` (simulação; `--aplicar` grava) para corrigir
  todos os registros afetados de uma vez e listar os que não têm arquivo localizável.

## Arquivos
- `app/Models/DocumentSubmission.php`
- `app/Http/Controllers/DocumentAuditController.php`
- `app/Console/Commands/RepairBrokenReturnedFiles.php` (novo)

## Importação no prof-aid.com
Copiar os dois métodos novos do model, a reordenação validação→move no hook, o fallback
no download e o comando. Rodar `app:reparar-devolucoes --aplicar` após o deploy.

## Complemento — processo concluído sem a devolução (CONCL-TCC-2026-0008)
O registro 337 estava com status "Conclusão Aprovada" e `google_drive_returned_file_id = null`
(nada gravado, nenhum caminho quebrado). Causa provável: o botão "Salvar e Notificar Aluno" era
uma `Action` com `->action('publishDispatch')`, não o submit do formulário. O Filament só trava
botões de submit enquanto um upload (FileUpload) está em andamento; clicar antes de o PDF
terminar de subir salvava e notificava o aluno sem o arquivo, sem erro.

Correção em `EditDocumentSubmission`: para o professor o botão passou a ser o
`getSaveFormAction()` (submit, travado durante upload), com `is_draft_save = false`,
notificação "Despacho Publicado" e redirect para a listagem via `getRedirectUrl()` /
`getSavedNotification()`. Fluxo do aluno inalterado.
