# Guia de Bases de Dados Indexadas para Prospecção Científica

Este guia detalha o funcionamento, cobertura e mecanismos de acesso às bases de dados indexadas suportadas pelo pipeline `research-gap-finder`.

---

## 1. SciELO (Scientific Electronic Library Online)
- **Cobertura:** Periódicos científicos de acesso aberto de 16 países (América Latina, Caribe, Portugal, Espanha e África do Sul). Foco expressivo em agronomia, ciências sociais, saúde pública e medicina tropical.
- **Estrutura de DOI:** Os artigos SciELO utilizam o prefixo internacional `10.1590/`.
- **Mecanismo de Consulta:**
  - O script acessa a API do Crossref filtrando pelo prefixo `10.1590`, garantindo busca em tempo real sem bloqueios de WAF/Cloudflare.
  - Também é coberto integralmente pela base OpenAlex via filtro de proveniência institucional.
- **Tipos de Metadados:** Título (frequentemente multilíngue: PT/EN/ES), Autores, Periódico, Resumo, Palavras-chave, Referências e Citações.

---

## 2. PubMed / MEDLINE (NCBI / NLM)
- **Cobertura:** Mais de 36 milhões de registros biomédicos, ciências da saúde, toxicologia, farmacologia e biologia aplicada.
- **Mecanismo de Consulta:** NCBI Entrez E-utilities (`esearch.fcgi` e `efetch.fcgi`).
- **Autenticação:**
  - Acesso público padrão: até 3 requisições por segundo (sem necessidade de chave).
  - Com chave `NCBI_API_KEY`: até 10 requisições por segundo.
- **Vocabulário Controlado:** Indexação rigorosa via MeSH (Medical Subject Headings), permitindo isolar termos clínicos e populacionais exatos.

---

## 3. OpenAlex
- **Cobertura:** Mais de 250 milhões de obras científicas de todas as disciplinas do mundo. É o sucessor aberto do Microsoft Academic Graph (MAG), integrando dados do Crossref, PubMed, Scopus, DOAJ e repositórios institucionais.
- **Mecanismo de Consulta:** REST API (`https://api.openalex.org/works`).
- **Autenticação:** Aberta e gratuita. Recomenda-se informar um e-mail no cabeçalho ou parâmetro `mailto:` para entrar no "Polite Pool" com limite de 10 req/s.
- **Diferencial Analítico:**
  - Inverted Abstract Index para reconstrução do resumo completo.
  - Grafo de conceitos (`concepts`) com pontuação de relevância.
  - Métricas de citação (`cited_by_count`) e contagem de citações anuais para avaliar velocidade de impacto.

---

## 4. CABI (CAB Direct / CAB Abstracts)
- **Cobertura:** Literatura aplicada em ciências agrárias, biotecnologia agrícola, ciências florestais, entomologia, sanidade vegetal e medicina veterinária.
- **Mecanismo de Consulta:** 
  - Consultas direcionadas via Crossref com identificador bibliográfico CABI / CAB Direct.
  - Periódicos sob tutela e indexação do CABI são recuperados através de seus ISSNs e metadados de publicação.

---

## 5. Scopus (Elsevier)
- **Cobertura:** A maior base de resumos e citações de literatura revisada por pares do mundo (mais de 80 milhões de registros).
- **Mecanismo de Consulta:** API Oficial da Elsevier (`https://api.elsevier.com/content/search/scopus`).
- **Autenticação:**
  - Requer chave de API (`SCOPUS_API_KEY` ou parâmetro `--scopus-key`).
  - Para usuários com assinatura institucional (ex: via Portal de Periódicos CAPES no Brasil), o acesso via IP institucional ou Institutional Token (`X-ELS-Insttoken`) permite baixar metadados estendidos.
  - *Nota:* Quando a chave não está disponível, a base OpenAlex supre a maior parte da literatura indexada na Scopus com os mesmos registros e métricas de citação abertas.

---

## 6. arXiv (Cornell University)
- **Cobertura:** Servidor de preprints líder mundial em física, matemática, ciência da computação, biologia quantitativa e machine learning.
- **Mecanismo de Consulta:** API pública XML Atom (`http://export.arxiv.org/api/query`).
- **Utilidade para Descoberta de Lacunas:** Crucial para identificar tendências emergentes que foram submetidas para publicação recente mas ainda não passaram pelos 6 a 12 meses do fluxo de peer-review convencional.
