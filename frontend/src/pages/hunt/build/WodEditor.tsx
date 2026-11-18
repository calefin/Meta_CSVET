/** Wheel of Destiny editor (D-59): export code, points per domain, revelation perk stages, gems and notes. */
import { useState } from 'react';
import { useI18n } from '../../../i18n';
import { ApiError, builds } from '../../../api';
import { REVELATIONS, WOD_DOMAINS, emptyWod, type Voc, type WodData } from '../../../core/build';
import { Modal } from '../../../components/Modal';

const DOMAIN_ICON: Record<string, string> = { green: '🟢', red: '🔴', purple: '🟣', blue: '🔵' };

export function WodEditor({
  initial,
  characterId,
  vocation,
  canSave,
  onSaved,
  onClose,
}: {
  initial: { id: number | null; name: string; data: WodData } | null;
  characterId: number | null;
  vocation: Voc | null;
  canSave: boolean;
  onSaved: (b: { id: number | null; name: string; data: WodData }) => void;
  onClose: () => void;
}) {
  const { t } = useI18n();
  const [name, setName] = useState(initial?.name ?? '');
  const [data, setData] = useState<WodData>(initial?.data ?? emptyWod());
  const [busy, setBusy] = useState(false);
  const [err, setErr] = useState('');
  const perks = vocation ? REVELATIONS[vocation] : REVELATIONS.knight.slice(0, 1);
  const extraPerks = Object.keys(data.revelations).filter((k) => !perks.includes(k));
  const totalPoints = Object.values(data.points).reduce((s, v) => s + v, 0);

  const save = async () => {
    const clean = name.trim() || t('hs_wod_default_name');
    const payload = { ...data, code: data.code?.trim() || null };
    if (payload.code && !/^[A-Za-z0-9+/=_-]{4,600}$/.test(payload.code)) {
      setErr(t('hs_wod_bad_code'));
      return;
    }
    setErr('');
    if (!canSave) {
      onSaved({ id: null, name: clean, data: payload });
      return;
    }
    setBusy(true);
    try {
      if (initial?.id) {
        await builds.update(initial.id, { name: clean, data: payload });
        onSaved({ id: initial.id, name: clean, data: payload });
      } else {
        const r = await builds.create({ kind: 'wod', name: clean, character_id: characterId, data: payload });
        onSaved({ id: r.id, name: clean, data: payload });
      }
    } catch (e) {
      setErr(e instanceof ApiError && e.code === 'build_limit' ? t('hs_build_limit') : t('ha_err_generic'));
    } finally {
      setBusy(false);
    }
  };

  return (
    <Modal
      title={initial?.id ? t('hs_wod_edit') : t('hs_wod_new')}
      onClose={onClose}
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
        <input value={name} onChange={(e) => setName(e.target.value)} maxLength={80} placeholder={t('hs_wod_name_ph')} />
      </label>
      <label className="field">
        <span>{t('hs_wod_code')}</span>
        <input
          value={data.code ?? ''}
          onChange={(e) => setData((d) => ({ ...d, code: e.target.value }))}
          maxLength={600}
          spellCheck={false}
          placeholder="D0Y2BgYJh2Akh4…"
        />
        <small className="muted">{t('hs_wod_code_hint')}</small>
      </label>

      <h4>
        {t('hs_wod_points')} <span className="muted small">({totalPoints})</span>
      </h4>
      <div className="wod-domains">
        {WOD_DOMAINS.map((d) => (
          <label key={d} className="field">
            <span>
              {DOMAIN_ICON[d]} {t(`hs_wod_${d}`)}
            </span>
            <input
              inputMode="numeric"
              value={data.points[d] ?? ''}
              onChange={(e) => {
                const v = Math.min(1000, Number(e.target.value.replace(/\D/g, '')) || 0);
                setData((x) => ({ ...x, points: { ...x.points, [d]: v } }));
              }}
            />
          </label>
        ))}
      </div>

      <h4>{t('hs_wod_revelations')}</h4>
      {!vocation && <p className="muted small">{t('hs_wod_pick_voc')}</p>}
      <div className="wod-perks">
        {[...perks, ...extraPerks].map((p) => (
          <div key={p} className="wod-perk">
            <span>{p}</span>
            <div className="seg" role="group" aria-label={p}>
              {[0, 1, 2, 3].map((s) => (
                <button
                  key={s}
                  type="button"
                  className={(data.revelations[p] ?? 0) === s ? 'active' : ''}
                  onClick={() =>
                    setData((x) => {
                      const r = { ...x.revelations };
                      if (s) r[p] = s;
                      else delete r[p];
                      return { ...x, revelations: r };
                    })
                  }
                >
                  {s ? `${t('hs_stage')} ${s}` : '—'}
                </button>
              ))}
            </div>
          </div>
        ))}
      </div>
      {vocation === 'monk' && <p className="muted small">{t('hs_wod_monk_note')}</p>}

      <label className="field">
        <span>{t('hs_wod_gems')}</span>
        <input value={data.gems ?? ''} onChange={(e) => setData((d) => ({ ...d, gems: e.target.value }))} maxLength={300} placeholder={t('hs_wod_gems_ph')} />
      </label>
      <label className="field">
        <span>{t('ha_notes')}</span>
        <textarea rows={2} value={data.notes ?? ''} onChange={(e) => setData((d) => ({ ...d, notes: e.target.value }))} maxLength={500} />
      </label>
    </Modal>
  );
}
