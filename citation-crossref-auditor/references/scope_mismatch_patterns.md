# Padrões de Incompatibilidade de Escopo e Inserção Errada

A citação de referências fora do escopo (*Scope Mismatch*) ou atribuídas a artigos errados é um dos problemas mais graves apontados por pareceristas de revistas científicas e membros de bancas examinadoras.

---

## 1. Principais Causas de Inserção Errada de Referências

1. **Troca Acidental de Chaves no Gerenciador Bibliográfico:**
   - Em softwares como Zotero, Mendeley ou EndNote, o autor seleciona por engano um artigo com chave parecida (ex: selecionar `@Silva2020a` em vez de `@Silva2020b`).
2. **Alucinação por Modelos de Linguagem (LLMs):**
   - Ferramentas de IA generativa frequentemente criam referências híbridas: pegam o nome de um autor real, combinam com um título de outro artigo e inventam um DOI ou usam o DOI de um terceiro trabalho.
3. **Cópia Acidental de Outros Artigos (*Citation Stacking*):**
   - O autor copia um bloco de referências de outro trabalho sem verificar se cada obra citada realmente dá suporte às afirmações feitas no parágrafo.
4. **Citação de Citação (*Apud*) Oculto ou Mal Resolvido:**
   - O autor leu uma citação indireta em outro artigo e copiou a referência original sem conferir a fonte primária.

---

## 2. Como a Skill Detecta a Incompatibilidade

O algoritmo de auditoria opera em dois níveis:

### A. Análise Lexical de Domínio
- Extrai os termos e eixos conceituais do manuscrito (ex: `soja`, `ferrugem`, `fungicida`, `produtividade`).
- Extrai os termos do título da obra citada e do periódico indexado (ex: `vaping`, `dental`, `caries`, `pediatric`).
- Se a sobreposição informativa for **zero**, a skill emite um **Alerta Vermelho de Escopo**.

### B. Confronto de Domínio Temático
- Se o manuscrito pertence à grande área de Ciências Agrárias e o artigo citado pertence à Odontologia ou Física Quântica, o sistema acusa discrepância estrutural de área de conhecimento.
