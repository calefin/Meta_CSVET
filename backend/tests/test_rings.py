"""Rings: registration, stats, assignment to a bird, and delete-guard while associated."""
from tests.conftest import auth_headers


async def _make_bird(client, headers, species_id):
    res = await client.post("/api/birds", headers=headers, json={"name": "Ave", "species_id": species_id})
    return res.json()["id"]


async def test_create_ring_and_stats(client, plans, species, user_factory):
    user = user_factory(email="ring1@plumora.com.br")
    headers = auth_headers(user)

    res = await client.post(
        "/api/rings",
        headers=headers,
        json={"ring_number": "BR-2026-0001", "species_id": species.id, "year": 2026},
    )
    assert res.status_code == 201

    res = await client.get("/api/rings/stats", headers=headers)
    assert res.json()["total_rings"] == 1
    assert res.json()["unassigned_rings"] == 1


async def test_assign_ring_to_own_bird_only(client, plans, species, user_factory):
    owner = user_factory(email="ring-owner@plumora.com.br")
    other = user_factory(email="ring-other@plumora.com.br")
    headers = auth_headers(owner)
    bird_id = await _make_bird(client, headers, species.id)
    other_bird_id = await _make_bird(client, auth_headers(other), species.id)

    ring_id = (
        await client.post("/api/rings", headers=headers, json={"ring_number": "BR-2026-0002"})
    ).json()["id"]

    res = await client.post(f"/api/rings/{ring_id}/assign", headers=headers, json={"bird_id": other_bird_id})
    assert res.status_code == 404

    res = await client.post(f"/api/rings/{ring_id}/assign", headers=headers, json={"bird_id": bird_id})
    assert res.status_code == 200


async def test_cannot_delete_ring_associated_with_active_bird(client, plans, species, user_factory):
    user = user_factory(email="ring2@plumora.com.br")
    headers = auth_headers(user)
    bird_id = await _make_bird(client, headers, species.id)
    ring_id = (
        await client.post("/api/rings", headers=headers, json={"ring_number": "BR-2026-0003"})
    ).json()["id"]
    await client.post(f"/api/rings/{ring_id}/assign", headers=headers, json={"bird_id": bird_id})

    res = await client.delete(f"/api/rings/{ring_id}", headers=headers)
    assert res.status_code == 400


async def test_ring_can_be_freed_after_its_bird_is_removed(client, plans, species, user_factory):
    user = user_factory(email="ring3@plumora.com.br")
    headers = auth_headers(user)
    bird_id = await _make_bird(client, headers, species.id)
    ring_id = (
        await client.post("/api/rings", headers=headers, json={"ring_number": "BR-2026-0004"})
    ).json()["id"]
    await client.post(f"/api/rings/{ring_id}/assign", headers=headers, json={"bird_id": bird_id})

    await client.delete(f"/api/birds/{bird_id}", headers=headers)

    res = await client.delete(f"/api/rings/{ring_id}", headers=headers)
    assert res.status_code == 204
