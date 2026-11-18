#!/usr/bin/env python3
"""
PRISMA-P Protocol & Systematic Review Gap Dossier Generator
Transforms audit diagnostics into a formal evidence synthesis protocol scaffold (PRISMA 2020 / PRISMA-P),
complete with PICO questions, eligibility criteria, subgroup hypotheses, and target journals.
"""

import sys
import os
import re
import json
import argparse
from datetime import datetime


def generate_bibtex(articles, output_path):
    entries = []
    for idx, art in enumerate(articles, 1):
        doi = art.get("doi")
        title = art.get("title", "Untitled").replace("{", "").replace("}", "")
        year = art.get("year") or 2024
        journal = art.get("journal", "Primary Study")
        key = f"PrimaryStudy_{year}_{idx}"

        entry = f"""@article{{{key},
  title = {{{{{title}}}}},
  journal = {{{journal}}},
  year = {{{year}}}{f",\n  doi = {{{doi}}}" if doi else ""}
}}"""
        entries.append(entry)

    with open(output_path, "w", encoding="utf-8") as f:
        f.write("\n\n".join(entries))


def build_markdown_report(audit_data, output_md):
    query = audit_data.get("query", "Área Temática")
    vm = audit_data.get("verdict_metrics", {})
    existing_revs = audit_data.get("existing_reviews", [])
    primary_studies = audit_data.get("primary_studies_sample", [])
    limitations = audit_data.get("extracted_review_limitations", [])

    lines = []
    lines.append(f"# Dossiê de Auditoria de Lacuna para Revisão Sistemática e Meta-Análise")
    lines.append(f"**Área Temática:** `{query}`  ")
    lines.append(f"**Data da Auditoria:** {datetime.now().strftime('%d/%m/%Y %H:%M')}  ")
    lines.append(f"**Índice de Oportunidade de Revisão (IRO):** `{vm.get('score_iro', 0)}/100`  ")
    lines.append(f"**Veredito:** `{vm.get('verdict', 'Em Análise')}`  \n")

    lines.append("---")
    lines.append("## 1. Diagnóstico do Cenário de Evidências\n")
    lines.append(f"- **Total de Revisões Sistemáticas/Meta-Análises Prévias:** {vm.get('total_reviews', 0)}")
    lines.append(f"- **Ano da Última Síntese Publicada:** {vm.get('latest_review_year') or 'Nenhuma existente'}")
    lines.append(f"- **Total de Estudos Primários Mapeados:** {vm.get('total_primary_studies', 0)}")
    lines.append(f"- **Estudos Primários Publicados Após a Última Revisão:** {vm.get('primary_studies_after_latest_review', 0)}\n")

    lines.append("### Justificativa Epistemológica da Lacuna:")
    for r in vm.get("rationale", []):
        lines.append(f"> 📌 **{r}**")
    lines.append("")

    lines.append("---")
    lines.append("## 2. Limitações Declaradas nas Revisões Anteriores (Gaps Concretos)\n")
    if limitations:
        for idx, lim in enumerate(limitations[:6], 1):
            lines.append(f"**{idx}. Limitação Metodológica / Demanda de Pesquisa:**")
            lines.append(f"> *\"{lim['limitation_statement']}\"*")
            lines.append(f"- **Publicação:** {lim['review_title']} ({lim['journal']}, {lim['year']})")
            if lim.get('doi'):
                lines.append(f"- **DOI:** [{lim['doi']}](https://doi.org/{lim['doi']})")
            lines.append("")
    else:
        lines.append("*Não foram detectadas revisões prévias com limitações textuais ou o tema é totalmente virgem de sínteses quantitativas.*  \n")

    lines.append("---")
    lines.append("## 3. Protocolo Preliminar Estruturado (Diretrizes PRISMA-P & PROSPERO)\n")
    
    title_en = f"Effectiveness and Outcomes of {query.title()}: A Systematic Review and Meta-Analysis"
    title_pt = f"Eficácia e Desfechos de {query.title()}: Revisão Sistemática e Meta-Análise"
    
    lines.append(f"### Título Proposto:")
    lines.append(f"- **EN:** `{title_en}`")
    lines.append(f"- **PT:** `{title_pt}`\n")

    lines.append("### Estrutura da Pergunta de Pesquisa (PICO / PECO):")
    lines.append(f"- **População / Sistema (P):** Indivíduos, culturas agrícolas, populações ou modelos sob condição de '{query}'.")
    lines.append(f"- **Intervenção / Exposição (I/E):** Aplicação de intervenção específica ou fator sob investigação em '{query}'.")
    lines.append("- **Comparador / Controle (C):** Grupo controle placebo, manejo convencional, condição padrão ou não-exposta.")
    lines.append("- **Desfechos Primários (Outcomes - O):** Eficácia mensurável, redução de sintomas/patógenos, ganho de rendimento ou parâmetros biomarcadores quantitativos.")
    lines.append("- **Desenho dos Estudos (Study Design - S):** Ensaios controlados randomizados (RCTs), estudos experimentais de campo/laboratório ou coortes comparativas.\n")

    lines.append("### Critérios de Elegibilidade Preliminares:")
    lines.append("| Critério | Inclusão | Exclusão |")
    lines.append("| :--- | :--- | :--- |")
    lines.append("| **Desenho** | Ensaios com grupo controle claro (RCTs, quase-experimentos, blocos ao acaso) | Editoriais, revisões narrativas, estudos sem grupo controle |")
    lines.append("| **Dados Quantitativos** | Médias, desvio padrão/erro padrão e tamanho amostral (N) reportados | Estudos apenas qualitativos sem dados para cálculo de Effect Size |")
    lines.append("| **Língua / Período** | Sem restrição de idioma (ou EN, PT, ES), desde a origem das bases | Estudos duplicados ou relatos anedóticos |")
    lines.append("")

    lines.append("### Plano de Análise Estatística e Meta-Regressão:")
    lines.append("- **Métrica de Efeito Primária:** Standardized Mean Difference (Hedges' g) para variáveis contínuas, ou Risk Ratio (RR) para desfechos binários.")
    lines.append("- **Modelo Estatístico:** Modelo de Efeitos Aleatórios (Random-effects model - DerSimonian-Laird ou REML) devido à heterogeneidade esperada.")
    lines.append("- **Avaliação de Heterogeneidade:** Estatística $I^2$, teste Q de Cochran e Tau-quadrado ($\tau^2$).")
    lines.append("- **Análise de Subgrupos / Moderadores:** Estratificação por dosagem, tipo de solo/região, duração do experimento e qualidade metodológica.")
    lines.append("- **Viés de Publicação:** Gráfico de Funil (Funnel Plot), teste de assimetria de Egger e método Trim-and-Fill.")
    lines.append("- **Certeza da Evidência:** Aplicação do sistema GRADE (Grading of Recommendations Assessment, Development and Evaluation).\n")

    lines.append("---")
    lines.append("## 4. Periódicos-Alvo Recomendados para Submissão\n")
    lines.append("| Periódico Indicado | Escopo e Seção de Revisões | Qualis-CAPES / Indexador |")
    lines.append("| :--- | :--- | :---: |")
    lines.append("| **Cochrane Database of Systematic Reviews** | Se a temática for da área da saúde e medicina baseada em evidências | Qualis A1 / Scopus Q1 |")
    lines.append("| **Science of The Total Environment / Environmental Pollution** | Se a temática envolver solos, poluição, biochar ou sustentabilidade | Qualis A1 / Scopus Q1 |")
    lines.append("| **Plos One / Scientific Reports** | Aceitação contínua de revisões sistemáticas PRISMA metodologicamente sólidas | Qualis A1-A2 / Scopus Q1 |")
    lines.append("| **Periódicos SciELO Especializados da Área** | Foco em impactos regionais e literatura em livre acesso | Qualis A2-A4 / SciELO |")
    lines.append("")

    lines.append("---")
    lines.append("## 5. Roteiro Imediato de Execução\n")
    lines.append("1. **Registro Prévio Obrigatório:** Cadastre este protocolo no **[PROSPERO](https://www.crd.york.ac.uk/prospero/)** (área de saúde) ou no **[Open Science Framework (OSF Registries)](https://osf.io/registries)** (agronomia, ambiente, ciências humanas) para garantir prioridade autoral.")
    lines.append("2. **Exportação dos Estudos Primários:** Os estudos identificados nesta busca prévia já foram exportados para o arquivo `estudos_primarios_candidatos.bib` gerado por esta ferramenta.")
    lines.append("3. **Ferramenta de Triagem Recomendada:** Importe o `.bib` no [Rayyan](https://www.rayyan.ai/) ou [Covidence](https://www.covidence.org/) para conduzir a triagem de títulos e resumos por pares cegos.")

    with open(output_md, "w", encoding="utf-8") as f:
        f.write("\n".join(lines))


def main():
    parser = argparse.ArgumentParser(description="Gera protocolo PRISMA-P e dossiê a partir do diagnóstico de lacuna de revisão sistemática.")
    parser.add_argument("-i", "--input", required=True, help="JSON de auditoria gerado pelo audit_systematic_reviews.py")
    parser.add_argument("-m", "--markdown", default="dossie_revisao_sistematica.md", help="Arquivo Markdown de saída")
    parser.add_argument("-b", "--bibtex", default="estudos_primarios_candidatos.bib", help="Arquivo BibTeX com estudos primários identificados")

    args = parser.parse_args()

    with open(args.input, "r", encoding="utf-8") as f:
        audit_data = json.load(f)

    build_markdown_report(audit_data, args.markdown)
    generate_bibtex(audit_data.get("primary_studies_sample", []), args.bibtex)

    print(f"[✓] Dossiê PRISMA gerado com sucesso em: {args.markdown}")
    print(f"[✓] Referências dos estudos primários exportadas para: {args.bibtex}")


if __name__ == "__main__":
    main()
