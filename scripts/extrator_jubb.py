# VetRank — Gerador de Questões por Capítulos de PDF
# Processa todos os PDFs de uma pasta, gerando ~TARGET_QUESTIONS por PDF.
# Usa resolve_hierarchy.py para obter disciplineId/subjectId por slug (nunca hardcoda IDs).
#
# Estratégia de cache: trecho do PDF no system message (fixo por chunk).
# DeepSeek cacheia o KV automaticamente → 2ª–8ª req por chunk custam ~10%.
#
# Env vars (.env):
#   DEEPSEEK_API_KEY=sk-...
#   DATABASE_URL=mysql://user:pass@host:3306/db
#   PDF_DIR=D:/pdfs/repteis                 (pasta com os PDFs)
#   OUTPUT_DIR=D:/vetrank/Livro/questoes    (pasta de saída — criada automaticamente)
#   DISCIPLINE_SLUG=animais-silvestres
#   SUBJECT_SLUG=repteis-anfibios
#   AREA_CONHECIMENTO=MEDICINA DE RÉPTEIS E ANFÍBIOS
#   TARGET_QUESTIONS=400
#   MODELS_PER_CHUNK=8
#   CHUNK_SIZE=25000
#   MAX_QPM=6
#   MIN_QPM=2
#   DEEPSEEK_MODEL=deepseek-chat
#   YEAR=2026
#
# Uso:
#   python scripts/extrator_jubb.py                → processa todos os PDFs
#   python scripts/extrator_jubb.py anestesia      → filtra por nome do arquivo

import os, sys, json, time, re, math
import openai
from PyPDF2 import PdfReader
from dotenv import load_dotenv

load_dotenv()

# ── Configuração ──────────────────────────────────────────────────────────────
PDF_DIR          = os.getenv("PDF_DIR",          "D:/pdfs/repteis")
OUTPUT_DIR       = os.getenv("OUTPUT_DIR",       "D:/vetrank/Livro/questoes")
DISCIPLINE_SLUG  = os.getenv("DISCIPLINE_SLUG",  "animais-silvestres")
SUBJECT_SLUG     = os.getenv("SUBJECT_SLUG",     "repteis-anfibios")
AREA_CONHECIMENTO = os.getenv("AREA_CONHECIMENTO", "MEDICINA DE RÉPTEIS E ANFÍBIOS")
TARGET_QUESTIONS = int(os.getenv("TARGET_QUESTIONS", "400"))
MODELS_PER_CHUNK = int(os.getenv("MODELS_PER_CHUNK", "8"))
CHUNK_SIZE       = int(os.getenv("CHUNK_SIZE",    "25000"))
MAX_QPM          = int(os.getenv("MAX_QPM",       "6"))
MIN_QPM          = int(os.getenv("MIN_QPM",       "2"))
DEEPSEEK_MODEL   = os.getenv("DEEPSEEK_MODEL",    "deepseek-chat")
YEAR             = int(os.getenv("YEAR",           "2026"))

QUESTION_MODELS = ['M1', 'M2', 'M3', 'M4', 'M5', 'M6', 'M7', 'M8']

# Mapeamento: filename stem → subjectTag em português
# (Capítulos de Mader's Reptile and Amphibian Medicine and Surgery)
CHAPTER_TAGS = {
    "Capitulo_11_1 Practice Management and Development":
        "Gestão da Prática Clínica em Répteis e Anfíbios",
    "Capitulo_12_2 Biology (Taxonomy, Anatomy, Physiology, and Behavior)":
        "Biologia dos Répteis e Anfíbios",
    "Capitulo_13_3 Husbandry and Management":
        "Manejo e Criação de Répteis e Anfíbios",
    "Capitulo_14_4 Infectious Diseases and Laboratory Sciences":
        "Doenças Infecciosas de Répteis e Anfíbios",
    "Capitulo_15_5 Techniques and Procedures":
        "Técnicas e Procedimentos em Répteis e Anfíbios",
    "Capitulo_16_6 Anesthesia":
        "Anestesiologia de Répteis e Anfíbios",
    "Capitulo_17_7 Diagnostic Imaging":
        "Diagnóstico por Imagem em Répteis e Anfíbios",
    "Capitulo_18_8 Endoscopy":
        "Endoscopia em Répteis e Anfíbios",
    "Capitulo_19_9 Medicine":
        "Medicina Clínica de Répteis e Anfíbios",
    "Capitulo_20_10 Surgery":
        "Cirurgia de Répteis e Anfíbios",
    "Capitulo_21_11 Therapy":
        "Terapêutica de Répteis e Anfíbios",
    "Capitulo_22_12 Differential Diagnoses by Clinical Signs":
        "Diagnósticos Diferenciais por Sinais Clínicos em Répteis",
    "Capitulo_23_13 Specific DiseaseCase Summary":
        "Doenças Específicas e Casos Clínicos de Répteis",
    "Capitulo_24_14 Population and Public Health":
        "Saúde Pública e Coletiva em Répteis e Anfíbios",
}

# Opções fixas do M5 — nunca geradas pela IA
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

# ── Resolução de hierarquia (IDs por slug, nunca hardcoda) ────────────────────
def load_hierarchy():
    sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
    from resolve_hierarchy import resolve_ids
    return resolve_ids(
        discipline_slug=DISCIPLINE_SLUG,
        subject_slug=SUBJECT_SLUG,
    )

# ── Regras por modelo ─────────────────────────────────────────────────────────
def model_rules(model: str) -> str:
    area = AREA_CONHECIMENTO
    rules = {
        'M1': (
            f'modelId: "M1", questionType: "multiple_choice", questionModel: "enade"\n'
            f'  - Enunciado rico e contextualizado (mínimo 3 linhas): mencione espécie, quadro clínico ou achado antes de perguntar.\n'
            f'  - 5 alternativas (A–E) com distratores baseados em condições semelhantes em {area}.\n'
            f'  - Exija raciocínio clínico — não apenas memorização de fatos.'
        ),
        'M2': (
            f'modelId: "M2", questionType: "multiple_choice", questionModel: "enade"\n'
            f'  - textPt é afirmação técnica incompleta terminando em ":" ou "...".\n'
            f'  - O contexto antes da lacuna deve descrever a espécie, quadro ou procedimento (2+ linhas).\n'
            f'  - Alternativas completam a frase com termos clínicos precisos e plausíveis.'
        ),
        'M3': (
            f'modelId: "M3", questionType: "complex_multiple_choice", questionModel: "enade"\n'
            f'  - textPt contém 4 assertivas (I–IV) técnicas e completas sobre {area}.\n'
            f'  - Alternativas são combinações: "Apenas I e III", "I, II e IV", etc.\n'
            f'  - Formato: "Sobre [tema]:\\n\\nI. [assertiva completa]\\nII. [assertiva]\\nIII. [assertiva]\\nIV. [assertiva]\\n\\nSão CORRETAS apenas:"'
        ),
        'M4': (
            f'modelId: "M4", questionType: "multiple_choice", questionModel: "enade"\n'
            f'  - textPt DEVE conter "EXCETO", "NÃO" ou "INCORRETO" em MAIÚSCULAS.\n'
            f'  - Enunciado descreve a espécie/procedimento/doença em detalhe antes da negativa.\n'
            f'  - 4 alternativas verdadeiras e 1 falsa (gabarito), todas clinicamente plausíveis.'
        ),
        'M5': (
            f'modelId: "M5", questionType: "assertion_reason", questionModel: "assertion_reason"\n'
            f'  - textPt: "ASSERÇÃO I: [proposição clínica ou fisiológica, 2+ linhas].\\n\\nPORQUE\\n\\nASSERÇÃO II: [mecanismo ou justificativa, 2+ linhas]."\n'
            f'  - Inclua "assertion1" e "assertion2" como campos separados.\n'
            f'  - correctOptionLetter: A=ambas V+II justifica I | B=ambas V sem relação causal | C=I V II F | D=I F II V | E=ambas F\n'
            f'  - NÃO inclua o campo "options" — injetado automaticamente.'
        ),
        'M6': (
            f'modelId: "M6", questionType: "cloze", questionModel: "enade"\n'
            f'  - textPt: trecho técnico com lacuna(s) [___] ou [1], [2]. Contexto: mínimo 3 linhas.\n'
            f'  - OBRIGATÓRIO: EXATAMENTE 5 alternativas (A, B, C, D, E) — nunca 3 ou 4.'
        ),
        'M7': (
            f'modelId: "M7", questionType: "clinical_case", questionModel: "enade"\n'
            f'  - textPt DEVE começar com "[CASO CLÍNICO]".\n'
            f'  - Inclua: espécie, sexo, idade estimada, anamnese, sinais clínicos, achados de exame físico.\n'
            f'  - Mínimo 5 dados clínicos relevantes antes da pergunta. Exija integração dos dados.'
        ),
        'M8': (
            f'modelId: "M8", questionType: "matching", questionModel: "enade"\n'
            f'  - Associe itens da Coluna I com características da Coluna II.\n'
            f'  - Formato: "Relacione...\\n\\nCOLUNA I\\n1. [item]\\n2. ...\\n\\nCOLUNA II\\na) [item]\\nb) ...\\n\\nA associação CORRETA é:"\n'
            f'  - 4 itens na Coluna I, 5 na Coluna II (1 distrator). Alternativas: "1-a / 2-c / 3-b / 4-d".\n'
            f'  - OBRIGATÓRIO: EXATAMENTE 5 alternativas (A, B, C, D, E) com mapeamentos diferentes.'
        ),
    }
    return rules.get(model, '')

# ── Pós-processamento ─────────────────────────────────────────────────────────
def post_process(q: dict, model_type: str, subject_tag: str,
                 discipline_id: int, subject_id: int) -> dict:
    out = dict(q)

    out['disciplineId'] = discipline_id
    out['subjectId']    = subject_id
    out['year']         = YEAR
    out['author']       = 'VetRank'
    out['isPremium']    = True
    out['status']       = 'approved'
    out['modelId']      = model_type
    out['subjectTag']   = subject_tag

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

    if 'options' not in out and 'alternatives' in out:
        letters = ['A', 'B', 'C', 'D', 'E']
        out['options'] = [
            {'id': letters[i], 'textPt': str(t).strip()}
            for i, t in enumerate(out['alternatives'])
        ]
    out.pop('alternatives', None)

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
    out['difficulty'] = raw_diff if raw_diff in VALID_DIFF else diff_map.get(raw_diff, 'hard')

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

# ── Extração de texto ─────────────────────────────────────────────────────────
def extract_text(pdf_path: str) -> str:
    reader = PdfReader(pdf_path)
    full_text = ""
    total = len(reader.pages)
    for i, page in enumerate(reader.pages, 1):
        full_text += (page.extract_text() or "") + " "
        if i % 30 == 0:
            print(f"   📖 {i}/{total} páginas")
    text = full_text.strip()
    print(f"   ✅ {len(text):,} chars | {total} páginas")
    return text

# ── Prompt de instrução ───────────────────────────────────────────────────────
def build_user_prompt(model_type: str, num_q: int, subject_tag: str) -> str:
    is_m5 = model_type == 'M5'
    area  = AREA_CONHECIMENTO

    example = (
        '{\n'
        '  "textPt": "ASSERÇÃO I: [proposição clínica ou fisiológica, 2+ linhas].\\n\\nPORQUE\\n\\nASSERÇÃO II: [mecanismo ou justificativa, 2+ linhas].",\n'
        '  "assertion1": "texto completo da Asserção I",\n'
        '  "assertion2": "texto completo da Asserção II",\n'
        '  "correctOptionLetter": "A",\n'
        '  "explanationPt": "explicação detalhada — mínimo 6 linhas",\n'
        '  "difficulty": "hard"\n'
        '}'
    ) if is_m5 else (
        '{\n'
        '  "textPt": "Enunciado contextualizado com 3+ linhas (espécie, quadro clínico, situação)",\n'
        '  "options": [\n'
        '    {"id": "A", "textPt": "alternativa A"},\n'
        '    {"id": "B", "textPt": "alternativa B"},\n'
        '    {"id": "C", "textPt": "alternativa C"},\n'
        '    {"id": "D", "textPt": "alternativa D"},\n'
        '    {"id": "E", "textPt": "alternativa E"}\n'
        '  ],\n'
        '  "correctOptionLetter": "B",\n'
        '  "explanationPt": "explicação com fundamentação clínica e diferenciais — mínimo 6 linhas",\n'
        '  "difficulty": "hard"\n'
        '}'
    )

    return (
        f"Use o TEXTO FONTE acima para gerar exatamente {num_q} questão(ões) de {area} "
        f"(tópico: {subject_tag}), modelo {model_type}.\n\n"
        f"PADRÃO DE QUALIDADE EXIGIDO:\n"
        f"- Nível Residência Veterinária / CFMV — dificuldade preferencialmente 'hard'.\n"
        f"- Enunciado RICO E CONTEXTUALIZADO: cite a espécie (ex: Iguana iguana, Python regius, "
        f"Geochelone carbonaria, Caiman latirostris), quadro clínico, procedimento ou achado antes de perguntar.\n"
        f"- NUNCA faça perguntas simples de definição que um estudante de 1º ano responderia.\n"
        f"- NUNCA mencione 'segundo o texto', 'de acordo com o texto' ou o PDF.\n"
        f"- Distratores plausíveis: espécies erradas, doses incorretas, diagnósticos semelhantes.\n"
        f"- explanationPt EXTENSA (mínimo 6 linhas): mecanismo/fisiopatologia, particularidades "
        f"anatômicas/fisiológicas de répteis/anfíbios, espécies acometidas, diagnósticos diferenciais.\n\n"
        f"MODELO {model_type}:\n{model_rules(model_type)}\n\n"
        f"OBRIGATÓRIO: Sempre gere EXATAMENTE 5 alternativas (A, B, C, D, E) — nunca 3 ou 4.\n\n"
        f"Retorne APENAS um array JSON válido com {num_q} objeto(s). Sem markdown.\n"
        f"Exemplo:\n{example}"
    )

# ── Gerar lote (system message = chunk cacheado) ──────────────────────────────
def generate_batch(chunk_idx: int, model_type: str, system_msg: str,
                   num_q: int, subject_tag: str,
                   discipline_id: int, subject_id: int) -> list:
    try:
        response = client.chat.completions.create(
            model=DEEPSEEK_MODEL,
            messages=[
                {"role": "system", "content": system_msg},
                {"role": "user",   "content": build_user_prompt(model_type, num_q, subject_tag)},
            ],
            temperature=0.75,
        )

        usage = response.usage
        hit  = getattr(usage, 'prompt_cache_hit_tokens',  0)
        miss = getattr(usage, 'prompt_cache_miss_tokens', 0)
        icon = "💾" if hit > 0 else "🔄"
        print(f"   {icon} In={usage.prompt_tokens}(hit={hit} miss={miss}) | Out={usage.completion_tokens}")

        raw_text = (response.choices[0].message.content or "[]").strip()
        raw_text = re.sub(r'^```(?:json)?\s*', '', raw_text, flags=re.IGNORECASE)
        raw_text = re.sub(r'\s*```$', '', raw_text).strip()

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

        return [post_process(q, model_type, subject_tag, discipline_id, subject_id) for q in raw]

    except Exception as e:
        print(f"   ❌ Erro chunk {chunk_idx} modelo {model_type}: {e}")
        return []

# ── Calcular questões por modelo ──────────────────────────────────────────────
def calc_qpm(num_chunks: int) -> int:
    if num_chunks == 0:
        return MIN_QPM
    raw = math.ceil(TARGET_QUESTIONS / (num_chunks * MODELS_PER_CHUNK))
    return max(MIN_QPM, min(MAX_QPM, raw))

# ── Processar um PDF ──────────────────────────────────────────────────────────
def process_pdf(filename: str, subject_tag: str,
                discipline_id: int, subject_id: int) -> int:
    pdf_path    = os.path.join(PDF_DIR, filename)
    stem        = filename.replace('.pdf', '')
    safe_stem   = re.sub(r'[^\w\-]', '_', stem)
    output_path = os.path.join(OUTPUT_DIR, f"questoes_{safe_stem}.json")

    print(f"\n{'=' * 65}")
    print(f"📚 {filename}")
    print(f"   Tag: {subject_tag}")
    print(f"   disciplineId={discipline_id} | subjectId={subject_id}")
    print(f"   Saída: {output_path}")

    if not os.path.exists(pdf_path):
        print(f"   ⚠️  PDF não encontrado — pulando.")
        return 0

    print(f"\n📄 Extraindo texto...")
    pdf_text = extract_text(pdf_path)

    chunks = [pdf_text[i:i + CHUNK_SIZE] for i in range(0, len(pdf_text), CHUNK_SIZE)]
    num_chunks = len(chunks)
    qpm = calc_qpm(num_chunks)
    models_ativo = QUESTION_MODELS[:MODELS_PER_CHUNK]
    estimativa = num_chunks * qpm * len(models_ativo)

    print(f"\n🔢 {num_chunks} chunks × {qpm} q × {len(models_ativo)} modelos ≈ {estimativa} questões (meta: {TARGET_QUESTIONS})")

    all_questions = []
    if os.path.exists(output_path):
        try:
            with open(output_path, 'r', encoding='utf-8') as f:
                existing = json.load(f)
            if isinstance(existing, list) and existing:
                all_questions = existing
                print(f"   ♻️  Retomando: {len(all_questions)} questões já existentes")
        except Exception:
            pass

    for i, chunk in enumerate(chunks):
        system_msg = (
            f"Você é especialista em elaboração de questões de {AREA_CONHECIMENTO} "
            f"para concursos de Residência Veterinária e CFMV. "
            f"Tópico: {subject_tag}. "
            f"Responda APENAS com um array JSON válido, sem markdown, sem texto extra.\n\n"
            f"TEXTO FONTE:\n\"\"\"\n{chunk}\n\"\"\""
        )

        print(f"\n{'─' * 65}")
        print(f"📦 Chunk {i + 1}/{num_chunks} ({len(chunk):,} chars)")

        chunk_total = 0
        for j, model_type in enumerate(models_ativo):
            label = "(preenchendo cache)" if j == 0 else "(cache hit esperado)"
            print(f"   🎯 {model_type} {label}")

            questions = generate_batch(i + 1, model_type, system_msg, qpm,
                                       subject_tag, discipline_id, subject_id)

            if questions:
                all_questions.extend(questions)
                chunk_total += len(questions)
                with open(output_path, 'w', encoding='utf-8') as f:
                    json.dump(all_questions, f, ensure_ascii=False, indent=2)
            else:
                print(f"   ⚠️  Sem questões neste lote")

            time.sleep(0.8)

        print(f"   ✅ +{chunk_total} neste chunk | Total: {len(all_questions)}")

        if i < num_chunks - 1:
            time.sleep(2)

    print(f"\n🎉 {filename}: {len(all_questions)} questões → {output_path}")
    return len(all_questions)

# ── Main ──────────────────────────────────────────────────────────────────────
def main():
    os.makedirs(OUTPUT_DIR, exist_ok=True)

    print(f"\n{'=' * 65}")
    print(f"🦎 VetRank — Extrator de Questões por Capítulos de PDF")
    print(f"{'=' * 65}")
    print(f"   Área: {AREA_CONHECIMENTO}")
    print(f"   Resolvendo hierarquia: {DISCIPLINE_SLUG} → {SUBJECT_SLUG}")

    ids = load_hierarchy()
    discipline_id = ids['disciplineId']
    subject_id    = ids['subjectId']

    print(f"   ✅ disciplineId={discipline_id} | subjectId={subject_id}")
    print(f"   Modelo DeepSeek: {DEEPSEEK_MODEL}")
    print(f"   Meta: ~{TARGET_QUESTIONS} questões por PDF")
    print(f"   Chunk: {CHUNK_SIZE:,} chars | QPM: {MIN_QPM}–{MAX_QPM} | Modelos: {MODELS_PER_CHUNK}")

    if not os.path.isdir(PDF_DIR):
        print(f"\n❌ Pasta não encontrada: {PDF_DIR}")
        return

    filter_arg = sys.argv[1].lower() if len(sys.argv) > 1 else None

    pdfs_to_run = []
    for filename in sorted(os.listdir(PDF_DIR)):
        if not filename.lower().endswith('.pdf'):
            continue
        stem = filename.replace('.pdf', '')
        tag = CHAPTER_TAGS.get(stem)
        if tag is None:
            # Fallback: usa o stem do arquivo como tag
            tag = stem.replace('_', ' ')
        if filter_arg and filter_arg not in filename.lower() and filter_arg not in tag.lower():
            continue
        pdfs_to_run.append({'file': filename, 'tag': tag})

    if not pdfs_to_run:
        print(f"\nNenhum PDF encontrado para o filtro '{filter_arg}'.")
        print(f"PDFs disponíveis em {PDF_DIR}:")
        for f in sorted(os.listdir(PDF_DIR)):
            if f.lower().endswith('.pdf'):
                print(f"   • {f}")
        return

    print(f"\n   PDFs a processar: {len(pdfs_to_run)}")
    for p in pdfs_to_run:
        print(f"      • {p['file']}")
        print(f"        → {p['tag']}")

    grand_total = 0
    results = []

    for idx, pdf_info in enumerate(pdfs_to_run):
        count = process_pdf(pdf_info['file'], pdf_info['tag'],
                            discipline_id, subject_id)
        grand_total += count
        results.append({"file": pdf_info['file'], "questoes": count})

        if idx < len(pdfs_to_run) - 1:
            print(f"\n⏸  5s antes do próximo PDF...")
            time.sleep(5)

    print(f"\n{'=' * 65}")
    print(f"📊 RESUMO FINAL")
    print(f"{'=' * 65}")
    for r in results:
        ok = r['questoes'] >= TARGET_QUESTIONS * 0.85
        print(f"   {'✅' if ok else '⚠️ '} {r['file']:<55} {r['questoes']:>5} questões")
    print(f"{'─' * 65}")
    print(f"   TOTAL: {grand_total} questões em {len(results)} PDF(s)")
    print(f"\nValidar cada arquivo:")
    print(f"   node scripts/normalize_questions_json.mjs <input.json> <output_val.json> \"<tag>\"")
    print(f"{'=' * 65}")

if __name__ == "__main__":
    main()
