from fastapi import APIRouter, Depends, HTTPException
from sqlalchemy.orm import Session
from typing import Optional, List
from datetime import datetime
from pydantic import BaseModel
from app.dependencies import get_db, get_current_user
from app.models.medication import Medication, MedicationRecord
from app.models.bird import Bird
from app.models.user import User

router = APIRouter(prefix="/api/medications", tags=["medications"])


class MedicationCreate(BaseModel):
    name: str
    active_ingredient: Optional[str] = None
    manufacturer: Optional[str] = None
    type: str = "other"
    presentation: Optional[str] = None
    requires_prescription: bool = False


class MedicationRecordCreate(BaseModel):
    bird_id: Optional[str] = None
    medication_id: str
    dose: Optional[float] = None
    unit: Optional[str] = None
    route: Optional[str] = None
    start_date: datetime
    end_date: Optional[datetime] = None
    interval_hours: Optional[int] = None
    notes: Optional[str] = None
    prescribed_by_name: Optional[str] = None


@router.get("")
def list_medications(db: Session = Depends(get_db), _=Depends(get_current_user)):
    meds = db.query(Medication).order_by(Medication.name).all()
    return [
        {
            "id": m.id,
            "name": m.name,
            "active_ingredient": m.active_ingredient,
            "manufacturer": m.manufacturer,
            "type": m.type,
            "presentation": m.presentation,
            "requires_prescription": m.requires_prescription,
        }
        for m in meds
    ]


@router.post("", status_code=201)
def create_medication(body: MedicationCreate, db: Session = Depends(get_db), current_user: User = Depends(get_current_user)):
    if current_user.role not in ("admin", "vet"):
        raise HTTPException(403, "Apenas administradores e veterinários podem criar medicamentos")
    med = Medication(**body.model_dump())
    db.add(med)
    db.commit()
    db.refresh(med)
    return med


@router.get("/records")
def list_records(db: Session = Depends(get_db), current_user: User = Depends(get_current_user)):
    records = db.query(MedicationRecord).filter(MedicationRecord.user_id == current_user.id).order_by(MedicationRecord.start_date.desc()).all()
    result = []
    for r in records:
        bird = db.query(Bird).filter(Bird.id == r.bird_id).first() if r.bird_id else None
        med = db.query(Medication).filter(Medication.id == r.medication_id).first()
        result.append({
            "id": r.id,
            "bird_id": r.bird_id,
            "bird_name": bird.name if bird else None,
            "medication_id": r.medication_id,
            "medication_name": med.name if med else None,
            "dose": r.dose,
            "unit": r.unit,
            "route": r.route,
            "start_date": r.start_date,
            "end_date": r.end_date,
            "interval_hours": r.interval_hours,
            "notes": r.notes,
            "created_at": r.created_at,
        })
    return result


@router.post("/records", status_code=201)
def create_record(body: MedicationRecordCreate, db: Session = Depends(get_db), current_user: User = Depends(get_current_user)):
    med = db.query(Medication).filter(Medication.id == body.medication_id).first()
    if not med:
        raise HTTPException(404, "Medicamento não encontrado")
    data = body.model_dump()
    data.pop("prescribed_by_name", None)
    record = MedicationRecord(user_id=current_user.id, **data)
    db.add(record)
    db.commit()
    db.refresh(record)
    return record


@router.delete("/records/{record_id}", status_code=204)
def delete_record(record_id: str, db: Session = Depends(get_db), current_user: User = Depends(get_current_user)):
    record = db.query(MedicationRecord).filter(MedicationRecord.id == record_id, MedicationRecord.user_id == current_user.id).first()
    if not record:
        raise HTTPException(404, "Registro não encontrado")
    db.delete(record)
    db.commit()
