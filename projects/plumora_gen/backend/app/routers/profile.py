from fastapi import APIRouter, Depends, HTTPException
from sqlalchemy.orm import Session
from typing import Optional
from datetime import datetime
from pydantic import BaseModel, field_validator
from app.config import get_settings
from app.dependencies import get_db, get_current_user
from app.models.user import User, Plan, Subscription
from app.utils.cpf import validate_cpf
from app.utils.security import hash_password, verify_password

router = APIRouter(prefix="/api/profile", tags=["profile"])
settings = get_settings()


class ProfileUpdate(BaseModel):
    name: Optional[str] = None
    full_name: Optional[str] = None
    phone: Optional[str] = None
    cpf_cnpj: Optional[str] = None
    profile_photo_url: Optional[str] = None
    address_street: Optional[str] = None
    address_number: Optional[str] = None
    address_complement: Optional[str] = None
    address_city: Optional[str] = None
    address_state: Optional[str] = None
    address_zip: Optional[str] = None
    association_name: Optional[str] = None
    association_number: Optional[str] = None
    crmv_state: Optional[str] = None
    crmv_number: Optional[str] = None


class ChangePasswordRequest(BaseModel):
    current_password: str
    new_password: str

    @field_validator("new_password")
    @classmethod
    def validate_strength(cls, v: str) -> str:
        if len(v) < 8:
            raise ValueError("Nova senha deve ter no mínimo 8 caracteres")
        return v


class SubscribeRequest(BaseModel):
    plan_id: str
    terms_accepted: bool
    billing_period: str = "monthly"  # monthly|yearly


@router.get("")
def get_profile(db: Session = Depends(get_db), current_user: User = Depends(get_current_user)):
    plan = db.query(Plan).filter(Plan.id == current_user.plan_id).first() if current_user.plan_id else None
    return {
        "id": current_user.id,
        "email": current_user.email,
        "name": current_user.name,
        "full_name": current_user.full_name,
        "phone": current_user.phone,
        "role": current_user.role,
        "cpf_cnpj": current_user.cpf_cnpj,
        "locale": current_user.locale,
        "profile_photo_url": current_user.profile_photo_url,
        "address_street": current_user.address_street,
        "address_number": current_user.address_number,
        "address_complement": current_user.address_complement,
        "address_city": current_user.address_city,
        "address_state": current_user.address_state,
        "address_zip": current_user.address_zip,
        "association_name": current_user.association_name,
        "association_number": current_user.association_number,
        "crmv_state": current_user.crmv_state,
        "crmv_number": current_user.crmv_number,
        "terms_accepted_at": current_user.terms_accepted_at,
        "plan_id": current_user.plan_id,
        "plan": {"id": plan.id, "name": plan.name, "max_birds": plan.max_birds} if plan else None,
        "created_at": current_user.created_at,
    }


@router.put("")
def update_profile(body: ProfileUpdate, db: Session = Depends(get_db), current_user: User = Depends(get_current_user)):
    for field, value in body.model_dump(exclude_none=True).items():
        setattr(current_user, field, value)
    current_user.updated_at = datetime.utcnow()
    db.commit()
    db.refresh(current_user)
    return {"ok": True, "message": "Perfil atualizado com sucesso"}


@router.put("/password")
def change_password(body: ChangePasswordRequest, db: Session = Depends(get_db), current_user: User = Depends(get_current_user)):
    if not verify_password(body.current_password, current_user.password_hash):
        raise HTTPException(400, "Senha atual incorreta")
    current_user.password_hash = hash_password(body.new_password)
    current_user.updated_at = datetime.utcnow()
    db.commit()
    return {"ok": True, "message": "Senha alterada com sucesso"}


@router.post("/subscribe", status_code=201)
def subscribe(body: SubscribeRequest, db: Session = Depends(get_db), current_user: User = Depends(get_current_user)):
    # Validate required fields
    missing = []
    if not current_user.cpf_cnpj:
        missing.append("cpf_cnpj")
    if not current_user.full_name:
        missing.append("full_name")
    if not current_user.address_street:
        missing.append("address_street")
    if not current_user.address_city:
        missing.append("address_city")
    if not current_user.address_state:
        missing.append("address_state")
    if not current_user.address_zip:
        missing.append("address_zip")
    if missing:
        raise HTTPException(400, f"Campos obrigatórios faltando: {', '.join(missing)}")

    if not body.terms_accepted:
        raise HTTPException(400, "Você deve aceitar os termos de serviço")

    # Validate CPF (only if it looks like a CPF — 11 digits)
    from re import sub
    cpf_digits = sub(r'\D', '', current_user.cpf_cnpj)
    if len(cpf_digits) == 11:
        if not validate_cpf(current_user.cpf_cnpj):
            raise HTTPException(400, "CPF inválido")

    # Check plan exists
    plan = db.query(Plan).filter(Plan.id == body.plan_id).first()
    if not plan:
        raise HTTPException(404, "Plano não encontrado")

    current_user.terms_accepted_at = datetime.utcnow()

    if plan.name == "free":
        # Downgrade/reset para o plano gratuito — não é uma compra, ativa na hora.
        sub = Subscription(
            user_id=current_user.id,
            plan_id=body.plan_id,
            status="active",
            billing_period=body.billing_period,
            current_period_end=None,
            start_date=datetime.utcnow(),
        )
        db.add(sub)
        current_user.plan_id = body.plan_id
        db.commit()
        db.refresh(sub)
        return {"ok": True, "subscription_id": sub.id, "plan": plan.name}

    # Plano pago: cria a cobrança real no Mercado Pago. O plano só é ativado quando o
    # webhook (POST /api/billing/webhook) confirmar o pagamento — ver check_subscription_active.
    sub = Subscription(
        user_id=current_user.id,
        plan_id=body.plan_id,
        status="pending",
        billing_period=body.billing_period,
        start_date=datetime.utcnow(),
    )
    db.add(sub)
    db.commit()
    db.refresh(sub)

    from app.services.mercadopago_client import create_preference, create_preapproval

    try:
        if body.billing_period == "yearly":
            result = create_preference(
                current_user, plan,
                success_url=f"{settings.frontend_url}/app/profile?billing=success",
                failure_url=f"{settings.frontend_url}/app/profile?billing=failure",
                pending_url=f"{settings.frontend_url}/app/profile?billing=pending",
            )
        else:
            result = create_preapproval(
                current_user, plan,
                back_url=f"{settings.frontend_url}/app/profile?billing=success",
            )
    except RuntimeError as e:
        raise HTTPException(502, str(e))

    return {"ok": True, "subscription_id": sub.id, "plan": plan.name, "checkout_url": result["checkout_url"]}


@router.post("/photo", status_code=501)
def upload_photo(_=Depends(get_current_user)):
    raise HTTPException(501, "Upload de foto ainda não disponível. Em breve via MinIO.")
