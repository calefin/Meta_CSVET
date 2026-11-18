import { useQuery } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import { useNavigate } from 'react-router-dom'
import { adminApi } from '@/services/api'

export default function AdminDashboardPage() {
  const { t } = useTranslation()
  const navigate = useNavigate()
  const { data: stats } = useQuery({ queryKey: ['admin-stats'], queryFn: () => adminApi.dashboard().then(r => r.data) })
  const { data: users = [] } = useQuery({ queryKey: ['admin-users'], queryFn: () => adminApi.users().then(r => r.data as { id: string; name: string; email: string; role: string; is_active: boolean }[]) })

  return (
    <div>
      <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: 24 }}>
        <h1 style={{ fontFamily: "'Cormorant Garamond',serif", fontSize: 30, fontWeight: 700, color: '#1a3d2b' }}>Administração</h1>
        <button onClick={() => navigate('/app/admin/campaigns')} style={{ background: '#1a3d2b', border: 'none', borderRadius: 8, color: '#faf6f0', fontSize: 12, fontWeight: 700, padding: '9px 18px', cursor: 'pointer' }}>
          📧 Campanhas de E-mail →
        </button>
      </div>

      {stats && (
        <div style={{ display: 'grid', gridTemplateColumns: 'repeat(3,1fr)', gap: 16, marginBottom: 28 }}>
          {[
            { label: 'Usuários', value: stats.total_users, icon: '👤', color: '#2d6a4f' },
            { label: 'Aves', value: stats.total_birds, icon: '🐦', color: '#c8922a' },
            { label: 'Assinaturas ativas', value: stats.active_subscriptions, icon: '💳', color: '#c45c3d' },
          ].map(s => (
            <div key={s.label} style={{ background: '#fff', borderRadius: 14, border: '1px solid #e0e8e2', padding: '18px 20px', display: 'flex', alignItems: 'center', gap: 14 }}>
              <div style={{ width: 44, height: 44, borderRadius: 12, background: `${s.color}18`, display: 'flex', alignItems: 'center', justifyContent: 'center', fontSize: 20 }}>{s.icon}</div>
              <div>
                <div style={{ fontSize: 26, fontWeight: 700, color: s.color, fontFamily: "'Cormorant Garamond',serif" }}>{s.value}</div>
                <div style={{ fontSize: 11, color: '#7a9a84' }}>{s.label}</div>
              </div>
            </div>
          ))}
        </div>
      )}

      <div style={{ background: '#fff', borderRadius: 16, border: '1px solid #e0e8e2', padding: 24 }}>
        <h2 style={{ fontFamily: "'Cormorant Garamond',serif", fontSize: 20, color: '#1a3d2b', marginBottom: 16 }}>Usuários</h2>
        <table style={{ width: '100%', borderCollapse: 'collapse', fontSize: 12 }}>
          <thead>
            <tr style={{ background: '#f5f8f5' }}>
              <th style={{ padding: '10px 12px', textAlign: 'left', color: '#4a6a54', fontWeight: 600 }}>Nome</th>
              <th style={{ padding: '10px 12px', textAlign: 'left', color: '#4a6a54', fontWeight: 600 }}>Email</th>
              <th style={{ padding: '10px 12px', textAlign: 'left', color: '#4a6a54', fontWeight: 600 }}>Perfil</th>
              <th style={{ padding: '10px 12px', textAlign: 'left', color: '#4a6a54', fontWeight: 600 }}>Status</th>
            </tr>
          </thead>
          <tbody>
            {users.map(u => (
              <tr key={u.id} style={{ borderTop: '1px solid #f0f4f1' }}>
                <td style={{ padding: '10px 12px', fontWeight: 500 }}>{u.name}</td>
                <td style={{ padding: '10px 12px', color: '#7a9a84' }}>{u.email}</td>
                <td style={{ padding: '10px 12px' }}>
                  <span style={{ background: u.role === 'admin' ? '#1a3d2b' : u.role === 'vet' ? '#fdf0ed' : '#e8f0e8', color: u.role === 'admin' ? '#faf6f0' : u.role === 'vet' ? '#c45c3d' : '#2d6a4f', borderRadius: 4, padding: '2px 7px', fontSize: 10, fontWeight: 700 }}>
                    {u.role}
                  </span>
                </td>
                <td style={{ padding: '10px 12px' }}>
                  <span style={{ background: u.is_active ? '#e8f0e8' : '#f5f5f5', color: u.is_active ? '#2d6a4f' : '#9ab5a0', borderRadius: 4, padding: '2px 7px', fontSize: 10, fontWeight: 700 }}>
                    {u.is_active ? 'Ativo' : 'Inativo'}
                  </span>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    </div>
  )
}
