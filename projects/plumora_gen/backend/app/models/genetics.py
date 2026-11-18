import uuid
from datetime import datetime
from sqlalchemy import Column, String, Boolean, DateTime, ForeignKey, Integer, Float, Text, JSON
from sqlalchemy.orm import relationship
from app.database import Base


class GeneticLocus(Base):
    __tablename__ = "genetic_loci"

    id = Column(String, primary_key=True, default=lambda: str(uuid.uuid4()))
    species_id = Column(String, ForeignKey("species.id"), nullable=False)
    symbol = Column(String(30), nullable=False)
    name_pt = Column(String(200), nullable=False)
    name_en = Column(String(200), nullable=False)
    inheritance_mode = Column(String(10), nullable=False)
    # re|se|do|in|cd
    allele_group = Column(String(50), nullable=True)
    allele_rank = Column(Integer, nullable=True)
    crossover_freq = Column(Float, nullable=True, default=0.0)
    linked_locus_id = Column(String, ForeignKey("genetic_loci.id"), nullable=True)
    is_multi_allele = Column(Boolean, default=False)
    alleles_json = Column(JSON, nullable=True)  # [{symbol, name, rank}]
    description = Column(Text, nullable=True)
    notes = Column(Text, nullable=True)
    display_order = Column(Integer, nullable=True, default=0)

    species = relationship("Species", back_populates="genetic_loci")
    linked_locus = relationship("GeneticLocus", remote_side="GeneticLocus.id")
    bird_genotypes = relationship("BirdGenotype", back_populates="locus")


class BirdGenotype(Base):
    __tablename__ = "bird_genotypes"

    id = Column(String, primary_key=True, default=lambda: str(uuid.uuid4()))
    bird_id = Column(String, ForeignKey("birds.id"), nullable=False)
    locus_id = Column(String, ForeignKey("genetic_loci.id"), nullable=False)
    allele_1 = Column(String(20), nullable=False)  # V|S|0|DF|SF|X1|X2
    allele_2 = Column(String(20), nullable=True)
    confidence = Column(String(20), nullable=False, default="estimated")
    # confirmed|inferred|estimated
    source = Column(String(20), nullable=False, default="manual")
    # manual|calculated|imported
    notes = Column(Text, nullable=True)
    created_at = Column(DateTime, default=datetime.utcnow)
    updated_by_user_id = Column(String, ForeignKey("users.id"), nullable=True)

    bird = relationship("Bird", back_populates="genotype")
    locus = relationship("GeneticLocus", back_populates="bird_genotypes")


class PigeonGenotype(Base):
    __tablename__ = "pigeon_genotypes"

    id = Column(String, primary_key=True, default=lambda: str(uuid.uuid4()))
    bird_id = Column(String, ForeignKey("birds.id"), nullable=False, unique=True)

    # Cor principal (sex-linked Z)
    color_z1 = Column(String(20), nullable=True)   # ash_red|blue|brown
    color_z2 = Column(String(20), nullable=True)   # macho apenas

    # Diluído (sex-linked Z, haplótipo com cor)
    dilute_z1 = Column(String(20), nullable=True)  # dilute|not_dilute
    dilute_z2 = Column(String(20), nullable=True)  # macho apenas

    # Padrão de asa (autossômico)
    pattern_a1 = Column(String(20), nullable=True)  # T_check|checker|bar|barless
    pattern_a2 = Column(String(20), nullable=True)

    # Spread (autossômico dominante)
    spread_a1 = Column(String(20), nullable=True)   # spread|not_spread
    spread_a2 = Column(String(20), nullable=True)

    # Vermelho recessivo (autossômico)
    rec_red_a1 = Column(String(20), nullable=True)
    rec_red_a2 = Column(String(20), nullable=True)

    # Crista (autossômico recessivo)
    crest_a1 = Column(String(20), nullable=True)    # crest|no_crest
    crest_a2 = Column(String(20), nullable=True)

    # Pé penado - Slipper (incompleto dominante)
    slipper_a1 = Column(String(20), nullable=True)
    slipper_a2 = Column(String(20), nullable=True)

    # Pé penado - Grouse (recessivo)
    grouse_a1 = Column(String(20), nullable=True)
    grouse_a2 = Column(String(20), nullable=True)

    confidence = Column(String(20), nullable=False, default="estimated")
    source = Column(String(20), nullable=False, default="manual")
    notes = Column(Text, nullable=True)
    created_at = Column(DateTime, default=datetime.utcnow)
    updated_by_user_id = Column(String, ForeignKey("users.id"), nullable=True)

    bird = relationship("Bird", back_populates="pigeon_genotype")


class BreedingGeneticResult(Base):
    __tablename__ = "breeding_genetic_results"

    id = Column(String, primary_key=True, default=lambda: str(uuid.uuid4()))
    breeding_id = Column(String, ForeignKey("breedings.id"), nullable=False, unique=True)
    calculated_at = Column(DateTime, default=datetime.utcnow)
    male_genotype_snapshot = Column(JSON, nullable=True)
    female_genotype_snapshot = Column(JSON, nullable=True)
    results_json = Column(JSON, nullable=True)
    warnings_json = Column(JSON, nullable=True)

    breeding = relationship("Breeding", back_populates="genetic_result")


class GeneticNotation(Base):
    __tablename__ = "genetic_notations"

    id = Column(String, primary_key=True, default=lambda: str(uuid.uuid4()))
    bird_id = Column(String, ForeignKey("birds.id"), nullable=False)
    user_id = Column(String, ForeignKey("users.id"), nullable=False)
    notation_type = Column(String(30), nullable=False)
    # ring_certificate|genetic_report|vet_confirmation|breeding_record|visual_assessment|dna_test
    content_text = Column(Text, nullable=True)
    attachment_url = Column(String(500), nullable=True)
    certified_by = Column(String(200), nullable=True)
    certification_date = Column(DateTime, nullable=True)
    is_public = Column(Boolean, default=False)
    created_at = Column(DateTime, default=datetime.utcnow)
