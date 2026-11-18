/**
 * SpawnRank (SPEC §6): best hunting places for one creature, now over every TibiaWiki hunting place (D-42).
 * "My tasks" mode: this week's creature tasks of one of your characters (D-37), with the top places for each.
 */
import { CreatureLink, CreatureSummary } from '../components/CreatureLink';
import { useEffect, useMemo, useState } from 'react';
import { api, characters as charsApi, weekly, type CharacterListItem, type Creature, type SpawnRankRow, type WeeklyTask } from '../api';
import { useAuth } from '../auth';
import { useI18n } from '../i18n';
import { linkHandler } from '../router';
import { BoostedBanner } from '../components/BoostedBanner';
import { QuestBadge, Stars } from './HuntingPlacesPage';

type Mode = 'creature' | 'tasks';

function ResultsTable({ rows, limit }: { rows: SpawnRankRow[]; limit?: number }) {
  const { t, n } = useI18n();
  if (!rows.length) return <p className="muted">{t('spawnrank_no_places')}</p>;
  return (
    <div className="table-wrap">
      <table className="spawnrank-table">
        <thead>
          <tr>
            <th>#</th>
            <th>{t('zone')}</th>
            <th className="num">{t('min_level')}</th>
            <th>{t('hunt_exp')}</th>
            <th>{t('hunt_loot')}</th>
            <th className="num">{t('score')}</th>
          </tr>
        </thead>
        <tbody>
          {rows.slice(0, limit ?? rows.length).map((r, i) => (
            <tr key={r.zone_id}>
              <td>{i + 1}</td>
              <td>
                {r.slug ? (
                  <a href={`/hunting/${r.slug}`} onClick={linkHandler(`/hunting/${r.slug}`)}>
                    {r.zone}
                  </a>
                ) : (
                  r.zone
                )}
                <div className="muted small">
                  {r.city} <QuestBadge quests={r.access_quests ?? []} />
                </div>
              </td>
              <td className="num">{r.min_level ? n(r.min_level) : '—'}</td>
              <td>
                <Stars n={r.exp_stars ?? null} label={t('hunt_exp')} />
              </td>
              <td>
                <Stars n={r.loot_stars ?? null} label={t('hunt_loot')} />
              </td>
              <td
                className="num"
                title={`${t('density')} ${r.components.density} · ${t('synergy')} ${r.components.synergy} · ${t('profit')} ${r.components.profitability} · ${t('friction')} ${r.components.access_friction}`}
              >
                <strong>{r.score.toFixed(2)}</strong>
              </td>
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  );
}

function ByCreature({ creatures }: { creatures: Creature[] }) {
  const { t } = useI18n();
  // ?creature=<id> preselects (links from weekly tasks and hunting places)
  const [selected, setSelected] = useState<number | null>(() => {
    const id = Number(new URLSearchParams(window.location.search).get('creature'));
    return Number.isInteger(id) && id > 0 ? id : null;
  });
  const [q, setQ] = useState('');
  const [rows, setRows] = useState<SpawnRankRow[] | null>(null);
  const [error, setError] = useState(false);

  useEffect(() => {
    if (selected == null) return;
    let alive = true;
    setRows(null);
    setError(false);
    api
      .spawnRank(selected)
      .then((r) => alive && setRows(r))
      .catch(() => alive && setError(true));
    return () => {
      alive = false;
    };
  }, [selected]);

  const matches = useMemo(() => {
    const needle = q.trim().toLowerCase();
    const list = needle ? creatures.filter((c) => c.name.toLowerCase().includes(needle)) : creatures.filter((c) => c.is_boosted);
    return list.slice(0, 30);
  }, [creatures, q]);
  const current = creatures.find((c) => c.id === selected);

  return (
    <>
      <label className="field spawnrank-search">
        <span>{t('select_creature')}</span>
        <input type="search" value={q} onChange={(e) => setQ(e.target.value)} placeholder={t('spawnrank_search_hint', { n: creatures.length })} />
      </label>
      {matches.length > 0 && (
        <div className="picker">
          {matches.map((c) => (
            <button key={c.id} type="button" className={`picker-item ${c.id === selected ? 'active' : ''}`} onClick={() => setSelected(c.id)}>
              {c.image_url && <img src={c.image_url} alt="" width={32} height={32} loading="lazy" />}
              <span>{c.name}</span>
              {c.is_boosted && <span className="tag boosted">{t('boosted')}</span>}
            </button>
          ))}
        </div>
      )}
      {q.trim() && !matches.length && <p className="muted">{t('spawnrank_no_creature')}</p>}
      {error && <p className="alert">{t('api_error')}</p>}
      {current && (
        <section className="card">
          <h2>
            <CreatureLink name={current.name} />
          </h2>
          <CreatureSummary name={current.name} />
          {rows ? <ResultsTable rows={rows} /> : <p className="muted">{t('loading')}</p>}
        </section>
      )}
    </>
  );
}

function MyTasks({ creatures }: { creatures: Creature[] }) {
  const { t } = useI18n();
  const { user } = useAuth();
  const [chars, setChars] = useState<CharacterListItem[] | null>(null);
  const [charId, setCharId] = useState<number | null>(null);
  const [tasks, setTasks] = useState<WeeklyTask[] | null>(null);
  const [places, setPlaces] = useState<Record<string, SpawnRankRow[] | 'none'>>({});

  useEffect(() => {
    if (!user) return;
    charsApi
      .list()
      .then((r) => {
        setChars(r.characters);
        setCharId(r.characters[0]?.id ?? null);
      })
      .catch(() => setChars([]));
  }, [user]);

  useEffect(() => {
    setTasks(null);
    setPlaces({});
    if (!charId) return;
    weekly
      .list(charId)
      .then((r) => setTasks(r.tasks.filter((x) => x.kind === 'creature')))
      .catch(() => setTasks([]));
  }, [charId]);

  useEffect(() => {
    if (!tasks) return;
    const byName = new Map(creatures.map((c) => [c.name.toLowerCase(), c.id]));
    for (const task of tasks) {
      const id = byName.get(task.name.toLowerCase());
      if (!id) {
        setPlaces((p) => ({ ...p, [task.name]: 'none' }));
        continue;
      }
      api
        .spawnRank(id)
        .then((rows) => setPlaces((p) => ({ ...p, [task.name]: rows })))
        .catch(() => setPlaces((p) => ({ ...p, [task.name]: 'none' })));
    }
  }, [tasks, creatures]);

  if (user === null) {
    return (
      <section className="card">
        <p>{t('spawnrank_tasks_login')}</p>
        <a className="button" href="/login?next=/spawnrank" onClick={linkHandler('/login?next=/spawnrank')}>
          {t('login')}
        </a>
      </section>
    );
  }
  if (!chars) return <p className="muted">{t('loading')}</p>;
  if (!chars.length) {
    return (
      <section className="card">
        <p>{t('soulcores_no_chars')}</p>
        <a className="button" href="/characters" onClick={linkHandler('/characters')}>
          {t('nav_characters')}
        </a>
      </section>
    );
  }

  return (
    <>
      <label className="field spawnrank-search">
        <span>{t('soulcores_character')}</span>
        <select value={charId ?? ''} onChange={(e) => setCharId(Number(e.target.value))}>
          {chars.map((c) => (
            <option key={c.id} value={c.id}>
              {c.name}
              {c.world ? ` · ${c.world}` : ''}
            </option>
          ))}
        </select>
      </label>
      {tasks && !tasks.length && (
        <section className="card">
          <p className="muted">{t('spawnrank_tasks_empty')}</p>
          <a href={`/characters/${charId}`} onClick={linkHandler(`/characters/${charId}`)}>
            {t('spawnrank_tasks_add')}
          </a>
        </section>
      )}
      {tasks?.map((task) => {
        const rows = places[task.name];
        return (
          <section className="card" key={task.id}>
            <h2>
              <CreatureLink name={task.name} resolve />{' '}
              <span className="muted small">
                {task.progress}/{task.target}
                {task.done_at ? ' ✓' : ''}
              </span>
            </h2>
            {rows === undefined && <p className="muted">{t('loading')}</p>}
            {rows === 'none' && <p className="muted">{t('spawnrank_no_places')}</p>}
            {Array.isArray(rows) && <ResultsTable rows={rows} limit={3} />}
          </section>
        );
      })}
    </>
  );
}

export function SpawnRankPage() {
  const { t } = useI18n();
  const [creatures, setCreatures] = useState<Creature[] | null>(null);
  const [error, setError] = useState(false);
  const [mode, setMode] = useState<Mode>(() =>
    new URLSearchParams(window.location.search).get('mode') === 'tasks' ? 'tasks' : 'creature',
  );

  useEffect(() => {
    api
      .creatures()
      .then(setCreatures)
      .catch(() => setError(true));
  }, []);

  return (
    <div className="page">
      <h1>{t('spawnrank_title')}</h1>
      <p className="muted">{t('spawnrank_intro')}</p>
      <BoostedBanner only="creature" />

      <div className="segmented spawnrank-modes" role="group">
        <button type="button" className={mode === 'creature' ? 'active' : ''} aria-pressed={mode === 'creature'} onClick={() => setMode('creature')}>
          {t('spawnrank_mode_creature')}
        </button>
        <button type="button" className={mode === 'tasks' ? 'active' : ''} aria-pressed={mode === 'tasks'} onClick={() => setMode('tasks')}>
          {t('spawnrank_mode_tasks')}
        </button>
      </div>

      {error && <p className="alert">{t('api_error')}</p>}
      {!creatures && !error && <p className="muted">{t('loading')}</p>}
      {creatures && (mode === 'creature' ? <ByCreature creatures={creatures} /> : <MyTasks creatures={creatures} />)}
    </div>
  );
}
