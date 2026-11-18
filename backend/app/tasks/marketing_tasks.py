import smtplib
import time
from datetime import datetime
from email.mime.multipart import MIMEMultipart
from email.mime.text import MIMEText

from app.config import get_settings
from app.tasks.pdf_tasks import celery_app

settings = get_settings()

SEND_DELAY_SECONDS = 1.5


@celery_app.task(name="send_campaign")
def send_campaign(campaign_id: str):
    """Send a marketing campaign via Gmail SMTP — separate provider/credentials from the
    Resend-based transactional emails in notification_tasks.py, by design."""
    from app.database import SessionLocal
    from app.models.marketing import Campaign, CampaignRecipient
    from app.utils.security import create_unsubscribe_token

    db = SessionLocal()
    try:
        campaign = db.query(Campaign).filter(Campaign.id == campaign_id).first()
        if not campaign:
            return

        recipients = (
            db.query(CampaignRecipient)
            .filter(CampaignRecipient.campaign_id == campaign_id, CampaignRecipient.status == "pending")
            .all()
        )

        try:
            server = smtplib.SMTP("smtp.gmail.com", 587, timeout=10)
            server.starttls()
            server.login(settings.gmail_smtp_user, settings.gmail_app_password)
        except Exception as e:
            print(f"send_campaign: SMTP login failed: {e}")
            campaign.status = "failed"
            db.commit()
            return

        sent, failed = 0, 0
        for r in recipients:
            try:
                unsubscribe_token = create_unsubscribe_token(r.user_id)
                unsubscribe_url = f"{settings.frontend_url}/api/marketing/unsubscribe?token={unsubscribe_token}"
                html = (
                    campaign.html_body
                    + '<hr style="margin-top:24px;border:none;border-top:1px solid #ddd">'
                    + f'<p style="font-size:11px;color:#999">Não quer mais receber esses e-mails? '
                    + f'<a href="{unsubscribe_url}">Cancelar inscrição</a></p>'
                )
                msg = MIMEMultipart("alternative")
                msg["Subject"] = campaign.subject
                msg["From"] = campaign.from_address
                msg["To"] = r.email
                msg.attach(MIMEText(html, "html"))

                server.sendmail(campaign.from_address, [r.email], msg.as_string())
                r.status = "sent"
                r.sent_at = datetime.utcnow()
                sent += 1
            except Exception as e:
                r.status = "failed"
                r.error_message = str(e)
                failed += 1
            db.commit()
            time.sleep(SEND_DELAY_SECONDS)

        try:
            server.quit()
        except Exception:
            pass

        campaign.total_sent = sent
        campaign.total_failed = failed
        campaign.status = "sent"
        campaign.sent_at = datetime.utcnow()
        db.commit()
    finally:
        db.close()
