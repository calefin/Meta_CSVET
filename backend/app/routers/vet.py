from fastapi import APIRouter, Depends, HTTPException
from sqlalchemy.orm import Session
from typing import List
from datetime import datetime
from app.dependencies import get_db, require_role
from app.schemas.health import VetConsultOut, VetRespondRequest
from app.models.health import VetConsultRequest
from app.models.user import User

router = APIRouter(prefix="/api/vet", tags=["vet"])


@router.get("/queue", response_model=List[VetConsultOut])
def get_queue(
    status: str = "pending",
    db: Session = Depends(get_db),
    current_user: User = Depends(require_role("vet", "admin")),
):
    q = db.query(VetConsultRequest)
    if status != "all":
        q = q.filter(VetConsultRequest.status == status)
    return q.order_by(VetConsultRequest.created_at.desc()).all()


@router.get("/consults/{consult_id}", response_model=VetConsultOut)
def get_consult(
    consult_id: str,
    db: Session = Depends(get_db),
    current_user: User = Depends(require_role("vet", "admin")),
):
    consult = db.query(VetConsultRequest).filter(VetConsultRequest.id == consult_id).first()
    if not consult:
        raise HTTPException(404, "Consult not found")
    return consult


@router.post("/consults/{consult_id}/respond", response_model=VetConsultOut)
def respond_consult(
    consult_id: str,
    body: VetRespondRequest,
    db: Session = Depends(get_db),
    current_user: User = Depends(require_role("vet", "admin")),
):
    consult = db.query(VetConsultRequest).filter(VetConsultRequest.id == consult_id).first()
    if not consult:
        raise HTTPException(404, "Consult not found")
    consult.response_text = body.response_text
    consult.status = body.status
    consult.response_date = datetime.utcnow()
    consult.assigned_vet_id = current_user.id
    db.commit()
    db.refresh(consult)
    return consult


@router.get("/patients")
def get_patients(
    db: Session = Depends(get_db),
    current_user: User = Depends(require_role("vet", "admin")),
):
    from app.models.bird import Bird
    from sqlalchemy import distinct
    bird_ids = (
        db.query(distinct(VetConsultRequest.bird_id))
        .filter(VetConsultRequest.assigned_vet_id == current_user.id)
        .all()
    )
    ids = [r[0] for r in bird_ids]
    return db.query(Bird).filter(Bird.id.in_(ids)).all()
