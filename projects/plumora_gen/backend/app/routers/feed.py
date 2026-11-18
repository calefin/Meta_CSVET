from fastapi import APIRouter, Depends, HTTPException
from sqlalchemy.orm import Session
from typing import Optional
from datetime import datetime
from pydantic import BaseModel
from app.dependencies import get_db, get_current_user
from app.models.feed import Feed, BirdFeedLog
from app.models.bird import Bird
from app.models.user import User

router = APIRouter(prefix="/api/feed", tags=["feed"])


class FeedCreate(BaseModel):
    name: str
    brand: Optional[str] = None
    type: str = "other"
    composition_notes: Optional[str] = None
    calories_per_100g: Optional[float] = None


class FeedLogCreate(BaseModel):
    bird_id: str
    feed_id: str
    amount_grams: Optional[float] = None
    date: datetime
    notes: Optional[str] = None


@router.get("/products")
def list_feeds(db: Session = Depends(get_db), _=Depends(get_current_user)):
    feeds = db.query(Feed).order_by(Feed.name).all()
    return [
        {
            "id": f.id,
            "name": f.name,
            "brand": f.brand,
            "type": f.type,
            "composition_notes": f.composition_notes,
            "calories_per_100g": f.calories_per_100g,
        }
        for f in feeds
    ]


@router.post("/products", status_code=201)
def create_feed(body: FeedCreate, db: Session = Depends(get_db), _=Depends(get_current_user)):
    feed = Feed(**body.model_dump())
    db.add(feed)
    db.commit()
    db.refresh(feed)
    return feed


@router.get("/logs")
def list_logs(db: Session = Depends(get_db), current_user: User = Depends(get_current_user)):
    logs = db.query(BirdFeedLog).filter(BirdFeedLog.recorded_by_user_id == current_user.id).order_by(BirdFeedLog.date.desc()).all()
    result = []
    for log in logs:
        bird = db.query(Bird).filter(Bird.id == log.bird_id).first()
        feed = db.query(Feed).filter(Feed.id == log.feed_id).first()
        result.append({
            "id": log.id,
            "bird_id": log.bird_id,
            "bird_name": bird.name if bird else None,
            "feed_id": log.feed_id,
            "feed_name": feed.name if feed else None,
            "date": log.date,
            "quantity_grams": log.quantity_grams,
            "notes": log.notes,
            "created_at": log.created_at,
        })
    return result


@router.post("/logs", status_code=201)
def create_log(body: FeedLogCreate, db: Session = Depends(get_db), current_user: User = Depends(get_current_user)):
    bird = db.query(Bird).filter(Bird.id == body.bird_id, Bird.user_id == current_user.id).first()
    if not bird:
        raise HTTPException(404, "Ave não encontrada")
    feed = db.query(Feed).filter(Feed.id == body.feed_id).first()
    if not feed:
        raise HTTPException(404, "Alimento não encontrado")
    log = BirdFeedLog(
        bird_id=body.bird_id,
        feed_id=body.feed_id,
        date=body.date,
        quantity_grams=body.amount_grams,
        recorded_by_user_id=current_user.id,
        notes=body.notes,
    )
    db.add(log)
    db.commit()
    db.refresh(log)
    return log


@router.delete("/logs/{log_id}", status_code=204)
def delete_log(log_id: str, db: Session = Depends(get_db), current_user: User = Depends(get_current_user)):
    log = db.query(BirdFeedLog).filter(BirdFeedLog.id == log_id, BirdFeedLog.recorded_by_user_id == current_user.id).first()
    if not log:
        raise HTTPException(404, "Registro não encontrado")
    db.delete(log)
    db.commit()
