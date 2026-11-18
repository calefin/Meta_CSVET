import json
import unicodedata
import os

# ================= CONFIGURAÇÃO =================
ARQUIVO_BASE = 'odu_256.json'           # Seu arquivo original com matemática/descrição
ARQUIVO_VERSOS = 'versos_brutos.json' # Seu arquivo de versos (já corrigido as barras)
ARQUIVO_FINAL = 'odus_unificado.json' # O arquivo final
# ================================================

def normalizar_texto(texto):
    """
    Remove acentos e padroniza grafias específicas para criar um ID compatível.
    """
    if not texto:
        return ""
    
    # 1. Normaliza unicode (separa o acento da letra)
    nfkd_form = unicodedata.normalize('NFKD', texto)
    
    # 2. Remove acentos e converte para minúsculo
    texto_sem_acento = "".join([c for c in nfkd_form if not unicodedata.combining(c)])
    texto_limpo = texto_sem_acento.lower().strip()

    # 3. CORREÇÃO DE GRAFIA (O Pulo do Gato)
    # Se o original estiver escrito "oturopon", forçamos para "oturupon"
    # para bater com o ID do arquivo de versos.
    if "oturopon" in texto_limpo:
        texto_limpo = texto_limpo.replace("oturopon", "oturupon")
    
    return texto_limpo

def gerar_id_do_odu(nome_direita, nome_esquerda):
    """
    Gera o ID baseado nos nomes das pernas.
    """
    direita = normalizar_texto(nome_direita)
    esquerda = normalizar_texto(nome_esquerda)
    
    if direita == esquerda:
        return f"{direita}_meji"
    else:
        return f"{direita}_{esquerda}"

def carregar_json(caminho):
    if not os.path.exists(caminho):
        print(f"ERRO: Arquivo '{caminho}' não encontrado.")
        return None
    try:
        with open(caminho, 'r', encoding='utf-8') as f:
            return json.load(f)
    except json.JSONDecodeError as e:
        print(f"ERRO CRÍTICO ao ler {caminho}: {e}")
        return None

def main():
    print(f"--- INICIANDO A FUSÃO (Corrigindo Oturopon -> Oturupon) ---")

    base_data = carregar_json(ARQUIVO_BASE)
    versos_data = carregar_json(ARQUIVO_VERSOS)

    if not base_data or not versos_data:
        return

    # Criar dicionário de versos
    mapa_versos = {item['id']: item for item in versos_data if 'id' in item}
    
    # Aplicar a correção manual do Oturupon Iwori (caso não esteja no arquivo)
    if 'oturupon_iwori' not in mapa_versos:
        mapa_versos['oturupon_iwori'] = {
            "id": "oturupon_iwori",
            "verse_yoruba": "Oturu ru ni awo aye\nIwori ni awo ode orun...",
            "verse_english": "Oturu is the priest of Aye...",
            "verse_portuguese": "Oturu é o sacerdote de Aye...",
            "verse_spanish": "Oturu es el sacerdote de Aye..."
        }

    contador_sucesso = 0
    contador_falha = 0
    lista_faltantes = []

    print("Processando...")

    for odu in base_data:
        # Pega os nomes originais (ex: "Òtúrópòn")
        r_name = odu.get('rightName', '')
        l_name = odu.get('leftName', '')
        
        # Gera o ID normalizado (ex: transforma "Òtúrópòn" em "oturupon")
        id_gerado = gerar_id_do_odu(r_name, l_name)
        
        # Salva o ID no objeto final
        odu['id'] = id_gerado

        # Tenta encontrar o verso
        if id_gerado in mapa_versos:
            verso_match = mapa_versos[id_gerado]
            
            # Preenche os dados
            odu['verso'] = verso_match.get('verse_portuguese', '')
            odu['conteudo_liturgico'] = {
                'yoruba': verso_match.get('verse_yoruba', ''),
                'ingles': verso_match.get('verse_english', ''),
                'portugues': verso_match.get('verse_portuguese', ''),
                'espanhol': verso_match.get('verse_spanish', '')
            }
            contador_sucesso += 1
        else:
            contador_falha += 1
            lista_faltantes.append(f"{id_gerado} (Orig: {r_name}/{l_name})")

    # Salvar
    with open(ARQUIVO_FINAL, 'w', encoding='utf-8') as f:
        json.dump(base_data, f, indent=2, ensure_ascii=False)

    print("\n" + "="*30)
    print("       RESULTADO FINAL")
    print("="*30)
    print(f"Total processado: {len(base_data)}")
    print(f"Sucesso (com versos): {contador_sucesso}")
    print(f"Falha (sem versos):   {contador_falha}")
    
    if lista_faltantes:
        print("\nATENÇÃO: Estes Odus não encontraram correspondência:")
        for item in lista_faltantes:
            print(f" - {item}")
    else:
        print("\nPERFEITO! Todos os 256 Odus foram unificados.")

    print(f"\nArquivo gerado: {ARQUIVO_FINAL}")

if __name__ == "__main__":
    main()