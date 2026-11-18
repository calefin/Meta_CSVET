#!/bin/sh
set -e

if [ ! -f .env ]; then
  cp .env.example .env
fi

# Em produção, APP_KEY DEVE vir como variável de ambiente do compose (uma vez
# só, gerada com `php artisan key:generate --show`) — nunca deixar isto gerar
# uma chave nova a cada deploy, ou toda sessão/dado criptografado anterior
# vira lixo. Só gera automaticamente se realmente não houver nenhuma (uso
# local/dev), e mesmo assim escreve no .env, que é descartável no container.
if [ -z "$APP_KEY" ] && ! grep -q "^APP_KEY=base64" .env 2>/dev/null; then
  php artisan key:generate --force
fi

# O healthcheck do compose considera o MySQL "healthy" um pouco antes da
# porta realmente aceitar conexões — evita a corrida tentando migrate
# algumas vezes antes de desistir.
for i in 1 2 3 4 5 6 7 8 9 10; do
  php artisan migrate --force && break
  echo "Aguardando o banco ficar pronto (tentativa $i)..."
  sleep 3
done

# NUNCA usar public/index.php direto como router do `php -S` — ele não sabe
# "deixar passar" arquivos estáticos, então todo pedido de CSS/JS caía no
# Laravel inteiro e voltava como text/html (200, mas o navegador recusa
# aplicar como CSS — página carregava sem erro nenhum, só sem estilo algum).
# server.php é o router de verdade que o `php artisan serve` usa por baixo,
# com um `file_exists()` que serve estático direto — mas ele usa getcwd()
# pra achar os arquivos, e `-t public` NÃO muda o cwd do processo (só define
# a docroot do PHP). Por isso entra em public/ antes de subir o servidor.
cd public
exec php -S 0.0.0.0:8000 -t . ../vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php
