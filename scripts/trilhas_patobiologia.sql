-- VetRank — Trilhas de Patobiologia
-- Inserir no VPS:
--   docker exec -i mysql-shared mysql -h 127.0.0.1 -u appuser -pXsanQqTJkz8JE0jXHQU5 vetrank < scripts/trilhas_patobiologia.sql
--
-- disciplineId 30002 = Patobiologia e Agentes Agressores
-- subjectIds usados:
--   30013 = Patologia Geral e Necropsia
--   30014 = Patologia Especial/Sistêmica
--   30015 = Farmacologia e Terapêutica
--   30016 = Toxicologia Veterinária
--   30010 = Microbiologia Veterinária

-- ─────────────────────────────────────────────────────────────────────────────
-- TRILHA 1 — Patologia Especial/Sistêmica — Trilha Completa
-- ─────────────────────────────────────────────────────────────────────────────
INSERT INTO trails (disciplineId, title, description, totalHours, passingScore, finalExamQuestions, finalExamTimeSeconds, active, createdBy)
VALUES (30002, 'Patologia Especial — Trilha Sistêmica', 'Percurso completo de Patologia Especial/Sistêmica: dos processos básicos às lesões orgânicas por sistema. Baseado em Jubb, Kennedy & Palmer. Progresso gradual do fácil ao complexo, com exame final integrador.', 70, 70, 40, 3600, 1, 1);
SET @t1 = LAST_INSERT_ID();
INSERT INTO trail_modules (trailId, `order`, title, summary, difficulty, questionCount, minPassRate, questionFilter) VALUES
(@t1, 1, 'Módulo 1 — Introdução e Sistema Cardiovascular', 'Revisão de lesões cardíacas, vasculares e pericárdicas. Cardiomiopatias, endocardites, aterosclerose e infarto.', 'easy', 40, 70, '{"disciplineId":30002,"subjectIds":[30014],"difficulty":"easy"}'),
(@t1, 2, 'Módulo 2 — Sistema Respiratório', 'Pneumonias, pleurites, edema pulmonar, bronquiectasia, neoplasias pulmonares. Lesões em diferentes espécies.', 'easy', 40, 70, '{"disciplineId":30002,"subjectIds":[30014],"difficulty":"easy"}'),
(@t1, 3, 'Módulo 3 — Sistema Digestório e Hepático', 'Gastroenterites, enterites hemorrágicas, hepatites, cirrose, colangite, pancreatites. Lesões de ruminantes e monogástricos.', 'medium', 50, 70, '{"disciplineId":30002,"subjectIds":[30014],"difficulty":"medium"}'),
(@t1, 4, 'Módulo 4 — Sistema Renal e Urinário', 'Glomerulonefrites, pielonefrites, nefrose, insuficiência renal. Urolitíase e lesões vesicais.', 'medium', 50, 70, '{"disciplineId":30002,"subjectIds":[30014],"difficulty":"medium"}'),
(@t1, 5, 'Módulo 5 — Sistema Nervoso e Musculoesquelético', 'Encefalites, meningites, desmielinizações. Miopatias, artrites, osteomielites e fraturas patológicas.', 'medium', 50, 70, '{"disciplineId":30002,"subjectIds":[30014],"difficulty":"medium"}'),
(@t1, 6, 'Módulo 6 — Sistemas Endócrino, Reprodutor e Tegumentar', 'Doenças adrenais, tireoidianas e pancreáticas. Metrites, ooforites, dermatites e neoplasias cutâneas.', 'hard', 55, 70, '{"disciplineId":30002,"subjectIds":[30014],"difficulty":"hard"}'),
(@t1, 7, 'Módulo 7 — Neoplasias e Diagnóstico Diferencial', 'Classificação de tumores, critérios de malignidade, metástases. Diagnósticos diferenciais por sistema.', 'hard', 55, 70, '{"disciplineId":30002,"subjectIds":[30014],"difficulty":"hard"}');
SELECT CONCAT('Trilha 1 criada: ID ', @t1) AS resultado;

-- ─────────────────────────────────────────────────────────────────────────────
-- TRILHA 2 — Patologia Geral — Processos Fundamentais
-- ─────────────────────────────────────────────────────────────────────────────
INSERT INTO trails (disciplineId, title, description, totalHours, passingScore, finalExamQuestions, finalExamTimeSeconds, active, createdBy)
VALUES (30002, 'Patologia Geral — Processos Fundamentais', 'Domínio dos processos patológicos básicos: degeneração, inflamação, necrose, reparação e neoplasia. Base essencial para toda a patologia sistêmica e provas do CFMV.', 55, 70, 35, 3000, 1, 1);
SET @t2 = LAST_INSERT_ID();
INSERT INTO trail_modules (trailId, `order`, title, summary, difficulty, questionCount, minPassRate, questionFilter) VALUES
(@t2, 1, 'Módulo 1 — Degenerações e Morte Celular', 'Degenerações hidrópica, lipídica e proteica. Necrose coagulativa, liquefativa, caseosa e gangrenosa. Apoptose.', 'easy', 35, 70, '{"disciplineId":30002,"subjectIds":[30013],"difficulty":"easy"}'),
(@t2, 2, 'Módulo 2 — Inflamação Aguda e Crônica', 'Inflamação exsudativa (serosa, fibrinosa, purulenta, hemorrágica). Inflamação crônica, granulomatosa e crônica ativa.', 'easy', 40, 70, '{"disciplineId":30002,"subjectIds":[30013],"difficulty":"easy"}'),
(@t2, 3, 'Módulo 3 — Distúrbios Circulatórios', 'Congestão, edema, hemorragia, trombose, embolia e infarto. Choque: tipos e alterações morfológicas.', 'medium', 45, 70, '{"disciplineId":30002,"subjectIds":[30013],"difficulty":"medium"}'),
(@t2, 4, 'Módulo 4 — Reparação e Cicatrização', 'Regeneração celular, cicatrização por primeira e segunda intenção. Cicatrizes patológicas, queloides e fístulas.', 'medium', 45, 70, '{"disciplineId":30002,"subjectIds":[30013],"difficulty":"medium"}'),
(@t2, 5, 'Módulo 5 — Neoplasia e Necropsia', 'Critérios de benignidade e malignidade. Invasão, metástase e estadiamento. Técnica de necropsia e diagnóstico macroscópico.', 'hard', 50, 70, '{"disciplineId":30002,"subjectIds":[30013],"difficulty":"hard"}'),
(@t2, 6, 'Módulo 6 — Integração Patológica', 'Questões integradoras cobrindo múltiplos processos. Diagnóstico diferencial de lesões macroscópicas e microscópicas.', 'hard', 55, 70, '{"disciplineId":30002,"subjectIds":[30013],"difficulty":"hard"}');
SELECT CONCAT('Trilha 2 criada: ID ', @t2) AS resultado;

-- ─────────────────────────────────────────────────────────────────────────────
-- TRILHA 3 — Farmacologia Veterinária — Da Teoria à Clínica
-- ─────────────────────────────────────────────────────────────────────────────
INSERT INTO trails (disciplineId, title, description, totalHours, passingScore, finalExamQuestions, finalExamTimeSeconds, active, createdBy)
VALUES (30002, 'Farmacologia Veterinária — Da Teoria à Clínica', 'Farmacocinética, farmacodinâmica e farmacologia de sistemas. Do mecanismo de ação ao uso clínico em diferentes espécies veterinárias.', 60, 70, 40, 3600, 1, 1);
SET @t3 = LAST_INSERT_ID();
INSERT INTO trail_modules (trailId, `order`, title, summary, difficulty, questionCount, minPassRate, questionFilter) VALUES
(@t3, 1, 'Módulo 1 — Farmacocinética e Farmacodinâmica', 'Absorção, distribuição, metabolismo e excreção. Receptores, agonistas, antagonistas e índice terapêutico.', 'easy', 40, 70, '{"disciplineId":30002,"subjectIds":[30015],"difficulty":"easy"}'),
(@t3, 2, 'Módulo 2 — Antimicrobianos', 'Betalactâmicos, aminoglicosídeos, tetraciclinas, fluoroquinolonas, macrolídeos. Resistência e associações antimicrobianas.', 'easy', 45, 70, '{"disciplineId":30002,"subjectIds":[30015],"difficulty":"easy"}'),
(@t3, 3, 'Módulo 3 — Anti-inflamatórios e Analgésicos', 'AINEs: mecanismo e uso em espécies. Corticosteroides: efeitos e riscos. Opioide e analgesia multimodal.', 'medium', 45, 70, '{"disciplineId":30002,"subjectIds":[30015],"difficulty":"medium"}'),
(@t3, 4, 'Módulo 4 — Fármacos do SNC e Cardiovascular', 'Sedativos, antiepilépticos, antiarrítmicos e diuréticos. Uso em cães, gatos, bovinos e equinos.', 'medium', 50, 70, '{"disciplineId":30002,"subjectIds":[30015],"difficulty":"medium"}'),
(@t3, 5, 'Módulo 5 — Antiparasitários e Vacinas', 'Endectocidas, avermectinas, benzimidazóis, anticoccidianos. Adjuvantes, tipos de vacinas e protocolos vacinais.', 'medium', 50, 70, '{"disciplineId":30002,"subjectIds":[30015],"difficulty":"medium"}'),
(@t3, 6, 'Módulo 6 — Farmacologia Clínica Aplicada', 'Cálculo de doses, vias de administração, interações medicamentosas e farmacovigilância. Casos clínicos.', 'hard', 55, 70, '{"disciplineId":30002,"subjectIds":[30015],"difficulty":"hard"}');
SELECT CONCAT('Trilha 3 criada: ID ', @t3) AS resultado;

-- ─────────────────────────────────────────────────────────────────────────────
-- TRILHA 4 — Toxicologia e Farmacologia — Trilha Temática
-- ─────────────────────────────────────────────────────────────────────────────
INSERT INTO trails (disciplineId, title, description, totalHours, passingScore, finalExamQuestions, finalExamTimeSeconds, active, createdBy)
VALUES (30002, 'Toxicologia e Farmacologia — Trilha Temática', 'Integração entre toxicologia veterinária e farmacologia: de intoxicações por plantas e agrotóxicos ao manejo terapêutico e antídotos.', 45, 70, 30, 2700, 1, 1);
SET @t4 = LAST_INSERT_ID();
INSERT INTO trail_modules (trailId, `order`, title, summary, difficulty, questionCount, minPassRate, questionFilter) VALUES
(@t4, 1, 'Módulo 1 — Princípios de Toxicologia', 'Toxicodinâmica e toxicocinética. DL50, sinergismo, antagonismo e fatores que influenciam a toxicidade.', 'easy', 30, 70, '{"disciplineId":30002,"subjectIds":[30016],"difficulty":"easy"}'),
(@t4, 2, 'Módulo 2 — Intoxicações por Plantas', 'Plantas hepatotóxicas, nefrotóxicas, fotossensibilizantes e que causam aborto. Diagnóstico e tratamento.', 'medium', 40, 70, '{"disciplineId":30002,"subjectIds":[30016],"difficulty":"medium"}'),
(@t4, 3, 'Módulo 3 — Agrotóxicos e Metais Pesados', 'Organofosforados, carbamatos, piretroides, rodenticidas. Chumbo, arsênio, mercúrio: mecanismos e antídotos.', 'medium', 40, 70, '{"disciplineId":30002,"subjectIds":[30016],"difficulty":"medium"}'),
(@t4, 4, 'Módulo 4 — Micotoxinas e Toxinas Bacterianas', 'Aflatoxinas, fumonisinas, tricotecenos, zearalenona. Botulismo, tétano e intoxicação estafilocócica.', 'hard', 45, 70, '{"disciplineId":30002,"subjectIds":[30016,30015],"difficulty":"hard"}'),
(@t4, 5, 'Módulo 5 — Antídotos e Tratamento de Urgência', 'Atropina, pralidoxima, BAL, EDTA, vitamina K1, flumazenil. Protocolos de descontaminação e suporte.', 'hard', 45, 70, '{"disciplineId":30002,"subjectIds":[30016,30015],"difficulty":"hard"}');
SELECT CONCAT('Trilha 4 criada: ID ', @t4) AS resultado;

-- ─────────────────────────────────────────────────────────────────────────────
-- TRILHA 5 — Patobiologia Completa — Revisão CFMV
-- ─────────────────────────────────────────────────────────────────────────────
INSERT INTO trails (disciplineId, title, description, totalHours, passingScore, finalExamQuestions, finalExamTimeSeconds, active, createdBy)
VALUES (30002, 'Patobiologia — Revisão Completa CFMV', 'Revisão abrangente de toda a grande área de Patobiologia para o exame do CFMV. Cobre Patologia Geral, Patologia Especial, Farmacologia, Toxicologia e Microbiologia em módulos integrados de alto rendimento.', 100, 70, 50, 5400, 1, 1);
SET @t5 = LAST_INSERT_ID();
INSERT INTO trail_modules (trailId, `order`, title, summary, difficulty, questionCount, minPassRate, questionFilter) VALUES
(@t5, 1, 'Módulo 1 — Patologia Geral: Processos Essenciais', 'Degeneração, inflamação, necrose, reparação. Os processos mais cobrados em provas do CFMV.', 'medium', 50, 70, '{"disciplineId":30002,"subjectIds":[30013],"difficulty":"medium"}'),
(@t5, 2, 'Módulo 2 — Patologia Especial: Sistemas Cardiovascular, Respiratório e Digestório', 'Lesões orgânicas dos sistemas de maior volume de questões em concurso.', 'medium', 55, 70, '{"disciplineId":30002,"subjectIds":[30014],"difficulty":"medium"}'),
(@t5, 3, 'Módulo 3 — Patologia Especial: Sistemas Renal, Nervoso e Endócrino', 'Lesões renais, neurológicas e endócrinas. Diagnóstico morfológico e diferencial.', 'medium', 55, 70, '{"disciplineId":30002,"subjectIds":[30014],"difficulty":"medium"}'),
(@t5, 4, 'Módulo 4 — Microbiologia: Bacteriologia e Virologia', 'Principais bactérias e vírus veterinários, mecanismos de patogenicidade e diagnóstico laboratorial.', 'medium', 50, 70, '{"disciplineId":30002,"subjectIds":[30010],"difficulty":"medium"}'),
(@t5, 5, 'Módulo 5 — Farmacologia: Antimicrobianos e Anti-inflamatórios', 'Grupos farmacológicos mais cobrados em concurso: antimicrobianos, AINEs e corticosteroides.', 'medium', 50, 70, '{"disciplineId":30002,"subjectIds":[30015],"difficulty":"medium"}'),
(@t5, 6, 'Módulo 6 — Toxicologia: Plantas, Agrotóxicos e Micotoxinas', 'Principais intoxicações em bovinos, equinos e pequenos animais. Antídotos e diagnóstico.', 'hard', 55, 70, '{"disciplineId":30002,"subjectIds":[30016],"difficulty":"hard"}'),
(@t5, 7, 'Módulo 7 — Integração e Casos Complexos', 'Questões integradoras cruzando patologia, farmacologia e toxicologia. Foco em raciocínio diagnóstico.', 'hard', 60, 70, '{"disciplineId":30002,"subjectIds":[30013,30014,30015,30016],"difficulty":"hard"}');
SELECT CONCAT('Trilha 5 criada: ID ', @t5) AS resultado;

-- ─────────────────────────────────────────────────────────────────────────────
-- TRILHA 6 — Patologia Especial — Revisão Express
-- ─────────────────────────────────────────────────────────────────────────────
INSERT INTO trails (disciplineId, title, description, totalHours, passingScore, finalExamQuestions, finalExamTimeSeconds, active, createdBy)
VALUES (30002, 'Patologia Especial — Revisão Express', 'Revisão rápida dos pontos mais cobrados em Patologia Especial. Ideal para quem tem pouco tempo antes da prova.', 20, 65, 20, 1800, 1, 1);
SET @t6 = LAST_INSERT_ID();
INSERT INTO trail_modules (trailId, `order`, title, summary, difficulty, questionCount, minPassRate, questionFilter) VALUES
(@t6, 1, 'Módulo 1 — Lesões Mais Cobradas (Sistemas Cardiovascular e Respiratório)', 'Revisão rápida dos pontos mais cobrados em provas: pneumonias, cardiopatias, hepatopatias.', 'mixed', 25, 65, '{"disciplineId":30002,"subjectIds":[30014],"difficulty":"mixed"}'),
(@t6, 2, 'Módulo 2 — Lesões Mais Cobradas (Sistemas Renal, Nervoso e Digestório)', 'Nefrites, enterites, encefalites. Diagnósticos diferenciais rápidos.', 'mixed', 25, 65, '{"disciplineId":30002,"subjectIds":[30014],"difficulty":"mixed"}'),
(@t6, 3, 'Módulo 3 — Neoplasias e Diagnóstico Final', 'Critérios de malignidade, tipos tumorais mais frequentes e diagnóstico integrado.', 'mixed', 25, 65, '{"disciplineId":30002,"subjectIds":[30014],"difficulty":"mixed"}');
SELECT CONCAT('Trilha 6 criada: ID ', @t6) AS resultado;

SELECT '=== 6 trilhas de Patobiologia inseridas com sucesso ===' AS status;
