import fs from 'fs';
import path from 'path';
import { execSync } from 'child_process';
import { fileURLToPath } from 'url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));

const QUESTOES_DIR = path.join(__dirname, '../Livro/questoes');
const NORMALIZER_PATH = path.join(__dirname, 'normalize_questions_json.mjs');

function getSubjectTag(filename) {
  // Ex: questoes_fisiologia_capitulo_1_tecido_nervoso.json -> Fisiologia (Tecido Nervoso)
  let stem = filename.replace('questoes_fisiologia_', '').replace('.json', '');
  stem = stem.replace(/^capitulo_\d+_/, '');
  stem = stem.replace(/^capitulo_\d+/, '');
  
  // Converte snake_case para Title Case com espaços
  const words = stem.split('_').filter(Boolean);
  const cleanName = words.map(w => {
    // Manter preposições em minúsculo se necessário, mas capitalizar o resto
    const lower = w.toLowerCase();
    const minorWords = ['da', 'do', 'das', 'dos', 'de', 'e', 'o', 'a', 'um', 'uma', 'nas', 'nos', 'sua', 'seu'];
    if (minorWords.includes(lower)) {
      return lower;
    }
    return w.charAt(0).toUpperCase() + w.slice(1).toLowerCase();
  }).join(' ');

  // Ajusta acentos comuns que podem ter sumido na sanitização de arquivo
  let formatted = cleanName.charAt(0).toUpperCase() + cleanName.slice(1);
  
  // Dicionário de correções de acentuação para ficar bonito nas tags
  const corrections = {
    "tecido nervoso": "Tecido Nervoso",
    "base eletroquimica da funcao dos neuronios": "Base Eletroquímica da Função dos Neurônios",
    "transmissao sinaptica": "Transmissão Sináptica",
    "sensibilidades somatica e visceral": "Sensibilidades Somática e Visceral",
    "olfacao e gustacao": "Olfação e Gustação",
    "agua corporal propriedades e funcoes": "Água Corporal | Propriedades e Funções",
    "composicao e funcoes do sangue": "Composição e Funções do Sangue",
    "principios basicos do equilibrio acidobasico": "Princípios Básicos do Equilíbrio Acidobásico",
    "temperatura corporal e sua regulacao": "Temperatura Corporal e sua Regulação",
    "sistema renal estruturas e funcoes": "Sistema Renal | Estruturas e Funções",
    "filtracao glomerular e transporte tubular": "Filtração Glomerular e Transporte Tubular",
    "manutencao da hidratacao do liquido extracelular": "Manutenção da Hidratação do Líquido Extracelular",
    "regulacao renal do volume e eletrolitos do liquido extracelular": "Regulação Renal do Volume e Eletrólitos do Líquido Extracelular",
    "miccao caracteristicas da urina e depuracao renal": "Micção, Características da Urina e Depuração Renal",
    "funcao renal nas aves": "Função Renal nas Aves",
    "visao geral do sistema respiratorio": "Visão Geral do Sistema Respiratório",
    "aspectos fisicos e mecanicos da respiracao": "Aspectos Físicos e Mecânicos da Respiração",
    "ventilacao pulmonar e transporte de gases": "Ventilação Pulmonar e Transporte de Gases",
    "regulacao da respiracao": "Regulação da Respiração",
    "outras funcoes do sistema respiratorio": "Outras Funções do Sistema Respiratório",
    "respiracao nas aves": "Respiração nas Aves",
    "fisiologia do musculo esqueletico": "Fisiologia do Músculo Esquelético",
    "fisiologia do musculo liso": "Fisiologia do Músculo Liso",
    "fisiologia do musculo cardiaco adaptacoes do musculo e disturbios musculares": "Fisiologia do Músculo Cardíaco, Adaptações do Músculo e Distúrbios Musculares",
    "coracao e vascularizacao estrutura macroscopica e propriedades basicas": "Coração e Vascularização | Estrutura Macroscópica e Propriedades Básicas",
    "eletrofisiologia do coracao": "Eletrofisiologia do Coração",
    "eletrocardiograma e arritmias cardiacas": "Eletrocardiograma e Arritmias Cardíacas",
    "atividade mecanica do coracao": "Atividade Mecânica do Coração",
    "regulacao do coracao": "Regulação do Coração",
    "mecanismos de controle do sistema circulatorio": "Mecanismos de Controle do Sistema Circulatório",
    "microcirculacao linfa e edema": "Microcirculação, Linfa e Edema",
    "circulacao pulmonar": "Circulação Pulmonar",
    "circulacoes especiais": "Circulações Especiais",
    "bulhas e sopros cardiacos": "Bulhas e Sopros Cardíacos",
    "hipertensao insuficiencia cardiaca e choque": "Hipertensão, Insuficiência Cardíaca e Choque",
    "fisiologia do exercicio dos animais terrestres": "Fisiologia do Exercício dos Animais Terrestres",
    "motilidade gastrintestinal": "Motilidade Gastrintestinal",
    "atividades secretoras do tubo gastrintestinal": "Atividades Secretoras do Tubo Gastrintestinal",
    "digestao e absorcao de nutrientes": "Digestão e Absorção de Nutrientes",
    "fisiologia digestiva e microbiologia intestinal dos ruminantes": "Fisiologia Digestiva e Microbiologia Intestinal dos Ruminantes",
    "digestao das aves": "Digestão das Aves",
    "disturbios do metabolismo dos carboidratos e lipidios": "Distúrbios do Metabolismo dos Carboidratos e Lipídios",
    "sistema endocrino": "Sistema Endócrino",
    "reproducao masculina nos mamiferos": "Reprodução Masculina nos Mamíferos",
    "reproducao feminina dos mamiferos": "Reprodução Feminina dos Mamíferos",
    "reproducao das aves domesticas": "Reprodução das Aves Domésticas",
    "lactacao": "Lactação"
  };

  const normKey = formatted.toLowerCase().normalize("NFD").replace(/[\u0300-\u036f]/g, "").replace(/[^a-z0-9\s]/g, "").trim();
  for (const [rawKey, val] of Object.entries(corrections)) {
    if (rawKey.normalize("NFD").replace(/[\u0300-\u036f]/g, "") === normKey) {
      formatted = val;
      break;
    }
  }

  return `Fisiologia (${formatted})`;
}

async function main() {
  console.log("=== Normalizando todas as questões de Fisiologia (Dukes) ===");
  
  if (!fs.existsSync(QUESTOES_DIR)) {
    console.error(`Diretório não encontrado: ${QUESTOES_DIR}`);
    process.exit(1);
  }

  const files = fs.readdirSync(QUESTOES_DIR)
    .filter(f => f.startsWith('questoes_fisiologia_') && f.endsWith('.json') && !f.endsWith('_normalizado.json'));

  if (files.length === 0) {
    console.log("Nenhum arquivo bruto de fisiologia encontrado para normalizar.");
    return;
  }

  console.log(`Encontrados ${files.length} arquivos brutos.`);

  let successCount = 0;

  for (const file of files) {
    const inputPath = path.join(QUESTOES_DIR, file);
    const outputPath = inputPath.replace(/\.json$/i, '_normalizado.json');
    
    // Identificar a tag correta
    const tag = getSubjectTag(file);

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
