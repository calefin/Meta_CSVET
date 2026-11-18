# 🔄 Guia de Fluxo de Trabalho (Workflow & Git Subtree)

Este guia define as boas práticas e padrões para desenvolver, sincronizar e manter os 13 repositórios gerenciados através do **Meta_CSVET**.

---

## 🏗️ Como Funciona a Estrutura

O **Meta_CSVET** utiliza o conceito de **Git Subtree** com `--squash`:
* Cada projeto possui seu repositório original independente no GitHub.
* O código de cada projeto reside dentro da pasta `projects/<nome-do-projeto>`.
* Ao clonar `Meta_CSVET`, todos os projetos já vêm prontos, sem necessidade de inicializar submódulos.

---

## ⚡ Comandos do Dia a Dia

Para facilitar o dia a dia, utilize o script automatizado na raiz:

```bash
./sync-subtrees.sh <comando> [projeto]
```

### 1. Atualizar projetos com alterações vindas dos repositórios remotos (`pull`)
Quando você ou sua equipe alterar o repositório original (ex: diretamente no GitHub ou em outro computador):
```bash
# Atualizar TODOS os 13 repositórios
./sync-subtrees.sh pull

# Atualizar APENAS um projeto específico
./sync-subtrees.sh pull ProvaGen
```

### 2. Enviar alterações feitas dentro do `Meta_CSVET` de volta para o repo original (`push`)
Quando você fizer alterações no código dentro de `projects/<nome>` e comitar no `Meta_CSVET`:
```bash
# Enviar alterações locais de volta para o repositório original
./sync-subtrees.sh push ProvaGen
```

### 3. Subir o estado central para o GitHub
Depois de realizar mudanças ou sincronizações no meta-repositório:
```bash
git push origin main
```

---

## 🔒 Regras de Segurança e Boas Práticas

### 1. Arquivos de Ambiente (`.env`)
- **NUNCA comite arquivos `.env` reais**.
- Utilize sempre arquivos `.env.example` contendo apenas chaves fictícias (ex: `GOOGLE_CLIENT_ID=your-client-id`).
- Lembre-se de que o **GitHub Push Protection** bloqueia automaticamente tentativas de push com credenciais ativas.

### 2. Padrão de Mensagens de Commit
Adote mensagens semânticas no formato:
```text
<tipo>(<escopo>): <descrição sucinta>

Exemplos:
feat(provagen): adiciona exportação de gabaritos em SVG
fix(journals): ajusta folha de estilo para OJS 3.5
docs(progress): atualiza backlog do plumora_gen
chore(meta): sincroniza subtrees com a branch upstream
```

### 3. Registro de Progresso Contínuo
Sempre que concluir uma demanda relevante ou alterar o foco de uma sprint:
- Edite o arquivo [`docs/PROGRESS.md`](./PROGRESS.md).
- Adicione a linha no histórico do respectivo projeto.
- Atualize a lista de próximos passos com `[x]`.
