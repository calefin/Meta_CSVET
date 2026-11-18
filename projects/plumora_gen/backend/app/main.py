from fastapi import Depends, FastAPI
from fastapi.middleware.cors import CORSMiddleware
from slowapi import _rate_limit_exceeded_handler
from slowapi.errors import RateLimitExceeded
from app.config import get_settings
from app.dependencies import check_subscription_active
from app.limiter import limiter
from app.routers import auth, birds, breeding, health, vet, genetics, races, pedigree, admin, billing, vaccines
from app.routers import profile, rings, cages, dashboard
from app.routers.medication import router as medication_router
from app.routers.feed import router as feed_router
from app.routers.marketing import router as marketing_router, admin_router as marketing_admin_router

settings = get_settings()

app = FastAPI(
    title="Plumora Gen API",
    description="Plataforma de Gestão para Criadores de Aves Exóticas, Domésticas e Pombos Correio",
    version="4.0.0",
    docs_url="/api/docs",
    redoc_url="/api/redoc",
)

app.state.limiter = limiter
app.add_exception_handler(RateLimitExceeded, _rate_limit_exceeded_handler)

app.add_middleware(
    CORSMiddleware,
    allow_origins=[settings.frontend_url],
    allow_credentials=True,
    allow_methods=["*"],
    allow_headers=["*"],
)

# Blocked once a breeder's paid plan lapses (see app/dependencies.py::check_subscription_active).
# Free plan, vet/admin accounts, and the routers below (auth/billing/profile/admin/vet/marketing)
# are never gated by this — a breeder must always be able to reach billing to pay again.
_subscription_gate = [Depends(check_subscription_active)]

app.include_router(auth.router)
app.include_router(birds.router, dependencies=_subscription_gate)
app.include_router(breeding.router, dependencies=_subscription_gate)
app.include_router(health.router, dependencies=_subscription_gate)
app.include_router(vet.router)
app.include_router(genetics.router, dependencies=_subscription_gate)
app.include_router(races.router, dependencies=_subscription_gate)
app.include_router(pedigree.router, dependencies=_subscription_gate)
app.include_router(admin.router)
app.include_router(billing.router)
app.include_router(vaccines.router, dependencies=_subscription_gate)
app.include_router(profile.router)
app.include_router(rings.router, dependencies=_subscription_gate)
app.include_router(cages.router, dependencies=_subscription_gate)
app.include_router(dashboard.router, dependencies=_subscription_gate)
app.include_router(medication_router, dependencies=_subscription_gate)
app.include_router(feed_router, dependencies=_subscription_gate)
app.include_router(marketing_router)
app.include_router(marketing_admin_router)


@app.get("/health")
def health_check():
    return {"status": "ok", "service": "Plumora Gen API", "version": "4.0.0"}
