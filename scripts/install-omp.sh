#!/usr/bin/env bash
# Instala/atualiza o código do OMP na raiz do repositório, na versão de OMP_VERSION.
# É o equivalente ao "composer install" do profatilio: roda no servidor via SSH
# depois do git clone/pull. Também funciona localmente (Git Bash).
#
#   bash scripts/install-omp.sh                 # baixa e extrai a versão de OMP_VERSION
#   OMP_TARBALL=/caminho/omp.tar.gz bash scripts/install-omp.sh   # usa um pacote já baixado
#
# Não toca em config.inc.php, public/ (uploads) nem nos arquivos versionados do projeto.
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"
VERSION="$(tr -d '[:space:]' < OMP_VERSION)"
URL="https://pkp.sfu.ca/omp/download/omp-${VERSION}.tar.gz"

echo "==> OMP ${VERSION} em ${ROOT}"

TMP="$(mktemp -d "${TMPDIR:-$HOME}/omp-install.XXXXXX")"
trap 'rm -rf "$TMP"' EXIT

if [ -n "${OMP_TARBALL:-}" ]; then
    cp "$OMP_TARBALL" "$TMP/omp.tar.gz"
else
    echo "==> Baixando ${URL}"
    if command -v curl >/dev/null; then curl -fSL --retry 3 -o "$TMP/omp.tar.gz" "$URL"
    else wget -q -O "$TMP/omp.tar.gz" "$URL"; fi
fi
gzip -t "$TMP/omp.tar.gz"

# Arquivos do pacote que NÃO devem sobrescrever os nossos nem ir para produção
EXCLUDES=(
    config.inc.php README.md robots.txt .htaccess .gitignore
    .github .husky .eslintrc.cjs .php-cs-fixer.php .prettierignore
    cypress.config.js cypress.travis.env.json jsconfig.json phpdoc.dist.xml
    schemaspy.properties vite.config.js vite.config.frontend.js .scrutinizer.yml
    'public/*'
)
args=()
for e in "${EXCLUDES[@]}"; do args+=(--exclude="omp-${VERSION}/$e"); done

echo "==> Extraindo"
tar -xzf "$TMP/omp.tar.gz" -C "$ROOT" --strip-components=1 "${args[@]}"

mkdir -p public cache/t_cache cache/t_compile cache/_db
[ -f public/index.html ] || : > public/index.html

# Primeira instalação: o instalador web precisa de um config.inc.php gravável
# (o do pacote é excluído acima para nunca sobrescrever a configuração existente)
if [ ! -f config.inc.php ]; then
    cp config.TEMPLATE.inc.php config.inc.php
    chmod 644 config.inc.php
    echo "==> config.inc.php criado a partir do modelo (o instalador web vai preenchê-lo)"
fi

# .htaccess de proteção nas pastas internas (e exceções para o navegador)
bash "$ROOT/scripts/protect-dirs.sh"

# Permissões típicas de hospedagem compartilhada (PHP roda como o usuário da conta)
if [ "$(uname -s)" = "Linux" ]; then
    find . -path ./.git -prune -o -type d -exec chmod 755 {} + 2>/dev/null || true
    find . -path ./.git -prune -o -type f -exec chmod 644 {} + 2>/dev/null || true
    if [ -f config.inc.php ]; then chmod 640 config.inc.php; fi
fi

echo "$VERSION" > .omp-installed-version
echo "==> Pronto."
if [ -f config.inc.php ] && grep -q '^installed = On' config.inc.php; then
    echo "    OMP já instalado. Se a versão mudou, rode:"
    echo "      php tools/upgrade.php check && php tools/upgrade.php upgrade"
else
    echo "    Primeira instalação: acesse o site e conclua o instalador web."
fi
