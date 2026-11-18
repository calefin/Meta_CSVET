import json
import unicodedata
import re

# --- 1. TABELA VERDADE DOS 16 ODUS MATRIZES ---
# 1 = Aberto (I), 2 = Fechado (II)
PATTERNS = {
    'ogbe':     [1, 1, 1, 1],
    'oyeku':    [2, 2, 2, 2],
    'iwori':    [2, 1, 1, 2],
    'odi':      [1, 2, 2, 1],
    'irosun':   [1, 1, 2, 2],
    'owonrin':  [2, 2, 1, 1],
    'obara':    [1, 2, 2, 2],
    'okanran':  [2, 2, 2, 1],
    'ogunda':   [1, 1, 1, 2],
    'osa':      [2, 1, 1, 1],
    'ika':      [2, 1, 2, 2],
    'oturupon': [2, 2, 1, 2],
    'otura':    [1, 2, 1, 1],
    'irete':    [1, 1, 2, 1],
    'ose':      [1, 2, 1, 2],
    'ofun':     [2, 1, 2, 1],
}

# Sinônimos para garantir que o script entenda variações
ALIASES = {
    'ejiogbe': 'ogbe',
    'eji': 'ogbe',
    'oshe': 'ose',
    'ofun': 'ofun', 
    'okana': 'okanran',
    'owanrin': 'owonrin',
    'otura': 'otura',
    'irete': 'irete',
    'ologbon': 'oturupon', 
    'otoropon': 'oturupon',
    'oturopon': 'oturupon', # <--- CORREÇÃO AQUI (Adicionado)
    'ogbe': 'ogbe',
    'oyeku': 'oyeku',
    'iwori': 'iwori',
    'odi': 'odi',
    'irosun': 'irosun',
    'owonrin': 'owonrin',
    'obara': 'obara',
    'okanran': 'okanran',
    'ogunda': 'ogunda',
    'osa': 'osa',
    'ika': 'ika',
    'ose': 'ose'
}

def normalize_name(text):
    """Remove acentos e deixa minúsculo para busca."""
    if not text: return ""
    # Normaliza unicode (NFD) e remove caracteres não-ASCII (acentos)
    text = unicodedata.normalize('NFD', text).encode('ascii', 'ignore').decode('utf-8')
    return text.lower().strip()

def get_bits(name_key):
    """Retorna os bits baseado no nome normalizado."""
    key = name_key.strip()
    
    # 1. Tenta direto na tabela principal
    if key in PATTERNS:
        return PATTERNS[key]
    
    # 2. Tenta nos apelidos
    if key in ALIASES:
        target = ALIASES[key]
        return PATTERNS[target]
    
    return None

def fix_json(input_file, output_file):
    try:
        with open(input_file, 'r', encoding='utf-8') as f:
            data = json.load(f)
    except FileNotFoundError:
        print(f"Erro: Arquivo '{input_file}' não encontrado.")
        return

    count = 0
    errors = []

    print(f"Processando {len(data)} Odus...")

    for odu in data:
        # Pega o nome de exibição (ex: "Ògbè Òtúrópòn")
        raw_name = odu.get('nome', '')
        
        # Normaliza (ex: "ogbe oturopon")
        norm_name = normalize_name(raw_name)
        
        # Divide as partes do nome
        parts = re.split(r'\s+', norm_name)
        
        right_key = None
        left_key = None

        # LÓGICA DE IDENTIFICAÇÃO
        if 'meji' in parts or len(parts) == 1 or (len(parts) == 2 and parts[0] == 'eji'):
            # É um Meji
            clean_parts = [p for p in parts if p not in ['meji', 'eji']]
            if clean_parts:
                root = clean_parts[0]
                right_key = root
                left_key = root
        elif len(parts) >= 2:
            # É um Odu Composto
            # O Primeiro nome é a Perna Direita, o Segundo é a Esquerda
            right_key = parts[0]
            left_key = parts[1]
        
        # Busca os bits
        bits_right = get_bits(right_key) if right_key else None
        bits_left = get_bits(left_key) if left_key else None

        if bits_right and bits_left:
            # Atualiza o JSON
            odu['right'] = bits_right
            odu['left'] = bits_left
            odu['rightBits'] = bits_right
            odu['leftBits'] = bits_left
            
            # --- Correção Opcional de ID ---
            # Descomente abaixo se quiser padronizar os IDs também (ex: "ogbe_oturupon")
            # odu['id'] = f"{right_key}_{left_key}" 
            
            count += 1
        else:
            errors.append(f"Não reconheci: {raw_name} (Chaves: {right_key}, {left_key})")

    # Salva o novo arquivo
    with open(output_file, 'w', encoding='utf-8') as f:
        json.dump(data, f, indent=2, ensure_ascii=False)

    print(f"\nConcluído! {count} Odus corrigidos.")
    print(f"Salvo em: {output_file}")
    
    if errors:
        print(f"\nAVISO: {len(errors)} erros encontrados:")
        for e in errors:
            print(f" - {e}")

# --- EXECUÇÃO ---
fix_json('odu_traduzido_verificacao.json', 'odus_corrigidos.json')