# VetRank — Gerador de questões guiado pela demanda do site (DeepSeek + livro de referência)
#
# Fluxo:
#   1. Admin → Conteúdo → "Plano de conteúdo": selecione os assuntos e baixe plano_conteudo_AAAA-MM-DD.json
#   2. python scripts/gerador_questoes.py plano plano_conteudo.json
#        → lista as prioridades (rank, assunto, quantas questões gerar)
#   3. python scripts/gerador_questoes.py gerar plano_conteudo.json 1 --pdf "D:/pdfs/livro.pdf" --paginas 120-185
#        → gera as questões do item 1 usando as páginas indicadas do livro
#   4. Admin → Importar → JSON: importe  <saída>/<item>/objetivas.json  (e discursivas.json)
#      Revise à mão  revisar_manual.json  (revisor discordou do gabarito) e reimporte o que estiver ok
#      com:  python scripts/gerador_questoes.py validar revisar_manual.json
#
# Opções úteis do "gerar":
#   --objetivas N / --discursivas N   (padrão: o recomendado no plano)
#   --modelos M1,M3,M7                (padrão: todos M1–M8, M1 e M7 com peso dobrado)
#   --sem-revisao                     (pula a resolução às cegas do gabarito — mais barato, menos seguro)
#   --pdf pode ser repetido; --paginas aceita "10-40,55,60-62" (aplicado a todos os PDFs)
#
# .env:
#   DEEPSEEK_API_KEY=sk-...
#   GERADOR_SAIDA=D:/vetrank/questoes/geradas      (pasta de saída)
#   GERADOR_MODELO=deepseek-chat                   (geração)
#   GERADOR_MODELO_REVISOR=deepseek-chat           (revisão; deepseek-reasoner = mais rigoroso e mais caro)
#   GERADOR_WORKERS=6
#
# Dependências: pip install openai pymupdf tenacity python-dotenv

import os, sys, re, json, math, time, random, argparse, unicodedata, threading, concurrent.futures
from collections import Counter
from datetime import datetime

try:
    import openai
    import fitz  # PyMuPDF
    from tenacity import retry, stop_after_attempt, wait_exponential
    from dotenv import load_dotenv
except ImportError as e:
    sys.exit(f"Dependência faltando ({e.name}). Rode: pip install openai pymupdf tenacity python-dotenv")

load_dotenv()
try:
    sys.stdout.reconfigure(encoding="utf-8")  # console do Windows (cp1252) quebra acentos
except Exception:
    pass

SAIDA_BASE     = os.getenv("GERADOR_SAIDA", "D:/vetrank/questoes/geradas")
MODELO         = os.getenv("GERADOR_MODELO", "deepseek-chat")
MODELO_REVISOR = os.getenv("GERADOR_MODELO_REVISOR", "deepseek-chat")
WORKERS        = int(os.getenv("GERADOR_WORKERS", "6"))
CHUNK_SIZE     = 20000
MAX_POR_CHAMADA = 4
YEAR           = datetime.now().year

LETRAS = ["A", "B", "C", "D", "E"]
M5_OPTIONS = [
    {"id": "A", "textPt": "As asserções I e II são verdadeiras, e a II é justificativa correta da I."},
    {"id": "B", "textPt": "As asserções I e II são verdadeiras, mas a II não é justificativa correta da I."},
    {"id": "C", "textPt": "A asserção I é verdadeira, e a II é falsa."},
    {"id": "D", "textPt": "A asserção I é falsa, e a II é verdadeira."},
    {"id": "E", "textPt": "As asserções I e II são falsas."},
]
TIPO_POR_MODELO = {
    "M1": "multiple_choice", "M2": "multiple_choice", "M3": "complex_multiple_choice",
    "M4": "multiple_choice", "M5": "assertion_reason", "M6": "cloze",
    "M7": "clinical_case", "M8": "matching",
}
PESOS_MODELO = {"M1": 2, "M2": 1, "M3": 1, "M4": 1, "M5": 1, "M6": 1, "M7": 2, "M8": 1}
MIX_DIFICULDADE = [("medium", 0.45), ("hard", 0.35), ("easy", 0.20)]

PROIBIDO = re.compile(
    r"(segundo o texto|de acordo com o texto|conforme o texto|conforme descrito|no cap[ií]tulo|neste cap[ií]tulo|"
    r"neste livro|o autor|os autores|texto fonte|trecho acima|figura\s*\d|tabela\s*\d|quadro\s*\d)",
    re.IGNORECASE,
)

_lock = threading.Lock()
TOKENS = Counter()


def client():
    key = os.getenv("DEEPSEEK_API_KEY")
    if not key:
        sys.exit("ERRO: defina DEEPSEEK_API_KEY no .env")
    return openai.OpenAI(api_key=key, base_url="https://api.deepseek.com")


# ── Utilidades ────────────────────────────────────────────────────────────────
def slugify(s: str) -> str:
    s = unicodedata.normalize("NFKD", s).encode("ascii", "ignore").decode("ascii")
    return re.sub(r"[^a-z0-9]+", "_", s.lower()).strip("_")[:60] or "item"


STOP = set("a o as os de da do das dos e em no na nos nas um uma para por com que se ao aos ou sua seu".split())


def shingles(text: str, n: int = 3) -> set:
    t = unicodedata.normalize("NFKD", text.lower()).encode("ascii", "ignore").decode("ascii")
    words = [w for w in re.findall(r"[a-z0-9]+", t[:300]) if w not in STOP]
    return {" ".join(words[i:i + n]) for i in range(max(0, len(words) - n + 1))}


def sobreposicao(a: set, b: set) -> float:
    # |A∩B| / min(|A|,|B|): funciona mesmo quando um dos textos está truncado (enunciados do site vêm com 220 chars)
    return len(a & b) / min(len(a), len(b)) if a and b else 0.0


class Dedup:
    def __init__(self, existentes):
        self.sets = [shingles(t) for t in existentes if t]

    def is_dup(self, text: str, limiar: float = 0.6) -> bool:
        s = shingles(text)
        return any(sobreposicao(s, o) >= limiar for o in self.sets if len(o) >= 5)

    def add(self, text: str):
        self.sets.append(shingles(text))


def parse_paginas(spec: str | None, total: int) -> list[int]:
    if not spec:
        return list(range(total))
    pages = set()
    for part in spec.split(","):
        part = part.strip()
        if "-" in part:
            a, b = part.split("-", 1)
            pages.update(range(int(a) - 1, min(int(b), total)))
        elif part:
            pages.add(int(part) - 1)
    return sorted(p for p in pages if 0 <= p < total)


def extrair_texto(pdfs: list[str], paginas: str | None) -> str:
    partes = []
    for path in pdfs:
        if not os.path.exists(path):
            sys.exit(f"ERRO: PDF não encontrado: {path}")
        with fitz.open(path) as doc:
            sel = parse_paginas(paginas, len(doc))
            for i in sel:
                partes.append(doc[i].get_text())
        print(f"   [PDF] {os.path.basename(path)}: {len(sel)} página(s)")
    txt = " ".join(partes)
    txt = re.sub(r"-\n(\w)", r"\1", txt)                    # hifenização de fim de linha
    txt = re.sub(r"(?m)^\s*\d{1,4}\s*$", " ", txt)           # números de página soltos
    txt = re.sub(r"[ \t]+", " ", txt)
    txt = re.sub(r"\n{2,}", "\n", txt).strip()
    if len(txt) < 3000:
        sys.exit(f"ERRO: só {len(txt)} caracteres extraídos. O PDF parece escaneado (imagem) — passe por OCR antes, ou confira --paginas.")
    print(f"   [OK] {len(txt):,} caracteres de texto")
    return txt


def chamar_json(cli, modelo: str, system: str, user: str, temperatura: float) -> dict:
    @retry(wait=wait_exponential(multiplier=1, min=2, max=20), stop=stop_after_attempt(4))
    def _go():
        r = cli.chat.completions.create(
            model=modelo,
            messages=[{"role": "system", "content": system}, {"role": "user", "content": user}],
            temperature=temperatura,
            response_format={"type": "json_object"},
            timeout=180.0,
        )
        u = r.usage
        with _lock:
            TOKENS["entrada"] += u.prompt_tokens
            TOKENS["cache"] += getattr(u, "prompt_cache_hit_tokens", 0) or 0
            TOKENS["saida"] += u.completion_tokens
        raw = (r.choices[0].message.content or "{}").strip()
        raw = re.sub(r"^```(?:json)?\s*|\s*```$", "", raw)
        return json.loads(raw)
    return _go()


# ── Prompts ───────────────────────────────────────────────────────────────────
REGRAS_MODELO = {
    "M1": "Múltipla escolha clássica. Enunciado contextualizado (mínimo 3 linhas: espécie, situação clínica/fisiológica/produtiva) antes da pergunta. Exija raciocínio aplicado, não memorização.",
    "M2": "Afirmação técnica incompleta terminando em ':' ou '...', com 2+ linhas de contexto antes da lacuna. As alternativas completam a frase.",
    "M3": "Múltipla escolha complexa. textPt com 4 assertivas: 'Sobre [tema], analise as assertivas:\\n\\nI. ...\\nII. ...\\nIII. ...\\nIV. ...\\n\\nSão CORRETAS apenas:'. Alternativas são combinações ('Apenas I e III', 'I, II e IV').",
    "M4": "Questão negativa: textPt DEVE conter 'EXCETO', 'NÃO' ou 'INCORRETA' em MAIÚSCULAS. 4 alternativas verdadeiras e 1 falsa (o gabarito).",
    "M5": "Asserção-razão. textPt: 'ASSERÇÃO I: [proposição, 2+ linhas].\\n\\nPORQUE\\n\\nASSERÇÃO II: [justificativa, 2+ linhas].' Inclua também os campos 'assertion1' e 'assertion2' (sem o prefixo). NÃO inclua 'options'. correctOption: A=ambas V e II justifica I | B=ambas V sem relação | C=I V, II F | D=I F, II V | E=ambas F.",
    "M6": "Lacuna (cloze): trecho técnico com lacuna(s) marcadas [___] ou [1], [2], com 3+ linhas de contexto. As alternativas preenchem as lacunas.",
    "M7": "Caso clínico: textPt DEVE começar com '[CASO CLÍNICO]'. Inclua espécie, sexo, idade, anamnese, sinais clínicos e achados de exame/laboratório com valores. Mínimo 5 dados antes da pergunta (diagnóstico, conduta ou prognóstico).",
    "M8": "Associação: 'Relacione os itens da Coluna I com os da Coluna II:\\n\\nCOLUNA I\\n1. ...\\n2. ...\\n3. ...\\n4. ...\\n\\nCOLUNA II\\na) ...\\nb) ...\\nc) ...\\nd) ...\\ne) ...\\n\\nA associação CORRETA é:'. 4 itens na Coluna I, 5 na II (1 distrator). Alternativas no formato '1-a / 2-c / 3-b / 4-d'.",
}


def contexto_item(item: dict) -> str:
    caminho = f"{item['disciplineName']} › {item['subjectName']}" + (f" › {item['topicName']}" if item.get("topicName") else "")
    notas = item.get("requestNotes") or []
    pedidos = ("\nPedidos de alunos para este assunto (priorize estes pontos):\n- " + "\n- ".join(notas[:8])) if notas else ""
    return caminho, pedidos


def system_geracao(item: dict, chunk: str) -> str:
    caminho, _ = contexto_item(item)
    return (
        "Você é professor de Medicina Veterinária e elabora questões inéditas para Residência, concursos públicos, "
        f"ENADE e CFMV. Área desta tarefa: {caminho}.\n"
        "Responda SEMPRE com um objeto JSON válido, sem markdown.\n\n"
        f"MATERIAL DE REFERÊNCIA (use como base técnica):\n\"\"\"\n{chunk}\n\"\"\""
    )


def user_objetivas(item: dict, modelo: str, dificuldades: list[str], gabaritos: list[str]) -> str:
    caminho, pedidos = contexto_item(item)
    n = len(dificuldades)
    gab = "" if modelo == "M5" else (
        f"- Gabaritos desta chamada, NA ORDEM das questões: {', '.join(gabaritos)}. Posicione a alternativa correta nessas letras.\n")
    exemplo = (
        '{"questoes":[{"textPt":"ASSERÇÃO I: ...\\n\\nPORQUE\\n\\nASSERÇÃO II: ...","assertion1":"...","assertion2":"...",'
        '"correctOption":"A","explanationPt":"...","difficulty":"hard"}]}'
        if modelo == "M5" else
        '{"questoes":[{"textPt":"...","options":[{"id":"A","textPt":"..."},{"id":"B","textPt":"..."},{"id":"C","textPt":"..."},'
        '{"id":"D","textPt":"..."},{"id":"E","textPt":"..."}],"correctOption":"C","explanationPt":"...","difficulty":"medium"}]}'
    )
    return (
        f"Gere {n} questão(ões) do MODELO {modelo} sobre: {caminho}.{pedidos}\n\n"
        f"MODELO {modelo}: {REGRAS_MODELO[modelo]}\n\n"
        "REGRAS:\n"
        f"- Dificuldades, na ordem: {', '.join(dificuldades)} (easy = graduação; medium = ENADE/concurso; hard = residência).\n"
        f"{gab}"
        "- Fique NO ASSUNTO indicado. Se o material de referência não tiver conteúdo suficiente sobre ele, "
        'retorne {"questoes": []} em vez de inventar ou fugir do tema.\n'
        "- PROIBIDO citar a fonte: 'segundo o texto', 'no capítulo', 'o autor', 'figura/tabela X'. A questão deve se sustentar sozinha.\n"
        "- PROIBIDO 'todas as anteriores', 'nenhuma das anteriores' e perguntas de definição trivial.\n"
        "- Distratores plausíveis (erros comuns, conceitos vizinhos, doses/valores de outra espécie).\n"
        "- Doses, valores de referência e nomes de fármacos devem ser reais e corretos para a espécie citada.\n"
        "- explanationPt (mínimo 5 linhas): por que a correta está certa e por que cada errada está errada, "
        "referindo-se às alternativas PELO CONTEÚDO, nunca pela letra.\n"
        "- EXATAMENTE 5 alternativas A–E (exceto M5, que não leva options).\n\n"
        f"Formato JSON:\n{exemplo}"
    )


def user_discursivas(item: dict, n: int) -> str:
    caminho, pedidos = contexto_item(item)
    return (
        f"Gere {n} questão(ões) DISCURSIVA(S) inédita(s) sobre: {caminho}.{pedidos}\n\n"
        "REGRAS:\n"
        "- Enunciado contextualizado (situação clínica, laboratorial ou produtiva) pedindo explicação, raciocínio ou conduta — "
        "não pergunte apenas 'defina X'. Pode ter itens a), b), c).\n"
        "- expectedAnswerPt = espelho de correção completo (mínimo 8 linhas): resposta esperada + lista dos pontos obrigatórios "
        "para nota máxima, em tópicos.\n"
        "- Fique no assunto; se o material não cobrir o tema, retorne {\"questoes\": []}.\n"
        "- PROIBIDO citar a fonte ('segundo o texto', 'no capítulo', 'figura X').\n"
        "- difficulty: easy, medium ou hard.\n\n"
        'Formato JSON: {"questoes":[{"textPt":"...","expectedAnswerPt":"...","difficulty":"medium"}]}'
    )


# ── Validação e normalização ──────────────────────────────────────────────────
def normalizar_objetiva(q: dict, modelo: str, item: dict) -> tuple[dict | None, str | None]:
    text = str(q.get("textPt") or q.get("enunciado") or "").strip()
    expl = str(q.get("explanationPt") or q.get("explicacao") or "").strip()
    corr = str(q.get("correctOption") or q.get("correctOptionLetter") or q.get("gabarito") or "").strip().upper()[:1]
    diff = str(q.get("difficulty") or "medium").lower()
    diff = diff if diff in ("very_easy", "easy", "medium", "hard", "very_hard") else "medium"

    if len(text) < 80: return None, "enunciado curto"
    if len(expl) < 150: return None, "explicação curta"
    if corr not in LETRAS: return None, "gabarito inválido"
    if PROIBIDO.search(text) or PROIBIDO.search(expl): return None, "cita a fonte/figura"

    if modelo == "M5":
        a1, a2 = str(q.get("assertion1") or "").strip(), str(q.get("assertion2") or "").strip()
        if not a1 or not a2 or "PORQUE" not in text.upper(): return None, "M5 sem asserções"
        options = [dict(o) for o in M5_OPTIONS]
    else:
        raw = q.get("options") or q.get("alternativas") or []
        options = []
        for i, o in enumerate(raw[:5]):
            if isinstance(o, str):
                options.append({"id": LETRAS[i], "textPt": re.sub(r"^[A-E][).:-]\s*", "", o).strip()})
            elif isinstance(o, dict):
                options.append({"id": str(o.get("id") or o.get("letter") or LETRAS[i]).upper()[:1],
                                "textPt": str(o.get("textPt") or o.get("text") or "").strip()})
        if len(options) != 5 or [o["id"] for o in options] != LETRAS: return None, "não tem 5 alternativas A–E"
        if any(len(o["textPt"]) < 1 for o in options): return None, "alternativa vazia"
        if len({o["textPt"].lower() for o in options}) < 5: return None, "alternativas repetidas"
        if any(re.search(r"(todas|nenhuma) (as|das) (anteriores|alternativas)", o["textPt"], re.I) for o in options):
            return None, "usa 'todas/nenhuma das anteriores'"

    up = text.upper()
    if modelo == "M3" and not all(f"{r}." in text for r in ("I", "II", "III", "IV")): return None, "M3 sem assertivas I–IV"
    if modelo == "M4" and not re.search(r"\b(EXCETO|NÃO|INCORRETA|INCORRETO)\b", text): return None, "M4 sem termo negativo"
    if modelo == "M6" and not re.search(r"\[(___+|\d)\]", text): return None, "M6 sem lacuna"
    if modelo == "M7" and not up.startswith("[CASO CLÍNICO]"): return None, "M7 sem [CASO CLÍNICO]"
    if modelo == "M8" and not ("COLUNA I" in up and "COLUNA II" in up): return None, "M8 sem colunas"

    out = {
        "disciplineId": item["disciplineId"],
        "subjectId": item["subjectId"],
        **({"topicId": item["topicId"]} if item.get("topicId") else {}),
        "subjectTag": item.get("topicName") or item["subjectName"],
        "difficulty": diff,
        "year": YEAR,
        "questionType": TIPO_POR_MODELO[modelo],
        "modelId": modelo,
        "textPt": text,
        "options": options,
        "correctOption": corr,
        "explanationPt": expl,
        "author": "VetRank",
        "isPremium": True,
    }
    if modelo == "M5":
        out["assertion1"], out["assertion2"] = a1, a2
    return out, None


def normalizar_discursiva(q: dict, item: dict) -> tuple[dict | None, str | None]:
    text = str(q.get("textPt") or "").strip()
    ans = str(q.get("expectedAnswerPt") or q.get("respostaEsperada") or "").strip()
    diff = str(q.get("difficulty") or "medium").lower()
    diff = {"very_easy": "easy", "very_hard": "hard"}.get(diff, diff)
    if diff not in ("easy", "medium", "hard"): diff = "medium"
    if len(text) < 60: return None, "enunciado curto"
    if len(ans) < 300: return None, "espelho de correção curto"
    if PROIBIDO.search(text) or PROIBIDO.search(ans): return None, "cita a fonte/figura"
    return {
        "disciplineId": item["disciplineId"], "subjectId": item["subjectId"],
        "subjectTag": item.get("topicName") or item["subjectName"],
        "author": "VetRank", "difficulty": diff, "year": YEAR,
        "textPt": text, "expectedAnswerPt": ans, "isPremium": True,
    }, None


# ── Revisão: outro modelo resolve às cegas e compara com o gabarito ───────────
def revisar(cli, item: dict, questoes: list[dict]) -> list[dict]:
    caminho, _ = contexto_item(item)
    blocos = []
    for i, q in enumerate(questoes):
        opts = "\n".join(f"{o['id']}) {o['textPt']}" for o in q["options"])
        blocos.append(f"### Questão {i}\n{q['textPt']}\n{opts}")
    system = ("Você é revisor sênior de bancas de Residência em Medicina Veterinária. Resolva cada questão de forma "
              "independente e aponte problemas. Responda apenas com JSON.")
    user = (
        f"Assunto esperado: {caminho}.\n\n" + "\n\n".join(blocos) +
        '\n\nPara cada questão retorne: {"revisoes":[{"i":0,"resposta":"C","confianca":0-100,'
        '"ambigua":false,"erroFactual":"" ,"foraDoAssunto":false}]}. '
        "'ambigua' = mais de uma alternativa defensável ou nenhuma correta. 'erroFactual' = descreva se houver dado/dose/conceito errado."
    )
    try:
        data = chamar_json(cli, MODELO_REVISOR, system, user, 0.1)
        revs = {int(r.get("i", -1)): r for r in data.get("revisoes", []) if isinstance(r, dict)}
    except Exception as e:
        print(f"   [Revisor] falhou ({e}) — lote vai para revisão manual")
        revs = {}
    return [revs.get(i, {}) for i in range(len(questoes))]


# ── Geração ───────────────────────────────────────────────────────────────────
def carregar(path):
    if os.path.exists(path):
        with open(path, encoding="utf-8") as f:
            return json.load(f)
    return []


def salvar(path, data):
    tmp = path + ".tmp"
    with open(tmp, "w", encoding="utf-8") as f:
        json.dump(data, f, ensure_ascii=False, indent=2)
    os.replace(tmp, path)


def gerar_objetivas(cli, item, chunks, alvo, modelos, revisao, pasta, dedup):
    p_ok, p_man, p_rej = (os.path.join(pasta, n) for n in ("objetivas.json", "revisar_manual.json", "rejeitadas.json"))
    aceitas, manual, rejeitadas = carregar(p_ok), carregar(p_man), carregar(p_rej)
    for q in aceitas + manual:
        dedup.add(q["textPt"])
    letras = Counter(q["correctOption"] for q in aceitas if q.get("modelId") != "M5")
    ciclo = [m for m in modelos for _ in range(PESOS_MODELO.get(m, 1))]
    random.shuffle(ciclo)
    uso_chunk = Counter()
    rodada, idx_modelo = 0, 0

    while len(aceitas) < alvo and rodada < 5:
        rodada += 1
        falta = alvo - len(aceitas)
        pedir = math.ceil(falta * 1.35)          # sobra para cobrir rejeições
        jobs = []
        while pedir > 0:
            modelo = ciclo[idx_modelo % len(ciclo)]; idx_modelo += 1
            n = min(MAX_POR_CHAMADA, pedir); pedir -= n
            ci = min(range(len(chunks)), key=lambda c: uso_chunk[c]); uso_chunk[ci] += 1
            difs = random.choices([d for d, _ in MIX_DIFICULDADE], [w for _, w in MIX_DIFICULDADE], k=n)
            gabs = []
            for _ in range(n):
                l = min(LETRAS, key=lambda x: (letras[x], random.random())); letras[l] += 1; gabs.append(l)
            jobs.append((ci, modelo, difs, gabs))
        print(f"\n   Rodada {rodada}: faltam {falta} → {len(jobs)} chamada(s)")

        candidatas = []
        def run(job):
            ci, modelo, difs, gabs = job
            data = chamar_json(cli, MODELO, system_geracao(item, chunks[ci]), user_objetivas(item, modelo, difs, gabs), 0.8)
            return modelo, data.get("questoes") or []
        with concurrent.futures.ThreadPoolExecutor(max_workers=WORKERS) as ex:
            for fut in concurrent.futures.as_completed([ex.submit(run, j) for j in jobs]):
                try:
                    modelo, qs = fut.result()
                except Exception as e:
                    print(f"   [FALHA] chamada: {e}"); continue
                for q in qs if isinstance(qs, list) else []:
                    norm, motivo = normalizar_objetiva(q, modelo, item)
                    if not norm:
                        rejeitadas.append({"motivo": motivo, "modelo": modelo, "bruto": q}); continue
                    if dedup.is_dup(norm["textPt"]):
                        rejeitadas.append({"motivo": "duplicada", "modelo": modelo, "textPt": norm["textPt"][:200]}); continue
                    dedup.add(norm["textPt"])
                    candidatas.append(norm)

        # recontagem real das letras (a IA nem sempre obedece a letra pedida)
        letras = Counter(q["correctOption"] for q in aceitas + candidatas if q.get("modelId") != "M5")

        if revisao and candidatas:
            print(f"   Revisando {len(candidatas)} questão(ões) às cegas…")
            lotes = [candidatas[i:i + 6] for i in range(0, len(candidatas), 6)]
            with concurrent.futures.ThreadPoolExecutor(max_workers=WORKERS) as ex:
                resultados = list(ex.map(lambda l: revisar(cli, item, l), lotes))
            for lote, revs in zip(lotes, resultados):
                for q, r in zip(lote, revs):
                    resp = str(r.get("resposta") or "").upper()[:1]
                    problemas = []
                    if not r: problemas.append("revisor não respondeu")
                    elif resp != q["correctOption"]: problemas.append(f"revisor marcou {resp or '?'} (gabarito {q['correctOption']})")
                    if r.get("ambigua"): problemas.append("ambígua")
                    if r.get("erroFactual"): problemas.append(f"erro factual: {r['erroFactual']}")
                    if r.get("foraDoAssunto"):
                        rejeitadas.append({"motivo": "fora do assunto", "textPt": q["textPt"][:200]}); continue
                    if problemas:
                        manual.append({**q, "_revisao": "; ".join(problemas)})
                    elif len(aceitas) < alvo:
                        aceitas.append(q)
        else:
            aceitas.extend(candidatas[: max(0, alvo - len(aceitas))])

        salvar(p_ok, aceitas); salvar(p_man, manual); salvar(p_rej, rejeitadas)
        print(f"   ✔ aceitas {len(aceitas)}/{alvo} | revisão manual {len(manual)} | rejeitadas {len(rejeitadas)}")

    return aceitas, manual, rejeitadas


def gerar_discursivas(cli, item, chunks, alvo, pasta, dedup):
    p = os.path.join(pasta, "discursivas.json")
    aceitas = carregar(p)
    for q in aceitas: dedup.add(q["textPt"])
    tentativas = 0
    while len(aceitas) < alvo and tentativas < 4:
        tentativas += 1
        falta = alvo - len(aceitas)
        jobs = [(i % len(chunks), min(3, falta - i * 3)) for i in range(math.ceil(falta / 3))]
        with concurrent.futures.ThreadPoolExecutor(max_workers=WORKERS) as ex:
            futs = [ex.submit(chamar_json, cli, MODELO, system_geracao(item, chunks[ci]), user_discursivas(item, n), 0.7) for ci, n in jobs if n > 0]
            for fut in concurrent.futures.as_completed(futs):
                try: qs = fut.result().get("questoes") or []
                except Exception as e: print(f"   [FALHA] {e}"); continue
                for q in qs:
                    norm, _ = normalizar_discursiva(q, item)
                    if norm and not dedup.is_dup(norm["textPt"]) and len(aceitas) < alvo:
                        dedup.add(norm["textPt"]); aceitas.append(norm)
        salvar(p, aceitas)
        print(f"   ✔ discursivas {len(aceitas)}/{alvo}")
    return aceitas


# ── Comandos ──────────────────────────────────────────────────────────────────
def ler_plano(path):
    with open(path, encoding="utf-8") as f:
        plano = json.load(f)
    itens = plano.get("itens") if isinstance(plano, dict) else None
    if not itens:
        sys.exit("ERRO: arquivo não parece um plano exportado do admin (campo 'itens' ausente).")
    return itens


def achar_item(itens, ref):
    for it in itens:
        if str(it.get("rank")) == str(ref) or it.get("key") == ref:
            return it
    sys.exit(f"ERRO: item '{ref}' não está no plano. Use o número da coluna # ou a chave (ex.: 30007:51).")


def cmd_plano(a):
    itens = ler_plano(a.plano)
    print(f"\n{'#':>3}  {'Assunto':<48} {'Tem':>4} {'Alunos':>6} {'Ped.':>4}  Gerar")
    print("-" * 90)
    for it in itens:
        nome = (it.get("topicName") or it["subjectName"])[:46]
        rec = it["recomendado"]
        print(f"{it['rank']:>3}  {nome:<48} {it['supply']['objetivas']:>4} {it['demand']['alunos90d']:>6} "
              f"{len(it.get('requestIds', [])):>4}  {rec['objetivas']} obj / {rec['discursivas']} disc")
        print(f"     {it['disciplineName']} › {it['subjectName']}  —  {' · '.join(it.get('motivos', []))[:120]}")
    print("\nPróximo passo: python scripts/gerador_questoes.py gerar <plano> <#> --pdf <livro.pdf> --paginas <ini-fim>")


def cmd_gerar(a):
    item = achar_item(ler_plano(a.plano), a.item)
    alvo_obj = a.objetivas if a.objetivas is not None else item["recomendado"]["objetivas"]
    alvo_disc = a.discursivas if a.discursivas is not None else item["recomendado"]["discursivas"]
    modelos = [m.strip().upper() for m in a.modelos.split(",")] if a.modelos else list(TIPO_POR_MODELO)
    if any(m not in TIPO_POR_MODELO for m in modelos):
        sys.exit("ERRO: --modelos aceita M1..M8")

    nome = item.get("topicName") or item["subjectName"]
    pasta = os.path.join(SAIDA_BASE, f"{slugify(item['subjectName'])}__{slugify(nome)}")
    os.makedirs(pasta, exist_ok=True)
    print("=" * 70)
    print(f"#{item['rank']}  {item['disciplineName']} › {item['subjectName']}" + (f" › {item['topicName']}" if item.get("topicName") else ""))
    print(f"   Meta: {alvo_obj} objetivas + {alvo_disc} discursivas | modelos {','.join(modelos)} | revisão {'NÃO' if a.sem_revisao else 'sim'}")
    print(f"   Saída: {pasta}")

    texto = extrair_texto(a.pdf, a.paginas)
    chunks = [texto[i:i + CHUNK_SIZE] for i in range(0, len(texto), CHUNK_SIZE)]
    existentes = item.get("existentes") or {}
    cli = client()
    t0 = time.time()

    aceitas, manual, rejeitadas = [], [], []
    if alvo_obj > 0:
        aceitas, manual, rejeitadas = gerar_objetivas(cli, item, chunks, alvo_obj, modelos, not a.sem_revisao, pasta,
                                                      Dedup(existentes.get("objetivas", [])))
    disc = []
    if alvo_disc > 0:
        print("\n   Discursivas…")
        disc = gerar_discursivas(cli, item, chunks, alvo_disc, pasta, Dedup(existentes.get("discursivas", [])))

    motivos = Counter(r.get("motivo") for r in rejeitadas)
    rel = [
        f"# {nome}", "",
        f"- Caminho: {item['disciplineName']} › {item['subjectName']}" + (f" › {item['topicName']} (topicId {item['topicId']})" if item.get("topicId") else ""),
        f"- Fonte: {', '.join(os.path.basename(p) for p in a.pdf)} | páginas: {a.paginas or 'todas'}",
        f"- Gerado em {datetime.now():%d/%m/%Y %H:%M} em {int(time.time() - t0)}s | modelo {MODELO} / revisor {MODELO_REVISOR}",
        f"- Tokens: entrada {TOKENS['entrada']:,} (cache {TOKENS['cache']:,}) | saída {TOKENS['saida']:,}", "",
        "## Resultado",
        f"- **objetivas.json**: {len(aceitas)} prontas para importar",
        f"- **revisar_manual.json**: {len(manual)} (revisor discordou ou viu problema — campo `_revisao`)",
        f"- **discursivas.json**: {len(disc)}",
        f"- rejeitadas: {len(rejeitadas)} — " + ", ".join(f"{k}: {v}" for k, v in motivos.most_common()), "",
        "## Distribuição (objetivas aceitas)",
        "- Modelos: " + ", ".join(f"{k} {v}" for k, v in sorted(Counter(q['modelId'] for q in aceitas).items())),
        "- Gabaritos: " + ", ".join(f"{k} {v}" for k, v in sorted(Counter(q['correctOption'] for q in aceitas).items())),
        "- Dificuldade: " + ", ".join(f"{k} {v}" for k, v in Counter(q['difficulty'] for q in aceitas).most_common()), "",
        "## Próximos passos",
        "1. Admin → Importar → JSON → `objetivas.json` (múltipla escolha) e `discursivas.json` (discursivas).",
        "2. Abra `revisar_manual.json`, corrija/apague o que precisar e rode `python scripts/gerador_questoes.py validar revisar_manual.json`.",
        f"3. Admin → Pedidos: marque como concluídos os pedidos {item.get('requestIds') or '—'}.",
    ]
    with open(os.path.join(pasta, "relatorio.md"), "w", encoding="utf-8") as f:
        f.write("\n".join(rel) + "\n")
    print("\n" + "\n".join(rel[7:]))


def cmd_validar(a):
    dados = carregar(a.arquivo)
    if not isinstance(dados, list):
        sys.exit("ERRO: esperado um array JSON de questões.")
    ok, ruins = [], []
    for q in dados:
        q = {k: v for k, v in q.items() if not k.startswith("_")}
        item = {"disciplineId": q.get("disciplineId"), "subjectId": q.get("subjectId"), "topicId": q.get("topicId"),
                "subjectName": q.get("subjectTag") or "", "topicName": q.get("subjectTag")}
        norm, motivo = normalizar_objetiva(q, q.get("modelId") or "M1", item)
        (ok if norm else ruins).append(norm or {"motivo": motivo, "textPt": str(q.get("textPt"))[:150]})
    saida = re.sub(r"\.json$", "", a.arquivo) + "_validado.json"
    salvar(saida, ok)
    print(f"{len(ok)} válidas → {saida}")
    for r in ruins:
        print(f"   ✖ {r['motivo']}: {r['textPt']}")


def main():
    ap = argparse.ArgumentParser(description="Gerador de questões VetRank guiado pelo Plano de conteúdo do admin")
    sub = ap.add_subparsers(dest="cmd", required=True)
    p1 = sub.add_parser("plano", help="lista as prioridades do plano exportado")
    p1.add_argument("plano")
    p2 = sub.add_parser("gerar", help="gera questões para um item do plano a partir de PDF(s)")
    p2.add_argument("plano"); p2.add_argument("item", help="número (#) ou chave do item")
    p2.add_argument("--pdf", action="append", required=True)
    p2.add_argument("--paginas")
    p2.add_argument("--objetivas", type=int); p2.add_argument("--discursivas", type=int)
    p2.add_argument("--modelos")
    p2.add_argument("--sem-revisao", action="store_true")
    p3 = sub.add_parser("validar", help="revalida um JSON editado à mão (ex.: revisar_manual.json)")
    p3.add_argument("arquivo")
    a = ap.parse_args()
    {"plano": cmd_plano, "gerar": cmd_gerar, "validar": cmd_validar}[a.cmd](a)


if __name__ == "__main__":
    main()
