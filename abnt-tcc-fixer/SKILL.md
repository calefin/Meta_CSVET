---
name: abnt-tcc-fixer
description: >-
  Use this skill when the user wants to automatically fix, correct, format, reorder,
  and standardize academic texts, TCC (Trabalho de Conclusão de Curso), monographs, theses,
  citations, and references according to current 2026 ABNT norms (NBR 10520:2023 author-date
  in lowercase, NBR 6023 references alphabetical sorting and styling, NBR 6024 headings, and NBR 14724).
---

# ABNT TCC Fixer: Correção e Formatação Automática (Normas 2026)

Esta skill executa correções automatizadas e padronização visual e textual em trabalhos acadêmicos (TCC, Monografias, Artigos Científicos, Teses e Dissertações) para colocá-los em conformidade estrita com as normas da ABNT vigentes em 2026.

---

## O que a Skill Corrige Automaticamente

1. **Citações Autor-Data (NBR 10520:2023):**
   - Converte autoria obsoleta em CAIXA ALTA dentro de parênteses para inicial maiúscula e restante minúsculo (ex: `(SILVA, 2020)` $\rightarrow$ `(Silva, 2020)`).
   - Corrige múltiplos autores com ponto e vírgula (ex: `(Silva e Santos, 2020)` $\rightarrow$ `(Silva; Santos, 2020)`).
   - Move pontos finais posicionados incorretamente antes dos parênteses da citação para o término da sentença (ex: `...efeito. (Silva, 2020)` $\rightarrow$ `...efeito (Silva, 2020).`).
   - Padroniza abreviatura de página para `p. XX` (eliminando `pg.`, `pág.`, `P.`).

2. **Numeração Progressiva de Seções (NBR 6024):**
   - Remove pontos desnecessários após o último algarismo das seções (ex: `1. INTRODUÇÃO` $\rightarrow$ `1 INTRODUÇÃO` e `1.1. Contexto` $\rightarrow$ `1.1 Contexto`).

3. **Resumo e Palavras-chave (NBR 6028):**
   - Padroniza a separação das palavras-chave com ponto e vírgula e encerramento com ponto final.

4. **Lista de Referências (NBR 6023):**
   - Reordena automaticamente toda a lista de referências em **ordem alfabética estrita**.
   - Converte datas de acesso a documentos online para a forma oficial com meses abreviados (ex: `Acesso em: 15 nov. 2026.`).
   - Assegura alinhamento à esquerda, espaçamento simples e linha em branco separadora entre entradas.

5. **Geração de Elementos Pré-Textuais (NBR 14724):**
   - Fornece templates prontos para Capa, Folha de Rosto, Folha de Aprovação e Sumário.

---

## Como Executar a Correção Automática

Execute o script de correção apontando para o arquivo que necessita de ajustes:

```bash
python3 scripts/fix_abnt.py \
  -i "/caminho/do/tcc_original.md" \
  -o "tcc_corrigido_abnt.md"
```

### Formatação de Lista de Referências Avulsas:
Caso você possua apenas uma lista desformatada de referências:

```bash
python3 scripts/format_references.py \
  -i "referencias_brutas.txt" \
  -o "referencias_abnt_formatadas.txt"
```

---

## Fluxo Integrado Recomendado (Auditoria + Correção)

Para garantir 100% de conformidade com a banca examinadora:
1. **Passo 1 (Auditoria Inicial):** Execute a skill [`abnt-tcc-verifier`](../abnt-tcc-verifier/SKILL.md) para gerar a nota diagnóstica e a lista de não-conformidades.
2. **Passo 2 (Correção Automática):** Execute o [`fix_abnt.py`](./scripts/fix_abnt.py) da skill `abnt-tcc-fixer` para aplicar os ajustes normativos.
3. **Passo 3 (Verificação Final):** Reexecute a auditoria para confirmar a elevação da nota de conformidade perante a banca.

---

## Manuais e Guias de Referência

- [Tabela de Conversão: Regras Antigas vs Regras Vigentes em 2026](./references/tabela_conversao.md)
- [Templates de Elementos Pré-Textuais](./references/templates_elementos.md)
- [Regras Completas de Formatação](./references/regras_formatacao.md)
