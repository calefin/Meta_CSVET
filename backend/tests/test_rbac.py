"""Cross-cutting RBAC matrix and auth rate limiting."""
import pytest

from tests.conftest import auth_headers


@pytest.mark.parametrize("role", ["breeder", "vet"])
async def test_admin_only_user_status_toggle_rejected_for_other_roles(client, plans, user_factory, role):
    actor = user_factory(email=f"rbac-{role}@plumora.com.br", role=role)
    target = user_factory(email=f"rbac-target-{role}@plumora.com.br")

    res = await client.put(
        f"/api/admin/users/{target.id}/status",
        headers=auth_headers(actor),
        params={"is_active": False},
    )
    assert res.status_code == 403


async def test_breeder_cannot_access_vet_patients_endpoint(client, plans, user_factory):
    breeder = user_factory(email="rbac-patients@plumora.com.br")
    res = await client.get("/api/vet/patients", headers=auth_headers(breeder))
    assert res.status_code == 403


async def test_vet_can_access_own_patients_endpoint(client, plans, user_factory):
    vet = user_factory(email="rbac-vet-patients@plumora.com.br", role="vet")
    res = await client.get("/api/vet/patients", headers=auth_headers(vet))
    assert res.status_code == 200
    assert res.json() == []


async def test_login_is_rate_limited_after_repeated_failures(client, plans, user_factory):
    user_factory(email="bruteforce@plumora.com.br", password="CorrectPass123!")

    last_status = None
    for _ in range(20):
        res = await client.post(
            "/api/auth/login",
            data={"username": "bruteforce@plumora.com.br", "password": "WrongGuess!"},
        )
        last_status = res.status_code

    assert last_status == 429
