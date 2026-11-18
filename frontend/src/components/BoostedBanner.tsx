import { CreatureLink } from './CreatureLink';
import { useEffect, useState } from 'react';
import { api, type Boosted } from '../api';
import { useI18n } from '../i18n';

/** FR-TD-01 / FR-TD-02: today's boosted creature and/or boss (from TibiaData via our API). */
export function BoostedBanner({ only }: { only?: 'creature' | 'boss' }) {
  const { t } = useI18n();
  const [data, setData] = useState<Boosted | null>(null);

  useEffect(() => {
    let alive = true;
    api
      .boosted()
      .then((d) => alive && setData(d))
      .catch(() => {
        /* banner is optional: hide on error */
      });
    return () => {
      alive = false;
    };
  }, []);

  if (!data) return null;

  const entries: { label: string; name: string; image: string }[] = [];
  if (only !== 'boss' && data.creature) {
    entries.push({ label: t('boosted_creature'), name: data.creature.name, image: data.creature.image_url });
  }
  if (only !== 'creature' && data.boss) {
    entries.push({ label: t('boosted_boss'), name: data.boss.name, image: data.boss.image_url });
  }
  if (entries.length === 0) return null;

  return (
    <div className="boosted-banner">
      {entries.map((e) => (
        <div key={e.label} className="boosted-entry">
          {e.image && <img src={e.image} alt="" width={32} height={32} loading="lazy" />}
          <div>
            <div className="muted small">{e.label}</div>
            <strong>
              <CreatureLink name={e.name} />
            </strong>
          </div>
        </div>
      ))}
    </div>
  );
}
