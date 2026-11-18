---
name: abnt-tcc-verifier
description: >-
  Use this skill when the user wants to audit, verify, lint, and check academic papers,
  theses, dissertations, or TCC (Trabalho de Conclusão de Curso) against the current 2026 ABNT norms,
  including NBR 14724 (academic work layout and structure), NBR 10520:2023 (citations author-date in lowercase),
  NBR 6023 (references), NBR 6028 (resumo), and NBR 6024 (progressive section numbering).
---

# ABNT TCC Verifier: Auditoria e Validação Normativa (Normas 2026)

Esta skill audita trabalhos acadêmicos (TCC, Monografias, Artigos Científicos, Teses e Dissertações) para diagnosticar não-conformidades em relação às normas vigentes da **Associação Brasileira de Normas Técnicas (ABNT)**.

---

## Normas ABNT Verificadas

- **NBR 14724 (Trabalhos Acadêmicos):** Estrutura (elementos pré-textuais, textuais e pós-textuais), margens, paginação e espaçamento.
- **NBR 10520:2023 (Citações em Documentos):** Sistema autor-data com sobrenomes em caixa baixa `(Silva, 2020)` em vez do formato obsoleto em maiúsculas `(SILVA, 2020)`, pontuação final após parênteses e formatação de páginas (`p. 15`).
- **NBR 6023 (Referências):** Ordem alfabética estrita, destaque no título ou nome do periódico, datas de acesso com meses abreviados e cruzamento 1:1 entre citações no texto e referências listadas.
- **NBR 6028 (Resumo e Palavras-chave):** Extensão entre 150 e 500 palavras em parágrafo único e palavras-chave separadas por ponto e vírgula.
- **NBR 6024 (Numeração Progressiva):** Ausência de ponto final após o último dígito de seções (ex: `1.1 Seção` em vez de `1.1. Seção`).

---

## Como Executar a Auditoria

Execute o script de verificação apontando para o arquivo de texto ou Markdown do trabalho:

```bash
python3 scripts/verify_abnt.py \
  -i "/caminho/do/trabalho_tcc.md" \
  -o "relatorio_auditoria_abnt.md" \
  -j "relatorio_auditoria.json"
```

### O que o Relatório Entrega:
1. **Nota de Conformidade ABNT (0 a 100):** Cálculo objetivo do nível de adequação do trabalho.
2. **Status da Banca:** *Aprovado*, *Aprovado com Ressalvas* ou *Necessita Correções Substanciais*.
3. **Lista de Não-Conformidades por Severidade:**
   - 🔴 **Críticas:** Citações sem referência, norma desatualizada de citação em caixa alta.
   - 🟡 **Moderadas:** Falhas de pontuação, ordem alfabética das referências, palavras-chave.
   - 🟢 **Leves:** Abreviatura incorreta de páginas (`pg.` em vez de `p.`), abreviatura de meses.
4. **Instruções Exatas de Como Corrigir:** Trecho identificado, número da linha e sugestão pronta de correção.

---

## Manuais e Guias de Consulta Rápida

- [NBR 14724: Estrutura e Formatação Física](./references/nbr14724_estrutura.md)
- [NBR 10520:2023: Citações Autor-Data em Caixa Baixa](./references/nbr10520_citacoes.md)
- [NBR 6023: Elaboração Canônica de Referências](./references/nbr6023_referencias.md)
- [Checklist de Banca Examinadora](./references/checklist_banca.md)
