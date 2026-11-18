"""
Pedigree / Claudograma generation.
Traverses the family tree up to N generations using BFS and exports to PDF.
"""
from typing import Optional, List, Dict
from collections import deque


def build_pedigree_tree(db, bird_id: str, generations: int = 3) -> Dict:
    """Return a nested dict representing the pedigree tree."""
    from app.models.bird import Bird

    def get_node(bird_id: Optional[str], gen: int) -> Optional[Dict]:
        if bird_id is None or gen == 0:
            return None
        bird = db.query(Bird).filter(Bird.id == bird_id).first()
        if not bird:
            return None
        return {
            "id": bird.id,
            "name": bird.name,
            "ring": bird.ring_number,
            "sex": bird.sex,
            "species": bird.species.common_name if bird.species else None,
            "color": bird.color,
            "photo_url": bird.photo_url,
            "father": get_node(bird.father_id, gen - 1),
            "mother": get_node(bird.mother_id, gen - 1),
        }

    return get_node(bird_id, generations + 1) or {}


def export_pedigree_pdf(db, bird_id: str, generations: int = 3, requested_by: str = "") -> str:
    """
    Generate a PDF pedigree and save to MinIO storage.
    Returns the file URL.
    """
    from reportlab.lib.pagesizes import A4, landscape
    from reportlab.pdfgen import canvas
    from reportlab.lib.units import cm
    import io, boto3
    from app.config import get_settings

    settings = get_settings()
    tree = build_pedigree_tree(db, bird_id, generations)

    buf = io.BytesIO()
    c = canvas.Canvas(buf, pagesize=landscape(A4))
    width, height = landscape(A4)

    # Header
    c.setFont("Helvetica-Bold", 20)
    c.drawString(2 * cm, height - 2 * cm, f"Claudograma — {tree.get('name', '')}")
    c.setFont("Helvetica", 10)
    c.drawString(2 * cm, height - 2.7 * cm, f"Anilha: {tree.get('ring', '')} | Plumora Gen")

    # Draw tree (simplified layout — row per generation)
    def draw_node(node: Dict, x: float, y: float, box_w: float = 5 * cm, box_h: float = 1.5 * cm):
        if not node:
            return
        c.rect(x, y - box_h, box_w, box_h)
        c.setFont("Helvetica-Bold", 8)
        c.drawString(x + 0.2 * cm, y - 0.6 * cm, node.get("name", "?")[:20])
        c.setFont("Helvetica", 7)
        c.drawString(x + 0.2 * cm, y - 1.1 * cm, (node.get("ring") or "")[:20])

    # Root
    draw_node(tree, width / 2 - 2.5 * cm, height - 4 * cm)

    c.save()
    buf.seek(0)

    # Upload to MinIO/S3
    file_key = f"pedigrees/{bird_id}_{generations}gen.pdf"
    try:
        s3 = boto3.client(
            "s3",
            endpoint_url=settings.minio_url,
            aws_access_key_id=settings.minio_user,
            aws_secret_access_key=settings.minio_password,
        )
        s3.put_object(Bucket=settings.minio_bucket, Key=file_key, Body=buf.read(), ContentType="application/pdf")
        return f"{settings.minio_url}/{settings.minio_bucket}/{file_key}"
    except Exception:
        return ""
