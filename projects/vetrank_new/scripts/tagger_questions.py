# VetRank — Tagger de Questões via DeepSeek
#
# Lê questões do banco sem subjectId (ou com subjectId específico errado),
# envia em batches para o DeepSeek classificar o assunto correto,
# e atualiza o banco com subjectId + subjectTag corretos.
#
# Env vars (.env):
#   DATABASE_URL=mysql://user:pass@host:3306/vetrank
#   DEEPSEEK_API_KEY=sk-...
#   DEEPSEEK_MODEL=deepseek-chat
#   TAGGER_DISCIPLINE_ID=30001          (obrigatório — disciplina a taggear)
#   TAGGER_SOURCE_SUBJECT_ID=           (opcional — filtrar só de um assunto errado)
#   TAGGER_ONLY_NULL=true               (true = só subjectId nulo; false = todos da disciplina)
#   TAGGER_BATCH_SIZE=20                (questões por chamada à API)
#   TAGGER_DRY_RUN=false                (true = só imprime, não grava no banco)
#   TAGGER_LIMIT=0                      (0 = processar tudo)
#   TAGGER_TAG_ONLY=false               (true = só atualiza subjectTag, não mexe em subjectId)
#
# Modo TAG_ONLY (TAGGER_TAG_ONLY=true):
#   - Não consulta a tabela subjects
#   - Usa lista hardcoded de sistemas fisiológicos (para subjectId 30007 — Fisiologia Animal)
#   - Atualiza APENAS subjectTag, mantendo subjectId intacto
#   - Requer TAGGER_SOURCE_SUBJECT_ID=30007
#
# Uso:
#   python scripts/tagger_questions.py
#
# Saída: grava diretamente no banco. Salva log em tagger_log_TIMESTAMP.json

import os
import json
import time
import re
import sys
from datetime import datetime
import openai
import mysql.connector
from dotenv import load_dotenv

load_dotenv()

# ── Config ────────────────────────────────────────────────────────────────────
DATABASE_URL        = os.getenv("DATABASE_URL", "")
DEEPSEEK_API_KEY    = os.getenv("DEEPSEEK_API_KEY", "")
DEEPSEEK_MODEL      = os.getenv("DEEPSEEK_MODEL", "deepseek-chat")
DISCIPLINE_ID       = int(os.getenv("TAGGER_DISCIPLINE_ID", "0"))
SOURCE_SUBJECT_ID   = os.getenv("TAGGER_SOURCE_SUBJECT_ID", "").strip()
ONLY_NULL           = os.getenv("TAGGER_ONLY_NULL", "true").lower() == "true"
BATCH_SIZE          = int(os.getenv("TAGGER_BATCH_SIZE", "20"))
DRY_RUN             = os.getenv("TAGGER_DRY_RUN", "false").lower() == "true"
TAGGER_LIMIT        = int(os.getenv("TAGGER_LIMIT", "0"))
TAG_ONLY            = os.getenv("TAGGER_TAG_ONLY", "false").lower() == "true"

# Sistemas fisiológicos para modo TAG_ONLY (subjectId 30007 — Fisiologia Animal)
FISIO_SISTEMAS = [
    "Sistema Nervoso",
    "Sistema Cardiovascular",
    "Sistema Respiratório",
    "Sistema Digestório",
    "Sistema Renal e Urinário",
    "Sistema Endócrino",
    "Sistema Reprodutor",
    "Sistema Musculoesquelético",
    "Sangue e Imunidade",
    "Termorregulação e Metabolismo",
    "Fisiologia da Lactação",
    "Fisiologia Sensorial",
]

if not DISCIPLINE_ID:
    sys.exit("ERRO: defina TAGGER_DISCIPLINE_ID no .env")
if not DEEPSEEK_API_KEY:
    sys.exit("ERRO: defina DEEPSEEK_API_KEY no .env")
if TAG_ONLY and not SOURCE_SUBJECT_ID:
    sys.exit("ERRO: TAGGER_TAG_ONLY=true requer TAGGER_SOURCE_SUBJECT_ID definido no .env")

# ── Parse DATABASE_URL ────────────────────────────────────────────────────────
def parse_db_url(url: str) -> dict:
    # mysql://user:pass@host:port/dbname
    m = re.match(r"mysql://([^:]+):([^@]+)@([^:/]+):(\d+)/([^?]+)", url)
    if not m:
        sys.exit(f"DATABASE_URL inválida: {url}")
    return {"user": m[1], "password": m[2], "host": m[3], "port": int(m[4]), "database": m[5]}

def get_conn():
    cfg = parse_db_url(DATABASE_URL)
    return mysql.connector.connect(**cfg)

# ── Buscar assuntos da disciplina ─────────────────────────────────────────────
def fetch_subjects(conn, discipline_id: int) -> list[dict]:
    cur = conn.cursor(dictionary=True)
    cur.execute(
        "SELECT id, namePt, slug FROM subjects WHERE disciplineId = %s AND active = 1 ORDER BY namePt",
        (discipline_id,)
    )
    rows = cur.fetchall()
    cur.close()
    return rows

# ── Buscar questões a taggear ─────────────────────────────────────────────────
def fetch_questions(conn, discipline_id: int, only_null: bool, source_subject_id: str, limit: int) -> list[dict]:
    cur = conn.cursor(dictionary=True)
    conditions = ["q.disciplineId = %s"]
    params = [discipline_id]

    if source_subject_id:
        conditions.append("q.subjectId = %s")
        params.append(int(source_subject_id))
    elif only_null:
        conditions.append("q.subjectId IS NULL")

    where = " AND ".join(conditions)
    query = f"""
        SELECT q.id, q.textPt, q.subjectId, q.subjectTag
        FROM questions q
        WHERE {where}
        ORDER BY q.id
        {"LIMIT %s" if limit > 0 else ""}
    """
    if limit > 0:
        params.append(limit)

    cur.execute(query, params)
    rows = cur.fetchall()
    cur.close()
    return rows

# ── DeepSeek: classificar batch ───────────────────────────────────────────────
client = openai.OpenAI(api_key=DEEPSEEK_API_KEY, base_url="https://api.deepseek.com")

def build_subjects_list(subjects: list[dict]) -> str:
    return "\n".join(f"  {s['id']}: {s['namePt']}" for s in subjects)

def classify_batch_tag_only(questions: list[dict], sistemas: list[str]) -> list[dict]:
    sistemas_text = "\n".join(f"  - {s}" for s in sistemas)
    questions_text = "\n\n".join(
        f"[{q['id']}] {q['textPt'][:400]}" for q in questions
    )

    prompt = f"""Você é um classificador de questões de Fisiologia Veterinária. Classifique cada questão no sistema fisiológico mais adequado.

SISTEMAS DISPONÍVEIS:
{sistemas_text}

QUESTÕES (formato [id] enunciado):
{questions_text}

INSTRUÇÕES:
- Para cada questão, escolha exatamente um sistema da lista acima
- Use o nome exato como aparece na lista
- Se a questão abranger múltiplos sistemas, escolha o predominante

Retorne APENAS um array JSON válido, sem markdown:
[
  {{"id": <question_id>, "subjectTag": "<nome_exato_do_sistema>"}},
  ...
]"""

    for attempt in range(3):
        try:
            resp = client.chat.completions.create(
                model=DEEPSEEK_MODEL,
                messages=[{"role": "user", "content": prompt}],
                temperature=0.1,
                max_tokens=800,
            )
            raw = resp.choices[0].message.content.strip()
            raw = re.sub(r"^```(?:json)?\s*", "", raw)
            raw = re.sub(r"\s*```$", "", raw)
            result = json.loads(raw)
            if isinstance(result, list):
                return result
        except Exception as e:
            print(f"  Tentativa {attempt+1} falhou: {e}")
            time.sleep(2 ** attempt)
    return []


def update_subjectTag_only(conn, classified: list[dict]) -> int:
    if not classified:
        return 0
    cur = conn.cursor()
    updated = 0
    for item in classified:
        try:
            cur.execute(
                "UPDATE questions SET subjectTag = %s WHERE id = %s",
                (item["subjectTag"], item["id"])
            )
            updated += 1
        except Exception as e:
            print(f"  Erro ao atualizar questão {item.get('id')}: {e}")
    conn.commit()
    cur.close()
    return updated


def classify_batch(questions: list[dict], subjects: list[dict]) -> list[dict]:
    subjects_text = build_subjects_list(subjects)
    questions_text = "\n\n".join(
        f"[{q['id']}] {q['textPt'][:400]}" for q in questions
    )

    prompt = f"""Você é um classificador de questões veterinárias. Sua tarefa é atribuir o assunto correto para cada questão com base no conteúdo do enunciado.

ASSUNTOS DISPONÍVEIS (id: nome):
{subjects_text}

QUESTÕES (formato [id] enunciado):
{questions_text}

INSTRUÇÕES:
- Para cada questão, escolha o assunto mais adequado da lista acima
- Use APENAS os IDs listados acima
- Se nenhum assunto se encaixar bem, use o assunto mais próximo
- subjectTag deve ser o namePt do assunto escolhido

Retorne APENAS um array JSON válido, sem markdown:
[
  {{"id": <question_id>, "subjectId": <subject_id>, "subjectTag": "<subject_namePt>"}},
  ...
]"""

    for attempt in range(3):
        try:
            resp = client.chat.completions.create(
                model=DEEPSEEK_MODEL,
                messages=[{"role": "user", "content": prompt}],
                temperature=0.1,
                max_tokens=1000,
            )
            raw = resp.choices[0].message.content.strip()
            # remover possível markdown
            raw = re.sub(r"^```(?:json)?\s*", "", raw)
            raw = re.sub(r"\s*```$", "", raw)
            result = json.loads(raw)
            if isinstance(result, list):
                return result
        except Exception as e:
            print(f"  Tentativa {attempt+1} falhou: {e}")
            time.sleep(2 ** attempt)
    return []

# ── Atualizar banco ───────────────────────────────────────────────────────────
def update_questions(conn, classified: list[dict]) -> int:
    if not classified:
        return 0
    cur = conn.cursor()
    updated = 0
    for item in classified:
        try:
            cur.execute(
                "UPDATE questions SET subjectId = %s, subjectTag = %s WHERE id = %s",
                (item["subjectId"], item["subjectTag"], item["id"])
            )
            updated += 1
        except Exception as e:
            print(f"  Erro ao atualizar questão {item.get('id')}: {e}")
    conn.commit()
    cur.close()
    return updated

# ── Main ──────────────────────────────────────────────────────────────────────
def main():
    mode = "TAG_ONLY" if TAG_ONLY else "FULL"
    print(f"{'[DRY RUN] ' if DRY_RUN else ''}VetRank Tagger [{mode}] — disciplineId={DISCIPLINE_ID}")
    print(f"  Modelo: {DEEPSEEK_MODEL} | Batch: {BATCH_SIZE} | Limite: {TAGGER_LIMIT or 'todos'}")

    conn = get_conn()

    if TAG_ONLY:
        print(f"\nModo TAG_ONLY: atualizando apenas subjectTag (subjectId mantido)")
        print(f"  subjectId filtro: {SOURCE_SUBJECT_ID}")
        print(f"  {len(FISIO_SISTEMAS)} sistemas disponíveis:")
        for s in FISIO_SISTEMAS:
            print(f"    - {s}")
        targets = FISIO_SISTEMAS
    else:
        subjects = fetch_subjects(conn, DISCIPLINE_ID)
        if not subjects:
            sys.exit(f"Nenhum assunto encontrado para disciplineId={DISCIPLINE_ID}")
        print(f"\n{len(subjects)} assuntos disponíveis:")
        for s in subjects:
            print(f"  {s['id']}: {s['namePt']}")
        targets = subjects

    questions = fetch_questions(conn, DISCIPLINE_ID, ONLY_NULL, SOURCE_SUBJECT_ID, TAGGER_LIMIT)
    print(f"\n{len(questions)} questões para classificar\n")

    if not questions:
        print("Nada a fazer.")
        conn.close()
        return

    log = []
    total_updated = 0
    batches = [questions[i:i+BATCH_SIZE] for i in range(0, len(questions), BATCH_SIZE)]

    for i, batch in enumerate(batches):
        print(f"Batch {i+1}/{len(batches)} ({len(batch)} questões)...", end=" ", flush=True)

        if TAG_ONLY:
            classified = classify_batch_tag_only(batch, targets)
            if not classified:
                print("FALHOU — pulando")
                continue
            valid_tags = set(targets)
            valid = [c for c in classified if c.get("subjectTag") in valid_tags]
            invalid = len(classified) - len(valid)
            if invalid:
                print(f"  {invalid} item(s) com subjectTag inválido ignorados")
            log.extend(valid)
            if not DRY_RUN:
                n = update_subjectTag_only(conn, valid)
                total_updated += n
                print(f"✓ {n} atualizadas")
            else:
                print(f"[dry] {len(valid)} seriam atualizadas")
                for v in valid:
                    print(f"      #{v['id']} → subjectTag={v['subjectTag']}")
        else:
            classified = classify_batch(batch, targets)
            if not classified:
                print("FALHOU — pulando")
                continue
            valid_ids = {s["id"] for s in targets}
            valid = [c for c in classified if c.get("subjectId") in valid_ids]
            invalid = len(classified) - len(valid)
            if invalid:
                print(f"  {invalid} item(s) com subjectId inválido ignorados")
            log.extend(valid)
            if not DRY_RUN:
                n = update_questions(conn, valid)
                total_updated += n
                print(f"✓ {n} atualizadas")
            else:
                print(f"[dry] {len(valid)} seriam atualizadas")
                for v in valid:
                    print(f"      #{v['id']} → {v['subjectId']} ({v['subjectTag']})")

        time.sleep(0.5)

    log_path = f"tagger_log_{datetime.now().strftime('%Y%m%d_%H%M%S')}.json"
    with open(log_path, "w", encoding="utf-8") as f:
        json.dump(log, f, ensure_ascii=False, indent=2)

    conn.close()
    print(f"\nConcluído. {total_updated} questões atualizadas no banco.")
    print(f"Log salvo em: {log_path}")

if __name__ == "__main__":
    main()
