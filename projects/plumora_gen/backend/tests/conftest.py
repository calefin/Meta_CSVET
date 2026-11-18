import os
import sys

sys.path.insert(0, os.path.join(os.path.dirname(__file__), ".."))

import pytest
import pytest_asyncio
from httpx import ASGITransport, AsyncClient
from sqlalchemy import create_engine
from sqlalchemy.orm import sessionmaker
from sqlalchemy.pool import StaticPool

from app.database import Base
from app.dependencies import get_db
from app.limiter import limiter
from app.main import app
from app.models.bird import Species
from app.models.user import Plan, User
from app.utils.security import create_access_token, hash_password

engine = create_engine(
    "sqlite://",
    connect_args={"check_same_thread": False},
    poolclass=StaticPool,
)
TestSessionLocal = sessionmaker(autocommit=False, autoflush=False, bind=engine)


def _override_get_db():
    session = TestSessionLocal()
    try:
        yield session
    finally:
        session.close()


app.dependency_overrides[get_db] = _override_get_db


@pytest.fixture(autouse=True)
def reset_database():
    Base.metadata.drop_all(bind=engine)
    Base.metadata.create_all(bind=engine)
    yield


@pytest.fixture(autouse=True)
def reset_rate_limiter():
    """The limiter is a process-wide singleton; without resetting it, login/register
    attempts from earlier tests would count against later tests' rate limit window."""
    limiter._storage.reset()
    yield


@pytest.fixture(autouse=True)
def _stub_celery_dispatch(monkeypatch):
    """Requesting a vet consult or a password reset fires a Celery .delay() call, which
    blocks for minutes retrying a Redis connection when no broker is reachable (as in
    tests). Stub these out so the suite doesn't hang on infra that isn't part of this test."""
    from app.tasks.notification_tasks import notify_vet_consult_email, send_password_reset_email

    monkeypatch.setattr(notify_vet_consult_email, "delay", lambda *a, **k: None)
    monkeypatch.setattr(send_password_reset_email, "delay", lambda *a, **k: None)


@pytest.fixture
def db():
    session = TestSessionLocal()
    try:
        yield session
    finally:
        session.close()


@pytest_asyncio.fixture
async def client():
    async with AsyncClient(transport=ASGITransport(app=app), base_url="http://test") as c:
        yield c


@pytest.fixture
def plans(db):
    rows = [
        Plan(id="plan-free", name="free", max_birds=10, price_monthly=0, price_yearly=0),
        Plan(id="plan-basic", name="basic", max_birds=50, price_monthly=1990, price_yearly=19900),
        Plan(id="plan-pro", name="pro", max_birds=200, price_monthly=4990, price_yearly=49900),
        Plan(id="plan-premium", name="premium", max_birds=9999, price_monthly=8990, price_yearly=89900),
    ]
    for row in rows:
        db.add(row)
    db.commit()
    return {row.name: row for row in rows}


@pytest.fixture
def species(db):
    sp = Species(
        id="sp-calopsita",
        common_name="Calopsita",
        scientific_name="Nymphicus hollandicus",
        category="ornamental",
        avg_lifespan_years=20,
        incubation_days=21,
        clutch_size_min=4,
        clutch_size_max=8,
    )
    db.add(sp)
    db.commit()
    return sp


@pytest.fixture
def user_factory(db):
    def _factory(*, email, role="breeder", plan_id="plan-free", name="Test User", password="TestPass123!"):
        user = User(
            email=email,
            password_hash=hash_password(password),
            name=name,
            role=role,
            plan_id=plan_id,
        )
        db.add(user)
        db.commit()
        db.refresh(user)
        return user

    return _factory


def auth_headers(user: User) -> dict:
    return {"Authorization": f"Bearer {create_access_token(user.id)}"}
