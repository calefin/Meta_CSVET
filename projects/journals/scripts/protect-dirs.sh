#!/usr/bin/env bash
# Segunda camada de proteção (como o protect.php do profatilio): .htaccess de
# negação nas pastas internas, caso o mod_rewrite da raiz falhe.
# Chamado pelo script de instalação; pode ser rodado sozinho no servidor:
#
#   bash scripts/protect-dirs.sh
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

DENY='# Gerado por scripts/protect-dirs.sh: acesso direto proibido
<IfModule mod_authz_core.c>
    Require all denied
</IfModule>
<IfModule !mod_authz_core.c>
    Order deny,allow
    Deny from all
</IfModule>'
for d in api cache classes controllers dbscripts docs jobs locale pages registry schemas tools \
         lib/pkp/api lib/pkp/classes lib/pkp/controllers lib/pkp/dtd lib/pkp/includes \
         lib/pkp/jobs lib/pkp/locale lib/pkp/pages lib/pkp/registry lib/pkp/schemas \
         lib/pkp/tools lib/pkp/xml lib/pkp/lib/vendor; do
    if [ -d "$d" ]; then printf '%s\n' "$DENY" > "$d/.htaccess"; fi
done

# Exceções dentro de lib/pkp/lib/vendor: bibliotecas que o navegador carrega
# direto (envio de arquivos/foto do perfil e editor de texto com seus plugins,
# temas e traduções). Só arquivos estáticos; .php continua bloqueado.
ALLOW='# Gerado por scripts/protect-dirs.sh: arquivos estáticos usados pelo navegador
<FilesMatch "\.(js|css|gif|png|jpe?g|svg|woff2?|eot)$">
    <IfModule mod_authz_core.c>
        Require all granted
    </IfModule>
    <IfModule !mod_authz_core.c>
        Order allow,deny
        Allow from all
    </IfModule>
</FilesMatch>'
for d in lib/pkp/lib/vendor/moxiecode/plupload/js lib/pkp/lib/vendor/tinymce/tinymce; do
    if [ -d "$d" ]; then printf '%s\n' "$ALLOW" > "$d/.htaccess"; fi
done

# Nenhum script executável é servido de public/ (uploads).
# (Sem php_flag: em PHP-FPM/CGI da hospedagem compartilhada ele causa erro 500.)
mkdir -p public
printf '%s\n' '# Gerado por scripts/protect-dirs.sh' '<FilesMatch "\.(php|phtml|phar|pl|py|cgi|sh)$">' \
    '    Require all denied' '</FilesMatch>' > public/.htaccess

echo "==> .htaccess de proteção gravados"
