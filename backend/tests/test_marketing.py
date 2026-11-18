"""Marketing: campaign segmentation, RBAC, sending (Gmail SMTP mocked), and unsubscribe."""
from tests.conftest import auth_headers


async def test_resolve_segment_filters_by_role_and_opt_out(db, plans, user_factory):
    from app.services.marketing import resolve_segment

    breeder = user_factory(email="seg-breeder@plumora.com.br", role="breeder")
    user_factory(email="seg-vet@plumora.com.br", role="vet")
    opted_out = user_factory(email="seg-optout@plumora.com.br", role="breeder")
    opted_out.marketing_opt_out = True
    db.add(opted_out)
    db.commit()

    result = resolve_segment(db, {"roles": ["breeder"]})
    emails = {u.email for u in result}
    assert breeder.email in emails
    assert "seg-vet@plumora.com.br" not in emails
    assert "seg-optout@plumora.com.br" not in emails


async def test_create_campaign_requires_admin(client, plans, user_factory):
    breeder = user_factory(email="mk-breeder@plumora.com.br", role="breeder")
    res = await client.post(
        "/api/admin/marketing/campaigns",
        headers=auth_headers(breeder),
        json={"name": "Teste", "subject": "Assunto", "html_body": "<p>Oi</p>", "from_address": "contato@csvet.com.br"},
    )
    assert res.status_code == 403


async def test_preview_segment_counts_recipients_and_flags_daily_limit(client, plans, user_factory):
    from app.config import get_settings

    admin = user_factory(email="mk-admin1@plumora.com.br", role="admin")
    user_factory(email="mk-r1@plumora.com.br", role="breeder")
    user_factory(email="mk-r2@plumora.com.br", role="breeder")

    original_limit = get_settings().marketing_daily_limit
    get_settings().marketing_daily_limit = 1
    try:
        res = await client.post(
            "/api/admin/marketing/campaigns",
            headers=auth_headers(admin),
            json={
                "name": "Campanha",
                "subject": "Oi",
                "html_body": "<p>Oi</p>",
                "from_address": "contato@csvet.com.br",
                "segment_filters": {"roles": ["breeder"]},
            },
        )
        assert res.status_code == 201
        campaign_id = res.json()["id"]

        res = await client.post(f"/api/admin/marketing/campaigns/{campaign_id}/preview", headers=auth_headers(admin))
        assert res.status_code == 200
        data = res.json()
        assert data["recipient_count"] == 2
        assert data["exceeds_daily_limit"] is True
    finally:
        get_settings().marketing_daily_limit = original_limit


async def test_send_campaign_marks_recipients_sent(client, plans, user_factory, db, monkeypatch):
    admin = user_factory(email="mk-admin2@plumora.com.br", role="admin")
    user_factory(email="mk-send1@plumora.com.br", role="breeder")
    user_factory(email="mk-send2@plumora.com.br", role="breeder")

    res = await client.post(
        "/api/admin/marketing/campaigns",
        headers=auth_headers(admin),
        json={
            "name": "Campanha2",
            "subject": "Oi",
            "html_body": "<p>Oi</p>",
            "from_address": "contato@csvet.com.br",
            "segment_filters": {"roles": ["breeder"]},
        },
    )
    campaign_id = res.json()["id"]

    res = await client.post(f"/api/admin/marketing/campaigns/{campaign_id}/send", headers=auth_headers(admin))
    assert res.status_code == 200
    assert res.json()["status"] == "sending"
    assert res.json()["total_recipients"] == 2

    class FakeSMTP:
        def __init__(self, *a, **k):
            pass

        def starttls(self):
            pass

        def login(self, *a, **k):
            pass

        def sendmail(self, *a, **k):
            pass

        def quit(self):
            pass

    import smtplib
    monkeypatch.setattr(smtplib, "SMTP", FakeSMTP)

    # O task usa `app.database.SessionLocal` internamente (não a dependency `get_db`
    # sobrescrita pelo FastAPI) — sem isso ele bateria no Postgres real configurado em
    # settings, não no SQLite isolado deste teste.
    from tests.conftest import TestSessionLocal
    monkeypatch.setattr("app.database.SessionLocal", TestSessionLocal)

    # .delay() só publica no broker — sem worker rodando no teste, chamamos o task
    # diretamente (padrão usual de teste de Celery) para validar a lógica de envio.
    from app.tasks.marketing_tasks import send_campaign
    send_campaign(campaign_id)

    from app.models.marketing import Campaign, CampaignRecipient
    campaign = db.query(Campaign).filter(Campaign.id == campaign_id).first()
    assert campaign.status == "sent"
    assert campaign.total_sent == 2
    assert campaign.total_failed == 0
    recipients = db.query(CampaignRecipient).filter(CampaignRecipient.campaign_id == campaign_id).all()
    assert len(recipients) == 2
    assert all(r.status == "sent" for r in recipients)


async def test_send_campaign_requires_recipients(client, plans, user_factory):
    admin = user_factory(email="mk-admin3@plumora.com.br", role="admin")
    res = await client.post(
        "/api/admin/marketing/campaigns",
        headers=auth_headers(admin),
        json={
            "name": "Campanha vazia",
            "subject": "Oi",
            "html_body": "<p>Oi</p>",
            "from_address": "contato@csvet.com.br",
            "segment_filters": {"roles": ["vet"]},
        },
    )
    campaign_id = res.json()["id"]
    res = await client.post(f"/api/admin/marketing/campaigns/{campaign_id}/send", headers=auth_headers(admin))
    assert res.status_code == 400


async def test_unsubscribe_endpoint_opts_out_user(client, plans, user_factory, db):
    from app.utils.security import create_unsubscribe_token
    from app.services.marketing import resolve_segment

    user = user_factory(email="mk-unsub@plumora.com.br", role="breeder")
    token = create_unsubscribe_token(user.id)

    res = await client.get("/api/marketing/unsubscribe", params={"token": token})
    assert res.status_code == 200
    assert "removido" in res.text.lower()

    remaining = resolve_segment(db, {"roles": ["breeder"]})
    assert user.email not in {u.email for u in remaining}


async def test_unsubscribe_rejects_wrong_token_type(client, plans, user_factory):
    from app.utils.security import create_reset_token

    user = user_factory(email="mk-wrongtoken@plumora.com.br", role="breeder")
    token = create_reset_token(user.id)  # type="reset", not "unsubscribe"

    res = await client.get("/api/marketing/unsubscribe", params={"token": token})
    assert res.status_code == 400
