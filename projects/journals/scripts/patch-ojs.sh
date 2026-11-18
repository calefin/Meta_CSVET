#!/usr/bin/env bash
# Correções no código do OJS que precisam valer antes de qualquer plugin carregar.
# Chamado por install-ojs.sh depois de extrair o pacote; pode ser rodado de novo a
# qualquer momento (não repete o que já foi aplicado).
#
#   bash scripts/patch-ojs.sh
#
# 1. Erro 1615 do MySQL ("Prepared statement needs to be re-prepared"): a conexão já nasce
#    com a preparação feita pelo PHP (PDO emulated prepares). O plugin csvetDb faz o mesmo,
#    mas só depois que os plugins carregam; a sessão de login é lida do banco antes disso
#    (Dispatcher::initSession), e o erro 1615 nessa leitura derrubava o login ("não autorizado",
#    "erro inesperado, recarregue a página") e fazia gravações da submissão falharem
#    (filiação, contribuidores).
#
# 2. Link do avaliador que dá 404: cada link de acesso direto novo (lembrete, reenvio do convite,
#    lembrete automático, convite para outro artigo e até abrir a janela "Lembrete ao Avaliador"
#    sem enviar) apagava todos os links anteriores do mesmo avaliador (Invitation::invite). O
#    avaliador que clicava no e-mail anterior caía em "404 Not Found". Com o patch, os links do
#    avaliador (tipo reviewerAccess) continuam valendo até vencer; os outros convites seguem o OJS.
#
# 3. Aceite do convite de papel (coeditor, editor...) com erro falso: o botão final da página
#    do convite não fica desativado durante o envio. O segundo clique chegava com o convite já
#    aceito e o OJS mostrava "Você não está autorizado para acessar o recurso solicitado", embora
#    a conta e o papel já tivessem sido gravados (Adriana, coeditora, 2026-10-20). Com o patch,
#    o aceite é enviado uma vez só; se falhar, o botão volta a funcionar.
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
STATUS=0

# patch_after NOME ARQUIVO MARCA TRECHO TEXTO: acrescenta TEXTO (já indentado) depois da linha do TRECHO
patch_after() {
    local name="$1" file="$ROOT/$2" mark="$3" anchor="$4" text="$5"

    if [ ! -f "$file" ]; then
        echo "  (aviso) $file não existe: OJS ainda não instalado"
        return
    fi
    if grep -qF -- "$mark" "$file"; then
        echo "==> $name: já aplicado"
        return
    fi
    if [ "$(grep -cF -- "$anchor" "$file")" != "1" ]; then
        echo "  ✗ $name: trecho esperado não encontrado em $2."
        echo "    A versão do OJS mudou; revise scripts/patch-ojs.sh."
        STATUS=1
        return
    fi
    A="$anchor" T="$text" perl -pi -e '
        $_ .= $ENV{T} if index($_, $ENV{A}) >= 0;
    ' "$file"
    php -l "$file" >/dev/null
    echo "==> $name: aplicado"
}

# patch_replace NOME ARQUIVO MARCA TRECHO TEXTO: troca o TRECHO (deve aparecer uma vez) por TEXTO.
# Para o JavaScript compilado do OJS, que vem numa linha só.
patch_replace() {
    local name="$1" file="$ROOT/$2" mark="$3" anchor="$4" text="$5"

    if [ ! -f "$file" ]; then
        echo "  (aviso) $file não existe: OJS ainda não instalado"
        return
    fi
    if grep -qF -- "$mark" "$file"; then
        echo "==> $name: já aplicado"
        return
    fi
    if [ "$(grep -oF -- "$anchor" "$file" | wc -l)" != "1" ]; then
        echo "  ✗ $name: trecho esperado não encontrado em $2."
        echo "    A versão do OJS mudou; revise scripts/patch-ojs.sh."
        STATUS=1
        return
    fi
    A="$anchor" T="$text" perl -0777 -pi -e '
        my $i = index($_, $ENV{A});
        substr($_, $i, length($ENV{A})) = $ENV{T} if $i >= 0;
    ' "$file"
    if command -v node >/dev/null; then node --check "$file"; fi
    echo "==> $name: aplicado"
}

patch_after "Patch do banco (erro 1615)" \
    "lib/pkp/classes/core/PKPContainer.php" \
    "csvet: emulated prepares" \
    "'collation' => Config::getVar('database', 'collation', 'utf8_general_ci')," \
"                // csvet: emulated prepares (scripts/patch-ojs.sh)
                'options' => [\\PDO::ATTR_EMULATE_PREPARES => true],
"

patch_after "Patch dos links do avaliador (404)" \
    "lib/pkp/classes/invitation/core/Invitation.php" \
    "csvet: reviewer links stay valid" \
    '->byNotId($this->getId())' \
"            // csvet: reviewer links stay valid (scripts/patch-ojs.sh)
            ->when(static::getType() === 'reviewerAccess', fn (Builder \$q) => \$q->whereRaw('1 = 0'))
"

# Ue() é o envio final da página do convite (store userInvitationPage); só dá para achar pelo texto
patch_replace "Patch do aceite do convite (clique duplo)" \
    "js/build.js" \
    "csvet: accept once" \
    'async function Ue(){if(Object.keys(Ke.value).length>0&&await Ne(),he.value){const{data:St,fetch:Vt}=Xo(_t,{expectValidationError:!0,method:"PUT",body:{}});await Vt(),St.value&&t({title:n("acceptInvitation.modal.title"),message:n("acceptInvitation.modal.message"),actions:[{label:n("acceptInvitation.modal.button"),callback:mn=>{Lt()}}],modalStyle:"success"})}}' \
    'async function Ue(){/* csvet: accept once (scripts/patch-ojs.sh) */if(Ue.busy)return;Ue.busy=!0;try{if(Object.keys(Ke.value).length>0&&await Ne(),he.value){const{data:St,fetch:Vt}=Xo(_t,{expectValidationError:!0,method:"PUT",body:{}});await Vt(),St.value&&(Ue.done=!0,t({title:n("acceptInvitation.modal.title"),message:n("acceptInvitation.modal.message"),actions:[{label:n("acceptInvitation.modal.button"),callback:mn=>{Lt()}}],modalStyle:"success"}))}}finally{Ue.done||(Ue.busy=!1)}}'

exit $STATUS
