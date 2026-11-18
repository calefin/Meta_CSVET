/** PWA glue (D-21): service worker registration, update prompt, install prompt, online status. */
import { useEffect, useState } from 'react';

interface BeforeInstallPromptEvent extends Event {
  prompt: () => Promise<void>;
  userChoice: Promise<{ outcome: 'accepted' | 'dismissed' }>;
}

type Listener = () => void;
const listeners = new Set<Listener>();
const notify = () => listeners.forEach((l) => l());

let waitingWorker: ServiceWorker | null = null;
let installEvent: BeforeInstallPromptEvent | null = null;

export function registerServiceWorker(): void {
  if (!('serviceWorker' in navigator) || !import.meta.env.PROD) return;

  window.addEventListener('beforeinstallprompt', (e) => {
    e.preventDefault(); // show our own button instead of the mini-infobar
    installEvent = e as BeforeInstallPromptEvent;
    notify();
  });
  window.addEventListener('appinstalled', () => {
    installEvent = null;
    notify();
  });

  window.addEventListener('load', async () => {
    try {
      const reg = await navigator.serviceWorker.register('/sw.js');
      const track = (worker: ServiceWorker | null) => {
        if (!worker) return;
        worker.addEventListener('statechange', () => {
          // A new version is ready while an old one controls the page
          if (worker.state === 'installed' && navigator.serviceWorker.controller) {
            waitingWorker = worker;
            notify();
          }
        });
      };
      if (reg.waiting && navigator.serviceWorker.controller) {
        waitingWorker = reg.waiting;
        notify();
      }
      reg.addEventListener('updatefound', () => track(reg.installing));
      // Long-lived installed apps: look for updates every hour
      window.setInterval(() => reg.update().catch(() => {}), 60 * 60 * 1000);
    } catch {
      /* SW is an enhancement only */
    }
  });

  // Reload only when an *update* takes over. On the very first visit the new worker claims the page too
  // (controller goes from null to the worker): reloading then wiped whatever the visitor had just pasted.
  const hadController = !!navigator.serviceWorker.controller;
  let reloading = false;
  navigator.serviceWorker.addEventListener('controllerchange', () => {
    if (reloading || !hadController) return;
    reloading = true;
    window.location.reload();
  });
}

export function applyUpdate(): void {
  waitingWorker?.postMessage('SKIP_WAITING');
}

export async function promptInstall(): Promise<void> {
  if (!installEvent) return;
  await installEvent.prompt();
  await installEvent.userChoice.catch(() => undefined);
  installEvent = null;
  notify();
}

export function isStandalone(): boolean {
  return (
    window.matchMedia?.('(display-mode: standalone)').matches ||
    (navigator as Navigator & { standalone?: boolean }).standalone === true
  );
}

/** Re-renders when update/install availability or connectivity changes. */
export function usePwa() {
  const [, force] = useState(0);
  const [online, setOnline] = useState(navigator.onLine);

  useEffect(() => {
    const l = () => force((n) => n + 1);
    listeners.add(l);
    const on = () => setOnline(true);
    const off = () => setOnline(false);
    window.addEventListener('online', on);
    window.addEventListener('offline', off);
    return () => {
      listeners.delete(l);
      window.removeEventListener('online', on);
      window.removeEventListener('offline', off);
    };
  }, []);

  return {
    online,
    updateReady: waitingWorker !== null,
    canInstall: installEvent !== null && !isStandalone(),
  };
}
