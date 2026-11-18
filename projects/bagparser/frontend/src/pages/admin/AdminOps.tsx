/** Admin dashboard (D-62): overview, payments, support tickets, error log. Owner-only, so pt-BR only. */
import { useCallback, useEffect, useMemo, useState } from 'react';
import { admin, ApiError, type AdminError, type AdminFinance, type AdminMpEvent, type AdminOverview, type AdminPaymentUser, type AdminStats, type AdminTicket, type AdminUser, type TicketStatus } from '../../api';
import { downloadText, toCsv } from '../../core/huntReport';
import { navigate, linkHandler } from '../../router';
import { CopyText } from '../../components/CopyText';

const errText = (err: unknown) => (err instanceof ApiError ? `Erro: ${err.code}` : 'Falha de conexão');
const fmtDateTime = (v: string | null) => (v ? new Date(v).toLocaleString('pt-BR', { day: '2-digit', month: '2-digit', year: '2-digit', hour: '2-digit', minute: '2-digit' }) : '—');
const fmtDate = (v: string | null) => (v ? new Date(v).toLocaleDateString('pt-BR') : '—');

/** "há 5 min", "há 3 h", "há 2 dias" */
export function ago(v: string | null): string {
  if (!v) return '—';
  const s = Math.max(0, (Date.now() - new Date(v).getTime()) / 1000);
  if (s < 60) return 'agora';
  if (s < 3600) return `há ${Math.round(s / 60)} min`;
  if (s < 86400) return `há ${Math.round(s / 3600)} h`;
  return `há ${Math.round(s / 86400)} dias`;
}

// ---------------------------------------------------------------------------
// Overview
// ---------------------------------------------------------------------------

function Card({ title, value, sub, to, tone }: { title: string; value: string | number; sub?: string; to?: string; tone?: 'warn' | 'ok' }) {
  const body = (
    <>
      <span className="adm-card-title">{title}</span>
      <strong className="adm-card-value">{value}</strong>
      {sub && <span className="muted small">{sub}</span>}
    </>
  );
  return to ? (
    <a className={`adm-card${tone ? ` ${tone}` : ''}`} href={to} onClick={linkHandler(to)}>
      {body}
    </a>
  ) : (
    <div className={`adm-card${tone ? ` ${tone}` : ''}`}>{body}</div>
  );
}

export function Overview({ overview, stats }: { overview: AdminOverview | null; stats: AdminStats | null }) {
  if (!overview || !stats) return <p className="muted">Carregando...</p>;
  const o = overview;
  const ticketsActive = o.tickets.pending + o.tickets.open;
  const todo: { text: string; to: string }[] = [];
  if (o.tickets.pending) todo.push({ text: `${o.tickets.pending} ticket(s) novo(s) sem resposta`, to: '/admin/tickets' });
  if (o.errors.last_24h) todo.push({ text: `${o.errors.last_24h} erro(s) aconteceram nas últimas 24 h`, to: '/admin/errors' });
  if (o.billing.problems) todo.push({ text: `${o.billing.problems} assinatura(s) com pagamento pendente ou falho`, to: '/admin/payments' });
  if (o.billing.alerts_7d) todo.push({ text: `${o.billing.alerts_7d} alerta(s) de cobrança nos últimos 7 dias`, to: '/admin/payments' });

  return (
    <div className="adm-overview">
      {(o.warnings ?? []).map((w) => (
        <ConfigWarning key={w} warning={w} />
      ))}
      <section className="card">
        <h2>Precisa de atenção</h2>
        {todo.length ? (
          <ul className="adm-todo">
            {todo.map((x) => (
              <li key={x.text}>
                <a href={x.to} onClick={linkHandler(x.to)}>
                  ⚠️ {x.text} →
                </a>
              </li>
            ))}
          </ul>
        ) : (
          <p className="pos">✓ Nada pendente agora.</p>
        )}
      </section>
      <div className="adm-cards">
        <Card title="Usuários" value={stats.users.total} sub={`+${stats.users.new_7d} em 7 dias · ${stats.users.active_30d} ativos em 30 dias`} to="/admin/users" />
        <Card title="Assinantes pagando" value={o.billing.paying} sub={`${o.billing.cancelling} cancelando no fim do período`} to="/admin/payments" tone={o.billing.problems ? 'warn' : undefined} />
        <Card title="Premium manual / testers" value={o.billing.manual_premium} sub="dado pelo admin ou código" to="/admin/payments" />
        <Card title="Tickets abertos" value={ticketsActive} sub={`${o.tickets.pending} novos · ${o.tickets.answered} respondidos`} to="/admin/tickets" tone={o.tickets.pending ? 'warn' : 'ok'} />
        <Card title="Erros abertos" value={o.errors.open} sub={`${o.errors.last_24h} nas últimas 24 h · ${o.errors.occurrences} ocorrências`} to="/admin/errors" tone={o.errors.last_24h ? 'warn' : 'ok'} />
        <Card
          title="Backup do banco"
          value={o.backup?.ok ? ago(o.backup.at) : o.backup ? 'falhou' : 'nenhum'}
          sub={o.backup?.ok ? `${o.backup.file} · ${(o.backup.size / 1024 / 1024).toFixed(1)} MB · todo dia 03:00, guarda 14 dias` : 'o container de backup grava em /opt/apps/bagparser/backups'}
          tone={o.backup?.ok && Date.now() - Date.parse(o.backup.at) < 36 * 3_600_000 ? 'ok' : 'warn'}
        />
        <MailCard overview={o} />
        <Card
          title="Anúncios (AdSense)"
          value={o.ads?.slot ? 'ligados' : o.ads?.client ? 'verificação' : 'desligados'}
          sub={
            o.ads?.slot
              ? `grátis veem 1 anúncio no fim das páginas públicas · ${o.ads.client}`
              : o.ads?.client
                ? 'ads.txt publicado: aguarde a aprovação do Google e coloque ADSENSE_SLOT'
                : 'coloque ADSENSE_CLIENT no .env para o Google verificar o site'
          }
          tone={o.ads?.slot ? 'ok' : undefined}
        />
        <Card title="Hunts salvas" value={o.content.hunts} sub={`+${o.content.hunts_7d} em 7 dias · ${o.content.payouts} acertos · ${o.content.characters} personagens`} />
      </div>
    </div>
  );
}

// ---------------------------------------------------------------------------
// Payments
// ---------------------------------------------------------------------------

const PAY_FILTERS = [
  { id: 'all', label: 'Todos' },
  { id: 'paying', label: 'Pagando' },
  { id: 'cancelling', label: 'Cancelando' },
  { id: 'problem', label: 'Com problema' },
  { id: 'manual', label: 'Premium manual' },
  { id: 'ended', label: 'Encerrados' },
] as const;

const STATUS_LABEL: Record<string, string> = {
  active: 'ativa',
  trialing: 'em teste',
  past_due: 'pagamento atrasado',
  unpaid: 'não paga',
  incomplete: 'incompleta',
  incomplete_expired: 'expirou',
  canceled: 'cancelada',
  paused: 'pausada',
};

function payKind(u: AdminPaymentUser): (typeof PAY_FILTERS)[number]['id'] {
  const s = u.stripe_status ?? '';
  if (['active', 'trialing'].includes(s)) return u.cancel_at_period_end ? 'cancelling' : 'paying';
  if (['past_due', 'unpaid', 'incomplete'].includes(s)) return 'problem';
  if (u.premium) return 'manual';
  return 'ended';
}

export function Payments({ onOpenUser }: { onOpenUser: (id: number) => void }) {
  const [rows, setRows] = useState<AdminPaymentUser[] | null>(null);
  const [filter, setFilter] = useState<(typeof PAY_FILTERS)[number]['id']>('all');
  const [error, setError] = useState('');
  useEffect(() => {
    admin
      .payments()
      .then((r) => setRows(r.users))
      .catch((e) => setError(errText(e)));
  }, []);
  const counts = useMemo(() => {
    const c: Record<string, number> = { all: rows?.length ?? 0 };
    for (const u of rows ?? []) c[payKind(u)] = (c[payKind(u)] ?? 0) + 1;
    return c;
  }, [rows]);
  const shown = (rows ?? []).filter((u) => filter === 'all' || payKind(u) === filter);

  return (
    <section className="card">
      <h2>Assinaturas e Premium</h2>
      <p className="muted small">
        Assinaturas vêm do Stripe (webhook). O valor e os recibos ficam no painel do Stripe; aqui você vê quem está pagando, quem vai cancelar e
        quem tem Premium dado à mão.
      </p>
      {error && <p className="alert">{error}</p>}
      <div className="chips-row" role="group" aria-label="Filtro de pagamentos">
        {PAY_FILTERS.map((f) => (
          <button key={f.id} type="button" className={filter === f.id ? 'chip active' : 'chip'} onClick={() => setFilter(f.id)}>
            {f.label} ({counts[f.id] ?? 0})
          </button>
        ))}
      </div>
      {!rows ? (
        <p className="muted">Carregando...</p>
      ) : !shown.length ? (
        <p className="muted">Ninguém aqui.</p>
      ) : (
        <div className="table-scroll">
          <table className="compact-table adm-table">
            <thead>
              <tr>
                <th>Conta</th>
                <th>Situação</th>
                <th>Premium até</th>
                <th>Assinatura</th>
              </tr>
            </thead>
            <tbody>
              {shown.map((u) => {
                const k = payKind(u);
                return (
                  <tr key={u.id}>
                    <td>
                      <button type="button" className="adm-link" onClick={() => onOpenUser(u.id)}>
                        <strong>{u.display_name}</strong>
                      </button>
                      <div className="muted small">{u.email}</div>
                    </td>
                    <td>
                      <span className={`tag ${k === 'paying' ? 'ok' : k === 'problem' ? 'banned' : k === 'cancelling' ? 'pro' : ''}`}>
                        {u.stripe_status ? STATUS_LABEL[u.stripe_status] ?? u.stripe_status : k === 'manual' ? 'manual' : '—'}
                      </span>
                      {u.cancel_at_period_end && <div className="muted small">cancela no fim do período</div>}
                      {u.premium_note && <div className="muted small">{u.premium_note}</div>}
                    </td>
                    <td>{fmtDate(u.premium_until)}</td>
                    <td className="small">{u.stripe_subscription_id ? <CopyText text={u.stripe_subscription_id} /> : '—'}</td>
                  </tr>
                );
              })}
            </tbody>
          </table>
        </div>
      )}
    </section>
  );
}

// ---------------------------------------------------------------------------
// Support tickets
// ---------------------------------------------------------------------------

const TICKET_FILTERS: { id: string; label: string }[] = [
  { id: 'active', label: 'Abertos' },
  { id: 'pending', label: 'Novos' },
  { id: 'open', label: 'Em andamento' },
  { id: 'answered', label: 'Respondidos' },
  { id: 'closed', label: 'Fechados' },
  { id: 'all', label: 'Todos' },
];
const TICKET_LABEL: Record<TicketStatus, string> = { pending: 'novo', open: 'em andamento', answered: 'respondido', closed: 'fechado' };
const CATEGORY_LABEL: Record<string, string> = { telegram: 'Telegram', problem: 'Problema', bug: 'Erro técnico', boss_loot: 'Loot de boss / Server Log', account: 'Conta / login', suggestion: 'Sugestão', other: 'Outro' };

/** D-75: conversation of a ticket (Telegram messages and answers) + answer by Telegram */
function TicketThread({ t, onSent }: { t: AdminTicket; onSent: () => void }) {
  const [replies, setReplies] = useState<{ id: number; from_admin: boolean; body: string; via: string; created_at: string }[] | null>(null);
  const [text, setText] = useState('');
  const [busy, setBusy] = useState(false);
  const [err, setErr] = useState('');
  const load = useCallback(() => {
    admin
      .ticketReplies(t.id)
      .then((r) => setReplies(r.replies))
      .catch(() => setReplies([]));
  }, [t.id]);
  useEffect(load, [load]);
  const sendReply = async () => {
    setBusy(true);
    setErr('');
    try {
      await admin.replyTicket(t.id, text.trim());
      setText('');
      load();
      onSent();
    } catch (e) {
      const code = e instanceof ApiError ? e.code : '';
      setErr(
        code === 'no_telegram'
          ? 'Esse ticket não tem Telegram: responda por e-mail.'
          : code === 'telegram_blocked'
            ? 'A pessoa bloqueou o bot.'
            : code === 'telegram_off'
              ? 'Bot do Telegram desligado (TELEGRAM_BOT_TOKEN).'
              : errText(e),
      );
    } finally {
      setBusy(false);
    }
  };
  return (
    <div className="ticket-thread">
      {replies && replies.length > 0 && (
        <ul className="ticket-replies">
          {replies.map((r) => (
            <li key={r.id} className={r.from_admin ? 'ticket-reply admin' : 'ticket-reply'}>
              <span className="muted small">
                {r.from_admin ? 'Você' : t.name} · {r.via === 'admin' ? 'painel' : 'Telegram'} · {fmtDateTime(r.created_at)}
              </span>
              <div className="ticket-message">{r.body}</div>
            </li>
          ))}
        </ul>
      )}
      {t.telegram && (
        <>
          <label className="field">
            <span>Responder pelo Telegram (a pessoa recebe no chat com o bot)</span>
            <textarea rows={3} value={text} onChange={(e) => setText(e.target.value)} maxLength={3500} />
          </label>
          <div className="button-row">
            <button type="button" className="button" disabled={busy || text.trim().length < 2} onClick={() => void sendReply()}>
              ✈️ Enviar pelo Telegram
            </button>
          </div>
        </>
      )}
      {err && <p className="alert">{err}</p>}
    </div>
  );
}

export function Tickets({ onChanged }: { onChanged: () => void }) {
  const [filter, setFilter] = useState('active');
  const [rows, setRows] = useState<AdminTicket[] | null>(null);
  const [openId, setOpenId] = useState<number | null>(null);
  const [notes, setNotes] = useState<Record<number, string>>({});
  const [error, setError] = useState('');

  const load = useCallback(() => {
    setRows(null);
    admin
      .tickets(filter)
      .then((r) => setRows(r.tickets))
      .catch((e) => setError(errText(e)));
  }, [filter]);
  useEffect(load, [load]);

  const update = async (t: AdminTicket, patch: { status?: TicketStatus; admin_notes?: string | null }) => {
    try {
      const r = await admin.updateTicket(t.id, patch);
      setRows((l) => l?.map((x) => (x.id === t.id ? { ...x, ...r.ticket } : x)) ?? null);
      onChanged();
    } catch (e) {
      setError(errText(e));
    }
  };

  return (
    <section className="card">
      <h2>Tickets de suporte</h2>
      <p className="muted small">
        Mensagens enviadas em <a href="/support" onClick={linkHandler('/support')}>/support</a> e no bot do Telegram. Tickets com Telegram
        respondem por aqui (ou respondendo ao aviso no seu Telegram, se a sua conta de admin estiver ligada ao bot); os outros, por e-mail.
      </p>
      {error && <p className="alert">{error}</p>}
      <div className="chips-row" role="group" aria-label="Filtro de tickets">
        {TICKET_FILTERS.map((f) => (
          <button key={f.id} type="button" className={filter === f.id ? 'chip active' : 'chip'} onClick={() => setFilter(f.id)}>
            {f.label}
          </button>
        ))}
      </div>
      {!rows ? (
        <p className="muted">Carregando...</p>
      ) : !rows.length ? (
        <p className="muted">Nenhum ticket aqui. 🎉</p>
      ) : (
        <ul className="adm-list">
          {rows.map((t) => {
            const open = openId === t.id;
            const draft = notes[t.id] ?? t.admin_notes ?? '';
            return (
              <li key={t.id} className={`adm-item ticket-${t.status}`}>
                <button type="button" className="adm-item-head" aria-expanded={open} onClick={() => setOpenId(open ? null : t.id)}>
                  <span className={`tag ticket-tag-${t.status}`}>{TICKET_LABEL[t.status] ?? t.status}</span>
                  <span className="adm-item-main">
                    <strong>{t.subject}</strong>
                    <span className="muted small">
                      #{t.id} · {CATEGORY_LABEL[t.category] ?? t.category} · {t.name} · {ago(t.created_at)}
                    </span>
                  </span>
                </button>
                {open && (
                  <div className="adm-item-body">
                    <p className="small muted">
                      De: <strong>{t.name}</strong> {t.email ? <>&lt;{t.email}&gt;</> : t.category === 'telegram' ? '(Telegram)' : ''} {t.account ? `· conta: ${t.account}` : '· sem conta'} · {fmtDateTime(t.created_at)}
                    </p>
                    <div className="ticket-message">{t.message}</div>
                    {(t.replies > 0 || t.telegram) && <TicketThread t={t} onSent={() => { load(); onChanged(); }} />}
                    {t.client_info && (
                      <details className="small">
                        <summary>Dados técnicos enviados</summary>
                        <pre className="adm-pre">{JSON.stringify(t.client_info, null, 2)}</pre>
                      </details>
                    )}
                    <label className="field">
                      <span>Notas internas (só admins veem)</span>
                      <textarea rows={3} value={draft} onChange={(e) => setNotes((n) => ({ ...n, [t.id]: e.target.value }))} maxLength={5000} />
                    </label>
                    <div className="button-row">
                      {t.email && (
                        <a
                          className={t.telegram ? 'button secondary' : 'button'}
                          href={`mailto:${encodeURIComponent(t.email)}?subject=${encodeURIComponent(`Re: ${t.subject} [BagParser #${t.id}]`)}`}
                        >
                          ✉️ Responder por e-mail
                        </a>
                      )}
                      {draft !== (t.admin_notes ?? '') && (
                        <button type="button" className="button secondary" onClick={() => void update(t, { admin_notes: draft })}>
                          💾 Salvar notas
                        </button>
                      )}
                      {(['open', 'answered', 'closed'] as TicketStatus[])
                        .filter((s) => s !== t.status)
                        .map((s) => (
                          <button key={s} type="button" className="button secondary small-btn" onClick={() => void update(t, { status: s })}>
                            Marcar {TICKET_LABEL[s]}
                          </button>
                        ))}
                    </div>
                  </div>
                )}
              </li>
            );
          })}
        </ul>
      )}
    </section>
  );
}

// ---------------------------------------------------------------------------
// Error log
// ---------------------------------------------------------------------------

export function Errors({ onChanged }: { onChanged: () => void }) {
  const [status, setStatus] = useState<'open' | 'resolved' | 'all'>('open');
  const [rows, setRows] = useState<AdminError[] | null>(null);
  const [openId, setOpenId] = useState<number | null>(null);
  const [error, setError] = useState('');

  const load = useCallback(() => {
    setRows(null);
    admin
      .errors(status)
      .then((r) => setRows(r.errors))
      .catch((e) => setError(errText(e)));
  }, [status]);
  useEffect(load, [load]);

  const act = async (e: AdminError, what: 'resolve' | 'reopen' | 'delete') => {
    try {
      if (what === 'delete') await admin.deleteError(e.id);
      else await admin.resolveError(e.id, what === 'resolve');
      // Leaves the current filter (open ↔ resolved); in "all" it stays with the new state
      setRows((l) =>
        (l ?? []).flatMap((x) => {
          if (x.id !== e.id) return [x];
          if (what === 'delete' || status !== 'all') return [];
          return [{ ...x, resolved_at: what === 'resolve' ? new Date().toISOString() : null }];
        }),
      );
      onChanged();
    } catch (err) {
      setError(errText(err));
    }
  };

  return (
    <section className="card">
      <h2>Erros do site</h2>
      <p className="muted small">
        Erros do navegador dos visitantes (telas que quebraram, promessas rejeitadas) e falhas 5xx da API, agrupados: o mesmo erro aparece uma vez
        com o número de vezes. Um erro resolvido que volta a acontecer é reaberto sozinho. Não guardamos IP; o endereço vai sem parâmetros.
      </p>
      {error && <p className="alert">{error}</p>}
      <div className="chips-row" role="group" aria-label="Filtro de erros">
        {(
          [
            ['open', 'Abertos'],
            ['resolved', 'Resolvidos'],
            ['all', 'Todos'],
          ] as const
        ).map(([id, label]) => (
          <button key={id} type="button" className={status === id ? 'chip active' : 'chip'} onClick={() => setStatus(id)}>
            {label}
          </button>
        ))}
        <button type="button" className="chip" onClick={load}>
          ↻ Atualizar
        </button>
      </div>
      {!rows ? (
        <p className="muted">Carregando...</p>
      ) : !rows.length ? (
        <p className="muted">Nenhum erro aqui. 🎉</p>
      ) : (
        <ul className="adm-list">
          {rows.map((e) => {
            const open = openId === e.id;
            return (
              <li key={e.id} className={`adm-item${e.resolved_at ? ' resolved' : ''}`}>
                <button type="button" className="adm-item-head" aria-expanded={open} onClick={() => setOpenId(open ? null : e.id)}>
                  <span className={`tag ${e.kind === 'server' ? 'banned' : 'pro'}`}>{e.kind === 'server' ? 'API' : 'navegador'}</span>
                  <span className="adm-item-main">
                    <strong className="adm-err-msg">{e.message}</strong>
                    <span className="muted small">
                      {e.count}× · último {ago(e.last_seen)} · {e.path ?? '—'} {e.version ? `· v${e.version}` : ''}
                      {e.resolved_at ? ' · resolvido' : ''}
                    </span>
                  </span>
                </button>
                {open && (
                  <div className="adm-item-body">
                    <p className="small muted">
                      Primeira vez {fmtDateTime(e.first_seen)} · última {fmtDateTime(e.last_seen)}
                      {e.last_user ? ` · último usuário: ${e.last_user}` : ''}
                    </p>
                    {e.user_agent && <p className="small muted">{e.user_agent}</p>}
                    {e.stack && <pre className="adm-pre">{e.stack}</pre>}
                    <div className="button-row">
                      {e.resolved_at ? (
                        <button type="button" className="button secondary small-btn" onClick={() => void act(e, 'reopen')}>
                          Reabrir
                        </button>
                      ) : (
                        <button type="button" className="button small-btn" onClick={() => void act(e, 'resolve')}>
                          ✓ Marcar resolvido
                        </button>
                      )}
                      <CopyText text={`${e.message}\n${e.path ?? ''} v${e.version ?? ''}\n${e.stack ?? ''}`} label="📋 Copiar" />
                      <button type="button" className="button secondary small-btn danger" onClick={() => void act(e, 'delete')}>
                        Apagar
                      </button>
                    </div>
                  </div>
                )}
              </li>
            );
          })}
        </ul>
      )}
    </section>
  );
}

export const goAdmin = (tab: string) => navigate(tab ? `/admin/${tab}` : '/admin');

// ---------------------------------------------------------------------------
// D-63: Pro for testers (period), in the Payments tab
// ---------------------------------------------------------------------------

const TESTER_DAYS = [7, 15, 30, 60, 90];

export function Testers() {
  const [q, setQ] = useState('');
  const [found, setFound] = useState<AdminUser[]>([]);
  const [picked, setPicked] = useState<AdminUser | null>(null);
  const [days, setDays] = useState(30);
  const [note, setNote] = useState('tester');
  const [testers, setTesters] = useState<AdminUser[] | null>(null);
  const [msg, setMsg] = useState('');

  const loadTesters = useCallback(() => {
    admin
      .users('', 'testers', 1)
      .then((r) => setTesters(r.users))
      .catch((e) => setMsg(errText(e)));
  }, []);
  useEffect(loadTesters, [loadTesters]);
  useEffect(() => {
    if (q.trim().length < 2) {
      setFound([]);
      return;
    }
    const id = setTimeout(() => {
      admin
        .users(q.trim(), 'all', 1)
        .then((r) => setFound(r.users.slice(0, 8)))
        .catch(() => setFound([]));
    }, 300);
    return () => clearTimeout(id);
  }, [q]);

  const grant = async (u: AdminUser, d: number, label = note) => {
    setMsg('');
    try {
      const r = await admin.grant(u.id, d, label.trim() || 'tester');
      setMsg(`✓ ${u.display_name}: Pro até ${fmtDate(r.premium_until)} (+${d} dias, somados ao tempo que já tinha).`);
      setPicked(null);
      setQ('');
      loadTesters();
    } catch (e) {
      setMsg(errText(e));
    }
  };
  const end = async (u: AdminUser) => {
    if (!window.confirm(`Encerrar agora o Pro de ${u.display_name}? Assinaturas pagas no Stripe não são afetadas.`)) return;
    try {
      await admin.update(u.id, { premium_until: new Date().toISOString() });
      setMsg(`Pro de ${u.display_name} encerrado.`);
      loadTesters();
    } catch (e) {
      setMsg(errText(e));
    }
  };

  return (
    <section className="card">
      <h2>🧪 Dar Pro por período (testers)</h2>
      <p className="muted small">
        Busque a conta, escolha quantos dias e confirme. Os dias somam ao Pro que a pessoa já tem; ao acabar, ela volta ao plano grátis
        sozinha. Não mexe em cobrança nem no Stripe.
      </p>
      <div className="tester-form">
        <input type="search" value={q} onChange={(e) => setQ(e.target.value)} placeholder="E-mail, nome ou personagem" aria-label="Buscar conta" />
        {found.length > 0 && !picked && (
          <ul className="tester-found">
            {found.map((u) => (
              <li key={u.id}>
                <button type="button" onClick={() => setPicked(u)}>
                  <strong>{u.display_name}</strong> <span className="muted small">{u.email}</span>
                  {u.premium && <span className="tag premium">Pro até {fmtDate(u.premium_until)}</span>}
                </button>
              </li>
            ))}
          </ul>
        )}
        {picked && (
          <div className="tester-picked">
            <p>
              Conta: <strong>{picked.display_name}</strong> <span className="muted small">{picked.email}</span>{' '}
              <button type="button" className="icon-btn" aria-label="Trocar conta" onClick={() => setPicked(null)}>
                ✕
              </button>
            </p>
            <div className="chips-row" role="group" aria-label="Dias de Pro">
              {TESTER_DAYS.map((d) => (
                <button key={d} type="button" className={days === d ? 'chip active' : 'chip'} onClick={() => setDays(d)}>
                  {d} dias
                </button>
              ))}
              <label className="small">
                ou{' '}
                <input type="number" min={1} max={366} value={days} onChange={(e) => setDays(Math.max(1, Math.min(366, Number(e.target.value) || 1)))} className="tester-days" aria-label="Dias" />{' '}
                dias
              </label>
            </div>
            <label className="field">
              <span>Etiqueta (aparece no filtro Testers)</span>
              <input value={note} onChange={(e) => setNote(e.target.value)} maxLength={60} />
            </label>
            <button type="button" className="button" onClick={() => void grant(picked, days)}>
              ✓ Dar {days} dias de Pro
            </button>
          </div>
        )}
      </div>
      {msg && <p className="small">{msg}</p>}

      <h3>Testers e Pro dado à mão</h3>
      {!testers ? (
        <p className="muted">Carregando...</p>
      ) : !testers.length ? (
        <p className="muted small">Nenhum ainda.</p>
      ) : (
        <div className="table-scroll">
          <table className="compact-table adm-table">
            <thead>
              <tr>
                <th>Conta</th>
                <th>Etiqueta</th>
                <th>Pro até</th>
                <th />
              </tr>
            </thead>
            <tbody>
              {testers.map((u) => (
                <tr key={u.id}>
                  <td>
                    <strong>{u.display_name}</strong>
                    <div className="muted small">{u.email}</div>
                  </td>
                  <td>{u.premium_note ?? '—'}</td>
                  <td>
                    {u.premium ? fmtDate(u.premium_until) : <span className="muted">expirou {fmtDate(u.premium_until)}</span>}
                  </td>
                  <td className="row-actions">
                    <button type="button" className="button secondary small-btn" onClick={() => void grant(u, 30, u.premium_note ?? 'tester')}>
                      +30 dias
                    </button>
                    {u.premium && (
                      <button type="button" className="button secondary small-btn danger" onClick={() => void end(u)}>
                        Encerrar
                      </button>
                    )}
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

// ---------------------------------------------------------------------------
// D-63: financial report
// ---------------------------------------------------------------------------

const money = (cents: number, currency: string) =>
  new Intl.NumberFormat('pt-BR', { style: 'currency', currency: (currency || 'brl').toUpperCase() }).format(cents / 100);

export function Finance() {
  const [data, setData] = useState<AdminFinance | null>(null);
  const [error, setError] = useState('');
  const [syncMsg, setSyncMsg] = useState('');
  const [busy, setBusy] = useState(false);
  const load = useCallback(() => {
    admin
      .finance()
      .then(setData)
      .catch((e) => setError(errText(e)));
  }, []);
  useEffect(load, [load]);

  const sync = async () => {
    setBusy(true);
    setSyncMsg('');
    try {
      const r = await admin.financeSync();
      setSyncMsg(`Stripe: ${r.customers} clientes conferidos, ${r.invoices} pagamentos lidos${r.failed ? `, ${r.failed} com erro` : ''}.`);
      load();
    } catch (e) {
      setSyncMsg(errText(e));
    } finally {
      setBusy(false);
    }
  };

  if (error) return <p className="alert">{error}</p>;
  if (!data) return <p className="muted">Carregando...</p>;

  // Monthly recurring revenue by currency (yearly plans count 1/12 per month); scheduled cancellations apart
  const mrr = new Map<string, { mrr: number; atRisk: number; subs: number; monthly: number; yearly: number }>();
  for (const s of data.subscriptions) {
    const cur = s.currency ?? 'brl';
    const m = mrr.get(cur) ?? { mrr: 0, atRisk: 0, subs: 0, monthly: 0, yearly: 0 };
    const monthly = s.interval === 'year' ? s.amount / 12 : s.amount;
    if (s.cancelling) m.atRisk += monthly;
    else m.mrr += monthly;
    m.subs += s.n;
    if (s.interval === 'year') m.yearly += s.n;
    else m.monthly += s.n;
    mrr.set(cur, m);
  }
  const churnBy = new Map(data.churn.map((c) => [c.month, c]));
  const months = [...new Set([...data.months.map((m) => m.month), ...data.churn.map((c) => c.month)])].sort().reverse();

  const exportCsv = () =>
    downloadText(
      `bagparser-financeiro-${new Date().toISOString().slice(0, 10)}.csv`,
      toCsv(
        [
          ['data', 'tipo', 'valor', 'moeda', 'plano', 'valido_ate', 'email', 'stripe_id'],
          ...data.recent.map((p) => [p.paid_at.slice(0, 16).replace('T', ' '), p.kind === 'invoice' ? 'pagamento' : 'reembolso', (p.amount / 100).toFixed(2).replace('.', ','), p.currency.toUpperCase(), p.interval ?? '', p.period_end?.slice(0, 10) ?? '', p.email ?? '', p.stripe_id]),
        ],
        ';',
      ),
    );

  return (
    <div className="adm-overview">
      <section className="card">
        <div className="section-head">
          <h2>💰 Financeiro</h2>
          <div className="button-row">
            <button type="button" className="button secondary small-btn" onClick={() => void sync()} disabled={busy || !data.enabled}>
              {busy ? 'Sincronizando...' : '↻ Sincronizar com o Stripe'}
            </button>
            <button type="button" className="button secondary small-btn" onClick={exportCsv} disabled={!data.recent.length}>
              ⬇️ CSV
            </button>
          </div>
        </div>
        <p className="muted small">
          Valores brutos, antes das taxas do Stripe (o líquido que cai na conta está no painel do Stripe). Cada pagamento é registrado quando o
          Stripe avisa; “Sincronizar” importa pagamentos antigos dos clientes do BagParser (só eles; a conta Stripe é compartilhada).
        </p>
        {!data.enabled && <p className="alert">Stripe não configurado neste servidor.</p>}
        {syncMsg && <p className="small">{syncMsg}</p>}
        <div className="adm-cards">
          {[...mrr].map(([cur, m]) => (
            <Card
              key={`mrr-${cur}`}
              title={`Receita mensal recorrente (${cur.toUpperCase()})`}
              value={money(Math.round(m.mrr), cur)}
              sub={`${m.subs} assinante(s): ${m.monthly} mensal · ${m.yearly} anual${m.atRisk ? ` · ${money(Math.round(m.atRisk), cur)} cancelando` : ''}`}
            />
          ))}
          {data.totals.map((t) => (
            <Card
              key={`tot-${t.currency}`}
              title={`Total recebido (${t.currency.toUpperCase()})`}
              value={money(t.gross - t.refunds, t.currency)}
              sub={`bruto ${money(t.gross, t.currency)} · reembolsos ${money(t.refunds, t.currency)} · ${t.payers} pagante(s)`}
            />
          ))}
          {!mrr.size && !data.totals.length && <Card title="Receita" value="—" sub="nenhum pagamento registrado ainda" />}
        </div>
      </section>

      <section className="card">
        <h3>Mês a mês</h3>
        {!months.length ? (
          <p className="muted small">Sem movimento ainda.</p>
        ) : (
          <div className="table-scroll">
            <table className="compact-table adm-table">
              <thead>
                <tr>
                  <th>Mês</th>
                  <th className="num">Recebido</th>
                  <th className="num">Reembolsos</th>
                  <th className="num">Pagamentos</th>
                  <th className="num">Novas</th>
                  <th className="num">Cancel. agendados</th>
                  <th className="num">Reativadas</th>
                  <th className="num">Encerradas</th>
                  <th className="num">Falhas</th>
                </tr>
              </thead>
              <tbody>
                {months.map((mo) => {
                  const rows = data.months.filter((m) => m.month === mo);
                  const c = churnBy.get(mo);
                  return (
                    <tr key={mo}>
                      <td>{mo}</td>
                      <td className="num">{rows.length ? rows.map((r) => money(r.gross, r.currency)).join(' + ') : '—'}</td>
                      <td className="num">{rows.some((r) => r.refunds) ? rows.filter((r) => r.refunds).map((r) => money(r.refunds, r.currency)).join(' + ') : '—'}</td>
                      <td className="num">{rows.reduce((s, r) => s + r.payments, 0) || '—'}</td>
                      <td className="num">{c?.subscribed || '—'}</td>
                      <td className="num">{c?.cancel_scheduled || '—'}</td>
                      <td className="num">{c?.resumed || '—'}</td>
                      <td className="num">{c?.ended || '—'}</td>
                      <td className="num">{c?.payment_failed || '—'}</td>
                    </tr>
                  );
                })}
              </tbody>
            </table>
          </div>
        )}
      </section>

      <section className="card">
        <h3>Últimos pagamentos e reembolsos</h3>
        {!data.recent.length ? (
          <p className="muted small">Nenhum ainda.</p>
        ) : (
          <div className="table-scroll">
            <table className="compact-table adm-table">
              <thead>
                <tr>
                  <th>Data</th>
                  <th>Conta</th>
                  <th className="num">Valor</th>
                  <th>Plano</th>
                  <th>Válido até</th>
                </tr>
              </thead>
              <tbody>
                {data.recent.map((p) => (
                  <tr key={p.stripe_id}>
                    <td>{fmtDateTime(p.paid_at)}</td>
                    <td className="small">{p.email ?? '—'}</td>
                    <td className={`num ${p.kind === 'refund' ? 'neg' : 'pos'}`}>
                      {money(p.amount, p.currency)}
                      {p.kind === 'refund' && <div className="muted small">reembolso{p.description ? ` (${p.description})` : ''}</div>}
                    </td>
                    <td>
                      {p.interval === 'year' ? 'anual' : p.interval === 'month' ? 'mensal' : '—'}
                      <div className="muted small">{p.provider === 'mercadopago' ? 'Pix' : 'cartão'}</div>
                    </td>
                    <td>{fmtDate(p.period_end)}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </section>
    </div>
  );
}

/** D-65: e-mail status + "send test e-mail" */
function MailCard({ overview }: { overview: AdminOverview }) {
  const [msg, setMsg] = useState('');
  const [busy, setBusy] = useState(false);
  const m = overview.mail;
  const on = m?.mode === 'smtp';
  return (
    <div className={`adm-card ${on ? 'ok' : 'warn'}`}>
      <span className="adm-card-title">E-mail</span>
      <strong className="adm-card-value">{on ? 'ligado' : m?.mode === 'log' ? 'só log (teste)' : 'desligado'}</strong>
      <span className="muted small">{m?.from ? `de ${m.from} · respostas para ${m.support}` : 'configure SMTP_* no .env da VPS'}</span>
      {m && m.mode !== 'off' && (
        <button
          type="button"
          className="button secondary small-btn"
          disabled={busy}
          onClick={async () => {
            setBusy(true);
            setMsg('');
            try {
              const r = await admin.mailTest();
              setMsg(`Enviado para ${r.to}. Confira a caixa de entrada (e o spam).`);
            } catch (e) {
              setMsg(e instanceof ApiError ? (e.code === 'forbidden' || e.code === 'unauthorized' ? 'Falhou: esta sessão não é de admin (saia e entre de novo com o e-mail que está em ADMIN_EMAILS).' : `Falhou: ${String(e.data.detail ?? e.code)}`) : 'Falha de conexão');
            } finally {
              setBusy(false);
            }
          }}
        >
          ✉️ Enviar e-mail de teste
        </button>
      )}
      <button
        type="button"
        className="button secondary small-btn"
        disabled={busy}
        onClick={async () => {
          setBusy(true);
          setMsg('');
          try {
            const r = await admin.mailCheck();
            setMsg(r.steps.map((s) => `${s.ok ? '✅' : '❌'} ${s.step}: ${s.detail}`).join('\n'));
          } catch (e) {
            setMsg(e instanceof ApiError ? `Falhou: ${e.code}` : 'Falha de conexão');
          } finally {
            setBusy(false);
          }
        }}
      >
        🩺 Diagnosticar e-mail
      </button>
      {msg && <span className="small" style={{ whiteSpace: 'pre-line' }}>{msg}</span>}
    </div>
  );
}

/**
 * D-66: server settings that are wrong, with the full explanation and the command to fix them (copy button).
 * Shown open, not as a link: there is nothing to click through to.
 */
function ConfigWarning({ warning }: { warning: string }) {
  const isUrl = warning.startsWith('PUBLIC_URL');
  const isMail = warning.startsWith('E-mail desligado');
  const isBackup = warning.startsWith('Backup:');
  const isPix = warning.startsWith('Pix:');
  const fixUrl = "cd /opt/apps/bagparser && sed -i 's|^PUBLIC_URL=.*|PUBLIC_URL=https://bagparser.com|' .env && grep '^PUBLIC_URL' .env && bash deploy.sh";
  return (
    <section className="card adm-warning" role="alert">
      <h2>⚠️ Configuração do servidor</h2>
      {isUrl ? (
        <>
          <p>
            O <code>.env</code> da VPS está com <code>{warning}</code>. Esse endereço é o do seu computador (desenvolvimento): com ele, os
            links que o site monta (volta do pagamento no Stripe, portal da assinatura, e-mails de senha e de confirmação) podem levar o
            visitante para <code>localhost</code>, que não abre nada. O login com Google/Discord e o pagamento já se protegem disso, mas o
            certo é corrigir o valor.
          </p>
          <p>Na VPS (troca só essa linha, sem mexer no resto do arquivo), e depois publica de novo:</p>
          <pre className="adm-pre">{fixUrl}</pre>
          <CopyText text={fixUrl} label="📋 Copiar comando" />
        </>
      ) : isPix ? (
        <>
          <p>{warning}.</p>
          <p>
            No painel do Mercado Pago (Suas integrações → BagParser → Credenciais de produção) há duas chaves que começam com{' '}
            <code>APP_USR-</code>: a <strong>Public Key</strong> (curta, formato <code>APP_USR-xxxxxxxx-xxxx-…</code>, o site não usa) e o{' '}
            <strong>Access Token</strong> (longa). No <code>.env</code> da VPS, troque a linha <code>MP_ACCESS_TOKEN</code> pelo Access Token
            (apague a linha antiga com <code>sed -i '/^MP_ACCESS_TOKEN=/d' .env</code> e rode de novo o comando que pede o token) e depois{' '}
            <code>bash deploy.sh</code>.
          </p>
        </>
      ) : isBackup ? (
        <>
          <p>{warning}.</p>
          <p>
            Os backups ficam em <code>/opt/apps/bagparser/backups</code> (um por dia, 14 dias). Para ver o que aconteceu na VPS:{' '}
            <code>docker logs --tail 30 bagparser_backup</code>. Para conferir que um arquivo abre sem mexer no site:{' '}
            <code>bash scripts/restore-db.sh backups/ARQUIVO.dump --check</code>.
          </p>
        </>
      ) : isMail ? (
        <>
          <p>{warning}.</p>
          <p>
            Acrescente as linhas <code>SMTP_HOST</code>, <code>SMTP_PORT</code>, <code>SMTP_USER</code>, <code>SMTP_PASS</code>,{' '}
            <code>MAIL_FROM</code> e <code>SUPPORT_EMAIL</code> ao <code>.env</code> da VPS (veja <code>docs/STATUS.md</code>) e rode{' '}
            <code>bash deploy.sh</code>. Depois use “Enviar e-mail de teste” aqui na visão geral.
          </p>
        </>
      ) : (
        <p>{warning}</p>
      )}
    </section>
  );
}

// ---------------------------------------------------------------------------
// D-73: Mercado Pago notifications — complaints, chargebacks and everything else MP sends
// ---------------------------------------------------------------------------

const MP_KINDS: { id: string; label: string }[] = [
  { id: 'claim', label: 'Reclamações' },
  { id: 'chargeback', label: 'Contestações' },
  { id: 'order', label: 'Pix (orders)' },
  { id: 'payment', label: 'Pagamentos' },
  { id: 'other', label: 'Outros avisos' },
  { id: 'all', label: 'Todos' },
];

export function MpEvents() {
  const [kind, setKind] = useState('claim');
  const [data, setData] = useState<{ events: AdminMpEvent[]; counts: Record<string, number> } | null>(null);
  const [openId, setOpenId] = useState<number | null>(null);
  const [error, setError] = useState('');
  useEffect(() => {
    setData(null);
    admin
      .mpEvents(kind)
      .then(setData)
      .catch((e) => setError(errText(e)));
  }, [kind]);
  const total = (k: string) => (k === 'all' ? Object.values(data?.counts ?? {}).reduce((a, b) => a + b, 0) : data?.counts[k] ?? 0);
  return (
    <section className="card">
      <h2>Mercado Pago: reclamações, contestações e avisos</h2>
      <p className="muted small">
        Tudo o que o Mercado Pago avisa pelo webhook fica guardado aqui (com assinatura conferida). Reclamações e contestações são lidas no
        Mercado Pago, ligadas à conta do BagParser quando o pagamento é nosso e mandam e-mail para os admins. Responda e envie documentos pelo
        painel do Mercado Pago, dentro do prazo.
      </p>
      {error && <p className="alert">{error}</p>}
      <div className="chips-row" role="group" aria-label="Tipo de aviso">
        {MP_KINDS.map((k) => (
          <button key={k.id} type="button" className={kind === k.id ? 'chip active' : 'chip'} onClick={() => setKind(k.id)}>
            {k.label} ({total(k.id)})
          </button>
        ))}
      </div>
      {!data ? (
        <p className="muted">Carregando...</p>
      ) : !data.events.length ? (
        <p className="muted small">Nada aqui. 🎉</p>
      ) : (
        <ul className="adm-list">
          {data.events.map((e) => {
            const open = openId === e.id;
            const urgent = (e.kind === 'claim' || e.kind === 'chargeback') && !/closed|coberta|won/i.test(e.status ?? '');
            return (
              <li key={e.id} className={`adm-item${urgent ? ' ticket-pending' : ''}`}>
                <button type="button" className="adm-item-head" aria-expanded={open} onClick={() => setOpenId(open ? null : e.id)}>
                  <span className={`tag ${e.kind === 'chargeback' ? 'banned' : e.kind === 'claim' ? 'premium' : ''}`}>{MP_KINDS.find((k) => k.id === e.kind)?.label ?? e.kind}</span>
                  <span className="adm-item-main">
                    <strong>{e.summary ?? `${e.type}${e.action ? ` · ${e.action}` : ''}`}</strong>
                    <span className="muted small">
                      {ago(e.received_at)} · id {e.data_id ?? '—'}
                      {e.email ? ` · ${e.email}` : e.kind === 'claim' || e.kind === 'chargeback' ? ' · não é de um Pix do BagParser (ou não deu para ligar)' : ''}
                      {e.deadline ? ` · prazo ${fmtDate(e.deadline)}` : ''}
                      {e.error ? ' · ⚠️ erro ao ler no MP' : ''}
                    </span>
                  </span>
                </button>
                {open && (
                  <div className="adm-item-body">
                    <p className="small muted">
                      Recebido {fmtDateTime(e.received_at)} · tipo <code>{e.type}</code>
                      {e.action ? <> · ação <code>{e.action}</code></> : null} · status {e.status ?? '—'}
                      {e.amount != null ? ` · ${(e.amount / 100).toFixed(2).replace('.', ',')}` : ''}
                    </p>
                    {e.error && <p className="alert small">{e.error}</p>}
                    <pre className="adm-pre">{JSON.stringify(e.details, null, 2)}</pre>
                  </div>
                )}
              </li>
            );
          })}
        </ul>
      )}
    </section>
  );
}
