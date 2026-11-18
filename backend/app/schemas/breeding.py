from pydantic import BaseModel
from typing import Optional
from datetime import datetime


# ---------- Cage ----------
class CageCreate(BaseModel):
    name: str
    location: Optional[str] = None
    notes: Optional[str] = None


class CageUpdate(BaseModel):
    name: Optional[str] = None
    location: Optional[str] = None
    notes: Optional[str] = None
    is_active: Optional[bool] = None


class CageOut(BaseModel):
    id: str
    user_id: str
    name: str
    location: Optional[str] = None
    notes: Optional[str] = None
    is_active: bool
    created_at: datetime

    class Config:
        from_attributes = True


# ---------- Breeding ----------
class BreedingCreate(BaseModel):
    male_id: str
    female_id: str
    pairing_date: datetime
    cage_id: Optional[str] = None
    separation_date: Optional[datetime] = None
    nest_type: Optional[str] = None
    nest_number: Optional[str] = None
    notes: Optional[str] = None


class BreedingUpdate(BaseModel):
    status: Optional[str] = None  # paired|incubating|hatched|failed|completed
    cage_id: Optional[str] = None
    separation_date: Optional[datetime] = None
    nest_type: Optional[str] = None
    nest_number: Optional[str] = None
    notes: Optional[str] = None


class BreedingOut(BaseModel):
    id: str
    user_id: str
    male_id: str
    female_id: str
    cage_id: Optional[str] = None
    pairing_date: datetime
    separation_date: Optional[datetime] = None
    nest_type: Optional[str] = None
    nest_number: Optional[str] = None
    status: str
    notes: Optional[str] = None
    created_at: datetime

    class Config:
        from_attributes = True


# ---------- Clutch ----------
class ClutchCreate(BaseModel):
    lay_date: datetime
    egg_count: int = 0
    fertile_count: Optional[int] = None
    hatched_count: Optional[int] = None
    candling_date: Optional[datetime] = None
    candling_notes: Optional[str] = None


class ClutchUpdate(BaseModel):
    egg_count: Optional[int] = None
    fertile_count: Optional[int] = None
    hatched_count: Optional[int] = None
    candling_date: Optional[datetime] = None
    candling_notes: Optional[str] = None


class ClutchOut(BaseModel):
    id: str
    breeding_id: str
    lay_date: datetime
    egg_count: int
    fertile_count: Optional[int] = None
    hatched_count: Optional[int] = None
    candling_date: Optional[datetime] = None
    candling_notes: Optional[str] = None
    created_at: datetime

    class Config:
        from_attributes = True


# ---------- Offspring ----------
class OffspringCreate(BaseModel):
    hatch_date: Optional[datetime] = None
    hatch_weight_g: Optional[float] = None
    ring_date: Optional[datetime] = None
    ring_number: Optional[str] = None
    notes: Optional[str] = None


class OffspringUpdate(BaseModel):
    hatch_date: Optional[datetime] = None
    hatch_weight_g: Optional[float] = None
    ring_date: Optional[datetime] = None
    ring_number: Optional[str] = None
    notes: Optional[str] = None


class OffspringOut(BaseModel):
    id: str
    clutch_id: str
    bird_id: Optional[str] = None
    hatch_date: Optional[datetime] = None
    hatch_weight_g: Optional[float] = None
    ring_date: Optional[datetime] = None
    ring_number: Optional[str] = None
    notes: Optional[str] = None

    class Config:
        from_attributes = True


class OffspringPromote(BaseModel):
    name: str
    species_id: Optional[str] = None
    breed_id: Optional[str] = None
    sex: str = "unknown"  # M|F|unknown
    color: Optional[str] = None
