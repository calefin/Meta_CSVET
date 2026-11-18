"""Races: race creation and result registration with velocity calculation."""
from tests.conftest import auth_headers


async def _make_bird(client, headers, species_id):
    res = await client.post("/api/birds", headers=headers, json={"name": "Pombo", "species_id": species_id})
    return res.json()["id"]


async def test_create_race_and_list(client, plans, user_factory):
    user = user_factory(email="race1@plumora.com.br")
    headers = auth_headers(user)

    res = await client.post(
        "/api/races",
        headers=headers,
        json={"name": "Prova de Velocidade", "date": "2026-03-01T08:00:00", "distance_km": 200},
    )
    assert res.status_code == 201
    race_id = res.json()["id"]

    res = await client.get("/api/races", headers=headers)
    assert any(r["id"] == race_id for r in res.json())

    res = await client.get(f"/api/races/{race_id}", headers=headers)
    assert res.status_code == 200


async def test_race_result_computes_velocity_and_flight_time(client, plans, species, user_factory):
    user = user_factory(email="race2@plumora.com.br")
    headers = auth_headers(user)
    bird_id = await _make_bird(client, headers, species.id)

    race_id = (
        await client.post(
            "/api/races",
            headers=headers,
            json={"name": "Prova 300km", "date": "2026-03-01T08:00:00", "distance_km": 300},
        )
    ).json()["id"]

    res = await client.post(
        f"/api/races/{race_id}/results",
        headers=headers,
        json={
            "bird_id": bird_id,
            "release_time": "2026-03-01T06:00:00",
            "arrival_time": "2026-03-01T09:00:00",
        },
    )
    assert res.status_code == 201
    result = res.json()
    assert result["flight_time_seconds"] == 3 * 3600
    assert result["velocity_mpm"] == round((300 * 1000) / (3 * 3600 / 60), 2)


async def test_race_not_found(client, plans, user_factory):
    user = user_factory(email="race3@plumora.com.br")
    res = await client.get("/api/races/does-not-exist", headers=auth_headers(user))
    assert res.status_code == 404
