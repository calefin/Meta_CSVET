/** Item image in a fixed square, with a neutral placeholder when there is no sprite (D-35). */
import { useEffect, useState } from 'react';
import { loadSpriteManifest, spriteUrls, type SpriteIndex } from '../core/sprites';

export function useSprites(): SpriteIndex | null {
  const [set, setSet] = useState<SpriteIndex | null>(null);
  useEffect(() => {
    let alive = true;
    void loadSpriteManifest().then((s) => alive && setSet(s));
    return () => {
      alive = false;
    };
  }, []);
  return set;
}

/** Tibia item sprites are square, 32 or 64 px (some wiki GIFs are 64×64). */
function looksLikeSprite(img: HTMLImageElement): boolean {
  const { naturalWidth: w, naturalHeight: h } = img;
  return w > 0 && w <= 64 && h <= 64 && Math.abs(w - h) <= 4;
}

export function ItemSprite({ name, sprites, size = 32 }: { name: string; sprites: SpriteIndex | null; size?: number }) {
  const urls = spriteUrls(name, sprites);
  const [attempt, setAttempt] = useState(0);
  useEffect(() => setAttempt(0), [name]);
  const url = urls[attempt];
  return (
    <span className="sprite" style={{ width: size, height: size }} aria-hidden="true">
      {url && (
        <img
          key={url}
          src={url}
          alt=""
          width={size}
          height={size}
          loading="lazy"
          decoding="async"
          referrerPolicy="no-referrer"
          // Official image missing or blocked → try TibiaWiki; then leave the empty square
          onError={() => setAttempt((a) => a + 1)}
          // The Fandom CDN answers blocked/unknown files with a 300×171 placeholder (status 200), not an error
          onLoad={(e) => {
            if (!looksLikeSprite(e.currentTarget)) setAttempt((a) => a + 1);
          }}
        />
      )}
    </span>
  );
}
