import csv
import io
from datetime import date

from fastapi import APIRouter, Depends, HTTPException, status
from fastapi.responses import StreamingResponse
from sqlalchemy.orm import Session
from typing import List
from app.dependencies import get_db, get_current_user
from app.schemas.bird import BirdCreate, BirdUpdate, BirdOut, SpeciesOut, BreedOut
from app.models.bird import Bird, Species, Breed
from app.models.user import User

router = APIRouter(prefix="/api/birds", tags=["birds"])


@router.get("", response_model=List[BirdOut])
def list_birds(
    status: str = None,
    species_id: str = None,
    db: Session = Depends(get_db),
    current_user: User = Depends(get_current_user),
):
    q = db.query(Bird).filter(Bird.user_id == current_user.id)
    if status:
        q = q.filter(Bird.status == status)
    if species_id:
        q = q.filter(Bird.species_id == species_id)
    return q.order_by(Bird.name).all()


# Declared before `/{bird_id}` — a literal path segment must come first, otherwise
# FastAPI would try to match "export" as a `bird_id` value.
@router.get("/export")
def export_birds(db: Session = Depends(get_db), current_user: User = Depends(get_current_user)):
    """Export the breeder's own plantel (birds) as a CSV spreadsheet — LGPD data
    portability. Only the plantel is in scope here (not health/vaccination/breeding
    records — those may be added in a future export)."""
    birds = db.query(Bird).filter(Bird.user_id == current_user.id).order_by(Bird.name).all()
    species_by_id = {s.id: s for s in db.query(Species).all()}
    breed_by_id = {b.id: b for b in db.query(Breed).all()}
    all_bird_ids = {b.id: b for b in db.query(Bird).filter(Bird.user_id == current_user.id).all()}

    buffer = io.StringIO()
    buffer.write("﻿")  # UTF-8 BOM so Excel no Windows detecta acentuação corretamente
    writer = csv.writer(buffer, delimiter=";")
    writer.writerow([
        "Nome", "Anilha", "Microchip", "Espécie", "Raça", "Sexo", "Data de Nascimento",
        "Data de Aquisição", "Tipo de Aquisição", "Cor", "Peso (g)", "Pai", "Mãe",
        "Status", "Observações", "Cadastrado em",
    ])
    for b in birds:
        species = species_by_id.get(b.species_id)
        breed = breed_by_id.get(b.breed_id)
        father = all_bird_ids.get(b.father_id)
        mother = all_bird_ids.get(b.mother_id)
        writer.writerow([
            b.name,
            b.ring_number or "",
            b.microchip or "",
            species.common_name if species else "",
            breed.name if breed else "",
            {"M": "Macho", "F": "Fêmea", "unknown": "Indeterminado"}.get(b.sex, b.sex),
            b.birth_date.strftime("%d/%m/%Y") if b.birth_date else "",
            b.acquisition_date.strftime("%d/%m/%Y") if b.acquisition_date else "",
            b.acquisition_type or "",
            b.color or "",
            b.weight_grams if b.weight_grams is not None else "",
            father.name if father else "",
            mother.name if mother else "",
            b.status,
            b.notes or "",
            b.created_at.strftime("%d/%m/%Y") if b.created_at else "",
        ])

    buffer.seek(0)
    filename = f"plantel-{date.today().isoformat()}.csv"
    return StreamingResponse(
        iter([buffer.getvalue()]),
        media_type="text/csv",
        headers={"Content-Disposition": f'attachment; filename="{filename}"'},
    )


@router.post("", response_model=BirdOut, status_code=201)
def create_bird(
    body: BirdCreate,
    db: Session = Depends(get_db),
    current_user: User = Depends(get_current_user),
):
    # Check plan bird limit
    from app.models.user import Plan
    plan = db.query(Plan).filter(Plan.id == current_user.plan_id).first()
    if plan:
        count = db.query(Bird).filter(Bird.user_id == current_user.id, Bird.status == "active").count()
        if count >= plan.max_birds:
            raise HTTPException(status_code=402, detail=f"Bird limit reached for your plan ({plan.max_birds})")

    bird = Bird(user_id=current_user.id, **body.model_dump())
    db.add(bird)
    db.commit()
    db.refresh(bird)
    return bird


@router.get("/{bird_id}", response_model=BirdOut)
def get_bird(
    bird_id: str,
    db: Session = Depends(get_db),
    current_user: User = Depends(get_current_user),
):
    bird = db.query(Bird).filter(Bird.id == bird_id, Bird.user_id == current_user.id).first()
    if not bird:
        raise HTTPException(status_code=404, detail="Bird not found")
    return bird


@router.put("/{bird_id}", response_model=BirdOut)
def update_bird(
    bird_id: str,
    body: BirdUpdate,
    db: Session = Depends(get_db),
    current_user: User = Depends(get_current_user),
):
    bird = db.query(Bird).filter(Bird.id == bird_id, Bird.user_id == current_user.id).first()
    if not bird:
        raise HTTPException(status_code=404, detail="Bird not found")
    for k, v in body.model_dump(exclude_none=True).items():
        setattr(bird, k, v)
    db.commit()
    db.refresh(bird)
    return bird


@router.delete("/{bird_id}", status_code=204)
def delete_bird(
    bird_id: str,
    db: Session = Depends(get_db),
    current_user: User = Depends(get_current_user),
):
    bird = db.query(Bird).filter(Bird.id == bird_id, Bird.user_id == current_user.id).first()
    if not bird:
        raise HTTPException(status_code=404, detail="Bird not found")
    bird.status = "deleted"
    db.commit()


@router.get("/{bird_id}/stats")
def bird_stats(
    bird_id: str,
    db: Session = Depends(get_db),
    current_user: User = Depends(get_current_user),
):
    from app.services.zootechnical import get_bird_stats
    bird = db.query(Bird).filter(Bird.id == bird_id, Bird.user_id == current_user.id).first()
    if not bird:
        raise HTTPException(status_code=404, detail="Bird not found")
    return get_bird_stats(db, bird)


@router.get("/species/all", response_model=List[SpeciesOut])
def list_species(db: Session = Depends(get_db), _=Depends(get_current_user)):
    return db.query(Species).order_by(Species.common_name).all()


@router.get("/breeds/by-species/{species_id}", response_model=List[BreedOut])
def list_breeds(species_id: str, db: Session = Depends(get_db), _=Depends(get_current_user)):
    return db.query(Breed).filter(Breed.species_id == species_id).all()
