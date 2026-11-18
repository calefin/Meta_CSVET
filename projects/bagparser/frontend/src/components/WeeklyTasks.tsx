/** Weekly tasks of a character (D-37): creatures to kill and items to deliver, with where to get them. */
import { CreatureLink } from './CreatureLink';
import { useCallback, useEffect, useState } from 'react';
import { weekly, type DropSources, type WeeklyTask } from '../api';
import { useI18n } from '../i18n';
import { linkHandler } from '../router';
import { ItemSprite, useSprites } from './ItemSprite';

function Where({ task }: { task: WeeklyTask }) {
  const { t, n } = useI18n();
  const [open, setOpen] = useState(false);
  const [drops, setDrops] = useState<DropSources | null>(null);
  const [creatureId, setCreatureId] = useState<number | null | undefined>(undefined);
  const [error, setError] = useState(false);

  useEffect(() => {
    if (!open) return;
    setError(false);
    if (task.kind === 'item' && !drops) weekly.drops(task.name).then(setDrops).catch(() => setError(true));
    if (task.kind === 'creature' && creatureId === undefined)
      weekly
        .creature(task.name)
        .then((c) => setCreatureId(c?.id ?? null))
        .catch(() => setCreatureId(null));
  }, [open, task, drops, creatureId]);

  const pct = (r: number) => `${n(Number((r * 100).toFixed(r < 0.01 ? 2 : 1)))}%`;

  return (
    <div className="where">
      <button type="button" className="link-btn" onClick={() => setOpen((v) => !v)} aria-expanded={open}>
        {open ? '▾' : '▸'} {t(task.kind === 'item' ? 'where_drop' : 'where_hunt')}
      </button>
      {open && task.kind === 'creature' && (
        <p className="small muted">
          {creatureId === undefined
            ? t('loading')
            : creatureId
              ? (
                  <a href={`/spawnrank?creature=${creatureId}`} onClick={linkHandler(`/spawnrank?creature=${creatureId}`)}>
                    {t('see_spawnrank', { name: task.name })}
                  </a>
                )
              : t('no_spawn_data')}
        </p>
      )}
      {open && task.kind === 'item' && (
        <div className="small">
          {error && <p className="alert">{t('api_error')}</p>}
          {!drops && !error && <p className="muted">{t('loading_wiki')}</p>}
          {drops && drops.places.length > 0 && (
            <>
              <strong>{t('best_places')}</strong>
              <ol className="where-list">
                {drops.places.slice(0, 5).map((p) => (
                  <li key={p.zone + p.creature}>
                    {p.zone} ({p.city}) — {p.creature}
                    {p.rate != null && <span className="muted"> · {pct(p.rate)} · {t('density')} {p.density}/5</span>}
                  </li>
                ))}
              </ol>
            </>
          )}
          {drops && (
            <>
              <strong>{t('dropped_by')}</strong>
              {drops.creatures.length === 0 ? (
                <p className="muted">{t('no_drop_data')}</p>
              ) : (
                <ul className="where-list">
                  {drops.creatures.slice(0, 8).map((c) => (
                    <li key={c.name}>
                      <CreatureLink name={c.name} resolve />
                      <span className="muted"> · {c.rate != null ? pct(c.rate) : t('rate_unknown')}</span>
                    </li>
                  ))}
                </ul>
              )}
              <p className="muted">{t('drops_source')}</p>
            </>
          )}
        </div>
      )}
    </div>
  );
}

export function WeeklyTasks({ characterId }: { characterId: number }) {
  const { t, n, locale } = useI18n();
  const sprites = useSprites();
  const [data, setData] = useState<Awaited<ReturnType<typeof weekly.list>> | null>(null);
  const [kind, setKind] = useState<'creature' | 'item'>('creature');
  const [name, setName] = useState('');
  const [target, setTarget] = useState('');
  const [msg, setMsg] = useState<string | null>(null);

  const load = useCallback(() => {
    weekly.list(characterId).then(setData).catch(() => setMsg(t('api_error')));
  }, [characterId, t]);
  useEffect(load, [load]);

  const add = async (e: React.FormEvent) => {
    e.preventDefault();
    setMsg(null);
    try {
      await weekly.add(characterId, kind, name, Number(target));
      setName('');
      setTarget('');
      load();
    } catch {
      setMsg(t('weekly_add_error'));
    }
  };

  const update = async (task: WeeklyTask, patch: { progress?: number; done?: boolean }) => {
    try {
      const updated = await weekly.update(characterId, task.id, patch);
      setData((d) => d && { ...d, tasks: d.tasks.map((x) => (x.id === task.id ? updated : x)) });
    } catch {
      setMsg(t('api_error'));
    }
  };

  if (!data) return null;
  const done = data.tasks.filter((x) => x.done_at).length;
  const weekLabel = new Date(`${data.week_start}T12:00:00Z`).toLocaleDateString(locale, { day: '2-digit', month: '2-digit' });

  return (
    <section className="card weekly">
      <div className="dest-head">
        <h2>{t('weekly_title')}</h2>
        <span className="muted small">
          {t('weekly_week_of', { date: weekLabel })} · {done}/{data.tasks.length}
        </span>
      </div>
      <p className="muted small">{t('weekly_intro')}</p>

      {data.tasks.length > 0 && (
        <ul className="weekly-list">
          {data.tasks.map((task) => (
            <li key={task.id} className={task.done_at ? 'weekly-item done' : 'weekly-item'}>
              <label className="weekly-check">
                <input type="checkbox" checked={!!task.done_at} onChange={(e) => update(task, { done: e.target.checked })} />
                <span className="sr-only">{t('weekly_done')}</span>
              </label>
              {task.kind === 'item' && <ItemSprite name={task.name.toLowerCase()} sprites={sprites} size={28} />}
              <div className="weekly-main">
                <div className="weekly-name">
                  <strong>{task.kind === 'creature' ? <CreatureLink name={task.name} resolve /> : task.name}</strong>
                  <span className="tag">{t(task.kind === 'item' ? 'weekly_item' : 'weekly_creature')}</span>
                </div>
                <div className="weekly-progress">
                  <input
                    type="number"
                    inputMode="numeric"
                    min={0}
                    max={100000}
                    defaultValue={task.progress}
                    aria-label={t('weekly_progress')}
                    onBlur={(e) => Number(e.target.value) !== task.progress && update(task, { progress: Number(e.target.value) || 0 })}
                  />
                  <span className="muted small">/ {n(task.target)}</span>
                  <div className="progress grow">
                    <div className="progress-fill" style={{ width: `${Math.min(100, (task.progress / task.target) * 100)}%` }} />
                  </div>
                </div>
                <Where task={task} />
              </div>
              <button
                type="button"
                className="icon-btn"
                aria-label={t('remove')}
                onClick={() => weekly.remove(characterId, task.id).then(load)}
              >
                ×
              </button>
            </li>
          ))}
        </ul>
      )}

      <form className="weekly-form" onSubmit={add}>
        <select value={kind} onChange={(e) => setKind(e.target.value as 'creature' | 'item')} aria-label={t('weekly_kind')}>
          <option value="creature">{t('weekly_creature')}</option>
          <option value="item">{t('weekly_item')}</option>
        </select>
        <input value={name} onChange={(e) => setName(e.target.value)} placeholder={t(kind === 'item' ? 'weekly_item_ph' : 'weekly_creature_ph')} required minLength={2} maxLength={80} />
        <input type="number" inputMode="numeric" min={1} max={100000} value={target} onChange={(e) => setTarget(e.target.value)} placeholder={t('weekly_amount')} required />
        <button type="submit">{t('add')}</button>
      </form>
      {msg && <p className="alert">{msg}</p>}

      {data.history.length > 0 && (
        <p className="muted small">
          {t('weekly_history')}:{' '}
          {data.history
            .map((h) => `${new Date(`${h.week_start}T12:00:00Z`).toLocaleDateString(locale, { day: '2-digit', month: '2-digit' })} ${h.done}/${h.total}`)
            .join(' · ')}
        </p>
      )}
    </section>
  );
}
