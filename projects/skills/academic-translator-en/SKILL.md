---
name: academic-translator-en
description: >-
  Use this skill when the user wants to translate scientific articles, academic papers,
  monographs, or journal submissions from Portuguese to English without altering the original meaning,
  ensuring strict fidelity to technical terminology, numbers, statistical data, scientific nomenclature,
  and maintaining consistency in British or American English without AI clichés.
---

# Academic Journal Translator: Tradução Científica Fiel (PT $\rightarrow$ EN)

Esta skill atua como **tradutor científico sênior e editor de periódico acadêmico**, especializada na transcrição e tradução de artigos científicos aprovados do Português para o Inglês, **preservando integralmente o sentido, a força probatória (*hedging*) e os dados do documento original**.

---

## Regras Invioláveis de Tradução Científica

1. **Fidelidade Conceitual Absoluta (Zero Distorção):**
   - Não invente, não resuma, não omita e não acrescente dados que não estejam no texto em português.
   - Preserva o nível de certeza exato do original: *"sugeriu"* $\rightarrow$ *suggested* (nunca *proved* ou *demonstrated*).
   - Se um trecho estiver ambíguo ou contiver erro manifesto na origem, traduza de forma neutra e insira a anotação: `[TRANSLATOR NOTE: descreva a questão]`.
2. **Preservação de Dados Numéricos e Estatísticos:**
   - Todos os números, porcentagens, valores de $p$, intervalos de confiança (IC 95%), desvios-padrão ($\pm$) e tamanhos de amostra ($n$) devem sair rigorosamente idênticos.
   - **Conversão de Separador Decimal:** Converta a vírgula decimal do português para **ponto** no inglês ($89,7\% \rightarrow 89.7\%$).
   - **Separador de Milhar:** Converta ponto/espaço para **vírgula** ($1.250 \rightarrow 1,250$).
3. **Terminologia Obrigatória por Área:**
   - **Medicina Veterinária:** *"responsável legal"* traduz-se exclusivamente como **"owner" / "owners"**. É terminantemente proibido o uso de *"tutor"* ou *"pet parent"*.
   - **Ciências Biológicas:** Nomes científicos mantidos rigorosamente em itálico com inicial maiúscula e espécie minúscula (*Gênero espécie*). Nomenclatura anatômica e taxonômica internacional.
   - **Filosofia:** Precisão de termos conceituais (*valid*, *sound*, *premise*, *intuition*, *sentience*).
4. **Filtro Anti-IA em Língua Inglesa:**
   - Eliminação estrita de clichês de tradução por IA: *delve, crucial, pivotal, landscape, realm, tapestry, intricate, shed light on, plays a significant role, groundbreaking, foster, holistic, multifaceted, ultimately, serves as a reminder*.
5. **Declaração e Consistência da Variante de Inglês:**
   - Defina previamente se a revista exige **British English** (UK: `-ise`, `haemorrhagic`, `oedema`, `programme`, `centre`, `colour`) ou **American English** (US: `-ize`, `hemorrhagic`, `edema`, `program`, `center`, `color`, Oxford comma). Mantenha consistência de 100% em todo o manuscrito.

---

## Fluxo de Trabalho de Tradução Editorial

Para garantir máxima qualidade em manuscritos completos, execute a tradução seção por seção:
1. **Título, Resumo e Palavras-chave:** Tradução fiel, preservando a contagem de palavras e descritores controlados (MeSH / DeCS).
2. **Introdução:** Tradução do funil conceitual (problema $\rightarrow$ lacuna $\rightarrow$ objetivo).
3. **Material e Métodos:** Preservação de reagentes, doses, delineamento e análises estatísticas.
4. **Resultados:** Transcrição direta dos dados e referências a tabelas e figuras.
5. **Discussão e Conclusões:** Preservação da linha argumentativa e das limitações declaradas.
6. **Legendas de Figuras e Tabelas:** Tradução das legendas e notas de rodapé de tabelas.

---

## Como Executar a Auditoria Pós-Tradução

Após redigir ou traduzir o artigo, execute o script validador para comparar o texto original em português com a versão em inglês:

```bash
python3 scripts/check_translation.py \
  --pt "artigo_original_pt.md" \
  --en "artigo_traduzido_en.md" \
  --variant "british" \
  --area "veterinaria" \
  -o "relatorio_qualidade_traducao.md"
```

### O que o Relatório de QA Verifica:
- **Discrepâncias Numéricas:** Detecta se algum número do português foi esquecido ou digitado errado no inglês.
- **Detecção de Vírgula Decimal:** Alerta sobre números decimais com vírgula no texto em inglês.
- **Detecção de Clichês de IA:** Rastreia e lista expressões automáticas proibidas.
- **Uso Indevido de "Tutor":** Bloqueia termos veterinários vedados.
- **Mistura de Variantes:** Confere se a grafia britânica ou americana foi respeitada.

---

## Manuais e Guias de Apoio

- [Diretrizes de Tradução Científica Fiel](./references/translation_guidelines.md)
- [Dicionário Terminológico e Falsos Cognatos](./references/terminology_dictionary.md)
- [Padrões Ortográficos: British vs American English](./references/variant_standards.md)
