# VetRank — Tagger de JSON de Questões (sem banco)
#
# Lê um JSON de questões, envia em batches para o DeepSeek classificar
# o sistema fisiológico correto, e grava um novo JSON com subjectTag corrigido.
# Útil para corrigir antes do import no banco.
#
# Env vars (.env):
#   DEEPSEEK_API_KEY=sk-...
#   DEEPSEEK_MODEL=deepseek-chat
#   TAGGER_INPUT=Livro/questoes_fisiologia_validado.json
#   TAGGER_OUTPUT=Livro/questoes_fisiologia_tagged.json
#   TAGGER_BATCH_SIZE=30
#   TAGGER_START=0      (retomar a partir do índice N)
#
# Uso: python scripts/tagger_json.py

import os, json, time, re, sys
import openai
from dotenv import load_dotenv

load_dotenv()

INPUT_PATH   = os.getenv("TAGGER_INPUT",  "Livro/questoes_fisiologia_validado.json")
OUTPUT_PATH  = os.getenv("TAGGER_OUTPUT", "Livro/questoes_fisiologia_tagged.json")
BATCH_SIZE   = int(os.getenv("TAGGER_BATCH_SIZE", "30"))
START_IDX    = int(os.getenv("TAGGER_START", "0"))
API_KEY      = os.getenv("DEEPSEEK_API_KEY", "")
MODEL        = os.getenv("DEEPSEEK_MODEL", "deepseek-chat")

if not API_KEY:
    sys.exit("ERRO: defina DEEPSEEK_API_KEY no .env")

# Sistemas fisiológicos para o DeepSeek escolher
SYSTEMS = [
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

SYSTEMS_TEXT = "\n".join(f"  - {s}" for s in SYSTEMS)

client = openai.OpenAI(api_key=API_KEY, base_url="https://api.deepseek.com")

def classify_batch(batch: list[dict]) -> list[str]:
    """Retorna lista de subjectTag para cada questão do batch (mesma ordem)."""
    items = "\n\n".join(
        f"[{i}] {q['textPt'][:300]}" for i, q in enumerate(batch)
    )
    prompt = f"""Classifique cada questão de fisiologia veterinária abaixo no sistema fisiológico correto.

SISTEMAS DISPONÍVEIS:
{SYSTEMS_TEXT}

QUESTÕES (índice entre colchetes):
{items}

Retorne APENAS um array JSON com a classificação de cada questão na mesma ordem, sem markdown:
["Sistema X", "Sistema Y", ...]

Use exatamente os nomes da lista acima."""

    for attempt in range(3):
        try:
            resp = client.chat.completions.create(
                model=MODEL,
                messages=[{"role": "user", "content": prompt}],
                temperature=0.0,
                max_tokens=1500,
            )
            raw = resp.choices[0].message.content.strip()
            raw = re.sub(r"^```(?:json)?\s*", "", raw)
            raw = re.sub(r"\s*```$", "", raw)
            result = json.loads(raw)
            if isinstance(result, list) and len(result) == len(batch):
                valid = []
                for tag in result:
                    if tag in SYSTEMS:
                        valid.append(tag)
                    else:
                        match = next((s for s in SYSTEMS if s.lower() in tag.lower() or tag.lower() in s.lower()), SYSTEMS[0])
                        valid.append(match)
                return valid
            else:
                print(f"  Tentativa {attempt+1}: esperado {len(batch)} tags, recebido {len(result) if isinstance(result, list) else type(result)}")
        except json.JSONDecodeError as e:
            print(f"  Tentativa {attempt+1} JSON inválido: {e} | resposta: {raw[:200]}")
        except Exception as e:
            print(f"  Tentativa {attempt+1} falhou: {e}")
        time.sleep(2 ** attempt)

    print(f"  FALLBACK ativado para batch de {len(batch)} questões")
    return ["Fisiologia Veterinária"] * len(batch)

def main():
    print(f"Lendo {INPUT_PATH}...")
    with open(INPUT_PATH, encoding="utf-8") as f:
        questions = json.load(f)

    total = len(questions)
    print(f"{total} questões | batch={BATCH_SIZE} | start={START_IDX}")

    # Se OUTPUT já existe, retomar de onde parou
    if START_IDX > 0 and os.path.exists(OUTPUT_PATH):
        with open(OUTPUT_PATH, encoding="utf-8") as f:
            done = json.load(f)
        print(f"Retomando: {len(done)} já processadas")
    else:
        done = questions[:START_IDX]  # copiar as já processadas sem alterar

    to_process = questions[START_IDX:]
    batches = [to_process[i:i+BATCH_SIZE] for i in range(0, len(to_process), BATCH_SIZE)]

    for bi, batch in enumerate(batches):
        global_start = START_IDX + bi * BATCH_SIZE
        print(f"Batch {bi+1}/{len(batches)} (questões {global_start}–{global_start+len(batch)-1})...", end=" ", flush=True)

        tags = classify_batch(batch)

        for q, tag in zip(batch, tags):
            q["subjectTag"] = tag
            done.append(q)

        # Salvar progressivamente
        with open(OUTPUT_PATH, "w", encoding="utf-8") as f:
            json.dump(done, f, ensure_ascii=False, indent=2)

        # Distribuição parcial
        from collections import Counter
        dist = Counter(t for t in tags)
        summary = " | ".join(f"{v}×{k}" for k, v in dist.most_common(3))
        print(f"OK [{summary}]")

        time.sleep(0.3)

    print(f"\nConcluído. {len(done)} questões salvas em {OUTPUT_PATH}")

    # Mostrar distribuição final
    from collections import Counter
    dist = Counter(q.get("subjectTag", "") for q in done)
    print("\nDistribuição final:")
    for tag, count in dist.most_common():
        print(f"  {count:5d}  {tag}")

if __name__ == "__main__":
    main()
