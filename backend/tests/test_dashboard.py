"""Dashboard: aggregated stats + prioritized alerts for the breeder."""
from datetime import datetime, timedelta

from tests.conftest import auth_headers


async def _make_bird(client, headers, sex, species_id, name="Ave"):
    res = await client.post("/api/birds", headers=headers, json={"name": name, "sex": sex, "species_id": species_id})
    return res.json()["id"]


async def test_dashboard_summary_empty_plantel(client, plans, user_factory):
    user = user_factory(email="dash-empty@plumora.com.br")
    res = await client.get("/api/dashboard/summary", headers=auth_headers(user))
    assert res.status_code == 200
    data = res.json()
    assert data["stats"]["active_birds"] == 0
    assert data["stats"]["active_pairs"] == 0
    assert data["alerts"] == []
    assert data["active_breedings"] == []


async def test_dashboard_shows_overdue_vaccine_alert(client, plans, species, user_factory, db):
    from app.models.medication import VaccineProduct

    user = user_factory(email="dash-vacc@plumora.com.br")
    headers = auth_headers(user)
    bird_id = await _make_bird(client, headers, "M", species.id, "Piu")

    product = VaccineProduct(id="vac-dash", name_pt="V1", name_en="V1", interval_days=365)
    db.add(product)
    db.commit()

    old_application_date = (datetime.utcnow() - timedelta(days=1000)).isoformat()
    await client.post(
        "/api/vaccines/records",
        headers=headers,
        json={"bird_id": bird_id, "vaccine_product_id": "vac-dash", "application_date": old_application_date},
    )

    res = await client.get("/api/dashboard/summary", headers=headers)
    assert res.status_code == 200
    alerts = res.json()["alerts"]
    assert any(a["type"] == "vaccine_overdue" and a["bird_id"] == bird_id for a in alerts)
    assert alerts[0]["severity"] == "urgent"


async def test_dashboard_shows_health_severity_alert(client, plans, species, user_factory):
    user = user_factory(email="dash-health@plumora.com.br")
    headers = auth_headers(user)
    bird_id = await _make_bird(client, headers, "F", species.id, "Lola")

    await client.post(
        f"/api/birds/{bird_id}/health",
        headers=headers,
        json={"severity": "urgent", "behavior_notes": "letárgica"},
    )

    res = await client.get("/api/dashboard/summary", headers=headers)
    alerts = res.json()["alerts"]
    assert any(a["type"] == "health_severity" and a["severity"] == "urgent" and a["bird_id"] == bird_id for a in alerts)


async def test_dashboard_active_breeding_with_expected_hatch_date(client, plans, species, user_factory):
    user = user_factory(email="dash-breeding@plumora.com.br")
    headers = auth_headers(user)
    male_id = await _make_bird(client, headers, "M", species.id, "Zeus")
    female_id = await _make_bird(client, headers, "F", species.id, "Hera")

    breeding = (
        await client.post(
            "/api/breeding",
            headers=headers,
            json={"male_id": male_id, "female_id": female_id, "pairing_date": "2026-01-10T00:00:00"},
        )
    ).json()
    await client.post(
        f"/api/breeding/{breeding['id']}/clutch",
        headers=headers,
        json={"lay_date": "2026-01-20T00:00:00", "egg_count": 4},
    )

    res = await client.get("/api/dashboard/summary", headers=headers)
    data = res.json()
    assert data["stats"]["active_pairs"] == 1
    ab = data["active_breedings"][0]
    assert ab["male_name"] == "Zeus"
    assert ab["female_name"] == "Hera"
    # fixture `species` (conftest.py) tem incubation_days=21 -> 2026-01-20 + 21 dias = 2026-02-10
    assert ab["expected_hatch_date"].startswith("2026-02-10")


async def test_dashboard_pending_vet_consult_counted(client, plans, species, user_factory):
    user = user_factory(email="dash-vet@plumora.com.br")
    headers = auth_headers(user)
    bird_id = await _make_bird(client, headers, "M", species.id, "Rex")
    record_id = (await client.post(f"/api/birds/{bird_id}/health", headers=headers, json={})).json()["id"]
    await client.post(
        f"/api/birds/{bird_id}/health/{record_id}/request-vet",
        headers=headers,
        json={"description": "Checagem"},
    )

    res = await client.get("/api/dashboard/summary", headers=headers)
    data = res.json()
    assert data["stats"]["pending_vet_consults"] == 1
    assert any(a["type"] == "vet_pending" for a in data["alerts"])


async def test_dashboard_isolated_per_user(client, plans, species, user_factory):
    owner = user_factory(email="dash-owner@plumora.com.br")
    other = user_factory(email="dash-other@plumora.com.br")
    await _make_bird(client, auth_headers(owner), "M", species.id, "Meu")

    res = await client.get("/api/dashboard/summary", headers=auth_headers(other))
    assert res.json()["stats"]["active_birds"] == 0


async def test_my_vet_consults_endpoint(client, plans, species, user_factory):
    user = user_factory(email="dash-myconsults@plumora.com.br")
    headers = auth_headers(user)
    bird_id = await _make_bird(client, headers, "M", species.id, "Rex2")
    record_id = (await client.post(f"/api/birds/{bird_id}/health", headers=headers, json={})).json()["id"]
    await client.post(
        f"/api/birds/{bird_id}/health/{record_id}/request-vet",
        headers=headers,
        json={"description": "Checagem"},
    )

    res = await client.get("/api/birds/vet-consults/mine", headers=headers)
    assert res.status_code == 200
    assert len(res.json()) == 1
    assert res.json()[0]["status"] == "pending"
