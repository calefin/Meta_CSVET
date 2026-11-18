---
name: citation-crossref-auditor
description: >-
  Use this skill when the user wants to audit, verify, and validate scientific citations
  and references in a paper against official Crossref metadata, checking title accuracy,
  author names, publication year, valid DOIs, and verifying whether cited works are in-scope
  or represent accidental/wrong insertions.
---

# Citation & Crossref Reference Auditor: Integridade Bibliográfica e Escopo

Esta skill realiza a **auditoria forense de citações e referências** de artigos científicos, teses e dissertações, confrontando cada obra citada com a base oficial internacional da **Crossref** e analisando se os títulos citados são **coerentes com o escopo temático do artigo** para detectar possíveis inserções erradas, trocas acidentais de referências ou alucinações de ferramentas de IA.

---

## O que a Skill Audita e Valida

1. **Validação Direta no Crossref (por DOI ou Busca Bibliográfica):**
   - Valida se o DOI informado existe de fato ou se é um **DOI fantasma / inválido (Erro 404)**.
   - Confronta o **Título Declarado** com o título oficial indexado.
   - Confronta o **Ano Declarado** com a data oficial de publicação.
   - Confirma se o **Autor Citado** realmente é autor da obra no registro oficial.
   - Confirma o nome oficial do periódico/revista (*container-title*).

2. **Detecção de Inserção Errada e Incompatibilidade de Escopo (*Scope Anomaly*):**
   - Analisa os termos conceituais do manuscrito e compara com o título e periódico da obra citada.
   - Alerta quando uma referência citada pertence a uma **área do conhecimento completamente desconectada** do manuscrito (ex: citar um artigo odontológico ou criptográfico num artigo sobre ferrugem da soja).

3. **Cruzamento Citação $\leftrightarrow$ Referência:**
   - Detecta **Citações Órfãs**: menções autor-data no texto que não possuem entrada correspondente na seção de Referências.
   - Detecta **Referências Fantasmas**: obras listadas na bibliografia que nunca foram citadas no corpo do artigo.

---

## Como Executar a Auditoria

Execute o script de auditoria informando o caminho do manuscrito:

```bash
python3 scripts/audit_citations_crossref.py \
  -i "/caminho/do/artigo_cientifico.md" \
  -o "relatorio_auditoria_crossref.md" \
  -j "auditoria_crossref.json"
```

### Parâmetros Opcionais:
- `-t, --topic`: Fornece explicitamente o tema/eixo do manuscrito (ex: `"soja, agricultura de precisão, ferrugem"`) para calibrar o detector de escopo caso o texto inicial seja muito curto.
- `-j, --json`: Exporta os dados brutos estruturados de todas as discrepâncias encontradas.

---

## O que o Relatório Entrega

1. **Quadro de Integridade:** Percentual de referências confirmadas no Crossref, referências com discrepâncias, alertas de escopo e DOIs inválidos.
2. **Alertas Críticos de Escopo:** Lista dos artigos com títulos fora de contexto, explicando o motivo pelo qual a citação é suspeita de troca de chave ou alucinação.
3. **Quadro Comparativo de Discrepâncias:** Tabela campo a campo mostrando *[Declarado pelo Autor]* vs *[Registro Oficial Crossref]* com o link ativo do DOI.
4. **Citações Órfãs:** Chamadas no texto que precisam ser adicionadas na bibliografia.

---

## Manuais e Guias de Apoio

- [Guia da API Crossref e Rate Limits](./references/crossref_api_guide.md)
- [Padrões de Incompatibilidade de Escopo](./references/scope_mismatch_patterns.md)
- [Tipologia de Discrepâncias de Metadados](./references/discrepancy_types.md)
