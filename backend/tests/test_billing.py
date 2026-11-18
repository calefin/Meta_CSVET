"""Billing: plan listing, Mercado Pago subscribe/cancel/webhook, and the hard-lockout
access gate for lapsed paid subscriptions."""
from datetime import datetime, timedelta

from tests.conftest import auth_headers


async def test_list_plans(client, plans, user_factory):
    res = await client.get("/api/billing/plans")
    assert res.status_code == 200
    assert {p["name"] for p in res.json()} == {"free", "basic", "pro", "premium"}


async def test_cancel_without_active_subscription_404s(client, plans, user_factory):
    user = user_factory(email="billing2@plumora.com.br")
    res = await client.post("/api/billing/cancel", headers=auth_headers(user))
    assert res.status_code == 404


async def test_cancel_active_subscription(client, plans, user_factory, db, monkeypatch):
    from app.models.user import Subscription
    import app.services.mercadopago_client as mp_client

    cancelled_ids = []
    monkeypatch.setattr(mp_client, "cancel_preapproval", lambda preapproval_id: cancelled_ids.append(preapproval_id))

    user = user_factory(email="billing3@plumora.com.br", plan_id="plan-basic")
    sub = Subscription(
        user_id=user.id, plan_id="plan-basic", status="active", billing_period="monthly",
        current_period_end=datetime.utcnow() + timedelta(days=20), mp_preapproval_id="preap-existing",
    )
    db.add(sub)
    db.commit()

    res = await client.post("/api/billing/cancel", headers=auth_headers(user))
    assert res.status_code == 200
    assert cancelled_ids == ["preap-existing"]

    # Cancelar não revoga o acesso na hora — status continua "active" até current_period_end vencer.
    db.refresh(sub)
    assert sub.status == "active"
    assert sub.mp_preapproval_id is None

    # Sem mais nenhum preapproval_id pra cancelar, a segunda chamada não encontra nada.
    res = await client.post("/api/billing/cancel", headers=auth_headers(user))
    assert res.status_code == 404


async def test_webhook_rejects_invalid_signature(client, plans, monkeypatch):
    import app.routers.billing as billing_module

    monkeypatch.setattr(billing_module.settings, "mp_webhook_secret", "whsec_test")
    res = await client.post(
        "/api/billing/webhook",
        json={"type": "payment", "data": {"id": "123"}},
        headers={"x-signature": "ts=123,v1=garbage"},
    )
    assert res.status_code == 401


async def test_webhook_payment_approved_activates_yearly_subscription(client, plans, user_factory, db, monkeypatch):
    import app.services.mercadopago_client as mp_client
    import json

    user = user_factory(email="billing4@plumora.com.br", plan_id="plan-free")
    ref = json.dumps({"user_id": user.id, "plan_id": "plan-pro", "billing_period": "yearly"})
    monkeypatch.setattr(mp_client, "get_payment", lambda payment_id: {
        "status": "approved", "external_reference": ref, "transaction_amount": 499.0,
    })

    res = await client.post("/api/billing/webhook", json={"type": "payment", "data": {"id": "pay-1"}})
    assert res.status_code == 200
    assert res.json()["activated"] is True

    from app.models.user import Subscription

    db.expire_all()
    sub = db.query(Subscription).filter(Subscription.user_id == user.id, Subscription.plan_id == "plan-pro").first()
    assert sub is not None
    assert sub.status == "active"
    assert sub.current_period_end > datetime.utcnow() + timedelta(days=360)

    user_row = db.query(type(user)).filter_by(id=user.id).first()
    assert user_row.plan_id == "plan-pro"


async def test_webhook_duplicate_payment_event_is_not_processed_twice(client, plans, user_factory, db, monkeypatch):
    import app.services.mercadopago_client as mp_client
    import json

    user = user_factory(email="billing5@plumora.com.br", plan_id="plan-free")
    ref = json.dumps({"user_id": user.id, "plan_id": "plan-basic", "billing_period": "yearly"})
    monkeypatch.setattr(mp_client, "get_payment", lambda payment_id: {
        "status": "approved", "external_reference": ref, "transaction_amount": 199.0,
    })

    res1 = await client.post("/api/billing/webhook", json={"type": "payment", "data": {"id": "pay-dup"}})
    assert res1.status_code == 200
    assert res1.json().get("activated") is True

    res2 = await client.post("/api/billing/webhook", json={"type": "payment", "data": {"id": "pay-dup"}})
    assert res2.status_code == 200
    assert res2.json().get("duplicate") is True


async def test_webhook_subscription_preapproval_authorized_activates_monthly(client, plans, user_factory, db, monkeypatch):
    import app.services.mercadopago_client as mp_client
    import json

    user = user_factory(email="billing6@plumora.com.br", plan_id="plan-free")
    ref = json.dumps({"user_id": user.id, "plan_id": "plan-basic", "billing_period": "monthly"})
    monkeypatch.setattr(mp_client, "get_preapproval", lambda preapproval_id: {
        "status": "authorized", "external_reference": ref,
    })

    res = await client.post("/api/billing/webhook", json={"type": "subscription_preapproval", "data": {"id": "preap-1"}})
    assert res.status_code == 200
    assert res.json()["activated"] is True

    from app.models.user import Subscription

    db.expire_all()
    sub = db.query(Subscription).filter(Subscription.user_id == user.id, Subscription.plan_id == "plan-basic").first()
    assert sub.status == "active"
    assert sub.mp_preapproval_id == "preap-1"
    assert sub.current_period_end < datetime.utcnow() + timedelta(days=31)


# --------------------------------------------------------------------------------------
# check_subscription_active — o bloqueio por inadimplência em si
# --------------------------------------------------------------------------------------

async def test_paid_plan_with_expired_subscription_is_blocked(client, plans, user_factory, db):
    from app.models.user import Subscription

    user = user_factory(email="billing-blocked@plumora.com.br", plan_id="plan-basic")
    db.add(Subscription(
        user_id=user.id, plan_id="plan-basic", status="active", billing_period="monthly",
        current_period_end=datetime.utcnow() - timedelta(days=1),  # já venceu
    ))
    db.commit()

    res = await client.get("/api/birds", headers=auth_headers(user))
    assert res.status_code == 402


async def test_paid_plan_with_active_subscription_is_allowed(client, plans, user_factory, db):
    from app.models.user import Subscription

    user = user_factory(email="billing-active@plumora.com.br", plan_id="plan-basic")
    db.add(Subscription(
        user_id=user.id, plan_id="plan-basic", status="active", billing_period="monthly",
        current_period_end=datetime.utcnow() + timedelta(days=10),
    ))
    db.commit()

    res = await client.get("/api/birds", headers=auth_headers(user))
    assert res.status_code == 200


async def test_free_plan_is_never_blocked(client, plans, user_factory):
    user = user_factory(email="billing-free@plumora.com.br", plan_id="plan-free")
    res = await client.get("/api/birds", headers=auth_headers(user))
    assert res.status_code == 200


async def test_no_plan_at_all_is_never_blocked(client, plans, user_factory):
    user = user_factory(email="billing-noplan@plumora.com.br", plan_id=None)
    res = await client.get("/api/birds", headers=auth_headers(user))
    assert res.status_code == 200


async def test_vet_role_never_blocked_even_with_lapsed_paid_plan(client, plans, user_factory, db):
    """/api/birds does carry the subscription gate (see main.py), but the gate itself
    exempts any non-breeder role — this hits that gated router as a vet to prove it."""
    from app.models.user import Subscription

    vet = user_factory(email="billing-vet@plumora.com.br", role="vet", plan_id="plan-basic")
    db.add(Subscription(
        user_id=vet.id, plan_id="plan-basic", status="active", billing_period="monthly",
        current_period_end=datetime.utcnow() - timedelta(days=30),
    ))
    db.commit()

    res = await client.get("/api/birds", headers=auth_headers(vet))
    assert res.status_code == 200
