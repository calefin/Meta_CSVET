from celery import Celery
from app.config import get_settings

settings = get_settings()
celery_app = Celery(
    "plumora",
    broker=settings.redis_url,
    backend=settings.redis_url,
    # The worker process is started as `celery -A app.tasks.pdf_tasks.celery_app worker`
    # (docker-compose.yml) — it only imports THIS module by default. `include` tells the
    # worker to also import these sibling task modules at startup so their @celery_app.task
    # decorators actually run and register in this app's task registry; without it, tasks
    # defined there are invisible to the worker even though they import the same celery_app
    # instance ("Received unregistered task", confirmed empirically).
    include=["app.tasks.notification_tasks", "app.tasks.marketing_tasks"],
)
celery_app.conf.update(
    task_ignore_result=True,
    broker_connection_timeout=2,
    broker_connection_retry=False,
    broker_connection_retry_on_startup=False,
    broker_connection_max_retries=0,
    task_publish_retry=False,
)


@celery_app.task(name="generate_pedigree_pdf")
def generate_pedigree_pdf(bird_id: str, generations: int, user_id: str):
    from app.database import SessionLocal
    from app.services.pedigree_gen import export_pedigree_pdf
    from app.models.race import PedigreeExport
    db = SessionLocal()
    try:
        url = export_pedigree_pdf(db, bird_id, generations, user_id)
        export = db.query(PedigreeExport).filter(
            PedigreeExport.bird_id == bird_id,
            PedigreeExport.requested_by_user_id == user_id,
        ).order_by(PedigreeExport.generated_at.desc()).first()
        if export:
            export.file_url = url
            db.commit()
        return {"url": url, "bird_id": bird_id}
    finally:
        db.close()
