import uuid
from datetime import datetime
from sqlalchemy import Column, String, Boolean, DateTime, ForeignKey, Integer, Float, Text
from sqlalchemy.orm import relationship
from app.database import Base


class Cage(Base):
    __tablename__ = "cages"

    id = Column(String, primary_key=True, default=lambda: str(uuid.uuid4()))
    user_id = Column(String, ForeignKey("users.id"), nullable=False)
    name = Column(String(100), nullable=False)
    location = Column(String(200), nullable=True)
    notes = Column(Text, nullable=True)
    is_active = Column(Boolean, nullable=False, default=True)
    created_at = Column(DateTime, default=datetime.utcnow)

    breedings = relationship("Breeding", back_populates="cage")


class Breeding(Base):
    __tablename__ = "breedings"

    id = Column(String, primary_key=True, default=lambda: str(uuid.uuid4()))
    user_id = Column(String, ForeignKey("users.id"), nullable=False)
    male_id = Column(String, ForeignKey("birds.id"), nullable=False)
    female_id = Column(String, ForeignKey("birds.id"), nullable=False)
    cage_id = Column(String, ForeignKey("cages.id"), nullable=True)
    pairing_date = Column(DateTime, nullable=False)
    separation_date = Column(DateTime, nullable=True)
    nest_type = Column(String(100), nullable=True)
    nest_number = Column(String(50), nullable=True)
    status = Column(String(20), nullable=False, default="paired")
    # paired|incubating|hatched|failed|completed
    notes = Column(Text, nullable=True)
    created_at = Column(DateTime, default=datetime.utcnow)

    male = relationship("Bird", foreign_keys=[male_id])
    female = relationship("Bird", foreign_keys=[female_id])
    cage = relationship("Cage", back_populates="breedings")
    clutches = relationship("Clutch", back_populates="breeding")
    genetic_result = relationship("BreedingGeneticResult", back_populates="breeding", uselist=False)


class Clutch(Base):
    __tablename__ = "clutches"

    id = Column(String, primary_key=True, default=lambda: str(uuid.uuid4()))
    breeding_id = Column(String, ForeignKey("breedings.id"), nullable=False)
    lay_date = Column(DateTime, nullable=False)
    egg_count = Column(Integer, nullable=False, default=0)
    fertile_count = Column(Integer, nullable=True)
    hatched_count = Column(Integer, nullable=True)
    candling_date = Column(DateTime, nullable=True)
    candling_notes = Column(Text, nullable=True)
    created_at = Column(DateTime, default=datetime.utcnow)

    breeding = relationship("Breeding", back_populates="clutches")
    offspring = relationship("Offspring", back_populates="clutch")


class Offspring(Base):
    __tablename__ = "offspring"

    id = Column(String, primary_key=True, default=lambda: str(uuid.uuid4()))
    clutch_id = Column(String, ForeignKey("clutches.id"), nullable=False)
    bird_id = Column(String, ForeignKey("birds.id"), nullable=True)
    hatch_date = Column(DateTime, nullable=True)
    hatch_weight_g = Column(Float, nullable=True)
    ring_date = Column(DateTime, nullable=True)
    ring_number = Column(String(100), nullable=True)
    notes = Column(Text, nullable=True)

    clutch = relationship("Clutch", back_populates="offspring")
    bird = relationship("Bird", foreign_keys=[bird_id])
