"""Seed data: Periquito-australiano / Budgerigar (Melopsittacus undulatus) genetic loci.

Fonte: nomenclatura e modos de herança consolidados da literatura padrão de
genética de cor em psitacídeos (ex.: Martin, "A Guide to Colour Mutations &
Genetics in Parrots"), restrita às mutações classificadas como "common" ou
"established" para a espécie.
"""

BUDGERIGAR_LOCI = [
    # Sex-linked recessive
    {"symbol": "ino", "name_pt": "Lutino / Clearbody (série ino)", "name_en": "Lutino / Clearbody (ino series)", "mode": "se",
     "is_multi_allele": True,
     "alleles": [
         {"symbol": "ino_lutino", "name_pt": "Lutino", "name_en": "Lutino", "rank": 1},
         {"symbol": "ino_clearbody", "name_pt": "Clearbody (Par-ino)", "name_en": "Clearbody (Par-ino)", "rank": 2},
     ]},
    {"symbol": "cin", "name_pt": "Canela (Cinnamon)", "name_en": "Cinnamon", "mode": "se"},
    {"symbol": "op",  "name_pt": "Opalino (Opaline)", "name_en": "Opaline", "mode": "se"},
    # Autosomal recessive
    {"symbol": "b",   "name_pt": "Azul / Parblue (locus Blue)", "name_en": "Blue / Parblue (Blue locus)", "mode": "re",
     "is_multi_allele": True,
     "alleles": [
         {"symbol": "b_blue", "name_pt": "Azul", "name_en": "Blue", "rank": 1},
         {"symbol": "b_turquoise", "name_pt": "Turquesa (Parblue)", "name_en": "Turquoise (Parblue)", "rank": 2},
     ]},
    {"symbol": "dil", "name_pt": "Greywing / Clearwing / Dilute (série Dilute)", "name_en": "Greywing / Clearwing / Dilute series", "mode": "re",
     "is_multi_allele": True,
     "alleles": [
         {"symbol": "dil_greywing", "name_pt": "Greywing", "name_en": "Greywing", "rank": 1},
         {"symbol": "dil_clearwing", "name_pt": "Clearwing", "name_en": "Clearwing", "rank": 2},
         {"symbol": "dil_dilute", "name_pt": "Dilute", "name_en": "Dilute", "rank": 3},
     ]},
    {"symbol": "fal", "name_pt": "Fallow (Bronze/German Fallow)", "name_en": "Fallow (Bronze/German Fallow)", "mode": "re"},
    {"symbol": "r",   "name_pt": "Malhado Recessivo (Danish Pied)", "name_en": "Recessive Pied (Danish Pied)", "mode": "re"},
    # Autosomal incomplete dominant / co-dominant
    {"symbol": "D",   "name_pt": "Fator Escuro (Dark Green / Olive)", "name_en": "Dark Factor (Dark Green / Olive)", "mode": "in"},
    {"symbol": "V",   "name_pt": "Violeta (Violet)", "name_en": "Violet", "mode": "in"},
    # Autosomal dominant
    {"symbol": "G",   "name_pt": "Cinza (Grey)", "name_en": "Grey", "mode": "do"},
    {"symbol": "P",   "name_pt": "Malhado Dominante (Dutch/Australian Pied)", "name_en": "Dominant Pied (Dutch/Australian Pied)", "mode": "do"},
    {"symbol": "spg", "name_pt": "Spangle", "name_en": "Spangle", "mode": "do"},
    {"symbol": "mel", "name_pt": "Melanístico (Blackface)", "name_en": "Melanistic (Blackface)", "mode": "do"},
]
