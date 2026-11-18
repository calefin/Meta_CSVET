"""Health records and PigeonVet consult queue (RBAC: vet/admin only)."""
import time

from tests.conftest import auth_headers


async def _make_bird(client, headers, species_id, name="Ave"):
    res = await client.post("/api/birds", headers=headers, json={"name": name, "species_id": species_id})
    return res.json()["id"]


async def test_request_vet_consult_does_not_hang_when_broker_is_unreachable(
    client, plans, species, user_factory, monkeypatch
):
    """Regression test for a real hang: notify_vet_consult_email.delay() used to block
    for ~19s-120s (result-backend pubsub retry, then broker connection retries) when
    Redis is unreachable, before the router's try/except could even catch it. Bypass
    the test suite's no-op stub here to exercise the real Celery dispatch path."""
    from app.tasks.notification_tasks import notify_vet_consult_email

    monkeypatch.delattr(notify_vet_consult_email, "delay")  # restore the real Task.delay

    breeder = user_factory(email="health-timeout@plumora.com.br")
    headers = auth_headers(breeder)
    bird_id = await _make_bird(client, headers, species.id)
    record_id = (await client.post(f"/api/birds/{bird_id}/health", headers=headers, json={})).json()["id"]

    start = time.monotonic()
    res = await client.post(
        f"/api/birds/{bird_id}/health/{record_id}/request-vet",
        headers=headers,
        json={"description": "Checagem geral"},
    )
    elapsed = time.monotonic() - start

    assert res.status_code == 201
    assert elapsed < 10, f"request-vet took {elapsed:.1f}s — Celery dispatch is blocking again"


async def test_create_and_list_health_records(client, plans, species, user_factory):
    user = user_factory(email="health1@plumora.com.br")
    headers = auth_headers(user)
    bird_id = await _make_bird(client, headers, species.id)

    res = await client.post(
        f"/api/birds/{bird_id}/health",
        headers=headers,
        json={"weight_grams": 88.0, "severity": "attention", "symptoms_json": ["letargia"]},
    )
    assert res.status_code == 201
    record_id = res.json()["id"]

    res = await client.get(f"/api/birds/{bird_id}/health", headers=headers)
    assert res.status_code == 200
    assert len(res.json()) == 1
    assert res.json()[0]["id"] == record_id


async def test_request_vet_consult_creates_queue_entry(client, plans, species, user_factory):
    breeder = user_factory(email="health2@plumora.com.br")
    vet = user_factory(email="vet1@plumora.com.br", role="vet")
    headers = auth_headers(breeder)
    bird_id = await _make_bird(client, headers, species.id)

    res = await client.post(f"/api/birds/{bird_id}/health", headers=headers, json={"severity": "urgent"})
    record_id = res.json()["id"]

    res = await client.post(
        f"/api/birds/{bird_id}/health/{record_id}/request-vet",
        headers=headers,
        json={"description": "Ave apática e sem apetite", "priority": "high"},
    )
    assert res.status_code == 201
    consult = res.json()
    assert consult["status"] == "pending"

    res = await client.get("/api/vet/queue", headers=auth_headers(vet))
    assert res.status_code == 200
    assert any(c["id"] == consult["id"] for c in res.json())


async def test_vet_queue_forbidden_for_breeder(client, plans, user_factory):
    breeder = user_factory(email="health3@plumora.com.br")
    res = await client.get("/api/vet/queue", headers=auth_headers(breeder))
    assert res.status_code == 403


async def test_vet_can_respond_to_consult(client, plans, species, user_factory):
    breeder = user_factory(email="health4@plumora.com.br")
    vet = user_factory(email="vet2@plumora.com.br", role="vet")
    headers = auth_headers(breeder)
    bird_id = await _make_bird(client, headers, species.id)
    record_id = (await client.post(f"/api/birds/{bird_id}/health", headers=headers, json={})).json()["id"]
    consult_id = (
        await client.post(
            f"/api/birds/{bird_id}/health/{record_id}/request-vet",
            headers=headers,
            json={"description": "Checagem geral"},
        )
    ).json()["id"]

    res = await client.post(
        f"/api/vet/consults/{consult_id}/respond",
        headers=auth_headers(vet),
        json={"response_text": "Sem alterações relevantes, observar por 48h.", "status": "responded"},
    )
    assert res.status_code == 200
    assert res.json()["status"] == "responded"
    assert res.json()["assigned_vet_id"] == vet.id


async def test_breeder_cannot_respond_to_consult(client, plans, species, user_factory):
    breeder = user_factory(email="health5@plumora.com.br")
    other_breeder = user_factory(email="health6@plumora.com.br")
    headers = auth_headers(breeder)
    bird_id = await _make_bird(client, headers, species.id)
    record_id = (await client.post(f"/api/birds/{bird_id}/health", headers=headers, json={})).json()["id"]
    consult_id = (
        await client.post(
            f"/api/birds/{bird_id}/health/{record_id}/request-vet",
            headers=headers,
            json={"description": "Checagem"},
        )
    ).json()["id"]

    res = await client.post(
        f"/api/vet/consults/{consult_id}/respond",
        headers=auth_headers(other_breeder),
        json={"response_text": "Tentando responder sem ser vet"},
    )
    assert res.status_code == 403
