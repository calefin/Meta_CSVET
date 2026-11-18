# VetRank — Gerador de Questões: Patologia Forense
# Processa PDFs de D:\pdfs\Patologia forense e gera JSON prontos para importação.
# Env vars (.env):
#   DEEPSEEK_API_KEY=sk-...
#   FORENSE_DIR=D:/pdfs/Patologia forense
#   OUTPUT_DIR=D:/vetrank/Livro/questoes
#   FORENSE_DISCIPLINE_ID=30006
#   FORENSE_SUBJECT_ID=30042
#   FORENSE_TOPIC_ID=0
#   FORENSE_TARGET_QUESTIONS=250
#   FORENSE_MODELS_PER_CHUNK=8
#   FORENSE_CHUNK_SIZE=25000
#   FORENSE_MAX_QPM=6
#   FORENSE_MIN_QPM=2
#   FORENSE_DEEPSEEK_MODEL=deepseek-chat
#   FORENSE_YEAR=2026
#
# Uso:
#   python scripts/extrator_patologia_forense.py              → todos os PDFs
#   python scripts/extrator_patologia_forense.py <filtro>     → filtra pelo nome do arquivo ou capítulo

import os, sys, json, time, re, math, concurrent.futures, unicodedata
import openai
import fitz  # PyMuPDF
from tenacity import retry, stop_after_attempt, wait_exponential
from dotenv import load_dotenv

load_dotenv()

# ── Configuração ──────────────────────────────────────────────────────────────
FORENSE_DIR      = os.getenv("FORENSE_DIR",      "D:/pdfs/Patologia forense")
OUTPUT_DIR       = os.getenv("OUTPUT_DIR",       "D:/vetrank/Livro/questoes")
DISCIPLINE_ID    = int(os.getenv("FORENSE_DISCIPLINE_ID", "30006"))
SUBJECT_ID       = int(os.getenv("FORENSE_SUBJECT_ID",   "30042"))
TOPIC_ID         = int(os.getenv("FORENSE_TOPIC_ID",     "0"))
TARGET_QUESTIONS = int(os.getenv("FORENSE_TARGET_QUESTIONS", "250"))
MODELS_PER_CHUNK = int(os.getenv("FORENSE_MODELS_PER_CHUNK", "8"))
CHUNK_SIZE       = int(os.getenv("FORENSE_CHUNK_SIZE",    "25000"))
MAX_QPM          = int(os.getenv("FORENSE_MAX_QPM",       "6"))
MIN_QPM          = int(os.getenv("FORENSE_MIN_QPM",       "2"))
DEEPSEEK_MODEL   = os.getenv("FORENSE_DEEPSEEK_MODEL",    "deepseek-chat")
YEAR             = int(os.getenv("FORENSE_YEAR",           "2026"))

QUESTION_MODELS = ['M1', 'M2', 'M3', 'M4', 'M5', 'M6', 'M7', 'M8']

if DISCIPLINE_ID == 0 or SUBJECT_ID == 0:
    sys.exit("ERRO: defina FORENSE_DISCIPLINE_ID e FORENSE_SUBJECT_ID no .env.")

# Mapeamento: stem do PDF → subjectTag (baseado em "Veterinary Forensics: Animal Cruelty Investigations")
CHAPTER_TAGS = {
    "Capitulo_03_1 Drowning and Bodies Recovered from Water": "Afogamento",
    "Capitulo_03_1 Medicolegal Investigation": "Investigação Médico-Legal",
    "Capitulo_04_2 Crime Scene Findings and the Identification, Collection, and Preservation of Evidence": "Achados de Cena de Crime e Evidências",
    "Capitulo_04_2 ThermalElectrical Injuries": "Queimaduras e Lesões Elétricas",
    "Capitulo_05_3 Neglect": "Negligência e Inanição",
    "Capitulo_05_3 Performing the Forensic Necropsy": "A Necropsia Forense",
    "Capitulo_06_4 Environmental Injuries": "Lesões Ambientais",
    "Capitulo_06_4 Postmortem Changes and Estimating the Postmortem Interval": "Alterações Pós-morte e Intervalo Pós-morte",
    "Capitulo_07_5 Blunt Force Trauma": "Trauma Contuso",
    "Capitulo_07_5 Poisoning": "Envenenamento",
    "Capitulo_08_6 Anesthesia-Related Deaths": "Mortes Relacionadas à Anestesia",
    "Capitulo_08_6 Sharp Force Trauma": "Trauma por Arma Branca e Perfurocortante",
    "Capitulo_09_7 Animal Fighting": "Rinhas de Animais",
    "Capitulo_09_7 Firearm Injuries": "Ferimentos por Arma de Fogo",
    "Capitulo_10_8 Animal Sexual Abuse Investigations": "Abuso Sexual de Animais",
    "Capitulo_10_8 Strangulation, Suffocation, and Asphyxia": "Estrangulamento, Sufocação e Asfixia",
    "Capitulo_11_9 Ritualistic Animal Killing": "Mortes Ritualísticas de Animais",
    "Capitulo_12_10 Writing the Necropsy Report": "Relatório de Necropsia",
    "Capitulo_13_11 Age Determination in Dogs and Cats": "Determinação da Idade em Cães e Gatos",
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

# ── Regras por modelo ─────────────────────────────────────────────────────────
def model_rules(model: str) -> str:
    rules = {
        'M1': (
            'modelId: "M1", questionType: "multiple_choice"\n'
            '  - Enunciado rico (mínimo 3 linhas): espécie, quadro clínico ou achado antes de perguntar.\n'
            '  - 5 alternativas (A–E). Distratores baseados em espécies ou condições semelhantes.\n'
            '  - Exija raciocínio clínico — nunca memorização simples.'
        ),
        'M2': (
            'modelId: "M2", questionType: "multiple_choice"\n'
            '  - textPt é afirmação técnica incompleta terminando em ":" ou "...".\n'
            '  - Contexto (2+ linhas) antes da lacuna descrevendo a espécie, quadro ou procedimento.\n'
            '  - Alternativas completam a frase com termos clínicos precisos.'
        ),
        'M3': (
            'modelId: "M3", questionType: "complex_multiple_choice"\n'
            '  - textPt contém 4 assertivas (I–IV) técnicas e completas.\n'
            '  - Alternativas são combinações: "Apenas I e III", "I, II e IV", etc.\n'
            '  - Formato: "Sobre [tema]:\\n\\nI. [assertiva]\\nII. [assertiva]\\nIII. [assertiva]\\nIV. [assertiva]\\n\\nSão CORRETAS apenas:"'
        ),
        'M4': (
            'modelId: "M4", questionType: "multiple_choice"\n'
            '  - textPt DEVE conter "EXCETO", "NÃO" ou "INCORRETO" em MAIÚSCULAS.\n'
            '  - Descreva a espécie/procedimento/doença em detalhe antes da negativa.\n'
            '  - 4 alternativas verdadeiras e 1 falsa (gabarito).'
        ),
        'M5': (
            'modelId: "M5", questionType: "assertion_reason"\n'
            '  - textPt: "ASSERÇÃO I: [proposição clínica/fisiológica, 2+ linhas].\\n\\nPORQUE\\n\\nASSERÇÃO II: [mecanismo/justificativa, 2+ linhas]."\n'
            '  - Inclua "assertion1" e "assertion2" como campos separados no JSON.\n'
            '  - correctOptionLetter: A=ambas V+II justifica I | B=ambas V sem relação | C=I V II F | D=I F II V | E=ambas F\n'
            '  - NÃO inclua "options" no JSON — injetado automaticamente.'
        ),
        'M6': (
            'modelId: "M6", questionType: "cloze"\n'
            '  - textPt: trecho técnico com lacuna(s) marcadas [___] ou [1], [2].\n'
            '  - Contexto antes da lacuna: mínimo 3 linhas.\n'
            '  - OBRIGATÓRIO: EXATAMENTE 5 alternativas (A, B, C, D, E).'
        ),
        'M7': (
            'modelId: "M7", questionType: "clinical_case"\n'
            '  - textPt DEVE começar com "[CASO CLÍNICO]".\n'
            '  - Inclua: espécie (nome científico quando possível), sexo, idade, anamnese, sinais clínicos, achados do exame físico.\n'
            '  - Mínimo 5 dados clínicos antes da pergunta. Exija integração — não memorização.'
        ),
        'M8': (
            'modelId: "M8", questionType: "matching"\n'
            '  - Associe itens da Coluna I com características da Coluna II.\n'
            '  - Formato: "Relacione...\\n\\nCOLUNA I\\n1. [item]\\n2. ...\\n\\nCOLUNA II\\na) [item]\\nb) ...\\n\\nA associação CORRETA é:"\n'
            '  - 4 itens na Coluna I, 5 na Coluna II (1 distrator). Alternativas: "1-a / 2-c / 3-b / 4-d".\n'
            '  - OBRIGATÓRIO: EXATAMENTE 5 alternativas (A, B, C, D, E).'
        ),
    }
    return rules.get(model, '')

# ── Pós-processamento — garante formato correto para importação ───────────────
def post_process(q: dict, model_type: str, subject_tag: str,
                 discipline_id: int, subject_id: int) -> dict:
    out = dict(q)

    # Campos de identificação / metadados
    out['disciplineId'] = discipline_id
    out['subjectId']    = subject_id
    out['topicId']      = TOPIC_ID
    out['subjectTag']   = subject_tag
    out['year']         = YEAR
    out['author']       = 'VetRank'
    out['isPremium']    = True
    out['status']       = 'approved'
    out['modelId']      = model_type

    # Tipo da questão
    if model_type == 'M5':
        out['questionType'] = 'assertion_reason'
        out['options']      = [dict(o) for o in M5_OPTIONS]
    elif model_type == 'M3':
        out['questionType'] = 'complex_multiple_choice'
    elif model_type == 'M6':
        out['questionType'] = 'cloze'
    elif model_type == 'M7':
        out['questionType'] = 'clinical_case'
    elif model_type == 'M8':
        out['questionType'] = 'matching'
    else:
        out.setdefault('questionType', 'multiple_choice')

    # Normalizar alternatives → options
    if 'options' not in out and 'alternatives' in out:
        letters = ['A', 'B', 'C', 'D', 'E']
        out['options'] = [
            {'id': letters[i], 'textPt': str(t).strip()}
            for i, t in enumerate(out['alternatives'])
        ]
    out.pop('alternatives', None)

    # Normalizar objetos de options
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

    # Normalizar difficulty
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

    # Normalizar correctOptionLetter
    def _safe_letter(value) -> str | None:
        s = str(value).upper().strip() if value is not None else ''
        return s[0] if s and s[0] in 'ABCDE' else None

    if 'correctOptionLetter' not in out or out.get('correctOptionLetter') is None:
        for alias in ('correctAlternative', 'correctOption', 'gabarito'):
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

    # Remover campos desnecessários / internos
    for campo in ('id', 'correctAlternative', 'answer', 'correct', 'questionModel'):
        out.pop(campo, None)

    return out

# ── Extração de texto do PDF (MELHORIA: Usando PyMuPDF) ───────────────────────
def extract_text(pdf_path: str) -> str:
    full_text = ""
    try:
        with fitz.open(pdf_path) as doc:
            total = len(doc)
            for i, page in enumerate(doc, 1):
                full_text += page.get_text() + " "
                if i % 30 == 0:
                    print(f"   📖 {i}/{total} páginas")
    except Exception as e:
        print(f"   ❌ Erro ao ler PDF com PyMuPDF: {e}. Tente instalar com 'pip install PyMuPDF'.")
        return ""
    text = full_text.strip()
    print(f"   ✅ {len(text):,} chars | {total} páginas")
    return text

# ── Prompt de instrução ───────────────────────────────────────────────────────
def build_user_prompt(model_type: str, num_q: int, subject_tag: str) -> str:
    is_m5 = model_type == 'M5'

    example = (
        '{\n'
        '  "textPt": "ASSERÇÃO I: [proposição com 2+ linhas].\\n\\nPORQUE\\n\\nASSERÇÃO II: [mecanismo/justificativa com 2+ linhas].",\n'
        '  "assertion1": "texto completo da Asserção I sem o prefixo ASSERÇÃO I:",\n'
        '  "assertion2": "texto completo da Asserção II sem o prefixo ASSERÇÃO II:",\n'
        '  "correctOptionLetter": "A",\n'
        '  "explanationPt": "explicação detalhada — mínimo 6 linhas",\n'
        '  "difficulty": "hard"\n'
        '}'
    ) if is_m5 else (
        '{\n'
        '  "textPt": "Enunciado contextualizado com 3+ linhas",\n'
        '  "options": [\n'
        '    {"id": "A", "textPt": "alternativa A"},\n'
        '    {"id": "B", "textPt": "alternativa B"},\n'
        '    {"id": "C", "textPt": "alternativa C"},\n'
        '    {"id": "D", "textPt": "alternativa D"},\n'
        '    {"id": "E", "textPt": "alternativa E"}\n'
        '  ],\n'
        '  "correctOptionLetter": "B",\n'
        '  "explanationPt": "explicação com fundamentação e diferenciais — mínimo 6 linhas",\n'
        '  "difficulty": "hard"\n'
        '}'
    )

    return (
        f"Use o TEXTO FONTE acima para gerar exatamente {num_q} questão(ões) de "
        f"PATOLOGIA FORENSE — área: {subject_tag}.\n\n"
        f"REGRAS OBRIGATÓRIAS:\n"
        f"- Nível Residência Veterinária / CFMV. Dificuldade preferencialmente 'hard'.\n"
        f"- Contextualize: mencione a espécie (ex: Canis lupus familiaris, Felis catus, Equus caballus), quadro clínico ou procedimento.\n"
        f"- PROIBIDO: 'segundo o texto', 'de acordo com o texto', 'no capítulo', 'conforme descrito', "
        f"ou qualquer referência à fonte, ao livro ou ao capítulo.\n"
        f"- PROIBIDO: perguntas de definição simples que um estudante de 1º ano responderia.\n"
        f"- Distratores plausíveis: achados de necropsia semelhantes, causas de morte alternativas, interpretações legais incorretas.\n"
        f"- explanationPt EXTENSA (mínimo 6 linhas): fisiopatologia da lesão, achados de necropsia, implicações legais, cadeia de custódia e diagnósticos diferenciais.\n\n"
        f"MODELO {model_type}:\n{model_rules(model_type)}\n\n"
        f"OBRIGATÓRIO: EXATAMENTE 5 alternativas (A, B, C, D, E). Nunca 3 ou 4.\n\n"
        f"Retorne APENAS um array JSON válido com {num_q} objeto(s). Sem markdown, sem texto extra.\n"
        f"Exemplo de um objeto:\n{example}"
    )

# ── Gerar lote via API ────────────────────────────────────────────────────────
@retry(wait=wait_exponential(multiplier=1, min=2, max=10), stop=stop_after_attempt(3))
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
            timeout=120.0,
        )

        usage = response.usage
        hit  = getattr(usage, 'prompt_cache_hit_tokens',  0)
        miss = getattr(usage, 'prompt_tokens', 0) - hit
        print(f"   {'💾' if hit > 0 else '🔄'} In={usage.prompt_tokens}(hit={hit} miss={miss}) Out={usage.completion_tokens}")

        raw = (response.choices[0].message.content or "[]").strip()
        raw = re.sub(r'^```(?:json)?\s*', '', raw, flags=re.IGNORECASE)
        raw = re.sub(r'\s*```$', '', raw).strip()

        start = raw.find('[')
        if start == -1:
            start = raw.find('{')
            if start != -1:
                raw = '[' + raw[start:] + ']'
        else:
            depth, in_str, esc = 0, False, False
            end = start
            for i, ch in enumerate(raw[start:], start):
                if esc:           esc = False; continue
                if ch == '\\' and in_str: esc = True; continue
                if ch == '"' and not esc: in_str = not in_str; continue
                if in_str:        continue
                if ch == '[':     depth += 1
                elif ch == ']':
                    depth -= 1
                    if depth == 0: end = i; break
            raw = raw[start:end + 1]

        parsed = json.loads(raw)
        if not isinstance(parsed, list):
            parsed = [parsed]

        return [post_process(q, model_type, subject_tag, discipline_id, subject_id)
                for q in parsed]

    except Exception as e:
        print(f"   ❌ Erro ao gerar lote para o modelo {model_type} (chunk {chunk_idx}): {e}")
        raise e

# ── Calcular questões por modelo ──────────────────────────────────────────────
def calc_qpm(num_chunks: int) -> int:
    if num_chunks == 0:
        return MIN_QPM
    raw = math.ceil(TARGET_QUESTIONS / (num_chunks * MODELS_PER_CHUNK))
    return max(MIN_QPM, min(MAX_QPM, raw))

# ── Processar um PDF (MELHORIA: com processamento paralelo) ───────────────────
def process_pdf(filename: str, subject_tag: str,
                discipline_id: int, subject_id: int) -> int:
    pdf_path = os.path.join(FORENSE_DIR, filename)

    # Gera um nome de arquivo seguro a partir do nome original do PDF para garantir unicidade.
    # Isso evita sobrescrever arquivos JSON se múltiplos PDFs mapearem para a mesma subject_tag.
    original_stem = os.path.splitext(filename)[0]
    safe_original_stem = unicodedata.normalize('NFKD', original_stem).encode('ascii', 'ignore').decode('ascii')
    safe_original_stem = re.sub(r'[^\w\s-]', '', safe_original_stem).strip().lower()
    safe_original_stem = re.sub(r'[-\s]+', '_', safe_original_stem)

    # O nome do arquivo de saída agora usa o stem original do PDF para ser único
    output_filename_base = f"questoes_patologia_forense_{safe_original_stem}"
    output_path = os.path.join(OUTPUT_DIR, f"{output_filename_base}.json")

    print(f"\n{'=' * 65}")
    print(f"📚 Processando capítulo: {subject_tag}")
    print(f"   disciplineId={discipline_id} | subjectId={subject_id}")
    print(f"   Arquivo de saída: {os.path.basename(output_path)}")

    if not os.path.exists(pdf_path):
        print(f"   ⚠️  PDF não encontrado — pulando.")
        return 0

    print(f"\n📄 Extraindo texto...")
    pdf_text = extract_text(pdf_path)
    if not pdf_text:
        print(f"   ❌ Não foi possível extrair texto de {filename}. Pulando.")
        return 0

    chunks = [pdf_text[i:i + CHUNK_SIZE] for i in range(0, len(pdf_text), CHUNK_SIZE)]
    num_chunks = len(chunks)
    qpm = calc_qpm(num_chunks)
    models_ativo = QUESTION_MODELS[:MODELS_PER_CHUNK]
    estimativa = num_chunks * qpm * len(models_ativo)

    print(f"\n🔢 {num_chunks} chunks × {qpm} q × {len(models_ativo)} modelos ≈ {estimativa} questões (meta: {TARGET_QUESTIONS})")
    if estimativa < TARGET_QUESTIONS * 0.9:
        print(f"   ⚠️  Atenção: A estimativa está significativamente abaixo da meta. Considere ajustar CHUNK_SIZE ou MAX_QPM.")

    # Retomada: se arquivo já existe, continua de onde parou
    all_questions = []
    if os.path.exists(output_path):
        try:
            with open(output_path, 'r', encoding='utf-8') as f:
                existing = json.load(f)
            if isinstance(existing, list) and existing:
                all_questions = existing
                print(f"   ♻️  Retomando: {len(all_questions)} questões existentes")
        except Exception:
            pass

    for i, chunk in enumerate(chunks):
        system_msg = (
            f"Você é especialista em elaboração de questões de PATOLOGIA FORENSE "
            f"para concursos de Residência Veterinária e CFMV. "
            f"Tópico atual: {subject_tag}. "
            f"Responda APENAS com um array JSON válido, sem markdown, sem texto extra.\n\n"
            f"TEXTO FONTE:\n\"\"\"\n{chunk}\n\"\"\""
        )

        print(f"\n{'─' * 65}")
        print(f"📦 Chunk {i + 1}/{num_chunks} ({len(chunk):,} chars)")

        chunk_total = 0
        with concurrent.futures.ThreadPoolExecutor(max_workers=len(models_ativo)) as executor:
            future_to_model = {
                executor.submit(
                    generate_batch, i + 1, model_type, system_msg, qpm,
                    subject_tag, discipline_id, subject_id
                ): model_type
                for model_type in models_ativo
            }

            for future in concurrent.futures.as_completed(future_to_model):
                model_type = future_to_model[future]
                try:
                    questions = future.result()
                    if questions:
                        all_questions.extend(questions)
                        chunk_total += len(questions)
                        print(f"   ✅ Modelo {model_type} retornou {len(questions)} questões.")
                except Exception as exc:
                    print(f"   ❌ Modelo {model_type} gerou uma exceção após retentativas: {exc}")

        if chunk_total > 0:
            with open(output_path, 'w', encoding='utf-8') as f:
                json.dump(all_questions, f, ensure_ascii=False, indent=2)
            print(f"   💾 +{chunk_total} neste chunk | Total: {len(all_questions)}")
        else:
            print(f"   ⚠️  Nenhuma questão gerada neste chunk.")

        if i < num_chunks - 1:
            time.sleep(2)

    print(f"\n🎉 {filename}: {len(all_questions)} questões → {output_path}")
    return len(all_questions)

# ── Main ──────────────────────────────────────────────────────────────────────
def main():
    os.makedirs(OUTPUT_DIR, exist_ok=True)

    print(f"\n{'=' * 65}")
    print(f"🔎 VetRank — Patologia Forense")
    print(f"{'=' * 65}")
    discipline_id = DISCIPLINE_ID
    subject_id    = SUBJECT_ID

    print(f"   disciplineId={discipline_id} | subjectId={subject_id}")
    print(f"   Modelo DeepSeek: {DEEPSEEK_MODEL}")
    print(f"   Meta: ~{TARGET_QUESTIONS} questões por PDF | Modelos: {MODELS_PER_CHUNK}")

    if not os.path.isdir(FORENSE_DIR):
        print(f"\n❌ Pasta não encontrada: {FORENSE_DIR}")
        return

    filter_arg = sys.argv[1].lower() if len(sys.argv) > 1 else None

    def normalize_key(s: str) -> str:
        s = unicodedata.normalize('NFKD', s).encode('ascii', 'ignore').decode('ascii')
        return re.sub(r'[^a-z0-9]', '', s.lower())

    normalized_tags = {normalize_key(k): v for k, v in CHAPTER_TAGS.items()}

    pdfs_to_run = []
    for filename in sorted(os.listdir(FORENSE_DIR)):
        if not filename.lower().endswith('.pdf'):
            continue
        stem = filename[:-4]

        # Tenta encontrar uma correspondência normalizada para o stem do arquivo no dicionário CHAPTER_TAGS.
        n_stem = normalize_key(stem)
        tag = normalized_tags.get(n_stem)

        if not tag:
            # Fallback: remove o prefixo "Capitulo XX Y " e usa o restante do nome
            clean_stem = re.sub(r'^cap[ií]tulo[_\s-]*\d+[_\s-]*\d*[_\s-]*', '', stem, flags=re.IGNORECASE)
            tag = re.sub(r'[_\s]+', ' ', clean_stem).strip()

        if filter_arg and filter_arg not in filename.lower() and filter_arg not in tag.lower():
            continue
        pdfs_to_run.append({'file': filename, 'tag': tag})

    if not pdfs_to_run:
        print(f"\nNenhum PDF para o filtro '{filter_arg}'.")
        return

    print(f"\n   PDFs a processar: {len(pdfs_to_run)}")
    for p in pdfs_to_run:
        print(f"      • {p['file']} (Capítulo: {p['tag']})")

    grand_total = 0
    results = []

    for idx, pdf_info in enumerate(pdfs_to_run):
        count = process_pdf(
            pdf_info['file'], pdf_info['tag'],
            discipline_id, subject_id
        )
        grand_total += count
        results.append({"file": pdf_info['file'], "tag": pdf_info['tag'], "questoes": count})

        if idx < len(pdfs_to_run) - 1:
            print(f"\n⏸  5s antes do próximo PDF...")
            time.sleep(5)

    print(f"\n{'=' * 65}")
    print(f"📊 RESUMO FINAL — Patologia Forense")
    print(f"{'=' * 65}")
    for r in results:
        ok = r['questoes'] >= TARGET_QUESTIONS * 0.85
        print(f"   {'✅' if ok else '⚠️ '} {r['tag']:<45} {r['questoes']:>5} q")
    print(f"{'─' * 65}")
    print(f"   TOTAL: {grand_total} questões em {len(results)} PDF(s)")
    print(f"\nValidar antes de importar:")
    print(f"   node scripts/normalize_questions_json.mjs <input.json> <output.json> \"<tag>\"")
    print(f"{'=' * 65}")

if __name__ == "__main__":
    main()
