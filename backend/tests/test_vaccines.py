"""Vaccination: immutable records, SHA256 hash, batch lots, schedule, no-edit convention."""
import hashlib

from tests.conftest import auth_headers


async def _make_bird(client, headers, species_id, name="Ave"):
    res = await client.post("/api/birds", headers=headers, json={"name": name, "species_id": species_id})
    return res.json()["id"]


async def _make_vaccine_product(db):
    from app.models.medication import VaccineProduct

    product = VaccineProduct(id="vac-test", name_pt="PMV-1", name_en="PMV-1", interval_days=365)
    db.add(product)
    db.commit()
    return product


async def test_create_record_has_deterministic_sha256_hash(client, plans, species, user_factory, db):
    user = user_factory(email="vacc1@plumora.com.br")
    headers = auth_headers(user)
    bird_id = await _make_bird(client, headers, species.id)
    await _make_vaccine_product(db)

    payload = {
        "bird_id": bird_id,
        "vaccine_product_id": "vac-test",
        "application_date": "2026-02-01T00:00:00",
        "applied_by_name": "Dra. Ana",
        "applied_by_crmv_state": "SP",
        "applied_by_crmv_number": "12345",
        "lot_number_manual": "L123",
        "dose_ml_applied": 0.5,
    }
    res = await client.post("/api/vaccines/records", headers=headers, json=payload)
    assert res.status_code == 201
    record = res.json()

    expected_hash_data = (
        f"{bird_id}|vac-test|2026-02-01T00:00:00|Dra. Ana|SP|12345|L123|0.5"
    )
    expected_hash = hashlib.sha256(expected_hash_data.encode()).hexdigest()
    assert record["record_hash"] == expected_hash


async def test_vaccination_record_has_no_update_endpoint(client, plans, species, user_factory, db):
    """Vaccination records are append-only by design — only create/delete exist."""
    user = user_factory(email="vacc2@plumora.com.br")
    headers = auth_headers(user)
    bird_id = await _make_bird(client, headers, species.id)
    await _make_vaccine_product(db)
    record = (
        await client.post(
            "/api/vaccines/records",
            headers=headers,
            json={"bird_id": bird_id, "vaccine_product_id": "vac-test", "application_date": "2026-02-01T00:00:00"},
        )
    ).json()

    res = await client.put(f"/api/vaccines/records/{record['id']}", headers=headers, json={"notes": "tampering"})
    assert res.status_code == 405


async def test_delete_vaccination_record(client, plans, species, user_factory, db):
    user = user_factory(email="vacc3@plumora.com.br")
    headers = auth_headers(user)
    bird_id = await _make_bird(client, headers, species.id)
    await _make_vaccine_product(db)
    record = (
        await client.post(
            "/api/vaccines/records",
            headers=headers,
            json={"bird_id": bird_id, "vaccine_product_id": "vac-test", "application_date": "2026-02-01T00:00:00"},
        )
    ).json()

    res = await client.delete(f"/api/vaccines/records/{record['id']}", headers=headers)
    assert res.status_code == 204

    res = await client.get(f"/api/vaccines/birds/{bird_id}/records", headers=headers)
    assert res.json() == []


async def test_batch_lot_applies_to_all_active_birds_of_owner(client, plans, species, user_factory, db):
    user = user_factory(email="vacc4@plumora.com.br")
    headers = auth_headers(user)
    await _make_bird(client, headers, species.id, "Ave A")
    await _make_bird(client, headers, species.id, "Ave B")
    await _make_vaccine_product(db)

    res = await client.post(
        "/api/vaccines/lots",
        headers=headers,
        json={
            "name": "Campanha PMV-1",
            "vaccine_product_id": "vac-test",
            "application_date": "2026-02-01T00:00:00",
            "selection_type": "all",
        },
    )
    assert res.status_code == 201
    lot = res.json()
    assert lot["total_birds_targeted"] == 2
    assert lot["total_birds_applied"] == 2


async def test_schedule_returns_records_with_upcoming_next_dose(client, plans, species, user_factory, db):
    user = user_factory(email="vacc5@plumora.com.br")
    headers = auth_headers(user)
    bird_id = await _make_bird(client, headers, species.id)
    await _make_vaccine_product(db)

    await client.post(
        "/api/vaccines/records",
        headers=headers,
        json={"bird_id": bird_id, "vaccine_product_id": "vac-test", "application_date": "2026-06-01T00:00:00"},
    )

    res = await client.get("/api/vaccines/schedule", headers=headers, params={"days": 400})
    assert res.status_code == 200
    assert len(res.json()) == 1


async def test_schedule_includes_overdue_vaccines(client, plans, species, user_factory, db):
    """A vaccine whose next_dose_date is already in the past must still show up in the
    schedule as overdue — it must never silently disappear from the list."""
    from datetime import datetime, timedelta

    user = user_factory(email="vacc6@plumora.com.br")
    headers = auth_headers(user)
    bird_id = await _make_bird(client, headers, species.id)
    await _make_vaccine_product(db)

    old_application_date = (datetime.utcnow() - timedelta(days=1000)).isoformat()
    await client.post(
        "/api/vaccines/records",
        headers=headers,
        json={"bird_id": bird_id, "vaccine_product_id": "vac-test", "application_date": old_application_date},
    )

    res = await client.get("/api/vaccines/schedule", headers=headers, params={"days": 30})
    assert res.status_code == 200
    assert len(res.json()) == 1
