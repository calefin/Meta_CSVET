import uuid
from datetime import datetime
from sqlalchemy import Column, String, Integer, ForeignKey, Text, DateTime
from sqlalchemy.orm import relationship
from app.database import Base


class Ring(Base):
    __tablename__ = "rings"

    id = Column(String, primary_key=True, default=lambda: str(uuid.uuid4()))
    ring_number = Column(String(50), nullable=False)
    year = Column(Integer, nullable=True)
    color = Column(String(50), nullable=True)
    species_id = Column(String, ForeignKey("species.id"), nullable=True)
    bird_id = Column(String, ForeignKey("birds.id"), nullable=True)
    owner_id = Column(String, ForeignKey("users.id"), nullable=False)
    source = Column(String(100), nullable=True)
    notes = Column(Text, nullable=True)
    created_at = Column(DateTime, default=datetime.utcnow)

    species = relationship("Species")
    bird = relationship("Bird", foreign_keys=[bird_id])
    owner = relationship("User", back_populates="rings", foreign_keys=[owner_id])
