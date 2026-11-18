"""Pedigree tree, Wright inbreeding coefficient, PDF export and free-plan export limit."""
from datetime import datetime, timedelta

from tests.conftest import auth_headers


def _give_active_paid_subscription(db, user, plan_id="plan-basic"):
    """Paid-plan users are gated by check_subscription_active — these tests use a paid
    plan just to bypass the free-tier export cap, so they need a real active Subscription
    now that lapsed/missing paid subscriptions are blocked."""
    from app.models.user import Subscription

    db.add(Subscription(
        user_id=user.id, plan_id=plan_id, status="active", billing_period="monthly",
        current_period_end=datetime.utcnow() + timedelta(days=30),
    ))
    db.commit()


async def _make_bird(client, headers, species_id, name, sex="unknown", father_id=None, mother_id=None, ring_number=None):
    res = await client.post(
        "/api/birds",
        headers=headers,
        json={
            "name": name,
            "species_id": species_id,
            "sex": sex,
            "father_id": father_id,
            "mother_id": mother_id,
            "ring_number": ring_number,
        },
    )
    assert res.status_code == 201, res.text
    return res.json()["id"]


async def _half_sibling_mating_tree(client, headers, species_id):
    grandsire = await _make_bird(client, headers, species_id, "Avô Comum", "M")
    unrelated_x = await _make_bird(client, headers, species_id, "Avó X", "F")
    unrelated_y = await _make_bird(client, headers, species_id, "Avó Y", "F")

    father = await _make_bird(client, headers, species_id, "Pai", "M", father_id=grandsire, mother_id=unrelated_x)
    mother = await _make_bird(client, headers, species_id, "Mae", "F", father_id=grandsire, mother_id=unrelated_y)

    target = await _make_bird(client, headers, species_id, "Filhote", "unknown", father_id=father, mother_id=mother)
    return target, father, grandsire


async def test_pedigree_tree_includes_ancestors(client, plans, species, user_factory):
    user = user_factory(email="pedigree1@plumora.com.br")
    headers = auth_headers(user)
    target_id, father_id, grandsire_id = await _half_sibling_mating_tree(client, headers, species.id)

    res = await client.get(f"/api/birds/{target_id}/pedigree", headers=headers, params={"generations": 3})
    assert res.status_code == 200
    tree = res.json()["tree"]
    assert tree["id"] == target_id
    assert tree["father"]["id"] == father_id
    assert tree["father"]["father"]["id"] == grandsire_id


async def test_half_sibling_mating_inbreeding_coefficient(client, plans, species, user_factory):
    user = user_factory(email="pedigree2@plumora.com.br")
    headers = auth_headers(user)
    target_id, _, _ = await _half_sibling_mating_tree(client, headers, species.id)

    res = await client.get(f"/api/birds/{target_id}/pedigree", headers=headers)
    assert res.json()["inbreeding_coefficient_pct"] == 12.5


async def test_unrelated_parents_have_zero_inbreeding(client, plans, species, user_factory):
    user = user_factory(email="pedigree3@plumora.com.br")
    headers = auth_headers(user)
    father_id = await _make_bird(client, headers, species.id, "Pai Solo", "M")
    mother_id = await _make_bird(client, headers, species.id, "Mae Solo", "F")
    target_id = await _make_bird(client, headers, species.id, "Sem Parentesco", father_id=father_id, mother_id=mother_id)

    res = await client.get(f"/api/birds/{target_id}/pedigree", headers=headers)
    assert res.json()["inbreeding_coefficient_pct"] == 0.0


async def test_export_pdf_without_minio_returns_empty_file_url(client, plans, species, user_factory, db):
    user = user_factory(email="pedigree4@plumora.com.br", plan_id="plan-basic")
    _give_active_paid_subscription(db, user)
    headers = auth_headers(user)
    bird_id = await _make_bird(client, headers, species.id, "Para Exportar", ring_number="BR-2026-001")

    res = await client.post(f"/api/birds/{bird_id}/pedigree/export", headers=headers)
    assert res.status_code == 200
    assert res.json()["file_url"] == ""


async def test_export_pdf_succeeds_for_bird_without_ring_number(client, plans, species, user_factory, db):
    user = user_factory(email="pedigree-noring@plumora.com.br", plan_id="plan-basic")
    _give_active_paid_subscription(db, user)
    headers = auth_headers(user)
    bird_id = await _make_bird(client, headers, species.id, "Sem Anilha")

    res = await client.post(f"/api/birds/{bird_id}/pedigree/export", headers=headers)
    assert res.status_code == 200


async def test_free_plan_export_limit(client, plans, species, user_factory):
    user = user_factory(email="pedigree5@plumora.com.br", plan_id="plan-free")
    headers = auth_headers(user)
    bird_id = await _make_bird(client, headers, species.id, "Exporta Free", ring_number="BR-2026-002")

    for _ in range(2):
        res = await client.post(f"/api/birds/{bird_id}/pedigree/export", headers=headers)
        assert res.status_code == 200

    res = await client.post(f"/api/birds/{bird_id}/pedigree/export", headers=headers)
    assert res.status_code == 402
