import json
import os
import time
from deep_translator import GoogleTranslator

# Configurações
INPUT_FILE = "odu_unificado_pronto.json"
OUTPUT_FILE = "odu_traduzido_verificacao.json"

# Limite de segurança para fatiar textos (Google aceita 5000)
CHUNK_SIZE = 3500 

def translate_chunk(text, target_code):
    """Traduz um pedaço pequeno com pausa para não bloquear."""
    try:
        time.sleep(0.5) # Pausa para respirar
        return GoogleTranslator(source='pt', target=target_code).translate(text)
    except Exception as e:
        print(f"      [ERRO API] {e}")
        return text

def smart_translate(text, target_lang):
    """Divide textos longos, traduz e remonta."""
    if not text: return ""
    
    # Mapeia código para a lib
    lang_code = 'en' if target_lang == 'ingles' else 'es'
    
    # Se for curto, vai direto
    if len(text) < CHUNK_SIZE:
        return translate_chunk(text, lang_code)

    # Se for longo, fatia
    # print(f"      [i] Texto longo ({len(text)} chars). Fatiando...")
    parts = []
    current_part = ""
    
    sentences = text.split('. ')
    for sentence in sentences:
        if len(current_part) + len(sentence) < CHUNK_SIZE:
            current_part += sentence + ". "
        else:
            parts.append(translate_chunk(current_part, lang_code))
            current_part = sentence + ". "
            
    if current_part:
        parts.append(translate_chunk(current_part, lang_code))
        
    return " ".join(parts)

def processar():
    if not os.path.exists(INPUT_FILE):
        print(f"ERRO: {INPUT_FILE} não encontrado.")
        return

    print(f"Lendo {INPUT_FILE}...")
    with open(INPUT_FILE, "r", encoding="utf-8") as f:
        data = json.load(f)

    nova_lista = []
    total = len(data)
    print(f"Iniciando tradução de {total} Odùs para JSON...")

    for i, item in enumerate(data):
        nome = item.get("nome", "Desconhecido")
        print(f"[{i+1}/{total}] Traduzindo: {nome}...")

        # 1. Dados Originais (PT)
        desc_pt = item.get("descricao", "")
        ire_pt  = item.get("ire", "")
        ibi_pt  = item.get("ibi", "")
        
        # 2. Versos Existentes (Preserva o que já tem)
        liturgico_old = item.get("conteudo_liturgico", {})
        verso_yo = liturgico_old.get("yoruba", "")
        verso_pt = liturgico_old.get("portugues", item.get("verso", ""))
        
        # Se já tiver tradução manual no JSON, usa ela. Se não, deixa vazio para traduzir agora.
        verso_en_existente = liturgico_old.get("ingles", "")
        verso_es_existente = liturgico_old.get("espanhol", "")

        # 3. Tradução para INGLÊS
        desc_en = smart_translate(desc_pt, 'ingles')
        ire_en  = smart_translate(ire_pt, 'ingles')
        ibi_en  = smart_translate(ibi_pt, 'ingles')
        # Verso: Usa o existente se houver, senão traduz do PT
        verso_en = verso_en_existente if verso_en_existente else smart_translate(verso_pt, 'ingles')

        # 4. Tradução para ESPANHOL
        desc_es = smart_translate(desc_pt, 'espanhol')
        ire_es  = smart_translate(ire_pt, 'espanhol')
        ibi_es  = smart_translate(ibi_pt, 'espanhol')
        # Verso: Usa o existente se houver, senão traduz do PT
        verso_es = verso_es_existente if verso_es_existente else smart_translate(verso_pt, 'espanhol')

        # 5. Monta o Objeto Rico
        item_traduzido = {
            "id": item.get("id"),
            "nome": nome,
            "right": item.get("right"),
            "left": item.get("left"),
            
            # Aqui está a estrutura organizada
            "conteudo_liturgico": {
                "yoruba": verso_yo,
                "portugues": {
                    "descricao": desc_pt,
                    "ire": ire_pt,
                    "ibi": ibi_pt,
                    "verso": verso_pt
                },
                "ingles": {
                    "descricao": desc_en,
                    "ire": ire_en,
                    "ibi": ibi_en,
                    "verso": verso_en
                },
                "espanhol": {
                    "descricao": desc_es,
                    "ire": ire_es,
                    "ibi": ibi_es,
                    "verso": verso_es
                }
            },
            
            # Mantemos campos raiz como fallback/legado
            "descricao": desc_pt,
            "ire": ire_pt,
            "ibi": ibi_pt,
            "verso": verso_pt
        }
        
        nova_lista.append(item_traduzido)

    # Salva JSON
    print(f"\nSalvando {OUTPUT_FILE}...")
    with open(OUTPUT_FILE, "w", encoding="utf-8") as f:
        json.dump(nova_lista, f, indent=2, ensure_ascii=False)

    print("--- CONCLUÍDO ---")
    print(f"Verifique o arquivo '{OUTPUT_FILE}'.")

if __name__ == "__main__":
    processar()