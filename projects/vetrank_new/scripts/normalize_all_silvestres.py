import os
import re
import sys
import subprocess
import unicodedata

# Mapeamento exato de capitulos para tags de assunto (mesmo do extrator)
CHAPTER_TAGS = {
    "Capitulo_01_1_Ferrets": "Ferrets (Furões)",
    "Capitulo_02_2_Rabbits": "Coelhos",
    "Capitulo_03_3_Guinea_pigs,_chinchillas,_and_degus": "Roedores de Médio Porte (Porquinho-da-Índia, Chinchila e Degu)",
    "Capitulo_04_4_Small_rodents": "Pequenos Roedores",
    "Capitulo_05_5_Common_or_cotton-eared_marmosets": "Primatas (Saguins)",
    "Capitulo_06_6_Hedgehogs": "Hedgehogs (Ouriços-pigmeus-africanos)",
    "Capitulo_07_7_Sugar_gliders": "Sugar Gliders (Petauros-do-açúcar)",
    "Capitulo_08_8_Parrots_and_related_species": "Aves (Psitacídeos - Papagaios e Calopsitas)",
    "Capitulo_09_9_Songbirds_and_softbills": "Aves (Passeriformes e Aves Canoras)",
    "Capitulo_10_10_Lizards": "Répteis (Lagartos)",
    "Capitulo_11_11_Snakes": "Répteis (Serpentes)",
    "Capitulo_12_12_Tortoises_and_turtles": "Répteis (Quelônios - Jabutis e Cágados)",
    "Capitulo_13_13_Amphibians": "Anfíbios",
    "Capitulo_14_14_Goldfish_and_koi": "Peixes Ornamentais (Kinguio e Carpa)",
    "Capitulo_15_15_Tropical_freshwater_fish": "Peixes Tropicais de Água Doce",
    "Capitulo_16_16_Tropical_marine_fish": "Peixes Tropicais Marinhos"
}

QUESTOES_DIR = r"D:\vetrank\Livro\questoes"
SCRIPTS_DIR = r"D:\vetrank\scripts"

def main():
    print("=" * 70)
    print("Normalizando todas as questões de Animais Selvagens")
    print("=" * 70)
    
    # Verifica se os diretórios existem
    if not os.path.exists(QUESTOES_DIR):
        print(f"Erro: Pasta de questões não encontrada: {QUESTOES_DIR}")
        return
        
    normalized_count = 0
    
    for key, tag in CHAPTER_TAGS.items():
        # Gera o mesmo safe_original_stem do extrator
        safe_key = unicodedata.normalize('NFKD', key).encode('ascii', 'ignore').decode('ascii')
        safe_key = re.sub(r'[^\w\s-]', '', safe_key).strip().lower()
        safe_key = re.sub(r'[-\s]+', '_', safe_key)
        
        input_filename = f"questoes_silvestres_{safe_key}.json"
        output_filename = f"questoes_silvestres_{safe_key}_normalizado.json"
        
        input_path = os.path.join(QUESTOES_DIR, input_filename)
        output_path = os.path.join(QUESTOES_DIR, output_filename)
        
        if not os.path.exists(input_path):
            print(f"[-] Arquivo não encontrado (pulando): {input_filename}")
            continue
            
        print(f"\n[+] Normalizando: {input_filename} -> {output_filename}")
        print(f"    Tag: {tag}")
        
        # Executa o script JS de normalização usando subprocess
        # Passa os caminhos completos
        cmd = [
            "node",
            os.path.join(SCRIPTS_DIR, "normalize_questions_json.mjs"),
            input_path,
            output_path,
            tag
        ]
        
        try:
            # Roda na pasta scripts para garantir resoluções locais se necessário
            result = subprocess.run(cmd, cwd=SCRIPTS_DIR, capture_output=True, text=True, check=True)
            print(result.stdout.strip())
            normalized_count += 1
        except subprocess.CalledProcessError as e:
            print(f"[ERRO] Falha ao normalizar {input_filename}:")
            print(e.stderr)
            
    print("\n" + "=" * 70)
    print(f"Normalização concluída! {normalized_count} arquivos processados.")
    print("=" * 70)

if __name__ == "__main__":
    main()
