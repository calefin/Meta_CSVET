/**
 * Une contas duplicadas pelo mesmo e-mail (conta "email" + conta "google" criadas pela mesma pessoa
 * quando o e-mail de verificação não chegou).
 *
 * Simulação (padrão — não altera nada):
 *   docker exec -i vetrank node scripts/merge_duplicate_accounts.mjs
 * Aplicar:
 *   docker exec -i vetrank node scripts/merge_duplicate_accounts.mjs --apply
 *
 * Regra:
 *  - "Principal" = a conta com mais valor: assinatura paga ativa > pagamentos aprovados >
 *    mais questões/simulados/XP > login mais recente.
 *  - O login Google passa a abrir a conta principal (ela recebe o openId google_*) e o e-mail
 *    fica marcado como verificado (o Google já confirmou a posse).
 *  - A conta secundária NÃO é apagada: recebe openId "merged_<id>_..." e e-mail "merged+<id>+...",
 *    saindo das buscas de login. Dá para reverter manualmente se preciso.
 *  - Pares em que AS DUAS contas têm uso relevante (> 20 questões cada) ou pagamentos nas duas
 *    são PULADOS e listados para decisão manual.
 */
import mysql from "mysql2/promise";

const APPLY = process.argv.includes("--apply");
const ACTIVITY_THRESHOLD = 20;

function score(u) {
  const now = Date.now();
  const activePremium = u.premiumEndsAt && new Date(u.premiumEndsAt).getTime() > now ? 1 : 0;
  return [
    activePremium,
    Number(u.approvedPayments) || 0,
    (Number(u.totalQuestions) || 0) + (Number(u.totalExams) || 0) * 10 + Math.floor((Number(u.xp) || 0) / 100),
    u.lastSignedIn ? new Date(u.lastSignedIn).getTime() : 0,
  ];
}
function cmp(a, b) {
  const sa = score(a), sb = score(b);
  for (let i = 0; i < sa.length; i++) if (sa[i] !== sb[i]) return sb[i] - sa[i];
  return a.id - b.id;
}

(async () => {
  const c = await mysql.createConnection(process.env.DATABASE_URL);
  const [dups] = await c.execute(`
    SELECT LOWER(email) AS email FROM users
    WHERE email IS NOT NULL AND email <> '' AND email NOT LIKE 'merged+%'
    GROUP BY LOWER(email) HAVING COUNT(*) > 1
  `);
  console.log(`${dups.length} e-mail(s) com contas duplicadas. Modo: ${APPLY ? "APLICAR" : "SIMULAÇÃO (use --apply para gravar)"}\n`);

  const skipped = [];
  let merged = 0;

  for (const { email } of dups) {
    const [accs] = await c.execute(`
      SELECT u.id, u.openId, u.loginMethod, u.plan, u.premiumEndsAt, u.subscriptionPlan, u.totalQuestions,
             u.totalExams, u.xp, u.lastSignedIn, u.emailVerifiedAt, (u.passwordHash IS NOT NULL) AS hasPassword,
             (SELECT COUNT(*) FROM payments p WHERE p.userId = u.id AND p.status = 'approved') AS approvedPayments
      FROM users u WHERE LOWER(u.email) = ? ORDER BY u.id
    `, [email]);

    if (accs.length !== 2) {
      skipped.push({ email, motivo: `${accs.length} contas — revisar manualmente`, contas: accs.map(a => a.id).join(",") });
      continue;
    }
    const google = accs.find(a => String(a.openId).startsWith("google_"));
    const other = accs.find(a => a !== google);
    if (!google || !other) {
      skipped.push({ email, motivo: "par sem exatamente 1 conta Google", contas: accs.map(a => `${a.id}:${a.loginMethod}`).join(",") });
      continue;
    }

    const bothActive = accs.every(a => (Number(a.totalQuestions) || 0) > ACTIVITY_THRESHOLD);
    const bothPaid = accs.every(a => Number(a.approvedPayments) > 0);
    if (bothActive || bothPaid) {
      skipped.push({
        email, motivo: bothPaid ? "pagamentos nas duas contas" : "uso relevante nas duas contas",
        contas: accs.map(a => `${a.id}:${a.loginMethod}:${a.plan}:${a.totalQuestions}q:${a.approvedPayments}pg`).join(" | "),
      });
      continue;
    }

    const [keeper, loser] = [...accs].sort(cmp);
    const googleOpenId = google.openId;
    console.log(`• ${email}`);
    console.log(`    principal  #${keeper.id} (${keeper.loginMethod}, ${keeper.plan}, ${keeper.totalQuestions}q, ${keeper.approvedPayments} pag.)`);
    console.log(`    secundária #${loser.id} (${loser.loginMethod}, ${loser.plan}, ${loser.totalQuestions}q, ${loser.approvedPayments} pag.)`);

    if (!APPLY) { merged++; continue; }

    await c.beginTransaction();
    try {
      // 1) tira a secundária do caminho (libera o openId google_* se for ela)
      await c.execute(
        `UPDATE users SET openId = LEFT(CONCAT('merged_', id, '_', openId), 64),
                          email = LEFT(CONCAT('merged+', id, '+', email), 320)
         WHERE id = ?`, [loser.id]);
      // 2) principal passa a responder pelo login Google e fica verificada
      if (loser.id === google.id) {
        await c.execute(`UPDATE users SET openId = ?, emailVerifiedAt = COALESCE(emailVerifiedAt, NOW()),
                           emailVerificationToken = NULL, emailVerificationTokenExpiresAt = NULL WHERE id = ?`,
          [googleOpenId, keeper.id]);
      } else {
        await c.execute(`UPDATE users SET emailVerifiedAt = COALESCE(emailVerifiedAt, NOW()),
                           emailVerificationToken = NULL, emailVerificationTokenExpiresAt = NULL WHERE id = ?`,
          [keeper.id]);
      }
      await c.commit();
      merged++;
      console.log(`    ✔ unidas`);
    } catch (e) {
      await c.rollback();
      console.error(`    ✖ erro — nada alterado neste par:`, e.message);
    }
  }

  console.log(`\n${APPLY ? "Unidas" : "Seriam unidas"}: ${merged}`);
  if (skipped.length) {
    console.log(`\nPuladas (decidir manualmente): ${skipped.length}`);
    console.table(skipped);
  }
  await c.end();
})().catch(e => { console.error(e); process.exit(1); });
