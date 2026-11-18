#!/usr/bin/env bash
# Verifica se o servidor atende aos requisitos do OMP antes da instalação.
# Uso (via SSH, na pasta do site):  bash scripts/check-server.sh
ok=0; warn=0; fail=0
pass(){ echo "  [OK]    $*"; ok=$((ok+1)); }
alert(){ echo "  [AVISO] $*"; warn=$((warn+1)); }
bad(){ echo "  [FALHA] $*"; fail=$((fail+1)); }

PHP="${PHP:-php}"
echo "== Ferramentas"
for c in git tar gzip curl; do
    if command -v "$c" >/dev/null; then pass "$c"; else
        if [ "$c" = curl ] && command -v wget >/dev/null; then pass "wget (no lugar de curl)"; else bad "$c não encontrado"; fi
    fi
done

echo "== PHP (CLI: $(command -v "$PHP"))"
if ! command -v "$PHP" >/dev/null; then bad "php não encontrado"; exit 1; fi
v=$("$PHP" -r 'echo PHP_VERSION;')
if "$PHP" -r 'exit(version_compare(PHP_VERSION,"8.2.0",">=")?0:1);'; then pass "PHP $v"; else bad "PHP $v (OMP 3.5 exige 8.2+). Ajuste no MultiPHP Manager e use /usr/local/bin/php ou ea-php8x"; fi
for ext in mysqli pdo_mysql mbstring intl xml dom simplexml zip gd curl fileinfo json openssl bcmath iconv ftp; do
    if "$PHP" -r "exit(extension_loaded('$ext')?0:1);"; then pass "ext $ext"; else
        case $ext in bcmath|ftp|iconv) alert "ext $ext ausente (opcional)";; *) bad "ext $ext ausente";; esac
    fi
done
mem=$("$PHP" -r 'echo ini_get("memory_limit");'); up=$("$PHP" -r 'echo ini_get("upload_max_filesize");'); post=$("$PHP" -r 'echo ini_get("post_max_size");')
echo "  memory_limit=$mem upload_max_filesize=$up post_max_size=$post"
echo "  (recomendado: memory_limit >= 256M, uploads >= 64M; ajuste no MultiPHP INI Editor)"

echo "== MySQL"
if command -v mysql >/dev/null; then pass "$(mysql --version 2>/dev/null | head -1)"; else alert "cliente mysql não disponível (ok: o instalador web conecta direto)"; fi

echo "== Pastas"
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
FILES="${OMP_FILES_DIR:-$HOME/books_files}"
if [ -d "$FILES" ] && [ -w "$FILES" ]; then pass "files_dir $FILES (gravável)"; else bad "crie a pasta privada: mkdir -p $FILES && chmod 750 $FILES"; fi
case "$FILES" in "$ROOT"*) bad "files_dir está DENTRO da raiz pública; mova para fora";; esac
[ -f "$ROOT/index.php" ] && pass "código do OMP instalado ($(cat "$ROOT/.omp-installed-version" 2>/dev/null))" || alert "OMP ainda não instalado: rode bash scripts/install-omp.sh"
if grep -q "cPanel-generated handler" "$ROOT/.htaccess" 2>/dev/null; then
    alert "o cPanel inseriu um bloco 'php handler' no .htaccess; ele será tratado como alteração local no git (veja o README)"
fi

echo
echo "Resumo: $ok OK, $warn avisos, $fail falhas"
[ "$fail" -eq 0 ]
