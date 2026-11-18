import fs from "fs";
import path from "path";
import { fileURLToPath } from "url";

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const DATA_DIR = path.join(__dirname, "../data");
const SEED_FILE = path.join(DATA_DIR, "blog_posts_seed.json");

// 30 específicos posts cobrindo todas as 7 categorias solicitadas
const SPECIFIC_30_POSTS = [
  // CATEGORIA 1: Clínica de pequenos animais
  {
    title: "Sintomas de cinomose em cães – guia para tutores e veterinários",
    summary: "Entenda a fisiopatologia da cinomose canina, as fases clínica e neurológica e as melhores estratégias diagnósticas para salvar a vida do paciente de forma ética e profissional.",
    intro: "A cinomose é uma virose altamente contagiosa e frequentemente fatal em cães, causada por um Morbillivirus da família Paramyxoviridae. O vírus tem uma forte afinidade por tecidos epiteliais, linfoides e nervosos, resultando em sintomatologia multissistêmica clássica que afeta principalmente filhotes não vacinados ou cães adultos imunossuprimidos.",
    sections: [
      {
        heading: "Fases da Doença e Evolução Clínica",
        text: "O curso clínico da cinomose é classicamente dividido em fases. A fase inicial (respiratória e gastrintestinal) cursa com febre bifásica, letargia, diarreia e corrimento oculonasal mucopurulento (frequentemente associado a pneumonia secundária por Bordetella bronchiseptica). A fase dermatológica pode cursar com hiperqueratose digital (coxins) e nasal. A fase neurológica se desenvolve quando o vírus ultrapassa a barreira hematoencefálica, desencadeando mioclonias involuntárias, convulsões do tipo 'mascar chiclete' (chewing-gum fits), ataxia e paralisia."
      },
      {
        heading: "Diagnóstico Diferencial e Conduta do Clínico",
        text: "O diagnóstico baseia-se na correlação de sinais clínicos multissistêmicos com exames complementares de alta especificidade. Exames hematológicos comumente mostram linfopenia severa. Testes de imunocromatografia rápida pesquisando antígenos virais em swabs conjuntivais são úteis na fase aguda. RT-PCR em amostras de sangue, urina ou líquor representa o padrão-ouro de confirmação. Condutas clínicas envolvem isolamento estrito do paciente, fluidoterapia, antibioticoterapia de suporte (como doxiciclina) e anticonvulsivantes."
      }
    ],
    listTitle: "Pegadinhas e Detalhes de Prova (ENARE/ENADE) sobre Cinomose",
    listItems: [
      "Inclusões virais patognomônicas chamadas corpúsculos de Lentz podem ser detectadas em hemácias ou neutrófilos no esfregaço sanguíneo.",
      "A hiperqueratose nasal e digital é um excelente marcador de cronicidade e envolvimento da cepa epiteliotrópica do vírus.",
      "O vírus da cinomose induz imunossupressão profunda ao alvejar linfócitos T e B, facilitando infecções oportunistas secundárias."
    ],
    tableHeader: ["Fase da Cinomose", "Sinais Clínicos Clássicos", "Gravidade Prognóstica"],
    tableRows: [
      ["Fase Epitelial (Respiratória/Gastrintestinal)", "Febre, tosse, diarreia, secreção mucopurulenta bilateral.", "Moderado (se tratada precocemente)"],
      ["Fase Dermatológica", "Hiperqueratose de focinho e coxins plantares, pústulas abdominais.", "Favorável a Reservado"],
      ["Fase Neurológica", "Mioclonias (contrações musculares rítmicas), convulsões, paresia.", "Grave / Reservado"]
    ]
  },
  {
    title: "Como diagnosticar e tratar parvovirose em filhotes de cachorro",
    summary: "Guia clínico completo sobre parvovirose canina em filhotes, detalhando diagnóstico rápido, protocolos de fluidoterapia emergencial e controle epidemiológico de surtos.",
    intro: "A parvovirose canina é uma infecção entérica severa induzida pelo Parvovírus Canino tipo 2 (CPV-2), altamente resistente no meio ambiente. Ela acomete principalmente filhotes de 6 semanas a 6 meses de idade, afetando o epitélio de revestimento das criptas intestinais e a medula óssea, o que resulta em gastrenterite hemorrágica desidratante e neutropenia profunda.",
    sections: [
      {
        heading: "Fisiopatogenia e Diagnóstico Rápido",
        text: "O CPV-2 tem predileção por células em rápida divisão ativa. Após infecção por via oronasal, o vírus replica-se no tecido linfoide da faringe e dissemina-se por via hematogênica para as criptas intestinais de Lieberkühn, destruindo o epitélio absortivo e facilitando a translocação bacteriana. O diagnóstico é comumente realizado na rotina clínica por meio de testes imunocromatográficos rápidos (ELISA) em amostras fecais frescas. A confirmação molecular por PCR ou isolamento viral pode ser realizada em casos de suspeita com resultados limítrofes."
      },
      {
        heading: "Protocolo de Fluidoterapia e Suporte Emergencial",
        text: "A fluidoterapia intensiva é o pilar absoluto da sobrevivência na parvovirose. Devem ser administrados cristaloides isotônicos (como Ringer Lactato ou NaCl 0,9%) enriquecidos com glicose e potássio conforme as perdas por vômitos e diarreias. O uso de antibióticos de amplo espectro (Ampicilina associada a Enrofloxacina ou Cefalosporinas) previne a sepse decorrente da translocação bacteriana em vigência de neutropenia. O jejum alimentar absoluto foi substituído pela alimentação enteral precoce por sonda nasogástrica, preservando a barreira mucosa entérica."
      }
    ],
    listTitle: "Alterações Hematológicas Clínicas Marcantes",
    listItems: [
      "Leucopenia severa com neutropenia absoluta devido ao ataque viral direto à medula óssea.",
      "Anemia normocítica normocrômica decorrente de perda sanguínea crônica pelo trato entérico.",
      "Hipopotassemia e acidose metabólica causadas por perdas volumosas de eletrólitos gastrointestinais."
    ],
    tableHeader: ["Fase do Manejo", "Conduta Recomendada", "Ação Fisiológica / Justificativa"],
    tableRows: [
      ["Estabilização Volêmica", "Cristaloides IV (bolus em choque se necessário).", "Restauração do débito cardíaco e perfusão periférica."],
      ["Antibioticoterapia", "Combinação Beta-lactâmico + Aminoglicosídeo ou Enrofloxacina.", "Prevenção de septicemia por bactérias gram-negativas opportunistas."],
      ["Nutrição Enteral Ativa", "Sonda nasoenteral com dieta líquida hipercalórica precoce.", "Trofiamento dos enterócitos e barreira imune intestinal."]
    ]
  },
  {
    title: "Doenças respiratórias em gatos indoor: causas, diagnóstico e prevenção",
    summary: "Entenda o complexo respiratório felino e a asma felina em felinos que vivem exclusivamente em ambientes fechados (indoor), com foco em diagnóstico clínico diferenciado.",
    intro: "Gatos que vivem exclusivamente dentro de casa (indoor) parecem protegidos de atropelamentos e brigas, mas estão sujeitos a afecções respiratórias específicas. O Complexo Respiratório Felino (CRF), de origem infecciosa, e a Asma Felina (bronquite alérgica crônica), de origem imunomediada, são os principais desafios respiratórios enfrentados pelos médicos-veterinários na clínica de felinos.",
    sections: [
      {
        heading: "Complexo Respiratório Felino (CRF)",
        text: "O CRF é classicamente associado à infecção combinada ou isolada pelo Herpesvírus Felino tipo 1 (FHV-1) e pelo Calicivírus Felino (FCV), além de infecções secundárias por Chlamydia felis e Mycoplasma felis. Gatos indoor podem contrair estes vírus por meio de fômites (calçados, roupas) ou reativação do FHV-1 latente no gânglio trigêmeo devido ao estresse. Os sinais incluem espirros frequentes, conjuntivite severa, quemose, blefaroespasmo e úlceras orais dolorosas típicas da calicirose."
      },
      {
        heading: "Asma Felina (Bronquite Crônica)",
        text: "A asma felina é uma síndrome inflamatória imunomediada das vias aéreas inferiores, ativada pela inalação de alérgenos ambientais (poeira, fumaça de cigarro, perfumes, produtos de limpeza, areias de caixa sanitária com muito pó). Causa broncoconstrição reflexa, hipertrofia do músculo liso bronquial e hipersecreção de muco. O diagnóstico baseia-se em radiografias torácicas (padrão bronquial ou intersticial difuso) e lavado broncoalveolar com alta celularidade de eosinófilos. O manejo envolve corticoides (como prednisolona) e broncodilatadores por via inalatória (aerossóis)."
      }
    ],
    listTitle: "Principais Diferenças Diagnósticas (Asma vs. Infeccioso)",
    listItems: [
      "Febre, úlceras na cavidade oral e corrimento nasal mucopurulento indicam forte etiologia infecciosa (CRF).",
      "Tosse seca que mimetiza tentativa de expelição de bola de pelos é o sinal primário da asma felina alérgica.",
      "A conjuntivite persistente com blefaroespasmo sugere fortemente infecção ativa por FHV-1 ou Chlamydia felis."
    ],
    tableHeader: ["Patologia", "Principais Diagnósticos", "Abordagem Terapêutica Clássica"],
    tableRows: [
      ["Asma Felina", "Raio-X (padrão bronquial 'rosquinhas'), Lavado Broncoalveolar.", "Prednisolona oral ou inalatória (fluticasona) + Broncodilatador."],
      ["Herpesvírus (CRF)", "PCR, sinais oculares severos e corrimento nasal.", "Suporte (inalações), colírios antivirais, antibiótico se houver infecção secundária."],
      ["Calicivírus (CRF)", "PCR, sialorreia e úlceras dolorosas de língua/palato.", "Analgesia potente (buprenorfina), fluidoterapia e alimentação úmida pastosa."]
    ]
  },
  {
    title: "Otite em cães: melhores protocolos de diagnóstico e tratamento",
    summary: "Aprenda a diagnosticar e tratar a otite externa canina de forma estruturada, identificando fatores primários, perpetuantes e selecionando terapias otológicas eficazes.",
    intro: "A otite externa canina é uma das queixas dermatológicas mais comuns nas clínicas de pequenos animais. Caracteriza-se pela inflamação do conduto auditivo externo (pavilhão e canal vertical/horizontal). A otite não deve ser encarada como uma doença única isolada, mas sim como um sintoma decorrente de fatores primários, predisponentes, secundários e perpetuantes.",
    sections: [
      {
        heading: "Identificação dos Fatores de Causa (Fatores PSPP)",
        text: "Para um tratamento eficaz, o veterinário deve classificar as causas da otite. Os *fatores predisponentes* incluem conformação anatômica (orelhas pendulares como em Basset Hounds e Spaniels) e umidade excessiva (cães nadadores). Os *fatores primários* iniciam a inflamação, sendo a dermatite atópica e a alergia alimentar as mais comuns (~80% dos casos de otite crônica bilateral). Os *fatores secundários* são infecções oportunistas por bactérias (Staphylococcus pseudintermedius, Pseudomonas aeruginosa) e leveduras (Malassezia pachydermatis). Os *fatores perpetuantes* mantêm a doença, como estenose crônica do canal e calcificação."
      },
      {
        heading: "Protocolo Diagnóstico por Exame Clínico e Citologia",
        text: "O exame clínico inicia-se pela otoscopia detalhada para inspecionar o conduto auditivo e avaliar a integridade da membrana timpânica (crucial para evitar ototoxicidade por medicamentos). A citologia do cerúmen auricular é o método diagnóstico rápido e obrigatório no consultório, identificando formas bacterianas (cocos ou bastonetes) e leveduras. Culturas e testes de sensibilidade a antimicrobianos (antibiograma) são reservados para otites bilaterais crônicas e casos resistentes refratários à terapia inicial."
      }
    ],
    listTitle: "Condutas Terapêuticas Recomendadas",
    listItems: [
      "Limpeza profunda inicial do conduto auditivo com ceruminolíticos adequados no consultório.",
      "Uso de gotas arquivos combinadas contendo antibiótico, antifúngico e corticoide de ação rápida.",
      "Tratamento de suporte sistêmico com analgésicos e anti-inflamatórios em condutos severamente estenosados.",
      "Identificação obrigatória e tratamento da dermatopatia de base (como atopia) para evitar recidivas cíclicas."
    ],
    tableHeader: ["Agente Citológico", "Aspecto Microscópico", "Classe de Fármaco de Escolha"],
    tableRows: [
      ["Malassezia pachydermatis", "Estruturas em formato de 'pegada' ou 'garrafa' azuladas.", "Antifúngicos tópicos (Miconazol, Cetoconazol, Posaconazol)."],
      ["Cocos bacterianos", "Pequenas esferas organizadas em cadeias ou agrupamentos.", "Antibióticos de espectro médio (Gentamicina, Neomicina)."],
      ["Bastonetes bacterianos", "Estruturas cilíndricas alongadas (Pseudomonas comum).", "Antibióticos potentes (Polimixina B, Enrofloxacina, Marbofloxacina)."]
    ]
  },
  {
    title: "Dermatite atópica em cães: opções de tratamento e manejo ambiental",
    summary: "Guia completo sobre a dermatite atópica canina (DAC), detalhando critérios diagnósticos de Favrot, terapia imunomoduladora moderna e controle ambiental profilático.",
    intro: "A dermatite atópica canina (DAC) é uma afecção cutânea inflamatória crônica e pruriginosa de base genética. É caracterizada pela hipersensibilidade (produção excessiva de anticorpos IgE) contra alérgenos ambientais comuns, como ácaros da poeira (Dermatophagoides spp.), pólens de gramíneas e esporos de fungos. A barreira cutânea alterada facilita a penetração desses alérgenos e induz inflamação crônica.",
    sections: [
      {
        heading: "Critérios de Favrot e Diagnóstico Diferencial",
        text: "O diagnóstico da DAC é essencialmente clínico e de exclusão, baseando-se nos critérios descritos por Favrot. Esses critérios incluem: idade de início inferior a 3 anos, prurido inicial sem lesões visíveis, cão que vive predominantemente em ambiente interno, prurido responsivo a glicocorticoides, otite externa bilateral, pododermatite bilateral e ausência de envolvimento da margem auricular. Antes do diagnóstico definitivo, o clínico deve excluir outras causas de prurido, como DAPP (Dermatite Alérgica à Picada de Pulgas), alergia alimentar e infecções parasitárias (sarna sarcóptica)."
      },
      {
        heading: "Opções Terapêuticas Modernas e Controle de Crise",
        text: "O manejo da DAC requer uma abordagem multimodal. Fármacos tradicionais, como os glicocorticoides, são eficazes na remissão rápida de crises agudas, mas causam sérios efeitos colaterais a longo prazo. As opções modernas de controle contínuo incluem imunomoduladores seletivos de ação rápida, como o Oclacitinib (inibidor de Janus Quinase - JAK, que bloqueia a citocina do prurido IL-31) e o anticorpo monoclonal direcionado Loki (Lokivetmab), que neutraliza seletivamente a IL-31 com mínimos efeitos colaterais sistêmicos."
      }
    ],
    listTitle: "Manejo da Barreira Cutânea e Ambiente",
    listItems: [
      "Banhos semanais com xampus hidratantes ou fitoterápicos à base de ceramidas para restaurar a barreira lipídica da pele.",
      "Suplementação oral contínua com ácidos graxos essenciais (Ômega-3 e Ômega-6) para diminuir a inflamação dérmica.",
      "Controle profilático rígido contra pulgas e carrapatos para evitar reações cruzadas e piora do quadro inflamatório.",
      "Redução da carga de ácaros no ambiente através da remoção de tapetes, cortinas e uso frequente de aspiradores de pó."
    ],
    tableHeader: ["Classe Farmacológica", "Mecanismo de Ação", "Vantagens Clínicas / Limitações"],
    tableRows: [
      ["Oclacitinib (Apoquel)", "Inibição de enzimas JAK-1 e JAK-3, bloqueando a sinalização de IL-31.", "Ação rápida (remissão em 24h), seguro para uso crônico sem efeitos esteroidais."],
      ["Lokivetmab (Cytopoint)", "Anticorpo monoclonal que se liga e inibe a IL-31 livre.", "Administração injetável mensal, excelente para cães jovens ou refratários a comprimidos."],
      ["Ciclosporina", "Inibição da ativação de células T via calcineurina.", "Altamente eficaz para controle de DAC grave, mas demora de 4 a 6 semanas para efeito pleno."]
    ]
  },
  // CATEGORIA 2: Medicina preventiva, vacinação e saúde única
  {
    title: "Calendário de vacinação para cães no Brasil: protocolos essenciais e opcionais",
    summary: "Guia completo sobre vacinas essenciais e opcionais para cães no cenário brasileiro, com base nas diretrizes do WSAVA e condutas de imunização eficazes.",
    intro: "A imunização ativa por meio de vacinas é a ferramenta profilática mais importante na medicina veterinária preventiva de pequenos animais. No Brasil, o clínico enfrenta o desafio de estabelecer calendários vacinais adaptados a alta prevalência de doenças infecciosas tropicais graves, seguindo as diretrizes científicas globais como as estabelecidas pelo WSAVA (World Small Animal Veterinary Association).",
    sections: [
      {
        heading: "Vacinas Essenciais (Core Vaccines)",
        text: "As vacinas essenciais são aquelas que todos os cães, independentemente da localização geográfica ou estilo de vida, devem receber para proteção contra patologias graves e letais. São representadas pelas vacinas múltiplas (V8 ou V10), que protegem contra o vírus da Cinomose, Parvovírus Canino (CPV), Adenovírus Canino (CAV-1 e CAV-2) e Parainfluenza. No Brasil, a vacina contra a Raiva é obrigatória por lei nacional de controle de zoonoses urbanas, devendo ser aplicada em dose única anual a partir dos 3-4 meses de idade."
      },
      {
        heading: "Vacinas Opcionais e Condicionais (Non-Core Vaccines)",
        text: "As vacinas opcionais são aplicadas conforme o estilo de vida do animal, localização geográfica e avaliação de risco de exposição pelo médico-veterinário. Entre as principais disponíveis no mercado brasileiro, destacam-se a vacina contra a Traqueobronquite Infecciosa Canina ('tosse dos canis', protegendo contra Bordetella bronchiseptica e vírus da parainfluenza), a vacina contra a Giardíase e a vacina recombinante contra a Leishmaniose Visceral Canina, que exige triagem sorológica prévia negativa e é recomendada em áreas endêmicas de transmissão ativa."
      }
    ],
    listTitle: "Diretrizes WSAVA de Imunização para Filhotes",
    listItems: [
      "O protocolo vacinal inicial de filhotes deve começar entre 6 a 8 semanas de idade.",
      "Devem ser administradas doses consecutivas da vacina múltipla com intervalos de 3 a 4 semanas entre elas.",
      "A última dose da vacina múltipla em filhotes deve ser obrigatoriamente administrada a partir ou após as 16 semanas de idade para contornar a interferência dos anticorpos maternos (MDA).",
      "O reforço vacinal completo deve ser realizado 12 meses após a última dose aplicada no filhote."
    ],
    tableHeader: ["Vacina Canina", "Classificação (WSAVA)", "Protocolo Recomendado (Brasil)"],
    tableRows: [
      ["V8 / V10 (Múltipla)", "Essencial (Core)", "3 doses em filhotes (intervalo 21-28 dias). Reforço anual ou trienal."],
      ["Antirrábica", "Essencial (Core/Lei)", "Dose única aos 3-4 meses em filhotes. Reforço anual obrigatório."],
      ["Gripe / Tosse dos Canis", "Opcional (Non-core)", "Filhotes: 2 doses injetáveis ou dose única intranasal. Reforço anual."],
      ["Leishmaniose (Recombinante)", "Condicional / Opcional", "3 doses (intervalo 21 dias) após teste rápido DPP negativo. Reforço anual."]
    ]
  },
  {
    title: "Vacinação de gatos em apartamento: o que é realmente necessário?",
    summary: "Descubra as particularidades da imunização em felinos que residem em apartamentos, analisando os riscos de contágio e a seleção racional de vacinas essenciais.",
    intro: "Muitos tutores acreditam equivocadamente que gatos que vivem exclusivamente em apartamentos (indoor) não necessitam de vacinação sistemática por estarem protegidos de fatores de risco de rua. No entanto, infecções virais severas podem atingir esses felinos através de fômites (solas de sapatos, roupas) ou visitas esporádicas a hotéis de pets, consultórios clínicos e banhos, exigindo uma imunização racional direcionada.",
    sections: [
      {
        heading: "A Vacina Tríplice ou Quádrupla (F3 / F4 Felina)",
        text: "Para gatos de apartamento, a vacinação essencial contra as principais doenças infecciosas agudas é mandatória. A vacina Tríplice Felina (V3) protege contra três patologias graves: a Panleucopenia Felina (Parvovírus felino, extremamente estável no ambiente e carregado por fômites), a Rinotraqueíte Infecciosa Felina (Herpesvírus Felino tipo 1 - FHV-1) e a Calicivirose Felina (Calicivírus Felino - FCV). A vacina Quádrupla Felina (V4) adiciona a proteção contra a bactéria Chlamydia felis, causadora de quadros severos de conjuntivite clínica em felinos domésticos."
      },
      {
        heading: "A Questão da Vacina Quíntupla e o Vírus da FeLV",
        text: "A vacina Quíntupla Felina (V5) inclui a imunização contra o vírus da Leucemia Felina (FeLV), uma retrovirose altamente debilitante e fatal transmitida pelo contato direto e íntimo entre gatos ('doença do beijo'). Para gatos que vivem estritamente isolados em apartamentos sem acesso a outros animais ou áreas externas de risco, a vacina FeLV pode ser classificada como condicional. No entanto, as diretrizes da AAFP (American Association of Feline Practitioners) recomendam que todos os filhotes de felinos recebam a vacina FeLV pelo menos na série inicial, devido a possíveis mudanças futuras no estilo de vida do animal."
      }
    ],
    listTitle: "Regras de Ouro da Imunização de Gatos",
    listItems: [
      "Inicie o protocolo de filhotes a partir das 6 a 8 semanas de idade com a F3 ou F4 felina.",
      "Filhotes necessitam de doses consecutivas a cada 3-4 semanas, finalizando obrigatoriamente aos 4 meses de idade.",
      "Teste diagnóstico obrigatório para FeLV e FIV deve ser realizado antes de aplicar a primeira dose da vacina V5.",
      "A vacinação contra a raiva é essencial para felinos indoor no Brasil devido à possibilidade de acidentes com morcegos invasores."
    ],
    tableHeader: ["Tipo de Vacina", "Componentes Inclusos", "Relevância para Gato Indoor"],
    tableRows: [
      ["Tríplice Felina (V3)", "Panleucopenia, Rinotraqueíte e Calicivirose.", "Altamente Obrigatória (Vírus resistentes no ambiente)."],
      ["Quádrupla Felina (V4)", "Adiciona proteção contra Clamidiose (Chlamydia felis).", "Recomendada (Especialmente se houver múltiplos gatos no local)."],
      ["Quíntupla Felina (V5)", "Adiciona proteção contra Leucemia Felina (FeLV).", "Recomendada na fase juvenil. Opcional em adultos indoor sob isolamento estrito."]
    ]
  },
  {
    title: "Vermifugação em cães e gatos: quando repetir e quais riscos evitar",
    summary: "Guia técnico de vermifugação em pequenos animais, detalhando os riscos de resistência parasitária, zoonoses urbanas e o papel da coproparasitologia periódica.",
    intro: "O controle sistemático de endoparasitas gastrointestinais (helmintos e protozoários) em cães e gatos é um pilar da clínica médica e um tema crítico de saúde única (One Health). O manejo antiparasitário visa prevenir patologias digestivas, nutricionais e sistêmicas graves nos pets, além de proteger as famílias humanas contra zoonoses urbanas altamente prevalentes, como a Larva Migrans Cutânea e a Toxocaríase.",
    sections: [
      {
        heading: "Transmissão e Riscos das Principais Zoonoses",
        text: "Os parasitas gastrointestinais mais comuns de cães e gatos include os helmintos nematódeos (Ancylostoma caninum, Toxocara canis, Toxocara cati) e cestódeos (Dipylidium caninum). A contaminação ocorre pela ingestão de ovos ou larvas infectantes presentes no solo de praças públicas, quintais ou pela via transmamária/transplacentária em filhotes. O Ancylostoma é o agente etiológico clássico do 'bicho geográfico' (Larva Migrans Cutânea) em humanos, enquanto a ingestão acidental de ovos de Toxocara canis leva à Larva Migrans Visceral, que pode provocar lesões hepáticas, pulmonares e cegueira ocular em crianças."
      },
      {
        heading: "A Mudança para a Vermifugação Consciente e Resistência",
        text: "A antiga prática de prescrever anti-helmínticos cegamente a cada 3 ou 6 meses sem critério diagnóstico está sendo desaconselhada pelas diretrizes internacionais devido ao risco iminente de induzir resistência parasitária. O protocolo moderno preconiza a realização rotineira de exames coproparasitológicos periódicos (como os métodos de flutuação de Willis e sedimentação de Hoffmann) para prescrever o princípio ativo direcionado (Pirantel, Praziquantel, Febantel, Fenbendazol, Moxidectina) apenas quando houver infestação diagnosticada, limitando a vermifugação preventiva para grupos de risco específicos (como filhotes e fêmeas gestantes)."
      }
    ],
    listTitle: "Protocolo Clínico Recomendado para Vermifugação",
    listItems: [
      "Filhotes de cães e gatos devem ser vermifugados profilaticamente quinzenalmente a partir de 2 semanas até as 8 semanas de idade.",
      "Fêmeas lactantes devem ser vermifugadas simultaneamente com suas respectivas ninhadas para evitar a reinfecção por via mamária.",
      "Realização de exame coproparasitológico anula a necessidade de vermifugações semestrais desnecessárias em animais adultos saudáveis.",
      "Uso de vermífugos de amplo espectro eficazes contra nematódeos e cestódeos simultaneamente em áreas de alto desafio epidemiológico."
    ],
    tableHeader: ["Classe de Parasita", "Exemplos Comuns", "Princípio Ativo de Escolha"],
    tableRows: [
      ["Nematódeos (Vermes cilíndricos)", "Toxocara canis, Ancylostoma caninum.", "Pirantel, Febantel, Fenbendazol, Ivermectina, Selamectina."],
      ["Cestódeos (Vermes planos)", "Dipylidium caninum (transmitido por pulgas).", "Praziquantel."],
      ["Protozoários intestinais", "Giardia duodenalis, Isospora spp.", "Fenbendazol, Metronidazol, Toltrazuril."]
    ]
  },
  {
    title: "Leptospirose: papel do médico-veterinário na prevenção em áreas urbanas",
    summary: "Revisão clínica e epidemiológica da leptospirose em cães, destacando a importância diagnóstica, manejo terapêutico rápido e o papel da vacinação profilática.",
    intro: "A leptospirose é uma zoonose infectocontagiosa de distribuição mundial, causada por espiroquetas patogênicas do gênero Leptospira. Afeta cães domésticos e diversas espécies de animais silvestres e de produção, tendo os roedores urbanos (principalmente o Rattus norvegicus) como principais reservatórios biológicos. No Brasil, o médico-veterinário atua na linha de frente da prevenção e controle desta patologia urbana de alto impacto na saúde pública.",
    sections: [
      {
        heading: "Transmissão Fisiopatogênica e Quadro Clínico Canino",
        text: "Os cães adquirem a infecção pelo contato direto com urina de animais infectados ou água e solo contaminados por leptospiras em períodos de alagamento urbano. As bactérias penetram através de mucosas intactas ou pele escoriada, multiplicando-se rapidamente na corrente sanguínea. A colonização bacteriana ocorre principalmente nos rins e no fígado, provocando nefropatia e hepatopatia severas. O paciente canino manifesta anorexia, vômitos, icterícia severa, petéquias e insuficiência renal aguda oligúrica ou anúrica, caracterizando a Síndrome de Weil."
      },
      {
        heading: "Diagnóstico Laboratorial e Profilaxia Eficaz",
        text: "O diagnóstico baseia-se em exames de soroaglutinação microscópica (SAM) – o teste padrão de referência para identificar os sorovares envolvidos (como Icterohaemorrhagiae e Canicola) – e na detecção molecular do DNA bacteriano por PCR na urina ou sangue. O tratamento envolve antibioticoterapia precoce com penicilina g cristalina na fase de leptospiremia, seguida por doxiciclina por 14 dias para eliminar o estado de portador renal crônico. A profilaxia baseia-se em vacinações periódicas bilaterais (semestrais ou anuais em animais de risco) com bacterinas múltiplas."
      }
    ],
    listTitle: "Condutas de Segurança e Saúde Única (Zoonose)",
    listItems: [
      "Isolamento obrigatório do cão internado suspeito com sinalização expressiva de 'risco biológico' no leito clínico.",
      "Uso indispensável de Equipamento de Proteção Individual (EPIs), incluindo luvas impermeáveis e óculos de proteção durante a manipulação do paciente.",
      "Desinfecção diária de baias com soluções à base de cloro ativo (hipoclorito de sódio a 1%), altamente eficaz contra leptospiras.",
      "Orientação dos tutores sobre o controle ambiental de roedores na residência e proteção contra águas de enchentes."
    ],
    tableHeader: ["Fase Terapêutica", "Fármaco Recomendado", "Objetivo Clínico / Ação"],
    tableRows: [
      ["Fase Inicial (Hematogênica)", "Ampicilina ou Penicilina G Potássica IV.", "Combater a bacteremia ativa aguda e limitar os danos hepáticos e renais imediatos."],
      ["Fase de Eliminação Renal", "Doxiciclina oral por 14 dias consecutivo.", "Eliminar a colonização das leptospiras nos túbulos renais e extinguir a eliminação urinária."],
      ["Suporte Renal Ativo", "Fluidoterapia rigorosa + Diuréticos se anúria.", "Preservação da perfusão dos néfrons e restabelecimento do débito urinário residual."]
    ]
  },
  // CATEGORIA 3: Produção animal, sanidade e agronegócio
  {
    title: "principais doenças em bovinos de leite e estratégias de controle",
    summary: "Revisão detalhada das afecções de rebanhos leiteiros, focando no complexo mastite, doenças metabólicas e protocolos preventivos na fazenda.",
    intro: "A eficiência de rebanhos leiteiros depende diretamente do controle sanitário rigoroso e do manejo higiênico e nutricional preventivo. As principais perdas econômicas e produtivas na bovinocultura de leite contemporânea estão associadas ao complexo de mastite bovina, doenças metabólicas puerperais e enfermidades infecciosas reprodutivas, exigindo uma visão preventiva e sistêmica do médico-veterinário.",
    sections: [
      {
        heading: "Doenças Metabólicas do Puerpério e Início de Lactação",
        text: "O período de transição (3 semanas antes a 3 semanas após o parto) é o momento mais crítico para o desenvolvimento de distúrbios metabólicos na vaca leiteira de alta produção. O Balanço Energético Negativo (BEN) decorrente da queda no consumo de matéria seca e do pico de lactação pode induzir a Cetose (acúmulo de corpos cetônicos no sangue). A Hipocalcemia Puerperal (febre do leite), causada pela rápida mobilização de cálcio para o colostro sem resposta paratireoidiana adequada, provoca paralisia flácida e decúbito esternal ou lateral imediato no pós-parto."
      },
      {
        heading: "Enfermidades Infecciosas e Reprodutivas de Rebanho",
        text: "Doenças como Diarreia Viral Bovina (BVD), Rinotraqueíte Infecciosa Bovina (IBR) e Leptospirose têm alto impacto na fertilidade do rebanho, causando perdas por abortamento, repetição de cio e natimortalidade. O monitoramento epidemiológico periódico por sorologia, aliado a programas sistemáticos de vacinação de bezerras e vacas adultas, é a principal estratégia de controle desses patógenos que afetam a eficiência reprodutiva."
      }
    ],
    listTitle: "Estratégias de Controle em Rebanhos Leiteiros",
    listItems: [
      "Formulações dietéticas pré-parto aniônicas para estimular a mobilização de cálcio ósseo e prevenir hipocalcemia puerperal.",
      "Monitoramento rotineiro de corpos cetônicos na urina ou sangue de vacas recém-paridas para detecção precoce de cetose subclínica.",
      "Protocolos rígidos de biosseguridade com controle de tráfego de pessoas e veículos nas instalações produtoras da fazenda.",
      "Separação sistemática e descarte ético orientado de animais positivos para patologias crônicas de rebanho (como Leucose Enzoótica Bovina)."
    ],
    tableHeader: ["Patologia Metabólica", "Gatilho Fisiológico", "Abordagem Preventiva Eficaz"],
    tableRows: [
      ["Hipocalcemia (Febre do Leite)", "Rápida drenagem de cálcio para produção de colostro no pós-parto imediato.", "Uso de dietas aniônicas no pré-parto por 21 dias para acidificar o sangue periférico."],
      ["Cetose Subclínica / Clínica", "Mobilização excessiva de gordura corporal por balanço energético negativo.", "Fornecimento de propilenoglicol oral e dietas equilibradas com teores de fibra ideais."],
      ["Acidose Ruminal Aguda", "Ingestão excessiva de carboidratos rapidamente fermentáveis (concentrados).", "Fornecimento de tamponantes na ração e transição lenta de dietas no período."]
    ]
  },
  {
    title: "mastite bovina: diagnóstico, tratamento e prevenção na fazenda",
    summary: "Guia completo sobre a mastite bovina clínica e subclínica, detalhando o teste de CMT, rotina de ordenha higiênica e estratégias profiláticas da terapia de vaca seca.",
    intro: "A mastite bovina (inflamação da glândula mamária) é a afecção de maior impacto econômico e sanitário no agronegócio produtor de leite no mundo. Classifica-se clinicamente em mastite clínica (com alterações visíveis na glândula ou no leite) e subclínica (sem sintomas visíveis, mas com alta contagem de células somáticas e queda silenciosa na produção de leite). A prevenção baseia-se na rotina higiênica de ordenha e no manejo integrado.",
    sections: [
      {
        heading: "Diferenciação Epidemiológica e Diagnóstico na Prática",
        text: "Epidemiologicamente, a mastite é dividida em contagiosa e ambiental. A mastite contagiosa (causada por Staphylococcus aureus e Streptococcus agalactiae) é transmitida entre vacas durante o processo de ordenha por meio de fômites e equipamentos de ordenha mal higienizados. A mastite ambiental (causada por Escherichia coli e Streptococcus uberis) decorre da contaminação do teto no ambiente da fazenda (lama, dejetos). O diagnóstico da mastite clínica inicia-se pelo teste do caneco de fundo preto (visualização de grumos, sangue ou pus). A mastite subclínica é identificada rotineiramente pelo California Mastitis Test (CMT) ou por analisadores de Contagem de Células Somáticas (CCS)."
      },
      {
        heading: "A Importância da Terapia da Vaca Seca e Rotina de Ordenha",
        text: "O controle integrado da mastite segue o plano clássico de manejo de tetos. A rotina higiênica de ordenha deve incluir o pré-dipping (limpeza do teto e desinfecção com soluções à base de cloro ou iodo por 30 segundos, seguida de secagem com papel toalha individual) e o pós-dipping (desinfecção imediata pós-ordenha para selar o esfíncter do teto contra bactérias ambientais). A terapia de vaca seca, que envolve a infusão intramamária de antibióticos de longa ação no momento da interrupção da lactação combinada com selantes de teto, previne novas infecções no período não produtivo."
      }
    ],
    listTitle: "Passos do Controle de Mastite na Ordenha",
    listItems: [
      "Descarte imediato dos primeiros jatos de leite no caneco de fundo preto para detecção de mastite clínica.",
      "Higienização e desinfecção corretas dos tetos (pré-dipping) utilizando papel toalha descartável de uso individual.",
      "Ajuste e manutenção periódica da pressão de vácuo das ordenhadeiras mecânicas para evitar hiperqueratose do teto.",
      "Aplicação de desinfetante pós-ordenha (pós-dipping) com alto poder residual de selamento biológico."
    ],
    tableHeader: ["Agente Etiológico", "Origem Epidemiológica", "Estratégia de Controle Primário"],
    tableRows: [
      ["Staphylococcus aureus", "Contagiosa (Tetos infectados, teteiras).", "Linha de ordenha estrita, tratamento adequado ou descarte clínico."],
      ["Escherichia coli", "Ambiental (Lama, fezes, piquetes úmidos).", "Limpeza de áreas de descanso, pré-dipping rigoroso e teto seco."],
      ["Streptococcus uberis", "Mista (Ambiente e contato na ordenha).", "Higiene da ordenha e correto manejo de dejetos nos currais."]
    ]
  },
  {
    title: "biosseguridade em granjas de aves: práticas essenciais para evitar surtos",
    summary: "Aprenda as práticas de biosseguridade em granjas avícolas comerciais, abordando controle sanitário estrito contra Influenza Aviária e Salmonella.",
    intro: "A avicultura industrial brasileira é reconhecida globalmente por seu alto padrão sanitário e biosseguridade estrita. Devido a ameaças iminentes de patógenos devastadores como a Influenza Aviária de Alta Patogenicidade (IAAP) e o complexo de Salmonelose Aviária, a adoção de barreiras físicas e rotinas sanitárias rigorosas é a única garantia de sustentabilidade econômica para o agronegócio avícola.",
    sections: [
      {
        heading: "Controle de Tráfego e Barreiras Sanitárias Estruturais",
        text: "O pilar inicial da biosseguridade consiste no isolamento físico da granja por cercas perimetrais eficazes e no controle rígido de tráfego de pessoas e veículos. Todos os caminhões que adentram o perímetro da granja (caminhões de ração, entrega de pintinhos de um dia) devem passar obrigatoriamente por arcos de desinfecção com pulverização de amônia quaternária ou glutaraldeído. Funcionários e visitantes devem passar pelo vestiário de barreira sanitária, onde realizam banho obrigatório e troca de roupas e calçados por vestimentas estéreis exclusivas da área limpa."
      },
      {
        heading: "Qualidade da Água, Ração e Manejo de Cama",
        text: "A água e a ração oferecidas às aves representam potenciais fontes de contaminação se não forem monitoradas sistematicamente. A água deve passar por processos rigorosos de cloração ativa (mantendo cloro residual entre 2 e 5 ppm). A cama de aviário deve ser tratada adequadamente entre os lotes de produção (por fermentação ou aplicação de cal) para reduzir a carga microbiológica e evitar a propagação de patógenos intestinais oportunistas como a Salmonella enteritidis e Salmonella typhimurium."
      }
    ],
    listTitle: "Regras Fundamentais de Biosseguridade Avícola",
    listItems: [
      "Manter telas antipássaros perfeitamente vedadas nos galpões avícolas comerciais para evitar contato direto com aves silvestres migratórias.",
      "Respeitar o período de vazio sanitário obrigatório de no mínimo 10 a 15 dias entre a saída de um lote e o alojamento do próximo.",
      "Monitoramento laboratorial semestral rigoroso do rebanho para controle e detecção de Salmonella aviária.",
      "Destinação adequada e biosegura de carcaças de aves mortas por compostagem ou desidratação na própria propriedade."
    ],
    tableHeader: ["Ação Preventiva", "Agente Sanitário Utilizado", "Benefício Principal para Granja"],
    tableRows: [
      ["Arco de Pulverização de Veículos", "Amônia Quaternária, Glutaraldeído.", "Descontaminação imediata de pneus e chassi de caminhões."],
      ["Cloração da Água de Bebida", "Cloro residual ativo (2-5 ppm).", "Inativação de vírus e bactérias patogênicas na tubulação de bebida."],
      ["Barreira de Vestiário (Ducha)", "Banho obrigatório e troca de vestimenta.", "Eliminação de patógenos trazidos no corpo ou roupas de fora da granja."]
    ]
  },
  {
    title: "bem‑estar animal na pecuária de corte: tendências de mercado e exigências de frigoríficos",
    summary: "Explore as diretrizes de bem-estar animal aplicadas à pecuária de corte brasileira, abordando técnicas de manejo racional, redução de estresse e ganhos de produtividade.",
    intro: "O bem-estar animal (BEA) na pecuária de corte brasileira deixou de ser apenas um apelo filosófico e tornou-se um requisito econômico e mercadológico fundamental. Consumidores e grandes frigoríficos exportadores exigem o cumprimento de diretrizes rígidas de manejo racional. Estudos comprovam que animais manejados sem estresse apresentam melhores índices zootécnicos e geram carcaças sem hematomas, melhorando o rendimento da carne.",
    sections: [
      {
        heading: "O Impacto do Manejo Estressante na Qualidade da Carne",
        text: "Manejos estressantes que envolvem o uso de ferrões elétricos, gritos, agressões físicas e cães pastores no curral de manejo provocam a liberação excessiva de hormônios do estresse (cortisol e adrenalina) na circulação do animal. Isso resulta no rápido consumo das reservas de glicogênio muscular antes do abate. Sem glicogênio suficiente para conversão em ácido lático pós-abate, o pH final da carne permanece elevado (acima de 5.8), resultando no defeito tecnológico conhecido como carne DFD (Dark, Firm, Dry - escura, firme e seca), que possui baixa vida de prateleira e rejeição no mercado varejista."
      },
      {
        heading: "Manejo Racional e Instalações Currais Inteligentes",
        text: "A transição para a pecuária sustentável exige a implementação do manejo racional e silencioso de bovinos. Os currais de manejo devem ser projetados utilizando conceitos de comportamento animal descritos por Temple Grandin. Currais circulares com paredes fechadas (seringas e troncos curvos) previnem que o animal veja distrações externas ou sombras no chão que possam assustá-lo. A eliminação do uso de aguilhões elétricos e a substituição por bandeiras plásticas coloridas reduzem drasticamente as taxas de hematomas e estresse no rebanho."
      }
    ],
    listTitle: "Diretrizes Práticas para Manejo com Alto Bem-Estar",
    listItems: [
      "Eliminação imediata do uso de aguilhões, bastões elétricos e uso de cães em currais de manejo de bovinos de corte.",
      "Adoção de bandeiras de lona flexível para guiar visualmente os animais de forma silenciosa e calma nos currais.",
      "Treinamento contínuo dos funcionários sobre zona de fuga e ponto de balanço do animal para manejo humanizado.",
      "Garantia de oferta contínua de água limpa e sombreamento adequado nos piquetes de engorda da fazenda."
    ],
    tableHeader: ["Ação de Manejo", "Consequência Fisiológica / Qualidade", "Ganhos Produtivos / Rendimento"],
    tableRows: [
      ["Eliminação de Aguilhão Elétrico", "Queda brusca na secreção de adrenalina pré-abate.", "Redução expressiva de carne DFD e diminuição de hematomas na carcaça."],
      ["Currais Curvos com Laterais Fechadas", "Redução do medo do animal por bloqueio visual de distrações.", "Facilidade no fluxo de manejo, reduzindo acidentes de trabalho com a equipe."],
      ["Embarque Lento e Calmo de Lotes", "Prevenção de lesões e traumas mecânicos nos bovinos.", "Melhoria do rendimento da carcaça limpa nos frigoríficos exportadores."]
    ]
  },
  // CATEGORIA 4: Especialidades veterinárias em alta
  {
    title: "como é a rotina do médico-veterinário em ultrassonografia",
    summary: "Conheça a rotina do ultrassonografista veterinário volante, abordando a preparação do paciente, as principais indicações clínicas e o mercado de diagnóstico por imagem.",
    intro: "A ultrassonografia tornou-se uma ferramenta diagnóstica indispensável na rotina da clínica médica veterinária de pequenos animais. Para o profissional especializado na área, o dia a dia exige um profundo conhecimento anatômico, habilidade técnica para manipulação do transdutor e grande capacidade de comunicação clínica para auxiliar os veterinários solicitantes.",
    sections: [
      {
        heading: "A Dinâmica do Trabalho Volante na Ultrassonografia",
        text: "Uma grande parcela dos veterinários especializados em diagnóstico por imagem atua de forma volante, deslocando-se com equipamentos portáteis modernos entre clínicas, consultórios e hospitais parceiros. Essa modalidade exige adaptabilidade a diferentes rotinas e infraestruturas, além de organização logística rigorosa para o transporte seguro de equipamentos de alto custo tecnológico. A rotina é dinâmica, com casos que variam de exames abdominais de rotina a triagens de emergência em pacientes politraumatizados (protocolo A-FAST e T-FAST)."
      },
      {
        heading: "Preparação do Paciente e Condução do Exame Abdominal",
        text: "Para obter imagens ultrassonográficas de alta qualidade diagnóstica, a correta preparação do paciente canino ou felino é fundamental. Isso inclui a tricotomia ampla do abdômen (raspagem dos pelos para evitar artefatos de ar) e a aplicação generosa de gel acústico. O jejum alimentar pré-exame de 8 a 12 horas é recomendado para reduzir o acúmulo de gás e fezes no trato gastrointestinal, fatores físicos que causam reflexão total das ondas sonoras e impedem a visibilização de órgãos adjacentes como adrenais e pâncreas."
      }
    ],
    listTitle: "Principais Indicações Clínicas para Ultrassom",
    listItems: [
      "Avaliação de dor abdominal aguda, suspeita de peritonite e presença de líquido livre em cavidade.",
      "Suspeita de obstrução mecânica gastrointestinal por corpo estranho radiotransparente (plásticos, tecidos).",
      "Estadiamento de pacientes oncológicos por avaliação de arquitetura tecidual do fígado, baço e linfonodos.",
      "Triagem emergencial ultrassonográfica rápida em pacientes com suspeita de trauma abdominal interno."
    ],
    tableHeader: ["Protocolo Emergencial", "Foco Diagnóstico Primário", "Ação de Intervenção Rápida"],
    tableRows: [
      ["A-FAST (Abdominal Fast)", "Pesquisa rápida de líquido livre (sangue, urina, exsudato) em 4 quadrantes.", "Direcionamento imediato para abdominocentese diagnóstica e estabilização."],
      ["T-FAST (Thoracic Fast)", "Avaliação rápida de cavidade pleural para pneumotórax ou efusão pleural.", "Drenagem torácica emergencial (toracocentese) para reestabelecer respiração."],
      ["Vet-BLUE (Pulmonar Fast)", "Detecção rápida de edema pulmonar cardiogênico ou consolidação pulmonar.", "Oxigenoterapia imediata e terapia com diuréticos de alça (furosemida)."]
    ]
  },
  {
    title: "raio‑X digital na clínica de pequenos animais: indicação e interpretação básica",
    summary: "Guia técnico sobre radiologia digital em pequenos animais, detalhando indicações clínicas de imagem, critérios de avaliação de silhueta cardíaca e radioproteção.",
    intro: "A radiologia digital revolucionou o diagnóstico por imagem na rotina veterinária, substituindo os antigos sistemas químicos reveladores por sensores eletrônicos de altíssima definição. Esse avanço tecnológico permite a obtenção instantânea de imagens de alta fidelidade e diminui consideravelmente a necessidade de repetição de exames e a exposição de funcionários à radiação ionizante.",
    sections: [
      {
        heading: "Indicações Clínicas e Posicionamento Radiográfico",
        text: "O exame radiográfico é o método inicial de eleição para avaliação de estruturas musculoesqueléticas (pesquisa de fraturas, osteoartrites, neoplasias ósseas como osteossarcoma) e de cavidade torácica (avaliação de padrão pulmonar e silhueta cardíaca). Para garantir a exatidão diagnóstica, são obrigatórias pelo menos duas projeções ortogonais (por exemplo, lateral esquerda/direita e ventrodorsal ou dorsoventral), permitindo uma avaliação tridimensional das estruturas anatômicas sobrepostas no exame."
      },
      {
        heading: "Parâmetros Clínicos de Avaliação Cardíaca (Método VHS)",
        text: "Na cardiologia veterinária, a radiografia torácica é fundamental para avaliar cardiomegalia crônica e sinais de insuficiência cardíaca congestiva esquerda (edema pulmonar). O método do Vertebral Heart Size (VHS) é a técnica quantitativa padrão recomendada para medir a silhueta cardíaca de forma objetiva. Consiste em medir o eixo maior e o eixo menor do coração no plano lateral, projetando esses valores sobre a coluna vertebral do animal a partir da quarta vértebra torácica (T4), obtendo o escore final de corpos vertebrais."
      }
    ],
    listTitle: "Normas Fundamentais de Radioproteção na Clínica",
    listItems: [
      "Uso obrigatório de Equipamentos de Proteção Individual (EPIs): avental plumbífero, protetor de tireoide e luvas plumbíferas.",
      "Uso indispensável do dosímetro individual pelo técnico operador para monitoramento mensal da dose cumulativa de radiação.",
      "Restrição de acesso à sala de exames apenas a pessoas indispensáveis ao procedimento de contenção do pet.",
      "Proibição absoluta da presença de menores de idade e mulheres gestantes na contenção ativa do paciente."
    ],
    tableHeader: ["Região Anatômica", "Projeções Radiográficas Padrão", "Principais Patologias Diagnosticáveis"],
    tableRows: [
      ["Tórax Canino/Felino", "Lateral Direita ou Esquerda e Ventrodorsal/Dorsoventral.", "Edema pulmonar, metástase pulmonar nodular, cardiomegalia, efusão pleural."],
      ["Esqueleto Apendicular", "Mediolateral e Craniocaudal / Caudocranial.", "Fraturas completas/incompletas, osteossarcoma agressivo, osteoartrose crônica."],
      ["Abdômen de Pequenos", "Lateral Direita e Ventrodorsal.", "Urolitíase de fosfato, corpos estranhos metálicos/ósseos, megacólon felino."]
    ]
  },
  {
    title: "principais cuidados na anestesia de cães braquicefálicos",
    summary: "Aprenda os requisitos anestésicos seguros para cães braquicefálicos (Pugs, Bulldogs), detalhando riscos respiratórios, intubação rápida e extubação tardia monitorada.",
    intro: "A anestesiologia de cães com conformação craniofacial braquicefálica (como Pugs, Bulldogs Francês e Inglês, Shih Tzus e Boxers) representa um dos maiores desafios de segurança clínica na medicina veterinária contemporânea. Esses animais apresentam alterações anatômicas respiratórias severas reunidas no complexo da Síndrome Respiratória dos Braquicefálicos (SRB).",
    sections: [
      {
        heading: "Particularidades Anatômicas da SRB",
        text: "Os animais afetados pela SRB apresentam quatro anomalias clássicas que reduzem drasticamente o fluxo de ar e dificultam a respiração contínua: estenose das narinas (narinas estreitas), prolongamento do palato mole (que obstrui parcialmente a glote), hipoplasia traqueal (traqueia com diâmetro reduzido) e eversão dos sáculos laríngeos decorrente da pressão negativa crônica. Essas características aumentam a resistência das vias aéreas e predispõem o animal a laringoespasmos fatais e hipóxia severa durante a indução e a recuperação anestésica."
      },
      {
        heading: "Protocolo Clínico Anestésico Seguro e Extubação Tardia",
        text: "A conduta anestésica segura em cães braquicefálicos exige pré-oxigenação sob máscara (100% de O2 por 5 minutos) antes da indução anestésica para aumentar a reserva de oxigênio pulmonar. A indução deve ser rápida com agentes de ação curta (Propofol ou Alaxolona) para garantir intubação orotraqueal imediata com tubo endotraqueal (sonda) de diâmetro menor que o habitualmente previsto para o peso do animal. A extubação só deve ser realizada tardiamente, ou seja, quando o cão apresentar deglutição ativa ativa e controle voluntário total da respiração e dos reflexos laríngeos."
      }
    ],
    listTitle: "Regras Críticas no Manejo de Braquicefálicos",
    listItems: [
      "Pré-oxigenação obrigatória sob máscara por 3 a 5 minutos antes da indução anestésica ativa.",
      "Preparo prévio de sondas endotraqueais de diâmetros menores (pelo menos dois números abaixo do normal para o peso).",
      "Uso profilático de anti-inflamatórios esteroides (dexametasona) para prevenir edema de laringe pós-intubação.",
      "Recuperação pós-anestésica em decúbito esternal com a cabeça estendida e língua tracionada até a extubação definitiva."
    ],
    tableHeader: ["Fase Anestésica", "Agente Recomendado / Conduta", "Justificativa Clínica de Segurança"],
    tableRows: [
      ["Medicação Pré-Anestésica", "Acepromazina de baixa dose + Opioide leve. Evitar sedação excessiva.", "Reduzir o estresse e a demanda por oxigênio sem deprimir severamente a respiração."],
      ["Indução Anestésica", "Propofol IV lento até perda do reflexo laringeo.", "Permitir intubação orotraqueal rápida e precisa, minimizando a fase sem controle de via aérea."],
      ["Recuperação Anestésica", "Manter intubado o máximo de tempo possível (extubação tardia).", "Garantir via aérea patente enquanto o tônus muscular da laringe se recupera por completo."]
    ]
  },
  {
    title: "como é trabalhar com animais silvestres: clínica, reabilitação e conservação",
    summary: "Guia completo sobre a rotina da medicina veterinária de animais silvestres e exóticos, abordando clínica de pets não convencionais, reabilitação ecológica e conservação.",
    intro: "A medicina veterinária de animais silvestres e exóticos é uma especialidade em expansão, atraindo profissionais apaixonados pela biologia da conservação e pelo atendimento clínico de pets não convencionais (aves, répteis e pequenos mamíferos como coelhos e roedores). A rotina exige conhecimento aprofundado da diversidade anatômica e fisiológica dessas espécies.",
    sections: [
      {
        heading: "Atendimento Clínico de Pets Não Convencionais",
        text: "No âmbito urbano, o veterinário de silvestres atua no atendimento clínico de animais de companhia exóticos (legalizados pelo IBAMA ou adquiridos legalmente). A anamnese nesses casos exige uma investigação detalhada do manejo ambiental e nutricional oferecido pelo tutor, visto que a imensa maioria das afecções clínicas diagnosticadas decorre de erros graves de manejo (como subnutrição, falta de exposição solar UV em répteis gerando doença metabólica óssea, e recintos inadequados)."
      },
      {
        heading: "Reabilitação Ecológica e Conservação em CETAS",
        text: "Em centros de reabilitação e triagem de animais silvestres (CETAS) ou zoológicos, a atuação do médico-veterinário é voltada à medicina populacional e reabilitação de fauna resgatada de tráfico ou acidentes. O foco consiste na contenção física segura das diversas espécies, manejo de feridas complexas, controle parasitário e triagem de patologias infecciosas zoonóticas para garantir que o animal esteja apto à soltura ou reintrodução ecológica coordenada no habitat natural."
      }
    ],
    listTitle: "Desafios do Manejo de Silvestres na Rotina",
    listItems: [
      "Desenvolver técnicas de contenção física segura e de mínimo estresse para evitar morte reflexa por miopatia de captura.",
      "Compreensão detalhada de tabelas de terapia de fluidos e doses alométricas devido à variação de taxa metabólica entre espécies.",
      "Legislação estrita do órgão fiscalizador (IBAMA/SISNAMA) sobre posse, tratamento e destinação de fauna nativa no Brasil.",
      "Uso de recintos de isolamento especializados com controle rígido de temperatura e umidade para recuperação pós-operatória."
    ],
    tableHeader: ["Classe Taxonômica", "Afecção Clínica Comum na Rotina", "Manejo Profilático Essencial"],
    tableRows: [
      ["Aves (Calopsitas/Papagaios)", "Hipovitaminose A por dieta exclusiva de sementes (girassol).", "Transição dietética para ração extrusada específica e vegetais escuros."],
      ["Répteis (Jabutis/Iguanas)", "Doença Metabólica Óssea (DMO) por falta de cálcio/luz UVB.", "Fornecimento de lâmpadas UV artificiais ou luz solar direta + Cálcio oral."],
      ["Pequenos Mamíferos (Coelhos)", "Estase gastrointestinal por falta de fibras na dieta.", "Fornecimento contínuo de feno de gramíneas de boa qualidade e água limpa."]
    ]
  },
  // CATEGORIA 5: Carreira, faculdade e residência
  {
    title: "matérias que você vai ver na faculdade de medicina veterinária",
    summary: "Conheça as principais matérias da grade curricular de Medicina Veterinária no Brasil, divididas entre o ciclo básico, pré-clínico e ciclo profissionalizante.",
    intro: "O curso de bacharelado em Medicina Veterinária no Brasil possui duração padrão de 5 anos (10 semestres) e exige dedicação integral dos estudantes. A grade curricular é ampla e multidisciplinar, projetada para capacitar o futuro profissional a atuar na clínica médica e cirúrgica, na sanidade animal, saúde pública e na produção animal sustentável.",
    sections: [
      {
        heading: "O Ciclo Básico: A Base Científica",
        text: "Os primeiros semestres do curso concentram-se na formação científica básica do estudante. As disciplinas nucleares incluem a Anatomia Veterinária (estudo macroscópico do esqueleto, musculatura e órgãos dos animais de produção e companhia), Histologia e Embriologia (estudo microscópico dos tecidos e desenvolvimento embrionário), Fisiologia Veterinária (estudo dos mecanismos biológicos normais dos sistemas orgânicos) e Bioquímica. Essas matérias formam o alicerce fundamental para a compreensão das futuras patologias clínicas."
      },
      {
        heading: "Ciclo Pré-Clínico e Profissionalizante",
        text: "A partir do meio do curso, o estudante ingressa nas disciplinas aplicadas e profissionalizantes. Destacam-se a Patologia Geral e Especial (estudo microscópico e macroscópico de lesões e causas de óbito animal), Farmacologia e Anestesiologia Veterinária (farmacocinética dos princípios ativos e controle de dor), Semiologia (técnicas de exame clínico físico e obtenção de sintomas) e a Clínica Médica e Cirúrgica de Pequenos e Grandes Animais, onde o aprendizado teórico é consolidado em práticas reais de ambulatório e bloco cirúrgico."
      }
    ],
    listTitle: "Áreas Temáticas Exigidas por Diretrizes do MEC",
    listItems: [
      "Ciências Biológicas e da Saúde: Anatomia, Fisiologia, Patologia, Farmacologia, Parasitologia, Microbiologia.",
      "Ciências Veterinárias Específicas: Clínica, Cirurgia, Anestesiologia, Diagnóstico por Imagem, Obstetrícia.",
      "Ciências de Produção Animal e Agronegócio: Zootecnia, Nutrição Animal, Genética e Melhoramento, Forragicultura.",
      "Saúde Única e Tecnologia de Alimentos: Inspeção e Higiene de Alimentos de Origem Animal, Epidemiologia e Zoonoses."
    ],
    tableHeader: ["Ano do Curso", "Disciplinas em Destaque", "Objetivo do Aprendizado"],
    tableRows: [
      ["1º e 2º Anos (Básico)", "Anatomia Animal I e II, Histologia, Fisiologia, Genética.", "Compreensão da estrutura celular e funcionamento biológico dos animais sadios."],
      ["3º e 4º Anos (Pré-clínico)", "Semiologia, Farmacologia, Patologia, Anestesiologia, Imagenologia.", "Aprendizado de técnicas de diagnóstico e ação terapêutica dos princípios ativos."],
      ["5º Ano (Profissionalizante)", "Clínicas Especializadas, Inspeção Sanitária, Estágio Supervisionado.", "Consolidação prática em ambiente clínico, industrial e saúde pública no mercado."]
    ]
  },
  {
    title: "áreas de residência em medicina veterinária mais disputadas e como se preparar",
    summary: "Guia completo sobre os programas de residência em Medicina Veterinária no Brasil, as áreas de especialização mais concorridas e como estruturar seus estudos para aprovação.",
    intro: "A Residência em Medicina Veterinária (Pós-Graduação Lato Sensu em regime de treinamento em serviço) é o caminho de especialização mais cobiçado por recém-formados no Brasil. Oferecida por universidades públicas e privadas de prestígio, a residência proporciona 60 horas semanais de prática intensiva sob supervisão docente, com bolsa financiada pelo MEC ou pelas próprias instituições.",
    sections: [
      {
        heading: "As Áreas de Residência Mais Disputadas do Mercado",
        text: "As especialidades da clínica médica e cirúrgica de pequenos animais concentram, historicamente, a maior concorrência nacional, com centenas de candidatos por vaga. As áreas líderes de demanda incluem: Clínica Médica de Pequenos Animais, Cirurgia e Anestesiologia de Pequenos Animais e Patologia Clínica Veterinária. Outras áreas com concorrência crescente são o Diagnóstico por Imagem, a Clínica Médica de Equinos e a Medicina de Animais Silvestres, refletindo as novas demandas mercadológicas."
      },
      {
        heading: "Como Estruturar uma Preparação Eficaz",
        text: "A aprovação em programas concorridos de residência (como USP, UNESP, ENARE e universidades federais) requer planejamento antecipado. As provas geralmente contam com três fases: prova objetiva de conhecimentos teóricos (básicos e específicos), prova prática ou discursiva de condutas clínicas e análise curricular rigorosa. O estudante deve iniciar a preparação resolvendo provas anteriores, organizando resumos das matérias de maior peso e construindo um currículo robusto com monitorias, iniciações científicas e publicações acadêmicas."
      }
    ],
    listTitle: "Pilares para Aprovação em Provas de Residência",
    listItems: [
      "Foco total na resolução sistemática de questões de provas anteriores de residências locais ou do ENARE.",
      "Estudo detalhado de matérias de saúde pública (Legislação do SUS, Epidemiologia e Zoonoses), que têm grande peso de pontuação.",
      "Construção ativa do currículo acadêmico desde os semestres iniciais da graduação (monitorias, estágios extracurriculares e publicação de resumos).",
      "Treinamento prático e revisão de condutas em emergência clínica e cirúrgica para as fases práticas presenciais."
    ],
    tableHeader: ["Fase do Processo Seletivo", "Foco de Avaliação Técnica", "Dica de Preparação Estratégica"],
    tableRows: [
      ["Prova Objetiva (Escrita)", "Conhecimentos teóricos de patologia, clínica, cirurgia e saúde pública.", "Uso da plataforma VetRank para treinar simulados cronometrados e monitorar metas."],
      ["Análise Curricular", "Pontuação de publicações científicas, estágios, monitorias e congressos.", "Organização dos certificados no padrão Lattes desde o início do curso acadêmico."],
      ["Prova Prática (Se houver)", "Condutas clínicas emergenciais, identificação de peças ou exames.", "Revisão prática de procedimentos e semiologia nos estágios finais de curso."]
    ]
  },
  {
    title: "temas mais cobrados em provas de residência e concursos em medicina veterinária",
    summary: "Descubra os tópicos recorrentes e de maior relevância estatística cobrados em concursos públicos e provas de residência de veterinária no Brasil.",
    intro: "Concursos públicos e provas de residência em Medicina Veterinária no Brasil apresentam grades programáticas extensas que cobram praticamente toda a bagagem da graduação. No entanto, análises estatísticas de provas anteriores demonstram que determinados temas são cobrados de forma recorrente, sendo fundamentais para garantir a nota de corte necessária para aprovação.",
    sections: [
      {
        heading: "Saúde Pública e Zoonoses: O Campeão de Questões",
        text: "Em concursos públicos municipais e estaduais (como para fiscais agropecuários e veterinários de vigilância sanitária) e em provas de residência geral (como as do ENARE), as disciplinas de Saúde Pública, Epidemiologia e Zoonoses concentram o maior peso de pontuação. Temas clássicos incluem a epidemiologia e controle da Leishmaniose Visceral Canina, Raiva no Brasil, Leptospirose, Esporotricose Felina e Tuberculose/Brucelose em bovinos de produção. A Legislação do SUS e o Código de Ética do CFMV são matérias de acerto obrigatório."
      },
      {
        heading: "Clínica Médica e Patologia Geral",
        text: "No bloco de conhecimentos específicos, as questões sobre Clínica Médica de Pequenos Animais costumam focar em afecções endocrinológicas (Cetoacidose Diabética, Cushing) e nefropatias (Doença Renal Crônica, urinálise e avaliação de densidade urinária). Em cirurgia e anestesia, os principais temas cobrados envolvem bloqueios locorregionais, fármacos pré-anestésicos (fenotiazínicos e agonistas alfa-2 adrenérgicos) e o manejo emergencial da Síndrome Dilatação Vólvulo Gástrica (DVG) canina."
      }
    ],
    listTitle: "Os 5 Tópicos de Maior Relevância em Provas",
    listItems: [
      "Zoonoses de Notificação Compulsória e Vigilância Epidemiológica nacional no Brasil.",
      "Leishmaniose Visceral Canina: Protocolo de diagnóstico rápido DPP confirmatório oficial.",
      "Anestesiologia Veterinária: Farmacologia dos anestésicos injetáveis e inalatórios e seus efeitos cardiovasculares.",
      "Patologia e Inspeção de Alimentos: Inspeção Federal (SIF) de carnes, leite, ovos, mel e controle de cisticercose.",
      "Código de Ética Profissional do Médico-Veterinário (Resolução 1138/CFMV): Deveres, proibições e infrações."
    ],
    tableHeader: ["Área Disciplinar", "Tema Específico de Alta Ocorrência", "Abordagem Exigida nas Questões"],
    tableRows: [
      ["Saúde Pública", "Epidemiologia e Ciclo de Transmissão de Leishmaniose e Raiva.", "Identificação de vetores, reservatórios urbanos primários e controle populacional."],
      ["Inspeção Sanitária", "Cisticercose bovina/suína e tuberculose em carcaças.", "Critérios de destinação de carcaças com lesões no abate (descarte, frio, salga)."],
      ["Clínica Médica", "Manejo e diferenciação de Doença Renal Crônica em cães e gatos.", "Parâmetros bioquímicos (creatinina, ureia, SDMA) e estagiamento IRIS."]
    ]
  },
  {
    title: "quanto ganha um médico-veterinário recém-formado no Brasil",
    summary: "Uma análise realista sobre remuneração na Medicina Veterinária, o piso salarial regulamentado e as áreas de maior ganho financeiro inicial no Brasil.",
    intro: "A questão financeira e o mercado de trabalho inicial são as principais preocupações dos graduandos e médicos-veterinários recém-formados no Brasil. A profissão conta com um mercado de trabalho amplo e diversificado, mas os salários iniciais variam muito conforme a região, o setor de atuação (público ou privado) e o nível de especialização técnica do profissional.",
    sections: [
      {
        heading: "O Piso Salarial Profissional da Lei Federal 4.950-A/66",
        text: "Pela Lei Federal nº 4.950-A de 1966, o piso salarial regulamentado para médicos-veterinários é vinculado ao salário mínimo federal, estipulando 6 salários mínimos para jornadas de 6 horas diárias de trabalho e 8,5 salários mínimos para jornadas de 8 horas diárias de trabalho técnico. No entanto, no mercado de trabalho privado real do país (especialmente em clínicas de pequenos animais de bairro e pequenos estabelecimentos), o piso salarial oficial muitas vezes não é plenamente implementado, forçando recém-formados a iniciar como profissionais autônomos ou prestadores de serviços terceirizados (plantonistas ou volantes)."
      },
      {
        heading: "Áreas com Maior Remuneração e Demanda de Trabalho",
        text: "A remuneração média inicial para profissionais que ingressam no mercado de pequenos animais gira em torno de R$ 2.500 a R$ 4.000 para recém-formados sem especialização. Por outro lado, setores com alta remuneração inicial e maior estabilidade incluem os concursos públicos para auditores fiscais federais agropecuários (MAPA), com remunerações iniciais que ultrapassam R$ 15.000 mensais, e a indústria de insumos de produção animal (nutrição e farmacêutica veterinária), onde posições na área de marketing técnico e vendas oferecem excelentes pacotes de compensação e bônus por produtividade."
      }
    ],
    listTitle: "Estratégias para Aumentar seu Valor Inicial no Mercado",
    listItems: [
      "Buscar especialização imediata através de Programas de Residência ou Pós-Graduações de alto renome prático.",
      "Capacitação em áreas de negócios complementares, como gestão financeira de clínicas e marketing ético profissional.",
      "Desenvolver networking profissional sólido por meio de participações ativas em congressos, simpósios e estágios especializados.",
      "Dominar inglês técnico e ferramentas computacionais para acesso a vagas corporativas globais em indústrias farmacêuticas."
    ],
    tableHeader: ["Área de Atuação Veterinária", "Média Salarial Inicial Estimada", "Perspectiva de Carreira a Longo Prazo"],
    tableRows: [
      ["Clínica Geral (Pequenos)", "R$ 2.500 a R$ 4.000", "Depende de especialização (dermato, cardio, imagem aumentam significativamente)."],
      ["Residência Veterinária", "Bolsa padrão MEC (aprox. R$ 4.100)", "Limitada a 2 anos. Altíssimo ganho de currículo e preparo prático real."],
      ["Auditor Fiscal (MAPA)", "R$ 15.000 a R$ 20.000", "Excelente. Estabilidade estatutária pública e progressão de carreira sólida."],
      ["Marketing Técnico (Indústria)", "R$ 5.000 a R$ 8.000 + Benefícios", "Alta. Crescimento corporativo, bônus por vendas e representação de mercado."]
    ]
  },
  // CATEGORIA 6: Empreendedorismo, clínica própria e marketing
  {
    title: "como abrir uma clínica veterinária: checklist completo de estrutura e documentos",
    summary: "Guia administrativo e checklist para médicos-veterinários empreendedores abrirem uma clínica própria, seguindo os requisitos de estrutura e regras do CFMV.",
    intro: "Abrir uma clínica veterinária própria é o grande sonho profissional de muitos médicos-veterinários que desejam empreender e consolidar sua marca no mercado pet brasileiro. No entanto, para além do amor aos animais e do conhecimento técnico de saúde animal, a estruturação de uma clínica exige planejamento administrativo rigoroso e conformidade legal com regras sanitárias e conselhos de classe.",
    sections: [
      {
        heading: "Adequação Sanitária e Exigências Legais",
        text: "No Brasil, a abertura de estabelecimentos veterinários é rigorosamente regulada pela Resolução nº 1275/2019 do Conselho Federal de Medicina Veterinária (CFMV). Esta resolução define a distinção estrutural obrigatória entre consultório, clínica e hospital veterinário. Toda clínica deve possuir, no mínimo, áreas físicas separadas para: recepção e sala de espera, consultório de atendimento, sala de preparo de pacientes, sala de cirurgia estéril (centro cirúrgico com pia de escovação e mesa cirúrgica de inox), área de recuperação/internação e sala de lavagem/esterilização de materiais cirúrgicos."
      },
      {
        heading: "Checklist de Documentação para Abertura Eficaz",
        text: "Antes de abrir as portas ao público, o proprietário deve obter uma série de licenças obrigatórias. O processo envolve a obtenção do CNPJ na Receita Federal, Inscrição Estadual e Municipal na prefeitura local, Alvará de Funcionamento e Localização emitido pela Prefeitura, Licença Sanitária expedida pela Vigilância Sanitária Municipal (VISA) e o Registro de Firma e Anotação de Responsabilidade Técnica (ART) junto ao CRMV correspondente ao estado de atuação."
      }
    ],
    listTitle: "Checklist de Equipamentos Mínimos Obrigatórios",
    listItems: [
      "Mesa de atendimento clínico lavável de inox de fácil higienização no consultório.",
      "Estetoscópio, termômetro clínico e balança digital calibrada para pesagem correta dos pets.",
      "Negatoscópio ou monitor digital para visualização de exames radiográficos veterinários.",
      "Autoclave calibrada para esterilização térmica e biológica segura de kits cirúrgicos descartáveis.",
      "Mesa cirúrgica de aço inoxidável com sistema de calha e suporte para fluidoterapia contínua."
    ],
    tableHeader: ["Órgão Regulador", "Documento Exigido", "Importância / Função Administrativa"],
    tableRows: [
      ["Prefeitura Municipal", "Alvará de Funcionamento e Licença de Instalação.", "Autorização legal para operação comercial do estabelecimento na zona urbana."],
      ["Vigilância Sanitária (VISA)", "Licença / Laudo Sanitário do Estabelecimento.", "Confirmação de que a clínica atende aos requisitos de controle higiênico e sanitário."],
      ["CRMV Estadual", "Anotação de Responsabilidade Técnica (ART) homologada.", "Garantia de que há um médico-veterinário registrado respondendo pela ética da clínica."]
    ]
  },
  {
    title: "custos fixos de uma clínica veterinária e como precificar consultas e cirurgias",
    summary: "Guia completo de gestão financeira para clínicas veterinárias, abordando controle de custos fixos/variáveis e precificação correta de serviços médicos.",
    intro: "Muitos médicos-veterinários talentosos enfrentam dificuldades financeiras em seus estabelecimentos simplesmente por não dominarem os conceitos fundamentais de gestão financeira de pequenas empresas. Compreender a diferença entre custos fixos e variáveis e dominar técnicas de precificação inteligente é a única forma de garantir a saúde financeira e lucratividade de sua clínica própria.",
    sections: [
      {
        heading: "Identificação Detalhada de Custos Fixos e Variáveis",
        text: "Os *custos fixos* de uma clínica veterinária são todas aquelas despesas operacionais recorrentes que não variam conforme o volume de atendimentos ou cirurgias realizadas no mês. Exemplos incluem o aluguel do imóvel, internet, folha de pagamento da equipe fixa (recepção, limpeza), pró-labore do proprietário e as taxas de conselhos profissionais. Já os *custos variáveis* são aquelas despesas atreladas diretamente ao volume de serviços prestados, representadas por insumos descartáveis utilizados em cirurgias e consultas (agulhas, seringas, anestésicos, fluidos), comissão de veterinários parceiros e as taxas de operadoras de cartões."
      },
      {
        heading: "Como Precificar Corretamente os Serviços Clínicos",
        text: "A precificação de consultas e cirurgias não deve basear-se exclusivamente no 'preço médio do concorrente ao lado'. O método correto de precificação envolve o cálculo do custo unitário do serviço (somando os insumos variáveis utilizados), a taxa de rateio dos custos fixos mensais com base nas horas operacionais ativas da clínica e a margem de lucro real desejada para a sustentabilidade da empresa. Para procedimentos complexos (como cirurgias sob anestesia geral), o preço deve refletir também o nível de risco clínico e o desgaste tecnológico dos equipamentos do centro cirúrgico."
      }
    ],
    listTitle: "Erros Comuns na Gestão Financeira Veterinária",
    listItems: [
      "Misturar as finanças pessoais (pró-labore) com o fluxo de caixa corporativo diário da clínica veterinária.",
      "Não registrar de forma detalhada o uso de pequenos insumos descartáveis na composição de custos de cirurgia.",
      "Ignorar a depreciação e necessidade de reinvestimento nos equipamentos de alto custo diagnóstico (como ultrassom e raio-x).",
      "Conceder descontos excessivos sem avaliar a real margem de contribuição restante para cobrir os custos fixos da empresa."
    ],
    tableHeader: ["Tipo de Despesa", "Exemplos Clássicos na Rotina", "Manejo e Otimização Financeira"],
    tableRows: [
      ["Custos Fixos", "Aluguel, salários administrativos, internet, taxas anuais CRMV.", "Monitorar e reduzir excessos, buscando manter controle sobre a estrutura básica da clínica."],
      ["Custos Variáveis", "Seringas, agulhas, vacinas, anestésicos, taxa de cartão.", "Comprar em lotes atacado, buscar fornecedores com preços competitivos sem perder qualidade."],
      ["Investimento (CAPEX)", "Aquisição de novo aparelho de anestesia inalatória ou ultrassom.", "Provisionar o valor mensal de depreciação do ativo para substituições futuras."]
    ]
  },
  {
    title: "SEO para clínicas veterinárias: como aparecer no topo do Google na sua cidade",
    summary: "Aprenda estratégias de SEO Local e marketing digital para posicionar sua clínica ou hospital veterinário no topo das buscas do Google na sua cidade.",
    intro: "No mercado contemporâneo, a primeira atitude de um tutor ao enfrentar uma emergência com seu pet ou buscar um médico-veterinário especializado é pesquisar no Google. Clínicas veterinárias que não estão posicionadas no topo dos resultados de busca orgânica local estão perdendo centenas de clientes mensais de forma silenciosa.",
    sections: [
      {
        heading: "O Poder do Google Meu Negócio (Perfil de Empresa no Google)",
        text: "Para negócios locais como clínicas veterinárias, o Perfil de Empresa no Google (antigo Google Meu Negócio) é a ferramenta de marketing digital gratuita mais potente de aquisição de clientes. Ter o perfil totalmente preenchido, com endereço atualizado, horário de atendimento (destacando plantões de emergência 24h) e fotos reais de alta qualidade da recepção e consultórios é o primeiro passo. O fator de classificação local mais poderoso do algoritmo é o volume e a consistência de avaliações positivas de cinco estrelas feitas por tutores atendidos no local."
      },
      {
        heading: "SEO On-Page e Criação de Conteúdo no Site Clínico",
        text: "Ter um site institucional próprio de carregamento rápido e otimizado para celulares é mandatório. As páginas internas do site devem conter tags HTML semânticas adequadas (h1, h2) contendo palavras-chave focadas na localização regional e nos serviços oferecidos (ex: 'clínica veterinária 24h em [Sua Cidade]'). A manutenção de um blog integrado publicando regularmente artigos relevantes de saúde e cuidados pets ajuda a construir autoridade digital, atraindo tráfego orgânico valioso e gratuito para o site do estabelecimento."
      }
    ],
    listTitle: "Pilares para Otimização de SEO Local Veterinário",
    listItems: [
      "Preenchimento estrito e completo de todas as informações básicas no Perfil de Empresa no Google.",
      "Incentivar ativamente os tutores satisfeitos a deixarem avaliações ricas de texto no perfil utilizando links curtos gerados.",
      "Inclusão de palavras-chave geolocalizadas em títulos de cabeçalho do site (ex: 'veterinário no bairro [Nome do Bairro]').",
      "Cadastro e listagem consistente em diretórios e mapas locais nacionais de busca pet."
    ],
    tableHeader: ["Ação Estratégica", "Resultado Esperado na Prática", "Prioridade no Planemanento Digital"],
    tableRows: [
      ["Otimização Perfil do Google", "Aparecimento no mapa local (Local Pack) em buscas móveis e imediatas.", "Altíssima / Imediata (Maior conversão local de emergências)."],
      ["Blog de Conteúdo", "Ranqueamento orgânico para dúvidas frequentes de tutores locais.", "Média / Longo Prazo (Construção de autoridade clínica regional)."],
      ["Otimização Mobile do Site", "Redução da taxa de rejeição e carregamento instantâneo no celular do tutor.", "Alta / Técnica (Google prioriza sites responsivos móveis)."]
    ]
  },
  {
    title: "como encantar tutores de pets na clínica: da recepção ao pós‑consulta",
    summary: "Descubra técnicas de atendimento premium e relacionamento focadas em encantar tutores, garantindo a fidelização e recomendações de sua clínica.",
    intro: "Na clínica médica de pequenos animais, há uma particularidade única na dinâmica de atendimento: embora o paciente seja o pet, quem avalia, decide e paga pelo serviço é o tutor humano. Portanto, o sucesso de um consultório depende não apenas de sua competência diagnóstica e clínica, mas de sua capacidade de encantar e se comunicar de forma empática com o tutor.",
    sections: [
      {
        heading: "A Recepção como o Cartão de Visitas da Clínica",
        text: "O encantamento do cliente inicia-se muito antes do contato direto com o médico-veterinário no consultório. O atendimento telefônico ou via WhatsApp deve ser ágil, prestativo e empático. Na admissão do paciente, a recepção da clínica deve ser limpa, climatizada, livre de odores desagradáveis (que assustam os cães e felinos) e possuir áreas de espera separadas para espécies diferentes sempre que possível, evitando estresse e agressividade entre cães e gatos em recintos fechados."
      },
      {
        heading: "Comunicação Médica Empática e o Fluxo de Pós-Consulta",
        text: "Dentro do consultório, o veterinário deve demonstrar interesse genuíno pelo pet e escuta ativa com o tutor. Evitar o uso excessivo de termos técnicos ininteligíveis para leigos e explicar de forma visual e simples o diagnóstico clínico e as opções de tratamento constrói confiança profissional sólida. O pós-consulta, que consiste em entrar em contato com o tutor após 24 a 48 horas para verificar o andamento do tratamento medicamentoso doméstico do pet, é a técnica de encantamento de maior impacto prático."
      }
    ],
    listTitle: "Regras Fundamentais para Encantar Tutores",
    listItems: [
      "Chamar sempre o pet e o tutor pelos respectivos nomes na recepção e durante todo o atendimento.",
      "Demonstrar carinho e calma na abordagem de exame clínico físico do paciente, evitando contenções agressivas na mesa.",
      "Fornecer explicações detalhadas e receitas digitais impressas de fácil leitura, sem rasuras manuscritas ininteligíveis.",
      "Instituir um canal de pós-consulta ativo com a equipe da recepção para acompanhamento de pacientes medicados."
    ],
    tableHeader: ["Fase do Atendimento", "Conduta Esperada (Premium)", "Percepção de Valor pelo Tutor"],
    tableRows: [
      ["Primeiro Contato (Recepção)", "Recepção limpa, acolhimento imediato, separação de felinos e caninos.", "Percepção de segurança, profissionalismo e redução imediata de estresse parental."],
      ["Exame Clínico (Consulta)", "Abordagem gentil (fear-free), explicação paciente de sintomas e hipóteses.", "Sentimento de acolhimento empático e confiança na capacidade profissional."],
      ["Acompanhamento (Pós-Consulta)", "Lembretes e mensagens para saber como o pet está evoluindo com as dosagens.", "Sensação de cuidado real que ultrapassa a barreira puramente comercial de venda."]
    ]
  },
  {
    title: "programas de fidelidade e planos de saúde pet para clínicas veterinárias",
    summary: "Análise mercadológica sobre planos de saúde e programas de fidelidade em clínicas veterinárias: saiba como aumentar sua receita recorrente e retenção.",
    intro: "A flutuação no volume de atendimentos clínicos e cirurgias é um dos maiores desafios de estabilidade financeira enfrentados por clínicas veterinárias de pequenos animais. Para combater a sazonalidade e aumentar a previsibilidade de receita do negócio, a implementação de planos preventivos de saúde pet e programas estruturados de fidelidade destaca-se como excelente alternativa estratégica.",
    sections: [
      {
        heading: "Planos Preventivos Próprios (Wellness Plans)",
        text: "Diferente dos planos de saúde convencionais que atuam como seguros de emergência (e muitas vezes reduzem a margem de ganho das clínicas credenciadas), os *Planos de Wellness Próprios* são pacotes anuais de medicina preventiva comercializados diretamente pela clínica aos seus clientes recorrentes. O tutor paga uma mensalidade fixa recorrente em troca de um cronograma estruturado de vacinas essenciais anuais, vermifugações periódicas programadas, check-ups laboratoriais semestrais (perfil hematológico e bioquímico básico) e descontos exclusivos em exames especializados e cirurgias eletivas."
      },
      {
        heading: "Retenção de Clientes e Aumento da Frequência de Visitas",
        text: "Os planos preventivos aumentam significativamente a frequência de visitas do tutor à clínica (geralmente dobrando as visitas anuais), gerando oportunidades contínuas de venda de serviços e produtos complementares (cross-selling de rações premium, farmácia de suporte e pet shop). O estabelecimento garante uma receita previsível mensal recorrente estável, o que ajuda a cobrir os custos fixos operacionais da empresa mesmo em meses de baixa sazonalidade de consultas e procedimentos."
      }
    ],
    listTitle: "Vantagens dos Planos de Wellness Próprios",
    listItems: [
      "Garantia de receita previsível e recorrente mensal para estabilizar o fluxo de caixa da clínica.",
      "Aumento comprovado na frequência de visitas do pet, facilitando a detecção precoce de patologias clínicas.",
      "Aumento drástico do ticket médio anual gasto pelo cliente recorrente dentro de seu estabelecimento.",
      "Fidelização e blindagem de sua base de clientes contra a concorrência direta de novos estabelecimentos locais."
    ],
    tableHeader: ["Modalidade de Serviço", "Forma de Funcionamento Prático", "Impacto no Fluxo de Caixa da Clínica"],
    tableRows: [
      ["Wellness Plan Próprio", "Tutor assina mensalidade de suporte preventivo (vacinas, checkups incluídos).", "Excelente. Receita previsível e fidelização máxima da base de tutores da clínica."],
      ["Plano Credenciado Externo", "A clínica credencia-se a planos terceiros de abrangência nacional.", "Moderado. Aumenta o volume de fluxo inicial, mas as margens de ganho são frequentemente reduzidas."],
      ["Clube de Fidelidade Típico", "Acúmulo de pontos a cada consulta para trocar por pet shop ou serviços.", "Bom. Incentiva o retorno recorrente do cliente de forma leve e de fácil controle."]
    ]
  },
  // CATEGORIA 7: Tecnologia, IA e futuro da veterinária
  {
    title: "telemedicina veterinária no Brasil: limites legais e oportunidades",
    summary: "Guia completo sobre a regulamentação da telemedicina veterinária no Brasil, baseando-se na Resolução 1465/2022 do CFMV e seus limites de atuação ética.",
    intro: "A evolução tecnológica das comunicações e a demanda por atendimentos ágeis impulsionaram a regulamentação da prestação de serviços virtuais de saúde animal. No Brasil, o Conselho Federal de Medicina Veterinária (CFMV) publicou a Resolução nº 1.465 de 2022, estabelecendo diretrizes éticas claras e limites rígidos para a prática segura da telemedicina veterinária.",
    sections: [
      {
        heading: "Modalidades Permitidas e a Exigência da RPVV",
        text: "A resolução do CFMV define que a prática de telemedicina só é permitida de forma ampla quando há o estabelecimento prévio da Relação Prévia Veterinário-Paciente-Cliente (RPVV). A RPVV consiste na realização de uma consulta presencial física prévia registrada em prontuário médico de forma oficial. Entre as modalidades permitidas estão: o Telediagnóstico (emissão de laudos de exames por imagem ou exames laboratoriais à distância), a Teleinterconsulta (troca de opiniões clínicas virtuais entre médicos-veterinários especialistas) e a Teleorientação (orientação e educação básica de tutores à distância sem fins diagnósticos)."
      },
      {
        heading: "Limites Éticos e Proibições no Atendimento Remoto",
        text: "É terminantemente proibido por lei ao médico-veterinário realizar o primeiro atendimento de emergência ou diagnosticar afecções clínicas novas de forma exclusivamente virtual, sem exame físico prévio do pet. Prescrever receitas de controle especial (como antibióticos e psicotrópicos restritos) em atendimentos online sem RPVV estabelecida constitui grave infração de bioética profissional sujeita a sanções administrativas de CRMV."
      }
    ],
    listTitle: "Modalidades Regulamentadas pela Resolução 1465",
    listItems: [
      "Teleconsulta: Atendimento clínico à distância para pets com RPVV ativa e diagnóstico prévio registrado.",
      "Telediagnóstico: Laudo remoto de exames complementares (raio-x, ressonância, eletrocardiograma) por especialistas.",
      "Teleorientação: Suporte educativo básico focado em sanar dúvidas sem fins diagnósticos imediatos ou prescrições.",
      "Telemonitoramento: Acompanhamento de parâmetros fisiológicos e exames de pets pós-tratados em consultório presencial."
    ],
    tableHeader: ["Modalidade de Atendimento", "Exigência de Consulta Presencial Prévia", "Conduta Permitida / Limites"],
    tableRows: [
      ["Teleconsulta Geral", "Obrigatoriamente Exigida (RPVV Ativa).", "Ajuste de doses de tratamento crônico, renovação de receitas comuns, orientação clínica."],
      ["Teleorientação Básica", "Não Exigida de forma prévia.", "Orientações gerais de vacinação, nutrição sadia de filhotes e cuidados ambientais gerais."],
      ["Telediagnóstico", "Não Exigida diretamente do laudista.", "Emissão profissional de laudo técnico especializado para o veterinário requisitante local."]
    ]
  },
  {
    title: "como a inteligência artificial está mudando o diagnóstico em medicina veterinária",
    summary: "Saiba como ferramentas de inteligência artificial (IA) estão revolucionando a análise de exames complementares e a triagem diagnóstica na veterinária moderna.",
    intro: "A medicina veterinária de alta precisão está vivenciando uma revolução tecnológica silenciosa impulsionada pelo desenvolvimento de algoritmos de inteligência artificial (IA) e deep learning. A aplicação dessas tecnologias na rotina clínica apoia e amplia a capacidade diagnóstica dos profissionais de imagem e laboratório, melhorando a precisão e rapidez na intervenção terapêutica.",
    sections: [
      {
        heading: "IA no Diagnóstico por Imagem Avançado",
        text: "Na radiologia digital e na ultrassonografia veterinária contemporânea, modelos de visão computacional treinados com milhões de imagens clínico-patológicas são aplicados para detecção automática de lesões. Esses sistemas analisam radiografias de tórax em segundos, identificando padrões de edema pulmonar, pneumonia ou nódulos tumorais microscópicos e sinalizando a gravidade com mapas de calor (heatmaps). Isso atua como um assistente de segunda opinião instantâneo para o clínico geral de plantão."
      },
      {
        heading: "Análise Laboratorial Preditiva e Triagem Rápida",
        text: "A automação inteligente estende-se aos analisadores hematológicos e bioquímicos automatizados das clínicas. Algoritmos de aprendizado de máquina cruzam dezenas de parâmetros laboratoriais (creatinina, ureia, densidade urinária, hematócrito) para prever precocemente o desenvolvimento de insuficiência renal aguda ou pancreatite subclínica muito antes das manifestações de sinais clínicos visíveis. Essa capacidade preditiva otimiza as chances de cura do paciente felino ou canino crítico."
      }
    ],
    listTitle: "Benefícios do Suporte Diagnóstico por IA",
    listItems: [
      "Rapidez na interpretação inicial de radiografias torácicas complexas em plantões noturnos de emergência.",
      "Redução expressiva nas taxas de erros diagnósticos ou falsos negativos de exames por imagem veterinários.",
      "Capacidade de prever alterações clínicas progressivas de forma preemptiva por modelagem de dados integrada.",
      "Otimização de tempo produtivo para laudistas especializados ao triar e priorizar os casos mais graves do dia."
    ],
    tableHeader: ["Área de Aplicação IA", "Ação do Algoritmo Inteligente", "Benefício Direto para o Paciente / Clínica"],
    tableRows: [
      ["Visão Computacional em Raio-X", "Detecção automática de padrões de padrão alveolar e nódulos pulmonares.", "Emissão de alerta de gravidade instantâneo para o clínico geral de plantão."],
      ["Análise de Hemogramas", "Reconhecimento automatizado e segmentação de leucócitos e plaquetas no esfregaço.", "Controle de qualidade e redução imediata de falsos resultados de análises manuais."],
      ["Eletrocardiografia (ECG)", "Identificação instantânea de complexos ventriculares prematuros e bloqueios.", "Segurança aumentada em exames pré-anestésicos rápidos realizados no local."]
    ]
  },
  {
    title: "softwares de gestão para clínicas veterinárias: o que observar antes de contratar",
    summary: "Guia completo de contratação de softwares de gestão de clínicas e pet shops, detalhando os requisitos cruciais de segurança de prontuários.",
    intro: "A informatização integral dos prontuários médicos e da administração financeira é o primeiro passo para o sucesso operacional de uma clínica veterinária moderna. A substituição das antigas fichas físicas de papel por softwares de gestão baseados em nuvem otimiza processos, reduz custos burocráticos e melhora a experiência do tutor.",
    sections: [
      {
        heading: "Requisitos Clínicos Fundamentais do Software",
        text: "Antes de selecionar e contratar uma ferramenta de software, o gestor veterinário deve avaliar a robustez de seus módulos clínicos. O sistema deve atender às exigências legais de guarda eletrônica de prontuário do paciente (Resolução do CFMV) e da LGPD (Lei Geral de Proteção de Dados) para informações cadastrais de tutores. Recursos cruciais incluem histórico de consultas interativo, laudos integrados de exames complementares de imagem e laboratório, controle rígido de doses e estoque de farmácia e geração automática de receitas impressas limpas e termo de consentimento livre e esclarecido (TCLE)."
      },
      {
        heading: "Integração Financeira e Lembretes Automatizados",
        text: "Um software completo deve atuar como o cérebro administrativo do negócio. O sistema deve gerenciar o fluxo de caixa diário (contas a pagar e receber), comissões de profissionais parceiros, e emitir notas fiscais de serviços de forma ágil e integrada. O módulo de automação de marketing que envia lembretes por WhatsApp ou e-mail de forma autônoma notificando tutores sobre datas de vacinas em dia e retornos de consultas é o principal gerador de receita incremental estável da empresa."
      }
    ],
    listTitle: "O que Avaliar antes de Fechar o Contrato",
    listItems: [
      "Disponibilidade em nuvem (Cloud Computing) de alta segurança para acesso móvel em qualquer local e tablet.",
      "Mecanismo de criptografia robusto e rotina de backup diário automático para blindagem de dados clínicos.",
      "Facilidade e agilidade na usabilidade da interface para evitar perda de tempo clínico no consultório.",
      "Suporte técnico rápido de emergência e canais de atendimento eficientes oferecidos pela desenvolvedora."
    ],
    tableHeader: ["Módulo do Sistema", "Recursos Indispensáveis", "Impacto Operacional na Clínica"],
    tableRows: [
      ["Módulo de Prontuário Clínico", "Prontuário eletrônico completo, laudos de exames integrados, emissão digital de receitas.", "Segurança legal dos dados médicos e agilidade no atendimento de consultório."],
      ["Automação de Marketing", "Disparo autônomo de mensagens de vacinas vencidas e retornos agendados.", "Aumento imediato no volume de consultas de rotina recorrentes da clínica."],
      ["Gestão Financeira", "Fluxo de caixa integrado, fechamento de caixa diário, controle de comissões.", "Previsibilidade financeira rigorosa e mitigação de perdas operacionais silenciosas."]
    ]
  },
  {
    title: "medicina veterinária personalizada: testes genéticos e planos de tratamento sob medida",
    summary: "Conheça o avanço da medicina genômica e dos testes genéticos em cães e gatos, descobrindo como tratamentos sob medida estão mudando a sobrevida de pets.",
    intro: "A medicina veterinária contemporânea está ingressando em um novo patamar de precisão terapêutica por meio da genômica aplicada. O avanço de biotecnologias inovadoras de sequenciamento de DNA de baixo custo viabilizou a realização de testes genéticos preditivos na rotina clínica, permitindo o diagnóstico precoce de patologias hereditárias graves e tratamentos sob medida.",
    sections: [
      {
        heading: "Testes Genéticos e Medicina Preditiva",
        text: "Os testes de genotipagem baseiam-se em amostras simples de DNA obtidas por esfregaço bucal (swab conjuntival/oral) ou coleta de sangue periférico. Exames rastreiam a presença de mutações conhecidas de alta herdabilidade, como a mutação no gene MDR1 (Multi-Drug Resistance 1) em raças do grupo Collie e pastores. Cães homozigotos afetados para a mutação MDR1 carecem de uma glicoproteína-P funcional na barreira hematoencefálica, predispondo-os a toxicidades graves e potencialmente fatais se expostos a fármacos de uso comum como a Ivermectina, Loperamida e quimioterápicos."
      },
      {
        heading: "Planos Terapêuticos Customizados Oncológicos e Nutricionais",
        text: "Na oncologia veterinária moderna, a análise molecular de biópsias de tumores identifica marcadores específicos de resistência ou sensibilidade à quimioterapia. Isso permite a seleção de fármacos adjuvantes altamente direcionados (como inibidores de tirosina quinase para mastocitomas de alto grau), poupando o paciente de toxicidades de agentes ineficazes. O manejo nutricional personalizado também avança, formulando dietas adaptadas ao microbioma e metabolismo individuais do animal."
      }
    ],
    listTitle: "Aplicações de Destaque da Genômica Veterinária",
    listItems: [
      "Mapeamento precoce de risco cardíaco hereditário (como Cardiomiopatia Hipertrófica em felinos da raça Maine Coon).",
      "Identificação prévia da sensibilidade farmacológica severa ao gene MDR1 em raças pastoras de cães.",
      "Direcionamento preciso de terapias oncológicas moleculares personalizadas com base no perfil de mutações de tumores.",
      "Triagem reprodutiva de reprodutores de canis e gatis para eliminação de patologias genéticas de herança recessiva."
    ],
    tableHeader: ["Mutação Genética", "Raças Caninas/Felinas de Risco", "Consequência Clínica / Conduta Recomendada"],
    tableRows: [
      ["Gene MDR1 (Hipersensibilidade)", "Border Collie, Pastor de Shetland, Collie, Pastor Branco Suíço.", "Evitar terminantemente o uso de Ivermectina, Doramectina e Loperamida. Substituir por fármacos seguros."],
      ["Gene MYBPC3 (HCM Felina)", "Maine Coon, Ragdoll.", "Avaliação genética prévia antes da reprodução e exames de ecocardiografia periódicos."],
      ["Gene PKD (Cistos Renais)", "Persas e Exóticos.", "Presença de cistos renais progressivos e insuficiência renal terminal. Mapeamento de ninhadas."]
    ]
  }
];

function generateSlug(title) {
  return title
    .toLowerCase()
    .normalize("NFD")
    .replace(/[\u0300-\u036f]/g, "")
    .replace(/[^a-z0-9\s-]/g, "")
    .trim()
    .replace(/\s+/g, "-");
}

function run() {
  console.log("=== VETRANK BLOG EXPANSION STRATEGY ===");
  console.log(`Checking local seed database file at: ${SEED_FILE}`);

  let existingPosts = [];
  if (fs.existsSync(SEED_FILE)) {
    try {
      existingPosts = JSON.parse(fs.readFileSync(SEED_FILE, "utf8"));
      console.log(`Loaded ${existingPosts.length} existing blog posts from disk.`);
    } catch (e) {
      console.error("Error reading seed file. Initializing empty array.", e);
      existingPosts = [];
    }
  } else {
    console.log("Seed file does not exist. Starting fresh.");
  }

  // Converter os 30 posts específicos em formato HTML rico em semântica
  const formattedNewPosts = SPECIFIC_30_POSTS.map(post => {
    const slug = generateSlug(post.title);

    // Montar o corpo HTML dinamicamente com base nas seções e tabelas
    let bodyHtml = `<h2>Introdução</h2>\n<p>${post.intro}</p>\n\n`;

    post.sections.forEach(sec => {
      bodyHtml += `<h3>${sec.heading}</h3>\n<p>${sec.text}</p>\n\n`;
    });

    bodyHtml += `<h3>${post.listTitle}</h3>\n<ul>\n`;
    post.listItems.forEach(item => {
      bodyHtml += `  <li>${item}</li>\n`;
    });
    bodyHtml += `</ul>\n\n`;

    bodyHtml += `<table style="width:100%; border-collapse:collapse; margin:20px 0;">\n`;
    bodyHtml += `  <thead>\n    <tr style="background-color:rgba(16,185,129,0.1);">\n`;
    post.tableHeader.forEach(th => {
      bodyHtml += `      <th style="padding:10px; border:1px solid rgba(255,255,255,0.1); text-align:left;">${th}</th>\n`;
    });
    bodyHtml += `    </tr>\n  </thead>\n  <tbody>\n`;
    post.tableRows.forEach(row => {
      bodyHtml += `    <tr>\n`;
      row.forEach(td => {
        bodyHtml += `      <td style="padding:10px; border:1px solid rgba(255,255,255,0.1);">${td}</td>\n`;
      });
      bodyHtml += `    </tr>\n`;
    });
    bodyHtml += `  </tbody>\n</table>`;

    return {
      slug,
      title: post.title,
      summary: post.summary,
      body: bodyHtml.trim(),
      author: "Equipe VetRank",
      active: 1,
      createdAt: new Date().toISOString(),
      updatedAt: new Date().toISOString()
    };
  });

  // Mesclar as postagens novas garantindo que não existam duplicatas por slug
  const finalPostsMap = new Map();
  
  // Primeiro, insere as existentes
  existingPosts.forEach(p => finalPostsMap.set(p.slug, p));

  // Depois, insere as 30 novas. Se coincidir slug, substitui para manter atualizado
  let addedCount = 0;
  let overriddenCount = 0;
  
  formattedNewPosts.forEach(p => {
    if (finalPostsMap.has(p.slug)) {
      overriddenCount++;
    } else {
      addedCount++;
    }
    finalPostsMap.set(p.slug, p);
  });

  const finalPosts = Array.from(finalPostsMap.values());

  // Salvar o arquivo de seed
  if (!fs.existsSync(DATA_DIR)) {
    fs.mkdirSync(DATA_DIR, { recursive: true });
  }
  
  fs.writeFileSync(SEED_FILE, JSON.stringify(finalPosts, null, 2), "utf8");
  console.log(`\nSuccess! Seed backup file updated successfully.`);
  console.log(`- New posts added: ${addedCount}`);
  console.log(`- Overridden posts: ${overriddenCount}`);
  console.log(`- Total posts in seed database now: ${finalPosts.length}\n`);

  // Se o banco de dados estiver rodando localmente ou for atualizado, o seeder sincronizará no boot.
  // Vamos rodar o sitemap updater para atualizar imediatamente o sitemap.xml
  try {
    console.log("Triggering sitemap.xml rebuild to index the new 30 posts...");
    // Importa dinamicamente ou executa via subprocesso
    import("./update_sitemap.js");
  } catch (err) {
    console.error("Failed to automatically run update_sitemap.js:", err);
  }
}

run();
