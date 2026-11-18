from fastapi import APIRouter, Depends, HTTPException
from sqlalchemy.orm import Session
from typing import List
from app.dependencies import get_db, get_current_user
from app.schemas.breeding import CageCreate, CageUpdate, CageOut
from app.models.breeding import Cage
from app.models.user import User

router = APIRouter(prefix="/api/cages", tags=["cages"])


@router.get("", response_model=List[CageOut])
def list_cages(
    active_only: bool = False,
    db: Session = Depends(get_db),
    current_user: User = Depends(get_current_user),
):
    q = db.query(Cage).filter(Cage.user_id == current_user.id)
    if active_only:
        q = q.filter(Cage.is_active == True)
    return q.order_by(Cage.name).all()


@router.post("", response_model=CageOut, status_code=201)
def create_cage(
    body: CageCreate,
    db: Session = Depends(get_db),
    current_user: User = Depends(get_current_user),
):
    cage = Cage(user_id=current_user.id, **body.model_dump())
    db.add(cage)
    db.commit()
    db.refresh(cage)
    return cage


@router.put("/{cage_id}", response_model=CageOut)
def update_cage(
    cage_id: str,
    body: CageUpdate,
    db: Session = Depends(get_db),
    current_user: User = Depends(get_current_user),
):
    cage = db.query(Cage).filter(Cage.id == cage_id, Cage.user_id == current_user.id).first()
    if not cage:
        raise HTTPException(404, "Gaiola não encontrada")
    for k, v in body.model_dump(exclude_none=True).items():
        setattr(cage, k, v)
    db.commit()
    db.refresh(cage)
    return cage


@router.delete("/{cage_id}", status_code=204)
def delete_cage(
    cage_id: str,
    db: Session = Depends(get_db),
    current_user: User = Depends(get_current_user),
):
    cage = db.query(Cage).filter(Cage.id == cage_id, Cage.user_id == current_user.id).first()
    if not cage:
        raise HTTPException(404, "Gaiola não encontrada")
    cage.is_active = False
    db.commit()
