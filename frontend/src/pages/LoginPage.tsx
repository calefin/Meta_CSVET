import { useState } from 'react'
import { useNavigate, Link } from 'react-router-dom'
import { useTranslation } from 'react-i18next'
import { authApi } from '@/services/api'
import { useAuthStore } from '@/store/auth'

export default function LoginPage() {
  const { t } = useTranslation()
  const navigate = useNavigate()
  const { setUser } = useAuthStore()
  const [email, setEmail] = useState('')
  const [password, setPassword] = useState('')
  const [error, setError] = useState('')
  const [loading, setLoading] = useState(false)

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault()
    setLoading(true)
    setError('')
    try {
      const { data: tokens } = await authApi.login(email, password)
      localStorage.setItem('plumora-access-token', tokens.access_token)
      localStorage.setItem('plumora-refresh-token', tokens.refresh_token)
      const { data: user } = await authApi.me()
      setUser(user)
      navigate('/app/dashboard')
    } catch {
      setError('Email ou senha inválidos')
    } finally {
      setLoading(false)
    }
  }

  return (
    <div style={{ minHeight: '100vh', background: '#1a3d2b', display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
      <div style={{ background: '#faf6f0', borderRadius: 20, padding: 40, width: '100%', maxWidth: 400, boxShadow: '0 20px 60px rgba(0,0,0,0.3)' }}>
        <div style={{ textAlign: 'center', marginBottom: 32 }}>
          <span style={{ fontSize: 40 }}>🪶</span>
          <h1 style={{ fontFamily: "'Cormorant Garamond',serif", fontSize: 32, fontWeight: 700, color: '#1a3d2b', margin: '8px 0 4px' }}>Plumora Gen</h1>
          <p style={{ fontSize: 12, color: '#6b8a76' }}>{t('subtitle')}</p>
        </div>
        <form onSubmit={handleSubmit}>
          {error && <div style={{ background: '#fdf0ed', border: '1px solid #c45c3d', borderRadius: 8, padding: '10px 14px', color: '#c45c3d', fontSize: 12, marginBottom: 16 }}>{error}</div>}
          <div style={{ marginBottom: 16 }}>
            <label style={{ display: 'block', fontSize: 11, fontWeight: 600, color: '#4a6a54', marginBottom: 5 }}>Email</label>
            <input type="email" value={email} onChange={e => setEmail(e.target.value)} required style={{ width: '100%', border: '1px solid #c8d4c8', borderRadius: 8, padding: '10px 12px', fontSize: 14, color: '#1e2620', background: '#fff', outline: 'none' }} />
          </div>
          <div style={{ marginBottom: 24 }}>
            <label style={{ display: 'block', fontSize: 11, fontWeight: 600, color: '#4a6a54', marginBottom: 5 }}>Senha</label>
            <input type="password" value={password} onChange={e => setPassword(e.target.value)} required style={{ width: '100%', border: '1px solid #c8d4c8', borderRadius: 8, padding: '10px 12px', fontSize: 14, color: '#1e2620', background: '#fff', outline: 'none' }} />
          </div>
          <button type="submit" disabled={loading} style={{ width: '100%', background: '#2d6a4f', border: 'none', borderRadius: 10, color: '#faf6f0', fontSize: 14, fontWeight: 700, padding: '12px 0', cursor: 'pointer', letterSpacing: '0.04em' }}>
            {loading ? 'Entrando...' : 'Entrar'}
          </button>
        </form>
        <p style={{ textAlign: 'center', fontSize: 12, color: '#7a9a84', marginTop: 20 }}>
          Não tem conta? <Link to="/register" style={{ color: '#2d6a4f', fontWeight: 600 }}>Criar conta</Link>
        </p>
      </div>
    </div>
  )
}
