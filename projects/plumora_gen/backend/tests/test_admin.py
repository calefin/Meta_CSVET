"""Admin panel: every endpoint must be admin-only."""
from tests.conftest import auth_headers

ADMIN_ENDPOINTS = [
    ("GET", "/api/admin/users"),
    ("GET", "/api/admin/subscriptions"),
    ("GET", "/api/admin/dashboard"),
]


async def test_non_admin_roles_are_rejected(client, plans, user_factory):
    breeder = user_factory(email="admin-breeder@plumora.com.br", role="breeder")
    vet = user_factory(email="admin-vet@plumora.com.br", role="vet")

    for role_user in (breeder, vet):
        for method, path in ADMIN_ENDPOINTS:
            res = await client.request(method, path, headers=auth_headers(role_user))
            assert res.status_code == 403, f"{method} {path} should reject role={role_user.role}"


async def test_admin_can_access_dashboard_and_manage_users(client, plans, species, user_factory, db):
    from app.models.bird import Bird

    admin = user_factory(email="admin1@plumora.com.br", role="admin")
    breeder = user_factory(email="admin-target@plumora.com.br", role="breeder")
    db.add(Bird(user_id=breeder.id, name="Ave", species_id=species.id))
    db.commit()
    headers = auth_headers(admin)

    res = await client.get("/api/admin/dashboard", headers=headers)
    assert res.status_code == 200
    assert res.json()["total_users"] >= 2
    assert res.json()["total_birds"] == 1

    res = await client.get("/api/admin/users", headers=headers)
    assert res.status_code == 200
    assert any(u["email"] == "admin-target@plumora.com.br" for u in res.json())

    res = await client.put(
        f"/api/admin/users/{breeder.id}/status",
        headers=headers,
        params={"is_active": False},
    )
    assert res.status_code == 200
    assert res.json()["is_active"] is False

    login = await client.post(
        "/api/auth/login",
        data={"username": "admin-target@plumora.com.br", "password": "TestPass123!"},
    )
    assert login.status_code == 401
