"""
Database seed script.
Run: python seeds/seed_db.py
"""
import sys, os
sys.path.insert(0, os.path.join(os.path.dirname(__file__), ".."))

from app.database import SessionLocal, engine, Base
from app.models import *  # noqa: ensure all models are imported

Base.metadata.create_all(bind=engine)

db = SessionLocal()

# ── Plans ──────────────────────────────────────────────────────────────────
from app.models.user import Plan

plans = [
    Plan(id="plan-free",    name="free",    max_birds=10,  price_monthly=0,    price_yearly=0),
    Plan(id="plan-basic",   name="basic",   max_birds=50,  price_monthly=1990, price_yearly=19900),
    Plan(id="plan-pro",     name="pro",     max_birds=200, price_monthly=4990, price_yearly=49900),
    Plan(id="plan-premium", name="premium", max_birds=9999,price_monthly=8990, price_yearly=89900),
]
for p in plans:
    if not db.query(Plan).filter(Plan.id == p.id).first():
        db.add(p)
db.commit()

# ── Species ────────────────────────────────────────────────────────────────
from app.models.bird import Species

species_data = [
    {"id": "sp-calopsita",   "common_name": "Calopsita",               "scientific_name": "Nymphicus hollandicus",       "category": "ornamental", "avg_lifespan_years": 20, "incubation_days": 21, "clutch_size_min": 4, "clutch_size_max": 8},
    {"id": "sp-pombo",       "common_name": "Pombo Correio",           "scientific_name": "Columba livia domestica",     "category": "pigeon",     "avg_lifespan_years": 15, "incubation_days": 18, "clutch_size_min": 1, "clutch_size_max": 2},
    {"id": "sp-periquito",   "common_name": "Periquito-australiano",   "scientific_name": "Melopsittacus undulatus",     "category": "ornamental", "avg_lifespan_years": 10, "incubation_days": 18, "clutch_size_min": 4, "clutch_size_max": 8},
    {"id": "sp-agapornis-r", "common_name": "Agapornis roseicollis",   "scientific_name": "Agapornis roseicollis",       "category": "ornamental", "avg_lifespan_years": 15, "incubation_days": 23, "clutch_size_min": 4, "clutch_size_max": 6},
    {"id": "sp-rosela-ori",  "common_name": "Rosela-oriental",        "scientific_name": "Platycercus eximius",        "category": "exotic",     "avg_lifespan_years": 25, "incubation_days": 19, "clutch_size_min": 3, "clutch_size_max": 7},
    {"id": "sp-rosela-car",  "common_name": "Rosela-carmesim",        "scientific_name": "Platycercus elegans",        "category": "exotic",     "avg_lifespan_years": 25, "incubation_days": 20, "clutch_size_min": 3, "clutch_size_max": 5},
    {"id": "sp-red-rumped",  "common_name": "Red-rumped / Canário-do-mato", "scientific_name": "Psephotus haematonotus","category": "exotic",     "avg_lifespan_years": 15, "incubation_days": 20, "clutch_size_min": 4, "clutch_size_max": 6},
    {"id": "sp-agap-mask",   "common_name": "Agapornis máscara",      "scientific_name": "Agapornis personatus",       "category": "ornamental", "avg_lifespan_years": 12, "incubation_days": 23, "clutch_size_min": 4, "clutch_size_max": 6},
    {"id": "sp-forpus",      "common_name": "Forpus azul",            "scientific_name": "Forpus coelestis",           "category": "exotic",     "avg_lifespan_years": 12, "incubation_days": 23, "clutch_size_min": 4, "clutch_size_max": 6},
    {"id": "sp-canario",     "common_name": "Canário-doméstico",      "scientific_name": "Serinus canaria",            "category": "domestic",   "avg_lifespan_years": 12, "incubation_days": 14, "clutch_size_min": 3, "clutch_size_max": 5},
]
for s in species_data:
    if not db.query(Species).filter(Species.id == s["id"]).first():
        db.add(Species(**s))
db.commit()

# ── Genetic Loci ────────────────────────────────────────────────────────────
from app.models.genetics import GeneticLocus
from seeds.genetics.cockatiel import COCKATIEL_LOCI
from seeds.genetics.canary import CANARY_LOCI
from seeds.genetics.budgerigar import BUDGERIGAR_LOCI
from seeds.genetics.lovebird_peachfaced import LOVEBIRD_PEACHFACED_LOCI
from seeds.genetics.lovebird_masked import LOVEBIRD_MASKED_LOCI
from seeds.genetics.rosella_eastern import ROSELLA_EASTERN_LOCI
from seeds.genetics.rosella_crimson import ROSELLA_CRIMSON_LOCI
from seeds.genetics.red_rumped import RED_RUMPED_LOCI
from seeds.genetics.forpus import FORPUS_LOCI

LOCI_BY_SPECIES = [
    ("sp-calopsita",   "cal",  COCKATIEL_LOCI),
    ("sp-canario",     "can",  CANARY_LOCI),
    ("sp-periquito",   "bud",  BUDGERIGAR_LOCI),
    ("sp-agapornis-r", "lbp",  LOVEBIRD_PEACHFACED_LOCI),
    ("sp-agap-mask",   "lbm",  LOVEBIRD_MASKED_LOCI),
    ("sp-rosela-ori",  "rose", ROSELLA_EASTERN_LOCI),
    ("sp-rosela-car",  "rosc", ROSELLA_CRIMSON_LOCI),
    ("sp-red-rumped",  "rrp",  RED_RUMPED_LOCI),
    ("sp-forpus",      "fps",  FORPUS_LOCI),
]

for species_id, prefix, loci_list in LOCI_BY_SPECIES:
    for i, locus in enumerate(loci_list):
        lid = f"gl-{prefix}-{locus['symbol']}"
        if not db.query(GeneticLocus).filter(GeneticLocus.id == lid).first():
            db.add(GeneticLocus(
                id=lid,
                species_id=species_id,
                symbol=locus["symbol"],
                name_pt=locus["name_pt"],
                name_en=locus.get("name_en", locus["name_pt"]),
                inheritance_mode=locus["mode"],
                crossover_freq=locus.get("crossover_to_next", 0.0),
                is_multi_allele=locus.get("is_multi_allele", False),
                alleles_json=locus.get("alleles"),
                display_order=i,
            ))
db.commit()

# ── Vaccine Products ───────────────────────────────────────────────────────
from app.models.medication import VaccineProduct

vaccines = [
    {"id": "vac-pmv1",    "name_pt": "Paramyxovírus (PMV-1 / Newcastle)",          "name_en": "Paramyxovirus (PMV-1 / Newcastle)", "route": "SC/IM", "interval_days": 365},
    {"id": "vac-salm",    "name_pt": "Salmonela (S. typhimurium)",                  "name_en": "Salmonella (S. typhimurium)",        "route": "oral/água", "interval_days": 180},
    {"id": "vac-newc-bi", "name_pt": "Newcastle + Bronquite Infecciosa (BI)",       "name_en": "Newcastle + Infectious Bronchitis",  "route": "ocular/nasal", "interval_days": 90},
    {"id": "vac-fowlpox", "name_pt": "Bouba Aviária (Fowlpox)",                     "name_en": "Avian Pox (Fowlpox)",               "route": "asa (wing web)", "interval_days": 365},
    {"id": "vac-marek",   "name_pt": "Marek",                                        "name_en": "Marek's Disease",                   "route": "SC", "interval_days": None, "notes": "Dose única no pinto"},
    {"id": "vac-gumboro", "name_pt": "Gumboro (IBD)",                               "name_en": "Gumboro (IBD)",                     "route": "ocular/água", "interval_days": 21},
    {"id": "vac-flu",     "name_pt": "Influenza Aviária",                           "name_en": "Avian Influenza",                   "route": "IM", "interval_days": 180},
    {"id": "vac-bfd",     "name_pt": "Poliomavírus (BFD)",                          "name_en": "Polyomavirus (BFD)",                "route": "IM", "interval_days": 21},
]
for v in vaccines:
    if not db.query(VaccineProduct).filter(VaccineProduct.id == v["id"]).first():
        db.add(VaccineProduct(
            id=v["id"],
            name_pt=v["name_pt"],
            name_en=v["name_en"],
            route=v.get("route"),
            interval_days=v.get("interval_days"),
            requires_booster=v.get("interval_days") is not None,
        ))
db.commit()

print("✓ Database seeded successfully.")
db.close()
