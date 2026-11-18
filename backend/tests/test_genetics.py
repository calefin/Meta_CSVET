"""Genetics: locus listing, bird genotype upsert, multi-locus calculator."""
from tests.conftest import auth_headers


def _add_loci(db, species_id):
    from app.models.genetics import GeneticLocus

    loci = [
        GeneticLocus(id="gl-dil", species_id=species_id, symbol="dil", name_pt="Diluído", name_en="Dilute", inheritance_mode="re"),
        GeneticLocus(id="gl-cin", species_id=species_id, symbol="cin", name_pt="Canela", name_en="Cinnamon", inheritance_mode="se", crossover_freq=0.07),
        GeneticLocus(id="gl-op", species_id=species_id, symbol="op", name_pt="Opalino", name_en="Opaline", inheritance_mode="se"),
    ]
    for locus in loci:
        db.add(locus)
    db.commit()
    return loci


async def _make_bird(client, headers, species_id, sex, name):
    res = await client.post("/api/birds", headers=headers, json={"name": name, "species_id": species_id, "sex": sex})
    return res.json()["id"]


async def test_list_species_loci(client, plans, species, user_factory, db):
    _add_loci(db, species.id)
    user = user_factory(email="genetics-loci@plumora.com.br")
    res = await client.get(f"/api/genetics/species/{species.id}/loci", headers=auth_headers(user))
    assert res.status_code == 200
    symbols = {locus["symbol"] for locus in res.json()}
    assert symbols == {"dil", "cin", "op"}


async def test_upsert_bird_genotype(client, plans, species, user_factory, db):
    _add_loci(db, species.id)
    user = user_factory(email="genetics-upsert@plumora.com.br")
    headers = auth_headers(user)
    bird_id = await _make_bird(client, headers, species.id, "M", "Ave Genotipo")

    res = await client.post(
        f"/api/genetics/birds/{bird_id}/genotype",
        headers=headers,
        json={"locus_id": "gl-dil", "allele_1": "V", "allele_2": "V", "confidence": "confirmed"},
    )
    assert res.status_code == 201
    genotype_id = res.json()["id"]

    # Upsert: posting again for the same locus updates rather than duplicates
    res = await client.post(
        f"/api/genetics/birds/{bird_id}/genotype",
        headers=headers,
        json={"locus_id": "gl-dil", "allele_1": "V", "allele_2": "+", "confidence": "estimated"},
    )
    assert res.json()["id"] == genotype_id
    assert res.json()["allele_2"] == "+"

    res = await client.get(f"/api/genetics/birds/{bird_id}/genotype", headers=headers)
    assert len(res.json()) == 1


async def test_calculate_genetics_combines_loci_and_warns_on_missing(client, plans, species, user_factory, db):
    _add_loci(db, species.id)
    user = user_factory(email="genetics-calc@plumora.com.br")
    headers = auth_headers(user)
    male_id = await _make_bird(client, headers, species.id, "M", "Macho")
    female_id = await _make_bird(client, headers, species.id, "F", "Femea")

    # dil (re): male visual (V/V), female not_carrier (+/+) -> all offspring split
    await client.post(f"/api/genetics/birds/{male_id}/genotype", headers=headers, json={"locus_id": "gl-dil", "allele_1": "V", "allele_2": "V"})
    await client.post(f"/api/genetics/birds/{female_id}/genotype", headers=headers, json={"locus_id": "gl-dil", "allele_1": "+", "allele_2": "+"})

    # cin (se): male carrier (V/+), female not_carrier (+) -> sex-linked split
    await client.post(f"/api/genetics/birds/{male_id}/genotype", headers=headers, json={"locus_id": "gl-cin", "allele_1": "V", "allele_2": "+"})
    await client.post(f"/api/genetics/birds/{female_id}/genotype", headers=headers, json={"locus_id": "gl-cin", "allele_1": "+"})

    # "op" locus is defined for the species but never genotyped on either bird -> warning expected

    res = await client.post(
        "/api/genetics/calculate",
        headers=headers,
        json={"male_bird_id": male_id, "female_bird_id": female_id},
    )
    assert res.status_code == 200
    data = res.json()

    assert any(w["locus"] == "op" for w in data["warnings"])
    assert data["results"], "expected at least one combined offspring outcome"
    assert all("dil" in r["splits"] for r in data["results"]), "dil locus should be 100% split in every outcome"
    sexes = {r["sex"] for r in data["results"]}
    assert sexes == {"M", "F"}, "cin sex-linkage should produce both son and daughter outcomes"


async def test_calculate_genetics_rejects_bird_not_owned(client, plans, species, user_factory, db):
    _add_loci(db, species.id)
    owner = user_factory(email="genetics-owner@plumora.com.br")
    other = user_factory(email="genetics-other@plumora.com.br")
    male_id = await _make_bird(client, auth_headers(owner), species.id, "M", "Macho")
    female_id = await _make_bird(client, auth_headers(owner), species.id, "F", "Femea")

    res = await client.post(
        "/api/genetics/calculate",
        headers=auth_headers(other),
        json={"male_bird_id": male_id, "female_bird_id": female_id},
    )
    assert res.status_code == 404


async def test_calculate_genetics_multi_allele_autosomal(client, plans, species, user_factory, db):
    """aabz/opl-style locus: two recessive mutant alleles (rank-ordered), both
    recessive to wild-type '+'. Regression test for the dispatch-order bug
    that used to route multi-allele loci through the plain binary V/+ punnett
    square instead of _punnett_multi_allele."""
    from app.models.genetics import GeneticLocus

    db.add(GeneticLocus(
        id="gl-opl", species_id=species.id, symbol="opl", name_pt="Opala/Onix", name_en="Opal/Onyx",
        inheritance_mode="re", is_multi_allele=True,
        alleles_json=[
            {"symbol": "opal", "name_pt": "Opala", "name_en": "Opal", "rank": 1},
            {"symbol": "onyx", "name_pt": "Onix", "name_en": "Onyx", "rank": 2},
        ],
    ))
    db.commit()

    user = user_factory(email="genetics-multi-re@plumora.com.br")
    headers = auth_headers(user)
    male_id = await _make_bird(client, headers, species.id, "M", "Macho")
    female_id = await _make_bird(client, headers, species.id, "F", "Femea")

    # Male visual opal (opal/opal), female visual onyx (onyx/onyx) -> all offspring compound het opal/onyx, visually opal (rank 1)
    await client.post(f"/api/genetics/birds/{male_id}/genotype", headers=headers, json={"locus_id": "gl-opl", "allele_1": "opal", "allele_2": "opal"})
    await client.post(f"/api/genetics/birds/{female_id}/genotype", headers=headers, json={"locus_id": "gl-opl", "allele_1": "onyx", "allele_2": "onyx"})

    res = await client.post(
        "/api/genetics/calculate",
        headers=headers,
        json={"male_bird_id": male_id, "female_bird_id": female_id, "min_probability": 0},
    )
    assert res.status_code == 200
    data = res.json()
    assert not any(w["locus"] == "opl" for w in data["warnings"])
    # opal (rank 1) beats onyx (rank 2) in the compound heterozygote -> visual opal, never "split"/"not_carrier"
    assert all("opl" in r["phenotype"] for r in data["results"])
    assert not any("opl" in r["splits"] for r in data["results"])


async def test_calculate_genetics_multi_allele_sex_linked(client, plans, species, user_factory, db):
    """ino/inopd-style locus: sex-linked multi-allele series. Daughters are
    hemizygous (ZW) so any mutant allele from the father is fully visual."""
    from app.models.genetics import GeneticLocus

    db.add(GeneticLocus(
        id="gl-ino", species_id=species.id, symbol="ino", name_pt="Ino/Pálido", name_en="Ino/Pallid",
        inheritance_mode="se", is_multi_allele=True,
        alleles_json=[
            {"symbol": "ino", "name_pt": "Ino", "name_en": "Ino", "rank": 1},
            {"symbol": "inopd", "name_pt": "Pálido", "name_en": "Pallid", "rank": 2},
        ],
    ))
    db.commit()

    user = user_factory(email="genetics-multi-se@plumora.com.br")
    headers = auth_headers(user)
    male_id = await _make_bird(client, headers, species.id, "M", "Macho")
    female_id = await _make_bird(client, headers, species.id, "F", "Femea")

    # Father visual pallid (inopd/inopd), mother +  -> all daughters visual pallid, all sons split
    await client.post(f"/api/genetics/birds/{male_id}/genotype", headers=headers, json={"locus_id": "gl-ino", "allele_1": "inopd", "allele_2": "inopd"})
    await client.post(f"/api/genetics/birds/{female_id}/genotype", headers=headers, json={"locus_id": "gl-ino", "allele_1": "+"})

    res = await client.post(
        "/api/genetics/calculate",
        headers=headers,
        json={"male_bird_id": male_id, "female_bird_id": female_id, "min_probability": 0},
    )
    assert res.status_code == 200
    data = res.json()
    daughters = [r for r in data["results"] if r["sex"] == "F"]
    sons = [r for r in data["results"] if r["sex"] == "M"]
    assert daughters and all("ino" in r["phenotype"] for r in daughters)
    assert sons and all("ino" in r["splits"] for r in sons)


async def test_pigeon_genotype_upsert(client, plans, user_factory, db):
    from app.models.bird import Species

    pigeon_species = Species(id="sp-pombo", common_name="Pombo Correio", scientific_name="Columba livia", category="pigeon")
    db.add(pigeon_species)
    db.commit()

    user = user_factory(email="genetics-pigeon@plumora.com.br")
    headers = auth_headers(user)
    bird_id = await _make_bird(client, headers, pigeon_species.id, "M", "Pombo")

    res = await client.post(
        f"/api/genetics/birds/{bird_id}/pigeon-genotype",
        headers=headers,
        json={"color_z1": "blue", "color_z2": "ash_red", "pattern_a1": "barless", "pattern_a2": "barless"},
    )
    assert res.status_code == 201

    res = await client.get(f"/api/genetics/birds/{bird_id}/pigeon-genotype", headers=headers)
    assert res.status_code == 200
    assert res.json()["pattern_a1"] == "barless"
