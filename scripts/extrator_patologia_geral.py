# VetRank — Gerador de Questões: Patologia Geral e Sistêmica (versão tolerante com logs)
import os, sys, json, time, re, math, concurrent.futures, unicodedata, hashlib, logging, argparse
from datetime import datetime
from typing import List, Dict, Optional
import openai
import fitz
from tenacity import retry, stop_after_attempt, wait_exponential
from dotenv import load_dotenv

load_dotenv()

# ── Configuração ──────────────────────────────────────────────────────────────
GERAL_DIR        = os.getenv("GERAL_DIR",        "D:/pdfs/Patologia geral")
OUTPUT_DIR       = os.getenv("OUTPUT_DIR",       "D:/vetrank/Livro/questoes")
CACHE_DIR        = os.getenv("GERAL_CACHE_DIR",  "D:/vetrank/cache/geral")
DISCIPLINE_ID    = int(os.getenv("GERAL_DISCIPLINE_ID", "30002"))
DEFAULT_SUBJECT_ID = int(os.getenv("GERAL_SUBJECT_ID", "30013"))
TOPIC_ID         = int(os.getenv("GERAL_TOPIC_ID",      "0"))
TARGET_QUESTIONS = int(os.getenv("GERAL_TARGET_QUESTIONS", "100"))
MODELS_PER_CHUNK = int(os.getenv("GERAL_MODELS_PER_CHUNK", "8"))
CHUNK_SIZE       = int(os.getenv("GERAL_CHUNK_SIZE",    "25000"))
MAX_QPM          = int(os.getenv("GERAL_MAX_QPM",       "5"))
MIN_QPM          = int(os.getenv("GERAL_MIN_QPM",       "2"))
DEEPSEEK_MODEL   = os.getenv("GERAL_DEEPSEEK_MODEL",    "deepseek-chat")
YEAR             = int(os.getenv("GERAL_YEAR",           "2026"))
RATE_LIMIT       = int(os.getenv("GERAL_RATE_LIMIT",    "20"))

QUESTION_MODELS = ['M1', 'M2', 'M3', 'M4', 'M5', 'M6', 'M7', 'M8']

# Mapeamento de tags (mesmo)
CHAPTER_TAGS = {
    "colors_of_normal_tissues_and_organs": "Cores de Tecidos e Órgãos Normais",
    "diagnoses_in_veterinary_pathology": "Diagnósticos em Patologia Veterinária",
    "diagnostic_pathology": "Patologia Diagnóstica",
    "diseases_of_immunitya": "Doenças da Imunidade",
    "endocrine_systema": "Sistema Endócrino",
    "evaluation_of_lesions_affecting_tissues_and_organs": "Avaliação de Lesões em Tecidos e Órgãos",
    "female_reproductive_system_and_mammaea": "Sistema Reprodutor Feminino e Mamas",
    "hepatobiliary_system_and_exocrine_pancreasa": "Sistema Hepatobiliar e Pâncreas Exócrino",
    "inflammation_and_healinga": "Inflamação e Reparo Tecidual",
    "language_of_veterinary_pathology": "A Linguagem da Patologia Veterinária",
    "male_reproductive_systema": "Sistema Reprodutor Masculino",
    "mechanisms_of_microbial_infectionsa": "Mecanismos das Infecções Microbianas",
    "neoplasia_and_tumor_biologya": "Neoplasia e Biologia Tumoral",
    "nervous_systema": "Sistema Nervoso",
    "recognition_and_interpretation_of_lesions": "Reconhecimento e Interpretação de Lesões",
    "skeletal_musclea": "Músculo Esquelético",
    "the_ear": "Orelha e Sistema Auditivo",
    "the_eye": "Olho e Sistema Oftálmico",
    "the_integumenta": "Tegumento e Sistema Dermatológico",
    "the_urinary_systema": "Sistema Urinário",
    "vascular_disorders_and_thrombosisa": "Distúrbios Vasculares e Trombose",
    "a_communication_collaboration_and_consultation_in_veterinary_pathology": "Comunicação, Colaboração e Consulta em Patologia",
    "alimentary_system_and_the_peritoneum_omentum_mesentery_and_peritoneal_cavitya": "Sistema Alimentar e Peritônio",
    "b_photographic_techniques_in_veterinary_pathology": "Técnicas Fotográficas em Patologia",
    "bone_marrow_blood_cells_and_the_lymphoidlymphatic_systema": "Medula Óssea, Células Sanguíneas e Sistema Linfático",
    "bones_joints_tendons_and_ligamentsa": "Ossos, Articulações, Tendões e Ligamentos",
    "c_postmortem_examination_autopsy_of_domestic_animal_speciesa": "Exame Post-mortem e Necropsia",
    "cardiovascular_system_pericardial_cavity_and_lymphatic_vesselsa": "Sistema Cardiovascular",
    "d_recognition_and_interpretation_of_macroscopic_gross_lesions": "Reconhecimento e Interpretação de Lesões Macroscópicas",
    "e_diagnoses_in_veterinary_pathologya": "Diagnósticos em Patologia Veterinária",
    "f_general_principles_of_tissue_fixation_and_processing": "Princípios Gerais de Fixação e Processamento de Tecidos",
    "g_fixatives_and_fixation": "Fixadores e Fixação",
    "h_fixation_and_trimming_of_the_brain_spinal_cord_and_nerves": "Fixação e Clivagem do Encéfalo, Medula Espinhal e Nervos",
    "i_surgical_biopsiesprocurement_processing_and_sectioning_artifactsa": "Biópsias Cirúrgicas: Obtenção, Processamento e Artefatos",
    "j_gross_examination_and_infusion_fixation_of_the_lung": "Exame Macroscópico e Fixação do Pulmão",
    "k_necropsy_techniques_by_organ_systema": "Técnicas de Necropsia por Sistema Orgânico",
    "mechanisms_and_morphology_of_cellular_injury_adaptation_and_deatha": "Mecanismos e Morfologia da Lesão Celular, Adaptação e Morte",
    "pathologic_basis": "Bases Patológicas Gerais",
    "respiratory_system_thoracic_cavities_mediastinum_and_pleuraea": "Sistema Respiratório, Cavidade Torácica e Pleura"
}

PDF_SUBJECT_MAP = {
    "Colors_of_Normal_Tissues_and_Organs": 30013,
    "Diagnoses_in_Veterinary_Pathology": 30013,
    "Diagnostic_Pathology": 30013,
    "Diseases_of_Immunitya": 30014,
    "Endocrine_Systema": 30014,
    "Evaluation_of_Lesions_Affecting_Tissues_and_Organs": 30013,
    "Female_Reproductive_System_and_Mammaea": 30014,
    "Hepatobiliary_System_and_Exocrine_Pancreasa": 30014,
    "Inflammation_and_Healinga": 30013,
    "Language_of_Veterinary_Pathology": 30013,
    "Male_Reproductive_Systema": 30014,
    "Mechanisms_of_Microbial_Infectionsa": 30014,
    "Neoplasia_and_Tumor_Biologya": 30013,
    "Nervous_Systema": 30014,
    "Recognition_and_Interpretation_of_Lesions": 30013,
    "Skeletal_Musclea": 30014,
    "The_Ear": 30014,
    "The_Eye": 30014,
    "The_Integumenta": 30014,
    "The_Urinary_Systema": 30014,
    "Vascular_Disorders_and_Thrombosisa": 30014,
    "a_communication_collaboration_and_consultation_in_veterinary_pathology": 30013,
    "alimentary_system_and_the_peritoneum_omentum_mesentery_and_peritoneal_cavitya": 30014,
    "b_photographic_techniques_in_veterinary_pathology": 30013,
    "bone_marrow_blood_cells_and_the_lymphoidlymphatic_systema": 30014,
    "bones_joints_tendons_and_ligamentsa": 30014,
    "c_postmortem_examination_autopsy_of_domestic_animal_speciesa": 30013,
    "cardiovascular_system_pericardial_cavity_and_lymphatic_vesselsa": 30014,
    "d_recognition_and_interpretation_of_macroscopic_gross_lesions": 30013,
    "e_diagnoses_in_veterinary_pathologya": 30013,
    "f_general_principles_of_tissue_fixation_and_processing": 30013,
    "g_fixatives_and_fixation": 30013,
    "h_fixation_and_trimming_of_the_brain_spinal_cord_and_nerves": 30013,
    "i_surgical_biopsiesprocurement_processing_and_sectioning_artifactsa": 30013,
    "j_gross_examination_and_infusion_fixation_of_the_lung": 30013,
    "k_necropsy_techniques_by_organ_systema": 30013,
    "mechanisms_and_morphology_of_cellular_injury_adaptation_and_deatha": 30013,
    "pathologic_basis": 30013,
    "respiratory_system_thoracic_cavities_mediastinum_and_pleuraea": 30014,
}

M5_OPTIONS = [
    {"id": "A", "textPt": "As asserções I e II são verdadeiras, e a II é justificativa correta da I."},
    {"id": "B", "textPt": "As asserções I e II são verdadeiras, mas a II não é justificativa correta da I."},
    {"id": "C", "textPt": "A asserção I é verdadeira, e a II é falsa."},
    {"id": "D", "textPt": "A asserção I é falsa, e a II é verdadeira."},
    {"id": "E", "textPt": "As asserções I e II são falsas."},
]

client = openai.OpenAI(api_key=os.getenv("DEEPSEEK_API_KEY"), base_url="https://api.deepseek.com")

os.makedirs(OUTPUT_DIR, exist_ok=True)
os.makedirs(CACHE_DIR, exist_ok=True)
LOG_FILE = os.path.join(OUTPUT_DIR, f"geral_{datetime.now().strftime('%Y%m%d_%H%M%S')}.log")
logging.basicConfig(
    level=logging.DEBUG,  # DEBUG para ver respostas brutas
    format='%(asctime)s - %(levelname)s - %(message)s',
    handlers=[logging.FileHandler(LOG_FILE, encoding='utf-8'), logging.StreamHandler()]
)
logger = logging.getLogger(__name__)

# ── Função para normalizar e corrigir questão (tolerante) ────────────────────
def normalize_question(q: dict, model_type: str, subject_tag: str, discipline_id: int, subject_id: int) -> Optional[dict]:
    """Converte qualquer dicionário em uma questão válida, preenchendo campos faltantes."""
    out = {}
    
    # textPt (obrigatório)
    text = q.get('textPt') or q.get('enunciado') or q.get('question') or q.get('text')
    if not text or len(str(text)) < 20:
        logger.warning(f"textPt muito curto ou ausente: {text}")
        return None
    out['textPt'] = str(text).strip()
    
    # options
    options = q.get('options') or q.get('alternatives')
    if not options or not isinstance(options, list):
        # tenta construir a partir de keys alternativas
        alt_list = []
        for letter in ['A','B','C','D','E']:
            alt = q.get(letter) or q.get(f'opt_{letter}') or q.get(f'alternativa_{letter}')
            if alt:
                alt_list.append({'id': letter, 'textPt': str(alt)})
        if len(alt_list) == 5:
            options = alt_list
    if not options or len(options) != 5:
        logger.warning(f"options inválidas: {options}")
        return None
    # garantir que cada option tenha id e textPt
    for i, opt in enumerate(options):
        if isinstance(opt, str):
            opt = {'id': chr(65+i), 'textPt': opt}
        if 'id' not in opt:
            opt['id'] = chr(65+i)
        if 'textPt' not in opt:
            opt['textPt'] = str(opt.get('text', ''))
    out['options'] = options
    
    # correctOptionLetter
    correct = q.get('correctOptionLetter') or q.get('correctAlternative') or q.get('gabarito') or q.get('answer')
    if correct:
        letter = str(correct).upper().strip()[0]
        if letter in 'ABCDE':
            out['correctOptionLetter'] = letter
        else:
            out['correctOptionLetter'] = 'A'
    else:
        out['correctOptionLetter'] = 'A'
    
    # explanationPt
    explanation = q.get('explanationPt') or q.get('explicacao') or q.get('justificativa')
    if not explanation or len(str(explanation)) < 20:
        explanation = f"Explicação: {out['textPt'][:200]}..."
    out['explanationPt'] = str(explanation).strip()
    
    # difficulty
    diff = q.get('difficulty') or q.get('dificuldade')
    diff_map = {'muito fácil':'very_easy','facil':'easy','easy':'easy','médio':'medium','medio':'medium','difícil':'hard','dificil':'hard','hard':'hard','muito difícil':'very_hard'}
    out['difficulty'] = diff_map.get(str(diff).lower(), 'hard')
    
    # Para M5, assertion1 e assertion2
    if model_type == 'M5':
        out['assertion1'] = q.get('assertion1') or out['textPt']
        out['assertion2'] = q.get('assertion2') or "Justificativa não fornecida."
    
    # Metadados fixos
    out['disciplineId'] = discipline_id
    out['subjectId'] = subject_id
    out['topicId'] = TOPIC_ID
    out['subjectTag'] = subject_tag
    out['year'] = YEAR
    out['author'] = 'VetRank'
    out['isPremium'] = True
    out['status'] = 'approved'
    out['modelId'] = model_type
    
    # questionType
    if model_type == 'M5':
        out['questionType'] = 'assertion_reason'
        out['options'] = [dict(o) for o in M5_OPTIONS]
    elif model_type == 'M3':
        out['questionType'] = 'complex_multiple_choice'
    elif model_type == 'M6':
        out['questionType'] = 'cloze'
    elif model_type == 'M7':
        out['questionType'] = 'clinical_case'
    elif model_type == 'M8':
        out['questionType'] = 'matching'
    else:
        out['questionType'] = 'multiple_choice'
    
    return out

# ── Prompt simplificado ───────────────────────────────────────────────────────
def build_user_prompt(model_type: str, num_q: int, subject_tag: str) -> str:
    # Prompt curto e direto, pedindo explicitamente o formato JSON
    return (
        f"Você é um especialista em Patologia Veterinária. Gere exatamente {num_q} questões de múltipla escolha (nível residência) sobre o tópico: {subject_tag}.\n"
        f"Cada questão deve ser um objeto JSON com os campos:\n"
        f"- textPt (string longa, o enunciado)\n"
        f"- options (lista de 5 objetos, cada um com id: 'A' a 'E' e textPt: texto da alternativa)\n"
        f"- correctOptionLetter (string, uma letra de A a E)\n"
        f"- explanationPt (string, explicação detalhada)\n"
        f"- difficulty (string, sempre \"hard\")\n"
        f"Retorne APENAS um array JSON. Exemplo:\n"
        f'[{{"textPt": "Questão...", "options": [{{"id": "A", "textPt": "..."}}, ...], "correctOptionLetter": "B", "explanationPt": "...", "difficulty": "hard"}}]\n'
        f"Não adicione texto antes ou depois do JSON."
    )

# ── Extração de texto ────────────────────────────────────────────────────────
def extract_text(pdf_path: str) -> str:
    full_text = ""
    try:
        with fitz.open(pdf_path) as doc:
            total = len(doc)
            for i, page in enumerate(doc, 1):
                full_text += page.get_text() + " "
                if i % 30 == 0:
                    logger.info(f"   📖 {i}/{total} páginas")
    except Exception as e:
        logger.error(f"Erro ao ler PDF: {e}")
        return ""
    text = full_text.strip()
    logger.info(f"   ✅ {len(text):,} chars | {total} páginas")
    return text

# ── Geração de lote (com cache e log da resposta bruta) ──────────────────────
@retry(wait=wait_exponential(multiplier=1, min=2, max=10), stop=stop_after_attempt(2))
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
        hit = getattr(usage, 'prompt_cache_hit_tokens', 0)
        miss = usage.prompt_tokens - hit
        logger.info(f"   {'💾' if hit>0 else '🔄'} In={usage.prompt_tokens}(hit={hit} miss={miss}) Out={usage.completion_tokens}")
        
        raw = response.choices[0].message.content or "[]"
        logger.debug(f"Raw response from {model_type} (first 500 chars): {raw[:500]}")
        
        # Limpeza básica
        raw = re.sub(r'^```(?:json)?\s*', '', raw, flags=re.IGNORECASE)
        raw = re.sub(r'\s*```$', '', raw).strip()
        
        # Tentar extrair array JSON
        start = raw.find('[')
        if start == -1:
            start = raw.find('{')
            if start != -1:
                raw = '[' + raw[start:] + ']'
        else:
            depth, in_str, esc = 0, False, False
            end = start
            for i, ch in enumerate(raw[start:], start):
                if esc: esc=False; continue
                if ch=='\\' and in_str: esc=True; continue
                if ch=='"' and not esc: in_str = not in_str; continue
                if in_str: continue
                if ch=='[': depth+=1
                elif ch==']':
                    depth-=1
                    if depth==0: end=i; break
            raw = raw[start:end+1]
        
        # Tentar reparo JSON (opcional)
        try:
            import json_repair
            raw = json_repair.repair_json(raw)
        except ImportError:
            pass
        except Exception as e:
            logger.warning(f"json_repair falhou: {e}")
        
        try:
            parsed = json.loads(raw)
        except json.JSONDecodeError as e:
            logger.error(f"JSON inválido para {model_type}: {e}")
            return []
        
        if not isinstance(parsed, list):
            parsed = [parsed]
        
        # Normalizar cada questão
        valid_questions = []
        for q in parsed:
            norm = normalize_question(q, model_type, subject_tag, discipline_id, subject_id)
            if norm:
                valid_questions.append(norm)
            else:
                logger.warning(f"Questão descartada para {model_type}: {q}")
        
        return valid_questions
        
    except Exception as e:
        logger.error(f"Erro em {model_type} chunk {chunk_idx}: {e}")
        raise e

# ── Funções auxiliares (calc_qpm, process_pdf, main) são basicamente as mesmas ──
# Para evitar repetir, mantenha as funções calc_qpm, process_pdf e main do script anterior,
# mas substitua a chamada a generate_batch e post_process pela nova lógica.
# Abaixo vou fornecer as versões adaptadas de process_pdf e main (pois mudam pouco).

def calc_qpm(num_chunks: int) -> int:
    if num_chunks == 0:
        return MIN_QPM
    raw = math.ceil(TARGET_QUESTIONS / (num_chunks * MODELS_PER_CHUNK))
    return max(MIN_QPM, min(MAX_QPM, raw))

def process_pdf(filename: str, subject_tag: str, subject_id: int,
                discipline_id: int, dry_run: bool = False) -> int:
    pdf_path = os.path.join(GERAL_DIR, filename)
    stem = os.path.splitext(filename)[0]
    safe_stem = unicodedata.normalize('NFKD', stem).encode('ascii', 'ignore').decode('ascii')
    safe_stem = re.sub(r'[^\w\s-]', '', safe_stem).strip().lower()
    safe_stem = re.sub(r'[-\s]+', '_', safe_stem)
    output_path = os.path.join(OUTPUT_DIR, f"questoes_patologia_geral_{safe_stem}.json")
    
    logger.info(f"\n{'='*65}")
    logger.info(f"📚 Processando: {subject_tag}")
    logger.info(f"   disciplineId={discipline_id} | subjectId={subject_id}")
    logger.info(f"   Arquivo: {os.path.basename(output_path)}")
    
    if not os.path.exists(pdf_path):
        logger.warning(f"⚠️ PDF não encontrado: {filename}")
        return 0
    
    if dry_run:
        logger.info("🔍 DRY RUN: apenas extraindo texto e estimando")
        pdf_text = extract_text(pdf_path)
        if pdf_text:
            chunks = [pdf_text[i:i+CHUNK_SIZE] for i in range(0, len(pdf_text), CHUNK_SIZE)]
            qpm = calc_qpm(len(chunks))
            models = QUESTION_MODELS[:MODELS_PER_CHUNK]
            estimativa = len(chunks) * qpm * len(models)
            logger.info(f"   Estimativa: ~{estimativa} questões (chunks={len(chunks)}, qpm={qpm}, modelos={len(models)})")
        return 0
    
    logger.info(f"\n📄 Extraindo texto...")
    pdf_text = extract_text(pdf_path)
    if not pdf_text:
        logger.error(f"❌ Falha na extração: {filename}")
        return 0
    
    chunks = [pdf_text[i:i+CHUNK_SIZE] for i in range(0, len(pdf_text), CHUNK_SIZE)]
    num_chunks = len(chunks)
    qpm = calc_qpm(num_chunks)
    models_ativo = QUESTION_MODELS[:MODELS_PER_CHUNK]
    estimativa = num_chunks * qpm * len(models_ativo)
    logger.info(f"\n🔢 {num_chunks} chunks × {qpm} q × {len(models_ativo)} modelos ≈ {estimativa} questões (meta: {TARGET_QUESTIONS})")
    
    all_questions = []
    if os.path.exists(output_path):
        try:
            with open(output_path, 'r', encoding='utf-8') as f:
                existing = json.load(f)
            if isinstance(existing, list) and existing:
                all_questions = existing
                logger.info(f"   ♻️ Retomando com {len(all_questions)} questões")
        except Exception:
            pass
    
    for i, chunk in enumerate(chunks):
        system_msg = (
            f"Você é especialista em PATOLOGIA VETERINÁRIA. Tópico: {subject_tag}.\n"
            f"Use o seguinte texto como fonte para criar as questões (não mencione o texto na pergunta):\n\n{chunk}"
        )
        logger.info(f"\n{'─'*65}\n📦 Chunk {i+1}/{num_chunks} ({len(chunk):,} chars)")
        chunk_total = 0
        with concurrent.futures.ThreadPoolExecutor(max_workers=len(models_ativo)) as executor:
            future_to_model = {
                executor.submit(generate_batch, i+1, model_type, system_msg, qpm,
                                subject_tag, discipline_id, subject_id): model_type
                for model_type in models_ativo
            }
            for future in concurrent.futures.as_completed(future_to_model):
                model_type = future_to_model[future]
                try:
                    questions = future.result()
                    if questions:
                        all_questions.extend(questions)
                        chunk_total += len(questions)
                        logger.info(f"   ✅ Modelo {model_type} gerou {len(questions)} questões.")
                except Exception as exc:
                    logger.error(f"   ❌ Modelo {model_type} falhou: {exc}")
        if chunk_total > 0:
            with open(output_path, 'w', encoding='utf-8') as f:
                json.dump(all_questions, f, ensure_ascii=False, indent=2)
            logger.info(f"   💾 +{chunk_total} | Total: {len(all_questions)}")
        if i < num_chunks-1:
            time.sleep(2)
    logger.info(f"\n🎉 {filename}: {len(all_questions)} questões → {output_path}")
    return len(all_questions)

def main():
    parser = argparse.ArgumentParser()
    parser.add_argument("filter", nargs="?", default=None, help="Filtro por nome/tag")
    parser.add_argument("--dry-run", action="store_true", help="Simular sem chamar API")
    args = parser.parse_args()
    os.makedirs(OUTPUT_DIR, exist_ok=True)
    os.makedirs(CACHE_DIR, exist_ok=True)
    logger.info(f"\n{'='*65}\n🔎 VetRank — Patologia Geral e Sistêmica (tolerante)\n{'='*65}")
    logger.info(f"   disciplineId={DISCIPLINE_ID}")
    logger.info(f"   Modelo: {DEEPSEEK_MODEL}")
    logger.info(f"   Meta por PDF: {TARGET_QUESTIONS}")
    if args.dry_run:
        logger.info("   🔍 DRY RUN ativado")
    if not os.path.isdir(GERAL_DIR):
        logger.error(f"Pasta não encontrada: {GERAL_DIR}")
        return
    filter_lower = args.filter.lower() if args.filter else None
    def normalize_key(s: str) -> str:
        s = unicodedata.normalize('NFKD', s).encode('ascii', 'ignore').decode('ascii')
        return re.sub(r'[^a-z0-9]', '', s.lower())
    normalized_tags = {normalize_key(k): v for k, v in CHAPTER_TAGS.items()}
    pdfs_to_run = []
    for filename in sorted(os.listdir(GERAL_DIR)):
        if not filename.lower().endswith('.pdf'):
            continue
        stem = filename[:-4]
        tag = normalized_tags.get(normalize_key(stem))
        if not tag:
            tag = re.sub(r'[_\s]+', ' ', stem).strip()
        subject_id = PDF_SUBJECT_MAP.get(stem, DEFAULT_SUBJECT_ID)
        tipo = "Geral" if subject_id == 30013 else ("Sistêmica" if subject_id == 30014 else "Fallback")
        if filter_lower and filter_lower not in filename.lower() and filter_lower not in tag.lower():
            continue
        pdfs_to_run.append({'file': filename, 'tag': tag, 'subject_id': subject_id, 'tipo': tipo})
    if not pdfs_to_run:
        logger.info(f"Nenhum PDF para o filtro '{args.filter}'.")
        return
    logger.info(f"\n   PDFs a processar: {len(pdfs_to_run)}")
    for p in pdfs_to_run:
        logger.info(f"      • {p['file']} → {p['tag']} (subject_id={p['subject_id']}, {p['tipo']})")
    grand_total = 0
    results = []
    for idx, pdf_info in enumerate(pdfs_to_run):
        count = process_pdf(pdf_info['file'], pdf_info['tag'], pdf_info['subject_id'],
                            DISCIPLINE_ID, dry_run=args.dry_run)
        grand_total += count
        results.append({"file": pdf_info['file'], "tag": pdf_info['tag'],
                        "subject_id": pdf_info['subject_id'], "questoes": count})
        if not args.dry_run and idx < len(pdfs_to_run)-1:
            logger.info("\n⏸  5s antes do próximo PDF...")
            time.sleep(5)
    logger.info(f"\n{'='*65}\n📊 RESUMO FINAL\n{'='*65}")
    for r in results:
        ok = r['questoes'] >= TARGET_QUESTIONS * 0.85
        logger.info(f"   {'✅' if ok else '⚠️ '} {r['tag']:<45} {r['questoes']:>5} q  (subject_id={r['subject_id']})")
    logger.info(f"{'─'*65}\n   TOTAL: {grand_total} questões em {len(results)} PDF(s)")
    if not args.dry_run:
        logger.info(f"\n📝 Log salvo em: {LOG_FILE}")
    logger.info(f"{'='*65}")

if __name__ == "__main__":
    main()