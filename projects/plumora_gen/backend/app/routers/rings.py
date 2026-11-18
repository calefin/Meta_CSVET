from fastapi import APIRouter, Depends, HTTPException
from sqlalchemy.orm import Session
from typing import Optional, List
from pydantic import BaseModel
from app.dependencies import get_db, get_current_user
from app.models.ring import Ring
from app.models.bird import Bird, Species
from app.models.user import User

router = APIRouter(prefix="/api/rings", tags=["rings"])


class RingCreate(BaseModel):
    ring_number: str
    year: Optional[int] = None
    color: Optional[str] = None
    species_id: Optional[str] = None
    source: Optional[str] = None
    notes: Optional[str] = None


class RingAssign(BaseModel):
    bird_id: str


@router.get("")
def list_rings(db: Session = Depends(get_db), current_user: User = Depends(get_current_user)):
    rings = db.query(Ring).filter(Ring.owner_id == current_user.id).order_by(Ring.created_at.desc()).all()
    result = []
    for r in rings:
        bird = db.query(Bird).filter(Bird.id == r.bird_id).first() if r.bird_id else None
        species = db.query(Species).filter(Species.id == r.species_id).first() if r.species_id else None
        result.append({
            "id": r.id,
            "ring_number": r.ring_number,
            "year": r.year,
            "color": r.color,
            "species_id": r.species_id,
            "species_name": species.common_name if species else None,
            "bird_id": r.bird_id,
            "bird_name": bird.name if bird else None,
            "source": r.source,
            "notes": r.notes,
            "created_at": r.created_at,
        })
    return result


@router.get("/stats")
def ring_stats(db: Session = Depends(get_db), current_user: User = Depends(get_current_user)):
    rings = db.query(Ring).filter(Ring.owner_id == current_user.id).all()
    total = len(rings)
    assigned = sum(1 for r in rings if r.bird_id is not None)
    unassigned = total - assigned

    # Group by species
    by_species: dict = {}
    for r in rings:
        key = r.species_id or "unknown"
        if key not in by_species:
            species = db.query(Species).filter(Species.id == key).first() if key != "unknown" else None
            by_species[key] = {"species_id": key, "species_name": species.common_name if species else "Sem espécie", "count": 0}
        by_species[key]["count"] += 1

    return {
        "total_rings": total,
        "assigned_rings": assigned,
        "unassigned_rings": unassigned,
        "by_species": list(by_species.values()),
    }


@router.post("", status_code=201)
def create_ring(body: RingCreate, db: Session = Depends(get_db), current_user: User = Depends(get_current_user)):
    ring = Ring(owner_id=current_user.id, **body.model_dump())
    db.add(ring)
    db.commit()
    db.refresh(ring)
    return ring


@router.delete("/{ring_id}", status_code=204)
def delete_ring(ring_id: str, db: Session = Depends(get_db), current_user: User = Depends(get_current_user)):
    ring = db.query(Ring).filter(Ring.id == ring_id, Ring.owner_id == current_user.id).first()
    if not ring:
        raise HTTPException(404, "Anilha não encontrada")
    if ring.bird_id:
        bird = db.query(Bird).filter(Bird.id == ring.bird_id).first()
        if bird and bird.status == "active":
            raise HTTPException(400, "Anilha está associada a uma ave ativa. Remova a associação primeiro.")
    db.delete(ring)
    db.commit()


@router.post("/{ring_id}/assign")
def assign_ring(ring_id: str, body: RingAssign, db: Session = Depends(get_db), current_user: User = Depends(get_current_user)):
    ring = db.query(Ring).filter(Ring.id == ring_id, Ring.owner_id == current_user.id).first()
    if not ring:
        raise HTTPException(404, "Anilha não encontrada")
    bird = db.query(Bird).filter(Bird.id == body.bird_id, Bird.user_id == current_user.id).first()
    if not bird:
        raise HTTPException(404, "Ave não encontrada")
    ring.bird_id = body.bird_id
    db.commit()
    db.refresh(ring)
    return {"ok": True, "ring_id": ring.id, "bird_id": body.bird_id}
