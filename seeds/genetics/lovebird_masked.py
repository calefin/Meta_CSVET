"""Seed data: Agapornis personatus (grupo White Eye-ring: Masked, Nyasa,
Fischer's, Black-cheeked Lovebird) genetic loci.

Fonte: nomenclatura e modos de herança consolidados da literatura padrão de
genética de cor em psitacídeos (ex.: Martin, "A Guide to Colour Mutations &
Genetics in Parrots"), restrita às mutações classificadas como "common" ou
"established" para o grupo.
"""

LOVEBIRD_MASKED_LOCI = [
    # Autosomal recessive
    {"symbol": "a",   "name_pt": "Lutino NSL / Lime (série ino não ligada ao sexo)", "name_en": "NSL Lutino / Lime (autosomal ino series)", "mode": "re",
     "is_multi_allele": True,
     "alleles": [
         {"symbol": "a_ino", "name_pt": "Lutino (NSL)", "name_en": "NSL Lutino", "rank": 1},
         {"symbol": "a_lime", "name_pt": "Lime (Pastel/Dilute)", "name_en": "Lime (Pastel/Dilute)", "rank": 2},
     ]},
    {"symbol": "b",   "name_pt": "Azul (locus Blue)", "name_en": "Blue", "mode": "re"},
    {"symbol": "fal", "name_pt": "Fallow", "name_en": "Fallow", "mode": "re"},
    {"symbol": "r",   "name_pt": "Malhado Recessivo (Pied)", "name_en": "Recessive Pied", "mode": "re"},
    # Autosomal incomplete dominant / co-dominant
    {"symbol": "D",   "name_pt": "Fator Escuro (Dark Green / Cobalt)", "name_en": "Dark Factor (Dark Green / Cobalt)", "mode": "in"},
    {"symbol": "V",   "name_pt": "Violeta (Violet)", "name_en": "Violet", "mode": "in"},
    # Autosomal dominant
    {"symbol": "khk", "name_pt": "Khaki (Misty)", "name_en": "Khaki (Misty)", "mode": "do"},
    {"symbol": "P",   "name_pt": "Malhado Dominante (Greenwing)", "name_en": "Dominant Pied (Greenwing)", "mode": "do"},
]
