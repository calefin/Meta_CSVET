import fs from 'fs';
import path from 'path';
import { execSync } from 'child_process';
import { fileURLToPath } from 'url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));

const QUESTOES_DIR = path.join(__dirname, '../Livro/questoes');
const NORMALIZER_PATH = path.join(__dirname, 'normalize_questions_json.mjs');

const CHAPTER_TAGS = {
  "capitulo_01_1_ferrets": "Ferrets (Furões)",
  "capitulo_02_2_rabbits": "Coelhos",
  "capitulo_03_3_guinea_pigs_chinchillas_and_degus": "Roedores de Médio Porte (Porquinho-da-Índia, Chinchila e Degu)",
  "capitulo_04_4_small_rodents": "Pequenos Roedores",
  "capitulo_05_5_common_or_cotton_eared_marmosets": "Primatas (Saguins)",
  "capitulo_06_6_hedgehogs": "Hedgehogs (Ouriços-pigmeus-africanos)",
  "capitulo_07_7_sugar_gliders": "Sugar Gliders (Petauros-do-açúcar)",
  "capitulo_08_8_parrots_and_related_species": "Aves (Psitacídeos - Papagaios e Calopsitas)",
  "capitulo_09_9_songbirds_and_softbills": "Aves (Passeriformes e Aves Canoras)",
  "capitulo_10_10_lizards": "Répteis (Lagartos)",
  "capitulo_11_11_snakes": "Répteis (Serpentes)",
  "capitulo_12_12_tortoises_and_turtles": "Répteis (Quelônios - Jabutis e Cágados)",
  "capitulo_13_13_amphibians": "Anfíbios",
  "capitulo_14_14_goldfish_and_koi": "Peixes Ornamentais (Kinguio e Carpa)",
  "capitulo_15_15_tropical_freshwater_fish": "Peixes Tropicais de Água Doce",
  "capitulo_16_16_tropical_marine_fish": "Peixes Tropicais Marinhos"
};

function normalizeKey(str) {
  return str.toLowerCase()
    .normalize("NFD")
    .replace(/[\u0300-\u036f]/g, "")
    .replace(/[^a-z0-9]/g, "");
}

async function main() {
  console.log("=== Normalizando todas as questões de Animais Silvestres ===");
  
  if (!fs.existsSync(QUESTOES_DIR)) {
    console.error(`Diretório não encontrado: ${QUESTOES_DIR}`);
    process.exit(1);
  }

  const files = fs.readdirSync(QUESTOES_DIR)
    .filter(f => f.startsWith('questoes_silvestres_') && f.endsWith('.json') && !f.endsWith('_normalizado.json'));

  if (files.length === 0) {
    console.log("Nenhum arquivo bruto de silvestres encontrado para normalizar.");
    return;
  }

  console.log(`Encontrados ${files.length} arquivos brutos.`);

  let successCount = 0;

  for (const file of files) {
    const inputPath = path.join(QUESTOES_DIR, file);
    const outputPath = inputPath.replace(/\.json$/i, '_normalizado.json');
    
    // Identificar a tag correta
    const stem = file.replace('questoes_silvestres_', '').replace('.json', '');
    const normStem = normalizeKey(stem);
    
    let tag = "";
    for (const [key, value] of Object.entries(CHAPTER_TAGS)) {
      if (normalizeKey(key) === normStem) {
        tag = value;
        break;
      }
    }

    if (!tag) {
      // Fallback
      tag = stem.replace(/_/g, ' ');
      tag = tag.charAt(0).toUpperCase() + tag.slice(1);
    }

    console.log(`\n--------------------------------------------------`);
    console.log(`Processando: ${file}`);
    console.log(`Tag identificada: "${tag}"`);
    console.log(`Saída: ${path.basename(outputPath)}`);

    try {
      const command = `node "${NORMALIZER_PATH}" "${inputPath}" "${outputPath}" "${tag}"`;
      const output = execSync(command, { encoding: 'utf-8' });
      console.log(output.trim());
      successCount++;
    } catch (error) {
      console.error(`Erro ao normalizar ${file}:`, error.message);
    }
  }

  console.log(`\n==================================================`);
  console.log(`Concluído! ${successCount}/${files.length} arquivos normalizados com sucesso.`);
  console.log(`==================================================`);
}

main().catch(err => console.error("Erro fatal:", err));
