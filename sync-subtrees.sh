#!/usr/bin/env bash
# ==============================================================================
# Script de Gerenciamento de Subtrees para o repositório Meta_CSVET
# ==============================================================================

set -e

BASE_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$BASE_DIR"

# Prefixo padrão onde os projetos ficarão (ex: "projects/" ou "")
PREFIX_DIR="projects"

# Mapeamento dos projetos: nome|url|branch_padrao
PROJECTS=(
  "bagparser|https://github.com/calefin/bagparser|main"
  "plumora_gen|https://github.com/calefin/plumora_gen|main"
  "ProvaGen|https://github.com/calefin/ProvaGen|main"
  "vetrank_new|https://github.com/calefin/vetrank_new|main"
  "journals|https://github.com/calefin/journals|main"
  "profatilio|https://github.com/calefin/profatilio|master"
  "skills|https://github.com/calefin/skills|main"
  "books_csvet|https://github.com/calefin/books_csvet|main"
  "csvet.com.br|https://github.com/calefin/csvet.com.br|main"
  "medvetlegal_producao|https://github.com/calefin/medvetlegal_producao|main"
  "ijoifa|https://github.com/calefin/ijoifa|main"
  "provagen_cell|https://github.com/calefin/provagen_cell|main"
  "odu_journey|https://github.com/calefin/odu_journey|main"
)

usage() {
  echo "Uso: $0 {setup|pull [nome]|push <nome>|list}"
  echo ""
  echo "Comandos:"
  echo "  setup         Adiciona remotes e importa todos os projetos via git subtree"
  echo "  pull [nome]   Atualiza todos os projetos (ou apenas [nome]) a partir dos repos remotos"
  echo "  push <nome>   Envia alterações feitas localmente em <nome> de volta para o repo remoto"
  echo "  list          Exibe a lista dos 13 projetos configurados"
  echo ""
  exit 1
}

cmd_list() {
  echo "Projetos cadastrados:"
  printf "%-25s %-10s %s\n" "PROJETO" "BRANCH" "URL"
  echo "--------------------------------------------------------------------------------"
  for item in "${PROJECTS[@]}"; do
    IFS="|" read -r name url branch <<< "$item"
    printf "%-25s %-10s %s\n" "$name" "$branch" "$url"
  done
}

cmd_setup() {
  echo "=== Configurando remotes e subtrees ==="
  for item in "${PROJECTS[@]}"; do
    IFS="|" read -r name url branch <<< "$item"
    prefix_path="${PREFIX_DIR}/${name}"

    # 1. Configurar remote
    if git remote get-url "$name" >/dev/null 2>&1; then
      echo "[-] Remote '$name' já configurado."
    else
      echo "[+] Adicionando remote '$name' ($url)..."
      git remote add "$name" "$url"
    fi

    # 2. Buscar atualizações do remote
    echo "[*] Buscando branch $branch do remote $name..."
    git fetch "$name" "$branch"

    # 3. Adicionar subtree se o diretório ainda não existir
    if [ -d "$prefix_path" ]; then
      echo "[-] Diretório '$prefix_path' já existe. Pulando 'git subtree add'."
    else
      echo "[+] Adicionando subtree em '$prefix_path'..."
      git subtree add --prefix="$prefix_path" "$name" "$branch" --squash
    fi
  done
  echo ""
  echo "=== Concluído! Para enviar tudo ao Meta_CSVET no GitHub, execute: ==="
  echo "git push origin main"
}

cmd_pull() {
  target="$1"
  for item in "${PROJECTS[@]}"; do
    IFS="|" read -r name url branch <<< "$item"
    if [ -n "$target" ] && [ "$target" != "$name" ]; then
      continue
    fi
    prefix_path="${PREFIX_DIR}/${name}"
    echo "[*] Atualizando subtree: $prefix_path a partir de $name/$branch..."
    git subtree pull --prefix="$prefix_path" "$name" "$branch" --squash
  done
}

cmd_push() {
  target="$1"
  if [ -z "$target" ]; then
    echo "Erro: especifique qual projeto você quer subir (ex: $0 push bagparser)"
    exit 1
  fi
  found=0
  for item in "${PROJECTS[@]}"; do
    IFS="|" read -r name url branch <<< "$item"
    if [ "$target" = "$name" ]; then
      found=1
      prefix_path="${PREFIX_DIR}/${name}"
      echo "[*] Enviando alterações de $prefix_path para $name/$branch..."
      git subtree push --prefix="$prefix_path" "$name" "$branch"
      break
    fi
  done

  if [ $found -eq 0 ]; then
    echo "Erro: projeto '$target' não encontrado na lista."
    exit 1
  fi
}

case "$1" in
  setup)
    cmd_setup
    ;;
  pull)
    cmd_pull "$2"
    ;;
  push)
    cmd_push "$2"
    ;;
  list)
    cmd_list
    ;;
  *)
    usage
    ;;
esac
