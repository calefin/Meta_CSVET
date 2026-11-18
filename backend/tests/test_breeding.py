"""Breeding: pairs, clutches and zootechnical stats."""
from tests.conftest import auth_headers


async def _make_bird(client, headers, name, sex, species_id):
    res = await client.post("/api/birds", headers=headers, json={"name": name, "sex": sex, "species_id": species_id})
    return res.json()["id"]


async def test_create_breeding_and_add_clutch(client, plans, species, user_factory):
    user = user_factory(email="breeder-pair@plumora.com.br")
    headers = auth_headers(user)
    male_id = await _make_bird(client, headers, "Macho", "M", species.id)
    female_id = await _make_bird(client, headers, "Femea", "F", species.id)

    res = await client.post(
        "/api/breeding",
        headers=headers,
        json={"male_id": male_id, "female_id": female_id, "pairing_date": "2026-01-10T00:00:00"},
    )
    assert res.status_code == 201
    breeding = res.json()
    assert breeding["status"] == "paired"

    res = await client.post(
        f"/api/breeding/{breeding['id']}/clutch",
        headers=headers,
        json={"lay_date": "2026-01-20T00:00:00", "egg_count": 4, "fertile_count": 3, "hatched_count": 2},
    )
    assert res.status_code == 201

    res = await client.get(f"/api/breeding/{breeding['id']}", headers=headers)
    assert res.json()["status"] == "incubating"


async def test_breeding_stats_summary(client, plans, species, user_factory):
    user = user_factory(email="breeder-stats@plumora.com.br")
    headers = auth_headers(user)
    male_id = await _make_bird(client, headers, "Macho2", "M", species.id)
    female_id = await _make_bird(client, headers, "Femea2", "F", species.id)

    res = await client.post(
        "/api/breeding",
        headers=headers,
        json={"male_id": male_id, "female_id": female_id, "pairing_date": "2026-01-10T00:00:00"},
    )
    breeding_id = res.json()["id"]
    await client.post(
        f"/api/breeding/{breeding_id}/clutch",
        headers=headers,
        json={"lay_date": "2026-01-20T00:00:00", "egg_count": 4, "fertile_count": 2, "hatched_count": 1},
    )

    res = await client.get("/api/breeding/stats/summary", headers=headers)
    assert res.status_code == 200
    stats = res.json()
    assert stats["total_pairs"] == 1
    assert stats["total_eggs"] == 4
    assert stats["fertility_rate_pct"] == 50.0
    assert stats["hatch_rate_pct"] == 50.0


async def test_breeding_not_found_for_other_user(client, plans, species, user_factory):
    owner = user_factory(email="breeder-owner@plumora.com.br")
    other = user_factory(email="breeder-other@plumora.com.br")
    headers = auth_headers(owner)
    male_id = await _make_bird(client, headers, "Macho3", "M", species.id)
    female_id = await _make_bird(client, headers, "Femea3", "F", species.id)

    res = await client.post(
        "/api/breeding",
        headers=headers,
        json={"male_id": male_id, "female_id": female_id, "pairing_date": "2026-01-10T00:00:00"},
    )
    breeding_id = res.json()["id"]

    res = await client.get(f"/api/breeding/{breeding_id}", headers=auth_headers(other))
    assert res.status_code == 404


async def test_create_breeding_rejects_wrong_sex(client, plans, species, user_factory):
    user = user_factory(email="breeder-wrongsex@plumora.com.br")
    headers = auth_headers(user)
    male_id = await _make_bird(client, headers, "Macho4", "M", species.id)
    male2_id = await _make_bird(client, headers, "Macho5", "M", species.id)

    res = await client.post(
        "/api/breeding",
        headers=headers,
        json={"male_id": male_id, "female_id": male2_id, "pairing_date": "2026-01-10T00:00:00"},
    )
    assert res.status_code == 400


async def test_create_breeding_rejects_bird_not_owned(client, plans, species, user_factory):
    owner = user_factory(email="breeder-birdowner@plumora.com.br")
    other = user_factory(email="breeder-birdother@plumora.com.br")
    male_id = await _make_bird(client, headers=auth_headers(other), name="MachoAlheio", sex="M", species_id=species.id)
    female_id = await _make_bird(client, headers=auth_headers(owner), name="FemeaPropria", sex="F", species_id=species.id)

    res = await client.post(
        "/api/breeding",
        headers=auth_headers(owner),
        json={"male_id": male_id, "female_id": female_id, "pairing_date": "2026-01-10T00:00:00"},
    )
    assert res.status_code == 404


async def test_full_offspring_promote_cycle_updates_pedigree(client, plans, species, user_factory):
    user = user_factory(email="breeder-promote@plumora.com.br")
    headers = auth_headers(user)
    male_id = await _make_bird(client, headers, "Pai", "M", species.id)
    female_id = await _make_bird(client, headers, "Mae", "F", species.id)

    breeding = (await client.post(
        "/api/breeding",
        headers=headers,
        json={"male_id": male_id, "female_id": female_id, "pairing_date": "2026-01-10T00:00:00"},
    )).json()

    clutch = (await client.post(
        f"/api/breeding/{breeding['id']}/clutch",
        headers=headers,
        json={"lay_date": "2026-01-20T00:00:00", "egg_count": 2, "fertile_count": 2, "hatched_count": 1},
    )).json()

    offspring = (await client.post(
        f"/api/breeding/clutch/{clutch['id']}/offspring",
        headers=headers,
        json={"hatch_date": "2026-02-10T00:00:00", "hatch_weight_g": 15.5, "ring_number": "BR-2026-001"},
    )).json()

    res = await client.post(
        f"/api/breeding/offspring/{offspring['id']}/promote",
        headers=headers,
        json={"name": "Filhote 1", "sex": "M"},
    )
    assert res.status_code == 201
    bird = res.json()
    assert bird["father_id"] == male_id
    assert bird["mother_id"] == female_id
    assert bird["ring_number"] == "BR-2026-001"

    pedigree = await client.get(f"/api/birds/{bird['id']}/pedigree", headers=headers)
    assert pedigree.status_code == 200
    tree = pedigree.json()["tree"]
    assert tree["father"]["id"] == male_id
    assert tree["mother"]["id"] == female_id

    # Segunda promoção deve ser rejeitada
    res_again = await client.post(
        f"/api/breeding/offspring/{offspring['id']}/promote",
        headers=headers,
        json={"name": "Filhote 1 Duplicado", "sex": "M"},
    )
    assert res_again.status_code == 409

    # Filhote já promovido não pode ser excluído
    res_delete = await client.delete(f"/api/breeding/offspring/{offspring['id']}", headers=headers)
    assert res_delete.status_code == 409


async def test_update_breeding_clears_cage_and_separation_date_with_explicit_null(client, plans, species, user_factory):
    """Regression test: model_dump(exclude_none=True) used to silently drop cage_id/
    separation_date when the client explicitly sent null to clear them — the PUT looked
    successful but nothing changed. Must use exclude_unset=True instead."""
    user = user_factory(email="breeder-clearfields@plumora.com.br")
    headers = auth_headers(user)
    male_id = await _make_bird(client, headers, "Macho7", "M", species.id)
    female_id = await _make_bird(client, headers, "Femea7", "F", species.id)

    cage_id = (await client.post("/api/cages", headers=headers, json={"name": "Gaiola Clear"})).json()["id"]

    breeding = (await client.post(
        "/api/breeding",
        headers=headers,
        json={
            "male_id": male_id, "female_id": female_id, "pairing_date": "2026-01-10T00:00:00",
            "cage_id": cage_id, "separation_date": "2026-02-01T00:00:00",
        },
    )).json()
    assert breeding["cage_id"] == cage_id
    assert breeding["separation_date"] is not None

    res = await client.put(
        f"/api/breeding/{breeding['id']}",
        headers=headers,
        json={"cage_id": None, "separation_date": None},
    )
    assert res.status_code == 200
    assert res.json()["cage_id"] is None
    assert res.json()["separation_date"] is None
