from fastapi import APIRouter, Depends, HTTPException
from fastapi.responses import HTMLResponse
from sqlalchemy.orm import Session
from typing import List
from jose import jwt, JWTError

from app.dependencies import get_db, get_current_user, require_role
from app.config import get_settings
from app.models.user import User
from app.models.marketing import EmailTemplate, Campaign, CampaignRecipient
from app.schemas.marketing import (
    EmailTemplateCreate,
    EmailTemplateOut,
    CampaignCreate,
    CampaignOut,
    SegmentPreviewOut,
)

settings = get_settings()

router = APIRouter(tags=["marketing"])
admin_router = APIRouter(prefix="/api/admin/marketing", tags=["marketing-admin"])


@admin_router.get("/templates", response_model=List[EmailTemplateOut])
def list_templates(db: Session = Depends(get_db), _=Depends(require_role("admin"))):
    return db.query(EmailTemplate).order_by(EmailTemplate.created_at.desc()).all()


@admin_router.post("/templates", response_model=EmailTemplateOut, status_code=201)
def create_template(
    body: EmailTemplateCreate,
    db: Session = Depends(get_db),
    current_user: User = Depends(require_role("admin")),
):
    template = EmailTemplate(created_by=current_user.id, **body.model_dump())
    db.add(template)
    db.commit()
    db.refresh(template)
    return template


@admin_router.get("/campaigns", response_model=List[CampaignOut])
def list_campaigns(db: Session = Depends(get_db), _=Depends(require_role("admin"))):
    return db.query(Campaign).order_by(Campaign.created_at.desc()).all()


@admin_router.get("/campaigns/{campaign_id}", response_model=CampaignOut)
def get_campaign(campaign_id: str, db: Session = Depends(get_db), _=Depends(require_role("admin"))):
    campaign = db.query(Campaign).filter(Campaign.id == campaign_id).first()
    if not campaign:
        raise HTTPException(404, "Campanha não encontrada")
    return campaign


@admin_router.post("/campaigns", response_model=CampaignOut, status_code=201)
def create_campaign(
    body: CampaignCreate,
    db: Session = Depends(get_db),
    current_user: User = Depends(require_role("admin")),
):
    data = body.model_dump()
    segment_filters = data.pop("segment_filters", None)
    campaign = Campaign(
        created_by=current_user.id,
        segment_filters_json=segment_filters,
        status="draft",
        **data,
    )
    db.add(campaign)
    db.commit()
    db.refresh(campaign)
    return campaign


@admin_router.post("/campaigns/{campaign_id}/preview", response_model=SegmentPreviewOut)
def preview_campaign_segment(campaign_id: str, db: Session = Depends(get_db), _=Depends(require_role("admin"))):
    from app.services.marketing import resolve_segment

    campaign = db.query(Campaign).filter(Campaign.id == campaign_id).first()
    if not campaign:
        raise HTTPException(404, "Campanha não encontrada")

    recipients = resolve_segment(db, campaign.segment_filters_json or {})
    count = len(recipients)
    return SegmentPreviewOut(
        recipient_count=count,
        exceeds_daily_limit=count > settings.marketing_daily_limit,
        daily_limit=settings.marketing_daily_limit,
    )


@admin_router.post("/campaigns/{campaign_id}/send", response_model=CampaignOut)
def send_campaign_endpoint(campaign_id: str, db: Session = Depends(get_db), _=Depends(require_role("admin"))):
    from app.services.marketing import resolve_segment
    from app.tasks.marketing_tasks import send_campaign

    campaign = db.query(Campaign).filter(Campaign.id == campaign_id).first()
    if not campaign:
        raise HTTPException(404, "Campanha não encontrada")
    if campaign.status != "draft":
        raise HTTPException(409, "Esta campanha já foi enviada ou está em andamento")

    recipients = resolve_segment(db, campaign.segment_filters_json or {})
    if not recipients:
        raise HTTPException(400, "Nenhum destinatário encontrado para os filtros deste segmento")

    for user in recipients:
        db.add(CampaignRecipient(campaign_id=campaign.id, user_id=user.id, email=user.email))

    campaign.status = "sending"
    campaign.total_recipients = len(recipients)
    db.commit()
    db.refresh(campaign)

    try:
        send_campaign.delay(campaign.id)
    except Exception:
        pass

    return campaign


@router.get("/api/marketing/unsubscribe", response_class=HTMLResponse)
def unsubscribe(token: str, db: Session = Depends(get_db)):
    try:
        payload = jwt.decode(token, settings.secret_key, algorithms=[settings.algorithm])
        if payload.get("type") != "unsubscribe":
            raise JWTError("wrong token type")
        user_id = payload.get("sub")
    except JWTError:
        return HTMLResponse("<h2>Link de descadastro inválido ou expirado.</h2>", status_code=400)

    user = db.query(User).filter(User.id == user_id).first()
    if not user:
        return HTMLResponse("<h2>Usuário não encontrado.</h2>", status_code=404)

    user.marketing_opt_out = True
    db.commit()

    return HTMLResponse(
        "<h2>Você foi removido(a) da nossa lista de e-mails de marketing.</h2>"
        "<p>Você continuará recebendo e-mails transacionais importantes sobre sua conta.</p>"
    )
