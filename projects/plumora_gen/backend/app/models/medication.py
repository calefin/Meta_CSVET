import uuid
from datetime import datetime
from sqlalchemy import Column, String, Boolean, DateTime, ForeignKey, Integer, Float, Text
from sqlalchemy.orm import relationship
from app.database import Base


class Medication(Base):
    __tablename__ = "medications"

    id = Column(String, primary_key=True, default=lambda: str(uuid.uuid4()))
    name = Column(String(200), nullable=False)
    active_ingredient = Column(String(200), nullable=True)
    manufacturer = Column(String(200), nullable=True)
    type = Column(String(30), nullable=False)
    # antibiotic|antiparasitic|vitamin|vaccine|antifungal|other
    presentation = Column(String(20), nullable=True)
    # oral|injectable|topical|water_soluble
    requires_prescription = Column(Boolean, default=False)

    records = relationship("MedicationRecord", back_populates="medication")


class MedicationRecord(Base):
    __tablename__ = "medication_records"

    id = Column(String, primary_key=True, default=lambda: str(uuid.uuid4()))
    bird_id = Column(String, ForeignKey("birds.id"), nullable=True)
    user_id = Column(String, ForeignKey("users.id"), nullable=False)
    medication_id = Column(String, ForeignKey("medications.id"), nullable=False)
    dose = Column(Float, nullable=True)
    unit = Column(String(10), nullable=True)  # mg|ml|drops|IU
    route = Column(String(20), nullable=True)  # oral|IM|SC|topical|drinking_water
    start_date = Column(DateTime, nullable=False)
    end_date = Column(DateTime, nullable=True)
    interval_hours = Column(Integer, nullable=True)
    prescribed_by = Column(String, ForeignKey("users.id"), nullable=True)
    batch_treatment = Column(Boolean, default=False)
    batch_description = Column(Text, nullable=True)
    withdrawal_period_days = Column(Integer, nullable=True)
    withdrawal_end_date = Column(DateTime, nullable=True)
    notes = Column(Text, nullable=True)
    created_at = Column(DateTime, default=datetime.utcnow)

    bird = relationship("Bird", back_populates="medication_records", foreign_keys=[bird_id])
    medication = relationship("Medication", back_populates="records")
    prescriber = relationship("User", foreign_keys=[prescribed_by])


class VaccineProduct(Base):
    __tablename__ = "vaccine_products"

    id = Column(String, primary_key=True, default=lambda: str(uuid.uuid4()))
    name_pt = Column(String(300), nullable=False)
    name_en = Column(String(300), nullable=False)
    manufacturer = Column(String(200), nullable=True)
    batch_number = Column(String(100), nullable=True)
    disease_target = Column(String(300), nullable=True)
    species_ids = Column(String, nullable=True)  # JSON array as string
    dose_ml = Column(Float, nullable=True)
    route = Column(String(50), nullable=True)
    interval_days = Column(Integer, nullable=True)
    requires_booster = Column(Boolean, default=False)
    storage_temp_min = Column(Float, nullable=True)
    storage_temp_max = Column(Float, nullable=True)
    withdrawal_period_days = Column(Integer, nullable=True)
    notes = Column(Text, nullable=True)
    created_at = Column(DateTime, default=datetime.utcnow)

    vaccination_records = relationship("VaccinationRecord", back_populates="vaccine_product")


class VaccinationRecord(Base):
    __tablename__ = "vaccination_records"

    id = Column(String, primary_key=True, default=lambda: str(uuid.uuid4()))
    bird_id = Column(String, ForeignKey("birds.id"), nullable=False)
    user_id = Column(String, ForeignKey("users.id"), nullable=False)
    vaccine_product_id = Column(String, ForeignKey("vaccine_products.id"), nullable=False)
    application_date = Column(DateTime, nullable=False)
    dose_number = Column(Integer, nullable=False, default=1)
    dose_ml_applied = Column(Float, nullable=True)
    route_used = Column(String(50), nullable=True)
    applied_by_vet_id = Column(String, ForeignKey("users.id"), nullable=True)
    lot_treatment_id = Column(String, ForeignKey("vaccination_lots.id"), nullable=True)
    next_dose_date = Column(DateTime, nullable=True)
    reaction_observed = Column(Boolean, default=False)
    reaction_description = Column(Text, nullable=True)
    certificate_url = Column(String(500), nullable=True)
    notes = Column(Text, nullable=True)
    applied_by_name = Column(String(200), nullable=True)
    applied_by_crmv_state = Column(String(2), nullable=True)
    applied_by_crmv_number = Column(String(20), nullable=True)
    lot_number_manual = Column(String(100), nullable=True)
    record_hash = Column(String(64), nullable=True)
    created_at = Column(DateTime, default=datetime.utcnow)

    bird = relationship("Bird", back_populates="vaccination_records", foreign_keys=[bird_id])
    vaccine_product = relationship("VaccineProduct", back_populates="vaccination_records")
    vet = relationship("User", foreign_keys=[applied_by_vet_id])
    lot = relationship("VaccinationLot", back_populates="records", foreign_keys=[lot_treatment_id])


class VaccinationLot(Base):
    __tablename__ = "vaccination_lots"

    id = Column(String, primary_key=True, default=lambda: str(uuid.uuid4()))
    user_id = Column(String, ForeignKey("users.id"), nullable=False)
    name = Column(String(200), nullable=False)
    vaccine_product_id = Column(String, ForeignKey("vaccine_products.id"), nullable=False)
    application_date = Column(DateTime, nullable=False)
    applied_by_vet_id = Column(String, ForeignKey("users.id"), nullable=True)
    protocol_description = Column(Text, nullable=True)
    selection_type = Column(String(20), nullable=False, default="all")
    # all|species|location|manual
    species_filter_id = Column(String, ForeignKey("species.id"), nullable=True)
    location_filter = Column(String(200), nullable=True)
    bird_ids = Column(String, nullable=True)  # JSON array
    total_birds_targeted = Column(Integer, nullable=True)
    total_birds_applied = Column(Integer, nullable=True)
    notes = Column(Text, nullable=True)
    created_at = Column(DateTime, default=datetime.utcnow)

    records = relationship("VaccinationRecord", back_populates="lot", foreign_keys="VaccinationRecord.lot_treatment_id")
