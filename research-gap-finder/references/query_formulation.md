# Formulação Estruturada de Strings de Busca Acadêmica

Para extrair metadados precisos e evitar ruído ou viés de seleção, as consultas devem ser formuladas utilizando vocabulários controlados e operadores lógicos adequados.

---

## 1. O Modelo PICO / PECO para Definição da Busca

- **P (Population / População ou Sistema):** O organismo, cultura, solo, ecossistema ou grupo de pacientes (ex: `"Glycine max"`, `"tropical latosol"`, `"pediatric sepsis"`).
- **I / E (Intervention / Exposure - Intervenção ou Exposição):** O tratamento, insumo, composto ou tecnologia aplicada (ex: `"biochar"`, `"CRISPR-Cas9"`, `"artificial intelligence"`).
- **C (Comparison / Controle):** A condição padrão ou intervenção tradicional (opcional na busca ampla).
- **O (Outcome / Desfecho):** O efeito mensurado (ex: `"yield"`, `"carbon sequestration"`, `"mortality"`, `"microbial diversity"`).

---

## 2. Operadores Booleanos e Sintaxe Padronizada

1. **AND:** Interseção obrigatória entre os eixos conceituais (ex: `biochar AND soil`).
2. **OR:** Agrupamento de sinônimos e variações linguísticas (ex: `"biochar" OR "pyrogenic carbon" OR "biocarbon"`).
3. **NOT:** Exclusão de termos ruidosos (usar com cautela para não eliminar artigos relevantes).
4. **Aspas (`"..."`):** Busca por correspondência exata da locução (ex: `"heavy metals"`, `"precision agriculture"`).
5. **Truncamento (`*`):** Recupera variações mórficas da palavra (ex: `nanopart*` recupera *nanoparticle*, *nanoparticles*, *nanoparticulate*).

---

## 3. Integração Multilíngue (DeCS / MeSH)

Para cobrir integralmente bases como SciELO e PubMed:
- No SciELO, muitos artigos são indexados em Português ou Espanhol. É prudente incluir sinônimos nos dois idiomas.
  - Exemplo: `("sustentabilidade" OR "sustainability") AND ("soja" OR "soybean")`
- No PubMed e OpenAlex, o Inglês é predominante. Utilize termos MeSH (Medical Subject Headings) oficiais sempre que possível.
