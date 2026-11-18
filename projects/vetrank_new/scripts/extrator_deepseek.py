# VetRank — Geração de Questões via DeepSeek API (Python)
# Saída: array JSON plano compatível com normalize_questions_json.mjs
#
# Estratégia de cache: o trecho do PDF fica no system message (fixo por chunk).
# O DeepSeek cacheia o KV automaticamente — 2ª–8ª requisição por chunk custam
# ~10% do preço normal nos tokens de entrada (prompt_cache_hit_tokens).
#
# Env vars (.env):
#   DEEPSEEK_API_KEY=sk-...
#   PDF_PATH=D:/vetrank/Livro/fisiologia_dukes.pdf
#   OUTPUT_JSON_PATH=D:/vetrank/Livro/questoes_fisiologia.json
#   SUBJECT_TAG=Fisiologia Cardiovascular   (mude por sessão/capítulo)
#   SUBJECT_ID=30007
#   DISCIPLINE_ID=30001
#   YEAR=2026
#   QUESTIONS_PER_MODEL=3   (questões por modelo por chunk; 3 × 8 = 24/chunk)
#   MODELS_PER_CHUNK=8      (quantos dos 8 modelos rodar por chunk; 1–8)
#   CHUNK_SIZE=25000        (chars; maior = mais tokens cacheados por chunk)
#   MAX_CHUNKS=0            (0 = processar tudo)
#   START_CHUNK=0           (para retomar a partir de um chunk específico)
#
# IDs de referência:
#   Fisiologia Veterinária I e II  → disciplineId=30001 | subjectId=30007
#   Microbiologia Veterinária      → disciplineId=30002 | subjectId=30010
#   Patologia Geral                → disciplineId=30002 | subjectId=30013
#   Patologia Especial/Sistêmica   → disciplineId=30002 | subjectId=30014
#   Farmacologia                   → disciplineId=30002 | subjectId=30015
#   Toxicologia                    → disciplineId=30002 | subjectId=30016
#
# Uso: python scripts/extrator_deepseek.py

import os
import json
import time
import re
import openai
from PyPDF2 import PdfReader
from dotenv import load_dotenv

load_dotenv()

# ── Configuração ──────────────────────────────────────────────────────────────
PDF_PATH            = os.getenv("PDF_PATH",             "./livro.pdf")
OUTPUT_PATH         = os.getenv("OUTPUT_JSON_PATH",     "./questoes_deepseek.json")
CHUNK_SIZE          = int(os.getenv("CHUNK_SIZE",       "25000"))
QUESTIONS_PER_MODEL = int(os.getenv("QUESTIONS_PER_MODEL", "3"))
MODELS_PER_CHUNK    = int(os.getenv("MODELS_PER_CHUNK", "8"))
MAX_CHUNKS          = int(os.getenv("MAX_CHUNKS",       "0"))   # 0 = todos
START_CHUNK         = int(os.getenv("START_CHUNK",      "0"))
DISCIPLINE_ID       = int(os.getenv("DISCIPLINE_ID",    "30001"))
SUBJECT_ID          = int(os.getenv("SUBJECT_ID",       "30007"))
SUBJECT_TAG         = os.getenv("SUBJECT_TAG",           "Fisiologia Veterinária")
YEAR                = int(os.getenv("YEAR",              "2026"))

# M1–M8 apenas (M9 não existe no VetRank)
QUESTION_MODELS = ['M1', 'M2', 'M3', 'M4', 'M5', 'M6', 'M7', 'M8']

# Opções fixas do M5 — NUNCA geradas pela IA, sempre injetadas pelo script
M5_OPTIONS = [
    {"id": "A", "textPt": "As asserções I e II são verdadeiras, e a II é justificativa correta da I."},
    {"id": "B", "textPt": "As asserções I e II são verdadeiras, mas a II não é justificativa correta da I."},
    {"id": "C", "textPt": "A asserção I é verdadeira, e a II é falsa."},
    {"id": "D", "textPt": "A asserção I é falsa, e a II é verdadeira."},
    {"id": "E", "textPt": "As asserções I e II são falsas."},
]

client = openai.OpenAI(
    api_key=os.getenv("DEEPSEEK_API_KEY"),
    base_url="https://api.deepseek.com",
)

# ── Regras por modelo ─────────────────────────────────────────────────────────
def model_rules(model: str) -> str:
    rules = {
        'M1': 'Pergunta direta. 5 alternativas (A–E), apenas uma correta.\n  modelId: "M1", questionType: "multiple_choice", questionModel: "enade"',
        'M2': 'textPt é afirmação incompleta terminando em ":" ou "..."; alternativas completam a frase.\n  modelId: "M2", questionType: "multiple_choice", questionModel: "enade"',
        'M3': (
            'textPt contém assertivas I, II, III, IV. Alternativas são combinações (ex: "Apenas I e III").\n'
            '  Formato textPt: "Sobre [tema]:\\n\\nI. [assertiva]\\nII. [assertiva]\\nIII. [assertiva]\\nIV. [assertiva]\\n\\nSão CORRETAS apenas:"\n'
            '  modelId: "M3", questionType: "complex_multiple_choice", questionModel: "enade"'
        ),
        'M4': 'textPt DEVE conter "EXCETO", "NÃO" ou "INCORRETO" em MAIÚSCULAS.\n  modelId: "M4", questionType: "multiple_choice", questionModel: "enade"',
        'M5': (
            'textPt no formato EXATO: "ASSERÇÃO I: [texto].\\n\\nPORQUE\\n\\nASSERÇÃO II: [texto]."\n'
            '  Inclua "assertion1" (Asserção I sem prefixo) e "assertion2" (Asserção II sem prefixo).\n'
            '  "correctOptionLetter": A=ambas V+II justifica I | B=ambas V sem relação | C=I V II F | D=I F II V | E=ambas F\n'
            '  NÃO inclua o campo "options" — injetado automaticamente.\n'
            '  modelId: "M5", questionType: "assertion_reason", questionModel: "assertion_reason"'
        ),
        'M6': (
            'textPt com lacuna [___] (uma) ou [1], [2] (múltiplas). Alternativas preenchem a(s) lacuna(s).\n'
            '  OBRIGATÓRIO: EXATAMENTE 5 alternativas (A, B, C, D, E) — nunca 3 ou 4.\n'
            '  modelId: "M6", questionType: "cloze", questionModel: "enade"'
        ),
        'M7': (
            'textPt DEVE começar com "[CASO CLÍNICO]" seguido de caso clínico detalhado e pergunta.\n'
            '  modelId: "M7", questionType: "clinical_case", questionModel: "enade"'
        ),
        'M8': (
            'textPt contém duas colunas para associação.\n'
            '  Formato: "Relacione...\\n\\nCOLUNA I\\n1. [item]\\n2. ...\\n\\nCOLUNA II\\na) [desc]\\nb) ...\\n\\nA associação CORRETA é:"\n'
            '  Alternativas no formato "1-a / 2-c / 3-b / 4-d".\n'
            '  OBRIGATÓRIO: EXATAMENTE 5 alternativas (A, B, C, D, E) com mapeamentos diferentes — nunca 4.\n'
            '  modelId: "M8", questionType: "matching", questionModel: "enade"'
        ),
    }
    return rules.get(model, '')

# ── Pós-processamento ─────────────────────────────────────────────────────────
def post_process(q: dict, model_type: str) -> dict:
    out = dict(q)

    out['disciplineId'] = DISCIPLINE_ID
    out['subjectId']    = SUBJECT_ID
    out['year']         = YEAR
    out['author']       = 'VetRank'
    out['isPremium']    = True
    out['status']       = 'approved'
    out['modelId']      = model_type
    if SUBJECT_TAG:
        out['subjectTag'] = SUBJECT_TAG

    if model_type == 'M5':
        out['questionType']  = 'assertion_reason'
        out['questionModel'] = 'assertion_reason'
        out['options']       = [dict(o) for o in M5_OPTIONS]
    elif model_type == 'M3':
        out.setdefault('questionType',  'complex_multiple_choice')
        out.setdefault('questionModel', 'enade')
    else:
        out.setdefault('questionType',  'multiple_choice')
        out.setdefault('questionModel', 'enade')

    # alternatives → options
    if 'options' not in out and 'alternatives' in out:
        letters = ['A', 'B', 'C', 'D', 'E']
        out['options'] = [
            {'id': letters[i], 'textPt': str(t).strip()}
            for i, t in enumerate(out['alternatives'])
        ]
    out.pop('alternatives', None)

    # Normalizar options → {id, textPt}
    if isinstance(out.get('options'), list):
        letters = ['A', 'B', 'C', 'D', 'E']
        normalized = []
        for i, o in enumerate(out['options']):
            if isinstance(o, str):
                normalized.append({
                    'id': letters[i] if i < 5 else chr(65 + i),
                    'textPt': re.sub(r'^[A-E][).]\s*', '', o, flags=re.IGNORECASE).strip()
                })
            elif isinstance(o, dict):
                obj = dict(o)
                if 'id' not in obj and 'label' in obj:
                    obj['id'] = obj.pop('label')
                if 'textPt' not in obj and 'text' in obj:
                    obj['textPt'] = obj.pop('text')
                normalized.append(obj)
        out['options'] = normalized

    # Normalizar difficulty — garante valor aceito pelo validador
    VALID_DIFF = {'very_easy', 'easy', 'medium', 'hard', 'very_hard'}
    diff_map = {
        'difícil': 'hard', 'dificil': 'hard', 'difficult': 'hard',
        'muito difícil': 'very_hard', 'muito dificil': 'very_hard',
        'very difficult': 'very_hard', 'very_difficult': 'very_hard',
        'médio': 'medium', 'medio': 'medium', 'moderate': 'medium',
        'fácil': 'easy', 'facil': 'easy', 'simple': 'easy',
        'muito fácil': 'very_easy', 'very easy': 'very_easy',
    }
    raw_diff = str(out.get('difficulty', '')).lower().strip()
    out['difficulty'] = raw_diff if raw_diff in VALID_DIFF else diff_map.get(raw_diff, 'medium')

    # Normalizar correctOptionLetter — sem IndexError em None/""/lixo
    def _safe_letter(value) -> str | None:
        s = str(value).upper().strip() if value is not None else ''
        return s[0] if s and s[0] in 'ABCDE' else None

    if 'correctOptionLetter' not in out or out.get('correctOptionLetter') is None:
        for alias in ('correctAlternative', 'correctOption'):
            letter = _safe_letter(out.get(alias))
            if letter:
                out['correctOptionLetter'] = letter
                break

    if 'correctOptionLetter' in out:
        letter = _safe_letter(out['correctOptionLetter'])
        if letter:
            out['correctOptionLetter'] = letter
        else:
            del out['correctOptionLetter']

    for campo in ('id', 'correctAlternative', 'answer', 'correct'):
        out.pop(campo, None)

    return out

# ── Extração de texto do PDF ──────────────────────────────────────────────────
def extract_text_from_pdf(pdf_path: str) -> str:
    print(f"\n📄 Lendo PDF: {pdf_path}")
    reader = PdfReader(pdf_path)
    full_text = ""
    total = len(reader.pages)
    for i, page in enumerate(reader.pages, 1):
        full_text += (page.extract_text() or "") + " "
        if i % 20 == 0:
            print(f"   📖 Página {i}/{total}")
    text = full_text.strip()
    print(f"   ✅ {len(text):,} caracteres extraídos de {total} páginas")
    return text

# ── Prompt do usuário (instrução pequena — não cacheada) ──────────────────────
def build_user_prompt(model_type: str, num_q: int) -> str:
    is_m5 = model_type == 'M5'

    example = (
        '{\n'
        '  "textPt": "ASSERÇÃO I: [texto].\\n\\nPORQUE\\n\\nASSERÇÃO II: [texto].",\n'
        '  "assertion1": "texto da Asserção I",\n'
        '  "assertion2": "texto da Asserção II",\n'
        '  "correctOptionLetter": "A",\n'
        '  "explanationPt": "justificativa detalhada",\n'
        '  "difficulty": "hard"\n'
        '}'
    ) if is_m5 else (
        '{\n'
        '  "textPt": "enunciado",\n'
        '  "options": [\n'
        '    {"id": "A", "textPt": "alternativa A"},\n'
        '    {"id": "B", "textPt": "alternativa B"},\n'
        '    {"id": "C", "textPt": "alternativa C"},\n'
        '    {"id": "D", "textPt": "alternativa D"},\n'
        '    {"id": "E", "textPt": "alternativa E"}\n'
        '  ],\n'
        '  "correctOptionLetter": "B",\n'
        '  "explanationPt": "justificativa detalhada",\n'
        '  "difficulty": "medium"\n'
        '}'
    )

    return (
        f"Use o TEXTO FONTE acima para gerar exatamente {num_q} questão(ões) do modelo {model_type}.\n\n"
        f"REGRAS GERAIS:\n"
        f"- NUNCA mencione 'segundo o texto', 'de acordo com o texto' ou referências ao PDF.\n"
        f"- O enunciado deve ser autossuficiente.\n"
        f"- difficulty: 'medium' ou 'hard'\n\n"
        f"MODELO {model_type}:\n{model_rules(model_type)}\n\n"
        f"OBRIGATÓRIO: Sempre gere EXATAMENTE 5 alternativas (A, B, C, D, E) — nunca 3 ou 4.\n\n"
        f"Retorne APENAS um array JSON válido com {num_q} objeto(s). Sem markdown, sem texto extra.\n"
        f"Exemplo:\n{example}"
    )

# ── Gerar lote (system message = chunk cacheado) ──────────────────────────────
def generate_batch(chunk_idx: int, model_type: str, system_with_chunk: str, num_q: int) -> list:
    try:
        response = client.chat.completions.create(
            model="deepseek-chat",
            messages=[
                {"role": "system", "content": system_with_chunk},
                {"role": "user",   "content": build_user_prompt(model_type, num_q)},
            ],
            temperature=0.7,
        )

        usage = response.usage
        cache_hit  = getattr(usage, 'prompt_cache_hit_tokens',  0)
        cache_miss = getattr(usage, 'prompt_cache_miss_tokens', 0)
        icon = "💾" if cache_hit > 0 else "🔄"
        print(
            f"   {icon} In={usage.prompt_tokens} "
            f"(hit={cache_hit} miss={cache_miss}) | Out={usage.completion_tokens}"
        )

        raw_text = (response.choices[0].message.content or "[]").strip()
        raw_text = re.sub(r'^```(?:json)?\s*', '', raw_text, flags=re.IGNORECASE)
        raw_text = re.sub(r'\s*```$', '', raw_text).strip()

        # Extrair o primeiro array JSON por correspondência de colchetes
        start = raw_text.find('[')
        if start == -1:
            start = raw_text.find('{')
            if start != -1:
                raw_text = '[' + raw_text[start:] + ']'
        else:
            depth, in_str, esc = 0, False, False
            end = start
            for i, ch in enumerate(raw_text[start:], start):
                if esc:
                    esc = False; continue
                if ch == '\\' and in_str:
                    esc = True; continue
                if ch == '"' and not esc:
                    in_str = not in_str; continue
                if in_str:
                    continue
                if ch == '[':
                    depth += 1
                elif ch == ']':
                    depth -= 1
                    if depth == 0:
                        end = i; break
            raw_text = raw_text[start:end + 1]

        raw = json.loads(raw_text)
        if not isinstance(raw, list):
            raw = [raw]

        return [post_process(q, model_type) for q in raw]

    except Exception as e:
        print(f"   ❌ Erro chunk {chunk_idx} modelo {model_type}: {e}")
        return []

# ── Main ──────────────────────────────────────────────────────────────────────
def main():
    models_ativo = QUESTION_MODELS[:MODELS_PER_CHUNK]

    print("\n" + "=" * 65)
    print("🚀 VetRank — Gerador DeepSeek (cache automático)")
    print("=" * 65)
    print(f"   disciplineId: {DISCIPLINE_ID} | subjectId: {SUBJECT_ID}")
    print(f"   subjectTag: \"{SUBJECT_TAG}\" | year: {YEAR}")
    print(f"   Chunk: {CHUNK_SIZE:,} chars | {QUESTIONS_PER_MODEL} questões × {len(models_ativo)} modelos = {QUESTIONS_PER_MODEL * len(models_ativo)} por chunk")
    print(f"   Modelos: {', '.join(models_ativo)}")

    pdf_text = extract_text_from_pdf(PDF_PATH)

    chunks = [pdf_text[i:i + CHUNK_SIZE] for i in range(0, len(pdf_text), CHUNK_SIZE)]
    end = min(len(chunks), START_CHUNK + MAX_CHUNKS) if MAX_CHUNKS > 0 else len(chunks)
    total_proc = end - START_CHUNK
    print(f"\n🔢 {len(chunks)} chunks totais | processando {START_CHUNK + 1}–{end} ({total_proc} chunks)")
    print(f"   Estimativa: ~{total_proc * QUESTIONS_PER_MODEL * len(models_ativo)} questões\n")

    # Retomada segura — carregar questões já geradas
    all_questions = []
    if os.path.exists(OUTPUT_PATH):
        try:
            with open(OUTPUT_PATH, 'r', encoding='utf-8') as f:
                existing = json.load(f)
            if isinstance(existing, list) and existing:
                all_questions = existing
                print(f"   ♻️  Retomando: {len(all_questions)} questões já existentes\n")
        except Exception:
            pass

    for i in range(START_CHUNK, end):
        chunk = chunks[i]

        # System message = prefixo fixo + trecho do PDF
        # Idêntico para todos os modelos deste chunk → DeepSeek cacheia o KV
        system_with_chunk = (
            "Você é especialista em elaborar questões para concursos de Medicina Veterinária (CFMV/ENADE). "
            "Responda APENAS com um array JSON válido, sem markdown, sem texto extra.\n\n"
            f"TEXTO FONTE:\n\"\"\"\n{chunk}\n\"\"\""
        )

        print(f"\n{'─' * 65}")
        print(f"📦 Chunk {i + 1}/{end} ({len(chunk):,} chars)")

        chunk_total = 0
        for j, model_type in enumerate(models_ativo):
            label = "(preenchendo cache)" if j == 0 else f"(cache hit esperado)"
            print(f"   🎯 {model_type} {label}")

            questions = generate_batch(i + 1, model_type, system_with_chunk, QUESTIONS_PER_MODEL)

            if questions:
                all_questions.extend(questions)
                chunk_total += len(questions)
                with open(OUTPUT_PATH, 'w', encoding='utf-8') as f:
                    json.dump(all_questions, f, ensure_ascii=False, indent=2)
            else:
                print(f"   ⚠️  Nenhuma questão gerada")

            # Delay curto entre modelos (cache ainda quente)
            time.sleep(0.8)

        print(f"   ✅ +{chunk_total} questões neste chunk | Total: {len(all_questions)}")

        # Delay maior entre chunks (muda o system message = novo preenchimento de cache)
        if i < end - 1:
            time.sleep(2)

    print("\n" + "=" * 65)
    print(f"🎉 Concluído! {len(all_questions)} questões salvas em:")
    print(f"   {OUTPUT_PATH}")
    print(f"\nPróximo passo — validar e normalizar:")
    out_val = OUTPUT_PATH.replace('.json', '_validado.json')
    print(f'   node scripts/normalize_questions_json.mjs {OUTPUT_PATH} {out_val} "{SUBJECT_TAG}"')
    print("=" * 65)

if __name__ == "__main__":
    main()
