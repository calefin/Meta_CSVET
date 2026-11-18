"""Medications: catalog (admin/vet only) and per-bird treatment records."""
from tests.conftest import auth_headers


async def _make_bird(client, headers, species_id):
    res = await client.post("/api/birds", headers=headers, json={"name": "Ave", "species_id": species_id})
    return res.json()["id"]


async def test_only_admin_or_vet_can_create_medication(client, plans, user_factory):
    breeder = user_factory(email="med1@plumora.com.br")
    vet = user_factory(email="med-vet@plumora.com.br", role="vet")

    res = await client.post(
        "/api/medications",
        headers=auth_headers(breeder),
        json={"name": "Enrofloxacina", "type": "antibiotic"},
    )
    assert res.status_code == 403

    res = await client.post(
        "/api/medications",
        headers=auth_headers(vet),
        json={"name": "Enrofloxacina", "type": "antibiotic"},
    )
    assert res.status_code == 201


async def test_create_and_delete_medication_record(client, plans, species, user_factory, db):
    from app.models.medication import Medication

    user = user_factory(email="med2@plumora.com.br")
    headers = auth_headers(user)
    bird_id = await _make_bird(client, headers, species.id)

    med = Medication(id="med-test", name="Enrofloxacina", type="antibiotic")
    db.add(med)
    db.commit()

    res = await client.post(
        "/api/medications/records",
        headers=headers,
        json={"bird_id": bird_id, "medication_id": "med-test", "dose": 10, "unit": "mg", "start_date": "2026-02-01T00:00:00"},
    )
    assert res.status_code == 201
    record_id = res.json()["id"]

    res = await client.get("/api/medications/records", headers=headers)
    assert len(res.json()) == 1
    assert res.json()[0]["medication_name"] == "Enrofloxacina"

    res = await client.delete(f"/api/medications/records/{record_id}", headers=headers)
    assert res.status_code == 204
    assert (await client.get("/api/medications/records", headers=headers)).json() == []
