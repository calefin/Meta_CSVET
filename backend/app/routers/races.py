from fastapi import APIRouter, Depends, HTTPException
from sqlalchemy.orm import Session
from typing import Optional, List
from datetime import datetime
from pydantic import BaseModel
from app.dependencies import get_db, get_current_user
from app.models.race import Race, RaceResult
from app.models.user import User

router = APIRouter(prefix="/api/races", tags=["races"])


class RaceCreate(BaseModel):
    name: str
    date: datetime
    start_location: Optional[str] = None
    start_lat: Optional[float] = None
    start_lon: Optional[float] = None
    distance_km: Optional[float] = None
    wind_speed_kmh: Optional[float] = None
    wind_direction: Optional[str] = None
    weather_conditions: Optional[str] = None
    organizer: Optional[str] = None
    category: Optional[str] = None
    notes: Optional[str] = None


class RaceResultCreate(BaseModel):
    bird_id: str
    release_time: Optional[datetime] = None
    arrival_time: Optional[datetime] = None
    loft_location: Optional[str] = None
    loft_lat: Optional[float] = None
    loft_lon: Optional[float] = None
    actual_distance_km: Optional[float] = None
    position_overall: Optional[int] = None
    position_category: Optional[int] = None
    disqualified: bool = False
    disqualification_reason: Optional[str] = None
    notes: Optional[str] = None


@router.get("")
def list_races(db: Session = Depends(get_db), _=Depends(get_current_user)):
    return db.query(Race).order_by(Race.date.desc()).all()


@router.post("", status_code=201)
def create_race(body: RaceCreate, db: Session = Depends(get_db), _=Depends(get_current_user)):
    race = Race(**body.model_dump())
    db.add(race)
    db.commit()
    db.refresh(race)
    return race


@router.get("/{race_id}")
def get_race(race_id: str, db: Session = Depends(get_db), _=Depends(get_current_user)):
    race = db.query(Race).filter(Race.id == race_id).first()
    if not race:
        raise HTTPException(404, "Race not found")
    return race


@router.post("/{race_id}/results", status_code=201)
def add_result(
    race_id: str,
    body: RaceResultCreate,
    db: Session = Depends(get_db),
    current_user: User = Depends(get_current_user),
):
    race = db.query(Race).filter(Race.id == race_id).first()
    if not race:
        raise HTTPException(404, "Race not found")
    data = body.model_dump()
    # Calculate velocity and flight time
    if body.release_time and body.arrival_time:
        delta = (body.arrival_time - body.release_time).total_seconds()
        data["flight_time_seconds"] = int(delta)
        dist = body.actual_distance_km or race.distance_km
        if dist and delta > 0:
            data["velocity_mpm"] = round((dist * 1000) / (delta / 60), 2)
    result = RaceResult(race_id=race_id, user_id=current_user.id, **data)
    db.add(result)
    db.commit()
    db.refresh(result)
    return result
