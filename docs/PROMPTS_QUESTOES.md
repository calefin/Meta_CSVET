# VetRank — Guia de Prompts para Criação de Questões

> Use este arquivo como referência para gerar questões via IA (ChatGPT, Claude, Gemini) e importá-las via JSON.
> Sempre substitua `[TÓPICO ESPECÍFICO]` pelo conteúdo desejado antes de enviar o prompt.

---

## Estrutura do Banco (IDs de Produção)

### Grande Área 30001 — Ciências Biológicas e Ciclo Básico
| subjectId | Disciplina |
|-----------|-----------|
| 30001 | Anatomia Veterinária I e II (Sistêmica e Topográfica) |
| 30002 | Biologia Celular e Molecular |
| 30003 | Histologia Veterinária (Geral e Especial) |
| 30004 | Embriologia Veterinária |
| 30005 | Bioquímica Veterinária e Metabólica |
| 30006 | Biofísica Veterinária |
| 30007 | Fisiologia Veterinária I e II |
| 30008 | Genética e Evolução Animal |
| 30009 | Bioestatística e Metodologia Científica |

### Grande Área 30002 — Patobiologia e Agentes Agressores
| subjectId | Disciplina |
|-----------|-----------|
| 30010 | Microbiologia Veterinária (Bacteriologia e Virologia) |
| 30011 | Imunologia Veterinária |
| 30012 | Parasitologia Veterinária |
| 30013 | Patologia Geral e Técnicas de Necropsia |
| 30014 | Patologia Especial/Sistêmica Veterinária |
| 30015 | Farmacologia e Terapêutica Veterinária |
| 30016 | Toxicologia Veterinária |

### Grande Área 30003 — Clínica, Cirurgia e Reprodução Animal
| subjectId | Disciplina |
|-----------|-----------|
| 30017 | Semiologia Veterinária (Propedêutica) |
| 30018 | Patologia Clínica Veterinária (Diagnóstico Laboratorial) |
| 30019 | Diagnóstico por Imagem (Radiologia e Ultrassonografia) |
| 30020 | Técnica Cirúrgica Veterinária |
| 30021 | Anestesiologia Veterinária |
| 30022 | Clínica Médica e Cirúrgica de Pequenos Animais (Cães e Gatos) |
| 30023 | Clínica Médica e Cirúrgica de Ruminantes (Bovinos, Ovinos e Caprinos) |
| 30024 | Clínica Médica e Cirúrgica de Equídeos |
| 30025 | Fisiopatologia da Reprodução (Macho e Fêmea) |
| 30026 | Obstetrícia Veterinária e Biotecnologia da Reprodução |
| 30046 | Medicina dos Animais Silvestres |

> **Hierarquia de Medicina dos Animais Silvestres (subjectId 30046):**
> - `disciplineId = 30003` → Grande Área: Clínica, Cirurgia e Reprodução Animal
> - `subjectId = 30046` → Disciplina: Medicina dos Animais Silvestres
> - `subjectTag` → Assunto livre por espécie/grupo: ex. `"Anestesiologia"`, `"Cirurgia"`, `"Medicina Clínica"`, `"Doenças Infecciosas e Laboratório"`
>
> O `subjectTag` diferencia o conteúdo dentro da disciplina e é o campo usado nos filtros do banco de questões. O `topicId` **não é usado** neste fluxo.

### Grande Área 30004 — Medicina Preventiva, Saúde Pública e Inspeção
| subjectId | Disciplina |
|-----------|-----------|
| 30027 | Epidemiologia Veterinária |
| 30028 | Zoonoses e Saúde Pública |
| 30029 | Saneamento Ambiental e Planejamento em Saúde Animal |
| 30030 | Inspeção de Produtos de Origem Animal (Carnes, Leite, Ovos, Pescado e Mel) |
| 30031 | Tecnologia de Produtos de Origem Animal |
| 30032 | Higiene de Alimentos |

### Grande Área 30005 — Zootecnia e Produção Animal
| subjectId | Disciplina |
|-----------|-----------|
| 30033 | Introdução à Zootecnia |
| 30034 | Nutrição e Alimentação Animal |
| 30035 | Agrostologia e Forragicultura (Pastagens) |
| 30036 | Melhoramento Genético Animal |
| 30037 | Bovinocultura de Corte e de Leite |
| 30038 | Avicultura, Suinocultura e Equideocultura |
| 30039 | Aquicultura e Piscicultura |

### Grande Área 30006 — Ética, Humanidades e Áreas Complementares
| subjectId | Disciplina |
|-----------|-----------|
| 30040 | Deontologia, Ética e Bioética na Medicina Veterinária |
| 30041 | Bem-Estar Animal e Etologia |
| 30042 | Medicina Veterinária Legal (Perícia) |
| 30043 | Administração e Planejamento Rural |
| 30044 | Extensão Rural e Sociologia |
| 30045 | Medicina de Animais Selvagens e Pets Exóticos |

---

## Padrão de Formatação de `textPt` — Quebras de Linha

> ⚠️ **Leia antes de gerar qualquer questão.**
>
> O `textPt` é armazenado como string JSON. Use `\n` (barra-n literal dentro da string JSON) para inserir quebras de linha. O sistema de renderização depende dessas quebras para identificar e exibir corretamente os itens de cada tipo de questão.

### Formato Obrigatório por Modelo

#### M3 — Questões com Assertivas (complex_multiple_choice)
```
"Sobre [tema], analise as assertivas:\n\nI. [assertiva 1]\nII. [assertiva 2]\nIII. [assertiva 3]\nIV. [assertiva 4]\n\nSão CORRETAS apenas:"
```
- A introdução termina com `\n\n` (linha em branco) antes do `I.`
- Cada assertiva em sua própria linha: `\nII.`, `\nIII.`, `\nIV.`
- O comando final separado por `\n\n` (ex.: `São CORRETAS apenas:`)

#### M5 — Asserção–Razão (assertion_reason)
```
"ASSERÇÃO I: [proposição 1].\n\nPORQUE\n\nASSERÇÃO II: [proposição 2].\n\nA respeito das asserções acima, assinale a alternativa CORRETA:"
```
- `PORQUE` isolado em bloco próprio: `\n\nPORQUE\n\n`

#### M6 — Cloze / Lacuna
```
"[Trecho técnico com lacuna(s) indicadas por [___] ou [1], [2]...]\n\nAssinale a alternativa que preenche CORRETAMENTE a(s) lacuna(s):"
```
- A lacuna é marcada com `[___]` (uma lacuna) ou `[1]`, `[2]` (múltiplas)
- O comando de resposta é separado por `\n\n`

#### M8 — Associação de Colunas (matching)
```
"Relacione os itens da Coluna I com os respectivos da Coluna II:\n\nCOLUNA I\n1. [item]\n2. [item]\n3. [item]\n4. [item]\n\nCOLUNA II\na) [descrição]\nb) [descrição]\nc) [descrição]\nd) [descrição]\ne) [descrição - distrator]\n\nA associação CORRETA é:"
```
- `COLUNA I` e `COLUNA II` em MAIÚSCULAS, cada um em linha própria separado por `\n\n`
- Itens da Coluna I: `1.`, `2.`, `3.`, `4.` — cada um em `\n`
- Itens da Coluna II: `a)`, `b)`, `c)`, `d)`, `e)` — cada um em `\n`
- **Não use o formato inline** `(Coluna I)...(Coluna II):` — o sistema interpreta esse formato, mas a renderização em colunas visuais só é garantida com os headers explícitos.

#### M9 — Ordenação (ordering)
```
"Assinale a sequência CORRETA das etapas do procedimento X:\n\nI. [etapa]\nII. [etapa]\nIII. [etapa]\nIV. [etapa]\nV. [etapa]"
```

#### M7 — Caso Clínico (clinical_case)
```
"[CASO CLÍNICO]\nAnimal: [...]. Anamnese: [...]. Exame físico: [...]\n\nCom base no caso acima, [pergunta]:"
```
- A tag `[CASO CLÍNICO]` na primeira linha ajuda o renderizador a identificar o bloco de contexto.

### Formatos Reconhecidos pelo Importador

O importador (`Admin → Importar JSON`) aceita dois formatos para questões de colunas (matching):

| Formato | Exemplo | Suporte |
|---------|---------|---------|
| **Padrão (recomendado)** | `COLUNA I\n1. Item\n...\nCOLUNA II\na) Alt\n...` | Renderização completa em colunas visuais |
| **Inline** | `(Coluna I) com (Coluna II): 1. Item 2. Item a) Alt b) Alt` | Reconhecido automaticamente, renderizado em colunas |

Para M3, o formato padrão com `\n` entre as assertivas é sempre preferido.

---

## Campos Obrigatórios do JSON (bulkImport)

```json
{
  "disciplineId": 30001,
  "subjectId": 30007,
  "difficulty": "medium",
  "year": 2026,
  "questionType": "multiple_choice",
  "modelId": "M1",
  "questionModel": "enade",
  "author": "VetRank",
  "banca": "CFMV",
  "textPt": "Enunciado da questão...",
  "options": [
    { "id": "A", "textPt": "Alternativa A" },
    { "id": "B", "textPt": "Alternativa B" },
    { "id": "C", "textPt": "Alternativa C" },
    { "id": "D", "textPt": "Alternativa D" },
    { "id": "E", "textPt": "Alternativa E" }
  ],
  "correctOption": "B",
  "explanationPt": "Justificativa completa...",
  "isPremium": true,
  "status": "approved"
}
```

**Valores válidos:**
- `difficulty`: `very_easy` | `easy` | `medium` | `hard` | `very_hard`
- `questionType`: `multiple_choice` | `assertion_reason` | `complex_multiple_choice` | `matching` | `true_false` | `ordering` | `cloze` | `clinical_case` | `image_analysis` | `interpretation` | `discursive`
- `modelId`: `M1` a `M10`
- `questionModel`: `standard` | `enade` | `true_false` | `assertion_reason`
- `banca`: `CFMV` | `ENADE` | `REVALIDA` | `CRMV-SP` | `concurso_publico`

---

## Prompts por Modelo de Item

---

### M1 — Resposta Única (Pergunta Direta)
**Quando usar:** conhecimento factual, definições, mecanismos, classificações.
**questionType:** `multiple_choice` | **modelId:** `M1`

**Prompt:**
```
Você é especialista em elaboração de questões para CFMV/ENADE Veterinária.
Gere 10 questões M1 (Resposta Única — Pergunta Direta) sobre:

TÓPICO: [TÓPICO ESPECÍFICO]
Grande Área: disciplineId [XX]
Disciplina: subjectId [YY]

REGRAS OBRIGATÓRIAS:
- author: "VetRank"
- banca: "ENADE" ou "CFMV"
- modelId: "M1"
- questionModel: "enade"
- questionType: "multiple_choice"
- isPremium: true, status: "approved"
- Enunciado: pergunta direta terminando em "?" ou comando "Assinale a alternativa CORRETA sobre..."
- 5 alternativas (A–E), apenas uma correta
- Distratores plausíveis baseados em erros conceituais reais
- Varie difficulty entre easy, medium e hard
- Justificativa detalhada em explanationPt
- Sem textEn (apenas textPt)

Retorne APENAS o JSON válido (array de objetos), sem markdown, sem comentários:

[
  {
    "disciplineId": XX,
    "subjectId": YY,
    "difficulty": "medium",
    "year": 2026,
    "questionType": "multiple_choice",
    "modelId": "M1",
    "questionModel": "enade",
    "author": "VetRank",
    "banca": "ENADE",
    "textPt": "...",
    "options": [
      {"id":"A","textPt":"..."},
      {"id":"B","textPt":"..."},
      {"id":"C","textPt":"..."},
      {"id":"D","textPt":"..."},
      {"id":"E","textPt":"..."}
    ],
    "correctOption": "B",
    "explanationPt": "...",
    "isPremium": true,
    "status": "approved"
  }
]
```

---

### M2 — Afirmação Incompleta
**Quando usar:** definições, conceitos expressos como complemento de frase.
**questionType:** `multiple_choice` | **modelId:** `M2`

**Prompt:**
```
Gere 10 questões M2 (Afirmação Incompleta) sobre:

TÓPICO: [TÓPICO ESPECÍFICO]
Grande Área: disciplineId [XX] | Disciplina: subjectId [YY]

REGRAS:
- modelId: "M2", questionType: "multiple_choice", questionModel: "enade"
- author: "VetRank", isPremium: true, status: "approved"
- Enunciado DEVE terminar com ":" ou "..." (é uma frase incompleta)
- As 5 alternativas completam a frase do enunciado formando uma sentença gramaticalmente correta
- Apenas uma alternativa é verdadeira quando combina com o enunciado
- Distratores: conceitos próximos ao correto mas factualmente errados
- Inclua explanationPt explicando por que cada distrator está errado

Exemplo de estrutura:
textPt: "O agente etiológico mais comum da mastite bovina em rebanhos de alta produção leiteira é:"
options: A) Staphylococcus aureus  B) Streptococcus agalactiae  C) Escherichia coli  D) Klebsiella pneumoniae  E) Mycoplasma bovis
correctOption: "B"

Retorne APENAS o JSON válido (array):
[{ "disciplineId": XX, "subjectId": YY, "difficulty": "...", "year": 2026,
   "questionType": "multiple_choice", "modelId": "M2", "questionModel": "enade",
   "author": "VetRank", "banca": "CFMV", "textPt": "...",
   "options": [...], "correctOption": "...", "explanationPt": "...",
   "isPremium": true, "status": "approved" }]
```

---

### M3 — Resposta Múltipla / Combinações (Assertivas I–IV)
**Quando usar:** múltiplos aspectos de um conceito; nível análise/avaliação.
**questionType:** `complex_multiple_choice` | **modelId:** `M3`

> ⚠️ **Atenção:** As assertivas (I, II, III, IV) devem ser incluídas no `textPt` mesmo. As alternativas são combinações das assertivas corretas. Após importar, se precisar, use o painel do professor para refinamento.

**Prompt:**
```
Gere 10 questões M3 (Resposta Múltipla / Assertivas) sobre:

TÓPICO: [TÓPICO ESPECÍFICO]
Grande Área: disciplineId [XX] | Disciplina: subjectId [YY]

REGRAS:
- modelId: "M3", questionType: "complex_multiple_choice", questionModel: "enade"
- author: "VetRank", isPremium: true, status: "approved"
- O textPt DEVE conter as assertivas I, II, III, IV embutidas no enunciado
- Alternativas são combinações das assertivas corretas: "Apenas I", "I e III", "I, II e IV", etc.
- NUNCA usar "Todas" ou "Nenhuma"
- O gabarito indica quais assertivas são verdadeiras
- Explique em explanationPt quais assertivas são V/F e por quê

Formato textPt:
"Sobre [tema], analise as assertivas:\n\nI. [assertiva 1]\nII. [assertiva 2]\nIII. [assertiva 3]\nIV. [assertiva 4]\n\nSão CORRETAS apenas:"

Formato alternativas (exemplos):
A) Apenas I e II.
B) Apenas I e III.
C) Apenas II e IV.
D) I, III e IV.
E) Apenas II e III.

Retorne APENAS o JSON válido (array):
[{ "disciplineId": XX, "subjectId": YY, "difficulty": "hard", "year": 2026,
   "questionType": "complex_multiple_choice", "modelId": "M3", "questionModel": "enade",
   "author": "VetRank", "banca": "ENADE", "textPt": "...",
   "options": [...], "correctOption": "D", "explanationPt": "I-VERDADEIRA: ... II-FALSA: ... III-VERDADEIRA: ...",
   "isPremium": true, "status": "approved" }]
```

---

### M4 — Foco Negativo (EXCETO / NÃO / INCORRETO)
**Quando usar:** avaliar exceções e casos especiais; usar com moderação (≤20% das questões).
**questionType:** `multiple_choice` | **modelId:** `M4`

**Prompt:**
```
Gere 8 questões M4 (Foco Negativo) sobre:

TÓPICO: [TÓPICO ESPECÍFICO]
Grande Área: disciplineId [XX] | Disciplina: subjectId [YY]

REGRAS:
- modelId: "M4", questionType: "multiple_choice", questionModel: "enade"
- author: "VetRank", isPremium: true, status: "approved"
- O enunciado DEVE conter "EXCETO", "NÃO" ou "INCORRETO" em MAIÚSCULAS
- 4 alternativas são CORRETAS/VERDADEIRAS; apenas 1 é o gabarito (INCORRETA)
- O gabarito (incorreto) deve ser plausível, não absurdo
- Use dificuldades medium e hard

Exemplos de enunciado:
"São características da doença X em bovinos, EXCETO:"
"Em relação ao fármaco Y, é INCORRETO afirmar que:"
"São indicações do procedimento Z, EXCETO:"

Retorne APENAS o JSON válido (array):
[{ "disciplineId": XX, "subjectId": YY, "difficulty": "medium", "year": 2026,
   "questionType": "multiple_choice", "modelId": "M4", "questionModel": "enade",
   "author": "VetRank", "banca": "CFMV", "textPt": "...",
   "options": [...], "correctOption": "C",
   "explanationPt": "A alternativa C é INCORRETA porque... As demais são corretas: A)... B)... D)... E)...",
   "isPremium": true, "status": "approved" }]
```

---

### M5 — Asserção–Razão (PORQUE)
**Quando usar:** relações causais, mecanismos de ação, fisiopatologia.
**questionType:** `assertion_reason` | **modelId:** `M5`

> ⚠️ **Importante:** Para M5, as alternativas são FIXAS e predefinidas. O `correctOption` é determinado pela veracidade das asserções.

**Alternativas FIXAS para todo M5** (copie exatamente — nunca altere o texto):
- A: As asserções I e II são verdadeiras, e a II é justificativa correta da I.
- B: As asserções I e II são verdadeiras, mas a II não é justificativa correta da I.
- C: A asserção I é verdadeira, e a II é falsa.
- D: A asserção I é falsa, e a II é verdadeira.
- E: As asserções I e II são falsas.

**Prompt:**
```
Gere 10 questões M5 (Asserção–Razão) sobre:

TÓPICO: [TÓPICO ESPECÍFICO]
Grande Área: disciplineId [XX] | Disciplina: subjectId [YY]

REGRAS:
- modelId: "M5", questionType: "assertion_reason", questionModel: "assertion_reason"
- author: "VetRank", isPremium: true, status: "approved"
- O textPt DEVE seguir o formato:
  "ASSERÇÃO I: [proposição 1].\n\nPORQUE\n\nASSERÇÃO II: [proposição 2].\n\nA respeito das asserções acima, assinale a alternativa CORRETA:"
- As 5 alternativas são SEMPRE as fixas A-E do modelo M5 (copie exatamente abaixo)
- Distribua gabaritos: não use sempre A; varie entre A, B, C, D, E
- Inclua assertion1 e assertion2 como campos separados ALÉM de estarem no textPt
- Inclua a1Verdadeira, a2Verdadeira, relacaoCausal (booleans) consistent com o gabarito
- explanationPt deve explicar V/F de cada asserção e a relação causal

Mapeamento gabarito → booleans:
A: a1=true, a2=true, relacaoCausal=true
B: a1=true, a2=true, relacaoCausal=false
C: a1=true, a2=false, relacaoCausal=false
D: a1=false, a2=true, relacaoCausal=false
E: a1=false, a2=false, relacaoCausal=false

Retorne APENAS o JSON válido (array):
[{
  "disciplineId": XX, "subjectId": YY, "difficulty": "hard", "year": 2026,
  "questionType": "assertion_reason", "modelId": "M5", "questionModel": "assertion_reason",
  "author": "VetRank", "banca": "ENADE",
  "textPt": "ASSERÇÃO I: ...\n\nPORQUE\n\nASSERÇÃO II: ...\n\nA respeito das asserções acima, assinale a alternativa CORRETA:",
  "assertion1": "texto da asserção I",
  "assertion2": "texto da asserção II",
  "a1Verdadeira": true,
  "a2Verdadeira": true,
  "relacaoCausal": true,
  "options": [
    {"id":"A","textPt":"As asserções I e II são verdadeiras, e a II é justificativa correta da I."},
    {"id":"B","textPt":"As asserções I e II são verdadeiras, mas a II não é justificativa correta da I."},
    {"id":"C","textPt":"A asserção I é verdadeira, e a II é falsa."},
    {"id":"D","textPt":"A asserção I é falsa, e a II é verdadeira."},
    {"id":"E","textPt":"As asserções I e II são falsas."}
  ],
  "correctOption": "A",
  "explanationPt": "Asserção I é VERDADEIRA porque... Asserção II é VERDADEIRA porque... A relação causal É válida porque...",
  "isPremium": true, "status": "approved"
}]
```

---

### M6 — Lacuna (Cloze)
**Quando usar:** terminologia técnica, fases de protocolos, sequências fixas.
**questionType:** `cloze` | **modelId:** `M6`

**Prompt:**
```
Gere 8 questões M6 (Lacuna/Cloze) sobre:

TÓPICO: [TÓPICO ESPECÍFICO]
Grande Área: disciplineId [XX] | Disciplina: subjectId [YY]

REGRAS:
- modelId: "M6", questionType: "cloze", questionModel: "enade"
- author: "VetRank", isPremium: true, status: "approved"
- O textPt deve ser um trecho técnico com UMA lacuna indicada por [___]
  OU com múltiplas lacunas indicadas por [1], [2], [3]
- Para múltiplas lacunas, as alternativas são combinações "X — Y — Z" na ordem das lacunas
- Enunciado: "Assinale a alternativa que preenche CORRETAMENTE a(s) lacuna(s) acima:"
- Distratores: termos da mesma categoria semântica
- Varie entre 1 e 3 lacunas por questão

Exemplo (1 lacuna):
textPt: "O período de incubação da Raiva em bovinos varia de [___] dias após a exposição ao vírus."
options: A) 2 a 5  B) 15 a 60  C) 90 a 180  D) 1 a 3  E) 365 a 730
correctOption: "B"

Retorne APENAS o JSON válido (array):
[{ "disciplineId": XX, "subjectId": YY, "difficulty": "medium", "year": 2026,
   "questionType": "cloze", "modelId": "M6", "questionModel": "enade",
   "author": "VetRank", "banca": "CFMV",
   "textPt": "Texto com lacuna(s) [___] ou [1] e [2]...\n\nAssinale a alternativa que preenche CORRETAMENTE a(s) lacuna(s):",
   "options": [...], "correctOption": "B", "explanationPt": "...",
   "isPremium": true, "status": "approved" }]
```

---

### M7 — Interpretação (Texto / Caso Clínico / Dados)
**Quando usar:** análise de dados, casos clínicos, textos científicos; nível análise/avaliação.
**questionType:** `interpretation` ou `clinical_case` | **modelId:** `M7`

**Prompt:**
```
Gere 8 questões M7 (Interpretação com base em caso clínico ou dados) sobre:

TÓPICO: [TÓPICO ESPECÍFICO]
Grande Área: disciplineId [XX] | Disciplina: subjectId [YY]

REGRAS:
- modelId: "M7", questionType: "clinical_case" (para casos) ou "interpretation" (para dados/texto)
- questionModel: "enade", author: "VetRank", isPremium: true, status: "approved"
- O textPt DEVE começar com o texto-base (caso clínico, dados ou trecho científico)
  seguido do enunciado/pergunta
- O candidato PRECISA do texto-base para responder (não pode ser de memória)
- Inclua dados concretos: raça, idade, sinais clínicos, exames, valores laboratoriais
- Alternativas: conclusões ou condutas deriváveis do texto-base
- Dificuldade: preferencialmente hard ou very_hard

Formato textPt:
"[CASO CLÍNICO]\nAnimal: [espécie, raça, sexo, idade]. Anamnese: [...]. Exame físico: [...]. Exames complementares: [...]\n\nCom base no caso acima, [pergunta]:"

Retorne APENAS o JSON válido (array):
[{ "disciplineId": XX, "subjectId": YY, "difficulty": "hard", "year": 2026,
   "questionType": "clinical_case", "modelId": "M7", "questionModel": "enade",
   "author": "VetRank", "banca": "ENADE",
   "textPt": "[CASO CLÍNICO]\n...\n\nCom base no caso acima, ...",
   "options": [...], "correctOption": "C", "explanationPt": "...",
   "isPremium": true, "status": "approved" }]
```

---

### M8 — Associação / Correspondência (Relacionar Colunas)
**Quando usar:** associar estruturas–funções, agentes–doenças, fármacos–mecanismos.
**questionType:** `matching` | **modelId:** `M8`

> ⚠️ O textPt deve conter as duas colunas embutidas usando o **formato padrão com `\n`** (ver seção de formatação acima). Formato de alternativas: "1-b / 2-c / 3-a / 4-d". **NUNCA escreva os dois conjuntos de itens na mesma linha** — use `\nCOLUNA I\n1. ...\n\nCOLUNA II\na) ...`.

**Prompt:**
```
Gere 8 questões M8 (Associação de Colunas) sobre:

TÓPICO: [TÓPICO ESPECÍFICO]
Grande Área: disciplineId [XX] | Disciplina: subjectId [YY]

REGRAS:
- modelId: "M8", questionType: "matching", questionModel: "enade"
- author: "VetRank", isPremium: true, status: "approved"
- Use 4 pares (Coluna I: 1–4; Coluna II: a–d + 1 distrator extra: e)
- O textPt contém as duas colunas e termina com o comando de associação
- Alternativas no formato: "1-a / 2-c / 3-b / 4-d"
- Apenas uma alternativa tem TODOS os pares corretos
- Dificulte: inclua itens da Coluna II que pareçam plausíveis para mais de uma da Coluna I

Formato textPt:
"Relacione os itens da Coluna I com os respectivos da Coluna II:\n\nCOLUNA I\n1. [item]\n2. [item]\n3. [item]\n4. [item]\n\nCOLUNA II\na) [descrição]\nb) [descrição]\nc) [descrição]\nd) [descrição]\ne) [descrição - distrator]\n\nA associação CORRETA é:"

Formato alternativas:
A) 1-b / 2-a / 3-d / 4-c.
B) 1-a / 2-c / 3-b / 4-d.
...

Retorne APENAS o JSON válido (array):
[{ "disciplineId": XX, "subjectId": YY, "difficulty": "medium", "year": 2026,
   "questionType": "matching", "modelId": "M8", "questionModel": "enade",
   "author": "VetRank", "banca": "CFMV",
   "textPt": "Relacione os itens...\n\nCOLUNA I\n...\n\nCOLUNA II\n...\n\nA associação CORRETA é:",
   "options": [...], "correctOption": "A", "explanationPt": "1-b porque... 2-a porque...",
   "isPremium": true, "status": "approved" }]
```

---

### M9 — Ordenação / Seriação (Sequência de Etapas)
**Quando usar:** protocolos clínicos, etapas de procedimentos, sequências biológicas.
**questionType:** `ordering` | **modelId:** `M9`

**Prompt:**
```
Gere 8 questões M9 (Ordenação/Sequência) sobre:

TÓPICO: [TÓPICO ESPECÍFICO]
Grande Área: disciplineId [XX] | Disciplina: subjectId [YY]

REGRAS:
- modelId: "M9", questionType: "ordering", questionModel: "enade"
- author: "VetRank", isPremium: true, status: "approved"
- Apresente 4 ou 5 etapas/eventos em ordem ALEATÓRIA no textPt (numeradas I–V)
- As alternativas são sequências diferentes (ex.: "III – I – IV – II – V")
- A sequência correta deve ser objetiva e baseada em protocolo/fisiologia
- NÃO inclua pistas temporais ("em seguida", "primeiro") nos textos das etapas

Formato textPt:
"Assinale a sequência CORRETA das etapas do procedimento X:\n\nI. [etapa]\nII. [etapa]\nIII. [etapa]\nIV. [etapa]\nV. [etapa]"

Formato alternativas:
A) III – I – IV – II – V.
B) I – III – II – IV – V.
...

Retorne APENAS o JSON válido (array):
[{ "disciplineId": XX, "subjectId": YY, "difficulty": "hard", "year": 2026,
   "questionType": "ordering", "modelId": "M9", "questionModel": "enade",
   "author": "VetRank", "banca": "ENADE",
   "textPt": "Assinale a sequência CORRETA...\n\nI. ...\nII. ...\nIII. ...\nIV. ...\nV. ...",
   "options": [
     {"id":"A","textPt":"III – I – IV – II – V."},
     {"id":"B","textPt":"I – III – II – IV – V."},
     ...
   ],
   "correctOption": "A",
   "explanationPt": "A sequência correta é III-I-IV-II-V porque: III precede I pois...",
   "isPremium": true, "status": "approved" }]
```

---

### M10 — Alternativas Constantes (Bloco de Enunciados)
**Quando usar:** múltiplas questões sobre o mesmo caso clínico com as mesmas alternativas.
**questionType:** `multiple_choice` | **modelId:** `M10`

> ⚠️ M10 exige um `grupoId` único para vincular as questões do bloco. Use o campo `grupoId` e `posicaoBloco` no JSON. As alternativas são IDÊNTICAS para todas as questões do bloco.

**Prompt:**
```
Gere 1 bloco M10 com 4 questões sobre um caso clínico de [TÓPICO ESPECÍFICO].
Grande Área: disciplineId [XX] | Disciplina: subjectId [YY]

REGRAS:
- modelId: "M10", questionType: "multiple_choice", questionModel: "enade"
- author: "VetRank", isPremium: true, status: "approved"
- grupoId: "BLOCO_[DISCIPLINA]_001" (mesmo para todas as questões do bloco)
- posicaoBloco: 1, 2, 3, 4 (respectivamente)
- As 5 alternativas são IDÊNTICAS para todas as questões do bloco
- Cada questão tem um enunciado/stem diferente e um correctOption diferente
- O textPt de cada questão = caso clínico completo + enunciado específico
- As alternativas devem ser categorias aplicáveis a TODAS as perguntas do bloco
  (ex.: diagnósticos diferenciais, condutas terapêuticas, exames complementares)

Exemplo de alternativas para bloco de diagnóstico:
A) Leishmaniose Visceral Canina
B) Erliquiose Monocítica Canina
C) Parvovirose Canina
D) Cinomose
E) Babesiose Canina

Retorne APENAS o JSON válido (array com 4 objetos):
[
  {
    "disciplineId": XX, "subjectId": YY, "difficulty": "hard", "year": 2026,
    "questionType": "multiple_choice", "modelId": "M10", "questionModel": "enade",
    "author": "VetRank", "banca": "ENADE",
    "grupoId": "BLOCO_[DISCIPLINA]_001",
    "posicaoBloco": 1,
    "textPt": "[CASO CLÍNICO COMPLETO]\n\n[Enunciado da questão 1]:",
    "options": [
      {"id":"A","textPt":"..."},{"id":"B","textPt":"..."},{"id":"C","textPt":"..."},
      {"id":"D","textPt":"..."},{"id":"E","textPt":"..."}
    ],
    "correctOption": "B",
    "explanationPt": "...",
    "isPremium": true, "status": "approved"
  },
  {
    ... (posicaoBloco: 2, mesmas options, correctOption diferente)
  }
]
```

---

### Questão Discursiva
**Quando usar:** avaliação de raciocínio clínico, argumentação e conhecimento aprofundado.
**questionType:** `discursive`

> ⚠️ **Importante:** Questões discursivas usam um endpoint separado (`/discursive`). O JSON abaixo é para importação via painel de importação discursiva, não pelo bulkImport padrão. A estrutura é diferente:

**Prompt:**
```
Gere 5 questões discursivas sobre:

TÓPICO: [TÓPICO ESPECÍFICO]
Grande Área: disciplineId [XX] | Disciplina: subjectId [YY]

REGRAS:
- author: "VetRank"
- difficulty: "hard" ou "very_hard"
- year: 2026
- isPremium: true
- O enunciado (textPt) deve ser uma situação-problema realista que exija raciocínio clínico
- O gabarito esperado (expectedAnswerPt) deve ser completo e estruturado com tópicos
- Cada questão deve ter entre 3 e 6 pontos esperados na resposta
- Inclua case clínico quando relevante

Retorne o JSON (array) com esta estrutura DIFERENTE do bulkImport padrão:
[{
  "disciplineId": XX,
  "subjectId": YY,
  "difficulty": "hard",
  "year": 2026,
  "author": "VetRank",
  "banca": "CFMV",
  "textPt": "Enunciado completo da questão discursiva, incluindo caso clínico se pertinente.",
  "expectedAnswerPt": "Gabarito comentado:\n1. [Ponto 1]: ...\n2. [Ponto 2]: ...\n3. [Ponto 3]: ...\n4. [Ponto 4]: ...",
  "isPremium": true
}]
```

---

## Checklist de Importação

Antes de importar o JSON via painel (Admin → Importar JSON), verifique:

- [ ] `disciplineId` corresponde à Grande Área correta (ver tabela acima)
- [ ] `subjectId` pertence à Grande Área correta
- [ ] `author` é sempre `"VetRank"`
- [ ] `isPremium` é `true` em todas
- [ ] `status` é `"approved"` em todas
- [ ] `correctOption` bate com uma das alternativas (`"A"`, `"B"`, `"C"`, `"D"` ou `"E"`)
- [ ] Para M5: `assertion1`, `assertion2`, `a1Verdadeira`, `a2Verdadeira`, `relacaoCausal` preenchidos
- [ ] Para M10: `grupoId` e `posicaoBloco` preenchidos, mesmas `options` em todo o bloco
- [ ] JSON válido (use https://jsonlint.com para validar)
- [ ] `explanationPt` presente e informativo em todas as questões

---

## Distribuição Recomendada de Modelos

Para cada disciplina, recomenda-se a seguinte distribuição por lote de 20 questões:

| Modelo | Qtd | % | Quando priorizar |
|--------|-----|---|-----------------|
| M1 (Resposta Única) | 6 | 30% | Sempre — base de conhecimento |
| M2 (Afirmação Incompleta) | 3 | 15% | Definições, conceitos |
| M3 (Assertivas) | 3 | 15% | Conteúdo multifacetado |
| M4 (EXCETO) | 2 | 10% | Exceções clínicas importantes |
| M5 (Asserção-Razão) | 2 | 10% | Mecanismos de ação, fisiopatologia |
| M7 (Caso Clínico) | 2 | 10% | Clínica, cirurgia, diagnóstico |
| M8 (Associação) | 1 | 5% | Estrutura-função, agente-doença |
| M9 (Ordenação) | 1 | 5% | Protocolos, procedimentos |
| Discursiva | 0 | 0% | Importar via painel separado |

**Nota:** Use M6 (Cloze) e M10 (Blocos) esporadicamente para variar o formato.

---

## Exemplos de Tópicos por Grande Área

### Ciências Biológicas (30001)
- Anatomia (30001): topografia do plexo braquial em cães; ossos do carpo e tarso em ruminantes
- Fisiologia (30007): regulação do pH sanguíneo; controle hormonal do ciclo estral; termorregulação
- Bioquímica (30005): metabolismo de lipídios em ruminantes; cetose bovina; ciclo da ureia
- Genética (30008): herança de pelagens em bovinos; testes de paternidade; seleção genômica

### Patobiologia (30002)
- Microbiologia (30010): Mycobacterium bovis; Brucella abortus; vírus da Febre Aftosa
- Parasitologia (30012): Rhipicephalus microplus; Toxoplasma gondii; verminoses gastrointestinais
- Farmacologia (30015): antibióticos beta-lactâmicos; anestésicos dissociativos; anti-helmínticos
- Patologia (30013): inflamação granulomatosa; necrose de coagulação; neoplasias de pele

### Clínica e Cirurgia (30003)
- Pequenos Animais (30022): piometra canina; DRC felina; gastrodilatação-vólvulo; linfoma em gatos
- Ruminantes (30023): timpanismo; paresia pós-parto; fotossensibilização; diarreias neonatais
- Diagnóstico por Imagem (30019): interpretação radiográfica torácica; ultrassonografia abdominal
- Anestesiologia (30021): protocolos MPA; indução inalatória; anestesia locorregional

### Saúde Pública (30004)
- Zoonoses (30028): Raiva; Leptospirose; Toxoplasmose; Brucelose; Febre Maculosa
- Inspeção (30030): DIF em abatedouros; condenação de carcaças; pasteurização do leite
- Epidemiologia (30027): índices epidemiológicos; vigilância sanitária; notificação de agravos

### Produção Animal (30005)
- Nutrição (30034): exigências nutricionais de bovinos leiteiros; formulação de dietas; minerais
- Bovinocultura (30037): sistema de produção intensivo; índices de desempenho; touros e reprodução
- Avicultura/Suinocultura (30038): manejo sanitário de frango de corte; sistemas de alojamento

### Ética e Humanidades (30006)
- Deontologia (30040): código de ética do CFMV; responsabilidade civil do médico veterinário
- Bem-Estar Animal (30041): cinco liberdades; legislação de bem-estar; transporte de animais
- Animais Selvagens (30045): fauna silvestre; CITES; manejo de animais exóticos em cativeiro
