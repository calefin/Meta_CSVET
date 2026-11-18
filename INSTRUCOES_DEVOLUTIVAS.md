# Manual Operacional: Como Publicar e Atualizar Devolutivas Pós-Correção

Este guia descreve o procedimento completo para gerar, carregar e publicar as notas e espelhos pós-correção no portal **profatilio.com.br** em qualquer computador, de forma 100% aderente à **LGPD (Art. 12)** e sem depender de scripts locais.

---

## ⚡ Método Recomendado: Importação Direta pelo Painel Admin (Sem Scripts)

Não é necessário rodar scripts Python locais nem configurar ambientes com caminhos de pastas específicos. O sistema web faz a ingestão, o cálculo dos hashes e a criptografia Zero-Knowledge diretamente no navegador.

### Passo 1: Acessar a Área Administrativa
1. Acesse: [https://profatilio.com.br/admin](https://profatilio.com.br/admin)
2. Faça o login com sua conta de administrador.
3. No menu lateral, acesse: **Gestão Acadêmica & Devolutivas** ➔ **Devolutivas & Espelhos de Prova** (URL direta: `/admin/gerenciar-espelhos-prova`).

---

### Passo 2: Escolher o Pacote e Turma
Localize o card da disciplina correspondente:
- **Empreendedorismo e Inovação** (Campus PTA — Paulista)
- **Medicina dos Animais Silvestres** (Campus PTA · GRU · SM)
- **Fisiologia Animal II** (Campus PTA — Paulista)

No card da avaliação, clique no botão:
👉 **`📥 Importar Notas (CSV/TSV)`**

> **Dica**: Se a avaliação ainda não existir (ex.: uma nova P1 ou P2 de um novo semestre), basta clicar no botão **`➕ Inicializar Pacote de Disciplina`** no topo da página.

---

### Passo 3: Preparar e Carregar os Dados

Você tem duas maneiras muito práticas:
1. **Enviar arquivo CSV/TSV** exportado do Excel, Google Planilhas ou ZipGrade.
2. **Ou Simplesmente Copiar e Colar (Ctrl+C / Ctrl+V)** as linhas selecionadas na planilha diretamente na caixa de texto.

---

### 📝 Formato para Provas (ZipGrade / Avaliações Objetivas + Discursivas)

#### Colunas Reconhecidas Automaticamente:
| Coluna | Descrição | Exemplo |
| :--- | :--- | :--- |
| `rgm` | Registro de Matrícula (apenas números) | `34096167` |
| `versao` | Caderno da Prova (A, B, C...) | `B` |
| `q1` | Alternativa assinalada na questão 1 | `A` |
| `q2` | Alternativa assinalada na questão 2 | `A` |
| `q3` | Alternativa assinalada na questão 3 | `D` |
| `acertos` | Quantidade de questões objetivas acertadas | `13` |
| `nota_final` | Nota total da avaliação | `2.30` |
| `feedback` | *(Opcional)* Comentário sobre a questão discursiva | `Resolução correta do caso clínico.` |

#### Exemplo de Texto para Colar Direto (CSV):
```csv
RGM,Versao,Q1,Q2,Q3,Acertos,Nota_Final,Feedback
34096167,B,A,A,D,13,2.30,Excelente conduta frente à suspeita de Clamidiose.
35182904,A,C,B,E,11,1.85,Necessário atentar para dosagem medicamentosa.
```

---

### 📁 Formato para Trabalhos Acadêmicos (Projetos em Grupo / Canvas / 5W2H)

#### Colunas Reconhecidas Automaticamente:
| Coluna | Descrição | Exemplo |
| :--- | :--- | :--- |
| `rgm` | Registro de Matrícula do aluno | `38258439` |
| `nome` *(ou `aluno`)* | Nome do aluno (usado para gerar variações anônimas) | `Abigail Carvalho` |
| `trabalho` | Nota da entrega do trabalho prático | `4.0` |
| `pbb` | Nota da avaliação online Blackboard | `1.0` |
| `projeto` | Nome do projeto ou da empresa do grupo | `REABILITAR` |
| `pontos_fortes` | *(Opcional)* Destaques positivos separados por `;` | `Excelente BMC; Detalhamento do 5W2H impecável` |
| `pontos_falhos` | *(Opcional)* Oportunidades de melhoria separadas por `;` | `Ajustar estimativa de fluxo de caixa` |

#### Exemplo de Texto para Colar Direto (CSV):
```csv
RGM,Nome,Nota_Trabalho,Nota_PBB,Projeto,Pontos_Fortes,Pontos_Falhos
38258439,Abigail Carvalho,4.0,1.0,REABILITAR,Excelente BMC; Clareza no 5W2H,Ajustar cronograma operacional
37102941,Bruno Henrique Santos,3.5,0.8,ECOVET,Boa proposta de valor,Detalhamento de canais incompleto
```

---

### Passo 4: Clicar em "Processar, Criptografar & Publicar"
- O sistema processará as linhas, derivará as chaves anônimas (`HMAC-SHA256` + `AES-256-CBC`) e gravará no cofre digital.
- Uma notificação de sucesso aparecerá informando quantos alunos foram inseridos e atualizados.

---

## 👤 Lançamento de Aluno Avulso (Segunda Chamada / Revisão)

Caso precise adicionar apenas um aluno que fez segunda chamada ou teve a nota revisada:
1. No card do pacote, clique em: **`👤 + Aluno`**.
2. Selecione a turma de destino.
3. Preencha os campos (RGM, respostas/nome e notas).
4. Clique em **`Criptografar & Gravar Aluno no Portal`**. O espelho fica disponível na hora para o estudante.

---

## 🏛️ Padrões Institucionais & Conformidade LGPD

1. **Siglas de Campus Oficiais**:
   - `PTA` — Paulista
   - `SM` — São Miguel
   - `GRU` — Guarulhos
   - `AF` — Anália Franco
   - `VL` — Villa Lobos

2. **Turnos Autorizados**:
   - `Matutino`
   - `Noturno`

3. **Restrição Absoluta de Códigos Numéricos (LGPD Art. 12)**:
   - Nunca insira nem exiba códigos numéricos de oferta (ex.: `2276204`, `2280372`). As turmas devem ser sempre identificadas por **Campus + Turno** (ex.: `PTA · Matutino`).

4. **Disclaimer Oficial Obrigatório**:
   - A página de consulta e todos os espelhos possuem aviso explícito de que a nota oficial válida é exclusivamente a lançada na **Área do Aluno** da universidade, tendo o portal propósito estritamente pedagógico e formativo.

---

## 🔒 Encerramento de Semestre
Ao término do período letivo:
1. Acesse o painel `/admin/gerenciar-espelhos-prova`.
2. Na seção **Gestão de Semestres**, clique em **`Encerrar & Bloquear`** no semestre correspondente (ex.: `2026.2`).
3. O sistema bloqueia automaticamente qualquer visualização de espelhos antigos, impedindo vazamentos ou consultas indevidas fora de época.

---

## 🚀 Comandos de Atualização no Servidor (Deploy)

Após qualquer alteração via Git:
```bash
cd /home2/atilio/profatilio.com.br
git pull origin master
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan filament:optimize
```
