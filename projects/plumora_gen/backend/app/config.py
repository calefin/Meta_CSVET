from pydantic_settings import BaseSettings
from functools import lru_cache
from urllib.parse import quote_plus


class Settings(BaseSettings):
    app_env: str = "development"
    secret_key: str = "change-me-in-production"
    frontend_url: str = "http://localhost:5173"
    algorithm: str = "HS256"
    access_token_expire_minutes: int = 15
    refresh_token_expire_days: int = 7

    db_host: str = "localhost"
    db_port: int = 5432
    db_user: str = "plumora_user"
    db_password: str = "plumora_pass"
    db_name: str = "plumora"

    redis_url: str = "redis://localhost:6379/0"

    minio_url: str = "http://localhost:9000"
    minio_user: str = "minioadmin"
    minio_password: str = "minioadmin"
    minio_bucket: str = "plumora-uploads"

    smtp_host: str = "smtp.resend.com"
    smtp_port: int = 587
    smtp_user: str = "resend"
    smtp_password: str = ""
    email_from: str = "noreply@plumora.com.br"

    # Mercado Pago (billing) — Stripe foi descontinuado nesta versão.
    mp_access_token: str = ""
    mp_webhook_secret: str = ""

    # E-mail marketing (campanhas em massa) — provedor separado do transacional (Resend acima).
    # Gmail exige "senha de app" (não a senha normal da conta) quando 2FA está ativo.
    gmail_smtp_user: str = ""
    gmail_app_password: str = ""
    marketing_daily_limit: int = 450

    @property
    def database_url(self) -> str:
        return (
            f"postgresql://{quote_plus(self.db_user)}:{quote_plus(self.db_password)}"
            f"@{self.db_host}:{self.db_port}/{self.db_name}"
        )

    class Config:
        env_file = ".env"


@lru_cache
def get_settings() -> Settings:
    return Settings()
