import { useState } from 'react'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { useAuthStore } from '@/store/auth'
import api from '@/services/api'

const BR_STATES = ['AC','AL','AP','AM','BA','CE','DF','ES','GO','MA','MT','MS','MG','PA','PB','PR','PE','PI','RJ','RN','RS','RO','RR','SC','SP','SE','TO']

const INPUT_STYLE = {
  width: '100%', border: '1px solid #c8d4c8', borderRadius: 8, padding: '9px 12px',
  fontSize: 13, color: '#1e2620', background: '#fafcfa', outline: 'none', boxSizing: 'border-box' as const,
}
const LABEL_STYLE = { display: 'block', fontSize: 11, fontWeight: 600 as const, color: '#4a6a54', marginBottom: 5 }
const SECTION_STYLE = { background: '#fff', borderRadius: 16, border: '1px solid #e0e8e2', padding: 24, marginBottom: 20, boxShadow: '0 2px 12px rgba(26,61,43,0.06)' }

function formatCpf(v: string) {
  const d = v.replace(/\D/g, '').slice(0, 11)
  if (d.length <= 3) return d
  if (d.length <= 6) return `${d.slice(0,3)}.${d.slice(3)}`
  if (d.length <= 9) return `${d.slice(0,3)}.${d.slice(3,6)}.${d.slice(6)}`
  return `${d.slice(0,3)}.${d.slice(3,6)}.${d.slice(6,9)}-${d.slice(9)}`
}

export default function ProfilePage() {
  const qc = useQueryClient()
  const { user: authUser } = useAuthStore()
  const [showModal, setShowModal] = useState(false)
  const [subForm, setSubForm] = useState({ plan_id: '', terms_accepted: false, billing_period: 'monthly' })
  const [saving, setSaving] = useState(false)
  const [saveMsg, setSaveMsg] = useState('')

  const { data: profile, isLoading } = useQuery({
    queryKey: ['profile'],
    queryFn: () => api.get('/profile').then(r => r.data),
  })

  const { data: plans = [] } = useQuery({
    queryKey: ['plans'],
    queryFn: () => api.get('/billing/plans').then(r => r.data),
  })

  const [form, setForm] = useState<Record<string, string>>({})
  const merged = { ...profile, ...form }

  const setF = (field: string, val: string) => setForm(f => ({ ...f, [field]: val }))

  const updateMutation = useMutation({
    mutationFn: (data: Record<string, unknown>) => api.put('/profile', data),
    onSuccess: () => { qc.invalidateQueries({ queryKey: ['profile'] }); setSaveMsg('Perfil salvo!'); setTimeout(() => setSaveMsg(''), 3000) },
    onError: () => setSaveMsg('Erro ao salvar'),
  })

  const subscribeMutation = useMutation({
    mutationFn: (data: unknown) => api.post('/profile/subscribe', data),
    onSuccess: (res) => {
      qc.invalidateQueries({ queryKey: ['profile'] })
      if (res.data.checkout_url) {
        // Plano pago: só ativa depois que o Mercado Pago confirmar o pagamento (webhook).
        window.location.href = res.data.checkout_url
        return
      }
      // Plano gratuito: ativa na hora, sem cobrança.
      setShowModal(false)
      alert('Plano atualizado com sucesso!')
    },
    onError: (e: unknown) => alert((e as { response?: { data?: { detail?: string } } })?.response?.data?.detail || 'Erro ao assinar'),
  })

  const handleSave = async () => {
    setSaving(true)
    updateMutation.mutate(form)
    setSaving(false)
  }

  const handleSubscribe = () => {
    if (!subForm.plan_id) return alert('Selecione um plano')
    if (!subForm.terms_accepted) return alert('Você deve aceitar os termos')
    // First save current form data, then subscribe
    api.put('/profile', form).then(() => {
      subscribeMutation.mutate(subForm)
    })
  }

  const initials = merged.name
    ? String(merged.name).split(' ').slice(0, 2).map((n: string) => n[0]).join('').toUpperCase()
    : 'P'

  if (isLoading) return <div style={{ padding: 40, color: '#6b8a76' }}>Carregando perfil...</div>

  const roleLabel = profile?.role === 'vet' ? 'Veterinário' : profile?.role === 'admin' ? 'Admin' : 'Criador'
  const planLabel = profile?.plan?.name || 'Gratuito'

  return (
    <div style={{ maxWidth: 800 }}>
      {/* Header card */}
      <div style={{ ...SECTION_STYLE, display: 'flex', alignItems: 'center', gap: 24 }}>
        <div style={{ width: 72, height: 72, borderRadius: '50%', background: 'linear-gradient(135deg,#2d6a4f,#c8922a)', display: 'flex', alignItems: 'center', justifyContent: 'center', color: '#fff', fontSize: 26, fontWeight: 700, border: '3px solid #c8922a', flexShrink: 0 }}>
          {initials}
        </div>
        <div style={{ flex: 1 }}>
          <h1 style={{ fontFamily: "'Cormorant Garamond',serif", fontSize: 26, fontWeight: 700, color: '#1a3d2b', margin: 0 }}>{profile?.name}</h1>
          <div style={{ display: 'flex', gap: 8, marginTop: 6 }}>
            <span style={{ fontSize: 10, fontWeight: 700, background: '#e8f0e8', color: '#2d6a4f', borderRadius: 5, padding: '2px 10px' }}>{roleLabel}</span>
            <span style={{ fontSize: 10, fontWeight: 700, background: '#fdf4e8', color: '#c8922a', borderRadius: 5, padding: '2px 10px' }}>{planLabel}</span>
          </div>
          <div style={{ fontSize: 12, color: '#6b8a76', marginTop: 4 }}>{profile?.email}</div>
        </div>
        <button onClick={handleSave} disabled={saving || updateMutation.isPending} style={{ background: '#1a3d2b', border: 'none', borderRadius: 10, color: '#faf6f0', fontSize: 13, fontWeight: 700, padding: '10px 24px', cursor: 'pointer' }}>
          {saving ? 'Salvando...' : 'Salvar alterações'}
        </button>
      </div>
      {saveMsg && <div style={{ background: '#e8f0e8', border: '1px solid #2d6a4f', borderRadius: 8, padding: '8px 16px', marginBottom: 12, fontSize: 12, color: '#2d6a4f' }}>{saveMsg}</div>}

      {/* Dados Pessoais */}
      <div style={SECTION_STYLE}>
        <h2 style={{ fontFamily: "'Cormorant Garamond',serif", fontSize: 18, fontWeight: 700, color: '#1a3d2b', marginBottom: 16 }}>Dados Pessoais</h2>
        <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 14 }}>
          <div>
            <label style={LABEL_STYLE}>Nome de exibição</label>
            <input value={merged.name || ''} onChange={e => setF('name', e.target.value)} style={INPUT_STYLE} />
          </div>
          <div>
            <label style={LABEL_STYLE}>Nome completo (para faturamento)</label>
            <input value={merged.full_name || ''} onChange={e => setF('full_name', e.target.value)} style={INPUT_STYLE} />
          </div>
          <div>
            <label style={LABEL_STYLE}>E-mail</label>
            <input value={profile?.email || ''} readOnly style={{ ...INPUT_STYLE, background: '#f0f0f0', color: '#999' }} />
          </div>
          <div>
            <label style={LABEL_STYLE}>Telefone</label>
            <input value={merged.phone || ''} onChange={e => setF('phone', e.target.value)} style={INPUT_STYLE} placeholder="(99) 99999-9999" />
          </div>
          <div>
            <label style={LABEL_STYLE}>CPF</label>
            <input
              value={formatCpf(merged.cpf_cnpj || '')}
              onChange={e => setF('cpf_cnpj', e.target.value.replace(/\D/g, '').slice(0, 11))}
              style={INPUT_STYLE}
              placeholder="000.000.000-00"
              maxLength={14}
            />
          </div>
        </div>
      </div>

      {/* Endereco */}
      <div style={SECTION_STYLE}>
        <h2 style={{ fontFamily: "'Cormorant Garamond',serif", fontSize: 18, fontWeight: 700, color: '#1a3d2b', marginBottom: 16 }}>Endereço</h2>
        <div style={{ display: 'grid', gridTemplateColumns: '2fr 1fr', gap: 14, marginBottom: 14 }}>
          <div>
            <label style={LABEL_STYLE}>Rua / Avenida</label>
            <input value={merged.address_street || ''} onChange={e => setF('address_street', e.target.value)} style={INPUT_STYLE} />
          </div>
          <div>
            <label style={LABEL_STYLE}>Número</label>
            <input value={merged.address_number || ''} onChange={e => setF('address_number', e.target.value)} style={INPUT_STYLE} />
          </div>
        </div>
        <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr 1fr 1fr', gap: 14 }}>
          <div style={{ gridColumn: 'span 2' }}>
            <label style={LABEL_STYLE}>Complemento</label>
            <input value={merged.address_complement || ''} onChange={e => setF('address_complement', e.target.value)} style={INPUT_STYLE} placeholder="Apto, sala, etc." />
          </div>
          <div>
            <label style={LABEL_STYLE}>Cidade</label>
            <input value={merged.address_city || ''} onChange={e => setF('address_city', e.target.value)} style={INPUT_STYLE} />
          </div>
          <div>
            <label style={LABEL_STYLE}>Estado</label>
            <select value={merged.address_state || ''} onChange={e => setF('address_state', e.target.value)} style={INPUT_STYLE}>
              <option value="">UF</option>
              {BR_STATES.map(s => <option key={s} value={s}>{s}</option>)}
            </select>
          </div>
          <div>
            <label style={LABEL_STYLE}>CEP</label>
            <input value={merged.address_zip || ''} onChange={e => setF('address_zip', e.target.value)} style={INPUT_STYLE} placeholder="00000-000" maxLength={9} />
          </div>
        </div>
      </div>

      {/* Associacao */}
      <div style={SECTION_STYLE}>
        <h2 style={{ fontFamily: "'Cormorant Garamond',serif", fontSize: 18, fontWeight: 700, color: '#1a3d2b', marginBottom: 16 }}>Associação de Criadores</h2>
        <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 14 }}>
          <div>
            <label style={LABEL_STYLE}>Nome da Associação</label>
            <input value={merged.association_name || ''} onChange={e => setF('association_name', e.target.value)} style={INPUT_STYLE} />
          </div>
          <div>
            <label style={LABEL_STYLE}>Número de Associado</label>
            <input value={merged.association_number || ''} onChange={e => setF('association_number', e.target.value)} style={INPUT_STYLE} />
          </div>
        </div>
      </div>

      {/* CRMV (vet role) */}
      {(profile?.role === 'vet' || profile?.role === 'admin') && (
        <div style={SECTION_STYLE}>
          <h2 style={{ fontFamily: "'Cormorant Garamond',serif", fontSize: 18, fontWeight: 700, color: '#1a3d2b', marginBottom: 16 }}>Dados Profissionais (CRMV)</h2>
          <div style={{ display: 'grid', gridTemplateColumns: '1fr 2fr', gap: 14 }}>
            <div>
              <label style={LABEL_STYLE}>Estado do CRMV</label>
              <select value={merged.crmv_state || ''} onChange={e => setF('crmv_state', e.target.value)} style={INPUT_STYLE}>
                <option value="">UF</option>
                {BR_STATES.map(s => <option key={s} value={s}>{s}</option>)}
              </select>
            </div>
            <div>
              <label style={LABEL_STYLE}>Número CRMV</label>
              <input value={merged.crmv_number || ''} onChange={e => setF('crmv_number', e.target.value)} style={INPUT_STYLE} placeholder="Ex: 12345" />
            </div>
          </div>
        </div>
      )}

      {/* Assinatura */}
      <div style={SECTION_STYLE}>
        <h2 style={{ fontFamily: "'Cormorant Garamond',serif", fontSize: 18, fontWeight: 700, color: '#1a3d2b', marginBottom: 12 }}>Assinatura</h2>
        <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between' }}>
          <div>
            <div style={{ fontSize: 13, fontWeight: 600, color: '#1e2620' }}>Plano atual: <span style={{ color: '#c8922a' }}>{planLabel}</span></div>
            {profile?.terms_accepted_at && <div style={{ fontSize: 11, color: '#6b8a76', marginTop: 4 }}>Termos aceitos em {new Date(profile.terms_accepted_at).toLocaleDateString('pt-BR')}</div>}
          </div>
          <button onClick={() => setShowModal(true)} style={{ background: 'linear-gradient(135deg,#c8922a,#a87520)', border: 'none', borderRadius: 10, color: '#fff', fontSize: 13, fontWeight: 700, padding: '10px 22px', cursor: 'pointer' }}>
            Assinar Plano Pro
          </button>
        </div>
      </div>

      {/* Subscription Modal */}
      {showModal && (
        <div style={{ position: 'fixed', inset: 0, background: 'rgba(0,0,0,0.5)', display: 'flex', alignItems: 'center', justifyContent: 'center', zIndex: 1000 }}>
          <div style={{ background: '#faf6f0', borderRadius: 20, padding: 32, width: 500, maxWidth: '95vw', maxHeight: '90vh', overflowY: 'auto', boxShadow: '0 8px 40px rgba(0,0,0,0.3)' }}>
            <h2 style={{ fontFamily: "'Cormorant Garamond',serif", fontSize: 24, fontWeight: 700, color: '#1a3d2b', marginBottom: 20 }}>Assinar Plano</h2>

            <div style={{ display: 'grid', gridTemplateColumns: '2fr 1fr', gap: 12, marginBottom: 16 }}>
              <div>
                <label style={LABEL_STYLE}>Selecionar plano</label>
                <select value={subForm.plan_id} onChange={e => setSubForm(f => ({ ...f, plan_id: e.target.value }))} style={INPUT_STYLE}>
                  <option value="">Selecione...</option>
                  {plans.map((p: { id: string; name: string; price_monthly: number; price_yearly: number }) => (
                    <option key={p.id} value={p.id}>
                      {p.name} — R$ {((subForm.billing_period === 'yearly' ? p.price_yearly : p.price_monthly) / 100).toFixed(2)}
                      {subForm.billing_period === 'yearly' ? '/ano' : '/mês'}
                    </option>
                  ))}
                </select>
              </div>
              <div>
                <label style={LABEL_STYLE}>Cobrança</label>
                <select value={subForm.billing_period} onChange={e => setSubForm(f => ({ ...f, billing_period: e.target.value }))} style={INPUT_STYLE}>
                  <option value="monthly">Mensal</option>
                  <option value="yearly">Anual</option>
                </select>
              </div>
            </div>

            <div style={{ background: '#fff9f5', borderRadius: 10, padding: 14, marginBottom: 16, fontSize: 11, color: '#7a5a2a', border: '1px solid #f0c8b0' }}>
              Para assinar, você precisa ter preenchido: nome completo, CPF, endereço completo. Esses dados serão usados para emissão de notas fiscais. Você será redirecionado para o Mercado Pago para concluir o pagamento (exceto no plano gratuito).
            </div>

            <div style={{ display: 'flex', alignItems: 'flex-start', gap: 10, marginBottom: 16 }}>
              <input
                type="checkbox"
                id="terms_cb"
                checked={subForm.terms_accepted}
                onChange={e => setSubForm(f => ({ ...f, terms_accepted: e.target.checked }))}
                style={{ marginTop: 2, accentColor: '#1a3d2b', width: 16, height: 16, flexShrink: 0 }}
              />
              <label htmlFor="terms_cb" style={{ fontSize: 12, color: '#4a6a54', cursor: 'pointer' }}>
                Estou ciente dos valores e{' '}
                <a href="/termos" target="_blank" style={{ color: '#c8922a', textDecoration: 'underline' }}>termos de serviço</a>
                {' '}do Plumora Gen.
              </label>
            </div>

            <div style={{ display: 'flex', gap: 10 }}>
              <button onClick={handleSubscribe} disabled={subscribeMutation.isPending} style={{ flex: 1, background: '#1a3d2b', border: 'none', borderRadius: 10, color: '#faf6f0', fontSize: 13, fontWeight: 700, padding: '12px 0', cursor: 'pointer' }}>
                {subscribeMutation.isPending ? 'Processando...' : 'Confirmar Assinatura'}
              </button>
              <button onClick={() => setShowModal(false)} style={{ background: '#fff', border: '1px solid #c8d4c8', borderRadius: 10, color: '#4a6a54', fontSize: 13, fontWeight: 600, padding: '12px 20px', cursor: 'pointer' }}>
                Cancelar
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  )
}
