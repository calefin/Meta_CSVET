from app.config import get_settings
# Reuses the single Celery app instance from pdf_tasks.py — the worker process is started
# as `celery -A app.tasks.pdf_tasks.celery_app worker` (docker-compose.yml), so a task
# registered on any *other* `Celery(...)` instance is invisible to that worker and gets
# silently discarded ("Received unregistered task", confirmed empirically). Do not create
# a separate `Celery(...)` here again.
from app.tasks.pdf_tasks import celery_app

settings = get_settings()


@celery_app.task(name="send_password_reset_email")
def send_password_reset_email(user_email: str, user_name: str, reset_token: str):
    """Send the password reset link (token carried in the URL) via Resend."""
    try:
        import resend
        resend.api_key = settings.smtp_password
        reset_url = f"{settings.frontend_url}/reset-password?token={reset_token}"
        resend.Emails.send({
            "from": settings.email_from,
            "to": [user_email],
            "subject": "Redefinição de senha — Plumora Gen",
            "html": (
                f"<p>Olá, {user_name}!</p>"
                f"<p>Recebemos uma solicitação para redefinir sua senha. Clique no link abaixo (válido por 1 hora):</p>"
                f"<p><a href=\"{reset_url}\">{reset_url}</a></p>"
                f"<p>Se você não solicitou, ignore este e-mail.</p>"
            ),
        })
    except Exception as e:
        print(f"send_password_reset_email failed: {e}")


@celery_app.task(name="send_vaccine_reminder")
def send_vaccine_reminder(user_email: str, bird_name: str, vaccine_name: str, due_date: str):
    """Send vaccine due reminder email via Resend/SendGrid."""
    try:
        import resend
        resend.api_key = settings.smtp_password
        resend.Emails.send({
            "from": settings.email_from,
            "to": [user_email],
            "subject": f"Lembrete de vacinação — {bird_name}",
            "html": f"<p>Olá! A vacina <b>{vaccine_name}</b> de <b>{bird_name}</b> vence em <b>{due_date}</b>.</p><p>Acesse o Plumora para registrar a aplicação.</p>",
        })
    except Exception as e:
        print(f"Email send failed: {e}")


@celery_app.task(name="notify_vet_new_consult")
def notify_vet_new_consult(vet_email: str, requester_name: str, bird_name: str, consult_id: str):
    try:
        import resend
        resend.api_key = settings.smtp_password
        resend.Emails.send({
            "from": settings.email_from,
            "to": [vet_email],
            "subject": f"Nova consulta PigeonVet — {bird_name}",
            "html": f"<p>{requester_name} abriu uma consulta para <b>{bird_name}</b>. Acesse o painel PigeonVet para atender.</p>",
        })
    except Exception as e:
        print(f"Email send failed: {e}")


@celery_app.task(name="notify_vet_consult_email")
def notify_vet_consult_email(consult_id: str):
    """Send email to vet (calefi@csvet.com.br) and to requester when a vet consult is created."""
    try:
        from app.database import SessionLocal
        from app.models.health import VetConsultRequest
        from app.models.bird import Bird
        from app.models.user import User
        import resend
        resend.api_key = settings.smtp_password

        db = SessionLocal()
        try:
            consult = db.query(VetConsultRequest).filter(VetConsultRequest.id == consult_id).first()
            if not consult:
                return
            bird = db.query(Bird).filter(Bird.id == consult.bird_id).first()
            requester = db.query(User).filter(User.id == consult.requester_user_id).first()
            bird_name = bird.name if bird else "Ave desconhecida"
            requester_name = requester.name if requester else "Criador"
            requester_email = requester.email if requester else None

            appointment_type = getattr(consult, "appointment_type", "telemedicine")
            scheduled_date = getattr(consult, "scheduled_date", None) or "Não definida"
            description = consult.description or "Sem descrição"
            priority = consult.priority

            # Email to vet
            resend.Emails.send({
                "from": settings.email_from,
                "to": ["calefi@csvet.com.br"],
                "subject": f"Novo Chamado PigeonVet — Plumora Gen",
                "html": (
                    f"<h2>Novo Chamado PigeonVet</h2>"
                    f"<p><b>Ave:</b> {bird_name}</p>"
                    f"<p><b>Criador:</b> {requester_name}</p>"
                    f"<p><b>Prioridade:</b> {priority}</p>"
                    f"<p><b>Tipo:</b> {'Telemedicina' if appointment_type == 'telemedicine' else 'Presencial'}</p>"
                    f"<p><b>Data agendada:</b> {scheduled_date}</p>"
                    f"<p><b>Descrição:</b> {description}</p>"
                    f"<p>Acesse o painel PigeonVet para atender este chamado.</p>"
                ),
            })

            # Email to requester
            if requester_email:
                resend.Emails.send({
                    "from": settings.email_from,
                    "to": [requester_email],
                    "subject": "Chamado PigeonVet recebido",
                    "html": (
                        f"<h2>Chamado PigeonVet recebido com sucesso!</h2>"
                        f"<p>Olá, {requester_name}!</p>"
                        f"<p>Seu chamado para a ave <b>{bird_name}</b> foi recebido.</p>"
                        f"<p><b>Tipo:</b> {'Telemedicina' if appointment_type == 'telemedicine' else 'Presencial'}</p>"
                        f"<p><b>Data agendada:</b> {scheduled_date}</p>"
                        f"<p>O Prof. Dr. Atilio Calefi irá analisar seu chamado em breve.</p>"
                        f"<p>Acesse o Plumora Gen para acompanhar o status.</p>"
                    ),
                })
        finally:
            db.close()
    except Exception as e:
        print(f"notify_vet_consult_email failed: {e}")
