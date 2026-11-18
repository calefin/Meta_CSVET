-- ─── Tabela topics (Assuntos — 3º nível) ─────────────────────────────────────
CREATE TABLE IF NOT EXISTS `topics` (
  `id` int NOT NULL AUTO_INCREMENT,
  `subjectId` int NOT NULL,
  `disciplineId` int NOT NULL,
  `slug` varchar(96) COLLATE utf8mb4_unicode_ci NOT NULL,
  `namePt` varchar(128) COLLATE utf8mb4_unicode_ci NOT NULL,
  `nameEn` varchar(128) COLLATE utf8mb4_unicode_ci NOT NULL,
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `createdAt` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_topics_subject` (`subjectId`),
  KEY `idx_topics_discipline` (`disciplineId`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── topicId em questions ──────────────────────────────────────────────────────
ALTER TABLE `questions`
  ADD COLUMN IF NOT EXISTS `topicId` int DEFAULT NULL AFTER `subjectId`,
  ADD KEY IF NOT EXISTS `idx_q_topic` (`topicId`);

-- ═══════════════════════════════════════════════════════════════════════════════
-- SEED: Assuntos por disciplina
-- Hierarquia: disciplines (Grande Área) → subjects (Disciplina) → topics (Assunto)
-- ═══════════════════════════════════════════════════════════════════════════════

-- ── 30001 Anatomia Veterinária I e II (subjectId=30001) ───────────────────────
INSERT IGNORE INTO `topics` (`subjectId`,`disciplineId`,`slug`,`namePt`,`nameEn`) VALUES
(30001,30001,'anatomia-sistema-osseo','Sistema Ósseo e Articulações','Skeletal System and Joints'),
(30001,30001,'anatomia-sistema-muscular','Sistema Muscular','Muscular System'),
(30001,30001,'anatomia-sistema-nervoso','Sistema Nervoso — Anatomia','Nervous System — Anatomy'),
(30001,30001,'anatomia-sistema-cardiovascular','Sistema Cardiovascular — Anatomia','Cardiovascular System — Anatomy'),
(30001,30001,'anatomia-sistema-digestorio','Sistema Digestório — Anatomia','Digestive System — Anatomy'),
(30001,30001,'anatomia-sistema-respiratorio','Sistema Respiratório — Anatomia','Respiratory System — Anatomy'),
(30001,30001,'anatomia-sistema-urinario','Sistema Urinário — Anatomia','Urinary System — Anatomy'),
(30001,30001,'anatomia-sistema-reprodutor','Sistema Reprodutor — Anatomia','Reproductive System — Anatomy'),
(30001,30001,'anatomia-topografica','Anatomia Topográfica e Regiões','Topographic Anatomy and Regions');

-- ── 30001 Biologia Celular e Molecular (subjectId=30002) ─────────────────────
INSERT IGNORE INTO `topics` (`subjectId`,`disciplineId`,`slug`,`namePt`,`nameEn`) VALUES
(30002,30001,'celula-estrutura','Estrutura Celular e Organelas','Cell Structure and Organelles'),
(30002,30001,'membrana-transporte','Membrana Plasmática e Transporte','Plasma Membrane and Transport'),
(30002,30001,'ciclo-celular-mitose','Ciclo Celular, Mitose e Meiose','Cell Cycle, Mitosis and Meiosis'),
(30002,30001,'dna-replicacao','DNA, Replicação e Reparo','DNA, Replication and Repair'),
(30002,30001,'transcricao-traducao','Transcrição e Tradução (Síntese Proteica)','Transcription and Translation'),
(30002,30001,'sinalizacao-celular','Sinalização Celular e Receptores','Cell Signaling and Receptors'),
(30002,30001,'morte-celular','Apoptose e Necrose','Apoptosis and Necrosis');

-- ── 30001 Histologia Veterinária (subjectId=30003) ───────────────────────────
INSERT IGNORE INTO `topics` (`subjectId`,`disciplineId`,`slug`,`namePt`,`nameEn`) VALUES
(30003,30001,'histologia-tecido-epitelial','Tecido Epitelial','Epithelial Tissue'),
(30003,30001,'histologia-tecido-conjuntivo','Tecido Conjuntivo','Connective Tissue'),
(30003,30001,'histologia-tecido-muscular','Tecido Muscular','Muscle Tissue'),
(30003,30001,'histologia-tecido-nervoso','Tecido Nervoso','Nervous Tissue'),
(30003,30001,'histologia-orgaos','Histologia dos Órgãos e Sistemas','Organ and System Histology');

-- ── 30001 Embriologia Veterinária (subjectId=30004) ─────────────────────────
INSERT IGNORE INTO `topics` (`subjectId`,`disciplineId`,`slug`,`namePt`,`nameEn`) VALUES
(30004,30001,'embriologia-gametogenese','Gametogênese e Fertilização','Gametogenesis and Fertilization'),
(30004,30001,'embriologia-desenvolvimento','Desenvolvimento Embrionário Precoce','Early Embryonic Development'),
(30004,30001,'embriologia-organos','Organogênese e Fisiologia Fetal','Organogenesis and Fetal Physiology'),
(30004,30001,'embriologia-anexos','Anexos Embrionários e Placenta','Embryonic Annexes and Placenta');

-- ── 30001 Bioquímica Veterinária (subjectId=30005) ───────────────────────────
INSERT IGNORE INTO `topics` (`subjectId`,`disciplineId`,`slug`,`namePt`,`nameEn`) VALUES
(30005,30001,'bioquimica-carboidratos','Metabolismo de Carboidratos (Glicólise, Krebs, Gliconeogênese)','Carbohydrate Metabolism'),
(30005,30001,'bioquimica-lipideos','Metabolismo de Lipídeos (Beta-oxidação, Lipogênese)','Lipid Metabolism'),
(30005,30001,'bioquimica-proteinas','Metabolismo de Proteínas e Aminoácidos','Protein and Amino Acid Metabolism'),
(30005,30001,'bioquimica-enzimas','Enzimas e Cinética Enzimática','Enzymes and Enzymatic Kinetics'),
(30005,30001,'bioquimica-vitaminas','Vitaminas, Minerais e Cofatores','Vitamins, Minerals and Cofactors'),
(30005,30001,'bioquimica-equilibrio-acido-base','Equilíbrio Ácido-Base e Tampões','Acid-Base Balance and Buffers');

-- ── 30001 Biofísica Veterinária (subjectId=30006) ────────────────────────────
INSERT IGNORE INTO `topics` (`subjectId`,`disciplineId`,`slug`,`namePt`,`nameEn`) VALUES
(30006,30001,'biofisica-eletrofisiologia','Eletrofisiologia e Potencial de Membrana','Electrophysiology and Membrane Potential'),
(30006,30001,'biofisica-hemodinamica','Hemodinâmica e Biofísica Cardiovascular','Hemodynamics and Cardiovascular Biophysics'),
(30006,30001,'biofisica-mecanica-respiratoria','Mecânica Respiratória','Respiratory Mechanics'),
(30006,30001,'biofisica-optica-acustica','Óptica, Acústica e Radiações','Optics, Acoustics and Radiation');

-- ── 30001 Fisiologia Veterinária I e II (subjectId=30007) ────────────────────
INSERT IGNORE INTO `topics` (`subjectId`,`disciplineId`,`slug`,`namePt`,`nameEn`) VALUES
(30007,30001,'fisiologia-sistema-nervoso','Sistema Nervoso — Fisiologia','Nervous System — Physiology'),
(30007,30001,'fisiologia-cardiovascular','Sistema Cardiovascular — Fisiologia','Cardiovascular System — Physiology'),
(30007,30001,'fisiologia-respiratoria','Sistema Respiratório — Fisiologia','Respiratory System — Physiology'),
(30007,30001,'fisiologia-renal','Sistema Renal e Equilíbrio Hidroeletrolítico','Renal System and Fluid Balance'),
(30007,30001,'fisiologia-digestoria','Sistema Digestório — Fisiologia','Digestive System — Physiology'),
(30007,30001,'fisiologia-endocrina','Sistema Endócrino — Fisiologia','Endocrine System — Physiology'),
(30007,30001,'fisiologia-muscular','Fisiologia Muscular e Neuromuscular','Muscle and Neuromuscular Physiology'),
(30007,30001,'fisiologia-sensorial','Fisiologia Sensorial (Visão, Audição, Dor)','Sensory Physiology'),
(30007,30001,'fisiologia-termorregulacao','Termorregulação e Metabolismo Energético','Thermoregulation and Energy Metabolism');

-- ── 30001 Genética e Evolução Animal (subjectId=30008) ───────────────────────
INSERT IGNORE INTO `topics` (`subjectId`,`disciplineId`,`slug`,`namePt`,`nameEn`) VALUES
(30008,30001,'genetica-mendeliana','Genética Mendeliana e Hereditariedade','Mendelian Genetics and Heredity'),
(30008,30001,'genetica-moleculares','Genética Molecular e Biotecnologia','Molecular Genetics and Biotechnology'),
(30008,30001,'genetica-populacional','Genética Populacional e Melhoramento','Population Genetics and Improvement'),
(30008,30001,'genetica-cromossomica','Genética Cromossômica e Citogenética','Chromosomal Genetics and Cytogenetics');

-- ── 30001 Bioestatística (subjectId=30009) ───────────────────────────────────
INSERT IGNORE INTO `topics` (`subjectId`,`disciplineId`,`slug`,`namePt`,`nameEn`) VALUES
(30009,30001,'bioestatistica-descritiva','Estatística Descritiva','Descriptive Statistics'),
(30009,30001,'bioestatistica-inferencial','Estatística Inferencial e Testes','Inferential Statistics and Tests'),
(30009,30001,'bioestatistica-metodologia','Delineamento Experimental e Metodologia Científica','Experimental Design and Scientific Methodology');

-- ═══ GRANDE ÁREA 2: Patobiologia (30002) ══════════════════════════════════════

-- ── Microbiologia Veterinária (subjectId=30010) ──────────────────────────────
INSERT IGNORE INTO `topics` (`subjectId`,`disciplineId`,`slug`,`namePt`,`nameEn`) VALUES
(30010,30002,'microbiologia-bacteriologia','Bacteriologia Veterinária','Veterinary Bacteriology'),
(30010,30002,'microbiologia-virologia','Virologia Veterinária','Veterinary Virology'),
(30010,30002,'microbiologia-micologia','Micologia Veterinária (Fungos)','Veterinary Mycology'),
(30010,30002,'microbiologia-tecnicas','Técnicas Laboratoriais Microbiológicas','Microbiological Laboratory Techniques'),
(30010,30002,'microbiologia-resistencia','Resistência Antimicrobiana','Antimicrobial Resistance');

-- ── Imunologia Veterinária (subjectId=30011) ─────────────────────────────────
INSERT IGNORE INTO `topics` (`subjectId`,`disciplineId`,`slug`,`namePt`,`nameEn`) VALUES
(30011,30002,'imunologia-inata','Imunidade Inata','Innate Immunity'),
(30011,30002,'imunologia-adaptativa','Imunidade Adaptativa (Humoral e Celular)','Adaptive Immunity'),
(30011,30002,'imunologia-vacinas','Vacinação e Imunoprofilaxia','Vaccination and Immunoprophylaxis'),
(30011,30002,'imunologia-hipersensibilidade','Hipersensibilidade e Autoimunidade','Hypersensitivity and Autoimmunity'),
(30011,30002,'imunologia-imunodiagnostico','Imunodiagnóstico','Immunodiagnosis');

-- ── Parasitologia Veterinária (subjectId=30012) ──────────────────────────────
INSERT IGNORE INTO `topics` (`subjectId`,`disciplineId`,`slug`,`namePt`,`nameEn`) VALUES
(30012,30002,'parasitologia-helmintologia','Helmintologia (Nematódeos, Cestódeos, Trematódeos)','Helminthology'),
(30012,30002,'parasitologia-protozoologia','Protozoologia Veterinária','Veterinary Protozoology'),
(30012,30002,'parasitologia-entomologia','Entomologia (Artrópodes e Ectoparasitos)','Entomology and Ectoparasites'),
(30012,30002,'parasitologia-controle','Controle e Profilaxia de Parasitoses','Parasite Control and Prophylaxis');

-- ── Patologia Geral e Necropsia (subjectId=30013) ────────────────────────────
INSERT IGNORE INTO `topics` (`subjectId`,`disciplineId`,`slug`,`namePt`,`nameEn`) VALUES
(30013,30002,'patologia-degeneracao-necrose','Degeneração Celular e Necrose','Cell Degeneration and Necrosis'),
(30013,30002,'patologia-inflamacao','Inflamação Aguda e Crônica','Acute and Chronic Inflammation'),
(30013,30002,'patologia-neoplasia','Neoplasias — Classificação e Comportamento','Neoplasms — Classification and Behavior'),
(30013,30002,'patologia-circulacao','Distúrbios Circulatórios (Edema, Trombose, Infarto)','Circulatory Disturbances'),
(30013,30002,'patologia-tanatologia','Tanatologia e Fenômenos Cadavéricos','Thanatology and Cadaveric Phenomena'),
(30013,30002,'patologia-tecnicas-necropsia','Técnicas de Necropsia e Histopatologia','Necropsy Techniques and Histopathology');

-- ── Patologia Especial/Sistêmica (subjectId=30014) ───────────────────────────
INSERT IGNORE INTO `topics` (`subjectId`,`disciplineId`,`slug`,`namePt`,`nameEn`) VALUES
(30014,30002,'patologia-sistema-nervoso','Patologia do Sistema Nervoso','Nervous System Pathology'),
(30014,30002,'patologia-cardiovascular','Patologia Cardiovascular','Cardiovascular Pathology'),
(30014,30002,'patologia-respiratoria','Patologia Respiratória','Respiratory Pathology'),
(30014,30002,'patologia-digestoria','Patologia Digestória (Fígado, Intestinos, Pâncreas)','Digestive Pathology'),
(30014,30002,'patologia-renal-urinaria','Patologia Renal e Urinária','Renal and Urinary Pathology'),
(30014,30002,'patologia-osteoarticular','Patologia Osteoarticular e Muscular','Osteoarticular and Muscle Pathology'),
(30014,30002,'patologia-pele','Dermatopatologia','Dermatopathology'),
(30014,30002,'patologia-sistema-hemolinfatico','Patologia do Sistema Hemolinfático','Hemolymphatic System Pathology');

-- ── Farmacologia e Terapêutica (subjectId=30015) ─────────────────────────────
INSERT IGNORE INTO `topics` (`subjectId`,`disciplineId`,`slug`,`namePt`,`nameEn`) VALUES
(30015,30002,'farmacologia-farmacocinetica','Farmacocinética (ADME)','Pharmacokinetics (ADME)'),
(30015,30002,'farmacologia-farmacodinamica','Farmacodinâmica e Mecanismo de Ação','Pharmacodynamics and Mechanism of Action'),
(30015,30002,'farmacologia-antimicrobianos','Antimicrobianos e Antiparasitários','Antimicrobials and Antiparasitic Drugs'),
(30015,30002,'farmacologia-sistema-nervoso','Fármacos do Sistema Nervoso (Analgésicos, Sedativos)','CNS Drugs'),
(30015,30002,'farmacologia-cardiovascular-renal','Fármacos Cardiovasculares e Renais','Cardiovascular and Renal Drugs'),
(30015,30002,'farmacologia-hormonios','Hormônios e Moduladores Endócrinos','Hormones and Endocrine Modulators'),
(30015,30002,'farmacologia-anti-inflamatorios','Anti-inflamatórios (AINEs e Corticoides)','Anti-inflammatory Drugs');

-- ── Toxicologia Veterinária (subjectId=30016) ────────────────────────────────
INSERT IGNORE INTO `topics` (`subjectId`,`disciplineId`,`slug`,`namePt`,`nameEn`) VALUES
(30016,30002,'toxicologia-organofosforados','Organofosforados e Carbamatos','Organophosphates and Carbamates'),
(30016,30002,'toxicologia-plantas-toxicas','Plantas Tóxicas para Animais','Toxic Plants for Animals'),
(30016,30002,'toxicologia-metais-pesados','Metais Pesados e Toxinas Ambientais','Heavy Metals and Environmental Toxins'),
(30016,30002,'toxicologia-micotoxinas','Micotoxinas e Intoxicações Fúngicas','Mycotoxins and Fungal Intoxications'),
(30016,30002,'toxicologia-medicamentos','Intoxicação por Medicamentos e Antiparasitários','Drug and Antiparasitic Intoxication');

-- ═══ GRANDE ÁREA 3: Clínica (30003) ════════════════════════════════════════════

-- ── Semiologia Veterinária (subjectId=30017) ─────────────────────────────────
INSERT IGNORE INTO `topics` (`subjectId`,`disciplineId`,`slug`,`namePt`,`nameEn`) VALUES
(30017,30003,'semiologia-anamnese','Anamnese e Exame Físico Geral','Anamnesis and General Physical Examination'),
(30017,30003,'semiologia-sistemas','Semiologia dos Sistemas (Cardiorrespiratório, Digestório, Nervoso)','Systems Semiology'),
(30017,30003,'semiologia-sinais-vitais','Parâmetros Vitais e Monitorização','Vital Signs and Monitoring');

-- ── Patologia Clínica (subjectId=30018) ──────────────────────────────────────
INSERT IGNORE INTO `topics` (`subjectId`,`disciplineId`,`slug`,`namePt`,`nameEn`) VALUES
(30018,30003,'patclinica-hematologia','Hematologia Veterinária (Hemograma)','Veterinary Hematology'),
(30018,30003,'patclinica-bioquimica-serica','Bioquímica Sérica e Urinálise','Serum Biochemistry and Urinalysis'),
(30018,30003,'patclinica-citologia','Citologia e Patologia Aspirativa','Cytology and Aspiration Pathology'),
(30018,30003,'patclinica-coagulacao','Hemostasia e Coagulação','Hemostasis and Coagulation'),
(30018,30003,'patclinica-interpretacao','Interpretação de Perfis Laboratoriais','Laboratory Profile Interpretation');

-- ── Diagnóstico por Imagem (subjectId=30019) ─────────────────────────────────
INSERT IGNORE INTO `topics` (`subjectId`,`disciplineId`,`slug`,`namePt`,`nameEn`) VALUES
(30019,30003,'imagem-radiologia','Radiologia Veterinária','Veterinary Radiology'),
(30019,30003,'imagem-ultrassonografia','Ultrassonografia Veterinária','Veterinary Ultrasonography'),
(30019,30003,'imagem-tomografia-rm','Tomografia e Ressonância Magnética','CT and MRI'),
(30019,30003,'imagem-endoscopia','Endoscopia Veterinária','Veterinary Endoscopy');

-- ── Técnica Cirúrgica (subjectId=30020) ──────────────────────────────────────
INSERT IGNORE INTO `topics` (`subjectId`,`disciplineId`,`slug`,`namePt`,`nameEn`) VALUES
(30020,30003,'cirurgia-principios','Princípios Cirúrgicos e Assepsia','Surgical Principles and Asepsis'),
(30020,30003,'cirurgia-instrumental','Instrumentação Cirúrgica e Suturas','Surgical Instruments and Sutures'),
(30020,30003,'cirurgia-tecnicas','Técnicas Cirúrgicas por Sistema','Surgical Techniques by System'),
(30020,30003,'cirurgia-complicacoes','Complicações Pós-operatórias','Postoperative Complications');

-- ── Anestesiologia Veterinária (subjectId=30021) ─────────────────────────────
INSERT IGNORE INTO `topics` (`subjectId`,`disciplineId`,`slug`,`namePt`,`nameEn`) VALUES
(30021,30003,'anestesia-premedicacao','MPA e Protocolos de Sedação','Preanesthetic Medication and Sedation'),
(30021,30003,'anestesia-geral','Anestesia Geral Inalatória e Injetável','General Inhalation and Injectable Anesthesia'),
(30021,30003,'anestesia-local-regional','Anestesia Local e Locoregional','Local and Locoregional Anesthesia'),
(30021,30003,'anestesia-monitoramento','Monitoramento Anestésico e Recuperação','Anesthetic Monitoring and Recovery'),
(30021,30003,'anestesia-emergencias','Emergências Anestésicas','Anesthetic Emergencies');

-- ── Clínica de Pequenos Animais (subjectId=30022) ────────────────────────────
INSERT IGNORE INTO `topics` (`subjectId`,`disciplineId`,`slug`,`namePt`,`nameEn`) VALUES
(30022,30003,'pequenos-clinica-cardiologia','Cardiologia de Pequenos Animais','Small Animal Cardiology'),
(30022,30003,'pequenos-clinica-pneumologia','Pneumologia de Pequenos Animais','Small Animal Pulmonology'),
(30022,30003,'pequenos-clinica-gastroenterologia','Gastroenterologia de Pequenos Animais','Small Animal Gastroenterology'),
(30022,30003,'pequenos-clinica-nefrologia','Nefrologia e Urologia de Pequenos Animais','Small Animal Nephrology'),
(30022,30003,'pequenos-clinica-neurologia','Neurologia de Pequenos Animais','Small Animal Neurology'),
(30022,30003,'pequenos-clinica-dermatologia','Dermatologia de Pequenos Animais','Small Animal Dermatology'),
(30022,30003,'pequenos-clinica-endocrinologia','Endocrinologia de Pequenos Animais','Small Animal Endocrinology'),
(30022,30003,'pequenos-clinica-oncologia','Oncologia Veterinária','Veterinary Oncology'),
(30022,30003,'pequenos-cirurgia','Cirurgia de Pequenos Animais','Small Animal Surgery');

-- ── Clínica de Ruminantes (subjectId=30023) ──────────────────────────────────
INSERT IGNORE INTO `topics` (`subjectId`,`disciplineId`,`slug`,`namePt`,`nameEn`) VALUES
(30023,30003,'ruminantes-bovinos','Clínica e Cirurgia de Bovinos','Bovine Medicine and Surgery'),
(30023,30003,'ruminantes-ovinos-caprinos','Clínica e Cirurgia de Ovinos e Caprinos','Ovine and Caprine Medicine'),
(30023,30003,'ruminantes-doencas-metabolicas','Doenças Metabólicas e Nutricionais de Ruminantes','Ruminant Metabolic Diseases'),
(30023,30003,'ruminantes-sistema-digestorio','Doenças do Sistema Digestório de Ruminantes','Ruminant Digestive Disease');

-- ── Clínica de Equídeos (subjectId=30024) ────────────────────────────────────
INSERT IGNORE INTO `topics` (`subjectId`,`disciplineId`,`slug`,`namePt`,`nameEn`) VALUES
(30024,30003,'equinos-medicina-interna','Medicina Interna de Equídeos','Equine Internal Medicine'),
(30024,30003,'equinos-sistema-locomotor','Sistema Locomotor e Podologia','Locomotor System and Podiatry'),
(30024,30003,'equinos-cirurgia','Cirurgia de Equídeos','Equine Surgery'),
(30024,30003,'equinos-neonatologia','Neonatologia Equina','Equine Neonatology');

-- ── Fisiopatologia da Reprodução (subjectId=30025) ───────────────────────────
INSERT IGNORE INTO `topics` (`subjectId`,`disciplineId`,`slug`,`namePt`,`nameEn`) VALUES
(30025,30003,'reproducao-ciclo-estral','Ciclo Estral e Controle Reprodutivo','Estrous Cycle and Reproductive Control'),
(30025,30003,'reproducao-infertilidade','Infertilidade e Doenças Reprodutivas','Infertility and Reproductive Diseases'),
(30025,30003,'reproducao-gestacao','Gestação e Diagnóstico de Prenhez','Pregnancy and Pregnancy Diagnosis');

-- ── Obstetrícia e Biotecnologia (subjectId=30026) ────────────────────────────
INSERT IGNORE INTO `topics` (`subjectId`,`disciplineId`,`slug`,`namePt`,`nameEn`) VALUES
(30026,30003,'obstetricia-parto','Fisiologia e Distócias do Parto','Parturition and Dystocia'),
(30026,30003,'biotecnologia-ia','Inseminação Artificial e Criopreservação','Artificial Insemination and Cryopreservation'),
(30026,30003,'biotecnologia-te-fiv','Transferência de Embriões e FIV','Embryo Transfer and IVF');

-- ═══ GRANDE ÁREA 4: Medicina Preventiva (30004) ════════════════════════════════

INSERT IGNORE INTO `topics` (`subjectId`,`disciplineId`,`slug`,`namePt`,`nameEn`) VALUES
(30027,30004,'epidemiologia-vigilancia','Vigilância Epidemiológica e Conceitos','Epidemiological Surveillance'),
(30027,30004,'epidemiologia-estudos','Estudos Epidemiológicos (Coorte, Caso-controle)','Epidemiological Studies'),
(30027,30004,'epidemiologia-medidas','Medidas de Frequência e Associação','Frequency and Association Measures');

INSERT IGNORE INTO `topics` (`subjectId`,`disciplineId`,`slug`,`namePt`,`nameEn`) VALUES
(30028,30004,'zoonoses-bacterianas','Zoonoses Bacterianas (Brucelose, Leptospirose, Tuberculose)','Bacterial Zoonoses'),
(30028,30004,'zoonoses-virais','Zoonoses Virais (Raiva, Influenza, Febre Aftosa)','Viral Zoonoses'),
(30028,30004,'zoonoses-parasitarias','Zoonoses Parasitárias (Toxoplasmose, Leishmaniose)','Parasitic Zoonoses'),
(30028,30004,'saude-publica-veterinaria','Saúde Pública Veterinária e Políticas','Public Veterinary Health and Policies');

INSERT IGNORE INTO `topics` (`subjectId`,`disciplineId`,`slug`,`namePt`,`nameEn`) VALUES
(30029,30004,'saneamento-agua-efluentes','Saneamento de Água e Efluentes','Water and Effluent Sanitation'),
(30029,30004,'saneamento-residuos','Gestão de Resíduos Sólidos e Biológicos','Solid and Biological Waste Management'),
(30029,30004,'saneamento-planejamento','Planejamento em Saúde Animal e Programas de Controle','Animal Health Planning');

INSERT IGNORE INTO `topics` (`subjectId`,`disciplineId`,`slug`,`namePt`,`nameEn`) VALUES
(30030,30004,'inspecao-carnes','Inspeção de Carnes e Abate Humanitário','Meat Inspection and Humane Slaughter'),
(30030,30004,'inspecao-leite-derivados','Inspeção de Leite e Derivados','Milk and Dairy Inspection'),
(30030,30004,'inspecao-ovos-pescado','Inspeção de Ovos, Pescado e Mel','Eggs, Fish and Honey Inspection'),
(30030,30004,'inspecao-legislacao','Legislação Sanitária e Registro de Produtos','Sanitary Legislation');

INSERT IGNORE INTO `topics` (`subjectId`,`disciplineId`,`slug`,`namePt`,`nameEn`) VALUES
(30031,30004,'tecnologia-conservacao','Tecnologia de Conservação de Alimentos','Food Conservation Technology'),
(30031,30004,'tecnologia-processamento','Processamento de Produtos de Origem Animal','Animal Product Processing'),
(30031,30004,'tecnologia-qualidade','Controle de Qualidade e Rastreabilidade','Quality Control and Traceability');

INSERT IGNORE INTO `topics` (`subjectId`,`disciplineId`,`slug`,`namePt`,`nameEn`) VALUES
(30032,30004,'higiene-microbiologia-alimentos','Microbiologia de Alimentos','Food Microbiology'),
(30032,30004,'higiene-dta','Doenças Transmitidas por Alimentos (DTA)','Foodborne Diseases'),
(30032,30004,'higiene-boas-praticas','Boas Práticas de Fabricação e APPCC','Good Manufacturing Practices and HACCP');

-- ═══ GRANDE ÁREA 5: Zootecnia (30005) ══════════════════════════════════════════

INSERT IGNORE INTO `topics` (`subjectId`,`disciplineId`,`slug`,`namePt`,`nameEn`) VALUES
(30033,30005,'zootecnia-historia-bases','História e Bases da Zootecnia','History and Foundations of Animal Science'),
(30033,30005,'zootecnia-racas-especies','Raças e Espécies de Interesse Zootécnico','Breeds and Species of Zootechnical Interest');

INSERT IGNORE INTO `topics` (`subjectId`,`disciplineId`,`slug`,`namePt`,`nameEn`) VALUES
(30034,30005,'nutricao-nutrientes','Nutrientes Essenciais e Exigências Nutricionais','Essential Nutrients and Nutritional Requirements'),
(30034,30005,'nutricao-formulacao','Formulação de Dietas e Tabelas de Alimentos','Diet Formulation and Feed Tables'),
(30034,30005,'nutricao-digestibilidade','Digestibilidade e Avaliação de Alimentos','Digestibility and Feed Evaluation');

INSERT IGNORE INTO `topics` (`subjectId`,`disciplineId`,`slug`,`namePt`,`nameEn`) VALUES
(30035,30005,'forragicultura-pastagens','Manejo de Pastagens e Plantas Forrageiras','Pasture Management and Forage Plants'),
(30035,30005,'forragicultura-silagem','Silagem e Conservação de Forragens','Silage and Forage Conservation'),
(30035,30005,'forragicultura-recuperacao','Recuperação e Reforma de Pastagens','Pasture Recovery and Renovation');

INSERT IGNORE INTO `topics` (`subjectId`,`disciplineId`,`slug`,`namePt`,`nameEn`) VALUES
(30036,30005,'melhoramento-genetica-quantitativa','Genética Quantitativa e Parâmetros Genéticos','Quantitative Genetics'),
(30036,30005,'melhoramento-selecao','Métodos de Seleção e Avaliação Genética','Selection Methods and Genetic Evaluation'),
(30036,30005,'melhoramento-cruzamento','Cruzamentos e Heterose','Crossbreeding and Heterosis');

INSERT IGNORE INTO `topics` (`subjectId`,`disciplineId`,`slug`,`namePt`,`nameEn`) VALUES
(30037,30005,'bovinocultura-corte','Bovinocultura de Corte — Sistemas de Produção','Beef Cattle Production Systems'),
(30037,30005,'bovinocultura-leite','Bovinocultura de Leite — Manejo e Produção','Dairy Cattle Management'),
(30037,30005,'bovinocultura-sanidade','Sanidade e Bem-estar em Bovinos','Bovine Health and Welfare');

INSERT IGNORE INTO `topics` (`subjectId`,`disciplineId`,`slug`,`namePt`,`nameEn`) VALUES
(30038,30005,'avicultura-corte-postura','Avicultura de Corte e Postura','Broiler and Layer Poultry'),
(30038,30005,'suinocultura','Suinocultura — Sistemas e Manejo','Swine Production and Management'),
(30038,30005,'equideocultura','Equideocultura — Manejo e Produção','Equine Production and Management');

INSERT IGNORE INTO `topics` (`subjectId`,`disciplineId`,`slug`,`namePt`,`nameEn`) VALUES
(30039,30005,'aquicultura-piscicultura','Piscicultura Continental e Marinha','Continental and Marine Fish Farming'),
(30039,30005,'aquicultura-carcinicultura','Carcinicultura e Maricultura','Shrimp Farming and Mariculture');

-- ═══ GRANDE ÁREA 6: Ética e Humanidades (30006) ═══════════════════════════════

INSERT IGNORE INTO `topics` (`subjectId`,`disciplineId`,`slug`,`namePt`,`nameEn`) VALUES
(30040,30006,'etica-deontologia','Deontologia Veterinária e Código de Ética','Veterinary Deontology and Code of Ethics'),
(30040,30006,'etica-bioetica','Bioética e Dilemas Éticos na MV','Bioethics and Ethical Dilemmas'),
(30040,30006,'etica-legislacao-profissional','Legislação Profissional e CFMV','Professional Legislation and CFMV');

INSERT IGNORE INTO `topics` (`subjectId`,`disciplineId`,`slug`,`namePt`,`nameEn`) VALUES
(30041,30006,'bem-estar-conceitos','Conceitos e Princípios de Bem-estar Animal','Animal Welfare Concepts'),
(30041,30006,'bem-estar-legislacao','Legislação de Bem-estar e Proteção Animal','Animal Welfare Legislation'),
(30041,30006,'etologia-comportamento','Etologia e Comportamento Animal','Ethology and Animal Behavior');

INSERT IGNORE INTO `topics` (`subjectId`,`disciplineId`,`slug`,`namePt`,`nameEn`) VALUES
(30042,30006,'mv-legal-pericia','Perícia e Avaliação em MV Legal','Expertise and Assessment in Legal VM'),
(30042,30006,'mv-legal-maus-tratos','Maus-tratos e Crimes Ambientais','Animal Abuse and Environmental Crimes');

INSERT IGNORE INTO `topics` (`subjectId`,`disciplineId`,`slug`,`namePt`,`nameEn`) VALUES
(30043,30006,'administracao-gestao','Gestão de Empresas e Propriedades Rurais','Rural Property Management'),
(30043,30006,'administracao-mercado','Mercado Veterinário e Empreendedorismo','Veterinary Market and Entrepreneurship');

INSERT IGNORE INTO `topics` (`subjectId`,`disciplineId`,`slug`,`namePt`,`nameEn`) VALUES
(30044,30006,'extensao-comunicacao','Comunicação e Extensão Rural','Rural Communication and Extension'),
(30044,30006,'extensao-sociologia','Sociologia Rural e Desenvolvimento','Rural Sociology and Development');

INSERT IGNORE INTO `topics` (`subjectId`,`disciplineId`,`slug`,`namePt`,`nameEn`) VALUES
(30045,30006,'animais-selvagens-clinica','Clínica de Animais Selvagens','Wildlife Clinical Medicine'),
(30045,30006,'animais-selvagens-manejo','Manejo e Conservação de Fauna Silvestre','Wildlife Management and Conservation'),
(30045,30006,'pets-exoticos','Medicina de Pets Exóticos (Répteis, Aves, Pequenos Mamíferos)','Exotic Pet Medicine');

SELECT CONCAT('Topics criados: ', COUNT(*)) as resultado FROM topics;
