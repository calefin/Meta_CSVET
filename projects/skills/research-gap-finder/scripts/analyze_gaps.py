#!/usr/bin/env python3
"""
Scientific Research Gap & Trend Analyzer
Analyzes article metadata, extracts linguistic gap markers from abstracts,
builds concept co-occurrence matrices, computes temporal trends, and proposes
unexplored scientific paper themes.
"""

import sys
import os
import re
import json
import argparse
from collections import Counter, defaultdict
from datetime import datetime


# Patterns for extracting explicit research gaps from abstracts
GAP_PATTERNS = [
    # English
    r"(?:further|future)\s+(?:research|studies|investigations|work)\s+(?:is|are|should|must)\s+[^.?!]+[.?!]",
    r"(?:remains|is\s+still)\s+(?:unclear|unknown|poorly\s+understood|debated|controversial|elusive)[^.?!]+[.?!]",
    r"(?:little|scant|limited)\s+(?:is\s+known|evidence|data|information|research)\s+[^.?!]+[.?!]",
    r"(?:lack|scarcity|absence|dearth)\s+of\s+(?:studies|evidence|data|literature|knowledge|clinical\s+trials)[^.?!]+[.?!]",
    r"(?:unexplored|understudied|neglected|overlooked|open\s+question)[^.?!]+[.?!]",
    r"(?:to\s+our\s+knowledge,\s+no\s+(?:study|research|prior\s+work)|no\s+previous\s+study)[^.?!]+[.?!]",
    r"(?:warrants|demands|requires)\s+(?:further|more|deeper)\s+(?:investigation|examination|analysis)[^.?!]+[.?!]",
    r"(?:optimal|precise|mechanistic)\s+(?:conditions|mechanisms|pathways)\s+(?:remain|have\s+not\s+been)[^.?!]+[.?!]",
    # Portuguese
    r"(?:estudos\s+futuros|pesquisas\s+futuras|trabalhos\s+futuros)\s+(?:devem|precisam|são\s+necessários)[^.?!]+[.?!]",
    r"(?:ainda\s+(?:não\s+está\s+claro|permanece\s+pouco\s+compreendido|desconhecido|controverso))[^.?!]+[.?!]",
    r"(?:pouco\s+se\s+sabe|escassez\s+de\s+(?:dados|estudos|evidências|literatura))[^.?!]+[.?!]",
    r"(?:lacuna|lacunas)\s+(?:no\s+conhecimento|na\s+literatura|de\s+pesquisa)[^.?!]+[.?!]",
    r"(?:até\s+onde\s+sabemos,\s+nenhum\s+estudo|pouco\s+explorado|subexplorado)[^.?!]+[.?!]",
    r"(?:necessidade\s+de\s+(?:novos\s+estudos|investigações\s+adicionais))[^.?!]+[.?!]",
    # Spanish
    r"(?:se\s+requieren|se\s+necesitan)\s+(?:futuros\s+estudios|más\s+investigaciones)[^.?!]+[.?!]",
    r"(?:aún\s+se\s+desconoce|poco\s+explorado|vacío\s+de\s+investigación)[^.?!]+[.?!]",
    r"(?:escasez\s+de\s+(?:estudios|evidencia|datos))[^.?!]+[.?!]"
]

STOPWORDS = {
    "the", "of", "and", "in", "to", "a", "is", "for", "with", "that", "on", "as",
    "by", "this", "an", "are", "from", "at", "be", "or", "was", "were", "which",
    "these", "can", "has", "have", "had", "been", "using", "used", "study", "results",
    "analysis", "based", "effect", "effects", "associated", "higher", "lower", "also",
    "such", "between", "into", "through", "after", "before", "during", "o", "a", "os",
    "as", "um", "uma", "de", "do", "da", "dos", "das", "em", "no", "na", "nos", "nas",
    "por", "para", "com", "não", "que", "se", "como", "ao", "aos", "à", "às", "estudo",
    "artigo", "pesquisa", "resultados", "foram", "foi", "são", "sobre", "entre"
}


def extract_keywords_from_text(text, top_n=30):
    """Extract frequent meaningful bigrams and unigrams from titles and abstracts."""
    if not text:
        return []
    words = re.findall(r"\b[a-zA-ZÀ-ÿ]{3,}\b", text.lower())
    filtered = [w for w in words if w not in STOPWORDS]
    
    # Bigrams
    bigrams = []
    for i in range(len(filtered) - 1):
        bigrams.append(f"{filtered[i]} {filtered[i+1]}")
    
    counts = Counter(bigrams)
    return [bg for bg, _ in counts.most_common(top_n)]


def find_explicit_gaps(articles):
    """Scan abstracts for explicit research gap statements and author recommendations."""
    explicit_gaps = []
    compiled_patterns = [re.compile(p, re.IGNORECASE) for p in GAP_PATTERNS]

    for art in articles:
        abstract = art.get("abstract", "")
        if not abstract:
            continue

        for pat in compiled_patterns:
            matches = pat.findall(abstract)
            for match in matches:
                clean_sentence = re.sub(r"\s+", " ", match).strip()
                if 25 < len(clean_sentence) < 400:
                    explicit_gaps.append({
                        "gap_sentence": clean_sentence,
                        "paper_title": art.get("title"),
                        "year": art.get("year"),
                        "journal": art.get("journal"),
                        "doi": art.get("doi"),
                        "source": art.get("source_database")
                    })
    
    # Deduplicate gap statements
    unique_gaps = []
    seen = set()
    for g in explicit_gaps:
        norm = g["gap_sentence"][:60].lower()
        if norm not in seen:
            seen.add(norm)
            unique_gaps.append(g)

    return unique_gaps


def analyze_temporal_trends(articles):
    """Analyzes volume and citation trajectory over time."""
    years = [art.get("year") for art in articles if art.get("year") and 1990 <= art.get("year") <= 2030]
    year_counts = Counter(years)
    
    current_year = datetime.now().year
    recent_years = [current_year - 2, current_year - 1, current_year, current_year + 1]
    
    recent_count = sum(year_counts[y] for y in recent_years)
    total_count = len(years) or 1
    recent_ratio = round((recent_count / total_count) * 100, 1)

    return {
        "yearly_distribution": dict(sorted(year_counts.items())),
        "total_analyzed": total_count,
        "recent_articles_count": recent_count,
        "recent_ratio_pct": recent_ratio,
        "trend_velocity": "Acelerada / Emergente" if recent_ratio >= 50 else ("Madura / Estável" if recent_ratio >= 25 else "Histórica / Pouco Ativa")
    }


def analyze_concept_cooccurrence(articles, top_k=25):
    """Identifies highly studied combinations vs missing concept intersections (White Spaces)."""
    # Collect all concept tokens per paper
    paper_concepts = []
    concept_freq = Counter()

    for art in articles:
        concepts = set()
        for kw in art.get("keywords", []):
            if kw and len(kw) > 2:
                concepts.add(kw.strip().lower())
        for msh in art.get("mesh_terms", []):
            if msh:
                concepts.add(msh.strip().lower())

        # If few keywords, extract key bigrams from title
        if len(concepts) < 3:
            extracted = extract_keywords_from_text(art.get("title", ""), top_n=5)
            concepts.update(extracted)

        paper_concepts.append(list(concepts))
        for c in concepts:
            concept_freq[c] += 1

    top_concepts = [c for c, _ in concept_freq.most_common(top_k)]
    
    # Co-occurrence count
    co_occurrences = defaultdict(int)
    for concepts in paper_concepts:
        sub = [c for c in concepts if c in top_concepts]
        for i in range(len(sub)):
            for j in range(i + 1, len(sub)):
                pair = tuple(sorted([sub[i], sub[j]]))
                co_occurrences[pair] += 1

    # Find white spaces: top concepts that appear often individually but rarely or never together
    white_spaces = []
    for i in range(len(top_concepts)):
        for j in range(i + 1, len(top_concepts)):
            c1 = top_concepts[i]
            c2 = top_concepts[j]
            pair = tuple(sorted([c1, c2]))
            pair_count = co_occurrences[pair]
            
            # If both are prominent (>10% of articles) but paired in 0 or 1 paper
            f1 = concept_freq[c1]
            f2 = concept_freq[c2]
            if f1 >= 2 and f2 >= 2 and pair_count <= 1:
                white_spaces.append({
                    "concept_a": c1,
                    "frequency_a": f1,
                    "concept_b": c2,
                    "frequency_b": f2,
                    "co_occurrence": pair_count,
                    "opportunity_score": round((f1 + f2) / (pair_count + 1), 2)
                })

    white_spaces.sort(key=lambda x: x["opportunity_score"], reverse=True)

    return {
        "top_concepts": top_concepts[:15],
        "top_pairs": [
            {"pair": f"{k[0]} + {k[1]}", "count": v}
            for k, v in sorted(co_occurrences.items(), key=lambda x: x[1], reverse=True)[:10]
        ],
        "white_spaces": white_spaces[:10]
    }


def synthesize_research_proposals(query, explicit_gaps, cooccurrences, temporal_info):
    """
    Synthesizes academic paper proposals designed for publication in indexed journals (SciELO, Scopus, PubMed).
    """
    proposals = []
    
    # 1. Systematic Review & Meta-Analysis Proposal (Addresses controversy or synthesizing evidence)
    proposals.append({
        "type": "Revisão Sistemática e Meta-Análise (PRISMA)",
        "title_suggestion": f"Efficacy and Environmental/Biological Outcomes of {query.title()}: A Global Systematic Review and Meta-Analysis",
        "title_pt": f"Eficácia e Desfechos Ambientais/Biológicos de {query.title()}: Revisão Sistemática Global e Meta-Análise",
        "gap_justification": f"Existe dispersão significativa de dados na literatura recente ({temporal_info['recent_articles_count']} publicações recentes), com variações metodológicas sem síntese quantitativa conclusiva.",
        "research_question_pico": f"Em sistemas sob intervenção com '{query}' (P), qual o impacto comparado aos controles convencionais (C) nos desfechos primários de eficiência e sustentabilidade (O)?",
        "target_journals": "SciELO Agronomia/Saúde, Science of The Total Environment, Environmental Pollution, Plos One (Qualis A1/A2, Scopus Q1/Q2)",
        "novelty_level": "Alto (Síntese definitiva com análise de viés de publicação)"
    })

    # 2. Gap-driven Original Article proposals from explicit gaps found in literature
    if explicit_gaps:
        top_gap = explicit_gaps[0]
        proposals.append({
            "type": "Artigo Original Experimental / Observacional",
            "title_suggestion": f"Unraveling Key Mechanisms in {query.title()}: Bridging the Evidence Gap in Real-World Conditions",
            "title_pt": f"Desvendando Mecanismos-Chave em {query.title()}: Preenchendo a Lacuna de Evidências em Condições Reais",
            "gap_justification": f"Lacuna explícita apontada por {top_gap['journal']} ({top_gap['year']}): \"{top_gap['gap_sentence']}\"",
            "research_question_pico": "Investigar a relação causa-efeito e as vias moleculares/físico-químicas sob condições controladas e de campo.",
            "target_journals": "Revistas indexadas no Scopus / PubMed / SciELO específicas da subárea temática",
            "novelty_level": "Muito Alto (Responde diretamente a uma demanda declarada pela comunidade científica)"
        })

    # 3. Cross-concept / White Space Proposal (Intersection of unexplored concepts)
    if cooccurrences.get("white_spaces"):
        ws = cooccurrences["white_spaces"][0]
        c_a = ws["concept_a"].title()
        c_b = ws["concept_b"].title()
        proposals.append({
            "type": "Artigo Original Interdisciplinar / Comunicação Rápida",
            "title_suggestion": f"Synergistic Integration of {c_a} and {c_b} in the Context of {query.title()}: An Unexplored Frontier",
            "title_pt": f"Integração Sinérgica de {c_a} e {c_b} no Contexto de {query.title()}: Uma Fronteira Inexplorada",
            "gap_justification": f"Ambos os conceitos ({c_a} e {c_b}) são amplamente estudados isoladamente ({ws['frequency_a']} e {ws['frequency_b']} ocorrências), mas praticamente inexistentes de forma combinada (apenas {ws['co_occurrence']} registros encontrados).",
            "research_question_pico": f"Como a interação entre {c_a} e {c_b} modula a performance e os mecanismos regulatórios de {query}?",
            "target_journals": "Periódicos interdisciplinares, Nature Communications, Frontiers, Cadernos SciELO",
            "novelty_level": "Excepcional (Espaço em branco temático identificado por mineração bibliométrica)"
        })

    return proposals


def analyze_corpus(articles_json_path, query=""):
    """Main analytical pipeline."""
    with open(articles_json_path, "r", encoding="utf-8") as f:
        articles = json.load(f)

    if not articles:
        return {"error": "Nenhum artigo encontrado no arquivo fornecido."}

    # Database distribution
    db_distribution = Counter(art.get("source_database", "Outros") for art in articles)

    # Explicit gaps from abstracts
    explicit_gaps = find_explicit_gaps(articles)

    # Temporal analysis
    temporal_info = analyze_temporal_trends(articles)

    # Concept co-occurrence & white spaces
    cooccurrences = analyze_concept_cooccurrence(articles)

    # Synthesize paper proposals
    proposals = synthesize_research_proposals(query or "Tema de Pesquisa", explicit_gaps, cooccurrences, temporal_info)

    analysis_result = {
        "query": query,
        "total_articles": len(articles),
        "database_breakdown": dict(db_distribution),
        "temporal_analysis": temporal_info,
        "explicit_gaps_found": explicit_gaps,
        "concept_analysis": cooccurrences,
        "research_proposals": proposals
    }

    return analysis_result


def main():
    parser = argparse.ArgumentParser(description="Analisa metadados de artigos para identificar lacunas científicas e propor temas não publicados.")
    parser.add_argument("-i", "--input", required=True, help="Arquivo JSON gerado pelo search_academic.py")
    parser.add_argument("-q", "--query", default="", help="Termo de pesquisa original")
    parser.add_argument("-o", "--output", default="gap_analysis.json", help="Arquivo JSON de saída com a análise")

    args = parser.parse_args()

    results = analyze_corpus(args.input, query=args.query)

    with open(args.output, "w", encoding="utf-8") as f:
        json.dump(results, f, ensure_ascii=False, indent=2)

    print(f"[✓] Análise concluída com sucesso:")
    print(f"    - Artigos analisados: {results.get('total_articles')}")
    print(f"    - Lacunas explícitas identificadas nos resumos: {len(results.get('explicit_gaps_found', []))}")
    print(f"    - Espaços em branco (conceitos não combinados): {len(results.get('concept_analysis', {}).get('white_spaces', []))}")
    print(f"    - Propostas de artigos geradas: {len(results.get('research_proposals', []))}")
    print(f"    - Salvo em: {args.output}")


if __name__ == "__main__":
    main()
