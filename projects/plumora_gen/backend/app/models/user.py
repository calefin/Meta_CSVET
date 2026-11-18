import uuid
from datetime import datetime
from sqlalchemy import Column, String, Boolean, DateTime, ForeignKey, Integer
from sqlalchemy.orm import relationship
from app.database import Base



class Plan(Base):
    __tablename__ = "plans"

    id = Column(String, primary_key=True, default=lambda: str(uuid.uuid4()))
    name = Column(String(50), nullable=False)  # free|basic|pro|premium
    max_birds = Column(Integer, nullable=False)
    price_monthly = Column(Integer, nullable=False, default=0)  # centavos
    price_yearly = Column(Integer, nullable=False, default=0)
    features_json = Column(String, nullable=True)

    users = relationship("User", back_populates="plan")
    subscriptions = relationship("Subscription", back_populates="plan")


class User(Base):
    __tablename__ = "users"

    id = Column(String, primary_key=True, default=lambda: str(uuid.uuid4()))
    email = Column(String(255), unique=True, nullable=False, index=True)
    password_hash = Column(String(255), nullable=False)
    role = Column(String(20), nullable=False, default="breeder")  # admin|vet|breeder
    name = Column(String(200), nullable=False)
    phone = Column(String(30), nullable=True)
    cpf_cnpj = Column(String(20), nullable=True)
    locale = Column(String(10), nullable=False, default="pt-BR")
    is_active = Column(Boolean, default=True, nullable=False)
    marketing_opt_out = Column(Boolean, default=False, nullable=False)
    plan_id = Column(String, ForeignKey("plans.id"), nullable=True)
    created_at = Column(DateTime, default=datetime.utcnow, nullable=False)
    updated_at = Column(DateTime, default=datetime.utcnow, onupdate=datetime.utcnow)

    full_name = Column(String(200), nullable=True)
    address_street = Column(String(200), nullable=True)
    address_number = Column(String(20), nullable=True)
    address_complement = Column(String(100), nullable=True)
    address_city = Column(String(100), nullable=True)
    address_state = Column(String(2), nullable=True)
    address_zip = Column(String(9), nullable=True)
    profile_photo_url = Column(String(500), nullable=True)
    association_name = Column(String(200), nullable=True)
    association_number = Column(String(100), nullable=True)
    crmv_state = Column(String(2), nullable=True)
    crmv_number = Column(String(20), nullable=True)
    terms_accepted_at = Column(DateTime, nullable=True)

    plan = relationship("Plan", back_populates="users")
    birds = relationship("Bird", back_populates="owner", foreign_keys="Bird.user_id")
    subscriptions = relationship("Subscription", back_populates="user")
    rings = relationship("Ring", back_populates="owner", foreign_keys="Ring.owner_id")


class Subscription(Base):
    __tablename__ = "subscriptions"

    id = Column(String, primary_key=True, default=lambda: str(uuid.uuid4()))
    user_id = Column(String, ForeignKey("users.id"), nullable=False)
    plan_id = Column(String, ForeignKey("plans.id"), nullable=False)
    status = Column(String(30), nullable=False, default="pending")
    # pending|active|cancelled
    billing_period = Column(String(10), nullable=False, default="monthly")
    # monthly|yearly
    current_period_end = Column(DateTime, nullable=True)
    # até quando o acesso é válido; None = plano free (nunca expira)
    mp_preapproval_id = Column(String(100), nullable=True)
    # assinatura mensal recorrente (Mercado Pago PreApproval)
    mp_payment_id = Column(String(100), nullable=True)
    # pagamento anual único (Mercado Pago Payment)
    start_date = Column(DateTime, nullable=False, default=datetime.utcnow)
    created_at = Column(DateTime, default=datetime.utcnow)

    user = relationship("User", back_populates="subscriptions")
    plan = relationship("Plan", back_populates="subscriptions")
