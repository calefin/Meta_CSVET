/** Admin panel (D-26; tabs D-62). Only for the site owner, so the text is pt-BR only. */
import { useCallback, useEffect, useRef, useState } from 'react';
import { admin, ApiError, type AdminOverview, type AdminStats, type AdminUser, type AuditEntry, type PromoCode, type YasirAdminReport } from '../api';
import { Errors, Finance, MpEvents, Overview, Payments, Testers, Tickets } from './admin/AdminOps';
import { useAuth } from '../auth';
import { useI18n } from '../i18n';
import { linkHandler, navigate } from '../router';

const TABS = [
  { id: '', label: 'Visão geral', icon: '📊' },
  { id: 'users', label: 'Usuários', icon: '👥' },
  { id: 'payments', label: 'Pagamentos', icon: '💳' },
  { id: 'finance', label: 'Financeiro', icon: '💰' },
  { id: 'tickets', label: 'Tickets', icon: '🎫' },
  { id: 'errors', label: 'Erros', icon: '🐞' },
  { id: 'moderation', label: 'Moderação', icon: '🛡️' },
  { id: 'promo', label: 'Promoções', icon: '🏷️' },
  { id: 'log', label: 'Log', icon: '📜' },
] as const;

const FILTERS = [
  { id: 'all', label: 'Todos' },
  { id: 'premium', label: 'Premium' },
  { id: 'testers', label: 'Testers' },
  { id: 'banned', label: 'Banidos' },
  { id: 'admin', label: 'Admins' },
];

const ACTION_LABELS: Record<string, string> = {
  update_user: 'alterou',
  reset_link: 'gerou link de senha',
  logout_all: 'encerrou sessões',
  delete_user: 'excluiu',
  grant_premium: 'deu Premium grátis para',
  promo_create: 'criou código',
  promo_enable: 'ativou código',
  promo_disable: 'desativou código',
  promo_delete: 'apagou código',
  yasir_delete_report: 'apagou relato do Yasir de',
  yasir_block: 'bloqueou relatos do Yasir de',
  yasir_unblock: 'desbloqueou relatos do Yasir de',
};

const BILLING_KINDS: Record<string, string> = {
  duplicate_refunded: '🔁 Assinatura duplicada cancelada e reembolsada',
  duplicate_refund_failed: '⚠️ Falha ao reembolsar duplicata — resolver no Stripe',
  unknown_customer: '❓ Evento do BagParser sem conta correspondente',
  customer_mismatch: '⚠️ Evento apontava para outra conta (ignorado)',
  cancelled_on_delete: '🗑️ Assinatura cancelada porque a conta foi excluída',
  sync_error: '⚠️ Erro ao sincronizar com o Stripe (o Stripe vai tentar de novo)',
  subscribed: '🎉 Nova assinatura',
  cancel_scheduled: '⏳ Assinante agendou o cancelamento (Pro até o fim do período)',
  resumed: '↩️ Assinante reativou a assinatura',
  payment_failed: '💳 Pagamento falhou ou atrasou',
  subscription_ended: '🔚 Assinatura encerrada',
  pix_paid: '⚡ Pix pago (Mercado Pago): dias de Pro adicionados',
  pix_refunded: '↩️ Pix devolvido no Mercado Pago — veja se precisa encerrar o Pro',
  mp_claim: '📣 Reclamação no Mercado Pago (responda pelo painel do MP)',
  mp_chargeback: '🚨 Contestação no Mercado Pago (envie documentos no prazo)',
};

/** Billing alerts (D-55): what the Stripe webhook had to fix or could not handle */
function BillingLog({ onError }: { onError: (msg: string) => void }) {
  const [rows, setRows] = useState<Awaited<ReturnType<typeof admin.billingLog>>['entries'] | null>(null);
  useEffect(() => {
    admin
      .billingLog()
      .then((r) => setRows(r.entries))
      .catch((err) => onError(errText(err)));
  }, []); // eslint-disable-line react-hooks/exhaustive-deps
  return (
    <section className="card">
      <h2>Alertas de cobrança (Stripe)</h2>
      <p className="muted small">
        Duplicatas são resolvidas sozinhas: fica a assinatura mais antiga e as outras são canceladas e reembolsadas. Itens com ⚠️ pedem
        conferência no painel do Stripe.
      </p>
      {rows === null ? (
        <p className="muted">Carregando…</p>
      ) : !rows.length ? (
        <p className="muted">Nenhum alerta. ✓</p>
      ) : (
        <div className="table-scroll">
          <table className="compact-table">
            <thead>
              <tr>
                <th>Quando</th>
                <th>O quê</th>
                <th>Conta</th>
                <th>Detalhes</th>
              </tr>
            </thead>
            <tbody>
              {rows.map((r) => (
                <tr key={r.id}>
                  <td className="small">{fmtDateTime(r.created_at)}</td>
                  <td>{BILLING_KINDS[r.kind] ?? r.kind}</td>
                  <td className="small">{r.email ?? '—'}</td>
                  <td className="small">
                    <code>{JSON.stringify(r.details)}</code>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}
    </section>
  );
}

/** Promo codes (D-50): discount (% off at checkout) or trial (N days of Premium when redeemed in /account) */
function PromoCodes({ onError }: { onError: (msg: string) => void }) {
  const [rows, setRows] = useState<PromoCode[] | null>(null);
  const [form, setForm] = useState({ code: '', kind: 'trial', percent_off: '20', premium_days: '30', max_uses: '', expires_at: '', note: '' });
  const [msg, setMsg] = useState('');
  const load = () =>
    admin
      .promoList()
      .then((r) => setRows(r.codes))
      .catch((err) => onError(errText(err)));
  useEffect(() => {
    void load();
  }, []); // eslint-disable-line react-hooks/exhaustive-deps

  const create = async () => {
    setMsg('');
    try {
      const r = await admin.promoCreate({
        code: form.code,
        kind: form.kind,
        percent_off: form.kind === 'discount' ? Number(form.percent_off) : null,
        premium_days: form.kind === 'trial' ? Number(form.premium_days) : null,
        max_uses: form.max_uses ? Number(form.max_uses) : null,
        expires_at: form.expires_at || null,
        note: form.note || null,
      });
      setMsg(`Código ${r.code} criado.`);
      setForm({ ...form, code: '', note: '' });
      void load();
    } catch (err) {
      setMsg(errText(err));
    }
  };
  const set = (k: keyof typeof form) => (e: React.ChangeEvent<HTMLInputElement | HTMLSelectElement>) => setForm({ ...form, [k]: e.target.value });

  return (
    <section className="card">
      <h2>Códigos promocionais</h2>
      <p className="muted small">
        <b>Teste grátis</b>: o usuário resgata em /account e ganha N dias de Premium (uma vez por conta). <b>Desconto</b>: % aplicado no
        checkout do Stripe (vira um cupom no Stripe) — o código já é validado e mostrado em /pricing.
      </p>
      <div className="form-grid">
        <label>
          <span>Código</span>
          <input value={form.code} onChange={set('code')} placeholder="TESTER30" maxLength={32} />
        </label>
        <label>
          <span>Tipo</span>
          <select value={form.kind} onChange={set('kind')}>
            <option value="trial">Teste grátis (dias de Premium)</option>
            <option value="discount">Desconto (%)</option>
          </select>
        </label>
        {form.kind === 'trial' ? (
          <label>
            <span>Dias</span>
            <input inputMode="numeric" value={form.premium_days} onChange={set('premium_days')} />
          </label>
        ) : (
          <label>
            <span>% de desconto</span>
            <input inputMode="numeric" value={form.percent_off} onChange={set('percent_off')} />
          </label>
        )}
        <label>
          <span>Máx. de usos (vazio = ilimitado)</span>
          <input inputMode="numeric" value={form.max_uses} onChange={set('max_uses')} />
        </label>
        <label>
          <span>Expira em</span>
          <input type="date" value={form.expires_at} onChange={set('expires_at')} />
        </label>
        <label>
          <span>Nota interna</span>
          <input value={form.note} onChange={set('note')} placeholder="Testers do Discord" />
        </label>
      </div>
      <div className="button-row">
        <button type="button" onClick={create} disabled={form.code.trim().length < 3}>
          Criar código
        </button>
      </div>
      {msg && <p className="notice">{msg}</p>}
      {rows && rows.length > 0 && (
        <div className="table-scroll">
          <table className="compact-table">
            <thead>
              <tr>
                <th>Código</th>
                <th>Benefício</th>
                <th className="num">Usos</th>
                <th>Expira</th>
                <th>Nota</th>
                <th />
              </tr>
            </thead>
            <tbody>
              {rows.map((c) => (
                <tr key={c.id} className={c.active ? '' : 'muted'}>
                  <td>
                    <code>{c.code}</code>
                  </td>
                  <td>{c.kind === 'trial' ? `${c.premium_days} dias Premium` : `${c.percent_off}% off`}</td>
                  <td className="num">
                    {c.uses}
                    {c.max_uses ? ` / ${c.max_uses}` : ''}
                  </td>
                  <td>{c.expires_at ? fmtDate(c.expires_at) : '—'}</td>
                  <td className="small">{c.note ?? ''}</td>
                  <td className="row-actions">
                    <button
                      type="button"
                      className="secondary"
                      onClick={() =>
                        admin
                          .promoToggle(c.id, !c.active)
                          .then(load)
                          .catch((e) => onError(errText(e)))
                      }
                    >
                      {c.active ? 'Desativar' : 'Ativar'}
                    </button>
                    <button
                      type="button"
                      className="secondary"
                      onClick={() => {
                        if (window.confirm(`Apagar o código ${c.code}?`))
                          admin
                            .promoDelete(c.id)
                            .then(load)
                            .catch((e) => onError(errText(e)));
                      }}
                    >
                      Apagar
                    </button>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}
    </section>
  );
}

/** Yasir report moderation (D-40): delete a report, or make an account's reports weigh 0 */
function YasirModeration({ onError }: { onError: (msg: string) => void }) {
  const [rows, setRows] = useState<YasirAdminReport[] | null>(null);
  const load = () =>
    admin
      .yasirReports(7)
      .then(setRows)
      .catch(() => onError('Não foi possível carregar os relatos do Yasir.'));
  const act = (p: Promise<unknown>) => p.then(load).catch(() => onError('A ação falhou.'));
  return (
    <section className="card">
      <div className="dest-head">
        <h2>Relatos do Yasir (7 dias)</h2>
        <button type="button" className="secondary" onClick={load}>
          {rows ? 'Atualizar' : 'Mostrar'}
        </button>
      </div>
      <p className="muted small">
        Peso = confiança do relato (2 = personagem verificado no mundo) × reputação. Bloquear zera o peso de todos os
        relatos da conta sem avisar a pessoa. Página pública: <a href="/yasir">/yasir</a>.
      </p>
      {rows && rows.length === 0 && <p className="muted">Nenhum relato.</p>}
      {rows && rows.length > 0 && (
        <div className="table-wrap">
          <table>
            <thead>
              <tr>
                <th>Dia</th>
                <th>Mundo</th>
                <th>Relato</th>
                <th>Quem</th>
                <th className="num">Peso</th>
                <th className="num">Reputação</th>
                <th />
              </tr>
            </thead>
            <tbody>
              {rows.map((r) => (
                <tr key={`${r.day}|${r.world}|${r.user_id}`} className={r.blocked ? 'muted' : ''}>
                  <td>{r.day}</td>
                  <td>{r.world}</td>
                  <td>{r.present ? `Viu · ${r.city === 'other' ? 'outro lugar' : r.city}` : 'Não estava'}</td>
                  <td>
                    {r.display_name} <span className="muted small">{r.email}</span>
                    {r.verified_on_world && <span className="tag">verificado</span>}
                    {r.blocked && <span className="tag banned">bloqueado</span>}
                  </td>
                  <td className="num">{r.weight}</td>
                  <td className="num">{r.reputation}</td>
                  <td>
                    <div className="button-row">
                      <button type="button" className="secondary small-btn" onClick={() => act(admin.deleteYasirReport(r))}>
                        Apagar
                      </button>
                      <button type="button" className="secondary small-btn" onClick={() => act(admin.blockYasir(r.user_id, !r.blocked))}>
                        {r.blocked ? 'Desbloquear' : 'Bloquear'}
                      </button>
                    </div>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}
    </section>
  );
}

const ERRORS: Record<string, string> = {
  cannot_demote_self: 'Você não pode tirar seu próprio admin.',
  cannot_ban_self: 'Você não pode banir a si mesmo.',
  cannot_delete_self: 'Exclua sua conta pela página Minha conta.',
  invalid_date: 'Data inválida.',
  invalid_name: 'Nome inválido.',
};

const errText = (err: unknown) => (err instanceof ApiError ? ERRORS[err.code] ?? `Erro: ${err.code}` : 'Erro de conexão.');

const fmtDate = (iso: string | null) => (iso ? new Date(iso).toLocaleDateString('pt-BR') : '—');
const fmtDateTime = (iso: string | null) =>
  iso ? new Date(iso).toLocaleString('pt-BR', { dateStyle: 'short', timeStyle: 'short' }) : '—';

function addDays(base: string | null, days: number): string {
  const start = base && new Date(base) > new Date() ? new Date(base) : new Date();
  start.setDate(start.getDate() + days);
  return start.toISOString();
}

function describe(details: Record<string, unknown> | null): string {
  if (!details) return '';
  return Object.entries(details)
    .map(([k, v]) => {
      if (k === 'premium_until') return v ? `premium até ${fmtDate(String(v))}` : 'premium removido';
      if (k === 'status') return v === 'banned' ? 'banido' : 'reativado';
      if (k === 'role') return v === 'admin' ? 'virou admin' : 'virou usuário';
      if (k === 'ban_reason') return `motivo: ${String(v)}`;
      if (k === 'display_name') return `nome: ${String(v)}`;
      return `${k}: ${String(v)}`;
    })
    .join(', ');
}

// ---------------------------------------------------------------------------

function StatsPanel({ stats }: { stats: AdminStats }) {
  const { n } = useI18n();
  const [copied, setCopied] = useState(false);
  const u = stats.users;
  const last = stats.traffic[1]; // previous (complete) month

  const report = () => {
    const lines = stats.traffic
      .slice(0, 6)
      .map((m) => `${m.month}: ${m.views} page views, ${m.visitor_days} visits (unique visitors per day, summed), peak ${m.peak_day}/day`);
    return `BagParser (https://bagparser.com) - traffic statistics\n${lines.join('\n')}`;
  };

  return (
    <>
      <div className="metrics admin-metrics">
        <div className="metric">
          <span className="muted small">Usuários</span>
          <span className="metric-value">{n(u.total)}</span>
        </div>
        <div className="metric">
          <span className="muted small">Novos (7 / 30 dias)</span>
          <span className="metric-value">
            {n(u.new_7d)} / {n(u.new_30d)}
          </span>
        </div>
        <div className="metric">
          <span className="muted small">Ativos (30 dias)</span>
          <span className="metric-value">{n(u.active_30d)}</span>
        </div>
        <div className="metric">
          <span className="muted small">Premium</span>
          <span className="metric-value">{n(u.premium)}</span>
        </div>
        <div className="metric">
          <span className="muted small">Banidos</span>
          <span className="metric-value">{n(u.banned)}</span>
        </div>
      </div>

      <section className="card">
        <div className="dest-head">
          <h2>Tráfego mensal</h2>
          <button
            type="button"
            className="secondary"
            onClick={() =>
              navigator.clipboard.writeText(report()).then(() => {
                setCopied(true);
                setTimeout(() => setCopied(false), 1500);
              })
            }
          >
            {copied ? 'Copiado!' : 'Copiar relatório (EN)'}
          </button>
        </div>
        <p className="muted small">
          O Fansite Agreement pede as estatísticas do mês anterior até o dia 3 de cada mês
          {last ? ` (${last.month}: ${n(last.views)} views)` : ''}. Contagem anônima: só totais por dia, sem guardar IP.
        </p>
        <div className="table-wrap">
          <table>
            <thead>
              <tr>
                <th>Mês</th>
                <th className="num">Page views</th>
                <th className="num">Visitas</th>
                <th className="num">Pico/dia</th>
              </tr>
            </thead>
            <tbody>
              {stats.traffic.map((m) => (
                <tr key={m.month}>
                  <td>{m.month}</td>
                  <td className="num">{n(m.views)}</td>
                  <td className="num">{n(m.visitor_days)}</td>
                  <td className="num">{n(m.peak_day)}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
        {stats.top_paths.length > 0 && (
          <p className="muted small">
            Este mês: {stats.top_paths.map((p) => `${p.path} (${n(p.views)})`).join(' · ')}
          </p>
        )}
      </section>
    </>
  );
}

// ---------------------------------------------------------------------------

function UserDetail({ id, onChanged, onClose }: { id: number; onChanged: () => void; onClose: () => void }) {
  const { user: me } = useAuth();
  const [data, setData] = useState<Awaited<ReturnType<typeof admin.user>> | null>(null);
  const [msg, setMsg] = useState<{ ok: boolean; text: string } | null>(null);
  const [premiumDate, setPremiumDate] = useState('');
  const [banReason, setBanReason] = useState('');
  const [resetUrl, setResetUrl] = useState<string | null>(null);
  const ref = useRef<HTMLElement>(null);

  // On phones the detail is below the list: bring it into view
  useEffect(() => {
    ref.current?.scrollIntoView({ behavior: 'smooth', block: 'start' });
  }, [id]);

  const load = useCallback(() => {
    admin
      .user(id)
      .then((d) => {
        setData(d);
        setPremiumDate(d.user.premium_until ? d.user.premium_until.slice(0, 10) : '');
      })
      .catch((err) => setMsg({ ok: false, text: errText(err) }));
  }, [id]);

  useEffect(() => {
    setData(null);
    setMsg(null);
    setResetUrl(null);
    setBanReason('');
    load();
  }, [load]);

  const run = async (fn: () => Promise<unknown>, ok: string) => {
    setMsg(null);
    try {
      await fn();
      setMsg({ ok: true, text: ok });
      load();
      onChanged();
    } catch (err) {
      setMsg({ ok: false, text: errText(err) });
    }
  };

  if (!data) return <section className="card" ref={ref}>{msg ? <p className="alert">{msg.text}</p> : <p className="muted">Carregando...</p>}</section>;
  const u = data.user;
  const self = me?.id === u.id;

  return (
    <section className="card user-detail" ref={ref}>
      <div className="dest-head">
        <h2>{u.display_name}</h2>
        <button type="button" className="secondary" onClick={onClose}>
          Fechar
        </button>
      </div>
      <dl className="kv">
        <dt>E-mail</dt>
        <dd>{u.email}</dd>
        <dt>Personagem</dt>
        <dd>{u.tibia_character ?? '—'}</dd>
        <dt>Cadastro</dt>
        <dd>{fmtDateTime(u.created_at)}</dd>
        <dt>Último login</dt>
        <dd>{fmtDateTime(u.last_login_at)}</dd>
        <dt>Papel</dt>
        <dd>{u.role === 'admin' ? 'Admin' : 'Usuário'}</dd>
        <dt>Status</dt>
        <dd>{u.status === 'banned' ? `Banido${u.ban_reason ? ` — ${u.ban_reason}` : ''}` : 'Ativo'}</dd>
        <dt>Premium</dt>
        <dd>
          {u.premium_until ? `${u.premium ? 'até' : 'expirou em'} ${fmtDate(u.premium_until)}` : 'Não'}
          {u.premium_note && <span className="tag premium"> {u.premium_note}</span>}
        </dd>
      </dl>

      {msg && <p className={msg.ok ? 'notice' : 'alert'}>{msg.text}</p>}

      <h3 className="small-heading">Tester (Premium grátis, soma ao tempo restante)</h3>
      <div className="button-row">
        {[7, 15, 30, 60].map((d) => (
          <button key={d} type="button" onClick={() => run(() => admin.grant(u.id, d, 'tester'), `Tester: +${d} dias de Premium grátis.`)}>
            🧪 Tester {d} dias
          </button>
        ))}
      </div>

      <h3 className="small-heading">Premium</h3>
      <div className="button-row">
        {[30, 90, 365].map((d) => (
          <button
            key={d}
            type="button"
            className="secondary"
            onClick={() => run(() => admin.update(u.id, { premium_until: addDays(u.premium_until, d) }), `+${d} dias de Premium.`)}
          >
            +{d} dias
          </button>
        ))}
        {u.premium_until && (
          <button type="button" className="secondary" onClick={() => run(() => admin.update(u.id, { premium_until: null }), 'Premium removido.')}>
            Remover
          </button>
        )}
      </div>
      <div className="inline-form">
        <input type="date" value={premiumDate} onChange={(e) => setPremiumDate(e.target.value)} aria-label="Premium até" />
        <button
          type="button"
          className="secondary"
          disabled={!premiumDate}
          onClick={() => run(() => admin.update(u.id, { premium_until: `${premiumDate}T23:59:59Z` }), 'Data do Premium salva.')}
        >
          Definir data
        </button>
      </div>

      <h3 className="small-heading">Conta</h3>
      <div className="button-row">
        {!self && (
          <button
            type="button"
            className="secondary"
            onClick={() =>
              run(() => admin.update(u.id, { role: u.role === 'admin' ? 'user' : 'admin' }), u.role === 'admin' ? 'Admin removido.' : 'Agora é admin.')
            }
          >
            {u.role === 'admin' ? 'Tirar admin' : 'Tornar admin'}
          </button>
        )}
        <button
          type="button"
          className="secondary"
          onClick={() =>
            run(async () => {
              const r = await admin.resetLink(u.id);
              setResetUrl(`${window.location.origin}${r.path}`);
            }, 'Link criado (vale 48 h, uso único). Envie para o usuário.')
          }
        >
          Link de nova senha
        </button>
        <button type="button" className="secondary" onClick={() => run(() => admin.logoutAll(u.id), 'Sessões encerradas.')}>
          Encerrar sessões ({data.sessions.length})
        </button>
      </div>
      {resetUrl && (
        <p className="reset-url">
          <code>{resetUrl}</code>{' '}
          <button type="button" className="secondary" onClick={() => navigator.clipboard.writeText(resetUrl)}>
            Copiar
          </button>
        </p>
      )}

      {!self && (
        <>
          <h3 className="small-heading">Moderação</h3>
          {u.status === 'active' ? (
            <div className="inline-form">
              <input value={banReason} onChange={(e) => setBanReason(e.target.value)} placeholder="Motivo do ban (opcional)" maxLength={500} />
              <button type="button" className="danger" onClick={() => run(() => admin.update(u.id, { status: 'banned', ban_reason: banReason }), 'Usuário banido.')}>
                Banir
              </button>
            </div>
          ) : (
            <button type="button" className="secondary" onClick={() => run(() => admin.update(u.id, { status: 'active' }), 'Usuário reativado.')}>
              Remover ban
            </button>
          )}
          <div className="button-row">
            <button
              type="button"
              className="secondary danger"
              onClick={() => {
                if (window.confirm(`Excluir a conta de ${u.email}? Isso não pode ser desfeito.`)) {
                  admin
                    .remove(u.id)
                    .then(() => {
                      onChanged();
                      onClose();
                    })
                    .catch((err) => setMsg({ ok: false, text: errText(err) }));
                }
              }}
            >
              Excluir conta
            </button>
          </div>
        </>
      )}

      {data.sessions.length > 0 && (
        <>
          <h3 className="small-heading">Sessões ativas</h3>
          <ul className="plain-list small">
            {data.sessions.map((s, i) => (
              <li key={i}>
                {fmtDateTime(s.last_seen_at)} · {s.ip ?? '?'} · <span className="muted">{(s.user_agent ?? '').slice(0, 60)}</span>
              </li>
            ))}
          </ul>
        </>
      )}

      {data.audit.length > 0 && (
        <>
          <h3 className="small-heading">Histórico</h3>
          <ul className="plain-list small">
            {data.audit.map((a, i) => (
              <li key={i}>
                {fmtDateTime(a.created_at)} · {a.admin_email} {ACTION_LABELS[a.action] ?? a.action} {describe(a.details)}
              </li>
            ))}
          </ul>
        </>
      )}
    </section>
  );
}

// ---------------------------------------------------------------------------

export function AdminPage({ path = '/admin' }: { path?: string }) {
  const tab = (path.split('/').filter(Boolean)[1] ?? '') as (typeof TABS)[number]['id'];
  const [overview, setOverview] = useState<AdminOverview | null>(null);
  const { user } = useAuth();
  const { n } = useI18n();
  const [stats, setStats] = useState<AdminStats | null>(null);
  const [q, setQ] = useState('');
  const [query, setQuery] = useState('');
  const [filter, setFilter] = useState('all');
  const [page, setPage] = useState(1);
  const [list, setList] = useState<{ total: number; page_size: number; users: AdminUser[] } | null>(null);
  const [selected, setSelected] = useState<number | null>(null);
  const [audit, setAudit] = useState<AuditEntry[] | null>(null);
  const [error, setError] = useState<string | null>(null);

  const isAdmin = user?.role === 'admin';

  useEffect(() => {
    if (user === null) navigate('/login');
  }, [user]);

  const loadStats = useCallback(() => {
    admin.stats().then(setStats).catch((err) => setError(errText(err)));
    admin.overview().then(setOverview).catch((err) => setError(errText(err)));
  }, []);

  const loadList = useCallback(() => {
    admin
      .users(query, filter, page)
      .then(setList)
      .catch((err) => setError(errText(err)));
  }, [query, filter, page]);

  useEffect(() => {
    if (isAdmin) loadStats();
  }, [isAdmin, loadStats]);

  useEffect(() => {
    if (isAdmin && tab === 'users') loadList();
  }, [isAdmin, loadList, tab]);

  useEffect(() => {
    if (isAdmin && tab === 'log' && !audit) admin.audit().then(setAudit).catch((err) => setError(errText(err)));
  }, [isAdmin, tab, audit]);

  // Debounce the search box
  useEffect(() => {
    const id = setTimeout(() => {
      setQuery(q.trim());
      setPage(1);
    }, 300);
    return () => clearTimeout(id);
  }, [q]);

  if (user === undefined) return <p className="muted">Carregando...</p>;
  if (!isAdmin) return <p className="alert">Acesso restrito a administradores.</p>;

  const pages = list ? Math.max(1, Math.ceil(list.total / list.page_size)) : 1;

  return (
    <div className="page admin-page">
      <h1>Administração</h1>
      <nav className="tabs adm-tabs" aria-label="Seções do admin">
        {TABS.map((tb) => {
          const href = tb.id ? `/admin/${tb.id}` : '/admin';
          const badge =
            tb.id === 'tickets' ? (overview?.tickets.pending ?? 0) + (overview?.tickets.open ?? 0) : tb.id === 'errors' ? overview?.errors.open ?? 0 : 0;
          return (
            <a key={tb.id} href={href} className={tab === tb.id ? 'tab active' : 'tab'} aria-current={tab === tb.id ? 'page' : undefined} onClick={linkHandler(href)}>
              <span aria-hidden="true">{tb.icon}</span> {tb.label}
              {badge > 0 && <span className="adm-badge">{badge}</span>}
            </a>
          );
        })}
      </nav>
      {error && <p className="alert">{error}</p>}

      {tab === '' && (
        <>
          <Overview overview={overview} stats={stats} />
          {stats && <StatsPanel stats={stats} />}
        </>
      )}
      {tab === 'payments' && (
        <>
          <Testers />
          <Payments
            onOpenUser={(id) => {
              setSelected(id);
              navigate('/admin/users');
            }}
          />
          <MpEvents />
          <BillingLog onError={setError} />
        </>
      )}
      {tab === 'finance' && <Finance />}
      {tab === 'tickets' && <Tickets onChanged={loadStats} />}
      {tab === 'errors' && <Errors onChanged={loadStats} />}
      {tab === 'moderation' && <YasirModeration onError={setError} />}
      {tab === 'promo' && <PromoCodes onError={setError} />}

      {tab === 'users' && (
      <section className="card">
        <h2>Usuários</h2>
        <div className="admin-toolbar">
          <input type="search" value={q} onChange={(e) => setQ(e.target.value)} placeholder="Buscar e-mail, nome ou personagem" aria-label="Buscar usuários" />
          <div className="chips-row" role="group" aria-label="Filtro">
            {FILTERS.map((f) => (
              <button
                key={f.id}
                type="button"
                className={filter === f.id ? 'chip active' : 'chip'}
                onClick={() => {
                  setFilter(f.id);
                  setPage(1);
                }}
              >
                {f.label}
              </button>
            ))}
          </div>
        </div>

        {!list && <p className="muted">Carregando...</p>}
        {list && list.users.length === 0 && <p className="muted">Nenhum usuário encontrado.</p>}
        {list && list.users.length > 0 && (
          <ul className="user-list">
            {list.users.map((u) => (
              <li key={u.id}>
                <button type="button" className={selected === u.id ? 'user-row active' : 'user-row'} onClick={() => setSelected(u.id)}>
                  <span className="user-main">
                    <strong>{u.display_name}</strong>
                    <span className="muted small">{u.email}</span>
                  </span>
                  <span className="user-tags">
                    {u.role === 'admin' && <span className="tag admin">Admin</span>}
                    {u.premium && <span className="tag premium">Premium</span>}
                    {u.premium_note && <span className="tag">{u.premium_note}</span>}
                    {u.status === 'banned' && <span className="tag banned">Banido</span>}
                    {u.email_verified_at === null && <span className="tag">e-mail não confirmado</span>}
                    <span className="muted small">{fmtDate(u.created_at)}</span>
                  </span>
                </button>
              </li>
            ))}
          </ul>
        )}
        {list && pages > 1 && (
          <div className="pager">
            <button type="button" className="secondary" disabled={page <= 1} onClick={() => setPage(page - 1)}>
              ‹
            </button>
            <span className="muted small">
              Página {page} de {pages} · {n(list.total)} usuários
            </span>
            <button type="button" className="secondary" disabled={page >= pages} onClick={() => setPage(page + 1)}>
              ›
            </button>
          </div>
        )}
      </section>

      )}

      {tab === 'users' && selected != null && (
        <UserDetail
          id={selected}
          onChanged={() => {
            loadList();
            loadStats();
          }}
          onClose={() => setSelected(null)}
        />
      )}

      {tab === 'log' && (
      <section className="card">
        <div className="dest-head">
          <h2>Log de ações dos admins</h2>
          <button type="button" className="secondary" onClick={() => admin.audit().then(setAudit).catch((err) => setError(errText(err)))}>
            {audit ? 'Atualizar' : 'Mostrar'}
          </button>
        </div>
        {audit && audit.length === 0 && <p className="muted">Nenhuma ação registrada.</p>}
        {audit && audit.length > 0 && (
          <ul className="plain-list small">
            {audit.map((a, i) => (
              <li key={i}>
                {fmtDateTime(a.created_at)} · {a.admin_email} {ACTION_LABELS[a.action] ?? a.action} {a.target_email ?? ''}{' '}
                <span className="muted">{describe(a.details)}</span>
              </li>
            ))}
          </ul>
        )}
      </section>
      )}
    </div>
  );
}
