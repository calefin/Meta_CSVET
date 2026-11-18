import uuid
from datetime import datetime
from sqlalchemy import Column, String, Boolean, DateTime, ForeignKey, Float, Text, JSON
from sqlalchemy.orm import relationship
from app.database import Base


class HealthRecord(Base):
    __tablename__ = "health_records"

    id = Column(String, primary_key=True, default=lambda: str(uuid.uuid4()))
    bird_id = Column(String, ForeignKey("birds.id"), nullable=False)
    user_id = Column(String, ForeignKey("users.id"), nullable=False)
    date = Column(DateTime, nullable=False, default=datetime.utcnow)
    weight_grams = Column(Float, nullable=True)
    temperature_celsius = Column(Float, nullable=True)
    droppings_aspect = Column(String(20), nullable=True)  # normal|abnormal
    behavior_notes = Column(Text, nullable=True)
    symptoms_json = Column(JSON, nullable=True)  # array of symptom strings
    photos_urls = Column(JSON, nullable=True)
    severity = Column(String(20), nullable=False, default="normal")  # normal|attention|urgent
    created_at = Column(DateTime, default=datetime.utcnow)

    bird = relationship("Bird", back_populates="health_records")
    vet_consult_request = relationship("VetConsultRequest", back_populates="health_record", uselist=False)


class VetConsultRequest(Base):
    __tablename__ = "vet_consult_requests"

    id = Column(String, primary_key=True, default=lambda: str(uuid.uuid4()))
    health_record_id = Column(String, ForeignKey("health_records.id"), nullable=True)
    bird_id = Column(String, ForeignKey("birds.id"), nullable=False)
    requester_user_id = Column(String, ForeignKey("users.id"), nullable=False)
    assigned_vet_id = Column(String, ForeignKey("users.id"), nullable=True)
    status = Column(String(20), nullable=False, default="pending")
    # pending|in_review|responded|closed
    priority = Column(String(20), nullable=False, default="medium")
    # low|medium|high|emergency
    description = Column(Text, nullable=True)
    response_text = Column(Text, nullable=True)
    response_date = Column(DateTime, nullable=True)
    attachments_urls = Column(JSON, nullable=True)
    created_at = Column(DateTime, default=datetime.utcnow)

    appointment_type = Column(String(20), nullable=False, default="telemedicine")
    scheduled_date = Column(String(10), nullable=True)
    scheduled_time = Column(String(5), nullable=True)
    location_address = Column(String(300), nullable=True)

    health_record = relationship("HealthRecord", back_populates="vet_consult_request")
    bird = relationship("Bird", foreign_keys=[bird_id])
    requester = relationship("User", foreign_keys=[requester_user_id])
    vet = relationship("User", foreign_keys=[assigned_vet_id])
    notes = relationship("VetNote", back_populates="consult_request")


class VetNote(Base):
    __tablename__ = "vet_notes"

    id = Column(String, primary_key=True, default=lambda: str(uuid.uuid4()))
    consult_request_id = Column(String, ForeignKey("vet_consult_requests.id"), nullable=False)
    vet_user_id = Column(String, ForeignKey("users.id"), nullable=False)
    content = Column(Text, nullable=False)
    is_private = Column(Boolean, default=False)
    created_at = Column(DateTime, default=datetime.utcnow)

    consult_request = relationship("VetConsultRequest", back_populates="notes")
    vet = relationship("User", foreign_keys=[vet_user_id])
