"""
Create initial users: admin, vet (Atilio) and test breeder.
Run: PYTHONPATH=/app python /seeds/create_users.py
"""
import sys, os
sys.path.insert(0, os.path.join(os.path.dirname(__file__), ".."))

from app.database import SessionLocal
from app.models.user import User
from passlib.context import CryptContext
import uuid

pwd_ctx = CryptContext(schemes=["bcrypt"], deprecated="auto")

db = SessionLocal()

users = [
    User(
        id=str(uuid.uuid4()),
        email="admin@plumoragen.com",
        password_hash=pwd_ctx.hash("Admin@2026!"),
        role="admin",
        name="Administrador",
        plan_id="plan-premium",
        is_active=True,
    ),
    User(
        id=str(uuid.uuid4()),
        email="atilio@plumoragen.com",
        password_hash=pwd_ctx.hash("Vet@2026!"),
        role="vet",
        name="Prof. Dr. Atilio Sersun Calefi",
        crmv_state="SP",
        plan_id="plan-premium",
        is_active=True,
    ),
    User(
        id=str(uuid.uuid4()),
        email="teste@plumoragen.com",
        password_hash=pwd_ctx.hash("Teste@2026!"),
        role="breeder",
        name="Usuário Teste",
        plan_id="plan-free",
        is_active=True,
    ),
]

for u in users:
    if not db.query(User).filter(User.email == u.email).first():
        db.add(u)
        print(f"✓ Criado: {u.email} ({u.role})")
    else:
        print(f"  Já existe: {u.email}")

db.commit()
db.close()
print("Concluído.")
