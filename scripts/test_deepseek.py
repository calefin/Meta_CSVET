import json, os, re
from dotenv import load_dotenv
import openai

load_dotenv()
API_KEY = os.getenv("DEEPSEEK_API_KEY", "")
MODEL = os.getenv("DEEPSEEK_MODEL", "deepseek-chat")

client = openai.OpenAI(api_key=API_KEY, base_url="https://api.deepseek.com")

with open("Livro/questoes_fisiologia_validado.json", encoding="utf-8") as f:
    qs = json.load(f)

batch = qs[:3]
items = "\n\n".join(f"[{i}] {q['textPt'][:200]}" for i, q in enumerate(batch))

SYSTEMS_TEXT = """  - Sistema Nervoso
  - Sistema Cardiovascular
  - Sistema Respiratorio
  - Sistema Digestorio
  - Sistema Renal e Urinario
  - Sistema Endocrino
  - Sistema Reprodutor
  - Sistema Musculoesqueletico
  - Sangue e Imunidade
  - Termorregulacao e Metabolismo
  - Fisiologia da Lactacao
  - Fisiologia Sensorial"""

prompt = f"""Classifique cada questao de fisiologia veterinaria no sistema correto.

SISTEMAS:
{SYSTEMS_TEXT}

QUESTOES:
{items}

Retorne SOMENTE um array JSON, sem explicacoes:
["Sistema X", "Sistema Y", ...]"""

resp = client.chat.completions.create(
    model=MODEL,
    messages=[{"role": "user", "content": prompt}],
    temperature=0.0,
    max_tokens=200,
)
raw = resp.choices[0].message.content
print("RAW:", repr(raw))
raw2 = re.sub(r"^```(?:json)?\s*", "", raw.strip())
raw2 = re.sub(r"\s*```$", "", raw2)
print("CLEANED:", repr(raw2))
parsed = json.loads(raw2)
print("PARSED:", parsed, "len:", len(parsed))
