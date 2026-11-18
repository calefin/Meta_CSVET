# TCC Completo: "Documento Assinado — mas precisa de correções"

**Data:** 2026-11-03

## Problema

No "TCC Completo (Envio Único)", o professor às vezes já assina o documento, mas ainda
quer correções. Só existiam "Aguardando Ajustes do Aluno" (sem registro de versão
assinada) e "Processo Concluído - TCC Completo Aprovado" (encerra o processo). Além
disso, a próxima devolução trocava `google_drive_returned_file_id` e o PDF assinado
deixava de aparecer.

## Solução

Status novo `DocumentSubmission::SIGNED_NEEDS_CORRECTIONS_STATUS` =
**"Documento Assinado - Aguardando Correções do Aluno"**, só no TCC Completo.

- **Não conclui:** está em `STUDENT_ACTIONABLE_STATUSES`, então o aluno edita e
  reenvia. O reenvio volta para "Aguardando Correção do TCC Completo" e as devoluções
  continuam normalmente.
- **Exige PDF assinado novo:** é preciso anexar um arquivo de devolução novo, porque o
  campo vem pré-preenchido com a devolução anterior. Há três barreiras:
  - validação no Select de status do Editar;
  - aviso no "Publicar Despacho";
  - `InvalidArgumentException` no model, como rede de segurança.

  O PDF passa pela mesma checagem de assinatura digital (`/ByteRange`, `/Type /Sig`)
  dos status de conclusão e gera o hash forense e o recibo.
- **Registro permanente:**
  - o arquivo é renomeado como `{processo} - {aluno} - Assinado_Versao_Final_{YmdHis}.pdf`, um nome que nunca colide com o `Assinado_Corrigido` das devoluções seguintes;
  - `metadata.signed_final_file` guarda a versão mais recente e `metadata.signed_final_at` a data;
  - `metadata.signed_final_history[]` guarda o caminho, a data e o sha256 de cada versão.
- **Download:** `GET /documento/download/{id}/signed_final` (mesma rota e permissões
  do download atual). O formulário mostra "✍️ Versão Assinada Registrada" com o link
  para o professor e para o aluno enquanto existir.
- **Avisos:** o e-mail e o Telegram têm um texto próprio ("assinado, mas ainda precisa
  de correções") com o link da versão assinada.

## Arquivos alterados

- `app/Models/DocumentSubmission.php`:
  - constante e `signedFinalFile()`;
  - `returnedFileStateHasNewFile()`;
  - `STUDENT_ACTIONABLE_STATUSES`;
  - renomeação, validação e registro no bloco de devolução;
  - guarda do status;
  - textos do e-mail e do Telegram.
- `app/Filament/Resources/DocumentSubmissionResource.php`:
  - opção nos 2 dropdowns do TCC Completo;
  - regra e texto de ajuda no Select;
  - Placeholder com o link;
  - checagem no "Publicar Despacho";
  - filtro de status.
- `app/Http/Controllers/DocumentAuditController.php`: tipo `signed_final` no `downloadFile()`.
- Listas de status:
  - `ListDocumentSubmissions::STUDENT_STATUSES`;
  - `SubmissionsKanban` (coluna corrections);
  - `ActiveSubmissionsWidget`;
  - `AcademicProjectResource\...\SubmissionsRelationManager`.
- Teste: `tests/Feature/TccCompletoSignedNeedsCorrectionsTest.php`.

## Importação no prof-aid.com

Sem migration (usa `metadata`). Copiar a constante e as chaves de metadata, e incluir
o status em todas as listas de status do lado do aluno, senão ele cai em "Não
Classificado".
