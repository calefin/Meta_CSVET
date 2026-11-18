# Regras de Transformação e Formatação de Textos para ABNT

Este documento detalha o conjunto de regras aplicado pelo corretor automático para transformar textos acadêmicos em conformidade com as normas ABNT vigentes.

---

## 1. Transformação de Citações (NBR 10520:2023)

### Regra de Capitalização de Autores
- **Padrão Antigo Detectado:** `\(([A-ZÀ-ÿ\s;]{2,}),\s*(\d{4}[a-z]?)\)`
- **Ação:**
  1. Quebra os autores separados por `;` ou `,`.
  2. Preserva siglas consagradas em maiúsculas (ex: `IBGE`, `OMS`, `UNESCO`, `EMBRAPA`, `MAPA`, `ANVISA`, `MEC`, `CAPES`, `CNPQ`).
  3. Para sobrenomes de autores pessoas físicas, converte para Title Case, mantendo partículas de ligação em caixa baixa (`da`, `de`, `do`, `das`, `dos`, `e`).
  4. Exemplo: `(SILVA; DOS SANTOS, 2021)` $\rightarrow$ `(Silva; dos Santos, 2021)`.

### Regra de Pontuação de Fechamento
- **Padrão Errado:** Uma sentença encerrada com ponto antes da chamada de citação:  
  `...redução de custos. (Silva, 2020)`
- **Ação:** Move o ponto final para o término da citação:  
  `...redução de custos (Silva, 2020).`

### Regra de Abreviatura de Páginas
- **Padrão Errado:** Variações como `pg.`, `pág.`, `pag.`, `P.` ou `Pág.`
- **Ação:** Substitui invariavelmente por `p.` seguido de espaço simples e o número da página:  
  `(Silva, 2020, pág. 15)` $\rightarrow$ `(Silva, 2020, p. 15)`.

---

## 2. Numeração Progressiva de Seções (NBR 6024)

- Os títulos de seções primárias devem ser grafados em caixa alta e negrito:  
  `1 INTRODUÇÃO`
- As seções secundárias devem ser grafadas com inicial maiúscula:  
  `1.1 Revisão Bibliográfica`
- As seções terciárias:  
  `1.1.1 Histórico Recente`
- **Regra de Eliminação de Ponto Final:** A ABNT veda o uso de ponto, hífen, travessão ou qualquer sinal após o último algarismo da seção:  
  `1.` $\rightarrow$ `1`  
  `1.1.` $\rightarrow$ `1.1`  
  `1.1.1.` $\rightarrow$ `1.1.1`

---

## 3. Formatação e Ordenação de Referências (NBR 6023)

- **Regra de Ordenação Alfabética:** O bloco de referências é decomposto em entradas individuais e ordenado alfabeticamente pela chave de autor/título, ignorando caracteres especiais.
- **Regra de Data de Acesso:** Datas de acesso em documentos da internet são convertidas para a sintaxe canônica:  
  `Acesso em: DD mmm. AAAA.`  
  Com os meses devidamente abreviados em 4 caracteres com ponto (`jan.`, `fev.`, `mar.`, `abr.`, `maio`, `jun.`, `jul.`, `ago.`, `set.`, `out.`, `nov.`, `dez.`).
