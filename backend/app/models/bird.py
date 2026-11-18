import uuid
from datetime import datetime
from sqlalchemy import Column, String, Boolean, DateTime, ForeignKey, Integer, Float, Text
from sqlalchemy.orm import relationship
from app.database import Base


class Species(Base):
    __tablename__ = "species"

    id = Column(String, primary_key=True, default=lambda: str(uuid.uuid4()))
    common_name = Column(String(200), nullable=False)
    scientific_name = Column(String(200), nullable=False)
    category = Column(String(30), nullable=False)  # exotic|domestic|pigeon|ornamental
    avg_lifespan_years = Column(Integer, nullable=True)
    incubation_days = Column(Integer, nullable=True)
    clutch_size_min = Column(Integer, nullable=True)
    clutch_size_max = Column(Integer, nullable=True)

    breeds = relationship("Breed", back_populates="species")
    birds = relationship("Bird", back_populates="species")
    genetic_loci = relationship("GeneticLocus", back_populates="species")


class Breed(Base):
    __tablename__ = "breeds"

    id = Column(String, primary_key=True, default=lambda: str(uuid.uuid4()))
    species_id = Column(String, ForeignKey("species.id"), nullable=False)
    name = Column(String(200), nullable=False)
    origin = Column(String(100), nullable=True)
    characteristics = Column(Text, nullable=True)

    species = relationship("Species", back_populates="breeds")
    birds = relationship("Bird", back_populates="breed")


class Bird(Base):
    __tablename__ = "birds"

    id = Column(String, primary_key=True, default=lambda: str(uuid.uuid4()))
    user_id = Column(String, ForeignKey("users.id"), nullable=False)
    name = Column(String(200), nullable=False)
    ring_number = Column(String(100), nullable=True)
    microchip = Column(String(100), nullable=True)
    species_id = Column(String, ForeignKey("species.id"), nullable=True)
    breed_id = Column(String, ForeignKey("breeds.id"), nullable=True)
    sex = Column(String(10), nullable=False, default="unknown")  # M|F|unknown
    birth_date = Column(DateTime, nullable=True)
    acquisition_date = Column(DateTime, nullable=True)
    acquisition_type = Column(String(20), nullable=True)  # born|bought|donated
    color = Column(String(100), nullable=True)
    plumage_description = Column(Text, nullable=True)
    weight_grams = Column(Float, nullable=True)
    father_id = Column(String, ForeignKey("birds.id"), nullable=True)
    mother_id = Column(String, ForeignKey("birds.id"), nullable=True)
    photo_url = Column(String(500), nullable=True)
    status = Column(String(20), nullable=False, default="active")  # active|deceased|sold|transferred
    notes = Column(Text, nullable=True)
    created_at = Column(DateTime, default=datetime.utcnow, nullable=False)
    updated_at = Column(DateTime, default=datetime.utcnow, onupdate=datetime.utcnow)

    owner = relationship("User", back_populates="birds", foreign_keys=[user_id])
    species = relationship("Species", back_populates="birds")
    breed = relationship("Breed", back_populates="birds")
    father = relationship("Bird", foreign_keys=[father_id], remote_side="Bird.id")
    mother = relationship("Bird", foreign_keys=[mother_id], remote_side="Bird.id")
    health_records = relationship("HealthRecord", back_populates="bird")
    medication_records = relationship("MedicationRecord", back_populates="bird")
    vaccination_records = relationship("VaccinationRecord", back_populates="bird")
    feed_logs = relationship("BirdFeedLog", back_populates="bird")
    genotype = relationship("BirdGenotype", back_populates="bird", uselist=False)
    pigeon_genotype = relationship("PigeonGenotype", back_populates="bird", uselist=False)
    race_results = relationship("RaceResult", back_populates="bird")
    pedigree_exports = relationship("PedigreeExport", back_populates="bird")
