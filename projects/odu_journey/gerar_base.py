import google.generativeai as genai
import json
import time
import os
import re

# ==============================================================================
# 1. CONFIGURAÇÕES
# ==============================================================================

API_KEY = "AIzaSyAQv4FDtHRWJnREp7OCFo3xIaY6kUHHPcE"  # <--- COLE SUA CHAVE AQUI

# Caminhos dos arquivos
CAMINHO_ENTRADA = r"D:\odu_journey\assets\json\odu_256.json"
CAMINHO_SAIDA = r"D:\odu_journey\assets\json\odu_256_PROCESSADO.json"

genai.configure(api_key=API_KEY)

# --- AQUI ESTAVA O PROBLEMA: USANDO O MODELO DISPONÍVEL NA SUA LISTA ---
NOME_MODELO = 'models/gemini-2.0-flash' 

generation_config = {
    "temperature": 0.5,
    "response_mime_type": "application/json"
}

print(f"Inicializando modelo: {NOME_MODELO}...")
model = genai.GenerativeModel(NOME_MODELO, generation_config=generation_config)

# ==============================================================================
# 2. CARREGAMENTO
# ==============================================================================

print(f"Lendo arquivo: {CAMINHO_ENTRADA}")

if not os.path.exists(CAMINHO_ENTRADA):
    print("ERRO: Arquivo de entrada não encontrado.")
    exit()

with open(CAMINHO_ENTRADA, 'r', encoding='utf-8') as f:
    dados_originais = json.load(f)

print(f"Total de Odùs para processar: {len(dados_originais)}")
lista_final = []

# ==============================================================================
# 3. PROCESSAMENTO
# ==============================================================================

def gerar_id(nome):
    s = nome.lower()
    s = s.replace("ò", "o").replace("ó", "o").replace("ọ", "o")
    s = s.replace("è", "e").replace("é", "e").replace("ẹ", "e")
    s = s.replace("ì", "i").replace("í", "i")
    s = s.replace("à", "a").replace("á", "a")
    s = s.replace("ù", "u").replace("ú", "u")
    s = s.replace("ṣ", "s").replace("n’", "n")
    s = re.sub(r'[^a-z0-9]', '_', s)
    return s.strip('_')

for i, item in enumerate(dados_originais):
    
    nome_odu = item.get('nome', f"Odu #{i}")
    id_odu = gerar_id(nome_odu)
    
    right = item.get('right', [0,0,0,0])
    left = item.get('left', [0,0,0,0])
    binary_str = "".join(str(bit) for bit in right + left)

    descricao_crua = item.get('descricao', '')
    ire_cru = item.get('ire', '')
    ibi_cru = item.get('ibi', '')
    
    print(f"[{i+1}/{len(dados_originais)}] Processando: {nome_odu}...")

    # PROMPT COM AS REGRAS DE IRE/IBI
    prompt = f"""
    Você é um Babalawo Sênior.
    
    DADOS ORIGINAIS:
    Nome: {nome_odu}
    Texto Base: {descricao_crua[:2500]} 
    Ire Original: {ire_cru}
    Ibi Original: {ibi_cru}
    
    TAREFA:
    1. Resumo: Reescreva a descrição em 2 ou 3 frases claras e espirituais. Evite plágio.
    2. Verso: Crie um verso poético curto (4 linhas).
    3. IRE e IBI:
       - Remova o NOME DO ODU do texto.
       - MANTENHA a palavra "Iré:" ou "Ibi:" (ou a tradução correta) no início.
       - Simplifique para frases curtas.
       
       EXEMPLO:
       PT -> "Iré: Vitória, sucesso e dinheiro."
       EN -> "Ire: Victory, success and money."
       ES -> "Iré: Victoria, éxito y dinero."

    4. Tradução: Português (pt), Inglês (en) e Espanhol (es).

    RETORNE APENAS ESTE JSON:
    {{
        "id": "{id_odu}",
        "binary": "{binary_str}",
        "content": {{
            "pt": {{ "nome": "{nome_odu}", "descricao": "...", "verso": "...", "ire": "...", "ibi": "..." }},
            "en": {{ "nome": "{nome_odu}", "description": "...", "verse": "...", "ire": "...", "ibi": "..." }},
            "es": {{ "nome": "{nome_odu}", "descripcion": "...", "verso": "...", "ire": "...", "ibi": "..." }}
        }}
    }}
    """

    try:
        response = model.generate_content(prompt)
        texto_limpo = response.text.strip()
        if texto_limpo.startswith("```json"):
            texto_limpo = texto_limpo[7:-3]
        
        odu_processado = json.loads(texto_limpo)
        lista_final.append(odu_processado)
        
        # O modelo 2.0 é rápido, 1.5s deve bastar
        time.sleep(1.5) 

    except Exception as e:
        print(f"❌ ERRO no {nome_odu}: {e}")
        lista_final.append({
            "id": id_odu,
            "binary": binary_str,
            "content": {"pt": {"nome": nome_odu, "descricao": "Erro IA", "verso": "", "ire": "", "ibi": ""}}
        })

# ==============================================================================
# 4. SALVAMENTO
# ==============================================================================

with open(CAMINHO_SAIDA, 'w', encoding='utf-8') as f:
    json.dump(lista_final, f, indent=2, ensure_ascii=False)

print(f"\n✅ SUCESSO! Verifique: {CAMINHO_SAIDA}")