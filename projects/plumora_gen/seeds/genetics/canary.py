"""Seed data: Canário-doméstico (Serinus canaria) genetic loci.

Fonte: nomenclatura e modos de herança clássicos da genética de cor em canários
(fatores de melanina, diluição e lipocromo), consolidados a partir da literatura
padrão da área (ex.: Perez-Beato, "Fundamentals of Color Genetics in Canaries").
"""

CANARY_LOCI = [
    # Sex-linked recessive (melanin factors)
    {"symbol": "blk", "name_pt": "Preto / Canela (Black / Brown-Cinnamon)", "name_en": "Black / Brown (Cinnamon)", "mode": "se"},
    {"symbol": "dil", "name_pt": "Diluição Ágata/Isabel (Agate/Isabel)",    "name_en": "Agate/Isabel Dilution",     "mode": "se"},
    {"symbol": "pst", "name_pt": "Pastel (super-diluição da feomelanina)", "name_en": "Pastel (pheomelanin super-dilution)", "mode": "se"},
    {"symbol": "ivy", "name_pt": "Marfim (Ivory) — dilui lipocromo",       "name_en": "Ivory (lipochrome dilution)", "mode": "se"},
    # Autosomal recessive (melanin factors), Opala/Onix multi-alélico
    {"symbol": "opl", "name_pt": "Opala / Onix (Opal/Onyx)", "name_en": "Opal / Onyx", "mode": "re",
     "is_multi_allele": True,
     "alleles": [
         {"symbol": "opl_opal", "name_pt": "Opala",  "name_en": "Opal",  "rank": 1},
         {"symbol": "opl_onyx", "name_pt": "Onix",   "name_en": "Onyx",  "rank": 2},
     ]},
    {"symbol": "eum", "name_pt": "Eumo",   "name_en": "Eumo",   "mode": "re"},
    {"symbol": "tpz", "name_pt": "Topázio (Topaz)", "name_en": "Topaz", "mode": "re"},
    {"symbol": "kbt", "name_pt": "Kobalt", "name_en": "Kobalt", "mode": "re"},
    # Autosomal, non-melanin
    {"symbol": "lmn", "name_pt": "Limão / Fator Ótico Azul (Lemon)", "name_en": "Lemon / Blue Optic Factor", "mode": "in"},
    {"symbol": "int", "name_pt": "Intensivo / Frost (Non-intensivo)", "name_en": "Intensive / Frost (Non-intensive)", "mode": "in"},
    {"symbol": "mos", "name_pt": "Mosaico (epistático sobre Intensivo)", "name_en": "Mosaic (epistatic under Intensive)", "mode": "do"},
    {"symbol": "wht", "name_pt": "Branco Dominante (letal em homozigose)", "name_en": "Dominant White (lethal homozygous)", "mode": "do"},
    {"symbol": "red", "name_pt": "Fator Vermelho (expressividade variável)", "name_en": "Red Factor (variable expressivity)", "mode": "do"},
]
