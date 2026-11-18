#!/usr/bin/env bash
# Instala/atualiza o código do OJS na raiz do repositório, na versão de OJS_VERSION.
# É o equivalente ao "composer install" do profatilio: roda no servidor via SSH
# depois do git clone/pull. Também funciona localmente (Git Bash).
#
#   bash scripts/install-ojs.sh                 # baixa e extrai a versão de OJS_VERSION
#   OJS_TARBALL=/caminho/ojs.tar.gz bash scripts/install-ojs.sh   # usa um pacote já baixado
#
# Não toca em config.inc.php, public/ (uploads) nem nos arquivos versionados do projeto.
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"
VERSION="$(tr -d '[:space:]' < OJS_VERSION)"
URL="https://pkp.sfu.ca/ojs/download/ojs-${VERSION}.tar.gz"

echo "==> OJS ${VERSION} em ${ROOT}"

TMP="$(mktemp -d "${TMPDIR:-$HOME}/ojs-install.XXXXXX")"
trap 'rm -rf "$TMP"' EXIT

if [ -n "${OJS_TARBALL:-}" ]; then
    cp "$OJS_TARBALL" "$TMP/ojs.tar.gz"
else
    echo "==> Baixando ${URL}"
    if command -v curl >/dev/null; then curl -fSL --retry 3 -o "$TMP/ojs.tar.gz" "$URL"
    else wget -q -O "$TMP/ojs.tar.gz" "$URL"; fi
fi
gzip -t "$TMP/ojs.tar.gz"

# Arquivos do pacote que NÃO devem sobrescrever os nossos nem ir para produção
EXCLUDES=(
    config.inc.php README.md robots.txt .htaccess .gitignore
    .github .husky .eslintrc.cjs .php-cs-fixer.php .prettierignore
    cypress.config.js cypress.travis.env.json jsconfig.json phpdoc.dist.xml
    schemaspy.properties vite.config.js vite.config.frontend.js
    'public/*'
)
args=()
for e in "${EXCLUDES[@]}"; do args+=(--exclude="ojs-${VERSION}/$e"); done

echo "==> Extraindo"
tar -xzf "$TMP/ojs.tar.gz" -C "$ROOT" --strip-components=1 "${args[@]}"

mkdir -p public cache/t_cache cache/t_compile cache/_db
[ -f public/index.html ] || : > public/index.html

# .htaccess de proteção nas pastas internas (e exceções para o navegador)
bash "$ROOT/scripts/protect-dirs.sh"

# Correções no código do OJS (erro 1615 do MySQL na hospedagem)
bash "$ROOT/scripts/patch-ojs.sh"

# Permissões típicas de hospedagem compartilhada (PHP roda como o usuário da conta)
if [ "$(uname -s)" = "Linux" ]; then
    find . -path ./.git -prune -o -type d -exec chmod 755 {} + 2>/dev/null || true
    find . -path ./.git -prune -o -type f -exec chmod 644 {} + 2>/dev/null || true
    if [ -f config.inc.php ]; then chmod 640 config.inc.php; fi
fi

echo "$VERSION" > .ojs-installed-version
echo "==> Pronto."
if [ -f config.inc.php ] && grep -q '^installed = On' config.inc.php; then
    echo "    OJS já instalado. Se a versão mudou, rode:"
    echo "      php tools/upgrade.php check && php tools/upgrade.php upgrade"
else
    echo "    Primeira instalação: acesse o site e conclua o instalador web."
fi
