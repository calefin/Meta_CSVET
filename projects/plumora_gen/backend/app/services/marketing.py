from typing import Any, Dict, List

from sqlalchemy.orm import Session


def resolve_segment(db: Session, filters: Dict[str, Any]):
    from app.models.user import User, Plan
    from app.models.bird import Bird

    filters = filters or {}

    q = db.query(User).filter(User.marketing_opt_out == False, User.is_active == True)  # noqa: E712

    if filters.get("roles"):
        q = q.filter(User.role.in_(filters["roles"]))
    if filters.get("plan_names"):
        q = q.join(Plan, User.plan_id == Plan.id).filter(Plan.name.in_(filters["plan_names"]))
    if filters.get("locales"):
        q = q.filter(User.locale.in_(filters["locales"]))
    if filters.get("signup_after"):
        q = q.filter(User.created_at >= filters["signup_after"])
    if filters.get("signup_before"):
        q = q.filter(User.created_at <= filters["signup_before"])
    if filters.get("species_ids"):
        q = q.join(Bird, Bird.user_id == User.id).filter(Bird.species_id.in_(filters["species_ids"]))

    return q.distinct().all()
