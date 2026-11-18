from fastapi import APIRouter, Depends, HTTPException
from sqlalchemy.orm import Session
from typing import List
from app.dependencies import get_db, get_current_user
from app.schemas.health import HealthRecordCreate, HealthRecordOut, VetConsultCreate, VetConsultOut, VetRespondRequest
from app.models.health import HealthRecord, VetConsultRequest
from app.models.bird import Bird
from app.models.user import User

router = APIRouter(prefix="/api/birds", tags=["health"])


@router.get("/vet-consults/mine", response_model=List[VetConsultOut])
def list_my_vet_consults(db: Session = Depends(get_db), current_user: User = Depends(get_current_user)):
    return (
        db.query(VetConsultRequest)
        .filter(VetConsultRequest.requester_user_id == current_user.id)
        .order_by(VetConsultRequest.created_at.desc())
        .all()
    )


@router.get("/{bird_id}/health", response_model=List[HealthRecordOut])
def list_health(bird_id: str, db: Session = Depends(get_db), current_user: User = Depends(get_current_user)):
    bird = db.query(Bird).filter(Bird.id == bird_id, Bird.user_id == current_user.id).first()
    if not bird:
        raise HTTPException(404, "Bird not found")
    return db.query(HealthRecord).filter(HealthRecord.bird_id == bird_id).order_by(HealthRecord.date.desc()).all()


@router.post("/{bird_id}/health", response_model=HealthRecordOut, status_code=201)
def create_health(
    bird_id: str,
    body: HealthRecordCreate,
    db: Session = Depends(get_db),
    current_user: User = Depends(get_current_user),
):
    bird = db.query(Bird).filter(Bird.id == bird_id, Bird.user_id == current_user.id).first()
    if not bird:
        raise HTTPException(404, "Bird not found")
    record = HealthRecord(bird_id=bird_id, user_id=current_user.id, **body.model_dump())
    db.add(record)
    db.commit()
    db.refresh(record)
    return record


@router.post("/{bird_id}/health/{record_id}/request-vet", response_model=VetConsultOut, status_code=201)
def request_vet(
    bird_id: str,
    record_id: str,
    body: VetConsultCreate,
    db: Session = Depends(get_db),
    current_user: User = Depends(get_current_user),
):
    bird = db.query(Bird).filter(Bird.id == bird_id, Bird.user_id == current_user.id).first()
    if not bird:
        raise HTTPException(404, "Bird not found")
    record = db.query(HealthRecord).filter(HealthRecord.id == record_id).first()
    if not record:
        raise HTTPException(404, "Health record not found")
    consult = VetConsultRequest(
        health_record_id=record_id,
        bird_id=bird_id,
        requester_user_id=current_user.id,
        **body.model_dump(),
    )
    db.add(consult)
    db.commit()
    db.refresh(consult)

    # Fire async email notification
    try:
        from app.tasks.notification_tasks import notify_vet_consult_email
        notify_vet_consult_email.delay(consult.id)
    except Exception:
        pass

    return consult
