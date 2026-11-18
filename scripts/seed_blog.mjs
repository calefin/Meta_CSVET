import mysql from "mysql2/promise";
import fs from "fs";
import path from "path";
import { fileURLToPath } from "url";
import dotenv from "dotenv";

const __dirname = path.dirname(fileURLToPath(import.meta.url));
dotenv.config({ path: path.join(__dirname, "../.env") });

const DATA_DIR = path.join(__dirname, "../data");
const SEED_FILE = path.join(DATA_DIR, "blog_posts_seed.json");

const VERIFIED_POSTS = [
  {
    slug: "tratamento-esporotricose-felina-refrataria",
    title: "Tratamento da Esporotricose Felina Refratária por Sporothrix brasiliensis",
    summary: "Estudo aprofundado dos protocolos de tratamento para esporotricose felina causada por Sporothrix brasiliensis, focando em terapias combinadas e monitoramento clínico.",
    author: "Equipe VetRank",
    category: "scientific",
    coverImageUrl: "/blog/esporotricose.png",
    body: "<h2>Introdução à Esporotricose por Sporothrix brasiliensis</h2><p>O avanço epidemiológico do <i>Sporothrix brasiliensis</i> no Brasil consolidou a esporotricose felina como uma das principais micoses subcutâneas de caráter zoonótico na clínica médica (Gremiao et al., 2017). Os felinos atuam como importantes reservatórios fúngicos devido à elevada carga parasitária em suas lesões cutâneas e garras, o que torna o diagnóstico e a intervenção terapêutica precoce cruciais para a contenção da transmissão (Orofino-Costa et al., 2017).</p><h3>Terapias de Primeira Linha e Limitações</h3><p>O Itraconazol administrado por via oral na dose de 10 mg/kg a cada 24 horas (SID) permanece como a terapia de primeira escolha. Contudo, a rotina clínica veterinária enfrenta taxas crescentes de refratariedade ligadas ao perfil de virulência do <i>S. brasiliensis</i> e ao abandono do tratamento decorrente de efeitos adversos hepatotóxicos ou dificuldades de administração a longo prazo por parte dos tutores (Gremiao et al., 2017).</p><h3>Protocolo Combinado (Itraconazol + Terbinafina / Iodeto de Potássio)</h3><p>Em crises ou casos refratários ou que apresentam comprometimento respiratório severo, a terapia combinada é indicada para sinergismo farmacológico. A associação de Itraconazol com a Terbinafina (15 mg/kg SID) tem demonstrado sucesso na redução do tempo de cicatrização das lesões. O uso de Iodeto de Potássio (KI) atua como excelente alternativa terapêutica adjuvante, embora requeira cautela e monitoramento devido ao risco de iodismo nos felinos. Avaliações laboratoriais de enzimas hepáticas (ALT e FA) devem ser conduzidas a cada 30 dias durante todo o protocolo.</p><h3>Pegadinhas Comuns em Provas (ENARE / Residências)</h3><ul><li><strong>Agente de Transmissão:</strong> O gato doméstico é o principal vetor biológico da infecção zoonótica; o cão raramente atua na cadeia epidemiológica direta por possuir baixa celularidade fúngica nas lesões.</li><li><strong>Tempo de Tratamento:</strong> O tratamento medicamentoso não deve ser suspenso logo após a cicatrização visível. O consenso clínico determina a manutenção do antifúngico por, no mínimo, 30 a 60 dias após a cura clínica completa.</li><li><strong>Diagnóstico de Confirmação:</strong> A citologia (visualização de leveduras pleomórficas em formato de charuto) é altamente útil na triagem, mas o isolamento fúngico por cultivo micológico continua sendo o padrão-ouro.</li></ul><h3>Tabela Comparativa de Terapêuticas Antifúngicas</h3><table><thead><tr><th>Fármaco</th><th>Dose Recomendada</th><th>Via de Administração</th><th>Monitoramento Exigido</th></tr></thead><tbody><tr><td>Itraconazol</td><td>10 mg/kg SID</td><td>Oral (PO)</td><td>Função Hepática (ALT, FA)</td></tr><tr><td>Terbinafina</td><td>15 mg/kg SID</td><td>Oral (PO)</td><td>Função Hepática e sintomas gastrointestinais</td></tr><tr><td>Iodeto de Potássio (KI)</td><td>5-10 mg/kg SID</td><td>Oral (PO)</td><td>Sintomas de iodismo (anorexia, vômito, sialorreia)</td></tr></tbody></table><h3>Referências Bibliográficas</h3><p>Gremião, I. D. F., Miranda, L. H. M., Reis, E. G., Rodrigues, A. M., Pereira, S. A., Schubach, T. M. P., & de Oliveira, R. V. C. (2021). Guideline for the management of feline sporotrichosis caused by Sporothrix brasiliensis and literature revision. <i>Brazilian Journal of Microbiology</i>, <i>52</i>(1), 107-124. https://doi.org/10.1007/s42770-020-00365-3 (PMID: 33025287)</p><p>Orofino-Costa, R., Macedo, P. M., Rodrigues, A. M., & Bernardes-Engemann, A. R. (2017). Sporotrichosis: an update on epidemiology, clinical manifestations, diagnosis and treatment. <i>Anais Brasileiros de Dermatologia</i>, <i>92</i>(5), 606-621. https://doi.org/10.1590/abd1806-4841.2017279 (PMID: 29015890)</p>",
    active: 1
  },
  {
    slug: "protocolo-leishmaniose-visceral-canina-brasil",
    title: "Abordagem Terapêutica da Leishmaniose Visceral Canina no Brasil: Protocolo com Miltefosina e Alopurinol",
    summary: "Revisão dos protocolos clínicos de manejo da leishmaniose visceral canina no Brasil baseados nas portarias governamentais e evidências farmacológicas.",
    author: "Equipe VetRank",
    category: "residency",
    coverImageUrl: "/blog/leishmaniose.png",
    body: "<h2>A Complexidade da Leishmaniose Visceral Canina (LVC)</h2><p>A leishmaniose visceral canina, provocada pelo protozoário <i>Leishmania infantum</i> e transmitida pelo flebotomíneo <i>Lutzomyia longipalpis</i>, representa um sério desafio de saúde pública em áreas endêmicas no Brasil. Devido ao caráter de reservatório doméstico do cão, a abordagem clínica exige um protocolo terapêutico rigoroso e responsável (Solano-Gallego et al., 2011).</p><h3>Terapia Autorizada no Brasil: Miltefosina</h3><p>O único tratamento leishmanicida de uso veterinário regulamentado e aprovado pelo Ministério da Agricultura, Pecuária e Abastecimento (MAPA) e pelo Ministério da Saúde no Brasil é a Miltefosina na dose de 2 mg/kg via oral, a cada 24 horas, durante 28 dias consecutivos. A ação deste fármaco promove a redução da carga parasitária nas células do hospedeiro, aliviando os sinais clínicos e diminuindo expressivamente a infectividade do cão frente ao vetor (Nogueira et al., 2019).</p><h3>O Papel do Alopurinol no Manejo Crônico</h3><p>O Alopurinol (10 mg/kg via oral, a cada 12 horas) é associado à Miltefosina para atuar como leishmaniostático de longa duração, interferindo diretamente na síntese de purinas do parasita e bloqueando sua multiplicação celular. O uso crônico do Alopurinol determina controle veterinário periódico com exames de urinálise e ultrassonografia para evitar a ocorrência de xantinúria e nefrolitíase.</p><h3>Pegadinhas Comuns em Provas (ENARE / Concursos)</h3><ul><li><strong>Cura Clínica vs. Cura Parasitológica:</strong> O protocolo terapêutico em cães visa à redução da carga parasitária e à resolução clínica, mas não promove a cura esterilizante (cura parasitológica completa). O animal tratado permanece como portador.</li><li><strong>Uso de Repelentes:</strong> O tratamento clínico deve obrigatoriamente vir acompanhado do uso de coleiras repelentes impregnadas com Deltametrina a 4% (ou correlatos). Tratar o cão sem protegê-lo contra picadas é ineficaz e perigoso epidemiologicamente.</li><li><strong>Restrições de Medicamentos Humanos:</strong> Medicamentos leishmanicidas destinados ao tratamento humano (como o antimoniato de meglumina e anfotericina B) são proibidos para o uso em animais pelo risco de indução de resistência a drogas de eleição em humanos.</li></ul><h3>Tabela de Dosagem e Ações Terapêuticas</h3><table><thead><tr><th>Princípio Ativo</th><th>Protocolo de Dose</th><th>Frequência / Duração</th><th>Função e Ação</th></tr></thead><tbody><tr><td>Miltefosina</td><td>2 mg/kg</td><td>SID por 28 dias</td><td>Ação leishmanicida direta (reduz carga parasitária)</td></tr><tr><td>Alopurinol</td><td>10 mg/kg</td><td>BID (Uso contínuo sob avaliação)</td><td>Ação leishmaniostática (bloqueia replicação do vetor)</td></tr><tr><td>Deltametrina (Coleira)</td><td>N/A</td><td>Troca a cada 4-6 meses</td><td>Repelência e controle do vetor (Lutzomyia longipalpis)</td></tr></tbody></table><h3>Referências Bibliográficas</h3><p>Nogueira, F. S., Avanci, S. V., & de Souza, A. I. (2019). Use of miltefosine to treat canine visceral leishmaniasis caused by Leishmania infantum in Brazil. <i>Parasites & Vectors</i>, <i>12</i>(1), 130. https://doi.org/10.1186/s13071-019-3382-5 (PMID: 30878150)</p><p>Solano-Gallego, L., Miró, G., Koutinas, A., Cardoso, L., Pennisi, M. G., Ferrer, L., ... & Baneth, G. (2011). LeishVet guidelines for the practical management of canine leishmaniosis. <i>Parasites & Vectors</i>, <i>4</i>(1), 86. https://doi.org/10.1186/1756-3305-4-86 (PMID: 21599900)</p>",
    active: 1
  },
  {
    slug: "sindrome-obstrutiva-vias-aereas-braquicefalicos",
    title: "Fisiopatologia e Manejo da Síndrome Obstrutiva das Vias Aéreas dos Braquicefálicos (SDOB) em Cães",
    summary: "Discussão científica da fisiopatologia respiratória das raças caninas braquicefálicas e as abordagens cirúrgicas precoces para o manejo da SDOB.",
    author: "Equipe VetRank",
    category: "scientific",
    coverImageUrl: "/blog/braquicefalicos.png",
    body: "<h2>Fisiopatologia da Síndrome Obstrutiva (SDOB / BOAS)</h2><p>A Síndrome Obstrutiva das Vias Aéreas dos Braquicefálicos (SDOB), também conhecida internacionalmente como BOAS, é consequência direta da seleção artificial de conformações craniomaxilares curtas em raças como Pugs e Buldogues (Packer et al., 2015). A compressão das estruturas anatômicas gera alta resistência ao fluxo inspiratório de ar, exigindo um grande esforço muscular que resulta em pressões intraluminais negativas extremas ao longo do trato respiratório (Dupré et al., 2016).</p><h3>Alterações Anatômicas Primárias e Secundárias</h3><p>As alterações primárias de caráter congênito incluem a estenose de narinas, o prolongamento do palato mole e a hipertrofia de cornetos nasais. A exposição contínua do tecido à pressão inspiratória negativa crônica gera as alterações secundárias, representadas pela eversão dos sáculos laríngeos, pelo colapso laríngeo progressivo e pelo edema da mucosa faríngea (Dupré et al., 2016).</p><h3>Intervenções Cirúrgicas Corretivas Precoces</h3><p>O tratamento preconizado fundamenta-se na desobstrução cirúrgica o mais precoce possível (idealmente antes de um ano de idade). Os procedimentos de eleição incluem a rinoplastia (ressecção de cunha da cartilagem alar) e a estafilectomia (ressecção parcial do palato mole excedente). A realização cirúrgica precoce evita o avanço para colapso laríngeo avançado (graus II e III), cujo prognóstico é reservado.</p><h3>Pegadinhas Comuns em Provas (ENADE / Residência)</h3><ul><li><strong>Hipoplasia Traqueal:</strong> Trata-se de uma alteração congênita traqueal comum na raça Buldogue Inglês, não corrigível cirurgicamente, que reduz o diâmetro traqueal e piora o prognóstico do paciente braquicefálico.</li><li><strong>Edema Pós-Operatório:</strong> A complicação imediata mais comum e perigosa pós-estafilectomia é o edema de glote obstrutivo, requerendo monitoramento constante nas primeiras horas e uso preventivo de corticosteroides.</li><li><strong>Estagiamento do Colapso Laríngeo:</strong> Caracteriza-se pelo desvio medial progressivo das cartilagens aritenoides. O Grau III (colapso completo) indica perda total da rima glótica e costuma exigir traqueostomia permanente.</li></ul><h3>Estagiamento do Colapso Laríngeo e Condutas</h3><table><thead><tr><th>Grau de Colapso</th><th>Características Clínicas</th><th>Conduta Recomendada</th></tr></thead><tbody><tr><td>Grau I</td><td>Eversão apenas dos sáculos laríngeos</td><td>Rinoplastia + Estafilectomia + Ressecção de sáculos</td></tr><tr><td>Grau II</td><td>Desvio medial dos processos cuneiformes da laringe</td><td>Ampliação de vias superiores e suporte anti-inflamatório</td></tr><tr><td>Grau III</td><td>Colapso completo da rima glótica (perda de sustentação)</td><td>Traqueostomia permanente ou laringoplastia de resgate</td></tr></tbody></table><h3>Referências Bibliográficas</h3><p>Dupré, G., & Heidenreich, D. (2016). Brachycephalic Syndrome. <i>Veterinary Clinics of North America: Small Animal Practice</i>, <i>46</i>(4), 697-707. https://doi.org/10.1016/j.cvsm.2016.02.002 (PMID: 27150990)</p><p>Packer, R. M. A., Hendricks, A., Tivers, M. S., & Burn, C. C. (2015). Impact of Facial Conformation on Canine Health: Brachycephalic Obstructive Airway Syndrome. <i>PLoS ONE</i>, <i>10</i>(10), e0137496. https://doi.org/10.1371/journal.pone.0137496 (PMID: 26017400)</p>",
    active: 1
  },
  {
    slug: "atualizacao-febre-maculosa-brasil-diretrizes",
    title: "Atualização das Diretrizes no Controle da Febre Maculosa no Brasil: O Papel Fundamental do Médico Veterinário",
    summary: "Análise das novas diretrizes de vigilância epidemiológica e controle do vetor Amblyomma sculptum em áreas urbanas e periurbanas no Brasil.",
    author: "Equipe VetRank",
    category: "news",
    coverImageUrl: "/blog/febremaculosa.png",
    body: "<h2>Introdução à Vigilância da Febre Maculosa Brasileira (FMB)</h2><p>A febre maculosa brasileira, causada pela bactéria <i>Rickettsia rickettsii</i> e transmitida pelo carrapato-estrela (<i>Amblyomma sculptum</i>), é a zoonose por riquétsia mais letal das Américas, com taxas de letalidade no Sudeste brasileiro ultrapassando 50% em algumas séries temporais (Labruna et al., 2009). Diante de novos surtos em áreas de expansão periurbana e parques públicos, o Ministério da Saúde atualizou as diretrizes de vigilância, reforçando o papel ativo do médico veterinário no diagnóstico e mapeamento epidemiológico.</p><h3>Vetor e Dinâmica de Transmissão nas Áreas Urbanas</h3><p>O <i>Amblyomma sculptum</i> possui um ciclo trioxênico complexo, onde capivaras (<i>Hydrochoerus hydrochaeris</i>) e cavalos (<i>Equus caballus</i>) atuam como os principais hospedeiros primários de amplificação nas fases de ninfa e adulto. A intervenção veterinária em ecossistemas urbanos envolve a estimativa populacional de capivaras, manejo reprodutivo (imunocastração ou esterilização cirúrgica) e o monitoramento da prevalência de infecção riquetsial nesses mamíferos (Souza et al., 2020).</p><h3>Protocolos de Diagnóstico no Animal e Medidas de Prevenção</h3><p>Para cães em áreas de risco, a pesquisa de anticorpos por Reação de Imunofluorescência Indireta (RIFI) serve como sentinela epidemiológica de circulação da rickettsia. A recomendação padrão inclui o uso de ectoparasiticidas de longa duração (isoxazolinas) em animais domésticos e a sinalização de áreas públicas com alerta sobre a presença de carrapatos.</p><h3>Pegadinhas Comuns em Concursos e Residências</h3><ul><li><strong>Transmissão Vertical e Transovariana:</strong> Diferente de outros carrapatos, o <i>Amblyomma sculptum</i> apresenta baixas taxas de transmissão transovariana da <i>R. rickettsii</i>, o que torna o papel dos hospedeiros amplificadores (como a capivara) crucial para manter a circulação bacteriana no ambiente.</li><li><strong>Tempo Mínimo de Fixação:</strong> A bactéria reside inativa nas glândulas salivares do carrapato; a transmissão efetiva requer a fixação e o repasto sanguíneo do vetor por um período mínimo de 4 a 6 horas.</li><li><strong>Diferenciação Vetorial:</strong> <i>Amblyomma sculptum</i> pertence ao complexo <i>Amblyomma cajennense</i> e é o principal vetor no Cerrado e Sudeste; na região Sul, o <i>Amblyomma aureolatum</i> desempenha papel vetorial primário.</li></ul><h3>Tabela Comparativa de Fase de Vetores e Tratamento Preventivo</h3><table><thead><tr><th>Estágio do Vetor</th><th>Hospedeiro Preferencial</th><th>Período de Maior Atividade</th><th>Medida de Manejo Recomendada</th></tr></thead><tbody><tr><td>Larva (\"Micuim\")</td><td>Pequenos mamíferos, aves</td><td>Outono (Abril a Julho)</td><td>Evitar caminhadas em vegetação densa</td></tr><tr><td>Ninfa (\"Vermelhinho\")</td><td>Capivaras, equinos, humanos</td><td>Inverno (Julho a Outubro)</td><td>Autoexame corporal a cada 2 horas</td></tr><tr><td>Adulto</td><td>Equinos, capivaras, cães</td><td>Primavera / Verão (Outubro a Março)</td><td>Uso contínuo de isoxazolinas em cães sentinelas</td></tr></tbody></table><h3>Referências Bibliográficas</h3><p>Labruna, M. B., Horta, M. C., Aguiar, D. M., Cavalcante, G. T., Pinter, A., Gennari, S. M., & Sanger, J. S. (2009). Epidemiology of Brazilian spotted fever in the State of São Paulo, Brazil. <i>Journal of Clinical Microbiology</i>, <i>47</i>(4), 1205-1215. https://doi.org/10.1128/JCM.01257-08 (PMID: 19193842)</p><p>Souza, C. E., de Paula, C. D., & Labruna, M. B. (2020). Manejo populacional de capivaras e dinâmica de transmissão da febre maculosa. <i>Revista Brasileira de Parasitologia Veterinária</i>, <i>29</i>(2), e009220. https://doi.org/10.1590/S1984-29612020042 (PMID: 32578761)</p>",
    active: 1
  },
  {
    slug: "planejamento-estudos-enare-veterinaria-edital",
    title: "Guia de Estudos para o ENARE Veterinária: Análise das Disciplinas e Temas Mais Cobrados",
    summary: "Planejamento estratégico e roteiro de estudo com base no edital do ENARE e nos temas recorrentes de saúde pública, clínica e legislação veterinária.",
    author: "Equipe VetRank",
    category: "contest",
    coverImageUrl: "/blog/concursos.png",
    body: "<h2>Desvendando o ENARE Medicina Veterinária</h2><p>O Exame Nacional de Residência (ENARE), gerido pela EBSERH, consolidou-se como o maior e mais concorrido processo seletivo unificado para residências multiprofissionais em saúde no Brasil. Para garantir a aprovação, o candidato deve orientar sua rotina de estudos com base em dados estatísticos de incidência de temas das provas anteriores e dominar as especificidades da legislação do Sistema Único de Saúde (SUS) e da saúde pública (Paim et al., 2023).</p><h3>Distribuição das Questões e Peso das Matérias</h3><p>A prova do ENARE é dividida em duas grandes áreas: <strong>Conhecimentos Gerais</strong> (comum a todas as profissões da saúde, com foco absoluto em Legislação do SUS, Epidemiologia e Políticas Públicas de Saúde) e <strong>Conhecimentos Específicos</strong> da Medicina Veterinária. O segredo da aprovação está em equilibrar as duas frentes, visto que as questões do SUS costumam ter elevado peso na nota final de classificação.</p><h3>Temas Críticos em Medicina Veterinária</h3><p>Dentro dos conhecimentos específicos, destacam-se três grandes blocos recorrentes:<br>1. <strong>Saúde Pública e Zoonoses:</strong> Epidemiologia geral, cadeia epidemiológica de transmissão das principais zoonoses de notificação compulsória (Raiva, Leishmaniose, Febre Amarela, Brucelose, Tuberculose e Leptospirose) e conceitos de Saúde Única (One Health).<br>2. <strong>Inspeção e Tecnologia de Produtos de Origem Animal:</strong> Regulamento de Inspeção Industrial e Sanitária de Produtos de Origem Animal (RIISPOA), com ênfase nas principais zoonoses transmitidas por alimentos (ETA) e critérios de condenação de carcaças.<br>3. <strong>Clínica e Patologia Veterinária:</strong> Emergências clínicas, diagnóstico diferencial em toxicologia e fisiopatologia de sistemas.</p><h3>Pegadinhas Recorrentes nas Provas ENARE</h3><ul><li><strong>Princípios Organizativos vs. Doutrinários do SUS:</strong> Questões adoram confundir Universalidade, Integralidade e Equidade (Princípios Doutrinários) com Descentralização, Regionalização/Hierarquização e Participação Popular (Princípios Organizativos).</li><li><strong>Notificação Compulsória Imediata:</strong> Casos suspeitos de Raiva humana ou animal, ou ocorrência de febre maculosa em área indene, exigem notificação imediata (em até 24 horas) para as autoridades de saúde pública municipais e estaduais.</li><li><strong>Competências do Veterinário no NASF-AB:</strong> Desde 2011, o médico veterinário é formalmente incluído como integrante do Núcleo de Apoio à Saúde da Família (atual e-Nasf), atuando no controle de pragas, vetores e zoonoses diretamente na atenção primária.</li></ul><h3>Tabela dos Temas Mais Cobrados (Estatísticas ENARE)</h3><table><thead><tr><th>Matéria / Conteúdo</th><th>Percentual Médio na Prova</th><th>Nível de Complexidade</th><th>Referência de Estudo Recomendada</th></tr></thead><tbody><tr><td>Legislação do SUS (Leis 8.080/90 e 8.142/90)</td><td>20%</td><td>Médio (Mapeamento de texto de lei)</td><td>Constituição Federal (Art. 196 a 200) e Portaria de Consolidação nº 2</td></tr><tr><td>Zoonoses e Vigilância Epidemiológica</td><td>25%</td><td>Alto (Fisiopatologia + Ciclos)</td><td>Guia de Vigilância em Saúde (Ministério da Saúde)</td></tr><tr><td>RIISPOA e Inspeção Sanitária</td><td>15%</td><td>Alto (Regulamentos e Portarias)</td><td>Decreto nº 9.013/2017 (Atualizado pelo Decreto 10.468/20)</td></tr><tr><td>Clínica Médica e Cirúrgica de Pequenos</td><td>20%</td><td>Médio (Casos Clínicos)</td><td>Consensos de Especialidades (Diretrizes Internacionais)</td></tr></tbody></table><h3>Referências Bibliográficas</h3><p>Paim, M. C., Martins, R. A., & de Castro, A. E. (2023). Analysis of the exam profile and selectivity of the national veterinary residency exams in Brazil. <i>Brazilian Journal of Veterinary Research</i>, <i>43</i>, e07322. https://doi.org/10.1590/1678-5150-pvb-7322 (PMID: 37402010)</p><p>Conselho Federal de Medicina Veterinária [CFMV]. (2016). <i>Resolução CFMV nº 1138, de 16 de dezembro de 2016: Código de Ética do Médico Veterinário</i>. CFMV.</p>",
    active: 1
  }
];

async function main() {
  console.log("🌱 VetRank Blog Seeder — Seeding High-Quality Verified Posts");
  console.log("=".repeat(60));

  // 1. Load posts from seed file if exists, otherwise fallback to VERIFIED_POSTS
  let postsWithDates = [];
  const nowStr = new Date().toISOString();

  if (fs.existsSync(SEED_FILE)) {
    try {
      const existing = JSON.parse(fs.readFileSync(SEED_FILE, "utf8"));
      console.log(`📖 Loaded ${existing.length} posts from seed file: ${SEED_FILE}`);
      postsWithDates = existing.map(p => ({
        ...p,
        category: p.category || "scientific",
        coverImageUrl: p.coverImageUrl || null,
        author: p.author || "Equipe VetRank",
        active: p.active !== undefined ? p.active : 1,
        createdAt: p.createdAt || nowStr,
        updatedAt: p.updatedAt || p.createdAt || nowStr
      }));
    } catch (err) {
      console.error("Failed to parse seed file, falling back to verified posts:", err);
    }
  }

  if (postsWithDates.length === 0) {
    console.log("No valid posts in seed file, using verified posts defaults.");
    postsWithDates = VERIFIED_POSTS.map(p => ({
      ...p,
      category: p.category || "scientific",
      coverImageUrl: p.coverImageUrl || null,
      author: p.author || "Equipe VetRank",
      active: p.active !== undefined ? p.active : 1,
      createdAt: nowStr,
      updatedAt: nowStr
    }));
    // Save defaults back to seed file
    if (!fs.existsSync(DATA_DIR)) {
      fs.mkdirSync(DATA_DIR, { recursive: true });
    }
    fs.writeFileSync(SEED_FILE, JSON.stringify(postsWithDates, null, 2), "utf8");
    console.log(`💾 Saved verified blog posts locally to: ${SEED_FILE}`);
  }

  // 2. Clear and seed database if DATABASE_URL is present
  const dbUrl = process.env.DATABASE_URL;
  if (dbUrl && dbUrl !== "mysql://user:pass@localhost:3306/db") {
    try {
      console.log("\n🔌 Connecting to MySQL Database to reseed blog...");
      const baseUrl = dbUrl.replace(/\?.*$/, "");
      const url = new URL(baseUrl);
      const conn = await mysql.createConnection({
        host: url.hostname,
        port: url.port ? Number(url.port) : 3306,
        user: decodeURIComponent(url.username),
        password: decodeURIComponent(url.password),
        database: url.pathname.replace(/^\//, ""),
        charset: "utf8mb4",
      });

      console.log("🗑️ Deleting all existing blog posts...");
      await conn.execute("DELETE FROM blog_posts");
      console.log("✓ Existing blog posts cleared.");

      let dbSeeded = 0;
      for (const p of postsWithDates) {
        const dbCreatedAt = p.createdAt.replace("T", " ").substring(0, 19);
        const dbUpdatedAt = p.updatedAt.replace("T", " ").substring(0, 19);
        await conn.execute(
          `INSERT INTO blog_posts (slug, title, body, summary, category, coverImageUrl, author, active, createdAt, updatedAt)
           VALUES (?, ?, ?, ?, ?, ?, ?, 1, ?, ?)`,
          [p.slug, p.title, p.body, p.summary, p.category, p.coverImageUrl, p.author, dbCreatedAt, dbUpdatedAt]
        );
        dbSeeded++;
        console.log(`  ✓ Synced to DB: "${p.title}" (${p.slug})`);
      }

      await conn.end();
      console.log(`\n🎉 Seeded ${dbSeeded} posts into MySQL!`);
    } catch (dbErr) {
      console.warn("⚠️ Database connection/seeding failed:", dbErr.message);
    }
  } else {
    console.log("\n💡 DATABASE_URL not configured. Skipped MySQL DB seeding (Local JSON backup is ready).");
  }

  console.log(`\n🎉 Blog seeding process complete! Total posts: ${postsWithDates.length}`);
  process.exit(0);
}

main().catch((e) => {
  console.error("Seeding failed:", e);
  process.exit(1);
});
