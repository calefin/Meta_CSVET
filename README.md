# Meta_CSVET

Repositório central de coordenação e gerenciamento dos projetos do ecossistema CSVET usando **Git Subtree**.

---

## 📁 Estrutura de Diretórios

Os projetos são organizados dentro da pasta `projects/`:

| Projeto | Repositório Original | Branch Padrão |
| :--- | :--- | :---: |
| `bagparser` | [calefin/bagparser](https://github.com/calefin/bagparser) | `main` |
| `plumora_gen` | [calefin/plumora_gen](https://github.com/calefin/plumora_gen) | `main` |
| `ProvaGen` | [calefin/ProvaGen](https://github.com/calefin/ProvaGen) | `main` |
| `vetrank_new` | [calefin/vetrank_new](https://github.com/calefin/vetrank_new) | `main` |
| `journals` | [calefin/journals](https://github.com/calefin/journals) | `main` |
| `profatilio` | [calefin/profatilio](https://github.com/calefin/profatilio) | `master` |
| `skills` | [calefin/skills](https://github.com/calefin/skills) | `main` |
| `books_csvet` | [calefin/books_csvet](https://github.com/calefin/books_csvet) | `main` |
| `csvet.com.br` | [calefin/csvet.com.br](https://github.com/calefin/csvet.com.br) | `main` |
| `medvetlegal_producao` | [calefin/medvetlegal_producao](https://github.com/calefin/medvetlegal_producao) | `main` |
| `ijoifa` | [calefin/ijoifa](https://github.com/calefin/ijoifa) | `main` |
| `provagen_cell` | [calefin/provagen_cell](https://github.com/calefin/provagen_cell) | `main` |
| `odu_journey` | [calefin/odu_journey](https://github.com/calefin/odu_journey) | `main` |

---

## 🚀 Como Usar o Script de Sincronização

Foi criado o script [`sync-subtrees.sh`](./sync-subtrees.sh) para automatizar os comandos:

### 1. Inicializar / Importar todos os subtrees
Configura os remotes, faz o fetch e executa o `git subtree add --squash` para cada repositório:
```bash
./sync-subtrees.sh setup
```
Depois envie para o repositório central:
```bash
git push origin main
```

### 2. Atualizar repositórios locais (Pull)
- **Atualizar todos os projetos:**
  ```bash
  ./sync-subtrees.sh pull
  ```
- **Atualizar apenas um projeto específico:**
  ```bash
  ./sync-subtrees.sh pull bagparser
  ```

### 3. Enviar alterações de volta ao repositório de origem (Push)
Se você fizer commits no repositório central alterando arquivos dentro de `projects/<nome>`, envie de volta com:
```bash
./sync-subtrees.sh push bagparser
```

### 4. Listar projetos
```bash
./sync-subtrees.sh list
```
