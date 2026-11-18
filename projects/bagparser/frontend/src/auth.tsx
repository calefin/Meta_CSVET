/** Logged-in user state (D-24). The session lives in an HttpOnly cookie; this only mirrors /api/auth/me. */
import { createContext, useCallback, useContext, useEffect, useMemo, useRef, useState, type ReactNode } from 'react';
import { auth, type User } from './api';

/** Browser data of the signed-in person (not the guest data waiting to be imported) */
const PERSONAL_LOCAL = ['bagparser_last_log', 'bagparser_soulcores_char'];
const PERSONAL_SESSION = ['bp_verify_bar'];

/**
 * D-74: another account signed in (or signed out) — forget what belonged to the previous one: the API
 * responses kept by the service worker, personal keys in storage, and (via `accountKey`) every page's state.
 */
export function clearAccountData(): void {
  for (const k of PERSONAL_LOCAL) {
    try {
      localStorage.removeItem(k);
    } catch {
      /* storage blocked */
    }
  }
  for (const k of PERSONAL_SESSION) {
    try {
      sessionStorage.removeItem(k);
    } catch {
      /* storage blocked */
    }
  }
  try {
    navigator.serviceWorker?.controller?.postMessage('CLEAR_API');
  } catch {
    /* no service worker */
  }
  if (typeof caches !== 'undefined') {
    void caches
      .keys()
      .then((keys) => Promise.all(keys.filter((k) => k.startsWith('bagparser-api-')).map((k) => caches.delete(k))))
      .catch(() => undefined);
  }
}

interface AuthValue {
  /** undefined = still loading, null = signed out */
  user: User | null | undefined;
  setUser: (u: User | null) => void;
  refresh: () => Promise<void>;
  logout: () => Promise<void>;
  /** Changes when another account signs in or out: pages are keyed by it so no state leaks between accounts */
  accountKey: string;
}

const AuthContext = createContext<AuthValue | null>(null);

export function AuthProvider({ children }: { children: ReactNode }) {
  const [user, setUser] = useState<User | null | undefined>(undefined);
  const [accountKey, setAccountKey] = useState('start');
  const lastId = useRef<number | null | undefined>(undefined);
  useEffect(() => {
    if (user === undefined) return;
    const id = user?.id ?? null;
    if (lastId.current !== undefined && lastId.current !== id) {
      clearAccountData();
      setAccountKey(`acc-${id ?? 'guest'}-${Date.now()}`);
    }
    lastId.current = id;
  }, [user]);

  const refresh = useCallback(async () => {
    try {
      setUser((await auth.me()).user);
    } catch {
      setUser(null); // offline or API down: act as signed out
    }
  }, []);

  const logout = useCallback(async () => {
    await auth.logout().catch(() => undefined);
    setUser(null);
  }, []);

  useEffect(() => {
    void refresh();
  }, [refresh]);

  const value = useMemo(() => ({ user, setUser, refresh, logout, accountKey }), [user, refresh, logout, accountKey]);
  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>;
}

export function useAuth(): AuthValue {
  const ctx = useContext(AuthContext);
  if (!ctx) throw new Error('useAuth outside AuthProvider');
  return ctx;
}
