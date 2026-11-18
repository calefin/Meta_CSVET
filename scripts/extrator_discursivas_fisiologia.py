# VetRank — Gerador de Questões Discursivas: Fisiologia Veterinária (Dukes)
# Processa capítulos de D:\pdfs\Fisiologia\Capitulos e gera um JSON único com questões discursivas.
#
# Env vars (.env):
#   DEEPSEEK_API_KEY=sk-...
#   FISIOLOGIA_DIR=D:/pdfs/Fisiologia/Capitulos
#   OUTPUT_FILE=D:/vetrank/Livro/questoes/questoes_discursivas_fisiologia.json
#   FISIOLOGIA_DISCIPLINE_ID=30001
#   FISIOLOGIA_SUBJECT_ID=30007
#   FISIOLOGIA_QUESTIONS_PER_CHAPTER=3
#   FISIOLOGIA_DEEPSEEK_MODEL=deepseek-chat
#
# Uso:
#   python scripts/extrator_discursivas_fisiologia.py
#   python scripts/extrator_discursivas_fisiologia.py <filtro>  → filtra capítulos pelo nome

import os, sys, json, time, re, concurrent.futures, unicodedata
import openai
import fitz  # PyMuPDF
from tenacity import retry, stop_after_attempt, wait_exponential
from dotenv import load_dotenv

load_dotenv()

# ── Configuração ──────────────────────────────────────────────────────────────
FISIOLOGIA_DIR       = os.getenv("FISIOLOGIA_DIR",       "D:/pdfs/Fisiologia/Capitulos")
OUTPUT_FILE          = os.getenv("OUTPUT_FILE",          "D:/vetrank/Livro/questoes/questoes_discursivas_fisiologia.json")
DISCIPLINE_ID        = int(os.getenv("FISIOLOGIA_DISCIPLINE_ID", "30001"))
SUBJECT_ID           = int(os.getenv("FISIOLOGIA_SUBJECT_ID",   "30007"))
QUESTIONS_PER_CHAP   = int(os.getenv("FISIOLOGIA_QUESTIONS_PER_CHAPTER", "3"))
DEEPSEEK_MODEL       = os.getenv("FISIOLOGIA_DEEPSEEK_MODEL",    "deepseek-chat")
YEAR                 = 2026

if DISCIPLINE_ID == 0 or SUBJECT_ID == 0:
    sys.exit("ERRO: defina FISIOLOGIA_DISCIPLINE_ID e FISIOLOGIA_SUBJECT_ID no .env.")

client = openai.OpenAI(
    api_key=os.getenv("DEEPSEEK_API_KEY"),
    base_url="https://api.deepseek.com",
)

# ── Extrair Texto Parcial (suficiente para discursivas) ─────────────────────
def extract_partial_text(pdf_path: str, max_chars: int = 15000) -> str:
    full_text = ""
    try:
        with fitz.open(pdf_path) as doc:
            for page in doc:
                full_text += page.get_text() + " "
                if len(full_text) >= max_chars:
                    break
    except Exception as e:
        print(f"   [ERRO] Falha ao ler {os.path.basename(pdf_path)}: {e}")
        return ""
    return full_text[:max_chars].strip()

# ── Formatar Assunto/Tag amigável ─────────────────────────────────────────────
def get_subject_tag(filename: str) -> str:
    # Ex: Capitulo_1_Tecido_Nervoso.pdf → Fisiologia (Tecido Nervoso)
    # Remove prefixo Capitulo_XX_ ou Capitulo_X_
    name = os.path.splitext(filename)[0]
    name = re.sub(r'^Capitulo_\d+_', '', name)
    name = re.sub(r'^Capitulo_\d+', '', name)
    name = name.replace('_', ' ').strip()
    # Adiciona prefixo Fisiologia
    return f"Fisiologia ({name})"

# ── Chamada de API ────────────────────────────────────────────────────────────
@retry(wait=wait_exponential(multiplier=1, min=2, max=10), stop=stop_after_attempt(3))
def generate_discursive_batch(filename: str, chunk: str, tag: str) -> list:
    system_msg = (
        f"Voce e especialista em elaboracao de questoes discursivas (abertas) "
        f"de FISIOLOGIA VETERINARIA para concursos e provas de residencia. "
        f"Tema: {tag}. "
        f"Responda APENAS com um array JSON valido contendo as questoes discursivas. Sem markdown, sem texto extra."
    )

    prompt = (
        f"Use o TEXTO FONTE abaixo para gerar exatamente {QUESTIONS_PER_CHAP} questoes discursivas (abertas) "
        f"inéditas e aprofundadas sobre {tag}.\n\n"
        f"TEXTO FONTE:\n\"\"\"\n{chunk}\n\"\"\"\n\n"
        f"REGRAS OBRIGATÓRIAS:\n"
        f"- Cada questão deve exigir do aluno a explicação de mecanismos fisiológicos e correlações clínicas veterinárias.\n"
        f"- Enunciado contextualizado (textPt): mínimo 3 linhas, descrevendo um cenário fisiológico, clínico ou zootécnico (ex: estresse térmico, lactação, insuficiência renal, exercício físico, etc.) e fazendo uma pergunta discursiva clara e direta.\n"
        f"- Resposta Padrão Esperada (expectedAnswerPt): Explicação detalhada e acadêmica (mínimo 6 linhas) com os pontos fundamentais que o examinador deve buscar na resposta do aluno (critérios de correção e palavras-chave obrigatórias).\n"
        f"- Dificuldade (difficulty): 'medium' ou 'hard'.\n"
        f"- Retorne APENAS um array JSON válido contendo exatamente os {QUESTIONS_PER_CHAP} objetos no formato abaixo:\n\n"
        f"[\n"
        f"  {{\n"
        f"    \"textPt\": \"Enunciado da questao discursiva...\",\n"
        f"    \"expectedAnswerPt\": \"Resposta detalhada padrão / criterio de correcao...\",\n"
        f"    \"difficulty\": \"hard\"\n"
        f"  }}\n"
        f"]"
    )

    try:
        response = client.chat.completions.create(
            model=DEEPSEEK_MODEL,
            messages=[
                {"role": "system", "content": system_msg},
                {"role": "user",   "content": prompt},
            ],
            temperature=0.7,
            timeout=90.0,
        )

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

        # Pós-processamento simples
        processed = []
        for q in parsed:
            processed.append({
                "disciplineId": DISCIPLINE_ID,
                "subjectId": SUBJECT_ID,
                "subjectTag": tag,
                "author": "VetRank",
                "year": YEAR,
                "difficulty": q.get("difficulty", "hard") if q.get("difficulty") in ("easy", "medium", "hard") else "hard",
                "textPt": q.get("textPt", "").strip(),
                "expectedAnswerPt": q.get("expectedAnswerPt", "").strip(),
                "isPremium": True
            })
        return processed

    except Exception as e:
        print(f"   [ERRO] Falha na chamada da API para {filename}: {e}")
        raise e

# ── Main ──────────────────────────────────────────────────────────────────────
def main():
    os.makedirs(os.path.dirname(OUTPUT_FILE), exist_ok=True)

    print("=================================================================")
    print("VetRank — Gerador de Questoes Discursivas: Fisiologia (Dukes)")
    print("=================================================================")
    print(f"   disciplineId={DISCIPLINE_ID} | subjectId={SUBJECT_ID}")
    print(f"   Questoes por capitulo: {QUESTIONS_PER_CHAP}")
    print(f"   Modelo DeepSeek: {DEEPSEEK_MODEL}")

    if not os.path.isdir(FISIOLOGIA_DIR):
        sys.exit(f"\n[ERRO] Pasta de capitulos nao encontrada: {FISIOLOGIA_DIR}")

    filter_arg = sys.argv[1].lower() if len(sys.argv) > 1 else None

    # Obter lista de PDFs
    files = sorted([f for f in os.listdir(FISIOLOGIA_DIR) if f.lower().endswith('.pdf')])
    
    # Aplicar filtro se houver
    if filter_arg:
        files = [f for f in files if filter_arg in f.lower() or filter_arg in get_subject_tag(f).lower()]

    if not files:
        print(f"\nNenhum PDF encontrado na pasta para o filtro '{filter_arg}'.")
        return

    print(f"   Total de capitulos a processar: {len(files)}")
    print("-----------------------------------------------------------------")

    # Carregar progresso anterior para retomada
    all_questions = []
    if os.path.exists(OUTPUT_FILE):
        try:
            with open(OUTPUT_FILE, 'r', encoding='utf-8') as f:
                existing = json.load(f)
                if isinstance(existing, list):
                    all_questions = existing
                    print(f"[Retomada] Carregadas {len(all_questions)} questoes ja existentes.")
        except Exception:
            pass

    # Mapear arquivos já processados pelo subjectTag
    processed_tags = {q["subjectTag"] for q in all_questions if "subjectTag" in q}

    # Processar capítulos usando um pool de threads para agilizar
    max_workers = 5  # Número seguro e rápido de workers paralelos
    print(f"Iniciando geracao em paralelo com {max_workers} threads...")

    futures = {}
    with concurrent.futures.ThreadPoolExecutor(max_workers=max_workers) as executor:
        for f in files:
            tag = get_subject_tag(f)
            
            if tag in processed_tags:
                print(f"   [PULANDO] {f} (ja processado)")
                continue

            pdf_path = os.path.join(FISIOLOGIA_DIR, f)
            text = extract_partial_text(pdf_path)
            
            if not text:
                print(f"   [WARN] Sem texto extraido de {f}. Pulando.")
                continue

            # Envia para execução em paralelo
            future = executor.submit(generate_discursive_batch, f, text, tag)
            futures[future] = f

        # Coletar resultados à medida que completam
        for future in concurrent.futures.as_completed(futures):
            filename = futures[future]
            try:
                questions = future.result()
                if questions:
                    all_questions.extend(questions)
                    # Salva progresso imediatamente
                    with open(OUTPUT_FILE, 'w', encoding='utf-8') as out_f:
                        json.dump(all_questions, out_f, ensure_ascii=False, indent=2)
                    print(f"   [SUCESSO] {filename} -> gerou {len(questions)} questoes. Total acumulado: {len(all_questions)}")
            except Exception as e:
                print(f"   [FALHA] Nao foi possivel processar {filename}: {e}")

    print("\n=================================================================")
    print(f"Concluido! Total de {len(all_questions)} questoes salvas em:")
    print(f"   -> {OUTPUT_FILE}")
    print("=================================================================")

if __name__ == "__main__":
    main()
