import { Outlet, useNavigate, useLocation } from 'react-router-dom'
import { useTranslation } from 'react-i18next'
import { useUIStore } from '@/store/ui'
import { useAuthStore } from '@/store/auth'
import i18n from '@/i18n'

const NAV_ITEMS = [
  { key: 'dashboard',   icon: '⊞', path: '/app/dashboard' },
  { key: 'birds',       icon: '🐦', path: '/app/birds' },
  { key: 'breeding',    icon: '🥚', path: '/app/breeding' },
  { key: 'cages',       icon: '🏠', path: '/app/cages' },
  { key: 'genetics',    icon: '🧬', path: '/app/genetics' },
  { key: 'health',      icon: '❤️', path: '/app/health' },
  { key: 'vaccines',    icon: '💉', path: '/app/vaccines' },
  { key: 'medications', icon: '💊', path: '/app/medications' },
  { key: 'feed',        icon: '🌾', path: '/app/feed' },
  { key: 'races',       icon: '🏆', path: '/app/races' },
  { key: 'pedigree',    icon: '🌳', path: '/app/pedigree' },
  { key: 'rings',       icon: '💍', path: '/app/rings' },
  { key: 'profile',     icon: '👤', path: '/app/profile' },
]

export default function Layout() {
  const { t } = useTranslation()
  const { locale, setLocale } = useUIStore()
  const { user, logout } = useAuthStore()
  const navigate = useNavigate()
  const location = useLocation()

  const handleLangToggle = () => {
    const next = locale === 'pt-BR' ? 'en-US' : 'pt-BR'
    setLocale(next)
    i18n.changeLanguage(next)
  }

  const handleLogout = () => {
    logout()
    navigate('/login')
  }

  const initials = user?.name
    ? user.name.split(' ').slice(0, 2).map(n => n[0]).join('').toUpperCase()
    : 'P'

  return (
    <div style={{ fontFamily: "'DM Sans', system-ui, sans-serif", background: '#faf6f0', minHeight: '100vh', display: 'flex', flexDirection: 'column' }}>
      {/* ── TOP BAR ── */}
      <header style={{ background: '#1a3d2b', borderBottom: '2px solid #c8922a', padding: '0 24px', height: 58, display: 'flex', alignItems: 'center', justifyContent: 'space-between', position: 'sticky', top: 0, zIndex: 100, boxShadow: '0 2px 16px rgba(0,0,0,0.2)' }}>
        <div style={{ display: 'flex', alignItems: 'center', gap: 12 }}>
          <span style={{ fontSize: 26 }}>🪶</span>
          <div>
            <span style={{ fontFamily: "'Cormorant Garamond',serif", fontSize: 22, fontWeight: 700, color: '#faf6f0', letterSpacing: '0.04em' }}>Plumora</span>
            <span style={{ display: 'block', fontSize: 9, color: '#c8922a', letterSpacing: '0.18em', textTransform: 'uppercase', marginTop: -2 }}>Gen · {t('subtitle')}</span>
          </div>
        </div>

        <div style={{ flex: 1, maxWidth: 380, margin: '0 32px', background: 'rgba(255,255,255,0.08)', border: '1px solid rgba(200,146,42,0.3)', borderRadius: 8, display: 'flex', alignItems: 'center', padding: '0 14px', gap: 8 }}>
          <span style={{ color: '#c8922a', fontSize: 13 }}>🔍</span>
          <input placeholder={t('search')} style={{ background: 'none', border: 'none', outline: 'none', color: '#faf6f0', fontSize: 13, width: '100%' }} />
        </div>

        <div style={{ display: 'flex', alignItems: 'center', gap: 16 }}>
          <button onClick={handleLangToggle} style={{ background: 'rgba(200,146,42,0.15)', border: '1px solid #c8922a', borderRadius: 6, color: '#c8922a', fontSize: 11, fontWeight: 600, padding: '4px 10px', cursor: 'pointer', letterSpacing: '0.1em' }}>
            {locale === 'pt-BR' ? 'EN' : 'PT'}
          </button>
          <div style={{ position: 'relative', cursor: 'pointer' }}>
            <span style={{ fontSize: 18 }}>🔔</span>
            <span style={{ position: 'absolute', top: -4, right: -4, background: '#c45c3d', color: '#fff', fontSize: 9, fontWeight: 700, borderRadius: 8, padding: '1px 4px' }}>3</span>
          </div>
          <div style={{ background: 'rgba(200,146,42,0.2)', border: '1px solid #c8922a', borderRadius: 6, padding: '3px 10px', fontSize: 11, color: '#c8922a', fontWeight: 600 }}>
            FREE · {t('plan.birds_limit')}
          </div>
          <div onClick={handleLogout} title="Logout" style={{ width: 34, height: 34, borderRadius: '50%', background: 'linear-gradient(135deg,#2d6a4f,#c8922a)', display: 'flex', alignItems: 'center', justifyContent: 'center', color: '#fff', fontSize: 13, fontWeight: 700, cursor: 'pointer', border: '2px solid #c8922a' }}>
            {initials}
          </div>
        </div>
      </header>

      <div style={{ display: 'flex', flex: 1 }}>
        {/* ── SIDEBAR ── */}
        <aside style={{ width: 200, background: '#1e2620', borderRight: '1px solid rgba(200,146,42,0.15)', display: 'flex', flexDirection: 'column', position: 'sticky', top: 58, height: 'calc(100vh - 58px)', overflowY: 'auto', paddingTop: 16 }}>
          {NAV_ITEMS.map(item => {
            const active = location.pathname.startsWith(item.path)
            return (
              <button key={item.key} onClick={() => navigate(item.path)} style={{ display: 'flex', alignItems: 'center', gap: 10, padding: '10px 18px', border: 'none', cursor: 'pointer', textAlign: 'left', fontSize: 13, fontWeight: 500, background: active ? 'rgba(200,146,42,0.15)' : 'none', color: active ? '#c8922a' : '#9ab5a0', borderLeft: active ? '3px solid #c8922a' : '3px solid transparent', transition: 'all 0.15s' }}>
                <span style={{ fontSize: 15 }}>{item.icon}</span>
                {t(`nav.${item.key}`)}
              </button>
            )
          })}

          {/* PigeonVet special */}
          <div style={{ margin: '12px 12px 0', padding: 12, background: 'linear-gradient(135deg,rgba(196,92,61,0.2),rgba(160,58,34,0.1))', border: '1px solid rgba(196,92,61,0.4)', borderRadius: 10 }}>
            <div style={{ display: 'flex', alignItems: 'center', gap: 6, marginBottom: 4 }}>
              <span style={{ fontSize: 14 }}>⚕️</span>
              <span style={{ color: '#e87a5a', fontSize: 12, fontWeight: 700, letterSpacing: '0.05em' }}>{t('nav.pigeonvet')}</span>
            </div>
            <div style={{ fontSize: 9, color: 'rgba(232,122,90,0.7)', lineHeight: 1.4 }}>{t('vet.by')}</div>
            {(user?.role === 'vet' || user?.role === 'admin') && (
              <button onClick={() => navigate('/app/vet')} style={{ marginTop: 8, width: '100%', background: '#c45c3d', border: 'none', borderRadius: 6, color: '#fff', fontSize: 10, fontWeight: 600, padding: '5px 0', cursor: 'pointer' }}>
                {t('vet.queue')}
              </button>
            )}
            {user?.role === 'breeder' && (
              <button onClick={() => navigate('/app/health')} style={{ marginTop: 8, width: '100%', background: '#c45c3d', border: 'none', borderRadius: 6, color: '#fff', fontSize: 10, fontWeight: 600, padding: '5px 0', cursor: 'pointer' }}>
                {t('vet.consult')}
              </button>
            )}
          </div>

          {/* Upgrade */}
          <div style={{ margin: 'auto 12px 16px', marginTop: 20 }}>
            <button style={{ width: '100%', background: 'linear-gradient(135deg,#c8922a,#a87520)', border: 'none', borderRadius: 8, color: '#fff', fontSize: 11, fontWeight: 700, padding: '9px 0', cursor: 'pointer', letterSpacing: '0.05em' }}>
              {t('plan.upgrade')} →
            </button>
          </div>
        </aside>

        {/* ── MAIN ── */}
        <main style={{ flex: 1, padding: '28px 28px 40px', overflowY: 'auto' }}>
          <Outlet />
        </main>
      </div>

      {/* ── FOOTER ── */}
      <footer style={{ background: '#1a3d2b', borderTop: '1px solid rgba(200,146,42,0.3)', padding: '12px 28px', display: 'flex', alignItems: 'center', justifyContent: 'space-between' }}>
        <div style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
          <span style={{ fontSize: 16 }}>🪶</span>
          <span style={{ fontFamily: "'Cormorant Garamond',serif", fontSize: 14, color: 'rgba(250,246,240,0.5)' }}>Plumora Gen © 2026</span>
        </div>
        <div style={{ textAlign: 'center' }}>
          <div style={{ fontSize: 11, color: '#c8922a', fontWeight: 600, letterSpacing: '0.04em' }}>{t('credit')}</div>
          <div style={{ fontSize: 9, color: 'rgba(200,146,42,0.6)', marginTop: 1 }}>{t('credit_sub')} · profatilio.com.br</div>
        </div>
        <div style={{ fontSize: 10, color: 'rgba(154,181,160,0.5)' }}>{locale} · v4.0</div>
      </footer>
    </div>
  )
}
