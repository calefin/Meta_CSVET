"""Birds: CRUD, per-user isolation, plan bird-limit enforcement."""
from tests.conftest import auth_headers


async def test_create_list_update_delete_bird(client, plans, species, user_factory):
    user = user_factory(email="birds1@plumora.com.br")
    headers = auth_headers(user)

    res = await client.post(
        "/api/birds",
        headers=headers,
        json={"name": "Piu Piu", "species_id": species.id, "sex": "M"},
    )
    assert res.status_code == 201
    bird_id = res.json()["id"]
    assert res.json()["status"] == "active"

    res = await client.get("/api/birds", headers=headers)
    assert res.status_code == 200
    assert len(res.json()) == 1

    res = await client.put(f"/api/birds/{bird_id}", headers=headers, json={"weight_grams": 95.5})
    assert res.status_code == 200
    assert res.json()["weight_grams"] == 95.5

    res = await client.delete(f"/api/birds/{bird_id}", headers=headers)
    assert res.status_code == 204

    # delete is a soft-delete (status="deleted"); the default unfiltered list still
    # returns it — callers must filter status=active to exclude deleted birds.
    res = await client.get("/api/birds", headers=headers, params={"status": "active"})
    assert res.json() == []
    res = await client.get("/api/birds", headers=headers)
    assert res.json()[0]["status"] == "deleted"


async def test_birds_are_isolated_per_user(client, plans, species, user_factory):
    user_a = user_factory(email="ownerA@plumora.com.br")
    user_b = user_factory(email="ownerB@plumora.com.br")

    res = await client.post(
        "/api/birds",
        headers=auth_headers(user_a),
        json={"name": "Ave da A", "species_id": species.id},
    )
    bird_id = res.json()["id"]

    res = await client.get("/api/birds", headers=auth_headers(user_b))
    assert res.json() == []

    res = await client.get(f"/api/birds/{bird_id}", headers=auth_headers(user_b))
    assert res.status_code == 404


async def test_bird_limit_enforced_by_plan(client, plans, species, user_factory, db):
    from app.models.bird import Bird

    user = user_factory(email="limit@plumora.com.br", plan_id="plan-free")
    for i in range(10):
        db.add(Bird(user_id=user.id, name=f"Ave {i}", species_id=species.id))
    db.commit()

    res = await client.post(
        "/api/birds",
        headers=auth_headers(user),
        json={"name": "Ave 11", "species_id": species.id},
    )
    assert res.status_code == 402


async def test_export_birds_returns_csv_with_only_own_birds(client, plans, species, user_factory):
    owner = user_factory(email="export-owner@plumora.com.br")
    other = user_factory(email="export-other@plumora.com.br")

    await client.post(
        "/api/birds",
        headers=auth_headers(owner),
        json={"name": "Piu Exportável", "ring_number": "BR-2026-999", "species_id": species.id, "sex": "F"},
    )
    await client.post(
        "/api/birds",
        headers=auth_headers(other),
        json={"name": "Ave de Outro Dono", "species_id": species.id},
    )

    res = await client.get("/api/birds/export", headers=auth_headers(owner))
    assert res.status_code == 200
    assert res.headers["content-type"].startswith("text/csv")
    assert "attachment" in res.headers["content-disposition"]

    body = res.text
    assert "Piu Exportável" in body
    assert "BR-2026-999" in body
    assert species.common_name in body
    assert "Ave de Outro Dono" not in body


async def test_list_species_and_breeds(client, plans, species, user_factory):
    user = user_factory(email="speciesuser@plumora.com.br")
    res = await client.get("/api/birds/species/all", headers=auth_headers(user))
    assert res.status_code == 200
    assert any(s["id"] == species.id for s in res.json())

    res = await client.get(f"/api/birds/breeds/by-species/{species.id}", headers=auth_headers(user))
    assert res.status_code == 200
    assert res.json() == []
