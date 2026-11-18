from pydantic import BaseModel, EmailStr
from typing import Literal, Optional


class RegisterRequest(BaseModel):
    name: str
    email: EmailStr
    password: str
    # Self-registration is limited to breeder/vet — "admin" must never be reachable here.
    # Admin accounts are created out-of-band (seeds/create_users.py or an admin-only endpoint).
    role: Literal["breeder", "vet"] = "breeder"
    locale: str = "pt-BR"


class LoginRequest(BaseModel):
    username: str  # email
    password: str


class TokenResponse(BaseModel):
    access_token: str
    refresh_token: str
    token_type: str = "bearer"


class RefreshRequest(BaseModel):
    refresh_token: str


class ForgotPasswordRequest(BaseModel):
    email: EmailStr


class ResetPasswordRequest(BaseModel):
    token: str
    new_password: str


class UserOut(BaseModel):
    id: str
    email: str
    name: str
    role: str
    locale: str
    is_active: bool
    plan_id: Optional[str] = None

    class Config:
        from_attributes = True
