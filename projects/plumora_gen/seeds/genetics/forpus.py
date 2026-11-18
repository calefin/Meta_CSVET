"""Seed data: Forpus azul / Pacific Parrotlet (Forpus coelestis) genetic loci.

Fonte: nomenclatura e modos de herança consolidados da literatura padrão de
genética de cor em psitacídeos (ex.: Martin, "A Guide to Colour Mutations &
Genetics in Parrots"), restrita às mutações documentadas para a espécie.
"""

FORPUS_LOCI = [
    # Autosomal recessive
    {"symbol": "a",   "name_pt": "Lutino NSL (não ligado ao sexo)", "name_en": "NSL Lutino (autosomal)", "mode": "re"},
    {"symbol": "b",   "name_pt": "Azul (locus Blue)", "name_en": "Blue", "mode": "re"},
    {"symbol": "dil", "name_pt": "Amarelo Americano / Europeu / Isabel (série Dilute)", "name_en": "American/European Yellow / Isabel (Dilute series)", "mode": "re",
     "is_multi_allele": True,
     "alleles": [
         {"symbol": "dil_american", "name_pt": "Amarelo Americano", "name_en": "American Yellow", "rank": 1},
         {"symbol": "dil_european", "name_pt": "Amarelo Europeu", "name_en": "European Yellow", "rank": 2},
         {"symbol": "dil_isabel", "name_pt": "Isabel", "name_en": "Isabel", "rank": 3},
     ]},
    {"symbol": "fal", "name_pt": "Fallow (Dun Fallow)", "name_en": "Fallow (Dun Fallow)", "mode": "re"},
    {"symbol": "rg",  "name_pt": "Cinza Recessivo (Recessive Greygreen)", "name_en": "Recessive Grey (Recessive Greygreen)", "mode": "re"},
    # Autosomal dominant
    {"symbol": "P",   "name_pt": "Malhado Dominante (Pied)", "name_en": "Dominant Pied", "mode": "do"},
]
