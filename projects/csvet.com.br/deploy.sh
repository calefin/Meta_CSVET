#!/bin/bash
# Publica o site em ~/csvet.com.br copiando só os arquivos públicos.
# Uso no servidor: bash ~/repositories/csvet.com.br/deploy.sh
# O que já existe na pasta do domínio e não está no repositório (ex.: .well-known/) não é tocado.
set -e
cd "$(dirname "$0")"
DEPLOYPATH="$HOME/csvet.com.br"

# Atualiza e reinicia o script, para que a lista de arquivos abaixo seja sempre a da versão recém-baixada
if [ -z "$CSVET_DEPLOY_PULLED" ]; then
    git pull --ff-only
    CSVET_DEPLOY_PULLED=1 exec bash "$0"
fi

mkdir -p "$DEPLOYPATH/css" "$DEPLOYPATH/img" "$DEPLOYPATH/fonts" "$DEPLOYPATH/partials"
cp index.php privacidade.php termos.php data.php .htaccess robots.txt sitemap.xml favicon.ico site.webmanifest "$DEPLOYPATH/"
cp css/style.css "$DEPLOYPATH/css/"
cp img/logo-csvet.png img/logo-csvet-branco.png img/fundo.webp img/og-image.jpg img/icon-192.png img/icon-512.png img/apple-touch-icon.png "$DEPLOYPATH/img/"
cp fonts/*.woff2 fonts/LICENSE.txt "$DEPLOYPATH/fonts/"
cp partials/*.php "$DEPLOYPATH/partials/"
echo "Publicado: $(git log -1 --format='%h %s') em $DEPLOYPATH"
