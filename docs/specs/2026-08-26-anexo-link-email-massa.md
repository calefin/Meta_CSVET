# Anexo (via link de download) no envio de e-mail em massa

- **Data:** 2026-08-26
- **Origem:** pedido para conseguir anexar arquivos (ex: PDF de edital) ao enviar e-mail em massa para os alunos. Em vez de anexar o arquivo bruto em cada mensagem da fila (o que multiplicaria o peso por destinatário), o e-mail traz um botão de download com um link assinado. **Atualização no mesmo dia:** o campo passou a aceitar múltiplos arquivos (até 5), não só um.
- **Status de importação para prof-aid.com:** pendente

## Comportamento

1. Na página "Enviar E-mail" (`/admin/enviar-email-alunos`), a seção "Mensagem" ganha um campo opcional "Anexar arquivos" (múltiplo, até 5 arquivos, PDF/Word/Excel/PNG/JPG, até 8MB cada).
2. Ao clicar em "Enviar E-mail", para cada arquivo anexado:
   - O arquivo é salvo em `storage/app/email-attachments/` (disco `local`, privado).
   - É gerado um link assinado (`URL::temporarySignedRoute`), válido por 60 dias, apontando para a rota `email.anexo.download` (um link por arquivo).
   - Os mesmos links são usados em todos os e-mails da fila (para os alunos) e na cópia enviada ao admin — os arquivos físicos não são duplicados nem anexados ao corpo MIME de cada mensagem.
3. No corpo do e-mail (template `emails/bulk-email.blade.php`), aparece um bloco "📎 Este e-mail tem N arquivo(s) anexado(s)" com um botão "Baixar {nome}" para cada arquivo, entre o conteúdo da mensagem e o rodapé do Telegram.
4. O aluno clica no botão e é redirecionado para `EmailAttachmentController@download`, que valida a assinatura da URL (middleware `signed`) e faz o download do arquivo do disco `local` — não exige login do aluno, já que ele pode abrir o e-mail em qualquer dispositivo/sessão.
5. Os arquivos permanecem em `storage/app/email-attachments/` mesmo depois do envio (necessário para os links continuarem funcionando pelos 60 dias de validade da assinatura). Não há limpeza automática implementada ainda.

## Arquivos alterados

- `app/Filament/Pages/EnviarEmailAlunos.php` — campo `FileUpload::make('anexos')->multiple()->maxFiles(5)` no formulário; em `enviarEmail()`, monta uma lista `['name' => ..., 'url' => ...]` (um item por arquivo, cada um com seu link assinado) e passa via `linkedAttachments` para cada `BulkEmailAlunos` (fila e cópia do admin); limpa o campo `anexos` ao resetar o formulário após o envio.
- `app/Mail/BulkEmailAlunos.php` — construtor ganha `linkedAttachments` (array), repassado para a view. **Atenção:** essa propriedade é deliberadamente chamada `linkedAttachments`, não `attachments` — o `Mailable` base do Laravel já usa `public $attachments` internamente (populado por `attach()`/`attachAll()` e consumido em `send()`), então reaproveitar esse nome quebraria silenciosamente o envio. `attachments()` (o método, não a propriedade) continua retornando `[]` — arquivo nunca é anexado ao MIME.
- `resources/views/emails/bulk-email.blade.php` — bloco `.attachment-block` agora itera `$linkedAttachments` e renderiza um botão de download por arquivo, exibido apenas quando a lista não está vazia.
- `app/Http/Controllers/EmailAttachmentController.php` (novo) — `download()` valida que o `path` da query pertence a `email-attachments/` e existe no disco `local`, e retorna o download; a validação da assinatura é feita pelo middleware `signed` da própria rota. Não muda com múltiplos arquivos (cada link já aponta pra um path específico).
- `routes/web.php` — nova rota `GET /email/anexo/download` (`email.anexo.download`), com middleware `signed`.

## Como importar para o prof-aid.com

1. Copiar os 5 arquivos acima para os mesmos caminhos.
2. Confirmar que o disco `local` está configurado em `config/filesystems.php` (padrão do Laravel, geralmente já existe).
3. Não depende de `storage:link` nem de disco `public` — o download é servido via controller autenticado por assinatura, não por URL pública direta.
4. Sem migration nova.
5. Atenção ao nome da propriedade do Mailable (`linkedAttachments`, não `attachments`) — ver nota acima.

## Possíveis melhorias futuras (não implementadas)

- Limpeza automática de `storage/app/email-attachments/` para arquivos com mais de ~60-90 dias (ex: comando agendado), já que hoje eles se acumulam indefinidamente.
- Mostrar o tamanho de cada arquivo no botão de download, para o aluno ter noção antes de clicar.
