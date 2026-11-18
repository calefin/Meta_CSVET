import { useState } from 'react'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { Link } from 'react-router-dom'
import { marketingApi } from '@/services/api'
import type { Campaign, EmailTemplate } from '@/types'

const INPUT_STYLE = {
  width: '100%', border: '1px solid #c8d4c8', borderRadius: 8, padding: '9px 12px',
  fontSize: 13, color: '#1e2620', background: '#fafcfa', outline: 'none', boxSizing: 'border-box' as const,
}
const LABEL_STYLE = { display: 'block', fontSize: 11, fontWeight: 600 as const, color: '#4a6a54', marginBottom: 5 }
const CARD_STYLE = { background: '#fff', borderRadius: 16, border: '1px solid #e0e8e2', padding: 24, marginBottom: 20 }

const ROLES = ['breeder', 'vet', 'admin']
const FROM_ADDRESSES = ['contato@csvet.com.br', 'calefi@csvet.com.br']

const STATUS_COLOR: Record<string, { bg: string; fg: string }> = {
  draft: { bg: '#f0f4f1', fg: '#6b8a76' },
  sending: { bg: '#fdf4e8', fg: '#c8922a' },
  sent: { bg: '#e8f0e8', fg: '#2d6a4f' },
  failed: { bg: '#fdf0ed', fg: '#c45c3d' },
}

function errorDetail(e: unknown): string {
  return (e as { response?: { data?: { detail?: string } } })?.response?.data?.detail || 'Erro ao salvar'
}

export default function AdminCampaignsPage() {
  const qc = useQueryClient()

  const [showTemplateForm, setShowTemplateForm] = useState(false)
  const [templateForm, setTemplateForm] = useState({ name: '', subject: '', html_body: '' })

  const [showCampaignForm, setShowCampaignForm] = useState(false)
  const [campaignForm, setCampaignForm] = useState({
    name: '', template_id: '', subject: '', html_body: '', from_address: FROM_ADDRESSES[0], roles: [] as string[],
  })
  const [previewByCampaign, setPreviewByCampaign] = useState<Record<string, { recipient_count: number; exceeds_daily_limit: boolean; daily_limit: number }>>({})

  const { data: templates = [] } = useQuery({
    queryKey: ['marketing-templates'],
    queryFn: () => marketingApi.listTemplates().then(r => r.data as EmailTemplate[]),
  })
  const { data: campaigns = [] } = useQuery({
    queryKey: ['marketing-campaigns'],
    queryFn: () => marketingApi.listCampaigns().then(r => r.data as Campaign[]),
  })

  const createTemplateMutation = useMutation({
    mutationFn: () => marketingApi.createTemplate(templateForm),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['marketing-templates'] })
      setShowTemplateForm(false)
      setTemplateForm({ name: '', subject: '', html_body: '' })
    },
    onError: (e: unknown) => alert(errorDetail(e)),
  })

  const createCampaignMutation = useMutation({
    mutationFn: () => marketingApi.createCampaign({
      name: campaignForm.name,
      template_id: campaignForm.template_id || undefined,
      subject: campaignForm.subject,
      html_body: campaignForm.html_body,
      from_address: campaignForm.from_address,
      segment_filters: campaignForm.roles.length ? { roles: campaignForm.roles } : undefined,
    }),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['marketing-campaigns'] })
      setShowCampaignForm(false)
      setCampaignForm({ name: '', template_id: '', subject: '', html_body: '', from_address: FROM_ADDRESSES[0], roles: [] })
    },
    onError: (e: unknown) => alert(errorDetail(e)),
  })

  const previewMutation = useMutation({
    mutationFn: (id: string) => marketingApi.previewSegment(id),
    onSuccess: (res, id) => setPreviewByCampaign(p => ({ ...p, [id]: res.data })),
    onError: (e: unknown) => alert(errorDetail(e)),
  })

  const sendMutation = useMutation({
    mutationFn: (id: string) => marketingApi.sendCampaign(id),
    onSuccess: () => qc.invalidateQueries({ queryKey: ['marketing-campaigns'] }),
    onError: (e: unknown) => alert(errorDetail(e)),
  })

  const applyTemplate = (templateId: string) => {
    const tpl = templates.find(t => t.id === templateId)
    setCampaignForm(f => ({
      ...f,
      template_id: templateId,
      subject: tpl?.subject || f.subject,
      html_body: tpl?.html_body || f.html_body,
    }))
  }

  const toggleRole = (role: string) => {
    setCampaignForm(f => ({
      ...f,
      roles: f.roles.includes(role) ? f.roles.filter(r => r !== role) : [...f.roles, role],
    }))
  }

  return (
    <div>
      <Link to="/app/admin" style={{ fontSize: 12, color: '#4a6a54', textDecoration: 'none' }}>← Administração</Link>
      <h1 style={{ fontFamily: "'Cormorant Garamond',serif", fontSize: 30, fontWeight: 700, color: '#1a3d2b', margin: '8px 0 24px' }}>
        Campanhas de E-mail
      </h1>

      {/* Templates */}
      <div style={CARD_STYLE}>
        <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: 16 }}>
          <h2 style={{ fontFamily: "'Cormorant Garamond',serif", fontSize: 20, color: '#1a3d2b' }}>Templates</h2>
          <button onClick={() => setShowTemplateForm(v => !v)} style={{ background: '#1a3d2b', border: 'none', borderRadius: 8, color: '#faf6f0', fontSize: 12, fontWeight: 700, padding: '9px 18px', cursor: 'pointer' }}>
            {showTemplateForm ? '✕ Cancelar' : '+ Novo Template'}
          </button>
        </div>

        {showTemplateForm && (
          <div style={{ background: '#fafcfa', borderRadius: 10, padding: 16, marginBottom: 16 }}>
            <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 12, marginBottom: 12 }}>
              <div>
                <label style={LABEL_STYLE}>Nome</label>
                <input value={templateForm.name} onChange={e => setTemplateForm(f => ({ ...f, name: e.target.value }))} style={INPUT_STYLE} />
              </div>
              <div>
                <label style={LABEL_STYLE}>Assunto</label>
                <input value={templateForm.subject} onChange={e => setTemplateForm(f => ({ ...f, subject: e.target.value }))} style={INPUT_STYLE} />
              </div>
            </div>
            <div style={{ marginBottom: 12 }}>
              <label style={LABEL_STYLE}>Corpo (HTML)</label>
              <textarea value={templateForm.html_body} onChange={e => setTemplateForm(f => ({ ...f, html_body: e.target.value }))} rows={6} style={{ ...INPUT_STYLE, resize: 'vertical', fontFamily: 'monospace' }} />
            </div>
            <button onClick={() => createTemplateMutation.mutate()} disabled={!templateForm.name || !templateForm.subject || createTemplateMutation.isPending} style={{ background: '#2d6a4f', border: 'none', borderRadius: 8, color: '#faf6f0', fontSize: 12, fontWeight: 700, padding: '8px 18px', cursor: 'pointer' }}>
              ✓ Salvar Template
            </button>
          </div>
        )}

        <div style={{ display: 'grid', gap: 8 }}>
          {templates.map(t => (
            <div key={t.id} style={{ border: '1px solid #f0f4f0', borderRadius: 8, padding: 10, fontSize: 12 }}>
              <strong>{t.name}</strong> — <span style={{ color: '#7a9a84' }}>{t.subject}</span>
            </div>
          ))}
          {templates.length === 0 && <p style={{ fontSize: 12, color: '#9ab5a0' }}>Nenhum template cadastrado.</p>}
        </div>
      </div>

      {/* Campanhas */}
      <div style={CARD_STYLE}>
        <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: 16 }}>
          <h2 style={{ fontFamily: "'Cormorant Garamond',serif", fontSize: 20, color: '#1a3d2b' }}>Campanhas</h2>
          <button onClick={() => setShowCampaignForm(v => !v)} style={{ background: '#1a3d2b', border: 'none', borderRadius: 8, color: '#faf6f0', fontSize: 12, fontWeight: 700, padding: '9px 18px', cursor: 'pointer' }}>
            {showCampaignForm ? '✕ Cancelar' : '+ Nova Campanha'}
          </button>
        </div>

        {showCampaignForm && (
          <div style={{ background: '#fafcfa', borderRadius: 10, padding: 16, marginBottom: 16 }}>
            <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 12, marginBottom: 12 }}>
              <div>
                <label style={LABEL_STYLE}>Nome da campanha</label>
                <input value={campaignForm.name} onChange={e => setCampaignForm(f => ({ ...f, name: e.target.value }))} style={INPUT_STYLE} />
              </div>
              <div>
                <label style={LABEL_STYLE}>Usar template (opcional)</label>
                <select value={campaignForm.template_id} onChange={e => applyTemplate(e.target.value)} style={INPUT_STYLE}>
                  <option value="">Escrever do zero</option>
                  {templates.map(t => <option key={t.id} value={t.id}>{t.name}</option>)}
                </select>
              </div>
              <div>
                <label style={LABEL_STYLE}>Assunto</label>
                <input value={campaignForm.subject} onChange={e => setCampaignForm(f => ({ ...f, subject: e.target.value }))} style={INPUT_STYLE} />
              </div>
              <div>
                <label style={LABEL_STYLE}>Remetente</label>
                <select value={campaignForm.from_address} onChange={e => setCampaignForm(f => ({ ...f, from_address: e.target.value }))} style={INPUT_STYLE}>
                  {FROM_ADDRESSES.map(a => <option key={a} value={a}>{a}</option>)}
                </select>
              </div>
            </div>
            <div style={{ marginBottom: 12 }}>
              <label style={LABEL_STYLE}>Corpo (HTML)</label>
              <textarea value={campaignForm.html_body} onChange={e => setCampaignForm(f => ({ ...f, html_body: e.target.value }))} rows={6} style={{ ...INPUT_STYLE, resize: 'vertical', fontFamily: 'monospace' }} />
            </div>
            <div style={{ marginBottom: 16 }}>
              <label style={LABEL_STYLE}>Segmento — Papéis</label>
              <div style={{ display: 'flex', gap: 12 }}>
                {ROLES.map(role => (
                  <label key={role} style={{ fontSize: 12, color: '#4a6a54', display: 'flex', alignItems: 'center', gap: 6 }}>
                    <input type="checkbox" checked={campaignForm.roles.includes(role)} onChange={() => toggleRole(role)} />
                    {role}
                  </label>
                ))}
              </div>
              <p style={{ fontSize: 11, color: '#9ab5a0', marginTop: 4 }}>Nenhum papel selecionado = todos os usuários ativos (que não pediram descadastro).</p>
            </div>
            <button onClick={() => createCampaignMutation.mutate()} disabled={!campaignForm.name || !campaignForm.subject || createCampaignMutation.isPending} style={{ background: '#2d6a4f', border: 'none', borderRadius: 8, color: '#faf6f0', fontSize: 12, fontWeight: 700, padding: '8px 18px', cursor: 'pointer' }}>
              ✓ Criar Campanha (rascunho)
            </button>
          </div>
        )}

        <div style={{ display: 'grid', gap: 10 }}>
          {campaigns.map(c => {
            const preview = previewByCampaign[c.id]
            const colors = STATUS_COLOR[c.status] || STATUS_COLOR.draft
            return (
              <div key={c.id} style={{ border: '1px solid #e0e8e2', borderRadius: 10, padding: 14 }}>
                <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: 6 }}>
                  <div style={{ fontSize: 13, fontWeight: 600, color: '#1e2620' }}>{c.name}</div>
                  <span style={{ background: colors.bg, color: colors.fg, borderRadius: 6, padding: '3px 10px', fontSize: 10, fontWeight: 700, textTransform: 'uppercase' }}>{c.status}</span>
                </div>
                <div style={{ fontSize: 11, color: '#7a9a84', marginBottom: 8 }}>
                  {c.subject} · de {c.from_address}
                  {c.status === 'sent' && ` · ${c.total_sent} enviados, ${c.total_failed} falharam`}
                  {c.status === 'sending' && ` · ${c.total_recipients} destinatários`}
                </div>
                {c.status === 'draft' && (
                  <div style={{ display: 'flex', alignItems: 'center', gap: 10 }}>
                    <button onClick={() => previewMutation.mutate(c.id)} style={{ background: 'none', border: '1px solid #2d6a4f', borderRadius: 6, color: '#2d6a4f', fontSize: 10, fontWeight: 600, padding: '4px 10px', cursor: 'pointer' }}>
                      Ver quantos vão receber
                    </button>
                    <button onClick={() => { if (confirm(`Enviar "${c.name}" agora?`)) sendMutation.mutate(c.id) }} style={{ background: '#c8922a', border: 'none', borderRadius: 6, color: '#fff', fontSize: 10, fontWeight: 700, padding: '4px 12px', cursor: 'pointer' }}>
                      Enviar
                    </button>
                    {preview && (
                      <span style={{ fontSize: 11, color: preview.exceeds_daily_limit ? '#c45c3d' : '#4a6a54', fontWeight: 600 }}>
                        {preview.recipient_count} destinatário(s)
                        {preview.exceeds_daily_limit && ` ⚠️ acima do limite diário do Gmail (${preview.daily_limit})`}
                      </span>
                    )}
                  </div>
                )}
              </div>
            )
          })}
          {campaigns.length === 0 && <p style={{ fontSize: 12, color: '#9ab5a0' }}>Nenhuma campanha criada ainda.</p>}
        </div>
      </div>
    </div>
  )
}
