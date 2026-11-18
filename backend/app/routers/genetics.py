from fastapi import APIRouter, Depends, HTTPException
from sqlalchemy.orm import Session
from typing import List
from app.dependencies import get_db, get_current_user
from app.schemas.genetics import (
    GeneticLocusOut, BirdGenotypeCreate, BirdGenotypeOut,
    PigeonGenotypeCreate, GeneticCalculateRequest, GeneticCalculateResponse,
)
from app.models.genetics import GeneticLocus, BirdGenotype, PigeonGenotype
from app.models.bird import Bird, Species
from app.models.user import User

router = APIRouter(prefix="/api/genetics", tags=["genetics"])


@router.get("/species/{species_id}/loci", response_model=List[GeneticLocusOut])
def get_species_loci(
    species_id: str,
    db: Session = Depends(get_db),
    _: User = Depends(get_current_user),
):
    return (
        db.query(GeneticLocus)
        .filter(GeneticLocus.species_id == species_id)
        .order_by(GeneticLocus.display_order)
        .all()
    )


@router.get("/birds/{bird_id}/genotype", response_model=List[BirdGenotypeOut])
def get_bird_genotype(
    bird_id: str,
    db: Session = Depends(get_db),
    current_user: User = Depends(get_current_user),
):
    bird = db.query(Bird).filter(Bird.id == bird_id, Bird.user_id == current_user.id).first()
    if not bird:
        raise HTTPException(404, "Bird not found")
    return db.query(BirdGenotype).filter(BirdGenotype.bird_id == bird_id).all()


@router.post("/birds/{bird_id}/genotype", response_model=BirdGenotypeOut, status_code=201)
def upsert_bird_genotype(
    bird_id: str,
    body: BirdGenotypeCreate,
    db: Session = Depends(get_db),
    current_user: User = Depends(get_current_user),
):
    bird = db.query(Bird).filter(Bird.id == bird_id, Bird.user_id == current_user.id).first()
    if not bird:
        raise HTTPException(404, "Bird not found")
    existing = db.query(BirdGenotype).filter(
        BirdGenotype.bird_id == bird_id,
        BirdGenotype.locus_id == body.locus_id,
    ).first()
    if existing:
        for k, v in body.model_dump().items():
            setattr(existing, k, v)
        db.commit()
        db.refresh(existing)
        return existing
    record = BirdGenotype(bird_id=bird_id, **body.model_dump())
    db.add(record)
    db.commit()
    db.refresh(record)
    return record


@router.get("/birds/{bird_id}/pigeon-genotype")
def get_pigeon_genotype(
    bird_id: str,
    db: Session = Depends(get_db),
    current_user: User = Depends(get_current_user),
):
    bird = db.query(Bird).filter(Bird.id == bird_id, Bird.user_id == current_user.id).first()
    if not bird:
        raise HTTPException(404, "Bird not found")
    g = db.query(PigeonGenotype).filter(PigeonGenotype.bird_id == bird_id).first()
    if not g:
        raise HTTPException(404, "Pigeon genotype not found")
    return g


@router.post("/birds/{bird_id}/pigeon-genotype", status_code=201)
def upsert_pigeon_genotype(
    bird_id: str,
    body: PigeonGenotypeCreate,
    db: Session = Depends(get_db),
    current_user: User = Depends(get_current_user),
):
    bird = db.query(Bird).filter(Bird.id == bird_id, Bird.user_id == current_user.id).first()
    if not bird:
        raise HTTPException(404, "Bird not found")
    existing = db.query(PigeonGenotype).filter(PigeonGenotype.bird_id == bird_id).first()
    if existing:
        for k, v in body.model_dump().items():
            setattr(existing, k, v)
        db.commit()
        db.refresh(existing)
        return existing
    g = PigeonGenotype(bird_id=bird_id, **body.model_dump())
    db.add(g)
    db.commit()
    db.refresh(g)
    return g


@router.post("/calculate", response_model=GeneticCalculateResponse)
def calculate_genetics(
    body: GeneticCalculateRequest,
    db: Session = Depends(get_db),
    current_user: User = Depends(get_current_user),
):
    from app.services.genetics.calculator import GeneticCalculator
    male = db.query(Bird).filter(Bird.id == body.male_bird_id, Bird.user_id == current_user.id).first()
    female = db.query(Bird).filter(Bird.id == body.female_bird_id, Bird.user_id == current_user.id).first()
    if not male or not female:
        raise HTTPException(404, "Bird not found")

    calculator = GeneticCalculator(db)
    results, warnings = calculator.calculate_from_birds(
        male, female,
        include_sexes=body.include_sexes,
        min_probability=body.min_probability,
    )
    return GeneticCalculateResponse(
        results=results,
        warnings=warnings,
        male_bird_id=body.male_bird_id,
        female_bird_id=body.female_bird_id,
    )
