import { useState } from 'react'
import { useNavigate, Link } from 'react-router-dom'
import { authApi } from '@/services/api'

export default function RegisterPage() {
  const navigate = useNavigate()
  const [form, setForm] = useState({ name: '', email: '', password: '', role: 'breeder' })
  const [accepted, setAccepted] = useState(false)
  const [error, setError] = useState('')
  const [loading, setLoading] = useState(false)

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault()
    if (!accepted) { setError('Você precisa aceitar os Termos de Serviço e a Política de Privacidade para continuar.'); return }
    setLoading(true)
    setError('')
    try {
      await authApi.register(form)
      navigate('/login')
    } catch (err: unknown) {
      const msg = (err as { response?: { data?: { detail?: string } } }).response?.data?.detail
      setError(msg || 'Erro ao criar conta')
    } finally {
      setLoading(false)
    }
  }

  return (
    <div style={{ minHeight: '100vh', background: '#1a3d2b', display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
      <div style={{ background: '#faf6f0', borderRadius: 20, padding: 40, width: '100%', maxWidth: 440, boxShadow: '0 20px 60px rgba(0,0,0,0.3)' }}>
        <div style={{ textAlign: 'center', marginBottom: 28 }}>
          <span style={{ fontSize: 36 }}>🪶</span>
          <h1 style={{ fontFamily: "'Cormorant Garamond',serif", fontSize: 28, fontWeight: 700, color: '#1a3d2b', margin: '8px 0 4px' }}>Criar Conta</h1>
          <p style={{ fontSize: 12, color: '#6b8a76' }}>Plumora Gen — Gestão de Aves</p>
        </div>
        <form onSubmit={handleSubmit}>
          {error && <div style={{ background: '#fdf0ed', border: '1px solid #c45c3d', borderRadius: 8, padding: '10px 14px', color: '#c45c3d', fontSize: 12, marginBottom: 16 }}>{error}</div>}
          {(['name', 'email', 'password'] as const).map(field => (
            <div key={field} style={{ marginBottom: 14 }}>
              <label style={{ display: 'block', fontSize: 11, fontWeight: 600, color: '#4a6a54', marginBottom: 5 }}>
                {field === 'name' ? 'Nome completo' : field === 'email' ? 'Email' : 'Senha'}
              </label>
              <input
                type={field === 'password' ? 'password' : field === 'email' ? 'email' : 'text'}
                value={form[field]}
                onChange={e => setForm(f => ({ ...f, [field]: e.target.value }))}
                required
                style={{ width: '100%', border: '1px solid #c8d4c8', borderRadius: 8, padding: '10px 12px', fontSize: 14, color: '#1e2620', background: '#fff', outline: 'none' }}
              />
            </div>
          ))}
          <div style={{ marginBottom: 22 }}>
            <label style={{ display: 'block', fontSize: 11, fontWeight: 600, color: '#4a6a54', marginBottom: 5 }}>Perfil</label>
            <select value={form.role} onChange={e => setForm(f => ({ ...f, role: e.target.value }))} style={{ width: '100%', border: '1px solid #c8d4c8', borderRadius: 8, padding: '10px 12px', fontSize: 14, color: '#1e2620', background: '#fff', outline: 'none' }}>
              <option value="breeder">Criador de Aves</option>
              <option value="vet">Médico Veterinário</option>
            </select>
          </div>
          <div style={{ marginBottom: 20, display: 'flex', alignItems: 'flex-start', gap: 10 }}>
            <input
              type="checkbox"
              id="terms"
              checked={accepted}
              onChange={e => setAccepted(e.target.checked)}
              style={{ marginTop: 2, cursor: 'pointer', accentColor: '#2d6a4f', width: 16, height: 16, flexShrink: 0 }}
            />
            <label htmlFor="terms" style={{ fontSize: 12, color: '#4a6a54', lineHeight: 1.6, cursor: 'pointer' }}>
              Li e aceito os{' '}
              <Link to="/termos" target="_blank" style={{ color: '#2d6a4f', fontWeight: 600 }}>Termos de Serviço</Link>
              {' '}e a{' '}
              <Link to="/privacidade" target="_blank" style={{ color: '#2d6a4f', fontWeight: 600 }}>Política de Privacidade</Link>
              {' '}da CSVET Editora, Cursos e Treinamentos. Estou ciente do tratamento de dados conforme a LGPD.
            </label>
          </div>
          <button type="submit" disabled={loading || !accepted} style={{ width: '100%', background: '#2d6a4f', border: 'none', borderRadius: 10, color: '#faf6f0', fontSize: 14, fontWeight: 700, padding: '12px 0', cursor: accepted ? 'pointer' : 'not-allowed', opacity: accepted ? 1 : 0.6 }}>
            {loading ? 'Criando...' : 'Criar Conta'}
          </button>
        </form>
        <p style={{ textAlign: 'center', fontSize: 12, color: '#7a9a84', marginTop: 18 }}>
          Já tem conta? <Link to="/login" style={{ color: '#2d6a4f', fontWeight: 600 }}>Entrar</Link>
        </p>
      </div>
    </div>
  )
}
