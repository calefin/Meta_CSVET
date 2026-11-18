# VetRank — Resolução de hierarquia por slug
#
# Uso:
#   from resolve_hierarchy import resolve_ids
#   ids = resolve_ids(
#       discipline_slug="patobiologia",
#       subject_slug="patologia-especial",
#       topic_slug="patol-sistema-endocrino",  # opcional
#   )
#   # ids = {"disciplineId": X, "subjectId": Y, "topicId": Z | None}

import re, sys
import mysql.connector
from dotenv import load_dotenv
import os

load_dotenv()

def _parse_db_url(url: str) -> dict:
    m = re.match(r"mysql://([^:]+):([^@]+)@([^:/]+):(\d+)/([^?]+)", url)
    if not m:
        sys.exit(f"DATABASE_URL inválida: {url}")
    return {"user": m[1], "password": m[2], "host": m[3], "port": int(m[4]), "database": m[5]}

def resolve_ids(
    discipline_slug: str,
    subject_slug: str,
    topic_slug: str | None = None,
    db_url: str | None = None,
) -> dict:
    url = db_url or os.getenv("DATABASE_URL", "")
    if not url:
        sys.exit("DATABASE_URL não definida no .env")

    conn = mysql.connector.connect(**_parse_db_url(url))
    cur = conn.cursor(dictionary=True)

    # Grande Área
    cur.execute("SELECT id FROM disciplines WHERE slug = %s LIMIT 1", (discipline_slug,))
    row = cur.fetchone()
    if not row:
        cur.close(); conn.close()
        sys.exit(f"Grande Área não encontrada: slug='{discipline_slug}'\nCrie pelo Admin antes de rodar o script.")
    discipline_id = row["id"]

    # Assunto
    cur.execute(
        "SELECT id FROM subjects WHERE slug = %s AND disciplineId = %s LIMIT 1",
        (subject_slug, discipline_id)
    )
    row = cur.fetchone()
    if not row:
        cur.close(); conn.close()
        sys.exit(f"Assunto não encontrado: slug='{subject_slug}' em disciplineId={discipline_id}\nCrie pelo Admin antes de rodar o script.")
    subject_id = row["id"]

    # Tópico (opcional)
    topic_id = None
    if topic_slug:
        cur.execute(
            "SELECT id FROM topics WHERE slug = %s AND subjectId = %s LIMIT 1",
            (topic_slug, subject_id)
        )
        row = cur.fetchone()
        if not row:
            cur.close(); conn.close()
            sys.exit(f"Tópico não encontrado: slug='{topic_slug}' em subjectId={subject_id}\nCrie pelo Admin → Qualidade → '+ Criar tópico'.")
        topic_id = row["id"]

    cur.close()
    conn.close()

    result = {"disciplineId": discipline_id, "subjectId": subject_id, "topicId": topic_id}
    print(f"  [hierarquia] disciplineId={discipline_id} | subjectId={subject_id} | topicId={topic_id}")
    return result
