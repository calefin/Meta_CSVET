"""Seed data: Rosela-oriental / Eastern Rosella (Platycercus eximius) genetic loci.

Fonte: nomenclatura e modos de herança consolidados da literatura padrão de
genética de cor em psitacídeos (ex.: Martin, "A Guide to Colour Mutations &
Genetics in Parrots"), restrita às mutações documentadas para a espécie.
"""

ROSELLA_EASTERN_LOCI = [
    # Sex-linked recessive
    {"symbol": "ino", "name_pt": "Lutino (Sex-linked)", "name_en": "Sex-linked Lutino", "mode": "se"},
    {"symbol": "op",  "name_pt": "Opalino (Red)", "name_en": "Opaline (Red)", "mode": "se"},
    # Autosomal recessive
    {"symbol": "b",   "name_pt": "Azul / Parblue (locus Blue)", "name_en": "Blue / Parblue (Blue locus)", "mode": "re",
     "is_multi_allele": True,
     "alleles": [
         {"symbol": "b_blue", "name_pt": "Azul", "name_en": "Blue", "rank": 1},
         {"symbol": "b_parblue", "name_pt": "Parblue", "name_en": "Parblue", "rank": 2},
     ]},
    {"symbol": "dil", "name_pt": "Pastel / Dilute (série Dilute)", "name_en": "Pastel / Dilute series", "mode": "re",
     "is_multi_allele": True,
     "alleles": [
         {"symbol": "dil_pastel", "name_pt": "Pastel", "name_en": "Pastel", "rank": 1},
         {"symbol": "dil_dilute", "name_pt": "Dilute", "name_en": "Dilute", "rank": 2},
     ]},
    {"symbol": "fd",  "name_pt": "Faded", "name_en": "Faded", "mode": "re"},
    # Autosomal dominant
    {"symbol": "P",   "name_pt": "Malhado Dominante (White-winged)", "name_en": "Dominant Pied (White-winged)", "mode": "do"},
    {"symbol": "mel", "name_pt": "Melanístico (Black)", "name_en": "Melanistic (Black)", "mode": "do"},
]
