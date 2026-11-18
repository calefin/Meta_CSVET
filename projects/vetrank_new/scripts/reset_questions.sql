-- ============================================================
--  VetRank — Reset de Questões
--  O QUE FAZ: apaga todas as questões e dados relacionados
--  O QUE PRESERVA: disciplines, subjects, topics, users, badges,
--                  payments, announcements, referrals, leads, plans
--  ATENÇÃO: operação IRREVERSÍVEL — faça backup antes de rodar
-- ============================================================

SET FOREIGN_KEY_CHECKS = 0;

-- ── 1. Dados de resposta e prática ──────────────────────────
TRUNCATE TABLE trail_module_answers;
TRUNCATE TABLE trail_module_progress;
TRUNCATE TABLE trail_enrollments;
TRUNCATE TABLE trail_module_questions;
TRUNCATE TABLE exam_answers;
TRUNCATE TABLE exams;
TRUNCATE TABLE practice_sessions;

-- ── 2. Validação e atribuições ───────────────────────────────
TRUNCATE TABLE question_assignments;
TRUNCATE TABLE question_reports;

-- ── 3. Estrutura de modelos especiais de questão ─────────────
TRUNCATE TABLE question_assertivas;
TRUNCATE TABLE question_matching_cols;
TRUNCATE TABLE question_matching_pairs;
TRUNCATE TABLE question_groups;

-- ── 4. Questões (principal) ──────────────────────────────────
TRUNCATE TABLE discursive_questions;
TRUNCATE TABLE questions;

-- ── 5. Trilhas — apaga módulos mas mantém as trilhas-pai ─────
--  Se quiser apagar as trilhas também, descomente as 2 linhas abaixo:
-- TRUNCATE TABLE trail_modules;
-- TRUNCATE TABLE trails;
--  (manter trilhas vazias é ok — elas podem ser repopuladas depois)
TRUNCATE TABLE trail_modules;
TRUNCATE TABLE trails;

-- ── 6. Filtros salvos de questões ────────────────────────────
TRUNCATE TABLE saved_filters;

-- ── 7. Resetar estatísticas dos usuários ────────────────────
--  (mantém xp/nível ganhos em jogos, mas zera contadores de questões)
UPDATE users SET
  totalExams      = 0,
  totalQuestions  = 0,
  totalCorrect    = 0;

-- ── 8. Limpar eventos de XP e leaderboard ───────────────────
--  Descomente abaixo se quiser zerar XP/ranking também:
-- TRUNCATE TABLE xp_events;
-- TRUNCATE TABLE weekly_xp;
-- TRUNCATE TABLE monthly_xp;
-- UPDATE users SET xp = 0, level = 1, streak = 0;

SET FOREIGN_KEY_CHECKS = 1;

-- ── Verificação ──────────────────────────────────────────────
SELECT 'questions'           AS tabela, COUNT(*) AS registros FROM questions
UNION ALL
SELECT 'discursive_questions',          COUNT(*) FROM discursive_questions
UNION ALL
SELECT 'exams',                         COUNT(*) FROM exams
UNION ALL
SELECT 'exam_answers',                  COUNT(*) FROM exam_answers
UNION ALL
SELECT 'practice_sessions',             COUNT(*) FROM practice_sessions
UNION ALL
SELECT 'trails',                        COUNT(*) FROM trails
UNION ALL
SELECT 'trail_modules',                 COUNT(*) FROM trail_modules
UNION ALL
SELECT 'disciplines (preservado)',      COUNT(*) FROM disciplines
UNION ALL
SELECT 'subjects (preservado)',         COUNT(*) FROM subjects
UNION ALL
SELECT 'topics (preservado)',           COUNT(*) FROM topics
UNION ALL
SELECT 'users (preservado)',            COUNT(*) FROM users;
