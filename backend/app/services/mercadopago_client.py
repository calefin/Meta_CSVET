"""Thin wrapper around the official `mercadopago` SDK, isolating the real API calls so
they're easy to mock in tests. Confirmed against the installed SDK (mercadopago==2.3.0):
`sdk.preference()`/`sdk.preapproval()`/`sdk.payment()` are factory methods returning a
resource object whose `.create(dict)`/`.get(id)`/`.update(id, dict)` return
`{"status": <http_status>, "response": <parsed json or None>}`.
"""
import json
from typing import Any, Dict

import mercadopago

from app.config import get_settings

settings = get_settings()


def _get_sdk() -> mercadopago.SDK:
    if not settings.mp_access_token:
        raise RuntimeError("MP_ACCESS_TOKEN não configurado — não é possível cobrar de verdade.")
    return mercadopago.SDK(settings.mp_access_token)


def create_preference(user, plan, success_url: str, failure_url: str, pending_url: str) -> Dict[str, Any]:
    """Checkout único (Preference) — usado para o plano anual."""
    sdk = _get_sdk()
    external_reference = json.dumps({"user_id": user.id, "plan_id": plan.id, "billing_period": "yearly"})
    body = {
        "items": [{
            "id": plan.id,
            "title": f"Plumora Gen — {plan.name.capitalize()} (Anual)",
            "quantity": 1,
            "unit_price": plan.price_yearly / 100,
            "currency_id": "BRL",
        }],
        "payer": {"email": user.email, "name": user.full_name or user.name},
        "back_urls": {"success": success_url, "failure": failure_url, "pending": pending_url},
        "auto_return": "approved",
        "external_reference": external_reference,
        "statement_descriptor": "PLUMORAGEN",
    }
    result = sdk.preference().create(body)
    response = result.get("response") or {}
    if result.get("status") not in (200, 201):
        raise RuntimeError(f"Falha ao criar preferência no Mercado Pago: {response}")
    return {
        "preference_id": response.get("id"),
        "checkout_url": response.get("init_point") or response.get("sandbox_init_point"),
    }


def create_preapproval(user, plan, back_url: str) -> Dict[str, Any]:
    """Assinatura recorrente (PreApproval) — usada para o plano mensal."""
    sdk = _get_sdk()
    external_reference = json.dumps({"user_id": user.id, "plan_id": plan.id, "billing_period": "monthly"})
    body = {
        "reason": f"Plumora Gen — {plan.name.capitalize()} (Mensal)",
        "payer_email": user.email,
        "back_url": back_url,
        "external_reference": external_reference,
        "status": "pending",
        "auto_recurring": {
            "frequency": 1,
            "frequency_type": "months",
            "transaction_amount": plan.price_monthly / 100,
            "currency_id": "BRL",
        },
    }
    result = sdk.preapproval().create(body)
    response = result.get("response") or {}
    if result.get("status") not in (200, 201):
        raise RuntimeError(f"Falha ao criar assinatura no Mercado Pago: {response}")
    return {
        "preapproval_id": response.get("id"),
        "checkout_url": response.get("init_point"),
    }


def cancel_preapproval(preapproval_id: str) -> None:
    sdk = _get_sdk()
    result = sdk.preapproval().update(preapproval_id, {"status": "cancelled"})
    if result.get("status") not in (200, 201):
        raise RuntimeError(f"Falha ao cancelar assinatura no Mercado Pago: {result.get('response')}")


def get_payment(payment_id: str) -> Dict[str, Any]:
    sdk = _get_sdk()
    result = sdk.payment().get(payment_id)
    return result.get("response") or {}


def get_preapproval(preapproval_id: str) -> Dict[str, Any]:
    sdk = _get_sdk()
    result = sdk.preapproval().get(preapproval_id)
    return result.get("response") or {}
