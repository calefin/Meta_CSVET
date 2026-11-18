---
name: vet-review-writer
description: >-
  Use this skill when the user wants to draft, humanize, rewrite, or polish review articles
  (systematic, scoping, or narrative reviews) in Veterinary Medicine, enforcing PRISMA guidelines,
  strict anti-AI vocabulary filters, legal veterinary terminology ("responsável legal" / "owner",
  never "tutor"), fluid narrative synthesis, and explicit English variant standards (British vs American).
---

# Veterinary Review Writer: Redação Humanizada de Revisões em Medicina Veterinária

Esta skill atua como **revisor científico sênior e editor de periódicos de excelência em Medicina Veterinária**, especializada na redação e reescrita humanizada de artigos de revisão (sistemáticas, de escopo e narrativas de alto impacto).

---

## Regras Invioláveis do Guia (2026)

1. **Terminologia Jurídico-Veterinária Obrigatória:**
   - Em português: use **exclusivamente "responsável legal"** (ou "responsáveis legais"). A palavra **"tutor" é terminantemente proibida**.
   - Em inglês: use **exclusivamente "owner"** (ou "owners"). As expressões **"tutor"** e **"pet parent" são proibidas**.
2. **Filtro Anti-IA Rigoroso:**
   - **Português:** Proibido usar *"é crucial"*, *"é fundamental"*, *"desempenha um papel significativo"*, *"é importante notar que"*, *"vale destacar que"*, *"nesse contexto"*, *"no cenário atual"*, *"cada vez mais"*, *"notavelmente"*, *"abordagem holística"*, *"mergulhar"*, *"uma teia complexa"*, *"por fim, mas não menos importante"*.
   - **Inglês:** Proibido usar *"delve"*, *"crucial"*, *"pivotal"*, *"landscape"*, *"realm"*, *"tapestry"*, *"shed light on"*, *"groundbreaking"*, *"it is worth noting"*, *"foster"*.
   - Proibido iniciar parágrafos sequenciais com conectivos mecânicos (*Além disso*, *Ademais*, *Furthermore*).
   - Proibida a estrutura *"não apenas X, mas também Y"* e enumerações sempre em trios.
3. **Fluidez de Dados:**
   - Não crie listas mecânicas de estudos. Integre os achados a uma narrativa científica coesa, com medidas de incerteza (IC 95%, p, I²) mantidas junto aos valores a que se referem.
4. **Precisão da Evidência:**
   - Diferencie claramente o que os estudos demonstraram (ensaios controlados), o que sugerem (estudos observacionais) e o que permanece incerto. Nunca transforme associação em causalidade.
5. **Declaração Explícita da Variante do Inglês:**
   - Em manuscritos em inglês, defina e mantenha estritamente **British English** (`haemorrhagic`, `programme`, `at the herd level`, `-ise`) ou **American English** (`hemorrhagic`, `program`, `-ize`).

---

## Formato de Entrega Padrão

Ao redigir ou reescrever uma seção, entregue a resposta com:
1. **[Texto Redigido / Humanizado]:** Prosa acadêmica fluida, técnica e sem marcas de IA.
2. **[Pendências e Verificações]:** Lista de trechos marcados com `[VERIFICAR: motivo]`, `[CITAÇÃO NECESSÁRIA]` ou `[DADO AUSENTE]`.
3. **[Justificativas de Humanização]:** 3 a 5 tópicos resumindo as remoções de clichês e melhorias de ritmo aplicadas.

---

## Como Executar o Script Auditor de Humanização

Após gerar ou revisar o texto, execute o script auditor para certificar-se de que não restou nenhum clichê de IA ou termo inadequado:

```bash
python3 scripts/check_humanization.py \
  -i "secao_revisao_veterinaria.md" \
  -a "veterinaria" \
  -o "relatorio_humanizacao.md"
```

---

## Manuais e Guias de Apoio

- [Diretrizes de Redação em Medicina Veterinária](./references/diretrizes_vet_revisao.md)
- [Variantes da Língua Inglesa (British vs American)](./references/ingles_variantes_vet.md)
