from fastapi import APIRouter, Depends, HTTPException
from sqlalchemy.orm import Session
from typing import List, Optional

from app.dependencies import get_db, get_current_user
from app.models.bird import Bird
from app.models.breeding import Breeding, Clutch, Offspring, Cage
from app.models.user import User
from app.schemas.bird import BirdOut
from app.schemas.breeding import (
    BreedingCreate,
    BreedingUpdate,
    BreedingOut,
    ClutchCreate,
    ClutchUpdate,
    ClutchOut,
    OffspringCreate,
    OffspringUpdate,
    OffspringOut,
    OffspringPromote,
)

router = APIRouter(prefix="/api/breeding", tags=["breeding"])


def _cage_is_occupied(db: Session, cage_id: str, exclude_breeding_id: Optional[str] = None) -> bool:
    q = db.query(Breeding).filter(
        Breeding.cage_id == cage_id,
        Breeding.status.in_(["paired", "incubating"]),
        Breeding.separation_date.is_(None),
    )
    if exclude_breeding_id:
        q = q.filter(Breeding.id != exclude_breeding_id)
    return db.query(q.exists()).scalar()


def _get_owned_breeding(db: Session, breeding_id: str, user_id: str) -> Breeding:
    b = db.query(Breeding).filter(Breeding.id == breeding_id, Breeding.user_id == user_id).first()
    if not b:
        raise HTTPException(404, "Breeding not found")
    return b


def _get_owned_clutch(db: Session, clutch_id: str, user_id: str) -> Clutch:
    clutch = (
        db.query(Clutch)
        .join(Breeding, Clutch.breeding_id == Breeding.id)
        .filter(Clutch.id == clutch_id, Breeding.user_id == user_id)
        .first()
    )
    if not clutch:
        raise HTTPException(404, "Postura não encontrada")
    return clutch


def _get_owned_offspring(db: Session, offspring_id: str, user_id: str) -> Offspring:
    offspring = (
        db.query(Offspring)
        .join(Clutch, Offspring.clutch_id == Clutch.id)
        .join(Breeding, Clutch.breeding_id == Breeding.id)
        .filter(Offspring.id == offspring_id, Breeding.user_id == user_id)
        .first()
    )
    if not offspring:
        raise HTTPException(404, "Filhote não encontrado")
    return offspring


@router.get("", response_model=List[BreedingOut])
def list_breedings(db: Session = Depends(get_db), current_user: User = Depends(get_current_user)):
    return db.query(Breeding).filter(Breeding.user_id == current_user.id).all()


@router.post("", response_model=BreedingOut, status_code=201)
def create_breeding(
    body: BreedingCreate,
    db: Session = Depends(get_db),
    current_user: User = Depends(get_current_user),
):
    male = db.query(Bird).filter(Bird.id == body.male_id, Bird.user_id == current_user.id).first()
    female = db.query(Bird).filter(Bird.id == body.female_id, Bird.user_id == current_user.id).first()
    if not male or not female:
        raise HTTPException(404, "Ave não encontrada ou não pertence a este usuário")
    if male.sex != "M":
        raise HTTPException(400, "A ave selecionada como macho não está marcada como sexo M")
    if female.sex != "F":
        raise HTTPException(400, "A ave selecionada como fêmea não está marcada como sexo F")

    if body.cage_id:
        cage = db.query(Cage).filter(Cage.id == body.cage_id, Cage.user_id == current_user.id).first()
        if not cage:
            raise HTTPException(404, "Gaiola não encontrada")
        if _cage_is_occupied(db, body.cage_id):
            raise HTTPException(409, "Esta gaiola já está em uso por outro casal ativo")

    breeding = Breeding(user_id=current_user.id, **body.model_dump())
    db.add(breeding)
    db.commit()
    db.refresh(breeding)
    return breeding


@router.get("/{breeding_id}", response_model=BreedingOut)
def get_breeding(breeding_id: str, db: Session = Depends(get_db), current_user: User = Depends(get_current_user)):
    return _get_owned_breeding(db, breeding_id, current_user.id)


@router.put("/{breeding_id}", response_model=BreedingOut)
def update_breeding(
    breeding_id: str,
    body: BreedingUpdate,
    db: Session = Depends(get_db),
    current_user: User = Depends(get_current_user),
):
    b = _get_owned_breeding(db, breeding_id, current_user.id)
    # exclude_unset (not exclude_none): a client explicitly sending cage_id/separation_date
    # as null means "clear this field" — exclude_none would silently drop that null and
    # leave the old value in place, since it can't distinguish "sent null" from "omitted".
    data = body.model_dump(exclude_unset=True)

    if "status" in data and data["status"] not in ("paired", "incubating", "hatched", "failed", "completed"):
        raise HTTPException(400, "Status inválido")

    new_cage_id = data.get("cage_id", b.cage_id)
    new_status = data.get("status", b.status)
    new_sep = data.get("separation_date", b.separation_date)
    if new_cage_id and new_status in ("paired", "incubating") and new_sep is None:
        if new_cage_id != b.cage_id:
            cage = db.query(Cage).filter(Cage.id == new_cage_id, Cage.user_id == current_user.id).first()
            if not cage:
                raise HTTPException(404, "Gaiola não encontrada")
        if _cage_is_occupied(db, new_cage_id, exclude_breeding_id=breeding_id):
            raise HTTPException(409, "Esta gaiola já está em uso por outro casal ativo")

    for k, v in data.items():
        setattr(b, k, v)
    db.commit()
    db.refresh(b)
    return b


@router.get("/{breeding_id}/clutches", response_model=List[ClutchOut])
def list_clutches(breeding_id: str, db: Session = Depends(get_db), current_user: User = Depends(get_current_user)):
    _get_owned_breeding(db, breeding_id, current_user.id)
    return db.query(Clutch).filter(Clutch.breeding_id == breeding_id).order_by(Clutch.lay_date).all()


@router.post("/{breeding_id}/clutch", response_model=ClutchOut, status_code=201)
def add_clutch(
    breeding_id: str,
    body: ClutchCreate,
    db: Session = Depends(get_db),
    current_user: User = Depends(get_current_user),
):
    b = _get_owned_breeding(db, breeding_id, current_user.id)
    clutch = Clutch(breeding_id=breeding_id, **body.model_dump())
    db.add(clutch)
    b.status = "incubating"
    db.commit()
    db.refresh(clutch)
    return clutch


@router.put("/clutch/{clutch_id}", response_model=ClutchOut)
def update_clutch(
    clutch_id: str,
    body: ClutchUpdate,
    db: Session = Depends(get_db),
    current_user: User = Depends(get_current_user),
):
    clutch = _get_owned_clutch(db, clutch_id, current_user.id)
    for k, v in body.model_dump(exclude_unset=True).items():
        setattr(clutch, k, v)
    db.commit()
    db.refresh(clutch)
    return clutch


@router.get("/clutch/{clutch_id}/offspring", response_model=List[OffspringOut])
def list_offspring(clutch_id: str, db: Session = Depends(get_db), current_user: User = Depends(get_current_user)):
    _get_owned_clutch(db, clutch_id, current_user.id)
    return db.query(Offspring).filter(Offspring.clutch_id == clutch_id).all()


@router.post("/clutch/{clutch_id}/offspring", response_model=OffspringOut, status_code=201)
def create_offspring(
    clutch_id: str,
    body: OffspringCreate,
    db: Session = Depends(get_db),
    current_user: User = Depends(get_current_user),
):
    _get_owned_clutch(db, clutch_id, current_user.id)
    offspring = Offspring(clutch_id=clutch_id, **body.model_dump())
    db.add(offspring)
    db.commit()
    db.refresh(offspring)
    return offspring


@router.put("/offspring/{offspring_id}", response_model=OffspringOut)
def update_offspring(
    offspring_id: str,
    body: OffspringUpdate,
    db: Session = Depends(get_db),
    current_user: User = Depends(get_current_user),
):
    offspring = _get_owned_offspring(db, offspring_id, current_user.id)
    for k, v in body.model_dump(exclude_unset=True).items():
        setattr(offspring, k, v)
    db.commit()
    db.refresh(offspring)
    return offspring


@router.delete("/offspring/{offspring_id}", status_code=204)
def delete_offspring(
    offspring_id: str,
    db: Session = Depends(get_db),
    current_user: User = Depends(get_current_user),
):
    offspring = _get_owned_offspring(db, offspring_id, current_user.id)
    if offspring.bird_id:
        raise HTTPException(409, "Não é possível excluir um filhote já promovido a ave do plantel")
    db.delete(offspring)
    db.commit()


@router.post("/offspring/{offspring_id}/promote", response_model=BirdOut, status_code=201)
def promote_offspring(
    offspring_id: str,
    body: OffspringPromote,
    db: Session = Depends(get_db),
    current_user: User = Depends(get_current_user),
):
    offspring = _get_owned_offspring(db, offspring_id, current_user.id)
    if offspring.bird_id:
        raise HTTPException(409, "Este filhote já foi promovido a ave do plantel")

    breeding = offspring.clutch.breeding

    from app.models.user import Plan
    plan = db.query(Plan).filter(Plan.id == current_user.plan_id).first()
    if plan:
        count = db.query(Bird).filter(Bird.user_id == current_user.id, Bird.status == "active").count()
        if count >= plan.max_birds:
            raise HTTPException(status_code=402, detail=f"Bird limit reached for your plan ({plan.max_birds})")

    bird = Bird(
        user_id=current_user.id,
        name=body.name,
        sex=body.sex,
        species_id=body.species_id or breeding.male.species_id,
        breed_id=body.breed_id,
        color=body.color,
        father_id=breeding.male_id,
        mother_id=breeding.female_id,
        birth_date=offspring.hatch_date,
        weight_grams=offspring.hatch_weight_g,
        ring_number=offspring.ring_number,
        acquisition_type="born",
        status="active",
    )
    db.add(bird)
    db.flush()
    offspring.bird_id = bird.id
    db.commit()
    db.refresh(bird)
    return bird


@router.get("/stats/summary")
def breeding_stats(db: Session = Depends(get_db), current_user: User = Depends(get_current_user)):
    from app.services.zootechnical import get_breeding_stats
    return get_breeding_stats(db, current_user.id)
