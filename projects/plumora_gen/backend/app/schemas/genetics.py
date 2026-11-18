from pydantic import BaseModel
from typing import Optional, List, Dict, Any
from datetime import datetime


class GeneticLocusOut(BaseModel):
    id: str
    species_id: str
    symbol: str
    name_pt: str
    name_en: str
    inheritance_mode: str
    allele_group: Optional[str] = None
    crossover_freq: Optional[float] = None
    is_multi_allele: bool = False
    alleles_json: Optional[List[Dict]] = None
    display_order: int = 0

    class Config:
        from_attributes = True


class BirdGenotypeCreate(BaseModel):
    locus_id: str
    allele_1: str
    allele_2: Optional[str] = None
    confidence: str = "estimated"
    source: str = "manual"
    notes: Optional[str] = None


class BirdGenotypeOut(BirdGenotypeCreate):
    id: str
    bird_id: str
    created_at: datetime

    class Config:
        from_attributes = True


class PigeonGenotypeCreate(BaseModel):
    color_z1: Optional[str] = None
    color_z2: Optional[str] = None
    dilute_z1: Optional[str] = None
    dilute_z2: Optional[str] = None
    pattern_a1: Optional[str] = None
    pattern_a2: Optional[str] = None
    spread_a1: Optional[str] = None
    spread_a2: Optional[str] = None
    rec_red_a1: Optional[str] = None
    rec_red_a2: Optional[str] = None
    crest_a1: Optional[str] = None
    crest_a2: Optional[str] = None
    slipper_a1: Optional[str] = None
    slipper_a2: Optional[str] = None
    grouse_a1: Optional[str] = None
    grouse_a2: Optional[str] = None
    confidence: str = "estimated"
    source: str = "manual"
    notes: Optional[str] = None


class GeneticCalculateRequest(BaseModel):
    male_bird_id: str
    female_bird_id: str
    include_sexes: bool = True
    min_probability: float = 1.0


class GeneticCalculateManualRequest(BaseModel):
    species_id: str
    male_genotype: Dict[str, Any]
    female_genotype: Dict[str, Any]


class GeneticResultItem(BaseModel):
    sex: str
    phenotype: List[str]
    splits: List[str]
    genotype_code: str
    probability: float
    visual_description: str
    warnings: Optional[List[Dict]] = None


class GeneticCalculateResponse(BaseModel):
    results: List[GeneticResultItem]
    warnings: List[Dict]
    male_bird_id: Optional[str] = None
    female_bird_id: Optional[str] = None
