import json
import os

# Arquivo que você verificou e está pronto
INPUT_FILE = 'odu_traduzido_verificacao.json'
# Arquivo de saída para o Flutter
OUTPUT_FILE = 'odu_data.dart'

def gerar_dart_final():
    
    # 1. Verificação de Segurança
    if not os.path.exists(INPUT_FILE):
        print(f"ERRO CRÍTICO: Não encontrei o arquivo '{INPUT_FILE}'.")
        print("Verifique se o nome está correto na pasta.")
        return

    print(f"Lendo dados verificados de {INPUT_FILE}...")
    
    try:
        with open(INPUT_FILE, 'r', encoding='utf-8') as f:
            data = json.load(f)
    except json.JSONDecodeError as e:
        print(f"ERRO: O arquivo JSON parece estar corrompido ou mal formatado.")
        print(f"Detalhe: {e}")
        return

    # 2. Validação Rápida (Opcional, só pra garantir que não está vazio)
    total_odus = len(data)
    print(f"Carregados {total_odus} Odùs.")
    
    if total_odus > 0:
        primeiro = data[0]
        # Verifica se tem a estrutura nova
        if "conteudo_liturgico" not in primeiro:
            print("AVISO: O JSON não parece ter a chave 'conteudo_liturgico'.")
            print("Certifique-se que está usando o arquivo gerado pelo script de tradução.")

    # 3. Conversão para String do Dart
    # ensure_ascii=False garante que acentos e caracteres yorubá fiquem legíveis
    json_string = json.dumps(data, ensure_ascii=False, indent=2)

    # 4. Proteção para o Dart
    # O Dart tenta interpretar $ como variável dentro de strings. Precisamos escapar.
    json_string = json_string.replace('$', '\\$')

    # 5. Montagem do Arquivo
    dart_content = f"""
// ARQUIVO DE DADOS DOS ODUS
// Gerado a partir de: {INPUT_FILE}
// Total de registros: {total_odus}

const String oduJsonString = r'''
{json_string}
''';
"""

    # 6. Salvar
    with open(OUTPUT_FILE, 'w', encoding='utf-8') as f:
        f.write(dart_content)

    print("="*60)
    print(f"SUCESSO ABSOLUTO!")
    print(f"Arquivo '{OUTPUT_FILE}' gerado corretamente.")
    print("Agora mova este arquivo para a pasta 'lib/data/' do seu projeto Flutter.")
    print("="*60)

if __name__ == "__main__":
    gerar_dart_final()