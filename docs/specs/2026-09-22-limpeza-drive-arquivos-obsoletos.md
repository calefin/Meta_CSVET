# Limpeza do Google Drive: arquivos obsoletos de correções antigas

**Data:** 2026-09-22

## Contexto
Conta do Drive perto do limite (25,2 de 30 GB). Medição em 2026-09-22: o app ocupa ~202 MB
(submissions 120 MB, returns 67 MB, livewire-tmp 15 MB, receipts 1 MB); o resto é conteúdo pessoal
(vídeos .MOV, gravações do Meet, Gmail/Fotos 12 GB) — tratado manualmente pelo professor.

## Onde sobram arquivos obsoletos
- **Lixeira**: ao reenviar correção, o arquivo novo é renomeado para o mesmo nome do anterior
  (`{processo} - {aluno} - {etapa}.pdf`) e o `move()` do adaptador masbug manda o antigo para a lixeira
  (`usePermanentDelete = false`) — que conta na cota.
- **Duplicatas**: o Drive aceita nomes repetidos na mesma pasta.
- **Órfãos**: arquivos cujo nome não aparece em nenhum registro do banco (versões substituídas,
  uploads abandonados, `livewire-tmp` antigo de antes do disco temporário ir para `local`).

## Implementação
- `App\Services\DriveCleanupService` — lista direto pela API do Drive (id, nome, tamanho, datas), cruza com
  TODAS as colunas de texto de todas as tabelas do banco atual (exceto cache/sessions/migrations), com
  normalização (escapes JSON, URL-encoding, acentos) — conservador: na dúvida, mantém.
  - Pastas limpáveis: `submissions`, `returns`, `temp_signatures`, `livewire-tmp`. `receipts` e
    `certificates` nunca são tocados (só o que já estiver na lixeira).
  - Duplicatas/órfãos só com mais de 7 dias (`--dias`).
  - Exclusão **definitiva por ID** (`files->delete`). Nunca `Storage::delete($path)`: com nomes repetidos o
    adaptador apaga todas as cópias, inclusive a em uso.
  - Órfãos são reconferidos contra o banco no momento da exclusão.
  - A raiz vem do próprio adaptador (reflection em `root`): o adaptador tratou o `GOOGLE_DRIVE_FOLDER_ID`
    como nome e criou uma pasta chamada "<id>" dentro dele — é lá que estão as pastas do app.
- `App\Jobs\DeleteObsoleteDriveFiles` — lotes de 40 que se re-enfileiram (worker da HostGator mata jobs longos).
- `php artisan app:limpar-drive [--apagar] [--categoria=trash|duplicate|orphan] [--dias=7]` — simulação por padrão.
- Página admin **Configurações → Limpeza do Drive** (`LimpezaDrive`): cota com barra, cards por categoria com
  botão "Apagar N arquivo(s)" (confirmação), tabela dos 300 maiores, progresso com poll.
- Testes: `tests/Feature/DriveCleanupServiceTest.php` (classificação, reconferência de órfão, página + fila).
- Validado em simulação contra o Drive real (sem apagar).

## Uso recomendado em produção
1. `php artisan app:limpar-drive` (simulação) ou "Analisar Drive agora" na página.
2. Conferir a lista de órfãos; apagar primeiro Lixeira e livewire-tmp.

## Importação no prof-aid.com
Service + Job + Command + Page + view + teste. Ajustar `CLEANABLE_FOLDERS` às pastas do fork.
