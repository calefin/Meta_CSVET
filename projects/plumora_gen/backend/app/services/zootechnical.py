from sqlalchemy.orm import Session
from sqlalchemy import func
from typing import Dict, Any


def get_bird_stats(db: Session, bird) -> Dict[str, Any]:
    from app.models.health import HealthRecord
    from app.models.race import RaceResult

    weight_records = (
        db.query(HealthRecord.weight_grams, HealthRecord.date)
        .filter(HealthRecord.bird_id == bird.id, HealthRecord.weight_grams.isnot(None))
        .order_by(HealthRecord.date)
        .all()
    )

    race_results = (
        db.query(RaceResult)
        .filter(RaceResult.bird_id == bird.id)
        .all()
    )

    velocities = [r.velocity_mpm for r in race_results if r.velocity_mpm]
    avg_velocity = round(sum(velocities) / len(velocities), 2) if velocities else None
    best_velocity = max(velocities) if velocities else None

    return {
        "bird_id": bird.id,
        "weight_history": [{"date": str(w[1].date()), "weight_g": w[0]} for w in weight_records],
        "race_stats": {
            "total_races": len(race_results),
            "avg_velocity_mpm": avg_velocity,
            "best_velocity_mpm": best_velocity,
            "top3_count": sum(1 for r in race_results if r.position_overall and r.position_overall <= 3),
        },
    }


def get_breeding_stats(db: Session, user_id: str) -> Dict[str, Any]:
    from app.models.breeding import Breeding, Clutch

    breedings = db.query(Breeding).filter(Breeding.user_id == user_id).all()
    clutches = (
        db.query(Clutch)
        .join(Breeding, Clutch.breeding_id == Breeding.id)
        .filter(Breeding.user_id == user_id)
        .all()
    )

    total_eggs = sum(c.egg_count or 0 for c in clutches)
    total_fertile = sum(c.fertile_count or 0 for c in clutches)
    total_hatched = sum(c.hatched_count or 0 for c in clutches)

    fertility_rate = round(total_fertile / total_eggs * 100, 1) if total_eggs else 0
    hatch_rate = round(total_hatched / total_fertile * 100, 1) if total_fertile else 0

    return {
        "total_pairs": len(breedings),
        "total_clutches": len(clutches),
        "total_eggs": total_eggs,
        "total_fertile": total_fertile,
        "total_hatched": total_hatched,
        "fertility_rate_pct": fertility_rate,
        "hatch_rate_pct": hatch_rate,
    }


def wright_inbreeding_coefficient(db: Session, bird_id: str, generations: int = 5) -> float:
    """
    Calculate Wright's inbreeding coefficient (F) using path coefficients.
    F = sum over common ancestors of (0.5^(L1+L2+1)) * (1 + FA)
    where L1, L2 = path lengths to common ancestor, FA = F of common ancestor.
    """
    from app.models.bird import Bird

    def get_ancestors(bird_id: str, depth: int) -> Dict[str, int]:
        if depth == 0:
            return {}
        bird = db.query(Bird).filter(Bird.id == bird_id).first()
        if not bird:
            return {}
        ancestors = {}
        for parent_id in [bird.father_id, bird.mother_id]:
            if parent_id:
                ancestors[parent_id] = ancestors.get(parent_id, 0) + 1
                for anc_id, count in get_ancestors(parent_id, depth - 1).items():
                    ancestors[anc_id] = ancestors.get(anc_id, 0) + count
        return ancestors

    bird = db.query(Bird).filter(Bird.id == bird_id).first()
    if not bird or not bird.father_id or not bird.mother_id:
        return 0.0

    sire_ancestors = get_ancestors(bird.father_id, generations)
    dam_ancestors = get_ancestors(bird.mother_id, generations)

    common = set(sire_ancestors.keys()) & set(dam_ancestors.keys())
    if not common:
        return 0.0

    # Simplified: sum (0.5)^(n_sire + n_dam + 1) for each common ancestor path
    F = 0.0
    for anc_id in common:
        n_sire = sire_ancestors[anc_id]
        n_dam = dam_ancestors[anc_id]
        F += (0.5 ** (n_sire + n_dam + 1))

    return round(F * 100, 2)  # return as percentage
