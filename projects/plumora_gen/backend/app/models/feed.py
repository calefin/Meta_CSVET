import uuid
from datetime import datetime
from sqlalchemy import Column, String, DateTime, ForeignKey, Integer, Float, Text, JSON
from sqlalchemy.orm import relationship
from app.database import Base


class Feed(Base):
    __tablename__ = "feeds"

    id = Column(String, primary_key=True, default=lambda: str(uuid.uuid4()))
    name = Column(String(200), nullable=False)
    brand = Column(String(200), nullable=True)
    type = Column(String(20), nullable=False)
    # seed|pellet|fresh|supplement|grain|other
    composition_notes = Column(Text, nullable=True)
    calories_per_100g = Column(Float, nullable=True)

    feed_logs = relationship("BirdFeedLog", back_populates="feed")


class BirdFeedLog(Base):
    __tablename__ = "bird_feed_logs"

    id = Column(String, primary_key=True, default=lambda: str(uuid.uuid4()))
    bird_id = Column(String, ForeignKey("birds.id"), nullable=False)
    feed_id = Column(String, ForeignKey("feeds.id"), nullable=False)
    date = Column(DateTime, nullable=False)
    quantity_grams = Column(Float, nullable=True)
    frequency = Column(String(20), nullable=True)  # daily|weekly|as_needed
    age_phase = Column(String(20), nullable=True)  # chick|juvenile|adult|breeding|molting
    notes = Column(Text, nullable=True)
    recorded_by_user_id = Column(String, ForeignKey("users.id"), nullable=False)
    created_at = Column(DateTime, default=datetime.utcnow)

    bird = relationship("Bird", back_populates="feed_logs")
    feed = relationship("Feed", back_populates="feed_logs")


class FeedingPlan(Base):
    __tablename__ = "feeding_plans"

    id = Column(String, primary_key=True, default=lambda: str(uuid.uuid4()))
    user_id = Column(String, ForeignKey("users.id"), nullable=False)
    name = Column(String(200), nullable=False)
    description = Column(Text, nullable=True)
    items = Column(JSON, nullable=True)
    # [{feed_id, quantity_g, frequency, phase}]
    created_at = Column(DateTime, default=datetime.utcnow)
