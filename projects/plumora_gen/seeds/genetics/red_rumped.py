"""Seed data: Red-rumped Parrot / Canário-do-mato (Psephotus haematonotus)
genetic loci.

Fonte: nomenclatura e modos de herança consolidados da literatura padrão de
genética de cor em psitacídeos (ex.: Martin, "A Guide to Colour Mutations &
Genetics in Parrots"), restrita às mutações documentadas para a espécie.
"""

RED_RUMPED_LOCI = [
    # Sex-linked recessive
    {"symbol": "ino", "name_pt": "Lutino / Lime / Platinum (série ino)", "name_en": "Lutino / Lime / Platinum (ino series)", "mode": "se",
     "is_multi_allele": True,
     "alleles": [
         {"symbol": "ino_lutino", "name_pt": "Lutino", "name_en": "Lutino", "rank": 1},
         {"symbol": "ino_platinum", "name_pt": "Platinum", "name_en": "Platinum", "rank": 2},
         {"symbol": "ino_lime", "name_pt": "Lime (Isabel/UK Cinnamon)", "name_en": "Lime (Isabel/UK Cinnamon)", "rank": 3},
     ]},
    {"symbol": "cin", "name_pt": "Canela (Cinnamon)", "name_en": "Cinnamon", "mode": "se"},
    {"symbol": "op",  "name_pt": "Opalino (Opaline)", "name_en": "Opaline", "mode": "se"},
    # Autosomal recessive
    {"symbol": "b",   "name_pt": "Azul / Turquesa (locus Blue)", "name_en": "Blue / Turquoise (Blue locus)", "mode": "re",
     "is_multi_allele": True,
     "alleles": [
         {"symbol": "b_blue", "name_pt": "Azul", "name_en": "Blue", "rank": 1},
         {"symbol": "b_turquoise", "name_pt": "Turquesa (European Blue)", "name_en": "Turquoise (European Blue)", "rank": 2},
     ]},
    {"symbol": "fal", "name_pt": "Fallow (Golden Fallow)", "name_en": "Fallow (Golden Fallow)", "mode": "re"},
    {"symbol": "dm",  "name_pt": "Malhado ADM (UK Pied)", "name_en": "ADM Pied (UK Pied)", "mode": "re"},
    {"symbol": "bec", "name_pt": "Olho-preto Claro (Australian Pied)", "name_en": "Black-eyed Clear (Australian Pied)", "mode": "re"},
    # Autosomal dominant
    {"symbol": "G",   "name_pt": "Cinza (Greygreen)", "name_en": "Grey (Greygreen)", "mode": "do"},
]
