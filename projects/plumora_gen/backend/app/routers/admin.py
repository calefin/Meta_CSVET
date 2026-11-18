from fastapi import APIRouter, Depends, HTTPException
from sqlalchemy.orm import Session
from app.dependencies import get_db, require_role
from app.models.user import User, Subscription, Plan
from app.models.bird import Bird

router = APIRouter(prefix="/api/admin", tags=["admin"])


@router.get("/users")
def list_users(skip: int = 0, limit: int = 50, db: Session = Depends(get_db), _=Depends(require_role("admin"))):
    return db.query(User).offset(skip).limit(limit).all()


@router.put("/users/{user_id}/status")
def toggle_user(user_id: str, is_active: bool, db: Session = Depends(get_db), _=Depends(require_role("admin"))):
    user = db.query(User).filter(User.id == user_id).first()
    if not user:
        raise HTTPException(404, "User not found")
    user.is_active = is_active
    db.commit()
    return {"id": user_id, "is_active": is_active}


@router.get("/subscriptions")
def list_subscriptions(db: Session = Depends(get_db), _=Depends(require_role("admin"))):
    return db.query(Subscription).all()


@router.get("/dashboard")
def dashboard(db: Session = Depends(get_db), _=Depends(require_role("admin"))):
    total_users = db.query(User).count()
    total_birds = db.query(Bird).count()
    active_subs = db.query(Subscription).filter(Subscription.status == "active").count()
    return {
        "total_users": total_users,
        "total_birds": total_birds,
        "active_subscriptions": active_subs,
    }
