/**
 * Equipment set editor (D-59): one item per slot (search the TibiaWiki catalog), forge tier and imbuements,
 * consumables, and the combat stats it gives — live. Saved per character so later hunts just pick it.
 */
import { useEffect, useMemo, useState } from 'react';
import { useI18n } from '../../../i18n';
import { ApiError, builds, type SavedBuild } from '../../../api';
import {
  CONSUMABLES,
  IMBUE_TIERS,
  SLOTS,
  computeSetStats,
  emptySet,
  imbuementsFor,
  itemLibrary,
  loadEquipment,
  searchEquipment,
  type Equipment,
  type SetData,
  type SetItem,
  type Slot,
  type Voc,
} from '../../../core/build';
import { Modal } from '../../../components/Modal';
import { ItemSprite, useSprites } from '../../../components/ItemSprite';
import { StatsPanel, titleCase } from './StatsPanel';

const SLOT_ICON: Record<Slot, string> = {
  helmet: '⛑️',
  amulet: '📿',
  armor: '🥋',
  weapon: '⚔️',
  shield: '🛡️',
  legs: '👖',
  ring: '💍',
  boots: '🥾',
  ammo: '🏹',
};
/** Paper-doll order (3 columns, like the game's inventory) */
const DOLL: (Slot | null)[] = ['amulet', 'helmet', null, 'weapon', 'armor', 'shield', 'ring', 'legs', 'ammo', null, 'boots', null];

export function useEquipment() {
  const [eq, setEq] = useState<{ list: Equipment[]; index: Map<string, Equipment> } | null>(null);
  useEffect(() => {
    let alive = true;
    void loadEquipment().then((r) => alive && setEq(r));
    return () => {
      alive = false;
    };
  }, []);
  return eq;
}

export function SetEditor({
  initial,
  characterId,
  vocation,
  canSave,
  onSaved,
  onClose,
}: {
  initial: { id: number | null; name: string; data: SetData } | null;
  characterId: number | null;
  vocation: Voc | null;
  /** logged in: saves to the account; guests keep it in this hunt only */
  canSave: boolean;
  onSaved: (b: { id: number | null; name: string; data: SetData }) => void;
  onClose: () => void;
}) {
  const { t } = useI18n();
  const eq = useEquipment();
  const sprites = useSprites();
  const [name, setName] = useState(initial?.name ?? '');
  const [data, setData] = useState<SetData>(initial?.data ?? emptySet());
  const [slot, setSlot] = useState<Slot | null>(null);
  const [q, setQ] = useState('');
  const [consumable, setConsumable] = useState('');
  const [busy, setBusy] = useState(false);
  const [err, setErr] = useState('');
  const [library, setLibrary] = useState<Partial<Record<Slot, SetItem[]>>>({});

  // D-61: "my items" from the other saved sets (configured tier / imbuements), reusable with one click
  useEffect(() => {
    if (!canSave) return;
    builds
      .list()
      .then((r) => setLibrary(itemLibrary(r.builds.filter((x) => x.kind === 'set' && x.id !== initial?.id).map((x) => x.data as SetData))))
      .catch(() => null);
  }, [canSave, initial?.id]);

  const stats = useMemo(() => (eq ? computeSetStats(data, eq.index) : null), [data, eq]);
  const results = useMemo(() => (eq && slot ? searchEquipment(eq.list, q, slot, vocation) : []), [eq, slot, q, vocation]);
  const current = slot ? data.slots[slot] : undefined;
  const currentInfo = current && eq ? eq.index.get(current.name.toLowerCase()) : undefined;

  const setItem = (s: Slot, item: SetData['slots'][Slot] | undefined) =>
    setData((d) => {
      const slots = { ...d.slots };
      if (item) slots[s] = item;
      else delete slots[s];
      // A two-handed weapon frees the shield hand
      if (s === 'weapon' && item && eq?.index.get(item.name.toLowerCase())?.two) delete slots.shield;
      return { ...d, slots };
    });

  const save = async () => {
    const clean = name.trim() || t('hs_set_default_name');
    setErr('');
    if (!canSave) {
      onSaved({ id: null, name: clean, data });
      return;
    }
    setBusy(true);
    try {
      if (initial?.id) {
        await builds.update(initial.id, { name: clean, data });
        onSaved({ id: initial.id, name: clean, data });
      } else {
        const r: SavedBuild = await builds.create({ kind: 'set', name: clean, character_id: characterId, data });
        onSaved({ id: r.id, name: clean, data });
      }
    } catch (e) {
      setErr(e instanceof ApiError && e.code === 'build_limit' ? t('hs_build_limit') : t('ha_err_generic'));
    } finally {
      setBusy(false);
    }
  };

  return (
    <Modal
      title={initial?.id ? t('hs_set_edit') : t('hs_set_new')}
      onClose={onClose}
      wide
      footer={
        <>
          {err && <span className="error small">{err}</span>}
          <button type="button" className="button secondary" onClick={onClose}>
            {t('cancel')}
          </button>
          <button type="button" className="button" onClick={save} disabled={busy}>
            {busy ? t('loading') : t('save')}
          </button>
        </>
      }
    >
      <label className="field">
        <span>{t('hs_build_name')}</span>
        <input value={name} onChange={(e) => setName(e.target.value)} maxLength={80} placeholder={t('hs_set_name_ph')} />
      </label>
      <div className="set-editor">
        <div>
          <div className="paper-doll">
            {DOLL.map((s, i) =>
              s ? (
                <button
                  key={s}
                  type="button"
                  className={`doll-slot${slot === s ? ' active' : ''}${data.slots[s] ? ' filled' : ''}`}
                  onClick={() => {
                    setSlot(s);
                    setQ('');
                  }}
                  title={data.slots[s]?.name ?? t(`hs_slot_${s}`)}
                  aria-label={`${t(`hs_slot_${s}`)}${data.slots[s] ? `: ${data.slots[s]!.name}` : ''}`}
                >
                  {data.slots[s] ? <ItemSprite name={data.slots[s]!.name} sprites={sprites} size={32} /> : <span aria-hidden="true">{SLOT_ICON[s]}</span>}
                  {(data.slots[s]?.imbues?.length ?? 0) > 0 && <span className="doll-imbue">{data.slots[s]!.imbues!.length}</span>}
                </button>
              ) : (
                <span key={`gap-${i}`} />
              ),
            )}
          </div>
          <h4>{t('hs_consumables')}</h4>
          <div className="chip-row">
            {data.consumables.map((c) => (
              <span key={c} className="chip">
                {c}
                <button type="button" className="icon-btn" aria-label={`${t('delete')} ${c}`} onClick={() => setData((d) => ({ ...d, consumables: d.consumables.filter((x) => x !== c) }))}>
                  ✕
                </button>
              </span>
            ))}
          </div>
          <form
            className="inline-add"
            onSubmit={(e) => {
              e.preventDefault();
              const c = consumable.trim();
              if (c && !data.consumables.includes(c) && data.consumables.length < 12) setData((d) => ({ ...d, consumables: [...d.consumables, c] }));
              setConsumable('');
            }}
          >
            <input list="hs-consumables" value={consumable} onChange={(e) => setConsumable(e.target.value)} placeholder={t('hs_consumable_ph')} maxLength={60} />
            <datalist id="hs-consumables">
              {CONSUMABLES.map((c) => (
                <option key={c} value={c} />
              ))}
            </datalist>
            <button type="submit" className="button secondary">
              +
            </button>
          </form>
        </div>

        <div className="set-slot-panel">
          {!slot ? (
            <p className="muted">{t('hs_pick_slot')}</p>
          ) : (
            <>
              <h4>
                {SLOT_ICON[slot]} {t(`hs_slot_${slot}`)}
              </h4>
              {current && (
                <div className="slot-current">
                  <div className="item-cell">
                    <ItemSprite name={current.name} sprites={sprites} size={32} />
                    <div>
                      <strong>{current.name}</strong>
                      {currentInfo && (
                        <div className="muted small">
                          {[
                            currentInfo.arm ? `Arm ${currentInfo.arm}` : '',
                            currentInfo.def ? `Def ${currentInfo.def}` : '',
                            ...Object.entries(currentInfo.a ?? {}).map(([k, v]) => `${k} ${v > 0 ? '+' : ''}${v}`),
                            ...Object.entries(currentInfo.r ?? {}).map(([k, v]) => `${k} ${v > 0 ? '+' : ''}${v}%`),
                          ]
                            .filter(Boolean)
                            .join(' · ')}
                        </div>
                      )}
                    </div>
                    <button type="button" className="icon-btn" aria-label={t('delete')} onClick={() => setItem(slot, undefined)}>
                      ✕
                    </button>
                  </div>
                  {(currentInfo?.tier ?? 0) > 0 && (
                    <label className="field inline">
                      <span>{t('hs_forge_tier')}</span>
                      <select value={current.tier ?? 0} onChange={(e) => setItem(slot, { ...current, tier: Number(e.target.value) || undefined })}>
                        {Array.from({ length: 11 }, (_, i) => (
                          <option key={i} value={i}>
                            {i}
                          </option>
                        ))}
                      </select>
                    </label>
                  )}
                  {currentInfo && (currentInfo.imbue ?? 0) > 0 && (
                    <div className="imbue-rows">
                      {Array.from({ length: currentInfo.imbue ?? 0 }, (_, i) => {
                        const im = current.imbues?.[i];
                        const opts = imbuementsFor(currentInfo);
                        const update = (next: { type: string; tier: number } | null) => {
                          const list = [...(current.imbues ?? [])];
                          if (next) list[i] = next;
                          else list.splice(i, 1);
                          setItem(slot, { ...current, imbues: list.filter(Boolean) });
                        };
                        return (
                          <div key={i} className="imbue-row">
                            <select
                              aria-label={`${t('hs_imbuement')} ${i + 1}`}
                              value={im?.type ?? ''}
                              onChange={(e) => update(e.target.value ? { type: e.target.value, tier: im?.tier ?? 3 } : null)}
                            >
                              <option value="">{t('hs_imbuement')} {i + 1}…</option>
                              {opts.map((o) => (
                                <option key={o.type} value={o.type}>
                                  {o.label}
                                </option>
                              ))}
                            </select>
                            {im && (
                              <select aria-label={t('hs_imbue_tier')} value={im.tier} onChange={(e) => update({ ...im, tier: Number(e.target.value) })}>
                                {IMBUE_TIERS.map((label, k) => (
                                  <option key={label} value={k + 1}>
                                    {label}
                                  </option>
                                ))}
                              </select>
                            )}
                          </div>
                        );
                      })}
                    </div>
                  )}
                </div>
              )}
              {(library[slot]?.length ?? 0) > 0 && (
                <div className="my-items">
                  <span className="muted small">⭐ {t('hs_my_items')}</span>
                  <div className="chip-row">
                    {library[slot]!.map((it, i) => (
                      <button key={i} type="button" className="chip my-item" onClick={() => setItem(slot, { ...it, imbues: it.imbues?.map((x) => ({ ...x })) })}>
                        <ItemSprite name={it.name} sprites={sprites} size={20} />
                        <span>
                          {it.name}
                          {it.tier ? ` T${it.tier}` : ''}
                          {it.imbues?.length ? <span className="muted"> · {it.imbues.map((x) => `${titleCase(x.type)} ${IMBUE_TIERS[x.tier - 1] ?? ''}`).join(', ')}</span> : null}
                        </span>
                      </button>
                    ))}
                  </div>
                </div>
              )}
              <input
                className="slot-search"
                value={q}
                onChange={(e) => setQ(e.target.value)}
                placeholder={t('hs_item_search_ph')}
                aria-label={t('hs_item_search_ph')}
              />
              {!eq ? (
                <p className="muted small">{t('loading')}</p>
              ) : (
                <ul className="slot-results">
                  {results.map((e) => (
                    <li key={e.name}>
                      <button
                        type="button"
                        onClick={() => {
                          setItem(slot, { name: e.name });
                          setQ('');
                        }}
                      >
                        <ItemSprite name={e.name} sprites={sprites} size={24} />
                        <span>
                          {e.name}
                          <span className="muted small">
                            {' '}
                            {e.level ? `Lv ${e.level}` : ''} {e.voc ? `· ${e.voc.join('/')}` : ''}
                          </span>
                        </span>
                      </button>
                    </li>
                  ))}
                  {!results.length && <li className="muted small">{t('hs_no_items')}</li>}
                </ul>
              )}
            </>
          )}
        </div>
      </div>
      {stats && <StatsPanel stats={stats} />}
      <p className="muted small">{t('hs_wiki_credit')}</p>
    </Modal>
  );
}

export { SLOT_ICON };
export const isSlot = (s: string): s is Slot => (SLOTS as readonly string[]).includes(s);
