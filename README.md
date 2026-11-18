# 🌐 Meta_CSVET

> **Repositório Central de Coordenação e Gestão do Ecossistema CSVET**  
> Desenvolvido e mantido pelo **Prof. Dr. Atilio Sersun Calefi**.

O **Meta_CSVET** consolida, em uma única árvore de trabalho gerenciada via **Git Subtree**, todos os 13 repositórios do ecossistema educacional, editorial científico, pericial, avícola, espiritual e de inteligência artificial da CSVET.

---

## 🧭 Navegação Rápida

- 📊 **[Diário de Bordo & Progresso dos Projetos](docs/PROGRESS.md)**: Status atual, backlog e histórico contínuo de evolução de cada projeto.
- 🔄 **[Guia de Fluxo de Trabalho (Workflow)](docs/WORKFLOW.md)**: Padrões de commit, sincronização bidirecional e segurança.
- ⚡ **[Script de Automação (`sync-subtrees.sh`)](./sync-subtrees.sh)**: Utilitário para `pull`, `push` e gestão de subtrees.

---

## 📦 Catálogo de Repositórios

Os projetos residem no diretório `projects/` e estão agrupados por domínios de atuação:

### 🏛️ Editorial Científico & Institucional
| Projeto | Caminho | Repositório Original | Branch | Finalidade |
| :--- | :--- | :--- | :---: | :--- |
| **`csvet.com.br`** | `projects/csvet.com.br` | [calefin/csvet.com.br](https://github.com/calefin/csvet.com.br) | `main` | Portal institucional da marca CSVET e autoridade SEO. |
| **`journals`** | `projects/journals` | [calefin/journals](https://github.com/calefin/journals) | `main` | Portal de periódicos científicos baseado em OJS 3.5 LTS. |
| **`books_csvet`** | `projects/books_csvet` | [calefin/books_csvet](https://github.com/calefin/books_csvet) | `main` | Portal de livros e monografias baseado em OMP 3.5 LTS. |
| **`profatilio`** | `projects/profatilio` | [calefin/profatilio](https://github.com/calefin/profatilio) | `master` | Portal acadêmico e institucional do Prof. Atilio. |

### 🎓 Avaliações, Educação & Veterinária
| Projeto | Caminho | Repositório Original | Branch | Finalidade |
| :--- | :--- | :--- | :---: | :--- |
| **`ProvaGen`** | `projects/ProvaGen` | [calefin/ProvaGen](https://github.com/calefin/ProvaGen) | `main` | Sistema web de elaboração de provas, gabaritos e turmas. |
| **`provagen_cell`** | `projects/provagen_cell` | [calefin/provagen_cell](https://github.com/calefin/provagen_cell) | `main` | App móvel Flutter para leitura de gabaritos com OpenCV. |
| **`vetrank_new`** | `projects/vetrank_new` | [calefin/vetrank_new](https://github.com/calefin/vetrank_new) | `main` | Plataforma de questões e estudos para Medicina Veterinária. |
| **`medvetlegal_producao`** | `projects/medvetlegal_producao` | [calefin/medvetlegal_producao](https://github.com/calefin/medvetlegal_producao) | `main` | Sistema pericial e laudos de Medicina Veterinária Legal. |

### 🪶 Avicultura & Genética
| Projeto | Caminho | Repositório Original | Branch | Finalidade |
| :--- | :--- | :--- | :---: | :--- |
| **`plumora_gen`** | `projects/plumora_gen` | [calefin/plumora_gen](https://github.com/calefin/plumora_gen) | `main` | SaaS de gestão de aves exóticas, pedigree e suporte PigeonVet. |

### 🕊️ Tradição & Cultura
| Projeto | Caminho | Repositório Original | Branch | Finalidade |
| :--- | :--- | :--- | :---: | :--- |
| **`ijoifa`** | `projects/ijoifa` | [calefin/ijoifa](https://github.com/calefin/ijoifa) | `main` | Sistema web da casa Ìjọ Ifá Èjìogbè (Laravel + Livewire). |
| **`odu_journey`** | `projects/odu_journey` | [calefin/odu_journey](https://github.com/calefin/odu_journey) | `main` | Aplicativo Flutter de estudos dos Odùs tradicionais. |

### 🤖 Inteligência Artificial & Utilitários
| Projeto | Caminho | Repositório Original | Branch | Finalidade |
| :--- | :--- | :--- | :---: | :--- |
| **`skills`** | `projects/skills` | [calefin/skills](https://github.com/calefin/skills) | `main` | Skills Antigravity (ABNT, integridade e revisão editorial IA). |
| **`bagparser`** | `projects/bagparser` | [calefin/bagparser](https://github.com/calefin/bagparser) | `main` | Analisador client-side de logs e loot de party hunt (Tibia). |

---

## 🚀 Como Operar o Repositório

### 1. Clonar o Meta-Repositório
Ao clonar, todo o código-fonte de todos os 13 projetos já vem pronto para uso:
```bash
git clone https://github.com/calefin/Meta_CSVET.git
cd Meta_CSVET
```

### 2. Sincronização com o Script `sync-subtrees.sh`

```bash
# Exibir lista com todos os projetos e suas branches
./sync-subtrees.sh list

# Atualizar TODOS os 13 projetos a partir dos repos remotos (Pull)
./sync-subtrees.sh pull

# Atualizar apenas um projeto específico
./sync-subtrees.sh pull ProvaGen

# Enviar alterações feitas em uma subpasta de volta para o repo original (Push)
./sync-subtrees.sh push ProvaGen

# Subir todas as alterações para o repositório central
git push origin main
```

---

## 📈 Acompanhamento Contínuo

Utilizamos o arquivo **[`docs/PROGRESS.md`](docs/PROGRESS.md)** como **fonte única de verdade** sobre o andamento de cada projeto. Sempre que concluir uma tarefa ou definir novos marcos, atualize a seção correspondente.
