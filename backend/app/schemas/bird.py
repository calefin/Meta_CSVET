from pydantic import BaseModel
from typing import Optional
from datetime import datetime


class SpeciesOut(BaseModel):
    id: str
    common_name: str
    scientific_name: str
    category: str
    avg_lifespan_years: Optional[int] = None
    incubation_days: Optional[int] = None
    clutch_size_min: Optional[int] = None
    clutch_size_max: Optional[int] = None

    class Config:
        from_attributes = True


class BreedOut(BaseModel):
    id: str
    species_id: str
    name: str
    origin: Optional[str] = None

    class Config:
        from_attributes = True


class BirdCreate(BaseModel):
    name: str
    ring_number: Optional[str] = None
    microchip: Optional[str] = None
    species_id: Optional[str] = None
    breed_id: Optional[str] = None
    sex: str = "unknown"
    birth_date: Optional[datetime] = None
    acquisition_date: Optional[datetime] = None
    acquisition_type: Optional[str] = None
    color: Optional[str] = None
    plumage_description: Optional[str] = None
    weight_grams: Optional[float] = None
    father_id: Optional[str] = None
    mother_id: Optional[str] = None
    photo_url: Optional[str] = None
    notes: Optional[str] = None


class BirdUpdate(BirdCreate):
    name: Optional[str] = None
    sex: Optional[str] = None


class BirdOut(BaseModel):
    id: str
    user_id: str
    name: str
    ring_number: Optional[str] = None
    microchip: Optional[str] = None
    species_id: Optional[str] = None
    breed_id: Optional[str] = None
    sex: str
    birth_date: Optional[datetime] = None
    acquisition_date: Optional[datetime] = None
    acquisition_type: Optional[str] = None
    color: Optional[str] = None
    plumage_description: Optional[str] = None
    weight_grams: Optional[float] = None
    father_id: Optional[str] = None
    mother_id: Optional[str] = None
    photo_url: Optional[str] = None
    status: str
    notes: Optional[str] = None
    created_at: datetime

    class Config:
        from_attributes = True
