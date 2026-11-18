import json
import os

INPUT_FILE = "odu_unificado.json"
OUTPUT_FILE = "odu_unificado_pronto.json"

def fix_text_keep_name(text):
    if not text: return ""
    
    # 1. Remove apenas espaços em branco no começo/fim
    text = text.strip()
    
    # --- AQUI ESTAVA O CÓDIGO QUE REMOVIA O NOME. FOI APAGADO. ---
    # O texto permanece com o prefixo (ex: "Ogbe Meji Ire: ...")

    # 2. CORREÇÃO DE FINAL CORTADO (CIRURGIA)
    # Lista de "rabos" para cortar se o texto morrer no meio da frase
    dangling_endings = [
        ' e', ' de', ' da', ' do', ' dos', ' das', 
        ' com', ' para', ' em', ' que', ' ou', ' a', ' o',
        ' no', ' na', ' nos', ' nas', ' por', ' mas'
    ]

    # Remove pontuação solta inicial
    text = text.rstrip(',;-: ')

    # Loop para limpar finais cortados recursivamente (ex: "de e")
    cleaned = False
    while not cleaned:
        cleaned = True
        for end in dangling_endings:
            if text.lower().endswith(end):
                # Corta o final
                text = text[:-len(end)].strip()
                # Limpa pontuação novamente
                text = text.rstrip(',;-: ')
                cleaned = False # Repete para garantir
                break
    
    # 3. Adiciona ponto final se não tiver (e se o texto não for vazio)
    if len(text) > 1 and text[-1] not in ['.', '!', '?', '"', "'"]:
        text += "."

    return text

def processar():
    if not os.path.exists(INPUT_FILE):
        print(f"ERRO: {INPUT_FILE} não encontrado.")
        return

    print(f"Lendo {INPUT_FILE}...")
    with open(INPUT_FILE, "r", encoding="utf-8") as f:
        data = json.load(f)

    count = 0
    total = len(data)

    print("Aplicando correções de pontuação e corte (Mantendo nomes)...")

    for item in data:
        # Pega original
        ire = item.get("ire", "")
        ibi = item.get("ibi", "")
        
        # Corrige
        new_ire = fix_text_keep_name(ire)
        new_ibi = fix_text_keep_name(ibi)

        # Atualiza
        item["ire"] = new_ire
        item["ibi"] = new_ibi
        count += 1

    # Salva
    with open(OUTPUT_FILE, "w", encoding="utf-8") as f:
        json.dump(data, f, indent=2, ensure_ascii=False)

    print(f"\n--- SUCESSO ---")
    print(f"Processados: {total}")
    print(f"Arquivo salvo: {OUTPUT_FILE}")
    print("Este arquivo mantém os nomes dos Odus e corrigiu os finais cortados.")
    print("Agora use este arquivo no script de tradução!")

if __name__ == "__main__":
    processar()