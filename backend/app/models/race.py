import uuid
from datetime import datetime
from sqlalchemy import Column, String, Boolean, DateTime, ForeignKey, Integer, Float, Text
from sqlalchemy.orm import relationship
from app.database import Base


class Race(Base):
    __tablename__ = "races"

    id = Column(String, primary_key=True, default=lambda: str(uuid.uuid4()))
    name = Column(String(200), nullable=False)
    date = Column(DateTime, nullable=False)
    start_location = Column(String(300), nullable=True)
    start_lat = Column(Float, nullable=True)
    start_lon = Column(Float, nullable=True)
    distance_km = Column(Float, nullable=True)
    wind_speed_kmh = Column(Float, nullable=True)
    wind_direction = Column(String(10), nullable=True)
    weather_conditions = Column(String(200), nullable=True)
    organizer = Column(String(200), nullable=True)
    category = Column(String(100), nullable=True)
    notes = Column(Text, nullable=True)
    created_at = Column(DateTime, default=datetime.utcnow)

    results = relationship("RaceResult", back_populates="race")


class RaceResult(Base):
    __tablename__ = "race_results"

    id = Column(String, primary_key=True, default=lambda: str(uuid.uuid4()))
    race_id = Column(String, ForeignKey("races.id"), nullable=False)
    bird_id = Column(String, ForeignKey("birds.id"), nullable=False)
    user_id = Column(String, ForeignKey("users.id"), nullable=False)
    release_time = Column(DateTime, nullable=True)
    arrival_time = Column(DateTime, nullable=True)
    flight_time_seconds = Column(Integer, nullable=True)
    velocity_mpm = Column(Float, nullable=True)
    loft_location = Column(String(300), nullable=True)
    loft_lat = Column(Float, nullable=True)
    loft_lon = Column(Float, nullable=True)
    actual_distance_km = Column(Float, nullable=True)
    position_overall = Column(Integer, nullable=True)
    position_category = Column(Integer, nullable=True)
    disqualified = Column(Boolean, default=False)
    disqualification_reason = Column(String(300), nullable=True)
    notes = Column(Text, nullable=True)
    created_at = Column(DateTime, default=datetime.utcnow)

    race = relationship("Race", back_populates="results")
    bird = relationship("Bird", back_populates="race_results")


class PedigreeExport(Base):
    __tablename__ = "pedigree_exports"

    id = Column(String, primary_key=True, default=lambda: str(uuid.uuid4()))
    bird_id = Column(String, ForeignKey("birds.id"), nullable=False)
    generations = Column(Integer, nullable=False, default=3)
    format = Column(String(10), nullable=False, default="pdf")  # pdf|png|svg
    generated_at = Column(DateTime, default=datetime.utcnow)
    file_url = Column(String(500), nullable=True)
    requested_by_user_id = Column(String, ForeignKey("users.id"), nullable=False)

    bird = relationship("Bird", back_populates="pedigree_exports")
