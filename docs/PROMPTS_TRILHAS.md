# VetRank — Guia de Prompts para Criação de Trilhas de Aprendizado

> As trilhas são percursos de estudo estruturados com módulos sequenciais e exame final.
> Cada módulo tem um mínimo de acerto para avançar. O aluno ganha XP ao responder.
>
> **Como usar este guia:**
> 1. Escolha o tipo de trilha (foco, abrangência, dificuldade)
> 2. Copie o prompt correspondente e substitua as variáveis
> 3. A IA retorna um JSON pronto para inserção via SQL ou painel Admin
> 4. Insira no banco via `scripts/insert_trail.sql` ou painel

---

## Estrutura do Banco

### Tabela `trails` (cabeçalho da trilha)
| Campo | Tipo | Descrição |
|-------|------|-----------|
| `disciplineId` | int | ID da Grande Área (30001–30006) |
| `title` | varchar | Nome da trilha |
| `description` | text | Descrição completa do percurso |
| `totalHours` | int | Carga horária estimada total |
| `passingScore` | int | % mínimo para passar (recomendado: 70) |
| `finalExamQuestions` | int | Questões no exame final (10–100) |
| `finalExamTimeSeconds` | int | Tempo do exame final em segundos |

### Tabela `trail_modules` (módulos da trilha)
| Campo | Tipo | Descrição |
|-------|------|-----------|
| `trailId` | int | ID da trilha pai |
| `order` | int | Posição sequencial (1, 2, 3...) |
| `title` | varchar | Título do módulo |
| `summary` | text | Resumo do conteúdo abordado |
| `difficulty` | enum | `easy` \| `medium` \| `hard` \| `mixed` |
| `questionCount` | int | Questões neste módulo (5–100) |
| `minPassRate` | int | % mínimo para passar o módulo (50–100) |
| `questionFilter` | JSON | `{"disciplineId": X, "subjectIds": [Y,Z], "difficulty": "medium"}` |

---

## IDs de Referência

### Grandes Áreas (disciplineId)
| ID | Nome |
|----|------|
| 30001 | Ciências Biológicas e Ciclo Básico |
| 30002 | Patobiologia e Agentes Agressores |
| 30003 | Clínica, Cirurgia e Reprodução Animal |
| 30004 | Medicina Preventiva, Saúde Pública e Inspeção |
| 30005 | Zootecnia e Produção Animal |
| 30006 | Ética, Humanidades e Áreas Complementares |

### Disciplinas (subjectId) — para o questionFilter
| ID | Disciplina | Grande Área |
|----|-----------|-------------|
| 30001 | Anatomia Veterinária I e II | 30001 |
| 30002 | Biologia Celular e Molecular | 30001 |
| 30003 | Histologia Veterinária | 30001 |
| 30004 | Embriologia Veterinária | 30001 |
| 30005 | Bioquímica Veterinária | 30001 |
| 30006 | Biofísica Veterinária | 30001 |
| 30007 | Fisiologia Veterinária I e II | 30001 |
| 30008 | Genética e Evolução Animal | 30001 |
| 30009 | Bioestatística e Metodologia | 30001 |
| 30010 | Microbiologia Veterinária | 30002 |
| 30011 | Imunologia Veterinária | 30002 |
| 30012 | Parasitologia Veterinária | 30002 |
| 30013 | Patologia Geral e Necropsia | 30002 |
| 30014 | Patologia Especial/Sistêmica | 30002 |
| 30015 | Farmacologia e Terapêutica | 30002 |
| 30016 | Toxicologia Veterinária | 30002 |
| 30017 | Semiologia Veterinária | 30003 |
| 30018 | Patologia Clínica (Lab. Diagnóstico) | 30003 |
| 30019 | Diagnóstico por Imagem | 30003 |
| 30020 | Técnica Cirúrgica Veterinária | 30003 |
| 30021 | Anestesiologia Veterinária | 30003 |
| 30022 | Clínica de Pequenos Animais | 30003 |
| 30023 | Clínica de Ruminantes | 30003 |
| 30024 | Clínica de Equídeos | 30003 |
| 30025 | Fisiopatologia da Reprodução | 30003 |
| 30026 | Obstetrícia e Biotecnologia Reprodutiva | 30003 |
| 30027 | Epidemiologia Veterinária | 30004 |
| 30028 | Zoonoses e Saúde Pública | 30004 |
| 30029 | Saneamento Ambiental | 30004 |
| 30030 | Inspeção de Produtos de Origem Animal | 30004 |
| 30031 | Tecnologia de Produtos de Origem Animal | 30004 |
| 30032 | Higiene de Alimentos | 30004 |
| 30033 | Introdução à Zootecnia | 30005 |
| 30034 | Nutrição e Alimentação Animal | 30005 |
| 30035 | Agrostologia e Forragicultura | 30005 |
| 30036 | Melhoramento Genético Animal | 30005 |
| 30037 | Bovinocultura de Corte e de Leite | 30005 |
| 30038 | Avicultura, Suinocultura e Equideocultura | 30005 |
| 30039 | Aquicultura e Piscicultura | 30005 |
| 30040 | Deontologia, Ética e Bioética | 30006 |
| 30041 | Bem-Estar Animal e Etologia | 30006 |
| 30042 | Medicina Veterinária Legal | 30006 |
| 30043 | Administração e Planejamento Rural | 30006 |
| 30044 | Extensão Rural e Sociologia | 30006 |
| 30045 | Medicina de Animais Selvagens | 30006 |

---

## Formato de Saída JSON

O JSON gerado tem esta estrutura (pronto para inserção SQL):

```json
{
  "title": "Nome da Trilha",
  "description": "Descrição completa...",
  "disciplineId": 30001,
  "accessType": "subscription",
  "totalHours": 60,
  "passingScore": 70,
  "finalExamQuestions": 40,
  "finalExamTimeSeconds": 3600,
  "modules": [
    {
      "title": "Módulo 1 — Título",
      "summary": "Conteúdos abordados neste módulo...",
      "difficulty": "easy",
      "questionCount": 40,
      "minPassRate": 70,
      "questionFilter": {
        "disciplineId": 30001,
        "subjectIds": [30007],
        "difficulty": "easy"
      }
    }
  ]
}
```

> **Atenção:** os campos da trilha (`title`, `description`, `disciplineId`, etc.) ficam na **raiz** do JSON, não dentro de uma chave `"trail"`. Os módulos **não** precisam do campo `"order"` — a ordem é definida pela posição no array.

---

## Script SQL de Inserção

Após gerar o JSON, use este template para inserir no banco:

```sql
-- 1. Inserir a trilha
INSERT INTO trails (disciplineId, title, description, totalHours, passingScore, finalExamQuestions, finalExamTimeSeconds, active, createdBy)
VALUES ([disciplineId], '[title]', '[description]', [totalHours], [passingScore], [finalExamQuestions], [finalExamTimeSeconds], 1, 1);

-- 2. Capturar o ID gerado
SET @trailId = LAST_INSERT_ID();

-- 3. Inserir cada módulo
INSERT INTO trail_modules (trailId, `order`, title, summary, difficulty, questionCount, minPassRate, questionFilter)
VALUES
  (@trailId, 1, 'Módulo 1 — ...', 'Resumo...', 'easy', 40, 70, '{"disciplineId":30001,"subjectIds":[30007],"difficulty":"easy"}'),
  (@trailId, 2, 'Módulo 2 — ...', 'Resumo...', 'medium', 50, 70, '{"disciplineId":30001,"subjectIds":[30007],"difficulty":"medium"}'),
  (@trailId, 3, 'Módulo 3 — ...', 'Resumo...', 'hard', 55, 70, '{"disciplineId":30001,"subjectIds":[30007],"difficulty":"hard"}');
```

---

## Prompts por Tipo de Trilha

---

### TIPO 1 — Trilha de Disciplina Única (Foco Profundo)
**Quando usar:** aprendizado aprofundado de uma disciplina específica, do básico ao avançado.
**Estrutura:** 5–8 módulos, progressão easy → medium → hard. Exame final com 40 questões.

```
Você é especialista em currículo veterinário e design instrucional.

Crie uma trilha de aprendizado VetRank para a disciplina abaixo.

DISCIPLINA: [NOME DA DISCIPLINA]
disciplineId: [ID DA GRANDE ÁREA]
subjectIds: [[ID DA DISCIPLINA]]

REQUISITOS:
- 6 a 8 módulos sequenciais, do mais fácil ao mais difícil
- Progressão: 2 módulos easy → 3 módulos medium → 2–3 módulos hard
- Cada módulo: 40–60 questões, minPassRate: 70
- totalHours: estimativa realista (ex.: 60–80h para disciplina completa)
- passingScore: 70
- finalExamQuestions: 40
- finalExamTimeSeconds: 3600 (1 hora)
- O title do módulo deve seguir o padrão: "Módulo N — [Tópico Específico]"
- O summary deve listar os principais conteúdos abordados (3–5 tópicos)
- O questionFilter deve ter disciplineId e subjectIds corretos + difficulty do módulo

Retorne APENAS o JSON válido com "trail" e "modules":

{
  "trail": {
    "disciplineId": [ID],
    "title": "...",
    "description": "...",
    "totalHours": 70,
    "passingScore": 70,
    "finalExamQuestions": 40,
    "finalExamTimeSeconds": 3600
  },
  "modules": [
    {
      "order": 1,
      "title": "Módulo 1 — ...",
      "summary": "...",
      "difficulty": "easy",
      "questionCount": 40,
      "minPassRate": 70,
      "questionFilter": {"disciplineId": [ID], "subjectIds": [[subjectId]], "difficulty": "easy"}
    }
  ]
}
```

**Exemplos de uso:**
- Fisiologia Veterinária I e II (subjectId: 30007)
- Farmacologia e Terapêutica (subjectId: 30015)
- Parasitologia Veterinária (subjectId: 30012)
- Clínica de Pequenos Animais (subjectId: 30022)

---

### TIPO 2 — Trilha de Grande Área Completa (Revisão Ampla)
**Quando usar:** revisão de toda uma grande área do CFMV, cobrindo múltiplas disciplinas.
**Estrutura:** 8–12 módulos, cada um dedicado a uma disciplina diferente. Exame final com 50 questões.

```
Você é especialista em currículo veterinário e design instrucional.

Crie uma trilha de revisão completa para a Grande Área VetRank abaixo.

GRANDE ÁREA: [NOME]
disciplineId: [ID]
Disciplinas incluídas: [lista de disciplinas com subjectIds]

REQUISITOS:
- 8–12 módulos, um ou dois por disciplina da grande área
- Dificuldade: misturar medium e hard (não use easy — é revisão para concurso)
- Cada módulo: 50–60 questões, minPassRate: 70
- totalHours: 80–120h
- passingScore: 70
- finalExamQuestions: 50
- finalExamTimeSeconds: 5400 (1h30)
- O questionFilter deve ter subjectIds específico de cada disciplina
- O title da trilha deve incluir "— Revisão Completa" ou "— Preparação CFMV"

Retorne APENAS o JSON válido com "trail" e "modules".
```

**Exemplos de uso:**
- Ciências Biológicas — Revisão Completa (disciplineId: 30001, subjectIds: 30001–30009)
- Patobiologia — Preparação CFMV (disciplineId: 30002, subjectIds: 30010–30016)
- Clínica e Cirurgia — Revisão Geral (disciplineId: 30003, subjectIds: 30017–30026)

---

### TIPO 3 — Trilha Temática (Recorte Específico)
**Quando usar:** temas transversais que cruzam mais de uma disciplina — ex.: "Doenças Infecciosas", "Reprodução Animal", "Diagnóstico Laboratorial".
**Estrutura:** 4–6 módulos temáticos. Exame final com 30 questões.

```
Você é especialista em currículo veterinário e design instrucional.

Crie uma trilha temática VetRank sobre o tema abaixo.

TEMA: [TEMA ESPECÍFICO]
DISCIPLINAS ENVOLVIDAS: [lista de disciplinas e subjectIds relevantes]
disciplineId principal: [ID DA GRANDE ÁREA PRINCIPAL]

REQUISITOS:
- 4–6 módulos, cada um com enfoque em um aspecto do tema
- Dificuldade: progressão easy → medium → hard
- Cada módulo: 30–50 questões
- O questionFilter pode ter múltiplos subjectIds de disciplinas diferentes
- totalHours: 30–50h (trilha mais curta e focada)
- passingScore: 70
- finalExamQuestions: 30
- finalExamTimeSeconds: 2700 (45 min)
- title da trilha: "[TEMA] — Trilha Temática VetRank"

Retorne APENAS o JSON válido com "trail" e "modules".
```

**Exemplos de temas transversais:**
| Tema | disciplineId | subjectIds envolvidos |
|------|-------------|----------------------|
| Doenças Infecciosas e Parasitárias | 30002 | 30010, 30011, 30012 |
| Diagnóstico Clínico e Laboratorial | 30003 | 30017, 30018, 30019 |
| Reprodução e Obstetrícia | 30003 | 30025, 30026 |
| Zoonoses e Saúde Pública | 30004 | 30027, 30028, 30029 |
| Nutrição e Produção Animal | 30005 | 30034, 30035, 30037 |
| Farmacologia Clínica Aplicada | 30002 | 30015, 30016 |
| Anestesia e Cirurgia | 30003 | 30020, 30021 |
| Ética Profissional e Legislação | 30006 | 30040, 30041, 30042 |

---

### TIPO 4 — Trilha de Preparação para Residência
**Quando usar:** candidatos à residência em área específica. Alta dificuldade, foco em raciocínio clínico.
**Estrutura:** 6–8 módulos hard, exame final rigoroso com 50 questões, 80% de aprovação.

```
Você é especialista em currículo veterinário e design instrucional.

Crie uma trilha de preparação para RESIDÊNCIA VETERINÁRIA em:

ESPECIALIDADE: [NOME DA ESPECIALIDADE]
disciplineId: [ID]
subjectIds principais: [IDs]

REQUISITOS:
- 6–8 módulos de alta complexidade
- Dificuldade: 2 módulos medium → 4–6 módulos hard
- Cada módulo: 50–70 questões de dificuldade hard
- Foco em casos clínicos, raciocínio diagnóstico e tomada de decisão
- minPassRate: 75 (mais exigente que trilhas normais)
- totalHours: 80–100h
- passingScore: 75
- finalExamQuestions: 50
- finalExamTimeSeconds: 5400 (1h30)
- title: "[Especialidade] — Trilha de Residência"
- O summary de cada módulo deve mencionar "raciocínio clínico" e "casos complexos"
- questionFilter: {"disciplineId": X, "subjectIds": [Y], "difficulty": "hard"}

Retorne APENAS o JSON válido com "trail" e "modules".
```

**Exemplos de especialidades:**
- Clínica Médica de Pequenos Animais (subjectId: 30022)
- Cirurgia Veterinária (subjectIds: 30020, 30021)
- Patologia Animal (subjectIds: 30013, 30014)
- Reprodução Animal (subjectIds: 30025, 30026)
- Medicina de Animais Selvagens (subjectId: 30045)
- Saúde Pública Veterinária (subjectIds: 30027, 30028)

---

### TIPO 5 — Trilha Express (Revisão Rápida)
**Quando usar:** revisão rápida pré-prova, candidatos com pouco tempo.
**Estrutura:** 3–4 módulos, mix de dificuldades, 20–30 questões por módulo. Exame final com 20 questões.

```
Você é especialista em currículo veterinário e design instrucional.

Crie uma trilha EXPRESS (revisão rápida) sobre:

TEMA/DISCIPLINA: [TEMA]
disciplineId: [ID]
subjectIds: [IDs]

REQUISITOS:
- APENAS 3–4 módulos (trilha curta!)
- Dificuldade: mixed (combina easy, medium e hard em cada módulo)
- Cada módulo: 20–30 questões
- minPassRate: 65 (mais acessível)
- totalHours: 15–25h
- passingScore: 65
- finalExamQuestions: 20
- finalExamTimeSeconds: 1800 (30 min)
- title: "[Tema] — Revisão Express"
- O summary deve ser direto: "Revisão rápida dos pontos mais cobrados em [tema]"
- questionFilter: {"disciplineId": X, "subjectIds": [Y], "difficulty": "mixed"}

Retorne APENAS o JSON válido com "trail" e "modules".
```

---

### TIPO 6 — Mega-Trilha CFMV (Preparação Completa para Exame)
**Quando usar:** preparação completa para o exame do CFMV, cobrindo todas as 6 grandes áreas.
**Estrutura:** 12–18 módulos, 2–3 por grande área. Exame final com 60 questões. Trilha de meses.

```
Você é especialista em currículo veterinário e design instrucional.

Crie a MEGA-TRILHA de preparação completa para o exame do CFMV.
Esta trilha deve cobrir todas as 6 grandes áreas, com 2–3 módulos cada.

GRANDES ÁREAS A COBRIR:
- Ciências Biológicas (30001) — subjectIds: 30001–30009
- Patobiologia (30002) — subjectIds: 30010–30016
- Clínica e Cirurgia (30003) — subjectIds: 30017–30026
- Medicina Preventiva (30004) — subjectIds: 30027–30032
- Zootecnia (30005) — subjectIds: 30033–30039
- Ética e Humanidades (30006) — subjectIds: 30040–30045

REQUISITOS:
- 12–18 módulos no total (2–3 por grande área)
- Use disciplineId: 30001 (trilha associada à área de Biológicas como âncora)
  OU crie um disciplineId especial — use 30001 se precisar de um único
- Dificuldade: medium e hard (é preparação para concurso)
- Cada módulo: 50–60 questões
- minPassRate: 70
- totalHours: 200 (é a maior trilha)
- passingScore: 70
- finalExamQuestions: 60
- finalExamTimeSeconds: 7200 (2 horas)
- title: "Preparação Completa CFMV — Todas as Grandes Áreas"
- O questionFilter de cada módulo usa o subjectIds da grande área correspondente

Retorne APENAS o JSON válido com "trail" e "modules".
```

---

## Trilhas Sugeridas para Popular o Banco

Execute este checklist para ter uma base completa de trilhas:

### Prioridade Alta (criar primeiro — maior demanda)
- [ ] **Fisiologia Veterinária I** — 7 módulos, 60h (subjectId: 30007)
- [ ] **Fisiologia Veterinária II** — 5 módulos, 45h (subjectId: 30007)
- [ ] **Farmacologia Clínica** — 6 módulos, 55h (subjectId: 30015)
- [ ] **Clínica de Pequenos Animais** — 8 módulos, 80h (subjectId: 30022)
- [ ] **Patologia Geral** — 6 módulos, 60h (subjectId: 30013)
- [ ] **Parasitologia Veterinária** — 5 módulos, 50h (subjectId: 30012)
- [ ] **Microbiologia Veterinária** — 5 módulos, 50h (subjectId: 30010)
- [ ] **Zoonoses — Trilha Temática** — 4 módulos, 35h (subjectIds: 30027, 30028)

### Prioridade Média
- [ ] **Anatomia Veterinária** — 6 módulos, 60h (subjectId: 30001)
- [ ] **Bioquímica Veterinária** — 5 módulos, 45h (subjectId: 30005)
- [ ] **Imunologia Veterinária** — 4 módulos, 40h (subjectId: 30011)
- [ ] **Patologia Especial** — 6 módulos, 55h (subjectId: 30014)
- [ ] **Clínica de Ruminantes** — 6 módulos, 60h (subjectId: 30023)
- [ ] **Reprodução Animal** — 5 módulos, 50h (subjectIds: 30025, 30026)
- [ ] **Inspeção de Alimentos** — 5 módulos, 45h (subjectIds: 30030, 30031, 30032)
- [ ] **Nutrição Animal** — 4 módulos, 40h (subjectId: 30034)

### Express (revisão rápida)
- [ ] **Farmacologia — Express** — 3 módulos, 20h
- [ ] **Zoonoses — Express** — 3 módulos, 18h
- [ ] **Ética Profissional — Express** — 3 módulos, 15h
- [ ] **Anestesia e Cirurgia — Express** — 3 módulos, 20h

### Residência
- [ ] **Residência em Pequenos Animais** — 7 módulos hard, 90h
- [ ] **Residência em Cirurgia** — 6 módulos hard, 80h
- [ ] **Residência em Saúde Pública** — 6 módulos hard, 75h

---

## Formato das Questões Vinculadas às Trilhas

As trilhas não criam questões próprias — elas filtram e selecionam questões do banco existente via `questionFilter`. Para garantir que as questões exibidas nas trilhas sejam renderizadas corretamente, os lotes de questões devem seguir o padrão de formatação descrito em **PROMPTS_QUESTOES.md**.

### Resumo do Padrão de Quebras de Linha

Ao gerar questões destinadas a trilhas (especialmente M3, M5, M6, M8, M9), use sempre `\n` explícito no JSON:

| Tipo | Quebra obrigatória |
|------|--------------------|
| M3 assertivas | `\n\nI. ...\nII. ...\nIII. ...` — cada assertiva em linha própria |
| M5 PORQUE | `\n\nPORQUE\n\n` — o conector em bloco isolado |
| M6 lacuna | `[___]` ou `[1]`/`[2]` para lacunas; `\n\n` antes do comando |
| M8 colunas | `\nCOLUNA I\n1. ...\n\nCOLUNA II\na) ...` — headers explícitos em maiúsculas |
| M9 sequência | `\n\nI. ...\nII. ...\nIII. ...` — cada etapa em linha própria |

> **Formato inline reconhecido mas não recomendado:** o importador reconhece questões de colunas no formato `(Coluna I) ... 1. Item 2. Item ... a) Alt b) Alt` (tudo em uma linha), porém a renderização visual em colunas é garantida apenas com os headers explícitos `COLUNA I` / `COLUNA II`.

### Checklist ao Criar Questões para Trilhas

- [ ] M3: `textPt` contém `\n\nI. ...\nII. ...\nIII. ...\nIV. ...` (assertivas separadas)
- [ ] M5: `assertion1` e `assertion2` preenchidos como campos separados E no `textPt`
- [ ] M6: lacunas marcadas com `[___]`, `[1]`, `[2]`; comando no final separado por `\n\n`
- [ ] M8: `COLUNA I` e `COLUNA II` em maiúsculas, itens separados por `\n`, sem itens inline
- [ ] M9: etapas numeradas `I.`–`V.` separadas por `\n`, não em linha única
- [ ] `explanationPt` informativo em todas (os alunos leem o feedback pós-resposta)
- [ ] `difficulty` adequado à dificuldade do módulo que vai receber a questão

---

## Inserção Rápida no Banco de Produção

Após gerar o JSON, adapte para este comando no VPS:

```bash
docker exec -i mysql-shared mysql -h 127.0.0.1 -u appuser -pXsanQqTJkz8JE0jXHQU5 vetrank << 'EOF'
-- Cole aqui o SQL gerado a partir do JSON
INSERT INTO trails (...) VALUES (...);
SET @trailId = LAST_INSERT_ID();
INSERT INTO trail_modules (...) VALUES
  (@trailId, 1, ...),
  (@trailId, 2, ...),
  ...;
SELECT CONCAT('Trilha criada com ID: ', @trailId) AS resultado;
EOF
```
