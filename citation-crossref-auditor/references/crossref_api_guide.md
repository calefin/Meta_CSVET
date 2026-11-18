# Guia da API Crossref para Auditoria Bibliográfica

A **Crossref** é a principal agência internacional de registro de DOI (Digital Object Identifier) para a literatura científica global, indexando mais de 150 milhões de obras de milhares de editoras acadêmicas (Elsevier, Springer Nature, Wiley, SciELO, IEEE, MDPI, etc.).

---

## 1. Endpoints Utilizados na Auditoria

### A. Consulta Direta por DOI
- **Endpoint:** `https://api.crossref.org/works/{doi}`
- **Método:** `GET`
- **Cabeçalho:** `User-Agent: CitationCrossrefAuditor/1.0 (mailto:seu-email@dominio.com)`
- **Códigos de Resposta:**
  - `200 OK`: DOI válido e metadados retornados.
  - `404 Not Found`: DOI inexistente, digitado incorretamente ou gerado artificialmente (alucinação).
  - `429 Too Many Requests`: Cota de requisições excedida temporariamente.

### B. Busca Bibliográfica (Quando não há DOI)
- **Endpoint:** `https://api.crossref.org/works?query.bibliographic={autor+titulo}&rows=3`
- **Finalidade:** Localizar o registro oficial a partir do título e sobrenome do primeiro autor para recuperar o DOI oficial.

---

## 2. Metadados Oficiais Extraídos e Confrontados

| Campo Crossref | O que representa | Verificação Realizada |
| :--- | :--- | :--- |
| `message.title` | Título oficial do artigo/livro | Confrontado com o título declarado via similaridade de sequência (SequenceMatcher). |
| `message.author` | Lista completa de autores com prenome e sobrenome | Confere se o autor citado realmente é autor da obra. |
| `message.published.date-parts` | Ano e data oficial de publicação | Detecta erros frequentes de ano (ex: ano de submissão vs publicação definitiva). |
| `message.container-title` | Nome do periódico, revista ou livro de actas | Valida se a revista declarada coincide com o veículo indexado. |
| `message.DOI` | O identificador persistente oficial | Permite gerar link direto `https://doi.org/{doi}`. |
| `message.subject` | Categorias temáticas indexadas | Utilizado no cálculo de coerência de escopo. |
