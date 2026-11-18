"""Auth: register, login, refresh, me, forgot/reset password."""
from jose import jwt

from app.config import get_settings

settings = get_settings()


async def _register(client, email="breeder1@plumora.com.br", password="TestPass123!"):
    return await client.post(
        "/api/auth/register",
        json={"name": "Test Breeder", "email": email, "password": password},
    )


async def test_register_and_login(client):
    res = await _register(client)
    assert res.status_code == 201
    data = res.json()
    assert data["email"] == "breeder1@plumora.com.br"
    assert data["role"] == "breeder"

    res = await client.post(
        "/api/auth/login",
        data={"username": "breeder1@plumora.com.br", "password": "TestPass123!"},
    )
    assert res.status_code == 200
    tokens = res.json()
    assert "access_token" in tokens
    assert "refresh_token" in tokens

    res = await client.get("/api/auth/me", headers={"Authorization": f"Bearer {tokens['access_token']}"})
    assert res.status_code == 200
    assert res.json()["email"] == "breeder1@plumora.com.br"


async def test_register_rejects_admin_role(client):
    """Self-registration must never grant admin — only breeder/vet are selectable at
    signup (see RegisterPage.tsx's role dropdown). Admin accounts are created out-of-band."""
    res = await client.post(
        "/api/auth/register",
        json={"name": "Invasor", "email": "invasor@plumora.com.br", "password": "TestPass123!", "role": "admin"},
    )
    assert res.status_code == 422


async def test_register_allows_vet_role(client):
    res = await client.post(
        "/api/auth/register",
        json={"name": "Vet Teste", "email": "vet-signup@plumora.com.br", "password": "TestPass123!", "role": "vet"},
    )
    assert res.status_code == 201
    assert res.json()["role"] == "vet"


async def test_register_duplicate_email_rejected(client):
    await _register(client, email="dup@plumora.com.br")
    res = await _register(client, email="dup@plumora.com.br")
    assert res.status_code == 400


async def test_login_with_wrong_password_rejected(client):
    await _register(client, email="wrongpass@plumora.com.br")
    res = await client.post(
        "/api/auth/login",
        data={"username": "wrongpass@plumora.com.br", "password": "NotTheRightOne!"},
    )
    assert res.status_code == 401


async def test_me_requires_valid_token(client):
    res = await client.get("/api/auth/me", headers={"Authorization": "Bearer not-a-real-token"})
    assert res.status_code == 401


async def test_refresh_token_issues_new_access_token(client):
    await _register(client, email="refresh@plumora.com.br")
    login = await client.post(
        "/api/auth/login",
        data={"username": "refresh@plumora.com.br", "password": "TestPass123!"},
    )
    refresh_token = login.json()["refresh_token"]

    res = await client.post("/api/auth/refresh", json={"refresh_token": refresh_token})
    assert res.status_code == 200
    assert "access_token" in res.json()


async def test_refresh_rejects_access_token_used_as_refresh(client):
    await _register(client, email="refreshmix@plumora.com.br")
    login = await client.post(
        "/api/auth/login",
        data={"username": "refreshmix@plumora.com.br", "password": "TestPass123!"},
    )
    access_token = login.json()["access_token"]

    res = await client.post("/api/auth/refresh", json={"refresh_token": access_token})
    assert res.status_code == 401


async def test_forgot_password_does_not_leak_account_existence(client):
    await _register(client, email="known@plumora.com.br")
    res_known = await client.post("/api/auth/forgot-password", json={"email": "known@plumora.com.br"})
    res_unknown = await client.post("/api/auth/forgot-password", json={"email": "ghost@plumora.com.br"})
    assert res_known.status_code == 202
    assert res_unknown.status_code == 202
    assert res_known.json() == res_unknown.json()


async def test_forgot_password_then_reset_password_end_to_end(client, monkeypatch):
    """The reset token is delivered by email (send_password_reset_email), never via the
    API response. We capture the task call args the way the real email body would."""
    captured = {}

    def _capture_delay(user_email, user_name, reset_token):
        captured["reset_token"] = reset_token

    from app.tasks.notification_tasks import send_password_reset_email
    monkeypatch.setattr(send_password_reset_email, "delay", _capture_delay)

    await _register(client, email="resetflow@plumora.com.br", password="OldPass123!")

    forgot_res = await client.post("/api/auth/forgot-password", json={"email": "resetflow@plumora.com.br"})
    assert forgot_res.status_code == 202
    assert captured.get("reset_token"), "forgot-password should dispatch an email task carrying a reset token"

    reset_res = await client.post(
        "/api/auth/reset-password",
        json={"token": captured["reset_token"], "new_password": "NewPass456!"},
    )
    assert reset_res.status_code == 200

    login_res = await client.post(
        "/api/auth/login",
        data={"username": "resetflow@plumora.com.br", "password": "NewPass456!"},
    )
    assert login_res.status_code == 200


async def test_reset_password_rejects_garbage_token(client):
    res = await client.post(
        "/api/auth/reset-password",
        json={"token": "not-a-jwt", "new_password": "NewPass456!"},
    )
    assert res.status_code == 400


async def test_reset_password_rejects_non_reset_token_types(client, db, user_factory):
    """An access token (never intended for password resets) must not work here."""
    user = user_factory(email="typeconfusion@plumora.com.br")
    access_token = jwt.encode(
        {"sub": user.id, "type": "access"}, settings.secret_key, algorithm=settings.algorithm
    )

    res = await client.post(
        "/api/auth/reset-password",
        json={"token": access_token, "new_password": "NewPass456!"},
    )
    assert res.status_code == 401
