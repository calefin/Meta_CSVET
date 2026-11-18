"""Profile: get/update, password change, subscribe, photo upload stub."""
from tests.conftest import auth_headers


async def test_get_and_update_profile(client, plans, user_factory):
    user = user_factory(email="profile1@plumora.com.br")
    headers = auth_headers(user)

    res = await client.get("/api/profile", headers=headers)
    assert res.status_code == 200
    assert res.json()["email"] == "profile1@plumora.com.br"
    assert res.json()["plan"]["name"] == "free"

    res = await client.put(
        "/api/profile",
        headers=headers,
        json={"full_name": "Maria da Silva", "phone": "+5511999999999"},
    )
    assert res.status_code == 200

    res = await client.get("/api/profile", headers=headers)
    assert res.json()["full_name"] == "Maria da Silva"


async def test_change_password_requires_correct_current_password(client, plans, user_factory):
    user = user_factory(email="pwchange@plumora.com.br", password="OldPass123!")
    headers = auth_headers(user)

    res = await client.put(
        "/api/profile/password",
        headers=headers,
        json={"current_password": "WrongOldPass!", "new_password": "NewPass456!"},
    )
    assert res.status_code == 400

    res = await client.put(
        "/api/profile/password",
        headers=headers,
        json={"current_password": "OldPass123!", "new_password": "NewPass456!"},
    )
    assert res.status_code == 200

    login = await client.post(
        "/api/auth/login",
        data={"username": "pwchange@plumora.com.br", "password": "NewPass456!"},
    )
    assert login.status_code == 200


async def test_change_password_rejects_short_password(client, plans, user_factory):
    user = user_factory(email="pwshort@plumora.com.br", password="OldPass123!")
    headers = auth_headers(user)

    res = await client.put(
        "/api/profile/password",
        headers=headers,
        json={"current_password": "OldPass123!", "new_password": "short"},
    )
    assert res.status_code == 422


async def test_subscribe_requires_profile_fields_and_terms(client, plans, user_factory, monkeypatch):
    import app.services.mercadopago_client as mp_client

    monkeypatch.setattr(
        mp_client,
        "create_preapproval",
        lambda user, plan, back_url: {"preapproval_id": "preap-fake", "checkout_url": "https://mp.example/checkout/fake"},
    )

    user = user_factory(email="subscribe1@plumora.com.br")
    headers = auth_headers(user)

    res = await client.post(
        "/api/profile/subscribe",
        headers=headers,
        json={"plan_id": "plan-basic", "terms_accepted": True},
    )
    assert res.status_code == 400
    assert "cpf_cnpj" in res.json()["detail"]

    await client.put(
        "/api/profile",
        headers=headers,
        json={
            "full_name": "João Criador",
            "cpf_cnpj": "111.444.777-35",
            "address_street": "Rua das Aves",
            "address_city": "São Paulo",
            "address_state": "SP",
            "address_zip": "01000-000",
        },
    )

    res = await client.post(
        "/api/profile/subscribe",
        headers=headers,
        json={"plan_id": "plan-basic", "terms_accepted": False},
    )
    assert res.status_code == 400

    res = await client.post(
        "/api/profile/subscribe",
        headers=headers,
        json={"plan_id": "plan-basic", "terms_accepted": True},
    )
    assert res.status_code == 201
    assert res.json()["plan"] == "basic"
    # Plano pago não ativa na hora — devolve o checkout do Mercado Pago; só o webhook ativa de fato.
    assert res.json()["checkout_url"] == "https://mp.example/checkout/fake"


async def test_subscribe_rejects_invalid_cpf(client, plans, user_factory):
    user = user_factory(email="badcpf@plumora.com.br")
    headers = auth_headers(user)

    await client.put(
        "/api/profile",
        headers=headers,
        json={
            "full_name": "Pessoa Teste",
            "cpf_cnpj": "111.111.111-11",
            "address_street": "Rua X",
            "address_city": "Cidade",
            "address_state": "SP",
            "address_zip": "00000-000",
        },
    )

    res = await client.post(
        "/api/profile/subscribe",
        headers=headers,
        json={"plan_id": "plan-basic", "terms_accepted": True},
    )
    assert res.status_code == 400
    assert "CPF" in res.json()["detail"]


async def test_photo_upload_not_implemented_yet(client, plans, user_factory):
    user = user_factory(email="photo@plumora.com.br")
    res = await client.post("/api/profile/photo", headers=auth_headers(user))
    assert res.status_code == 501
