/**
 * New hunt session (D-59) — three numbered steps on one page:
 *   1. About the hunt (optional): name / template, description, public, extra expenses, friends to share with
 *   2. Game data (required): one field per analyser (Party Hunt, Hunt Analyser, Input Analyser, Server Log)
 *   3. Character (recommended): link or create the character, place, equipment set + stats, WoD, charms, preys, stances
 * A sticky bar keeps the result (XP/h, final profit) and the save button in reach on phones.
 */
import { useEffect, useMemo, useRef, useState } from 'react';
import { useI18n } from '../../i18n';
import { useAuth } from '../../auth';
import { linkHandler, navigate } from '../../router';
import {
  ApiError,
  builds as buildsApi,
  characters,
  friends as friendsApi,
  hunts,
  huntTemplates,
  huntingPlaces,
  type CharacterListItem,
  type Friend,
  type HuntingPlaceSummary,
  type HuntRow,
  type HuntTemplate,
  type SavedBuild,
} from '../../api';
import { analysePaste, combineAnalyses, type PasteAnalysis } from '../../core/huntLog';
import {
  buildIsEmpty,
  computeSetStats,
  emptyBuild,
  expenseValue,
  expensesTotal,
  type Expense,
  type HuntBuild,
  type SetData,
  type Voc,
  type WodData,
} from '../../core/build';
import { HuntView } from './HuntView';
import { averages, fmtDuration, gp, loadLocalHunts, saveLocalHunts, VocationSelect } from './common';
import { SetEditor, useEquipment } from './build/SetEditor';
import { WodEditor } from './build/WodEditor';
import { CharmsEditor, PreysEditor, StancesEditor } from './build/BuildPickers';
import { damageMatrix, loadCreatures } from '../../core/creatures';
import { StatsPanel, titleCase } from './build/StatsPanel';

type FieldKey = 'party' | 'hunt' | 'input' | 'serverlog';
const FIELDS: { key: FieldKey; label: string; hint: string; pro?: boolean }[] = [
  { key: 'party', label: 'Party Hunt Analyser', hint: 'hs_field_party_hint' },
  { key: 'hunt', label: 'Hunt Analyser', hint: 'hs_field_hunt_hint' },
  { key: 'input', label: 'Input Analyser', hint: 'hs_field_input_hint' },
  { key: 'serverlog', label: 'Server Log', hint: 'hs_field_serverlog_hint', pro: true },
];
const TC_PRICE_KEY = 'bp_tc_price';

const readTcPrice = (): number => {
  try {
    return Number(localStorage.getItem(TC_PRICE_KEY)) || 0;
  } catch {
    return 0;
  }
};

/** Where a pasted / dropped text belongs */
function fieldFor(a: PasteAnalysis): FieldKey | null {
  if (a.formats.includes('serverlog')) return 'serverlog';
  if (a.formats.includes('party')) return 'party';
  if (a.formats.includes('hunt') || a.formats.includes('json')) return 'hunt';
  if (a.formats.includes('input')) return 'input';
  return null;
}

const vocOf = (s: string | null | undefined): Voc | null =>
  (['knight', 'paladin', 'sorcerer', 'druid', 'monk'] as Voc[]).find((v) => (s ?? '').toLowerCase().includes(v)) ?? null;

function Step({ n, title, badge, intro, children }: { n: number; title: string; badge: string; intro?: string; children: React.ReactNode }) {
  return (
    <section className="card hs-step">
      <header className="hs-step-head">
        <span className="hs-step-n" aria-hidden="true">
          {n}
        </span>
        <h2>{title}</h2>
        <span className="tag">{badge}</span>
      </header>
      {intro && <p className="muted small">{intro}</p>}
      {children}
    </section>
  );
}

export function AnalyseTab() {
  const { t, n, locale } = useI18n();
  const { user } = useAuth();
  const premium = !!user?.premium;
  const eq = useEquipment();

  // 1. About
  const [title, setTitle] = useState('');
  const [description, setDescription] = useState('');
  const [visibility, setVisibility] = useState<'private' | 'public'>('private');
  const [anonymous, setAnonymous] = useState(false);
  const [expenses, setExpenses] = useState<Expense[]>([]);
  const [sharedWith, setSharedWith] = useState<number[]>([]);
  const [templates, setTemplates] = useState<HuntTemplate[]>([]);
  const [friendList, setFriendList] = useState<Friend[]>([]);
  const [templateMsg, setTemplateMsg] = useState('');

  // 2. Game data
  const [fields, setFields] = useState<Record<FieldKey, string>>({ party: '', hunt: '', input: '', serverlog: '' });
  const [files, setFiles] = useState<string[]>([]);
  const [dragging, setDragging] = useState(false);
  const fileRef = useRef<HTMLInputElement>(null);

  // 3. Character and build
  const [chars, setChars] = useState<CharacterListItem[]>([]);
  const [charId, setCharId] = useState('');
  const [newChar, setNewChar] = useState('');
  const [addingChar, setAddingChar] = useState(false);
  const [charMsg, setCharMsg] = useState('');
  const [charName, setCharName] = useState('');
  const [level, setLevel] = useState('');
  const [vocation, setVocation] = useState('');
  const [places, setPlaces] = useState<HuntingPlaceSummary[]>([]);
  const [placeQuery, setPlaceQuery] = useState('');
  const [notes, setNotes] = useState('');
  const [build, setBuild] = useState<HuntBuild>(emptyBuild());
  const [saved, setSaved] = useState<SavedBuild[]>([]);
  const [editor, setEditor] = useState<null | 'set-new' | 'set-edit' | 'wod-new' | 'wod-edit'>(null);
  const [saveTemplate, setSaveTemplate] = useState(false);

  const [history, setHistory] = useState<HuntRow[]>([]);
  const [busy, setBusy] = useState(false);
  const [msg, setMsg] = useState<{ tone: 'ok' | 'err'; text: string } | null>(null);

  const perField = useMemo(
    () => Object.fromEntries(FIELDS.map((f) => [f.key, analysePaste(fields[f.key])])) as Record<FieldKey, PasteAnalysis>,
    [fields],
  );
  const analysis = useMemo(() => combineAnalyses(FIELDS.map((f) => perField[f.key])), [perField]);
  const hunt = analysis.hunt;
  const extra = expensesTotal(expenses);
  const finalProfit = (hunt?.balance ?? 0) - extra;
  const creatures = useMemo(() => (hunt?.monsters ?? []).map((m) => m.name.toLowerCase()), [hunt]);
  const partyNames = hunt?.party?.members.map((m) => m.name) ?? [];

  // ---- loading ----
  const loadChars = (select?: number) =>
    characters
      .list()
      .then((r) => {
        setChars(r.characters);
        const pick = select ?? r.characters[0]?.id;
        if (pick && !charId) setCharId(String(pick));
        if (select) setCharId(String(select));
      })
      .catch(() => null);

  useEffect(() => {
    huntingPlaces.list().then(setPlaces).catch(() => null);
    if (!user) return;
    void loadChars();
    huntTemplates
      .list()
      .then((r) => setTemplates(r.templates))
      .catch(() => null);
    friendsApi
      .list()
      .then((r) => setFriendList(r.friends))
      .catch(() => null);
  }, [user]); // eslint-disable-line react-hooks/exhaustive-deps

  const char = chars.find((c) => String(c.id) === charId) ?? null;
  const voc = vocOf(vocation) ?? vocOf(char?.vocation);
  useEffect(() => {
    if (!char) return;
    setLevel(char.level ? String(char.level) : '');
    setVocation(vocOf(char.vocation) ?? '');
    hunts
      .mine(char.name)
      .then((r) => setHistory(r.hunts))
      .catch(() => setHistory([]));
  }, [char]);
  useEffect(() => {
    if (!user) return;
    buildsApi
      .list(char?.id ?? null)
      .then((r) => setSaved(r.builds))
      .catch(() => setSaved([]));
  }, [user, char]);

  // Copy the setup of an earlier hunt (?from=<id>) or apply a template (?template=<id>)
  useEffect(() => {
    const q = new URLSearchParams(window.location.search);
    const from = q.get('from');
    if (from) {
      hunts
        .get(from)
        .then(({ hunt: h }) => {
          setTitle(h.title ?? h.place_name ?? '');
          setDescription(h.description ?? '');
          if (h.place_name) setPlaceQuery(h.place_name);
          if (h.build) setBuild(h.build);
          if (h.expenses) setExpenses(h.expenses);
          setTemplateMsg(t('hs_copied_from', { name: h.title ?? h.place_name ?? from }));
        })
        .catch(() => null);
    }
  }, []); // eslint-disable-line react-hooks/exhaustive-deps

  const applyTemplate = (tp: HuntTemplate) => {
    const d = tp.data;
    setTitle(tp.name);
    setDescription(d.description ?? '');
    setVisibility(d.visibility);
    setAnonymous(d.anonymous);
    setExpenses(d.expenses ?? []);
    setSharedWith(d.shared_with ?? []);
    if (d.place_name) setPlaceQuery(d.place_name);
    if (d.character_id && chars.some((c) => c.id === d.character_id)) setCharId(String(d.character_id));
    setBuild(d.build ?? emptyBuild());
    setTemplateMsg(t('hs_template_applied', { name: tp.name }));
  };

  // Suggest the hunting place from the creatures killed
  useEffect(() => {
    if (!hunt || placeQuery || !places.length) return;
    const kills = new Map(hunt.monsters.map((m) => [m.name.toLowerCase(), m.count]));
    const total = hunt.monsters.reduce((s, m) => s + m.count, 0) || 1;
    let best: { name: string; score: number } | null = null;
    for (const p of places) {
      if (!p.creatures?.length) continue;
      const covered = p.creatures.reduce((s, c) => s + (kills.get(c.toLowerCase()) ?? 0), 0);
      const score = covered / total - p.creatures.length * 0.01;
      if (covered / total >= 0.6 && (!best || score > best.score)) best = { name: p.name, score };
    }
    if (best) setPlaceQuery(best.name);
  }, [hunt, places]); // eslint-disable-line react-hooks/exhaustive-deps

  // Default title: the place
  const place = places.find((p) => p.name.toLowerCase() === placeQuery.trim().toLowerCase()) ?? null;
  const avg = useMemo(() => averages(history.filter((h) => h.duration_s >= 600)), [history]);
  const setStats = useMemo(() => (eq && build.set ? computeSetStats(build.set.data, eq.index, { consumables: build.consumables }) : null), [eq, build]);

  // ---- game data input ----
  const putText = (text: string, target?: FieldKey) => {
    const key = target ?? fieldFor(analysePaste(text)) ?? 'hunt';
    setFields((f) => ({ ...f, [key]: text }));
    setMsg(null);
  };
  const readFiles = async (list: FileList | File[]) => {
    const arr = [...list].slice(0, 8);
    const texts = await Promise.all(arr.map((f) => (f.size > 5_000_000 ? Promise.resolve('') : f.text())));
    setFiles(arr.map((f) => f.name));
    const next = { ...fields };
    for (const text of texts.filter(Boolean)) {
      const key = fieldFor(analysePaste(text)) ?? 'hunt';
      next[key] = next[key] && key !== 'serverlog' ? `${next[key]}\n\n${text}` : text;
    }
    setFields(next);
    setMsg(null);
  };

  const addCharacter = async () => {
    const name = newChar.trim();
    if (!name) return;
    setCharMsg('');
    setAddingChar(true);
    try {
      const r = await characters.add(name);
      await loadChars(r.id);
      setNewChar('');
      setCharMsg(t('hs_char_added', { name }));
    } catch (e) {
      const code = e instanceof ApiError ? e.code : 'error';
      setCharMsg(t(`hs_char_err_${code}`) === `hs_char_err_${code}` ? t('hs_char_err') : t(`hs_char_err_${code}`));
    } finally {
      setAddingChar(false);
    }
  };

  // ---- save ----
  const save = async () => {
    if (!hunt) return;
    setBusy(true);
    setMsg(null);
    const characterName = char?.name ?? (charName.trim() || null);
    const cleanBuild = buildIsEmpty(build) ? null : { ...build, character: characterName };
    const cleanTitle = title.trim() || (place ? place.name : placeQuery.trim()) || null;
    // D-62: keep the damage per creature and type with the hunt
    let input: (typeof analysis.input & { by_source_type?: unknown; estimated?: boolean }) | null = analysis.input;
    if (input && input.sources.length && input.types.length) {
      try {
        const m = damageMatrix(input, await loadCreatures());
        input = { ...input, by_source_type: m.rows, estimated: m.estimated };
      } catch {
        /* catalog unavailable: save the analyser as it is */
      }
    }
    const body = {
      ...hunt,
      input,
      server_log: analysis.serverLog,
      character_id: char?.id ?? null,
      character_name: characterName,
      level: level ? Number(level) : null,
      vocation: vocation || null,
      world: char?.world ?? null,
      place_slug: place?.slug ?? null,
      place_name: place ? place.name : placeQuery.trim() || null,
      visibility,
      anonymous,
      notes: notes.trim() || null,
      setup: {},
      title: cleanTitle,
      description: description.trim() || null,
      expenses,
      build: cleanBuild,
      shared_with: sharedWith,
    };
    try {
      if (!user) {
        const local = loadLocalHunts();
        local.unshift({
          id: Math.random().toString(36).slice(2, 10),
          savedAt: new Date().toISOString(),
          character: charName.trim(),
          place: cleanTitle ?? '',
          hunt,
        });
        saveLocalHunts(local);
        setMsg({ tone: 'ok', text: t('ha_saved_local') });
        return;
      }
      if (saveTemplate && cleanTitle) {
        await huntTemplates
          .save(cleanTitle, {
            description: body.description,
            place_slug: body.place_slug,
            place_name: body.place_name,
            character_id: body.character_id,
            visibility,
            anonymous,
            expenses,
            build: cleanBuild,
            shared_with: sharedWith,
          })
          .catch(() => null);
      }
      const r = await hunts.save(body);
      navigate(`/hunt-analyser/h/${r.id}${r.duplicate ? '?dup=1' : ''}`);
    } catch (err) {
      const code = err instanceof ApiError ? err.code : 'error';
      setMsg({
        tone: 'err',
        text: code === 'hunt_limit' ? t('ha_err_limit', { n: String((err as ApiError).data.limit ?? '') }) : t(`ha_err_${code}`) === `ha_err_${code}` ? t('ha_err_generic') : t(`ha_err_${code}`),
      });
    } finally {
      setBusy(false);
    }
  };

  const sets = saved.filter((b) => b.kind === 'set');
  const wods = saved.filter((b) => b.kind === 'wod');
  const setExpense = (i: number, patch: Partial<Expense>) => setExpenses((rows) => rows.map((r, k) => (k === i ? { ...r, ...patch } : r)));

  return (
    <div className="hs-form">
      <div className="hs-head">
        <div>
          <h2>{t('hs_new_title')}</h2>
          <p className="muted small">{t('hs_new_intro')}</p>
        </div>
        {user && (
          <a className="button secondary small-btn" href="/hunt-analyser/setup" onClick={linkHandler('/hunt-analyser/setup')}>
            ⚙️ {t('hs_setup_link')}
          </a>
        )}
      </div>

      {/* 1 — About */}
      <Step n={1} title={t('hs_step_about')} badge={t('hs_optional')} intro={t('hs_step_about_intro')}>
        {user && templates.length > 0 && (
          <label className="field">
            <span>{t('hs_template')}</span>
            <select
              value=""
              onChange={(e) => {
                const tp = templates.find((x) => String(x.id) === e.target.value);
                if (tp) applyTemplate(tp);
              }}
            >
              <option value="">{t('hs_template_pick')}</option>
              {templates.map((tp) => (
                <option key={tp.id} value={tp.id}>
                  {tp.name}
                </option>
              ))}
            </select>
          </label>
        )}
        {templateMsg && <p className="ok-text small">{templateMsg}</p>}
        <div className="form-grid">
          <label className="span-2">
            <span>{t('hs_name')}</span>
            <input value={title} onChange={(e) => setTitle(e.target.value)} maxLength={120} placeholder={place?.name ?? t('hs_name_ph')} />
          </label>
          <label className="span-2">
            <span>{t('hs_description')}</span>
            <textarea rows={2} value={description} onChange={(e) => setDescription(e.target.value)} maxLength={2000} placeholder={t('hs_description_ph')} />
          </label>
        </div>
        {user && (
          <div className="check-row">
            <label className="check">
              <input type="checkbox" checked={visibility === 'public'} onChange={(e) => setVisibility(e.target.checked ? 'public' : 'private')} /> {t('ha_make_public')}
            </label>
            {visibility === 'public' && (
              <label className="check">
                <input type="checkbox" checked={anonymous} onChange={(e) => setAnonymous(e.target.checked)} /> {t('ha_anonymous')}
              </label>
            )}
          </div>
        )}
        {visibility === 'public' && <p className="muted small">{t('ha_public_note')}</p>}

        <h3 className="hs-sub">
          💸 {t('hs_expenses')} {extra > 0 && <span className="muted small">· {gp(extra, locale)}</span>}
        </h3>
        <p className="muted small">{t('hs_expenses_intro')}</p>
        {expenses.map((e, i) => (
          <div key={i} className="expense-row">
            <label>
              <span>Tibia Coins</span>
              <input inputMode="numeric" value={e.tc || ''} onChange={(ev) => setExpense(i, { tc: Number(ev.target.value.replace(/\D/g, '')) || 0 })} placeholder="0" />
            </label>
            <label>
              <span>{t('hs_tc_price')}</span>
              <input
                inputMode="numeric"
                value={e.tc_price || ''}
                onChange={(ev) => {
                  const v = Number(ev.target.value.replace(/\D/g, '')) || 0;
                  setExpense(i, { tc_price: v });
                  try {
                    if (v) localStorage.setItem(TC_PRICE_KEY, String(v));
                  } catch {
                    /* private mode */
                  }
                }}
                placeholder="38000"
              />
            </label>
            <label>
              <span>{t('hs_gold')}</span>
              <input inputMode="numeric" value={e.gold || ''} onChange={(ev) => setExpense(i, { gold: Number(ev.target.value.replace(/\D/g, '')) || 0 })} placeholder="0" />
            </label>
            <label className="grow">
              <span>{t('hs_reason')}</span>
              <input value={e.reason ?? ''} onChange={(ev) => setExpense(i, { reason: ev.target.value })} maxLength={120} placeholder={t('hs_reason_ph')} />
            </label>
            {partyNames.length > 0 && (
              <label>
                <span>{t('hs_payer')}</span>
                <select value={e.payer ?? ''} onChange={(ev) => setExpense(i, { payer: ev.target.value || null })}>
                  <option value="">{t('ha_leader')}</option>
                  {partyNames.map((p) => (
                    <option key={p} value={p}>
                      {p}
                    </option>
                  ))}
                </select>
              </label>
            )}
            <strong className="expense-total">{gp(expenseValue(e), locale)}</strong>
            <button type="button" className="icon-btn" aria-label={t('delete')} onClick={() => setExpenses((rows) => rows.filter((_, k) => k !== i))}>
              ✕
            </button>
          </div>
        ))}
        {expenses.length < 10 && (
          <button
            type="button"
            className="button secondary small-btn"
            onClick={() => setExpenses((rows) => [...rows, { tc: 0, tc_price: readTcPrice(), gold: 0, reason: null, payer: null }])}
          >
            + {t('hs_add_expense')}
          </button>
        )}

        {user && (
          <>
            <h3 className="hs-sub">🤝 {t('hs_share_friends')}</h3>
            {friendList.length ? (
              <div className="chip-row">
                {friendList.map((f) => {
                  const on = sharedWith.includes(f.id);
                  return (
                    <button
                      key={f.id}
                      type="button"
                      className={`chip toggle${on ? ' ok' : ''}`}
                      aria-pressed={on}
                      onClick={() => setSharedWith((s) => (on ? s.filter((x) => x !== f.id) : [...s, f.id]))}
                    >
                      {on ? '✓ ' : ''}
                      {f.display_name}
                    </button>
                  );
                })}
              </div>
            ) : (
              <p className="muted small">
                {t('hs_no_friends')}{' '}
                <a href="/hunt-analyser/setup" onClick={linkHandler('/hunt-analyser/setup')}>
                  {t('hs_add_friends')} →
                </a>
              </p>
            )}
          </>
        )}
      </Step>

      {/* 2 — Game data */}
      <Step n={2} title={t('hs_step_data')} badge={t('hs_required')} intro={t('hs_step_data_intro')}>
        <div
          className={`hs-drop${dragging ? ' dragging' : ''}`}
          onDragOver={(e) => {
            e.preventDefault();
            setDragging(true);
          }}
          onDragLeave={() => setDragging(false)}
          onDrop={(e) => {
            e.preventDefault();
            setDragging(false);
            if (e.dataTransfer.files.length) void readFiles(e.dataTransfer.files);
          }}
        >
          <div className="button-row">
            <button type="button" className="button secondary" onClick={() => fileRef.current?.click()}>
              📂 {t('ha_open_files')}
            </button>
            {navigator.clipboard?.readText && (
              <button
                type="button"
                className="button secondary"
                onClick={() =>
                  navigator.clipboard
                    .readText()
                    .then((v) => putText(v))
                    .catch(() => null)
                }
              >
                📋 {t('hs_paste_auto')}
              </button>
            )}
            {Object.values(fields).some(Boolean) && (
              <button
                type="button"
                className="button secondary"
                onClick={() => {
                  setFields({ party: '', hunt: '', input: '', serverlog: '' });
                  setFiles([]);
                  setMsg(null);
                }}
              >
                ✕ {t('ha_clear')}
              </button>
            )}
          </div>
          {files.length > 0 && <p className="muted small">📄 {files.join(', ')}</p>}
          <input ref={fileRef} type="file" accept=".txt,.json,.log,text/plain,application/json" multiple hidden onChange={(e) => e.target.files && void readFiles(e.target.files)} />
        </div>

        <div className="hs-fields">
          {FIELDS.map((f) => {
            const a = perField[f.key];
            const wrong = fields[f.key].trim() && fieldFor(a) && fieldFor(a) !== f.key && !(f.key === 'hunt' && fieldFor(a) === 'input');
            return (
              <div key={f.key} className="hs-field">
                <label htmlFor={`hs-${f.key}`}>
                  <strong>{f.label}</strong> {f.pro && <span className="tag pro">Pro</span>}
                </label>
                <textarea
                  id={`hs-${f.key}`}
                  className="ha-textarea"
                  value={fields[f.key]}
                  onChange={(e) => setFields((x) => ({ ...x, [f.key]: e.target.value }))}
                  rows={fields[f.key] ? 5 : 3}
                  spellCheck={false}
                  placeholder={t(f.hint)}
                />
                <FieldMeta field={f.key} analysis={a} text={fields[f.key]} />
                {wrong && (
                  <button
                    type="button"
                    className="button secondary small-btn"
                    onClick={() => {
                      const target = fieldFor(a)!;
                      setFields((x) => ({ ...x, [target]: x[f.key], [f.key]: '' }));
                    }}
                  >
                    ↪ {t('hs_move_to', { field: FIELDS.find((x) => x.key === fieldFor(a))!.label })}
                  </button>
                )}
              </div>
            );
          })}
        </div>
        {analysis.serverLog && !premium && <p className="muted small">{t('ha_serverlog_not_saved')}</p>}
        <details className="ha-help">
          <summary>{t('ha_help_title')}</summary>
          <ol className="small">
            <li>{t('ha_help_hunt')}</li>
            <li>{t('ha_help_json')}</li>
            <li>{t('ha_help_input')}</li>
            <li>{t('ha_help_party')}</li>
            <li>{t('ha_help_serverlog')}</li>
          </ol>
        </details>
      </Step>

      {hunt && (
        <details className="card hs-preview" open>
          <summary>
            <strong>📊 {t('hs_preview')}</strong>
          </summary>
          <HuntView
            hunt={hunt}
            input={analysis.input}
            serverLog={analysis.serverLog}
            premium={premium}
            avg={avg}
            expenses={expenses}
          />
        </details>
      )}

      {/* 3 — Character and build */}
      <Step n={3} title={t('hs_step_char')} badge={t('hs_recommended')} intro={t('hs_step_char_intro')}>
        <div className="form-grid">
          {user ? (
            <label>
              <span>{t('hs_character')}</span>
              <select
                value={charId}
                onChange={(e) => {
                  setCharId(e.target.value);
                  setBuild((b) => ({ ...b, set: null, wod: null }));
                }}
              >
                {chars.map((c) => (
                  <option key={c.id} value={c.id}>
                    {c.name} {c.level ? `(${c.level})` : ''}
                  </option>
                ))}
                <option value="">{t('ha_other_character')}</option>
              </select>
            </label>
          ) : null}
          {(!char || !user) && (
            <label>
              <span>{t('ha_character_name')}</span>
              <input value={charName} onChange={(e) => setCharName(e.target.value)} maxLength={29} placeholder="Bubble" />
            </label>
          )}
          <label>
            <span>Level</span>
            <input inputMode="numeric" value={level} onChange={(e) => setLevel(e.target.value.replace(/\D/g, '').slice(0, 4))} />
          </label>
          <label>
            <span>{t('vocation')}</span>
            <VocationSelect value={vocation} onChange={setVocation} allLabel="—" />
          </label>
          <label className="span-2">
            <span>{t('ha_place')}</span>
            <input list="ha-places" value={placeQuery} onChange={(e) => setPlaceQuery(e.target.value)} placeholder={t('ha_place_ph')} />
            <datalist id="ha-places">
              {places.map((p) => (
                <option key={p.slug} value={p.name}>
                  {p.city}
                </option>
              ))}
            </datalist>
            {placeQuery && !place && <small className="muted">{t('ha_place_free')}</small>}
          </label>
        </div>
        {user && (
          <form
            className="inline-add"
            onSubmit={(e) => {
              e.preventDefault();
              void addCharacter();
            }}
          >
            <input value={newChar} onChange={(e) => setNewChar(e.target.value)} maxLength={29} placeholder={t('hs_new_char_ph')} aria-label={t('hs_new_char_ph')} />
            <button type="submit" className="button secondary" disabled={addingChar || !newChar.trim()}>
              {addingChar ? t('loading') : `+ ${t('hs_new_char')}`}
            </button>
          </form>
        )}
        {charMsg && <p className="small muted">{charMsg}</p>}
        {user && char && history.length > 0 && <p className="muted small">{t('hs_history_linked', { n: history.length, name: char.name })}</p>}

        <div className="hs-build">
          <div className="hs-build-block">
            <h3 className="hs-sub">🛡️ {t('hs_set')}</h3>
            <div className="picker-row">
              <select
                value={build.set?.id ?? (build.set ? 'local' : '')}
                onChange={(e) => {
                  const s = sets.find((x) => String(x.id) === e.target.value);
                  setBuild((b) => ({ ...b, set: s ? { id: s.id, name: s.name, data: s.data as SetData } : null }));
                }}
                aria-label={t('hs_set')}
              >
                <option value="">{t('hs_set_none')}</option>
                {build.set && !build.set.id && <option value="local">{build.set.name}</option>}
                {sets.map((s) => (
                  <option key={s.id} value={s.id}>
                    {s.name}
                  </option>
                ))}
              </select>
              {build.set && (
                <button type="button" className="button secondary small-btn" onClick={() => setEditor('set-edit')}>
                  ✏️ {t('edit')}
                </button>
              )}
              <button type="button" className="button secondary small-btn" onClick={() => setEditor('set-new')}>
                + {t('hs_set_new')}
              </button>
            </div>
            {setStats && <StatsPanel stats={setStats} compact />}
          </div>

          <div className="hs-build-block">
            <h3 className="hs-sub">☸️ Wheel of Destiny</h3>
            <div className="picker-row">
              <select
                value={build.wod?.id ?? (build.wod ? 'local' : '')}
                onChange={(e) => {
                  const w = wods.find((x) => String(x.id) === e.target.value);
                  setBuild((b) => ({ ...b, wod: w ? { id: w.id, name: w.name, data: w.data as WodData } : null }));
                }}
                aria-label="Wheel of Destiny"
              >
                <option value="">{t('hs_wod_none')}</option>
                {build.wod && !build.wod.id && <option value="local">{build.wod.name}</option>}
                {wods.map((w) => (
                  <option key={w.id} value={w.id}>
                    {w.name}
                  </option>
                ))}
              </select>
              {build.wod && (
                <button type="button" className="button secondary small-btn" onClick={() => setEditor('wod-edit')}>
                  ✏️ {t('edit')}
                </button>
              )}
              <button type="button" className="button secondary small-btn" onClick={() => setEditor('wod-new')}>
                + {t('hs_wod_new')}
              </button>
            </div>
            {build.wod && Object.keys(build.wod.data.revelations).length > 0 && (
              <p className="muted small">
                {Object.entries(build.wod.data.revelations)
                  .map(([p, s]) => `${p} ${t('hs_stage')} ${s}`)
                  .join(' · ')}
              </p>
            )}
          </div>

          <div className="hs-build-block">
            <h3 className="hs-sub">✨ Charms</h3>
            {!creatures.length && <p className="muted small">{t('hs_charms_hint')}</p>}
            <CharmsEditor value={build.charms} onChange={(charms) => setBuild((b) => ({ ...b, charms }))} creatures={creatures} />
          </div>

          <div className="hs-build-block">
            <h3 className="hs-sub">🎯 Preys</h3>
            <PreysEditor value={build.preys} onChange={(preys) => setBuild((b) => ({ ...b, preys }))} creatures={creatures} />
          </div>

          <div className="hs-build-block">
            <h3 className="hs-sub">🧘 {t('hs_stances')}</h3>
            <StancesEditor value={build.stances} onChange={(stances) => setBuild((b) => ({ ...b, stances }))} vocation={voc} />
          </div>

          <div className="hs-build-block">
            <h3 className="hs-sub">🧪 {t('hs_consumables')}</h3>
            <p className="muted small">{t('hs_consumables_hunt_hint')}</p>
            <ConsumablesInput value={build.consumables} onChange={(consumables) => setBuild((b) => ({ ...b, consumables }))} />
          </div>
        </div>

        <label className="field">
          <span>{t('ha_notes')}</span>
          <input value={notes} onChange={(e) => setNotes(e.target.value)} maxLength={1000} placeholder={t('ha_notes_ph')} />
        </label>
        {user && (
          <label className="check">
            <input type="checkbox" checked={saveTemplate} onChange={(e) => setSaveTemplate(e.target.checked)} /> {t('hs_save_template')}
          </label>
        )}
      </Step>

      {/* Sticky result + save */}
      <div className="hs-savebar">
        {hunt ? (
          <div className="hs-savebar-kpis">
            <span>
              <small className="muted">XP/h</small> <strong>{n(hunt.xp_h)}</strong>
            </span>
            <span>
              <small className="muted">{t('hs_final_profit')}</small>{' '}
              <strong className={finalProfit >= 0 ? 'pos' : 'neg'}>{gp(finalProfit, locale)}</strong>
            </span>
            <span className="hide-sm">
              <small className="muted">{t('ha_duration')}</small> <strong>{fmtDuration(hunt.duration_s)}</strong>
            </span>
          </div>
        ) : (
          <span className="muted small">{t('hs_need_data')}</span>
        )}
        <div className="button-row">
          {!user && (
            <a className="button secondary" href="/register" onClick={linkHandler('/register')}>
              {t('ha_create_account')}
            </a>
          )}
          <button type="button" className="button" onClick={save} disabled={busy || !hunt}>
            {busy ? t('loading') : user ? t('ha_save') : t('ha_save_local')}
          </button>
        </div>
      </div>
      {msg && <p className={msg.tone === 'ok' ? 'ok-text' : 'error'}>{msg.text}</p>}
      {!user && <p className="muted small">{t('ha_guest_note')}</p>}
      {avg && <p className="muted small">{t('ha_avg_note', { n: avg.count, xp: avg.xp_h.toLocaleString(locale) })}</p>}

      {(editor === 'set-new' || editor === 'set-edit') && (
        <SetEditor
          initial={editor === 'set-edit' ? build.set : null}
          characterId={char?.id ?? null}
          vocation={voc}
          canSave={!!user}
          onClose={() => setEditor(null)}
          onSaved={(s) => {
            setBuild((b) => ({ ...b, set: s }));
            if (s.id) setSaved((list) => [{ id: s.id!, kind: 'set', name: s.name, character_id: char?.id ?? null, data: s.data }, ...list.filter((x) => x.id !== s.id)]);
            setEditor(null);
          }}
        />
      )}
      {(editor === 'wod-new' || editor === 'wod-edit') && (
        <WodEditor
          initial={editor === 'wod-edit' ? build.wod : null}
          characterId={char?.id ?? null}
          vocation={voc}
          canSave={!!user}
          onClose={() => setEditor(null)}
          onSaved={(w) => {
            setBuild((b) => ({ ...b, wod: w }));
            if (w.id) setSaved((list) => [{ id: w.id!, kind: 'wod', name: w.name, character_id: char?.id ?? null, data: w.data }, ...list.filter((x) => x.id !== w.id)]);
            setEditor(null);
          }}
        />
      )}
    </div>
  );
}

/** What was read from one field (session, money, members, damage) */
function FieldMeta({ field, analysis, text }: { field: FieldKey; analysis: PasteAnalysis; text: string }) {
  const { t, n, locale } = useI18n();
  if (!text.trim()) return null;
  const h = analysis.hunt;
  if (!analysis.formats.length) return <p className="chip warn small">{t('ha_unknown_format')}</p>;
  const parts: string[] = [];
  if (h) {
    parts.push(fmtDuration(h.duration_s));
    if (h.party && field === 'party') {
      parts.push(`${t('ha_loot')} ${gp(h.loot, locale)}`, `${t('ha_supplies')} ${gp(h.supplies, locale)}`, `${t('ha_balance')} ${gp(h.balance, locale)}`);
      parts.push(`${h.party.members.length} ${t('hs_members')}${h.party.loot_type ? ` · ${h.party.loot_type}` : ''}`);
    } else {
      parts.push(`XP ${n(h.xp)}`, `${t('ha_loot')} ${gp(h.loot, locale)}`, `${t('ha_balance')} ${gp(h.balance, locale)}`);
      const kills = h.monsters.reduce((s, m) => s + m.count, 0);
      if (kills) parts.push(`${n(kills)} ${t('ha_kills')}${h.monsters[0] ? ` (${h.monsters[0].count}x ${titleCase(h.monsters[0].name)}…)` : ''}`);
    }
  }
  if (analysis.input && (field === 'input' || field === 'hunt')) {
    const i = analysis.input;
    parts.push(`${t('ha_received')} ${n(i.total)}${i.types[0] ? ` · ${i.types[0].name} ${i.types[0].pct}%` : ''}${i.sources[0] ? ` · ${i.sources[0].name}` : ''}`);
  }
  if (analysis.serverLog) {
    const s = analysis.serverLog;
    parts.push(`${s.sessions}× · ${s.first_time ?? '?'}–${s.last_time ?? '?'} · XP ${n(s.xp.total)}`);
  }
  return (
    <p className="hs-meta small">
      <span className="chip ok">✓</span> {parts.join(' · ')}
    </p>
  );
}

function ConsumablesInput({ value, onChange }: { value: string[]; onChange: (v: string[]) => void }) {
  const { t } = useI18n();
  const [v, setV] = useState('');
  return (
    <>
      {value.length > 0 && (
        <div className="chip-row">
          {value.map((c) => (
            <span key={c} className="chip">
              {c}
              <button type="button" className="icon-btn" aria-label={`${t('delete')} ${c}`} onClick={() => onChange(value.filter((x) => x !== c))}>
                ✕
              </button>
            </span>
          ))}
        </div>
      )}
      <form
        className="inline-add"
        onSubmit={(e) => {
          e.preventDefault();
          const c = v.trim();
          if (c && !value.includes(c) && value.length < 12) onChange([...value, c]);
          setV('');
        }}
      >
        <input list="hs-consumables-hunt" value={v} onChange={(e) => setV(e.target.value)} placeholder={t('hs_consumable_ph')} maxLength={60} aria-label={t('hs_consumables')} />
        <datalist id="hs-consumables-hunt">
          {['Berserk Potion', 'Bullseye Potion', 'Mastermind Potion', 'Transcendence Potion', 'Roasted Dragon Wings', 'Rotworm Stew', 'Hydra Tongue Salad', 'Blessed Steak', 'Wealth Duplex'].map((c) => (
            <option key={c} value={c} />
          ))}
        </datalist>
        <button type="submit" className="button secondary">
          +
        </button>
      </form>
    </>
  );
}
