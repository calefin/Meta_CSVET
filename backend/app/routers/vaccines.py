from fastapi import APIRouter, Depends, HTTPException
from sqlalchemy.orm import Session
from typing import List, Optional
from datetime import datetime, timedelta
from pydantic import BaseModel
import json
import hashlib
from app.dependencies import get_db, get_current_user
from app.models.medication import VaccineProduct, VaccinationRecord, VaccinationLot
from app.models.bird import Bird
from app.models.user import User

router = APIRouter(prefix="/api/vaccines", tags=["vaccines"])


class VaccineRecordCreate(BaseModel):
    bird_id: str
    vaccine_product_id: str
    application_date: datetime
    dose_number: int = 1
    dose_ml_applied: Optional[float] = None
    route_used: Optional[str] = None
    applied_by_vet_id: Optional[str] = None
    reaction_observed: bool = False
    reaction_description: Optional[str] = None
    notes: Optional[str] = None
    applied_by_name: Optional[str] = None
    applied_by_crmv_state: Optional[str] = None
    applied_by_crmv_number: Optional[str] = None
    lot_number_manual: Optional[str] = None


class VaccinationLotCreate(BaseModel):
    name: str
    vaccine_product_id: str
    application_date: datetime
    applied_by_vet_id: Optional[str] = None
    protocol_description: Optional[str] = None
    selection_type: str = "all"
    species_filter_id: Optional[str] = None
    location_filter: Optional[str] = None
    bird_ids: Optional[List[str]] = None
    notes: Optional[str] = None


@router.get("/products", response_model=List[dict])
def list_products(db: Session = Depends(get_db), _=Depends(get_current_user)):
    products = db.query(VaccineProduct).all()
    return [{"id": p.id, "name_pt": p.name_pt, "name_en": p.name_en, "route": p.route, "interval_days": p.interval_days} for p in products]


@router.get("/birds/{bird_id}/records")
def list_records(bird_id: str, db: Session = Depends(get_db), current_user: User = Depends(get_current_user)):
    bird = db.query(Bird).filter(Bird.id == bird_id, Bird.user_id == current_user.id).first()
    if not bird:
        raise HTTPException(404, "Bird not found")
    records = db.query(VaccinationRecord).filter(VaccinationRecord.bird_id == bird_id).order_by(VaccinationRecord.application_date.desc()).all()
    result = []
    for r in records:
        result.append({
            "id": r.id,
            "bird_id": r.bird_id,
            "user_id": r.user_id,
            "vaccine_product_id": r.vaccine_product_id,
            "application_date": r.application_date,
            "dose_number": r.dose_number,
            "dose_ml_applied": r.dose_ml_applied,
            "route_used": r.route_used,
            "applied_by_vet_id": r.applied_by_vet_id,
            "reaction_observed": r.reaction_observed,
            "reaction_description": r.reaction_description,
            "notes": r.notes,
            "applied_by_name": r.applied_by_name,
            "applied_by_crmv_state": r.applied_by_crmv_state,
            "applied_by_crmv_number": r.applied_by_crmv_number,
            "lot_number_manual": r.lot_number_manual,
            "record_hash": r.record_hash,
            "next_dose_date": r.next_dose_date,
            "created_at": r.created_at,
        })
    return result


@router.post("/records", status_code=201)
def create_record(body: VaccineRecordCreate, db: Session = Depends(get_db), current_user: User = Depends(get_current_user)):
    product = db.query(VaccineProduct).filter(VaccineProduct.id == body.vaccine_product_id).first()
    next_dose = None
    if product and product.interval_days:
        next_dose = body.application_date + timedelta(days=product.interval_days)

    # Compute SHA256 hash for immutability
    hash_data = (
        f"{body.bird_id}|{body.vaccine_product_id}|{body.application_date.isoformat()}"
        f"|{body.applied_by_name or ''}|{body.applied_by_crmv_state or ''}"
        f"|{body.applied_by_crmv_number or ''}|{body.lot_number_manual or ''}"
        f"|{body.dose_ml_applied or ''}"
    )
    record_hash = hashlib.sha256(hash_data.encode()).hexdigest()

    record = VaccinationRecord(
        user_id=current_user.id,
        next_dose_date=next_dose,
        record_hash=record_hash,
        **body.model_dump()
    )
    db.add(record)
    db.commit()
    db.refresh(record)
    return {
        "id": record.id,
        "bird_id": record.bird_id,
        "vaccine_product_id": record.vaccine_product_id,
        "application_date": record.application_date,
        "record_hash": record.record_hash,
        "applied_by_name": record.applied_by_name,
        "lot_number_manual": record.lot_number_manual,
        "created_at": record.created_at,
    }


@router.delete("/records/{record_id}", status_code=204)
def delete_record(record_id: str, db: Session = Depends(get_db), current_user: User = Depends(get_current_user)):
    record = db.query(VaccinationRecord).filter(VaccinationRecord.id == record_id, VaccinationRecord.user_id == current_user.id).first()
    if not record:
        raise HTTPException(404, "Registro não encontrado")
    db.delete(record)
    db.commit()


@router.post("/lots", status_code=201)
def create_lot(body: VaccinationLotCreate, db: Session = Depends(get_db), current_user: User = Depends(get_current_user)):
    # Determine which birds to vaccinate
    from app.models.bird import Species
    bird_ids = []
    if body.selection_type == "all":
        birds = db.query(Bird).filter(Bird.user_id == current_user.id, Bird.status == "active").all()
        bird_ids = [b.id for b in birds]
    elif body.selection_type == "species" and body.species_filter_id:
        birds = db.query(Bird).filter(Bird.user_id == current_user.id, Bird.species_id == body.species_filter_id, Bird.status == "active").all()
        bird_ids = [b.id for b in birds]
    elif body.selection_type == "manual" and body.bird_ids:
        bird_ids = body.bird_ids

    lot_data = body.model_dump()
    lot_data["bird_ids"] = json.dumps(bird_ids)
    lot_data["total_birds_targeted"] = len(bird_ids)
    lot = VaccinationLot(user_id=current_user.id, **lot_data)
    db.add(lot)
    db.flush()

    # Create individual records
    product = db.query(VaccineProduct).filter(VaccineProduct.id == body.vaccine_product_id).first()
    applied = 0
    for bid in bird_ids:
        next_dose = None
        if product and product.interval_days:
            next_dose = body.application_date + timedelta(days=product.interval_days)
        rec = VaccinationRecord(
            bird_id=bid,
            user_id=current_user.id,
            vaccine_product_id=body.vaccine_product_id,
            application_date=body.application_date,
            applied_by_vet_id=body.applied_by_vet_id,
            lot_treatment_id=lot.id,
            next_dose_date=next_dose,
        )
        db.add(rec)
        applied += 1

    lot.total_birds_applied = applied
    db.commit()
    db.refresh(lot)
    return lot


@router.get("/schedule")
def upcoming_vaccines(days: int = 30, db: Session = Depends(get_db), current_user: User = Depends(get_current_user)):
    cutoff = datetime.utcnow() + timedelta(days=days)
    records = (
        db.query(VaccinationRecord)
        .join(Bird, VaccinationRecord.bird_id == Bird.id)
        .filter(Bird.user_id == current_user.id)
        .filter(VaccinationRecord.next_dose_date.isnot(None))
        .filter(VaccinationRecord.next_dose_date <= cutoff)
        .order_by(VaccinationRecord.next_dose_date)
        .all()
    )
    return records
