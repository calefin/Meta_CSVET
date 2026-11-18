import uuid
from datetime import datetime
from sqlalchemy import Column, String, Float, DateTime, ForeignKey, JSON
from app.database import Base


class MPWebhookEvent(Base):
    """Raw log of every Mercado Pago webhook event received — used for idempotency
    (has this external_id already been processed as approved?) and for admin debugging."""
    __tablename__ = "mp_webhook_events"

    id = Column(String, primary_key=True, default=lambda: str(uuid.uuid4()))
    type = Column(String(50), nullable=False)
    action = Column(String(50), nullable=True)
    status = Column(String(30), nullable=True)
    user_id = Column(String, ForeignKey("users.id"), nullable=True)
    external_id = Column(String(100), nullable=True)
    amount = Column(Float, nullable=True)
    plan_id = Column(String, ForeignKey("plans.id"), nullable=True)
    raw = Column(JSON, nullable=True)
    created_at = Column(DateTime, default=datetime.utcnow)
