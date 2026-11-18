import { useEffect, useRef } from 'react';

declare global {
  interface Window {
    turnstile?: {
      render: (
        container: HTMLElement,
        options: {
          sitekey: string;
          action?: string;
          theme?: 'light' | 'dark' | 'auto';
          callback?: (token: string) => void;
          'error-callback'?: () => void;
          'expired-callback'?: () => void;
        },
      ) => string;
      reset: (widgetId?: string) => void;
      remove: (widgetId?: string) => void;
    };
    onTurnstileLoaded?: () => void;
  }
}

export const TURNSTILE_SITE_KEY = '0x4AAAAAAF1L8qttgfKwIY_H';

let scriptLoaded = false;
let scriptLoading = false;
const readyCallbacks: (() => void)[] = [];

function loadTurnstileScript(onReady: () => void) {
  if (window.turnstile) {
    onReady();
    return;
  }
  readyCallbacks.push(onReady);
  if (scriptLoaded || scriptLoading) return;
  scriptLoading = true;

  window.onTurnstileLoaded = () => {
    scriptLoaded = true;
    scriptLoading = false;
    readyCallbacks.forEach((cb) => cb());
    readyCallbacks.length = 0;
  };

  const script = document.createElement('script');
  script.src = 'https://challenges.cloudflare.com/turnstile/v0/api.js?onload=onTurnstileLoaded&render=explicit';
  script.async = true;
  script.defer = true;
  document.head.appendChild(script);
}

export function TurnstileWidget({
  action,
  onVerify,
  onExpire,
}: {
  action: string;
  onVerify: (token: string) => void;
  onExpire?: () => void;
}) {
  const containerRef = useRef<HTMLDivElement | null>(null);
  const widgetIdRef = useRef<string | null>(null);

  useEffect(() => {
    let active = true;

    loadTurnstileScript(() => {
      if (!active || !containerRef.current || !window.turnstile) return;
      if (widgetIdRef.current) {
        try {
          window.turnstile.remove(widgetIdRef.current);
        } catch {}
      }
      try {
        widgetIdRef.current = window.turnstile.render(containerRef.current, {
          sitekey: TURNSTILE_SITE_KEY,
          action,
          theme: 'auto',
          callback: (token: string) => {
            if (active) onVerify(token);
          },
          'expired-callback': () => {
            if (active && onExpire) onExpire();
          },
          'error-callback': () => {
            if (active && onExpire) onExpire();
          },
        });
      } catch {
        // ignore render error
      }
    });

    return () => {
      active = false;
      if (widgetIdRef.current && window.turnstile) {
        try {
          window.turnstile.remove(widgetIdRef.current);
        } catch {}
        widgetIdRef.current = null;
      }
    };
  }, [action]);

  return (
    <div style={{ margin: '1rem 0', minHeight: '65px', display: 'flex', justifyContent: 'center' }}>
      <div ref={containerRef} />
    </div>
  );
}
