import hashlib
import hmac
import json
from datetime import datetime, timedelta

from fastapi import APIRouter, Depends, HTTPException, Request
from sqlalchemy.orm import Session

from app.config import get_settings
from app.dependencies import get_db, get_current_user
from app.models.billing import MPWebhookEvent
from app.models.user import User, Subscription, Plan

router = APIRouter(prefix="/api/billing", tags=["billing"])
settings = get_settings()

PERIOD_DAYS = {"monthly": 30, "yearly": 365}


@router.get("/plans")
def list_plans(db: Session = Depends(get_db)):
    return db.query(Plan).all()


@router.post("/cancel")
def cancel_subscription(db: Session = Depends(get_db), current_user: User = Depends(get_current_user)):
    # Procura por uma assinatura recorrente com cobrança automática ainda ligada — não por
    # status="active", já que "ativo" continua valendo até current_period_end mesmo depois
    # de cancelado (é exatamente esse o ponto: cancelar não revoga o acesso na hora).
    sub = (
        db.query(Subscription)
        .filter(Subscription.user_id == current_user.id, Subscription.mp_preapproval_id.isnot(None))
        .order_by(Subscription.created_at.desc())
        .first()
    )
    if not sub:
        raise HTTPException(404, "No active subscription")

    from app.services.mercadopago_client import cancel_preapproval
    try:
        cancel_preapproval(sub.mp_preapproval_id)
    except RuntimeError as e:
        raise HTTPException(502, str(e))
    sub.mp_preapproval_id = None
    db.commit()

    # Cancelar só impede a próxima cobrança automática — o acesso continua válido até
    # current_period_end (já pago). Não mexemos em status/current_period_end aqui.
    return {"message": "Cobrança recorrente cancelada. Seu acesso continua ativo até o fim do período já pago."}


# ---------------------------------------------------------------------------
# Webhook do Mercado Pago
# ---------------------------------------------------------------------------

def _verify_webhook_signature(request: Request, body: dict) -> bool:
    secret = settings.mp_webhook_secret
    if not secret:
        return True  # sem secret configurado (dev) — não bloqueia, mas não deveria ir assim pra produção
    signature = request.headers.get("x-signature")
    request_id = request.headers.get("x-request-id", "")
    if not signature:
        return False
    parts = dict(p.split("=", 1) for p in signature.split(",") if "=" in p)
    ts = parts.get("ts")
    v1 = parts.get("v1")
    if not ts or not v1:
        return False
    data_id = str((body.get("data") or {}).get("id") or "")
    manifest = f"id:{data_id};request-id:{request_id};ts:{ts};"
    expected = hmac.new(secret.encode(), manifest.encode(), hashlib.sha256).hexdigest()
    try:
        return hmac.compare_digest(v1, expected)
    except Exception:
        return False


def _parse_external_reference(raw) -> dict | None:
    try:
        return json.loads(raw)
    except (TypeError, ValueError):
        return None


def _already_processed(db: Session, external_id: str) -> bool:
    # "approved" (payment/subscription_authorized_payment) e "authorized" (subscription_preapproval)
    # são os dois status de sucesso que ativam uma assinatura — qualquer um já contabilizado
    # para este external_id significa que esse evento já foi processado.
    return (
        db.query(MPWebhookEvent)
        .filter(MPWebhookEvent.external_id == external_id, MPWebhookEvent.status.in_(["approved", "authorized"]))
        .first()
        is not None
    )


def _log_event(db: Session, *, type: str, action: str | None = None, status: str | None = None,
                user_id: str | None = None, external_id: str | None = None, amount: float | None = None,
                plan_id: str | None = None, raw: dict | None = None):
    db.add(MPWebhookEvent(
        type=type, action=action, status=status, user_id=user_id,
        external_id=external_id, amount=amount, plan_id=plan_id, raw=raw,
    ))
    db.commit()


def _activate_subscription(db: Session, user_id: str, plan_id: str, billing_period: str,
                            mp_preapproval_id: str | None = None, mp_payment_id: str | None = None):
    days = PERIOD_DAYS.get(billing_period, 30)
    sub = (
        db.query(Subscription)
        .filter(Subscription.user_id == user_id, Subscription.plan_id == plan_id)
        .order_by(Subscription.created_at.desc())
        .first()
    )
    base = sub.current_period_end if (sub and sub.current_period_end and sub.current_period_end > datetime.utcnow()) else datetime.utcnow()
    new_period_end = base + timedelta(days=days)

    if not sub:
        sub = Subscription(user_id=user_id, plan_id=plan_id, billing_period=billing_period)
        db.add(sub)

    sub.status = "active"
    sub.billing_period = billing_period
    sub.current_period_end = new_period_end
    if mp_preapproval_id:
        sub.mp_preapproval_id = mp_preapproval_id
    if mp_payment_id:
        sub.mp_payment_id = mp_payment_id

    user = db.query(User).filter(User.id == user_id).first()
    if user:
        user.plan_id = plan_id

    db.commit()
    return new_period_end


@router.post("/webhook")
async def mp_webhook(request: Request, db: Session = Depends(get_db)):
    body = await request.json()

    if not _verify_webhook_signature(request, body):
        raise HTTPException(401, "Invalid webhook signature")

    event_type = body.get("type")
    data = body.get("data") or {}
    data_id = data.get("id")
    if not data_id:
        return {"received": True}

    from app.services.mercadopago_client import get_payment, get_preapproval

    try:
        return await _dispatch_webhook_event(db, event_type, data_id, body, get_payment, get_preapproval)
    except RuntimeError as e:
        # MP não configurado ou API indisponível — loga e devolve 200 (evita retries
        # agressivos do MP; a falha real fica visível no log do servidor/mp_webhook_events).
        print(f"[MP Webhook] {e}")
        _log_event(db, type=event_type or "unknown", external_id=str(data_id), raw=body)
        return {"received": True, "error": "processing_failed"}


async def _dispatch_webhook_event(db: Session, event_type, data_id, body, get_payment, get_preapproval):
    if event_type == "payment":
        payment = get_payment(data_id)
        status_ = payment.get("status")
        ref = _parse_external_reference(payment.get("external_reference"))

        # Checar idempotência ANTES de logar este evento — o log de auditoria é a mesma
        # tabela usada pra idempotência, então logar primeiro faria o evento se ver como
        # "já processado" a si mesmo.
        is_duplicate = status_ == "approved" and _already_processed(db, str(data_id))

        _log_event(
            db, type=event_type, status=status_,
            user_id=ref.get("user_id") if ref else None,
            external_id=str(data_id), amount=payment.get("transaction_amount"),
            plan_id=ref.get("plan_id") if ref else None, raw=body,
        )

        if status_ != "approved" or not ref:
            return {"received": True, "status": status_}
        if is_duplicate:
            return {"received": True, "duplicate": True}

        _activate_subscription(
            db, ref["user_id"], ref["plan_id"], ref.get("billing_period", "yearly"),
            mp_payment_id=str(data_id),
        )
        return {"received": True, "activated": True}

    if event_type == "subscription_preapproval":
        preapproval = get_preapproval(data_id)
        status_ = preapproval.get("status")
        ref = _parse_external_reference(preapproval.get("external_reference"))

        is_duplicate = status_ == "authorized" and _already_processed(db, str(data_id))

        _log_event(
            db, type=event_type, status=status_,
            user_id=ref.get("user_id") if ref else None,
            external_id=str(data_id), plan_id=ref.get("plan_id") if ref else None, raw=body,
        )

        if status_ == "authorized" and ref and not is_duplicate:
            _activate_subscription(
                db, ref["user_id"], ref["plan_id"], "monthly",
                mp_preapproval_id=str(data_id),
            )
            return {"received": True, "activated": True}

        return {"received": True, "status": status_}

    if event_type == "subscription_authorized_payment":
        payment = get_payment(data_id)
        status_ = payment.get("status")
        ref = _parse_external_reference(payment.get("external_reference"))

        is_duplicate = status_ == "approved" and _already_processed(db, str(data_id))

        _log_event(
            db, type=event_type, status=status_,
            user_id=ref.get("user_id") if ref else None,
            external_id=str(data_id), amount=payment.get("transaction_amount"),
            plan_id=ref.get("plan_id") if ref else None, raw=body,
        )

        if status_ == "approved" and ref and not is_duplicate:
            _activate_subscription(db, ref["user_id"], ref["plan_id"], "monthly", mp_payment_id=str(data_id))
            return {"received": True, "extended": True}

        return {"received": True, "status": status_}

    # Outros tipos de evento (claim, chargeback, fraud_alert, merchant_order, etc.) — loga e ignora.
    _log_event(db, type=event_type or "unknown", external_id=str(data_id), raw=body)
    return {"received": True}
