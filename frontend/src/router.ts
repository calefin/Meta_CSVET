import { useEffect, useState } from 'react';

/** Minimal History-API router (D-01). Keeps ?lang= (and only that) when navigating. */
export function navigate(to: string): void {
  if (to === window.location.pathname) return;
  const lang = new URLSearchParams(window.location.search).get('lang');
  const sep = to.includes('?') ? '&' : '?';
  window.history.pushState({}, '', to + (lang ? `${sep}lang=${encodeURIComponent(lang)}` : ''));
  window.dispatchEvent(new PopStateEvent('popstate'));
  window.scrollTo(0, 0);
}

export function usePathname(): string {
  const [path, setPath] = useState(window.location.pathname);
  useEffect(() => {
    const onPop = () => setPath(window.location.pathname);
    window.addEventListener('popstate', onPop);
    return () => window.removeEventListener('popstate', onPop);
  }, []);
  return path;
}

export function linkHandler(to: string) {
  return (e: React.MouseEvent) => {
    if (e.metaKey || e.ctrlKey || e.shiftKey || e.button !== 0) return;
    e.preventDefault();
    navigate(to);
  };
}
