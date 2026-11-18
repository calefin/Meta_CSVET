from fastapi import APIRouter, Depends, HTTPException
from sqlalchemy.orm import Session
from app.dependencies import get_db, get_current_user
from app.models.bird import Bird
from app.models.race import PedigreeExport
from app.models.user import User

router = APIRouter(prefix="/api/birds", tags=["pedigree"])


@router.get("/{bird_id}/pedigree")
def get_pedigree(
    bird_id: str,
    generations: int = 3,
    db: Session = Depends(get_db),
    current_user: User = Depends(get_current_user),
):
    bird = db.query(Bird).filter(Bird.id == bird_id, Bird.user_id == current_user.id).first()
    if not bird:
        raise HTTPException(404, "Bird not found")
    from app.services.pedigree_gen import build_pedigree_tree
    from app.services.zootechnical import wright_inbreeding_coefficient
    tree = build_pedigree_tree(db, bird_id, min(generations, 5))
    inbreeding = wright_inbreeding_coefficient(db, bird_id)
    return {"tree": tree, "inbreeding_coefficient_pct": inbreeding}


@router.post("/{bird_id}/pedigree/export")
def export_pedigree(
    bird_id: str,
    generations: int = 3,
    format: str = "pdf",
    db: Session = Depends(get_db),
    current_user: User = Depends(get_current_user),
):
    bird = db.query(Bird).filter(Bird.id == bird_id, Bird.user_id == current_user.id).first()
    if not bird:
        raise HTTPException(404, "Bird not found")

    # Check plan permissions
    from app.models.user import Plan
    plan = db.query(Plan).filter(Plan.id == current_user.plan_id).first()
    if plan and plan.name == "free":
        count = db.query(PedigreeExport).filter(PedigreeExport.requested_by_user_id == current_user.id).count()
        if count >= 2:
            raise HTTPException(402, "PDF export limit reached for free plan")

    from app.services.pedigree_gen import export_pedigree_pdf
    file_url = export_pedigree_pdf(db, bird_id, generations, current_user.id)

    export = PedigreeExport(
        bird_id=bird_id,
        generations=generations,
        format=format,
        file_url=file_url,
        requested_by_user_id=current_user.id,
    )
    db.add(export)
    db.commit()
    return {"file_url": file_url, "export_id": export.id}
