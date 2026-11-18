from datetime import datetime, timedelta
from typing import Any, Dict, List

from sqlalchemy import func
from sqlalchemy.orm import Session


def get_dashboard_summary(db: Session, user_id: str) -> Dict[str, Any]:
    from app.models.bird import Bird, Species
    from app.models.breeding import Breeding, Clutch, Cage
    from app.models.medication import VaccinationRecord, MedicationRecord
    from app.models.health import HealthRecord, VetConsultRequest

    now = datetime.utcnow()

    # ---------- Plantel ----------
    active_birds_q = db.query(Bird).filter(Bird.user_id == user_id, Bird.status == "active")
    active_birds_count = active_birds_q.count()
    birds_by_id = {b.id: b for b in active_birds_q.all()}

    species_breakdown_rows = (
        db.query(Species.common_name, func.count(Bird.id))
        .join(Bird, Bird.species_id == Species.id)
        .filter(Bird.user_id == user_id, Bird.status == "active")
        .group_by(Species.common_name)
        .order_by(func.count(Bird.id).desc())
        .limit(6)
        .all()
    )
    species_breakdown = [{"species_name": name, "count": count} for name, count in species_breakdown_rows]

    # ---------- Cages / active breedings ----------
    cages_total = db.query(Cage).filter(Cage.user_id == user_id, Cage.is_active == True).count()
    active_breedings = (
        db.query(Breeding)
        .filter(
            Breeding.user_id == user_id,
            Breeding.status.in_(["paired", "incubating"]),
            Breeding.separation_date.is_(None),
        )
        .all()
    )
    cages_occupied = len({b.cage_id for b in active_breedings if b.cage_id})

    active_breedings_out = []
    for b in active_breedings:
        latest_clutch = (
            db.query(Clutch)
            .filter(Clutch.breeding_id == b.id)
            .order_by(Clutch.lay_date.desc())
            .first()
        )
        species = None
        if b.male and b.male.species:
            species = b.male.species
        elif b.female and b.female.species:
            species = b.female.species

        expected_hatch_date = None
        if latest_clutch and species and species.incubation_days:
            expected_hatch_date = (latest_clutch.lay_date + timedelta(days=species.incubation_days)).isoformat()

        cage = db.query(Cage).filter(Cage.id == b.cage_id).first() if b.cage_id else None

        active_breedings_out.append({
            "id": b.id,
            "male_name": b.male.name if b.male else None,
            "female_name": b.female.name if b.female else None,
            "cage_name": cage.name if cage else None,
            "status": b.status,
            "pairing_date": b.pairing_date.isoformat(),
            "latest_clutch_lay_date": latest_clutch.lay_date.isoformat() if latest_clutch else None,
            "expected_hatch_date": expected_hatch_date,
        })

    # ---------- Vet consults ----------
    pending_consults = (
        db.query(VetConsultRequest)
        .filter(
            VetConsultRequest.requester_user_id == user_id,
            VetConsultRequest.status.in_(["pending", "in_review"]),
        )
        .all()
    )

    # ---------- Alerts ----------
    alerts: List[Dict[str, Any]] = []

    def bird_name(bird_id: str):
        bird = birds_by_id.get(bird_id) or db.query(Bird).filter(Bird.id == bird_id).first()
        return bird.name if bird else None

    vaccine_records = (
        db.query(VaccinationRecord)
        .join(Bird, VaccinationRecord.bird_id == Bird.id)
        .filter(Bird.user_id == user_id, VaccinationRecord.next_dose_date.isnot(None))
        .filter(VaccinationRecord.next_dose_date <= now + timedelta(days=7))
        .all()
    )
    for v in vaccine_records:
        overdue = v.next_dose_date < now
        alerts.append({
            "type": "vaccine_overdue" if overdue else "vaccine_upcoming",
            "severity": "urgent" if overdue else "attention",
            "bird_id": v.bird_id,
            "bird_name": bird_name(v.bird_id),
            "message": (
                f"Vacina atrasada desde {v.next_dose_date.strftime('%d/%m/%Y')}"
                if overdue
                else f"Próxima dose de vacina em {v.next_dose_date.strftime('%d/%m/%Y')}"
            ),
            "date": v.next_dose_date.isoformat(),
        })

    health_records = (
        db.query(HealthRecord)
        .join(Bird, HealthRecord.bird_id == Bird.id)
        .filter(
            Bird.user_id == user_id,
            HealthRecord.severity != "normal",
            HealthRecord.date >= now - timedelta(days=30),
        )
        .all()
    )
    for h in health_records:
        alerts.append({
            "type": "health_severity",
            "severity": h.severity,
            "bird_id": h.bird_id,
            "bird_name": bird_name(h.bird_id),
            "message": (
                f"Registro de saúde com severidade '{h.severity}'"
                + (f": {h.behavior_notes}" if h.behavior_notes else "")
            ),
            "date": h.date.isoformat(),
        })

    if pending_consults:
        alerts.append({
            "type": "vet_pending",
            "severity": "attention",
            "bird_id": None,
            "bird_name": None,
            "message": f"Você tem {len(pending_consults)} chamado(s) PigeonVet aguardando resposta",
            "date": max(c.created_at for c in pending_consults).isoformat(),
        })

    medication_records = (
        db.query(MedicationRecord)
        .filter(
            MedicationRecord.user_id == user_id,
            MedicationRecord.bird_id.isnot(None),
            MedicationRecord.withdrawal_end_date.isnot(None),
            MedicationRecord.withdrawal_end_date >= now,
        )
        .all()
    )
    for m in medication_records:
        alerts.append({
            "type": "medication_withdrawal",
            "severity": "info",
            "bird_id": m.bird_id,
            "bird_name": bird_name(m.bird_id),
            "message": f"Em período de carência até {m.withdrawal_end_date.strftime('%d/%m/%Y')}",
            "date": m.withdrawal_end_date.isoformat(),
        })

    severity_rank = {"urgent": 0, "attention": 1, "info": 2}
    type_rank = {
        "health_severity": 0,
        "vaccine_overdue": 1,
        "vet_pending": 2,
        "vaccine_upcoming": 3,
        "medication_withdrawal": 4,
    }
    alerts.sort(key=lambda a: (severity_rank.get(a["severity"], 9), type_rank.get(a["type"], 9), a["date"]))

    return {
        "stats": {
            "active_birds": active_birds_count,
            "active_pairs": len(active_breedings),
            "cages_free": max(cages_total - cages_occupied, 0),
            "cages_total": cages_total,
            "pending_vet_consults": len(pending_consults),
        },
        "species_breakdown": species_breakdown,
        "active_breedings": active_breedings_out,
        "alerts": alerts,
    }
