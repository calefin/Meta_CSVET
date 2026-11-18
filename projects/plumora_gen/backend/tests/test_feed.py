"""Feeding: product catalog and per-bird consumption log."""
from tests.conftest import auth_headers


async def _make_bird(client, headers, species_id):
    res = await client.post("/api/birds", headers=headers, json={"name": "Ave", "species_id": species_id})
    return res.json()["id"]


async def test_create_feed_product_and_log_consumption(client, plans, species, user_factory):
    user = user_factory(email="feed1@plumora.com.br")
    headers = auth_headers(user)
    bird_id = await _make_bird(client, headers, species.id)

    res = await client.post(
        "/api/feed/products",
        headers=headers,
        json={"name": "Mix Premium", "type": "seed", "calories_per_100g": 350},
    )
    assert res.status_code == 201
    feed_id = res.json()["id"]

    res = await client.post(
        "/api/feed/logs",
        headers=headers,
        json={"bird_id": bird_id, "feed_id": feed_id, "amount_grams": 20, "date": "2026-02-01T00:00:00"},
    )
    assert res.status_code == 201
    log_id = res.json()["id"]

    res = await client.get("/api/feed/logs", headers=headers)
    assert len(res.json()) == 1
    assert res.json()[0]["bird_name"] == "Ave"
    assert res.json()[0]["feed_name"] == "Mix Premium"

    res = await client.delete(f"/api/feed/logs/{log_id}", headers=headers)
    assert res.status_code == 204


async def test_cannot_log_feed_for_another_users_bird(client, plans, species, user_factory):
    owner = user_factory(email="feed-owner@plumora.com.br")
    other = user_factory(email="feed-other@plumora.com.br")
    bird_id = await _make_bird(client, auth_headers(owner), species.id)

    feed_id = (
        await client.post(
            "/api/feed/products",
            headers=auth_headers(other),
            json={"name": "Racao", "type": "pellet"},
        )
    ).json()["id"]

    res = await client.post(
        "/api/feed/logs",
        headers=auth_headers(other),
        json={"bird_id": bird_id, "feed_id": feed_id, "date": "2026-02-01T00:00:00"},
    )
    assert res.status_code == 404
