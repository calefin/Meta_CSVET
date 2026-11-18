/**
 * Creature sprite with fallbacks (D-57): the URL resolved by the API (official CipSoft library or TibiaWiki),
 * then the official library by name, then an optional extra (e.g. the soul core item). Hidden when none loads.
 */
import { useEffect, useMemo, useState } from 'react';
import { findCreature, loadCreatures } from '../core/creatures';
import { wikiImage } from '../core/sprites';

export const libraryImage = (name: string) => `https://static.tibia.com/images/library/${name.toLowerCase().replace(/[^a-z]/g, '')}.gif`;

export function CreatureImage({ name, url, fallback, size = 48 }: { name: string; url?: string | null; fallback?: string; size?: number }) {
  const [wiki, setWiki] = useState<string | null>(null);
  const urls = useMemo(() => [...new Set([url, libraryImage(name), fallback, wiki].filter((u): u is string => !!u))], [name, url, fallback, wiki]);
  const [i, setI] = useState(0);
  useEffect(() => {
    setI(0);
    setWiki(null);
  }, [name, url, fallback]);
  // D-82: only when every known address failed, look up the sprite kept in the catalog (loaded on demand)
  const exhausted = i >= urls.length;
  useEffect(() => {
    if (!exhausted || wiki) return;
    let alive = true;
    void loadCreatures().then((index) => {
      const img = findCreature(index, name)?.img;
      if (alive && img) setWiki(wikiImage(`https://static.wikia.nocookie.net/tibia/images/${img}`));
    });
    return () => {
      alive = false;
    };
  }, [exhausted, wiki, name]);
  if (i >= urls.length) return <span className="creature-img-empty" style={{ width: size, height: size }} aria-hidden="true" />;
  return (
    <img
      key={urls[i]}
      src={urls[i]}
      alt=""
      width={size}
      height={size}
      loading="lazy"
      decoding="async"
      referrerPolicy="no-referrer"
      style={{ objectFit: 'contain', imageRendering: 'pixelated' }}
      onError={() => setI((n) => n + 1)}
      // TibiaWiki answers some blocked files with a 300×171 placeholder (status 200)
      onLoad={(e) => {
        const { naturalWidth: w, naturalHeight: h } = e.currentTarget;
        if (w === 300 && h === 171) setI((n) => n + 1);
      }}
    />
  );
}
