"""Seed data: Calopsita (Nymphicus hollandicus) genetic loci."""

COCKATIEL_LOCI = [
    # Sex-linked recessive
    {"symbol": "cin",   "name_pt": "Canela (Cinnamon)",           "name_en": "Cinnamon",          "mode": "se", "crossover_to_next": 0.07},
    {"symbol": "ych",   "name_pt": "Bochecha Amarela (Yellow Cheek)", "name_en": "Yellow Cheek",  "mode": "se", "crossover_to_next": 0.03},
    {"symbol": "ino",   "name_pt": "Ino / Pálido / Platina",      "name_en": "Ino / Pallid / Platinum", "mode": "se",
     "is_multi_allele": True, "crossover_to_next": 0.30,
     "alleles": [
         {"symbol": "ino",   "name_pt": "Ino (Albino)",    "name_en": "Ino (Albino)",    "rank": 1},
         {"symbol": "inopd", "name_pt": "Pálido (Pallid)", "name_en": "Pallid",           "rank": 2},
     ]},
    {"symbol": "op",    "name_pt": "Opalino (Pérola)",            "name_en": "Opaline (Pearl)",   "mode": "se"},
    # Autosomal recessive
    {"symbol": "dil",   "name_pt": "Diluído (Prateado Pastel)",   "name_en": "Dilute (Silver Pastel)", "mode": "re"},
    {"symbol": "aabz",  "name_pt": "NSL-Ino / Bronze Fallow",     "name_en": "NSL-Ino / Bronze Fallow", "mode": "re",
     "is_multi_allele": True,
     "alleles": [
         {"symbol": "aabz_nsl", "name_pt": "NSL-Ino",      "name_en": "NSL-Ino",      "rank": 1},
         {"symbol": "aabz_bz",  "name_pt": "Bronze Fallow", "name_en": "Bronze Fallow", "rank": 2},
     ]},
    {"symbol": "mb",    "name_pt": "Malhado (Marbled)",           "name_en": "Marbled Pied",      "mode": "re"},
    {"symbol": "s",     "name_pt": "Malhado ADM (ADM Pied)",      "name_en": "ADM Pied",          "mode": "re"},
    {"symbol": "fd",    "name_pt": "Desbotado (Faded)",            "name_en": "Faded",             "mode": "re"},
    {"symbol": "wwpa",  "name_pt": "Face-Branca / Pastel-face",   "name_en": "Whiteface / Pastel-face", "mode": "re",
     "is_multi_allele": True,
     "alleles": [
         {"symbol": "ww", "name_pt": "Face-Branca (Whiteface)", "name_en": "Whiteface", "rank": 1, "sub_mode": "re"},
         {"symbol": "pa", "name_pt": "Pastel-face",             "name_en": "Pastel-face","rank": 2, "sub_mode": "do"},
     ]},
    {"symbol": "sf",    "name_pt": "Prateado Recessivo (Ashen Fallow)", "name_en": "Ashen Fallow", "mode": "re"},
    # Autosomal incomplete dominant
    {"symbol": "Si",    "name_pt": "Prateado Dominante (Ashen Dilute)", "name_en": "Ashen Dilute", "mode": "in"},
]
