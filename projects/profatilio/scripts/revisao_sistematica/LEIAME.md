# Kit de importação — Triagem Cega (profatilio.com.br)

Arquivos:

- `buscar.py` — busca no PubMed/Europe PMC, lê as exportações das outras bases, completa DOI pelo
  Crossref, remove duplicatas, valida e gera o JSON padronizado. Só precisa do Python 3.9+.
- `modelo_importacao.json` — formato oficial (`profatilio-sr/1`) com um registro de exemplo.
- `modelo_importacao.csv` — mesmo formato em planilha (para montar à mão no Excel, se precisar).

## Passo a passo (Fase 1 do POP)

1. Rode a busca no PubMed direto pelo script e exporte as outras bases:
   - **Scopus**: Export → RIS (ou CSV) com "Citation information" + "Abstract & keywords".
   - **Web of Science**: Export → "RIS" ou "Tab delimited file" → "Full Record".
   - **Embase / CAB**: Export → RIS.
2. Gere o arquivo (anote a sintaxe usada em cada base com `--consulta`, vai para o PRISMA-S):

```bash
python3 buscar.py --projeto 01_Meloxicam_Lagomorfos \
  --pubmed '("meloxicam" OR "metacam") AND ("rabbit" OR "Oryctolagus cuniculus")' \
  --arquivo scopus.ris:Scopus --consulta 'Scopus=TITLE-ABS-KEY(meloxicam AND rabbit*)' \
  --arquivo savedrecs.txt:"Web of Science" --consulta 'Web of Science=TS=(meloxicam AND rabbit*)' \
  --email seu@email.com --completar-doi --saida 01_meloxicam_triagem.json
```

3. No painel: **Revisões Sistemáticas → projeto → Importar registros** e envie o `.json`.

## Regras do formato

| Campo | Obrigatório | Padrão |
|---|---|---|
| `title` | sim | título original |
| `doi` | um de `doi`/`pmid`/`url` | DOI sem `https://doi.org/`, minúsculo (ex.: `10.1016/j.jepm.2021.01.001`) |
| `pmid` | um de `doi`/`pmid`/`url` | só dígitos |
| `url` | um de `doi`/`pmid`/`url` | `https://…` |
| `abstract` | recomendado | texto puro |
| `authors` | recomendado | `Sobrenome, Nome; Sobrenome, Nome` |
| `year` | recomendado | AAAA |
| `journal`, `volume`, `issue`, `pages`, `issn` | recomendado | como na base |
| `pmcid`, `language` (ISO 639), `publication_type`, `keywords` (separadas por `;`) | opcional | |
| `sources` | sim | lista das bases onde o registro apareceu |
| `record_id` | opcional | identificador estável (`DOI:…`, `PMID:…`, EID do Scopus, UT do WoS) |

O bloco `search` guarda o registro de cada busca no padrão **PRISMA-S** (base, plataforma, sintaxe,
data, nº de resultados, filtros) e os números da caixa "Identificação" do fluxograma **PRISMA 2020**
(`sources`, `duplicates_removed`).

**Por que o DOI não é 100% obrigatório:** parte da literatura veterinária antiga/regional não tem DOI;
descartar esses estudos enviesaria a revisão. O script tenta achar o DOI no Crossref (título ≥ 92% igual
e ano ±1) e só rejeita o que não tiver nenhum identificador — esses vão para `*_rejeitados.csv`.

O sistema também aceita direto `.ris`, `.nbib` e `.csv/.txt` do Scopus/WoS (sem o script), mas aí
não há registro PRISMA-S das buscas nem DOI completado.
