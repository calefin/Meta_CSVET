"""Seed data: Agapornis roseicollis / Peachfaced Lovebird genetic loci.

Fonte: nomenclatura e modos de herança consolidados da literatura padrão de
genética de cor em psitacídeos (ex.: Martin, "A Guide to Colour Mutations &
Genetics in Parrots"), restrita às mutações classificadas como "common" ou
"established" para a espécie.
"""

LOVEBIRD_PEACHFACED_LOCI = [
    # Sex-linked recessive
    {"symbol": "ino", "name_pt": "Lutino / Lime (série ino)", "name_en": "Lutino / Lime (ino series)", "mode": "se",
     "is_multi_allele": True,
     "alleles": [
         {"symbol": "ino_lutino", "name_pt": "Lutino", "name_en": "Lutino", "rank": 1},
         {"symbol": "ino_lime", "name_pt": "Lime (Canela Australiano)", "name_en": "Lime (Australian Cinnamon)", "rank": 2},
     ]},
    {"symbol": "cin", "name_pt": "Canela Americano (American Cinnamon)", "name_en": "American Cinnamon", "mode": "se"},
    {"symbol": "op",  "name_pt": "Opalino (Rose-headed)", "name_en": "Opaline (Rose-headed)", "mode": "se"},
    {"symbol": "orf", "name_pt": "Face Laranja (Orangeface)", "name_en": "Orangeface", "mode": "se"},
    # Autosomal recessive
    {"symbol": "b",   "name_pt": "Turquesa / Aqua (locus Blue)", "name_en": "Turquoise / Aqua (Blue locus)", "mode": "re",
     "is_multi_allele": True,
     "alleles": [
         {"symbol": "b_turquoise", "name_pt": "Turquesa (Whiteface Blue)", "name_en": "Turquoise (Whiteface Blue)", "rank": 1},
         {"symbol": "b_aqua", "name_pt": "Aqua (Seagreen)", "name_en": "Aqua (Seagreen)", "rank": 2},
     ]},
    {"symbol": "dil", "name_pt": "Diluído Marginado (Edged Dilute)", "name_en": "Edged Dilute", "mode": "re"},
    {"symbol": "fal", "name_pt": "Fallow (Bronze/Pale)", "name_en": "Fallow (Bronze/Pale)", "mode": "re",
     "is_multi_allele": True,
     "alleles": [
         {"symbol": "fal_bronze", "name_pt": "Bronze Fallow", "name_en": "Bronze Fallow", "rank": 1},
         {"symbol": "fal_pale", "name_pt": "Pale Fallow", "name_en": "Pale Fallow", "rank": 2},
     ]},
    {"symbol": "bec", "name_pt": "Olho-preto Claro (Black-eyed Clear)", "name_en": "Black-eyed Clear", "mode": "re"},
    # Autosomal incomplete dominant / co-dominant
    {"symbol": "D",   "name_pt": "Fator Escuro (Dark Green / Olive)", "name_en": "Dark Factor (Dark Green / Olive)", "mode": "in"},
    {"symbol": "V",   "name_pt": "Violeta (Violet)", "name_en": "Violet", "mode": "in"},
    # Autosomal dominant
    {"symbol": "P",   "name_pt": "Malhado Dominante (Pied)", "name_en": "Dominant Pied", "mode": "do"},
]
