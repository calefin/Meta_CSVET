import { useQuery } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import { useNavigate, Link } from 'react-router-dom'
import { useAuthStore } from '@/store/auth'
import { birdsApi, dashboardApi } from '@/services/api'
import type { Bird, DashboardSummary } from '@/types'

const SEVERITY_COLOR: Record<string, { bg: string; fg: string }> = {
  urgent: { bg: '#fdf0ed', fg: '#c45c3d' },
  attention: { bg: '#fdf4e8', fg: '#c8922a' },
  info: { bg: '#eef4f1', fg: '#4a6a54' },
}

const ALERT_LINK: Record<string, string> = {
  vaccine_overdue: '/app/vaccines',
  vaccine_upcoming: '/app/vaccines',
  health_severity: '/app/health',
  vet_pending: '/app/health',
  medication_withdrawal: '/app/medications',
}

const STATUS_LABELS: Record<string, string> = {
  paired: 'status_paired', incubating: 'status_incubating', hatched: 'status_hatched',
  failed: 'status_failed', completed: 'status_completed',
}

const statCard = (label: string, value: string | number, icon: string, color: string) => (
  <div key={label} style={{ background: '#fff', borderRadius: 14, border: '1px solid #e0e8e2', padding: '18px 20px', boxShadow: '0 2px 8px rgba(26,61,43,0.06)', display: 'flex', alignItems: 'center', gap: 14 }}>
    <div style={{ width: 44, height: 44, borderRadius: 12, background: `${color}18`, display: 'flex', alignItems: 'center', justifyContent: 'center', fontSize: 20 }}>{icon}</div>
    <div>
      <div style={{ fontSize: 26, fontWeight: 700, color, fontFamily: "'Cormorant Garamond',serif" }}>{value}</div>
      <div style={{ fontSize: 11, color: '#7a9a84', marginTop: 1 }}>{label}</div>
    </div>
  </div>
)

export default function DashboardPage() {
  const { t } = useTranslation()
  const { user } = useAuthStore()
  const navigate = useNavigate()

  const { data: birds = [] } = useQuery({ queryKey: ['birds'], queryFn: () => birdsApi.list().then(r => r.data as Bird[]) })
  const { data: summary } = useQuery({
    queryKey: ['dashboard-summary'],
    queryFn: () => dashboardApi.summary().then(r => r.data as DashboardSummary),
  })

  return (
    <div>
      {/* Welcome */}
      <div style={{ marginBottom: 28 }}>
        <h1 style={{ fontFamily: "'Cormorant Garamond',serif", fontSize: 30, fontWeight: 700, color: '#1a3d2b', letterSpacing: '0.02em' }}>
          {t('dashboard.welcome')} <span style={{ color: '#c8922a' }}>{user?.name?.split(' ')[0]}</span>
        </h1>
        <p style={{ color: '#6b8a76', fontSize: 13, marginTop: 4 }}>
          {new Date().toLocaleDateString('pt-BR', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' })}
        </p>
      </div>

      {/* Stats */}
      <div style={{ display: 'grid', gridTemplateColumns: 'repeat(4,1fr)', gap: 16, marginBottom: 28 }}>
        {statCard(t('dashboard.birds_total'), summary?.stats.active_birds ?? 0, '🐦', '#2d6a4f')}
        {statCard(t('dashboard.active_pairs'), summary?.stats.active_pairs ?? 0, '🥚', '#c8922a')}
        {statCard(t('nav.cages'), summary ? `${summary.stats.cages_free}/${summary.stats.cages_total}` : '0/0', '🏠', '#3d7a5f')}
        {statCard(t('vet.badge'), summary?.stats.pending_vet_consults ?? 0, '⚕️', '#c45c3d')}
      </div>

      {/* Two-column */}
      <div style={{ display: 'grid', gridTemplateColumns: '1fr 360px', gap: 20 }}>
        <div>
          {/* Alerts */}
          <div style={{ background: '#fff', borderRadius: 14, border: '1px solid #e0e8e2', padding: 18, boxShadow: '0 2px 8px rgba(26,61,43,0.05)', marginBottom: 20 }}>
            <div style={{ display: 'flex', alignItems: 'center', gap: 8, marginBottom: 14 }}>
              <span style={{ fontSize: 16 }}>⚠️</span>
              <span style={{ fontFamily: "'Cormorant Garamond',serif", fontSize: 18, fontWeight: 600, color: '#1a3d2b' }}>{t('dashboard.alerts')}</span>
            </div>
            {summary && summary.alerts.length === 0 && (
              <p style={{ fontSize: 13, color: '#4a6a54', textAlign: 'center', padding: '16px 0' }}>✓ Tudo em dia por aqui.</p>
            )}
            {summary?.alerts.map((a, i) => {
              const colors = SEVERITY_COLOR[a.severity] ?? SEVERITY_COLOR.info
              const link = ALERT_LINK[a.type] ?? '/app/dashboard'
              return (
                <Link key={i} to={link} style={{ textDecoration: 'none' }}>
                  <div style={{ display: 'flex', alignItems: 'center', gap: 12, padding: '10px 0', borderTop: i > 0 ? '1px solid #f0f4f1' : 'none' }}>
                    <div style={{ width: 8, height: 8, borderRadius: '50%', flexShrink: 0, background: colors.fg }} />
                    <div style={{ flex: 1, minWidth: 0 }}>
                      <div style={{ fontSize: 12, fontWeight: 500, color: '#1e2620' }}>{a.bird_name ? `${a.bird_name} — ` : ''}{a.message}</div>
                    </div>
                    <div style={{ fontSize: 9, fontWeight: 700, flexShrink: 0, color: colors.fg, background: colors.bg, borderRadius: 5, padding: '3px 8px', textTransform: 'uppercase' }}>
                      {a.severity}
                    </div>
                  </div>
                </Link>
              )
            })}
          </div>

          {/* Active breedings */}
          <div style={{ marginBottom: 20 }}>
            <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: 14 }}>
              <h2 style={{ fontFamily: "'Cormorant Garamond',serif", fontSize: 20, fontWeight: 600, color: '#1a3d2b' }}>{t('dashboard.active_breeding')}</h2>
              <button onClick={() => navigate('/app/breeding')} style={{ background: 'none', border: '1px solid #2d6a4f', borderRadius: 5, color: '#2d6a4f', fontSize: 10, fontWeight: 600, padding: '3px 8px', cursor: 'pointer' }}>Ver todos</button>
            </div>
            <div style={{ display: 'grid', gap: 10 }}>
              {summary?.active_breedings.map(b => (
                <Link key={b.id} to={`/app/breeding/${b.id}`} style={{ textDecoration: 'none' }}>
                  <div style={{ background: '#fff', borderRadius: 12, border: '1px solid #e0e8e2', padding: 14, display: 'flex', alignItems: 'center', justifyContent: 'space-between' }}>
                    <div>
                      <div style={{ fontSize: 13, fontWeight: 600, color: '#1e2620' }}>{b.male_name || '?'} × {b.female_name || '?'}</div>
                      <div style={{ fontSize: 11, color: '#7a9a84' }}>
                        {b.cage_name ? `🏠 ${b.cage_name}` : t('breeding.no_cage')}
                        {b.expected_hatch_date && ` · Eclosão prevista: ${new Date(b.expected_hatch_date).toLocaleDateString('pt-BR')}`}
                      </div>
                    </div>
                    <span style={{ background: '#e8f0e8', color: '#2d6a4f', borderRadius: 6, padding: '3px 8px', fontSize: 10, fontWeight: 600 }}>
                      {t(`breeding.${STATUS_LABELS[b.status] || b.status}`)}
                    </span>
                  </div>
                </Link>
              ))}
              {summary && summary.active_breedings.length === 0 && (
                <p style={{ fontSize: 12, color: '#9ab5a0', textAlign: 'center', padding: '12px 0' }}>Nenhum casal ativo no momento.</p>
              )}
            </div>
          </div>

          {/* Species breakdown */}
          {summary && summary.species_breakdown.length > 0 && (
            <div style={{ display: 'flex', gap: 8, flexWrap: 'wrap' }}>
              {summary.species_breakdown.map(s => (
                <span key={s.species_name} style={{ background: '#fff', border: '1px solid #e0e8e2', borderRadius: 20, padding: '6px 14px', fontSize: 12, color: '#1e2620' }}>
                  {s.species_name} <strong style={{ color: '#2d6a4f' }}>{s.count}</strong>
                </span>
              ))}
            </div>
          )}
        </div>

        {/* Right column */}
        <div style={{ display: 'flex', flexDirection: 'column', gap: 16 }}>
          {/* Bird cards */}
          <div>
            <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: 14 }}>
              <h2 style={{ fontFamily: "'Cormorant Garamond',serif", fontSize: 18, fontWeight: 600, color: '#1a3d2b' }}>{t('nav.birds')}</h2>
              <button onClick={() => navigate('/app/birds')} style={{ background: '#1a3d2b', border: 'none', borderRadius: 7, color: '#faf6f0', fontSize: 11, fontWeight: 600, padding: '6px 14px', cursor: 'pointer' }}>{t('birds.add')}</button>
            </div>
            <div style={{ display: 'grid', gap: 10 }}>
              {birds.slice(0, 4).map(bird => (
                <div key={bird.id} onClick={() => navigate('/app/birds')} style={{ background: '#fff', borderRadius: 14, border: '1px solid #e0e8e2', padding: 14, boxShadow: '0 2px 8px rgba(26,61,43,0.05)', cursor: 'pointer', display: 'flex', alignItems: 'center', gap: 12 }}>
                  <div style={{ width: 38, height: 38, borderRadius: 10, background: '#e8f0e8', fontSize: 18, display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
                    {bird.species?.category === 'pigeon' ? '🕊️' : '🦜'}
                  </div>
                  <div style={{ flex: 1, minWidth: 0 }}>
                    <div style={{ fontWeight: 600, color: '#1e2620', fontSize: 13 }}>{bird.name}</div>
                    <div style={{ fontSize: 10, color: '#7a9a84' }}>{bird.species?.common_name || '-'}</div>
                  </div>
                  <span style={{ background: bird.sex === 'M' ? '#ddeeff' : '#ffdde8', color: bird.sex === 'M' ? '#3366aa' : '#aa3366', fontSize: 9, fontWeight: 700, borderRadius: 4, padding: '2px 6px' }}>
                    {bird.sex === 'M' ? '♂ M' : bird.sex === 'F' ? '♀ F' : '?'}
                  </span>
                </div>
              ))}
              {birds.length === 0 && (
                <div style={{ textAlign: 'center', padding: 40, color: '#9ab5a0' }}>
                  <div style={{ fontSize: 36, marginBottom: 8 }}>🐦</div>
                  <p style={{ fontSize: 14 }}>Nenhuma ave cadastrada ainda.</p>
                  <button onClick={() => navigate('/app/birds')} style={{ marginTop: 12, background: '#2d6a4f', border: 'none', borderRadius: 8, color: '#fff', padding: '8px 20px', fontSize: 12, fontWeight: 600, cursor: 'pointer' }}>{t('birds.add')}</button>
                </div>
              )}
            </div>
          </div>

          {/* PigeonVet */}
          <div style={{ background: 'linear-gradient(135deg,#1e2620,#2a3020)', borderRadius: 14, padding: 18, border: '1px solid rgba(196,92,61,0.4)' }}>
            <div style={{ display: 'flex', alignItems: 'center', gap: 8, marginBottom: 6 }}>
              <span style={{ fontSize: 18 }}>⚕️</span>
              <span style={{ fontFamily: "'Cormorant Garamond',serif", fontSize: 18, fontWeight: 700, color: '#e87a5a' }}>{t('vet.badge')}</span>
            </div>
            <div style={{ fontSize: 10, color: 'rgba(232,122,90,0.8)', marginBottom: 10 }}>{t('vet.by')}</div>
            <div style={{ fontSize: 11, color: '#7a9a84', lineHeight: 1.5, marginBottom: 12 }}>
              {summary && summary.stats.pending_vet_consults > 0
                ? `Você tem ${summary.stats.pending_vet_consults} chamado(s) aguardando resposta.`
                : 'Suporte veterinário especializado em aves exóticas e pombos correio.'}
            </div>
            <button onClick={() => navigate('/app/health')} style={{ background: '#c45c3d', border: 'none', borderRadius: 7, color: '#fff', fontSize: 11, fontWeight: 700, padding: '8px 16px', cursor: 'pointer' }}>
              {t('vet.consult')} →
            </button>
          </div>
        </div>
      </div>
    </div>
  )
}
