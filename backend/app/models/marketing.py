import uuid
from datetime import datetime
from sqlalchemy import Column, String, Integer, Text, DateTime, ForeignKey, JSON
from sqlalchemy.orm import relationship
from app.database import Base


class EmailTemplate(Base):
    __tablename__ = "email_templates"

    id = Column(String, primary_key=True, default=lambda: str(uuid.uuid4()))
    name = Column(String(200), nullable=False)
    subject = Column(String(300), nullable=False)
    html_body = Column(Text, nullable=False)
    created_by = Column(String, ForeignKey("users.id"), nullable=False)
    created_at = Column(DateTime, default=datetime.utcnow)
    updated_at = Column(DateTime, default=datetime.utcnow, onupdate=datetime.utcnow)


class Campaign(Base):
    __tablename__ = "campaigns"

    id = Column(String, primary_key=True, default=lambda: str(uuid.uuid4()))
    name = Column(String(200), nullable=False)
    template_id = Column(String, ForeignKey("email_templates.id"), nullable=True)
    subject = Column(String(300), nullable=False)
    html_body = Column(Text, nullable=False)
    from_address = Column(String(200), nullable=False)
    segment_filters_json = Column(JSON, nullable=True)
    status = Column(String(20), nullable=False, default="draft")
    # draft|sending|sent|failed
    total_recipients = Column(Integer, nullable=False, default=0)
    total_sent = Column(Integer, nullable=False, default=0)
    total_failed = Column(Integer, nullable=False, default=0)
    created_by = Column(String, ForeignKey("users.id"), nullable=False)
    created_at = Column(DateTime, default=datetime.utcnow)
    sent_at = Column(DateTime, nullable=True)

    template = relationship("EmailTemplate")
    recipients = relationship("CampaignRecipient", back_populates="campaign")


class CampaignRecipient(Base):
    __tablename__ = "campaign_recipients"

    id = Column(String, primary_key=True, default=lambda: str(uuid.uuid4()))
    campaign_id = Column(String, ForeignKey("campaigns.id"), nullable=False)
    user_id = Column(String, ForeignKey("users.id"), nullable=False)
    email = Column(String(255), nullable=False)
    status = Column(String(20), nullable=False, default="pending")
    # pending|sent|failed
    error_message = Column(Text, nullable=True)
    sent_at = Column(DateTime, nullable=True)

    campaign = relationship("Campaign", back_populates="recipients")
    user = relationship("User", foreign_keys=[user_id])
