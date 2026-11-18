from datetime import datetime
from typing import Generator
from fastapi import Depends, HTTPException, status
from fastapi.security import OAuth2PasswordBearer
from sqlalchemy.orm import Session
from jose import JWTError, jwt
from app.database import SessionLocal
from app.config import get_settings

settings = get_settings()
oauth2_scheme = OAuth2PasswordBearer(tokenUrl="/api/auth/login")


def get_db() -> Generator:
    db = SessionLocal()
    try:
        yield db
    finally:
        db.close()


def get_current_user(
    token: str = Depends(oauth2_scheme),
    db: Session = Depends(get_db),
):
    from app.models.user import User

    credentials_exception = HTTPException(
        status_code=status.HTTP_401_UNAUTHORIZED,
        detail="Could not validate credentials",
        headers={"WWW-Authenticate": "Bearer"},
    )
    try:
        payload = jwt.decode(token, settings.secret_key, algorithms=[settings.algorithm])
        user_id: str = payload.get("sub")
        if user_id is None:
            raise credentials_exception
    except JWTError:
        raise credentials_exception

    user = db.query(User).filter(User.id == user_id, User.is_active == True).first()
    if user is None:
        raise credentials_exception
    return user


def require_role(*roles: str):
    def _checker(current_user=Depends(get_current_user)):
        if current_user.role not in roles:
            raise HTTPException(status_code=403, detail="Insufficient permissions")
        return current_user
    return _checker


def check_subscription_active(
    current_user=Depends(get_current_user),
    db: Session = Depends(get_db),
):
    """Blocks access to the app once a breeder's paid plan has lapsed (no active,
    non-expired Subscription). Free plan (or no plan at all) never blocks — this rule
    is only about paid tiers. vet/admin accounts are never gated by a breeder's billing
    status. Data is never touched here — this only blocks access, never deletes anything."""
    from app.models.user import Plan, Subscription

    if current_user.role != "breeder":
        return current_user

    plan = db.query(Plan).filter(Plan.id == current_user.plan_id).first() if current_user.plan_id else None
    if not plan or plan.name == "free":
        return current_user

    active = (
        db.query(Subscription)
        .filter(
            Subscription.user_id == current_user.id,
            Subscription.status == "active",
            Subscription.current_period_end >= datetime.utcnow(),
        )
        .first()
    )
    if not active:
        raise HTTPException(
            status_code=402,
            detail="Assinatura inativa ou vencida. Regularize o pagamento para continuar usando a plataforma.",
        )
    return current_user
