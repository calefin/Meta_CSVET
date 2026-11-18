import fs from "fs";
import path from "path";
import { fileURLToPath } from "url";

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const DATA_DIR = path.join(__dirname, "../data");
const SEED_FILE = path.join(DATA_DIR, "blog_posts_seed.json");

const EXTRA_POSTS_DATA = [
  {
    title: "Urolitíase por Oxalato de Cálcio em Cães: Fisiopatologia e Manejo Dietético",
    summary: "Uma análise aprofundada da fisiopatologia da urolitíase por oxalato de cálcio em cães, explorando as bases metabólicas e as estratégias dietéticas fundamentais para prevenir recorrências.",
    intro: "A urolitíase por oxalato de cálcio representa uma das afecções urinárias mais frequentes na clínica médica de pequenos animais. Diferente dos urólitos de estruvita, os de oxalato de cálcio não são passíveis de dissolução farmacológica ou dietética, exigindo intervenção cirúrgica ou minimamente invasiva para sua remoção, seguida de um rígido manejo profilático para evitar a alta taxa de recidiva clínica.",
    sections: [
      {
        heading: "Fisiopatologia e Supersaturação Urinária",
        text: "A formação do cálculo ocorre quando a urina se torna supersaturada com cálcio e oxalato. Esse processo é influenciado pelo pH urinário ácido (geralmente abaixo de 6.5), que favorece a precipitação desses íons. Fatores genéticos, hipercalcemia primária, acidose metabólica crônica e dietas ricas em precursores de oxalato são apontados como principais gatilhos para a supersaturação e subsequente cristalização."
      },
      {
        heading: "Estratégias para Profilaxia Dietética",
        text: "O pilar do tratamento profilático consiste em aumentar o volume urinário para diminuir a gravidade específica da urina (idealmente mantendo-a abaixo de 1.020). Dietas úmidas são altamente recomendadas. Deve-se evitar a restrição excessiva de cálcio na dieta, pois isso aumenta a absorção intestinal de oxalato livre. O controle do sódio dietético também é vital, pois o excesso promove hipercalciúria."
      }
    ],
    listTitle: "Principais Recomendações Dietéticas para Prevenção",
    listItems: [
      "Fornecimento preferencial de alimentos úmidos (enlatados ou sachês) para aumentar a ingestão hídrica.",
      "Manutenção do pH urinário neutro (entre 6.5 e 7.5) para evitar ambientes ácidos favoráveis ao oxalato.",
      "Uso de citrato de potássio como agente alcalinizante e inibidor natural da cristalização de cálcio.",
      "Evitar suplementações excessivas de Vitamina C, que é metabolizada em oxalato no organismo do pet."
    ],
    tableHeader: ["Parâmetro Nutritivo", "Alvo Dietético", "Justificativa Fisiológica"],
    tableRows: [
      ["Proteína Bruta", "Moderada (20-25% MS)", "Evita a sobrecarga de resíduos ácidos na urina que acidificam o pH."],
      ["Cálcio", "Adequado (0.6-0.8% MS)", "Liga-se ao oxalato livre no lúmen intestinal, impedindo sua absorção."],
      ["Sódio", "Restrito/Controlado", "Sódio elevado induz a excreção urinária reflexa de cálcio, piorando o quadro."]
    ]
  },
  {
    title: "Leishmaniose Visceral Canina: Diagnóstico Sorológico, Parasitológico e Aspectos Clínicos no Brasil",
    summary: "Revisão técnica sobre a Leishmaniose Visceral Canina, abordando a complexidade do diagnóstico laboratorial e o protocolo de conduta clínica e de saúde pública atualizado no Brasil.",
    intro: "A Leishmaniose Visceral Canina (LVC), causada pelo protozoário Leishmania infantum e transmitida pelo vetor Lutzomyia longipalpis, é uma zoonose de grande impacto epidemiológico no Brasil. O cão atua como o principal reservatório doméstico da doença, o que torna o diagnóstico precoce e a abordagem clínica correta cruciais não apenas para a saúde animal, mas para o controle sanitário populacional.",
    sections: [
      {
        heading: "Fisiopatogenia e Sintomatologia Polimórfica",
        text: "A infecção é caracterizada por uma resposta imune predominantemente celular (Th1 - resistente) ou humoral (Th2 - suscetível). Animais com resposta Th2 desenvolvem hipergamaglobulinemia, deposição de imunocomplexos e sinais clínicos multissistêmicos crônicos, incluindo linfadenopatia generalizada, dermatites esfoliativas, onicogrifose, esplenomegalia, glomerulonefrite e insuficiência renal terminal."
      },
      {
        heading: "Abordagem Diagnóstica Laboratorial",
        text: "O diagnóstico da LVC exige uma combinação de métodos devido à complexidade imunológica da doença. O protocolo oficial do Ministério da Saúde preconiza a triagem inicial por teste rápido (imunocromatografia com antígeno DPP) seguida da confirmação sorológica por ELISA. Métodos parasitológicos diretos (citologia de aspirado de medula óssea ou linfonodo) e PCR são considerados padrão-ouro para confirmação definitiva."
      }
    ],
    listTitle: "Métodos Diagnósticos Comparativos",
    listItems: [
      "Sorologia rápida (DPP): Excelente para triagem em massa devido à rapidez e sensibilidade.",
      "ELISA Confirmatório: Sorologia quantitativa que avalia a carga de anticorpos específicos circulares.",
      "Citologia por Aspirado Agulha Fina (CAAF): Pesquisa direta de formas amastigotas intracelulares.",
      "Reação em Cadeia da Polimerase (PCR): Altíssima sensibilidade molecular, detectando o DNA parasitário."
    ],
    tableHeader: ["Método Diagnóstico", "Sensibilidade / Especificidade", "Aplicação Recomendada"],
    tableRows: [
      ["Triagem Rápida (DPP)", "Alta Sensibilidade", "Triagem inicial rápida de cães suspeitos no consultório ou inquéritos campais."],
      ["Citologia Aspirativa", "Alta Especificidade", "Confirmação visual inequívoca das amastigotas em linfonodos ou medula óssea."],
      ["PCR quantitativo", "Altíssima Precisão", "Detecção precoce, monitoramento de carga parasitária pós-tratamento e estudos moleculares."]
    ]
  },
  {
    title: "Cetoacidose Diabética em Cães: Protocolos de Insulinoterapia e Manejo Eletrolítico na Emergência",
    summary: "Guia clínico intensivo sobre o manejo emergencial da Cetoacidose Diabética em cães, com ênfase na insulinoterapia de ação rápida e correção detalhada de distúrbios de potássio.",
    intro: "A cetoacidose diabética (CAD) é uma complicação metabólica aguda e potencialmente fatal da Diabetes Mellitus descompensada em cães. Caracteriza-se pela tríade clássica de hiperglicemia, cetonemia/cetonúria e acidose metabólica. O paciente em CAD apresenta desidratação severa, choque hipovolêmico latente e distúrbios eletrolíticos profundos, exigindo terapia intensiva imediata e monitoramento laboratorial de hora em hora.",
    sections: [
      {
        heading: "Fluidoterapia e Restabelecimento Volêmico",
        text: "A prioridade absoluta na admissão do paciente em CAD é a restauração da perfusão tecidual e hidratação. A fluidoterapia com cristaloides isotônicos (geralmente NaCl 0,9% ou Ringer Lactato) deve ser instituída imediatamente antes de qualquer dose de insulina. A reidratação inicial reduz passivamente a glicose sanguínea por diluição e estimula a excreção renal, restabelecendo a volemia tecidual."
      },
      {
        heading: "Insulinoterapia de Ação Rápida e Manejo do Potássio",
        text: "O tratamento com insulina só deve ser iniciado após 1 a 2 horas do início da fluidoterapia, utilizando insulina regular (ação rápida). O protocolo de infusão contínua de baixa dose ou injeções intramusculares de hora em hora permite uma redução gradual e controlada da glicemia. A introdução de insulina promove a entrada rápida de potássio nas células, o que pode causar hipocalemia severa e fatal se a reposição de potássio não for calculada e infundida adequadamente."
      }
    ],
    listTitle: "Passos Essenciais no Monitoramento Intensivo",
    listItems: [
      "Mensuração da glicemia a cada 1 ou 2 horas para garantir declínio suave (de 50 a 75 mg/dL por hora).",
      "Monitoramento de eletrólitos (potássio, fósforo, sódio) a cada 4 a 6 horas na fase inicial.",
      "Avaliação de hemogasometria venosa periódica para acompanhar a resolução da acidose metabólica.",
      "Monitoramento da produção de urina (débito urinário) para prevenir insuficiência renal aguda por hipoperfusão."
    ],
    tableHeader: ["Potássio Sérico (mEq/L)", "Quantidade de KCl a adicionar ao Fluido", "Taxa Máxima de Infusão"],
    tableRows: [
      ["Abaixo de 2.0", "80 mEq por Litro de Fluido", "Nunca exceder a taxa de infusão de 0.5 mEq/kg/hora de potássio."],
      ["2.0 a 2.5", "60 mEq por Litro de Fluido", "Administração sob monitoramento eletrocardiográfico contínuo para arritmias."],
      ["2.6 a 3.0", "40 mEq por Litro de Fluido", "Adequado para manter níveis basais seguros durante a insulinoterapia ativa."]
    ]
  },
  {
    title: "Mastocitoma Cutâneo Canino: Diagnóstico Citopatológico, Histopatológico e Estadiamento Clínico",
    summary: "Revisão detalhada sobre as abordagens diagnósticas, critérios de classificação histopatológica de Kiupel/Patnaik e estadiamento clínico do mastocitoma cutâneo em cães.",
    intro: "O mastocitoma cutâneo é a neoplasia maligna de pele mais comum na espécie canina, correspondendo a aproximadamente 20% de todos os tumores de pele diagnosticados. Apresenta um comportamento biológico altamente variável, variando desde nódulos benignos de crescimento lento até lesões ulceradas infiltrativas de alta agressividade metastática. O diagnóstico preciso e o estadiamento rigoroso são fundamentais para traçar a melhor estratégia terapêutica.",
    sections: [
      {
        heading: "Diagnóstico Citopatológico e Triagem Inicial",
        text: "A citologia por aspiração com agulha fina (CAAF) é o método inicial de escolha, apresentando alta sensibilidade diagnóstica. Mastocitos normais ou neoplásicos são facilmente identificados devido à presença marcante de grânulos citoplasmáticos metacromáticos de cor purpúrea (contendo histamina e heparina). No entanto, o diagnóstico citológico não define o grau histopatológico do tumor, sendo necessária a biópsia excisional."
      },
      {
        heading: "Critérios de Classificação Histopatológica (Patnaik vs. Kiupel)",
        text: "A classificação de Patnaik divide os mastocitomas em três graus (I, II e III), enquanto o sistema bidimensional de Kiupel classifica os tumores em Mastocitoma de Alto Grau e Baixo Grau. O sistema de Kiupel reduziu consideravelmente a subjetividade entre patologistas e provou ser um excelente preditor de sobrevida, índice mitótico, comportamento clínico e invasão linfática metastática."
      }
    ],
    listTitle: "Exames Obrigatórios para Estadiamento",
    listItems: [
      "Citologia aspirativa de linfonodos regionais, mesmo que apresentem tamanho macroscópico normal.",
      "Ultrassonografia abdominal completa para triagem de metástases no baço e fígado (órgãos alvo do mastocitoma).",
      "Radiografia torácica em três projeções para avaliação de linfadenopatia intratorácica ou afecções concomitantes.",
      "Perfil hematológico e bioquímico renal/hepático completo para avaliar o estado clínico do paciente."
    ],
    tableHeader: ["Grau Histopatológico (Kiupel)", "Características Biológicas", "Prognóstico Clínico"],
    tableRows: [
      ["Mastocitoma de Baixo Grau", "Baixo índice mitótico, células bem diferenciadas, ausência de multinucleação.", "Favorável. Sobrevivência média longa (>2 anos) com excisão cirúrgica ampla local."],
      ["Mastocitoma de Alto Grau", "Índice mitótico elevado (>7 em 10 campos), células gigantes multinucleadas, anisocariose severa.", "Desfavorável. Alta propensão a metástases e recorrência local rápida. Exige quimioterapia adjuvante."]
    ]
  },
  {
    title: "Hiperadrenocorticismo Canino (Síndrome de Cushing): Diagnóstico por Teste de Estimulação com ACTH",
    summary: "Entenda os critérios de diagnóstico do Hiperadrenocorticismo canino, focando no Teste de Estimulação com ACTH, monitoramento terapêutico e especificidade metodológica.",
    intro: "O hiperadrenocorticismo (HAC) ou Síndrome de Cushing é uma das endocrinopatias mais comuns em cães de meia-idade e idosos. Decorre do excesso crônico de glicocorticoides na circulação, seja por hiperprodução hipofisária (HAC pituitário-dependente, ~85% dos casos), tumores adrenais primários (~15%) ou administração iatrogênica de corticoides. O diagnóstico requer correlação estrita entre achados clínicos clássicos (poliúria, polidipsia, alopecia simétrica, abdômen abaulado) e testes dinâmicos hormonais.",
    sections: [
      {
        heading: "Mecanismo e Protocolo do Teste de Estimulação com ACTH",
        text: "O teste de estimulação com ACTH avalia a capacidade máxima de reserva secretória das glândulas adrenais. Consiste na mensuração do cortisol sérico basal (T0), seguido da administração de ACTH sintético (cosintropina) via endovenosa ou intramuscular, com nova dosagem de cortisol após 1 hora (T1). Adrenais hiperplásicas respondem com uma liberação exagerada de cortisol sérico, superando amplamente os níveis fisiológicos normais."
      },
      {
        heading: "Limitações e Monitoramento Clínico com Trilostano",
        text: "Embora seja o único teste capaz de diagnosticar o HAC iatrogênico (demonstrando adrenais atróficas sem resposta ao estímulo), o teste de ACTH apresenta menor sensibilidade que o teste de Supressão por Baixa Dose de Dexametasona (SBDD) em casos de tumores adrenais. Contudo, continua sendo o padrão-ouro e a ferramenta obrigatória para monitorar a eficácia e a segurança do tratamento médico com Trilostano (inibidor da síntese de esteroides)."
      }
    ],
    listTitle: "Interpretando os Níveis de Cortisol (Pós-ACTH)",
    listItems: [
      "Valores abaixo de 1.5 ug/dL: Sugestivos de HAC iatrogênico ou sobredose terapêutica de Trilostano.",
      "Valores entre 6.0 a 17.0 ug/dL: Resposta normal/fisiológica (ou faixa terapêutica ideal pós-Trilostano).",
      "Valores acima de 22.0 ug/dL: Altamente diagnósticos de hiperadrenocorticismo espontâneo ativo.",
      "Valores entre 17.0 a 22.0 ug/dL: Zona cinzenta, exige repetição do teste ou realização do teste de supressão."
    ],
    tableHeader: ["Situação Clínica", "Cortisol Basal (ug/dL)", "Cortisol Pós-ACTH 1h (ug/dL)"],
    tableRows: [
      ["Fisiológico / Saudável", "1.0 a 5.0", "6.0 a 17.0 (Reserva cortical saudável)"],
      ["HAC Espontâneo Ativo", "Geralmente elevado", "Acima de 22.0 (Resposta excessiva hiperplásica)"],
      ["HAC Iatrogênico / Hipoadreno", "Abaixo de 1.0", "Abaixo de 1.5 (Glândulas atróficas sem resposta)"]
    ]
  }
];

// Gerar outros tópicos para completar os 30 adicionais (tópicos 6 a 30)
const topicsMetadata = [
  { id: 6, title: "Otite Externa Crônica em Cães: Identificação Microbiológica e Resistência a Antimicrobianos", cat: "Publicações Científicas" },
  { id: 7, title: "Suplementação de Ômega-3 (EPA e DHA) na Nefroproteção de Cães e Gatos com Doença Renal Crônica", cat: "Blogs de Empresas do Setor (Nutrição)" },
  { id: 8, title: "Condroprotetores e Moduladores de Cartilagem (Condroitina e Glicosamina) na Osteoartrite Canina", cat: "Blogs de Empresas do Setor" },
  { id: 9, title: "Terapia Antimicrobiana Racional: Uso de Cefalosporinas de Terceira Geração na Dermatologia Veterinária", cat: "Farmacologia Veterinária" },
  { id: 10, title: "Nutrição Clínica: Dieta Hipoalergênica e Proteína Hidrolisada no Controle da Reação Adversa ao Alimento", cat: "Blogs de Empresas do Setor" },
  { id: 11, title: "Uso Veterinário de Probióticos e Prebióticos na Modulação da Microbiota Intestinal e Resposta Imune", cat: "Nutrição Clínica" },
  { id: 12, title: "Farmacocinética e Farmacodinâmica dos Anti-inflamatórios Não Esteroidais (AINEs) Seletivos COX-2", cat: "Farmacologia" },
  { id: 13, title: "Uso da Cannabis Medicinal na Neurologia e Oncologia Veterinária: Evidências e Desafios Regulatórios", cat: "Portais de Educação Continuada" },
  { id: 14, title: "Abordagem Multidisciplinar na Cardiomiopatia Dilatada Canina: Protocolos de Ecocardiografia Clínicos", cat: "Educação Continuada" },
  { id: 15, title: "Manejo Avançado da Hipertensão Arterial Sistêmica em Felinos: Protocolo com Anlodipino", cat: "Clínica de Felinos" },
  { id: 16, title: "Emergências em Animais Silvestres e Exóticos: Estabilização Inicial de Pequenos Mamíferos", cat: "Animais Silvestres" },
  { id: 17, title: "Odontologia Veterinária Completa: Doença Periodontal em Pequenos Animais e Prevenção Ativa", cat: "Educação Continuada" },
  { id: 18, title: "Terapia Regenerativa na Medicina Veterinária: Células-Tronco Mesenquimais em Lesões Tendíneas", cat: "Tendências e Atualização" },
  { id: 19, title: "Displasia Coxofemoral em Cães: Diagnóstico Precoce pelo Método PennHIP e Opções de Cirurgia", cat: "Portais de Grande Tráfego" },
  { id: 20, title: "Síndrome da Disfunção Cognitiva em Cães Idosos: Reconhecendo os Sinais Clínicos e Manejo Nutracêutico", cat: "Geriatria Veterinária" },
  { id: 21, title: "Olho Seco (Ceratoconjuntivite Seca) em Cães: Abordagem Terapêutica com Ciclosporina e Tacrolimus", cat: "Oftalmologia" },
  { id: 22, title: "Doença Inflamatória Intestinal (DII) Felina: Diagnóstico Diferencial com Linfoma Alimentar", cat: "Clínica de Felinos" },
  { id: 23, title: "Hiperatividade e Distúrbios de Ansiedade em Cães: Protocolos de Enriquecimento Ambiental e Medicamentoso", cat: "Medicina Comportamental" },
  { id: 24, title: "Abordagem Clínica à Babesiose e Erliquiose Canina: Fisiopatologia da Coinfecção por Hemoparasitos", cat: "Plataformas Técnicas" },
  { id: 25, title: "Fluidoterapia na Emergência Veterinária: Cristaloides, Coloides e Equilíbrio Ácido-Base Avançado", cat: "Medicina de Emergência" },
  { id: 26, title: "Dermatofitose em Pequenos Animais: Diagnóstico por Cultura Micológica e Lâmpada de Wood", cat: "Dermatologia" },
  { id: 27, title: "Bloqueios Anestésicos Locorregionais em Odontologia Veterinária: Protocolos Práticos", cat: "Anestesiologia" },
  { id: 28, title: "Complexo Respiratório Felino (Rinotraqueíte e Calicivirose): Manejo de Surtos em Gatis", cat: "Sanidade e Coletivos" },
  { id: 29, title: "Mercado Veterinário Brasileiro 2026: Tendências de Tráfego no Semrush e Demanda por Especialidades", cat: "Panorama de Tráfego Semrush" },
  { id: 30, title: "Como Profissionais Veterinários Utilizam Estratégias de SEO Clínico para Captação Ética de Clientes", cat: "Semrush e Marketing Ético" }
];

// Gerador dinâmico de HTML para preencher o conteúdo desses 25 tópicos restantes
function generateHtmlContent(title, cat) {
  const slug = title.toLowerCase().normalize("NFD").replace(/[\u0300-\u036f]/g, "").replace(/[^a-z0-9\s-]/g, "").trim().replace(/\s+/g, "-");
  const summary = `Revisão clínica abrangente sobre ${title.toLowerCase()}, discutindo diretrizes diagnósticas baseadas em evidências científicas e abordagens terapêuticas modernas para profissionais e estudantes de Medicina Veterinária.`;
  
  const h2 = `Introdução à temática de ${title}`;
  const h3_1 = `Bases Fisiopatológicas e Sinais Clínicos de Destaque`;
  const h3_2 = `Abordagem Diagnóstica e Diretrizes Terapêuticas Atuais`;
  
  const bodyHtml = `
    <h2>${h2}</h2>
    <p>O estudo de <strong>${title}</strong> é de importância vital na rotina clínica contemporânea. A busca constante por medicina baseada em evidências científicas (com publicações na <em>Revista Clínica Veterinária, BVS-Vet, e SciELO</em>) nos obriga a revisitar periodicamente os consensos internacionais e os guias farmacológicos de referência como o <em>Merck Veterinary Manual</em> para estabelecer as condutas de excelência diagnóstica e terapêutica.</p>
    
    <h3>${h3_1}</h3>
    <p>Na fisiopatologia deste quadro clínico, destaca-se o papel das interações celulares e das disfunções moleculares que geram a sintomatologia observada na rotina. Estudos apontam que o correto reconhecimento das manifestações subclínicas é o principal diferencial para o prognóstico favorável do paciente, mitigando o avanço de complicações crônicas e sistêmicas.</p>
    <ul>
      <li>Investigação epidemiológica detalhada associada a fatores de risco individuais.</li>
      <li>Exames laboratoriais triados estrategicamente para avaliar a integridade dos órgãos afetados.</li>
      <li>Manejo farmacológico multimodal para evitar efeitos colaterais deletérios.</li>
      <li>Suplementação nutricional de suporte (utilizando bases tecnológicas da <em>Vetnil ou PremieRVet</em>).</li>
    </ul>
    
    <h3>${h3_2}</h3>
    <p>O manejo clínico avançado propõe uma terapia racional e individualizada. A escolha de fármacos de última geração e o entendimento de sua farmacocinética (absorção, distribuição, metabolismo e excreção) são chaves para evitar iatrogenias e reações adversas graves na clínica veterinária de pequenos animais.</p>
    
    <table style="width:100%; border-collapse:collapse; margin:20px 0;">
      <thead>
        <tr style="background-color:rgba(16,185,129,0.1);">
          <th style="padding:10px; border:1px solid rgba(255,255,255,0.1); text-align:left;">Abordagem</th>
          <th style="padding:10px; border:1px solid rgba(255,255,255,0.1); text-align:left;">Ação Recomendada</th>
          <th style="padding:10px; border:1px solid rgba(255,255,255,0.1); text-align:left;">Benefício Esperado</th>
        </tr>
      </thead>
      <tbody>
        <tr>
          <td style="padding:10px; border:1px solid rgba(255,255,255,0.1);">Fase Emergencial</td>
          <td style="padding:10px; border:1px solid rgba(255,255,255,0.1);">Estabilização hemodinâmica e terapia intensiva.</td>
          <td style="padding:10px; border:1px solid rgba(255,255,255,0.1);">Sobrevida imediata do paciente crítico.</td>
        </tr>
        <tr>
          <td style="padding:10px; border:1px solid rgba(255,255,255,0.1);">Fase de Suporte</td>
          <td style="padding:10px; border:1px solid rgba(255,255,255,0.1);">Suplementação nutracêutica e alimentação clínica direcionada.</td>
          <td style="padding:10px; border:1px solid rgba(255,255,255,0.1);">Prevenção de recidivas e modulação imunológica ideal.</td>
        </tr>
      </tbody>
    </table>
  `;

  return {
    slug,
    title,
    summary,
    body: bodyHtml.replace(/\n\s+/g, "\n").trim(),
    author: "Equipe VetRank"
  };
}

function run() {
  console.log("Expanding blog posts database backup...");
  
  let existingPosts = [];
  if (fs.existsSync(SEED_FILE)) {
    try {
      existingPosts = JSON.parse(fs.readFileSync(SEED_FILE, "utf8"));
      console.log(`Loaded ${existingPosts.length} existing posts from ${SEED_FILE}`);
    } catch (e) {
      console.error("Failed to read existing posts. Starting fresh.");
      existingPosts = [];
    }
  }

  // Se já temos pelo menos 50 posts, não precisamos acrescentar mais.
  if (existingPosts.length >= 50) {
    console.log(`Seed file already contains ${existingPosts.length} posts. No need to expand.`);
    process.exit(0);
  }

  // 1. Extrair os primeiros 5 posts de alta qualidade descritos individualmente
  const highQualityPosts = EXTRA_POSTS_DATA.map(data => {
    const slug = data.title.toLowerCase().normalize("NFD").replace(/[\u0300-\u036f]/g, "").replace(/[^a-z0-9\s-]/g, "").trim().replace(/\s+/g, "-");
    
    // Monta o corpo HTML
    let body = `<h2>Introdução</h2>\n<p>${data.intro}</p>\n\n`;
    data.sections.forEach(s => {
      body += `<h3>${s.heading}</h3>\n<p>${s.text}</p>\n\n`;
    });
    body += `<h3>${data.listTitle}</h3>\n<ul>\n`;
    data.listItems.forEach(item => {
      body += `  <li>${item}</li>\n`;
    });
    body += `</ul>\n\n`;
    
    body += `<table style="width:100%; border-collapse:collapse; margin:20px 0;">\n`;
    body += `  <thead>\n    <tr style="background-color:rgba(16,185,129,0.1);">\n`;
    data.tableHeader.forEach(th => {
      body += `      <th style="padding:10px; border:1px solid rgba(255,255,255,0.1); text-align:left;">${th}</th>\n`;
    });
    body += `    </tr>\n  </thead>\n  <tbody>\n`;
    data.tableRows.forEach(row => {
      body += `    <tr>\n`;
      row.forEach(td => {
        body += `      <td style="padding:10px; border:1px solid rgba(255,255,255,0.1);">${td}</td>\n`;
      });
      body += `    </tr>\n`;
    });
    body += `  </tbody>\n</table>`;

    return {
      slug,
      title: data.title,
      summary: data.summary,
      body: body.trim(),
      author: "Equipe VetRank",
      active: 1,
      createdAt: new Date().toISOString(),
      updatedAt: new Date().toISOString()
    };
  });

  // 2. Gerar os outros 25 posts de tópicos avançados
  const generatedPosts = topicsMetadata.map(meta => {
    const p = generateHtmlContent(meta.title, meta.cat);
    return {
      ...p,
      active: 1,
      createdAt: new Date().toISOString(),
      updatedAt: new Date().toISOString()
    };
  });

  const allNewPosts = [...highQualityPosts, ...generatedPosts];
  
  // Mesclar com os posts existentes, garantindo que não haja duplicatas por slug
  const finalPostsMap = new Map();
  existingPosts.forEach(p => finalPostsMap.set(p.slug, p));
  allNewPosts.forEach(p => {
    if (!finalPostsMap.has(p.slug)) {
      finalPostsMap.set(p.slug, p);
    }
  });

  const finalPosts = Array.from(finalPostsMap.values());
  
  // Gravar no arquivo de seed
  if (!fs.existsSync(DATA_DIR)) {
    fs.mkdirSync(DATA_DIR, { recursive: true });
  }
  fs.writeFileSync(SEED_FILE, JSON.stringify(finalPosts, null, 2), "utf8");
  console.log(`Successfully expanded seed data. Total posts now: ${finalPosts.length}`);
}

run();
