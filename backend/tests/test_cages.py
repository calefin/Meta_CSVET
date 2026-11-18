"""Cages: reusable cage/box registry for breeding pairs."""
from tests.conftest import auth_headers


async def _make_bird(client, headers, name, sex, species_id):
    res = await client.post("/api/birds", headers=headers, json={"name": name, "sex": sex, "species_id": species_id})
    return res.json()["id"]


async def test_create_cage(client, plans, user_factory):
    user = user_factory(email="cage-owner@plumora.com.br")
    headers = auth_headers(user)
    res = await client.post("/api/cages", headers=headers, json={"name": "Gaiola 1", "location": "Galpão A"})
    assert res.status_code == 201
    body = res.json()
    assert body["is_active"] is True
    assert body["name"] == "Gaiola 1"


async def test_list_cages_active_only(client, plans, user_factory):
    user = user_factory(email="cage-list@plumora.com.br")
    headers = auth_headers(user)
    cage_id = (await client.post("/api/cages", headers=headers, json={"name": "Gaiola Ativa"})).json()["id"]
    await client.put(f"/api/cages/{cage_id}", headers=headers, json={"name": "Gaiola Ativa"})
    other_id = (await client.post("/api/cages", headers=headers, json={"name": "Gaiola Arquivada"})).json()["id"]
    await client.delete(f"/api/cages/{other_id}", headers=headers)

    res = await client.get("/api/cages", headers=headers, params={"active_only": True})
    names = [c["name"] for c in res.json()]
    assert "Gaiola Ativa" in names
    assert "Gaiola Arquivada" not in names


async def test_cage_blocked_when_occupied_by_active_breeding(client, plans, species, user_factory):
    user = user_factory(email="cage-conflict@plumora.com.br")
    headers = auth_headers(user)
    cage_id = (await client.post("/api/cages", headers=headers, json={"name": "Gaiola X"})).json()["id"]
    male_id = await _make_bird(client, headers, "M1", "M", species.id)
    female_id = await _make_bird(client, headers, "F1", "F", species.id)
    male2_id = await _make_bird(client, headers, "M2", "M", species.id)
    female2_id = await _make_bird(client, headers, "F2", "F", species.id)

    res1 = await client.post(
        "/api/breeding",
        headers=headers,
        json={"male_id": male_id, "female_id": female_id, "pairing_date": "2026-01-01T00:00:00", "cage_id": cage_id},
    )
    assert res1.status_code == 201

    res2 = await client.post(
        "/api/breeding",
        headers=headers,
        json={"male_id": male2_id, "female_id": female2_id, "pairing_date": "2026-02-01T00:00:00", "cage_id": cage_id},
    )
    assert res2.status_code == 409

    breeding_id = res1.json()["id"]
    res_sep = await client.put(
        f"/api/breeding/{breeding_id}",
        headers=headers,
        json={"status": "completed", "separation_date": "2026-03-01T00:00:00"},
    )
    assert res_sep.status_code == 200

    res3 = await client.post(
        "/api/breeding",
        headers=headers,
        json={"male_id": male2_id, "female_id": female2_id, "pairing_date": "2026-03-05T00:00:00", "cage_id": cage_id},
    )
    assert res3.status_code == 201
