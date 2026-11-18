"""Seed data: Rosela-carmesim / Crimson Rosella (Platycercus elegans) genetic loci.

Fonte: nomenclatura e modos de herança consolidados da literatura padrão de
genética de cor em psitacídeos (ex.: Martin, "A Guide to Colour Mutations &
Genetics in Parrots"), restrita às mutações documentadas para a espécie.
"""

ROSELLA_CRIMSON_LOCI = [
    # Sex-linked recessive
    {"symbol": "ino", "name_pt": "Lutino (Sex-linked)", "name_en": "Sex-linked Lutino", "mode": "se"},
    {"symbol": "cin", "name_pt": "Canela (Cinnamon)", "name_en": "Cinnamon", "mode": "se"},
    # Autosomal recessive
    {"symbol": "b",   "name_pt": "Azul / Parblue (locus Blue)", "name_en": "Blue / Parblue (Blue locus)", "mode": "re",
     "is_multi_allele": True,
     "alleles": [
         {"symbol": "b_blue", "name_pt": "Azul", "name_en": "Blue", "rank": 1},
         {"symbol": "b_parblue", "name_pt": "Parblue (Aqua/Orange)", "name_en": "Parblue (Aqua/Orange)", "rank": 2},
     ]},
    {"symbol": "r",   "name_pt": "Malhado Recessivo (Pied)", "name_en": "Recessive Pied", "mode": "re"},
    {"symbol": "bec", "name_pt": "Olho-preto Claro (Black-eyed Clear / Yellow)", "name_en": "Black-eyed Clear (Yellow)", "mode": "re"},
]
