# Tipologia de Discrepâncias de Metadados em Artigos Científicos

Durante o processo de revisão por pares, erros em referências comprometem a credibilidade metodológica do trabalho. Esta tipologia classifica os desvios encontrados entre os manuscritos e a base Crossref.

---

## 1. Discrepância de Autoria (Autor Mismatch)
- **Autor Inexistente no Registro:** O sobrenome citado no texto não consta em nenhuma posição da lista de autores da obra original. Frequentemente ocorre quando se cita o editor ou organizador como se fosse o autor, ou quando há confusão entre prenome e sobrenome.
- **Autor Secundário como Primeiro Autor:** O autor declarou `(Souza, 2020)`, mas no registro oficial Souza é o quinto autor e o primeiro autor é `Silva`.

---

## 2. Discrepância Cronológica (Year Mismatch)
- **Ano de Ahead-of-Print vs Ano de Volume:** O autor cita o ano em que o artigo saiu online (ex: 2021), mas a publicação final no volume ocorreu em 2022.
- **Erro de Digitação:** Troca de dígitos (ex: `2018` declarado vs `2015` real).

---

## 3. Discrepância de Título e Periódico
- **Título Incompleto ou Truncado:** O autor omitiu o subtítulo ou misturou partes do resumo no título.
- **Periódico Incorreto:** O autor declarou o título da revista no lugar do título do artigo, ou citou a revista em que o autor publicou outro trabalho.

---

## 4. DOIs Inválidos ou Fantasmas (Broken DOIs)
- **Erro 404:** O link `https://doi.org/...` retorna "DOI Not Found". Indica erro tipográfico na string do DOI ou DOI fictício gerado por alucinação.
- **DOI Invertido:** O DOI aponta para um artigo completamente diferente do título e autores descritos na referência.
