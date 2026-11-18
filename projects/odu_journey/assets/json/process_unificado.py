import json
import os
import time
from deep_translator import GoogleTranslator

# Arquivos
INPUT_FILE = "odu_unificado.json"
OUTPUT_FILE = "odu_data.dart"

# Limite de segurança (Google aceita 5000, vamos usar 4000 para garantir)
CHUNK_SIZE = 4000 

def translate_chunk(text, target_code):
    """Traduz um pedaço pequeno de texto."""
    try:
        # Pequena pausa para não bloquear a API
        time.sleep(0.5) 
        return GoogleTranslator(source='pt', target=target_code).translate(text)
    except Exception as e:
        print(f"      [ERRO NO PEDACINHO] {e}")
        return text

def smart_translate(text, target_lang):
    """
    Função inteligente que divide textos longos, 
    traduz partes e reagrupa.
    """
    if not text:
        return ""
    
    # Mapeia códigos de idioma
    lang_code = 'en' if target_lang == 'ingles' else 'es'
    
    # Se for pequeno, traduz direto
    if len(text) < CHUNK_SIZE:
        return translate_chunk(text, lang_code)

    # Se for grande, precisamos fatiar
    print(f"      [!] Texto longo detectado ({len(text)} caracteres). Fatiando...")
    
    parts = []
    current_part = ""
    
    # Divide por frases (ponto final) para tentar manter contexto
    sentences = text.split('. ')
    
    for sentence in sentences:
        # Se adicionar essa frase não estourar o limite, adiciona
        if len(current_part) + len(sentence) < CHUNK_SIZE:
            current_part += sentence + ". "
        else:
            # Se estourar, traduz o que acumulou até agora
            translated_part = translate_chunk(current_part, lang_code)
            parts.append(translated_part)
            # Começa novo bloco com a frase atual
            current_part = sentence + ". "
            
    # Traduz o que sobrou no último bloco
    if current_part:
        parts.append(translate_chunk(current_part, lang_code))
        
    return " ".join(parts)

def processar_json():
    if not os.path.exists(INPUT_FILE):
        print(f"ERRO: Arquivo {INPUT_FILE} não encontrado.")
        return

    print(f"Lendo {INPUT_FILE}...")
    with open(INPUT_FILE, "r", encoding="utf-8") as f:
        lista_odus = json.load(f)

    nova_lista = []
    total = len(lista_odus)

    print(f"Iniciando processamento de {total} Odùs com sistema anti-limite...\n")

    for i, item in enumerate(lista_odus):
        nome = item.get("nome", "Desconhecido")
        print(f"[{i+1}/{total}] Processando: {nome}...")

        # 1. PEGAR DADOS ORIGINAIS (PT)
        desc_pt = item.get("descricao", "")
        ire_pt  = item.get("ire", "")
        ibi_pt  = item.get("ibi", "")
        
        # 2. PEGAR VERSOS EXISTENTES
        liturgico_original = item.get("conteudo_liturgico", {})
        verso_yo = liturgico_original.get("yoruba", "")
        verso_pt = liturgico_original.get("portugues", item.get("verso", ""))
        verso_en = liturgico_original.get("ingles", "")
        verso_es = liturgico_original.get("espanhol", "")

        # 3. TRADUÇÃO INTELIGENTE (COM FATIAMENTO)
        
        # --- Inglês ---
        desc_en = smart_translate(desc_pt, 'ingles')
        ire_en  = smart_translate(ire_pt, 'ingles')
        ibi_en  = smart_translate(ibi_pt, 'ingles')
        if not verso_en: verso_en = smart_translate(verso_pt, 'ingles')

        # --- Espanhol ---
        desc_es = smart_translate(desc_pt, 'espanhol')
        ire_es  = smart_translate(ire_pt, 'espanhol')
        ibi_es  = smart_translate(ibi_pt, 'espanhol')
        if not verso_es: verso_es = smart_translate(verso_pt, 'espanhol')

        # 4. MONTAR OBJETO
        novo_odu = {
            "id": item.get("id"),
            "nome": nome,
            "right": item.get("right"),
            "left": item.get("left"),
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
            # Fallbacks
            "descricao": desc_pt,
            "ire": ire_pt,
            "ibi": ibi_pt,
            "verso": verso_pt
        }
        
        nova_lista.append(novo_odu)

    # 5. GERAR ARQUIVO
    print("\nGerando odu_data.dart...")
    json_str = json.dumps(nova_lista, indent=2, ensure_ascii=False)
    json_str = json_str.replace("$", "\\$")

    conteudo_dart = f"""
// ARQUIVO GERADO AUTOMATICAMENTE VIA PYTHON V2
// FONTE: {INPUT_FILE}

const String oduJsonString = r'''
{json_str}
''';
"""

    with open(OUTPUT_FILE, "w", encoding="utf-8") as f:
        f.write(conteudo_dart)

    print(f"SUCESSO! Arquivo '{OUTPUT_FILE}' gerado.")

if __name__ == "__main__":
    processar_json()