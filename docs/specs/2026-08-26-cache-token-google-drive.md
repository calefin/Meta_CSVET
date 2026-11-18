# Cache do access_token do Google Drive + download em streaming

- **Data:** 2026-08-26
- **Origem:** duas alunas relataram que o envio do Termo de Aceite de Orientação "fica carregando e não conclui". Investigação encontrou uma correção já escrita (não commitada) numa sessão anterior, com o diagnóstico correto: a cada requisição, `Storage::extend('google', ...)` cria um `Google\Client` novo sem nenhum cache de token, forçando um round-trip completo de `refresh_token` → `access_token` na `accounts.google.com` em **toda** operação de Drive (upload, download, exists, list) — mesmo quando o token anterior ainda era válido. Em uma hospedagem compartilhada isso é lento o suficiente para parecer travado, especialmente no upload feito pelo aluno (que acontece de forma síncrona dentro do próprio request do Livewire/Filament).
- **Status de importação para prof-aid.com:** pendente

## Comportamento

1. `app/Providers/AppServiceProvider.php`, dentro de `Storage::extend('google', ...)`:
   - Antes de pedir um novo token, verifica se já existe um `access_token` em cache (`Cache::get('gdrive:access_token:' . md5(clientId|refreshToken))`, mesmo backend de cache do `CachedGoogleDriveAdapter`, driver `database`).
   - Se houver, usa o token cacheado direto no `Google\Client`.
   - Só chama `fetchAccessTokenWithRefreshToken()` (o round-trip lento) quando o `Google\Client` realmente considera o token expirado — e, ao renovar, guarda o novo token em cache com TTL um pouco menor que o `expires_in` real (margem de 120s).
2. `app/Http/Controllers/DocumentAuditController.php` — `downloadFile()` e `downloadByPath()` deixam de carregar o arquivo inteiro em memória (`$targetDisk->get(...)`) e passam a fazer streaming (`response()->stream()` + `fpassthru($targetDisk->readStream(...))`), reduzindo uso de memória e tempo até o primeiro byte em arquivos grandes.

## Arquivos alterados

- `app/Providers/AppServiceProvider.php`
- `app/Http/Controllers/DocumentAuditController.php`

## Como importar para o prof-aid.com

1. Copiar os dois arquivos acima para os mesmos caminhos.
2. Confirmar que `CACHE_STORE` não é `array`/não-persistente (aqui é `database`) — do contrário o cache do token não sobrevive entre requisições e a correção não tem efeito.
3. Sem migration nova (usa a mesma tabela de cache já existente).

## Deploy

Este fix ainda **não foi enviado para produção** (deploy manual via SSH + `git pull`). Precisa ser deployado para resolver o problema relatado pelas alunas.
