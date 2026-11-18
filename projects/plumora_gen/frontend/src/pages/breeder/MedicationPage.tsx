import { useState } from 'react'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import api from '@/services/api'

const INPUT_STYLE = {
  width: '100%', border: '1px solid #c8d4c8', borderRadius: 8, padding: '9px 12px',
  fontSize: 13, color: '#1e2620', background: '#fafcfa', outline: 'none', boxSizing: 'border-box' as const,
}
const LABEL_STYLE = { display: 'block', fontSize: 11, fontWeight: 600 as const, color: '#4a6a54', marginBottom: 5 }

interface Medication {
  id: string
  name: string
  active_ingredient?: string
  manufacturer?: string
  type: string
  presentation?: string
  requires_prescription: boolean
}

interface MedRecord {
  id: string
  bird_id?: string
  bird_name?: string
  medication_id: string
  medication_name?: string
  dose?: number
  unit?: string
  route?: string
  start_date: string
  end_date?: string
  interval_hours?: number
  notes?: string
  created_at: string
}

const ROUTES = [
  { value: 'oral', label: 'Oral' },
  { value: 'IM', label: 'Intramuscular (IM)' },
  { value: 'SC', label: 'Subcutânea (SC)' },
  { value: 'topical', label: 'Tópica' },
  { value: 'drinking_water', label: 'Água de beber' },
]

const UNITS = ['mg', 'ml', 'drops', 'IU', 'g']

export default function MedicationPage() {
  const qc = useQueryClient()
  const [tab, setTab] = useState<'records' | 'catalog'>('records')
  const [showForm, setShowForm] = useState(false)
  const [form, setForm] = useState({
    bird_id: '', medication_id: '', dose: '', unit: 'mg', route: 'oral',
    start_date: new Date().toISOString().split('T')[0], end_date: '', interval_hours: '', notes: '',
  })

  const { data: records = [], isLoading: loadingRecords } = useQuery<MedRecord[]>({
    queryKey: ['med-records'],
    queryFn: () => api.get('/medications/records').then(r => r.data),
  })

  const { data: medications = [] } = useQuery<Medication[]>({
    queryKey: ['medications'],
    queryFn: () => api.get('/medications').then(r => r.data),
  })

  const { data: birds = [] } = useQuery({
    queryKey: ['birds'],
    queryFn: () => api.get('/birds').then(r => r.data),
  })

  const createMutation = useMutation({
    mutationFn: (data: unknown) => api.post('/medications/records', data),
    onSuccess: () => { qc.invalidateQueries({ queryKey: ['med-records'] }); setShowForm(false) },
    onError: (e: unknown) => alert((e as { response?: { data?: { detail?: string } } })?.response?.data?.detail || 'Erro'),
  })

  const deleteMutation = useMutation({
    mutationFn: (id: string) => api.delete(`/medications/records/${id}`),
    onSuccess: () => qc.invalidateQueries({ queryKey: ['med-records'] }),
  })

  const handleCreate = () => {
    if (!form.medication_id) return alert('Selecione um medicamento')
    if (!form.start_date) return alert('Data de início obrigatória')
    createMutation.mutate({
      bird_id: form.bird_id || undefined,
      medication_id: form.medication_id,
      dose: form.dose ? parseFloat(form.dose) : undefined,
      unit: form.unit || undefined,
      route: form.route || undefined,
      start_date: new Date(form.start_date).toISOString(),
      end_date: form.end_date ? new Date(form.end_date).toISOString() : undefined,
      interval_hours: form.interval_hours ? parseInt(form.interval_hours) : undefined,
      notes: form.notes || undefined,
    })
  }

  const TAB_BTN = (key: 'records' | 'catalog', label: string) => (
    <button onClick={() => setTab(key)} style={{ background: tab === key ? '#1a3d2b' : '#fff', color: tab === key ? '#faf6f0' : '#2d6a4f', border: '1px solid #2d6a4f', borderRadius: 8, fontSize: 12, fontWeight: 600, padding: '8px 18px', cursor: 'pointer' }}>
      {label}
    </button>
  )

  return (
    <div>
      <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start', marginBottom: 24 }}>
        <div>
          <h1 style={{ fontFamily: "'Cormorant Garamond',serif", fontSize: 30, fontWeight: 700, color: '#1a3d2b' }}>💊 Medicações</h1>
          <p style={{ color: '#6b8a76', fontSize: 13, marginTop: 4 }}>Registros de tratamentos e catálogo de medicamentos</p>
        </div>
        <div style={{ display: 'flex', gap: 8 }}>
          {TAB_BTN('records', '📋 Registros')}
          {TAB_BTN('catalog', '📚 Catálogo')}
        </div>
      </div>

      {tab === 'records' && (
        <>
          <div style={{ display: 'flex', justifyContent: 'flex-end', marginBottom: 16 }}>
            <button onClick={() => setShowForm(!showForm)} style={{ background: '#1a3d2b', border: 'none', borderRadius: 8, color: '#faf6f0', fontSize: 12, fontWeight: 700, padding: '9px 18px', cursor: 'pointer' }}>
              + Novo Registro
            </button>
          </div>

          {showForm && (
            <div style={{ background: '#fff', borderRadius: 16, border: '1px solid #e0e8e2', padding: 24, marginBottom: 20, boxShadow: '0 4px 20px rgba(26,61,43,0.08)' }}>
              <h3 style={{ fontFamily: "'Cormorant Garamond',serif", fontSize: 18, color: '#1a3d2b', marginBottom: 16 }}>Novo Registro de Medicação</h3>
              <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 14, marginBottom: 14 }}>
                <div>
                  <label style={LABEL_STYLE}>Ave</label>
                  <select value={form.bird_id} onChange={e => setForm(f => ({ ...f, bird_id: e.target.value }))} style={INPUT_STYLE}>
                    <option value="">Todas / Sem ave específica</option>
                    {(birds as { id: string; name: string }[]).map(b => <option key={b.id} value={b.id}>{b.name}</option>)}
                  </select>
                </div>
                <div>
                  <label style={LABEL_STYLE}>Medicamento *</label>
                  <select value={form.medication_id} onChange={e => setForm(f => ({ ...f, medication_id: e.target.value }))} style={INPUT_STYLE}>
                    <option value="">Selecione...</option>
                    {medications.map(m => <option key={m.id} value={m.id}>{m.name}</option>)}
                  </select>
                </div>
                <div>
                  <label style={LABEL_STYLE}>Dose</label>
                  <input type="number" value={form.dose} onChange={e => setForm(f => ({ ...f, dose: e.target.value }))} style={INPUT_STYLE} placeholder="0.5" />
                </div>
                <div>
                  <label style={LABEL_STYLE}>Unidade</label>
                  <select value={form.unit} onChange={e => setForm(f => ({ ...f, unit: e.target.value }))} style={INPUT_STYLE}>
                    {UNITS.map(u => <option key={u} value={u}>{u}</option>)}
                  </select>
                </div>
                <div>
                  <label style={LABEL_STYLE}>Via de administração</label>
                  <select value={form.route} onChange={e => setForm(f => ({ ...f, route: e.target.value }))} style={INPUT_STYLE}>
                    {ROUTES.map(r => <option key={r.value} value={r.value}>{r.label}</option>)}
                  </select>
                </div>
                <div>
                  <label style={LABEL_STYLE}>Intervalo (horas)</label>
                  <input type="number" value={form.interval_hours} onChange={e => setForm(f => ({ ...f, interval_hours: e.target.value }))} style={INPUT_STYLE} placeholder="24" />
                </div>
                <div>
                  <label style={LABEL_STYLE}>Data Início *</label>
                  <input type="date" value={form.start_date} onChange={e => setForm(f => ({ ...f, start_date: e.target.value }))} style={INPUT_STYLE} />
                </div>
                <div>
                  <label style={LABEL_STYLE}>Data Fim</label>
                  <input type="date" value={form.end_date} onChange={e => setForm(f => ({ ...f, end_date: e.target.value }))} style={INPUT_STYLE} />
                </div>
              </div>
              <div style={{ marginBottom: 14 }}>
                <label style={LABEL_STYLE}>Observações</label>
                <textarea value={form.notes} onChange={e => setForm(f => ({ ...f, notes: e.target.value }))} rows={2} style={{ ...INPUT_STYLE, resize: 'vertical' }} />
              </div>
              <div style={{ display: 'flex', gap: 10 }}>
                <button onClick={handleCreate} disabled={createMutation.isPending} style={{ background: '#2d6a4f', border: 'none', borderRadius: 10, color: '#faf6f0', fontSize: 13, fontWeight: 700, padding: '10px 24px', cursor: 'pointer' }}>
                  {createMutation.isPending ? 'Salvando...' : '✓ Salvar Registro'}
                </button>
                <button onClick={() => setShowForm(false)} style={{ background: '#fff', border: '1px solid #c8d4c8', borderRadius: 10, color: '#4a6a54', fontSize: 13, padding: '10px 18px', cursor: 'pointer' }}>
                  Cancelar
                </button>
              </div>
            </div>
          )}

          <div style={{ display: 'grid', gap: 10 }}>
            {loadingRecords ? (
              <div style={{ padding: 24, textAlign: 'center', color: '#6b8a76' }}>Carregando registros...</div>
            ) : records.length === 0 ? (
              <div style={{ background: '#fff', borderRadius: 12, border: '1px solid #e0e8e2', padding: 24, textAlign: 'center', color: '#6b8a76', fontSize: 13 }}>
                Nenhum registro encontrado. Adicione o primeiro tratamento.
              </div>
            ) : records.map(r => (
              <div key={r.id} style={{ background: '#fff', borderRadius: 12, border: '1px solid #e0e8e2', padding: 16, display: 'flex', alignItems: 'center', gap: 16 }}>
                <div style={{ flex: 1 }}>
                  <div style={{ display: 'flex', alignItems: 'center', gap: 10, marginBottom: 4 }}>
                    <span style={{ fontSize: 13, fontWeight: 700, color: '#1e2620' }}>{r.medication_name}</span>
                    {r.bird_name && <span style={{ fontSize: 11, background: '#e8f0e8', color: '#2d6a4f', borderRadius: 5, padding: '1px 8px', fontWeight: 600 }}>{r.bird_name}</span>}
                  </div>
                  <div style={{ display: 'flex', gap: 14, fontSize: 11, color: '#6b8a76' }}>
                    {r.dose && <span>Dose: {r.dose} {r.unit}</span>}
                    {r.route && <span>Via: {ROUTES.find(x => x.value === r.route)?.label || r.route}</span>}
                    <span>Início: {new Date(r.start_date).toLocaleDateString('pt-BR')}</span>
                    {r.end_date && <span>Fim: {new Date(r.end_date).toLocaleDateString('pt-BR')}</span>}
                  </div>
                  {r.notes && <div style={{ fontSize: 11, color: '#9ab5a0', marginTop: 4, fontStyle: 'italic' }}>{r.notes}</div>}
                </div>
                <button onClick={() => { if (confirm('Excluir registro?')) deleteMutation.mutate(r.id) }} style={{ background: '#fdf0ed', border: 'none', borderRadius: 8, color: '#c45c3d', fontSize: 11, fontWeight: 600, padding: '6px 12px', cursor: 'pointer' }}>
                  Excluir
                </button>
              </div>
            ))}
          </div>
        </>
      )}

      {tab === 'catalog' && (
        <div style={{ background: '#fff', borderRadius: 16, border: '1px solid #e0e8e2', overflow: 'hidden' }}>
          <table style={{ width: '100%', borderCollapse: 'collapse' }}>
            <thead>
              <tr style={{ background: '#f5f8f5' }}>
                {['Nome', 'Princípio Ativo', 'Fabricante', 'Tipo', 'Apresentação', 'Prescrição'].map(h => (
                  <th key={h} style={{ padding: '10px 14px', textAlign: 'left', fontSize: 11, fontWeight: 700, color: '#4a6a54', borderBottom: '1px solid #e0e8e2' }}>{h}</th>
                ))}
              </tr>
            </thead>
            <tbody>
              {medications.length === 0 ? (
                <tr><td colSpan={6} style={{ padding: 24, textAlign: 'center', color: '#6b8a76', fontSize: 13 }}>Catálogo vazio</td></tr>
              ) : medications.map((m, i) => (
                <tr key={m.id} style={{ borderTop: i > 0 ? '1px solid #f0f4f0' : 'none', background: i % 2 === 0 ? '#fff' : '#fafcfa' }}>
                  <td style={{ padding: '10px 14px', fontSize: 13, fontWeight: 600, color: '#1e2620' }}>{m.name}</td>
                  <td style={{ padding: '10px 14px', fontSize: 12, color: '#4a6a54' }}>{m.active_ingredient || '—'}</td>
                  <td style={{ padding: '10px 14px', fontSize: 12, color: '#4a6a54' }}>{m.manufacturer || '—'}</td>
                  <td style={{ padding: '10px 14px', fontSize: 11, color: '#6b8a76' }}>{m.type}</td>
                  <td style={{ padding: '10px 14px', fontSize: 11, color: '#6b8a76' }}>{m.presentation || '—'}</td>
                  <td style={{ padding: '10px 14px' }}>
                    <span style={{ fontSize: 10, fontWeight: 700, background: m.requires_prescription ? '#fdf0ed' : '#e8f0e8', color: m.requires_prescription ? '#c45c3d' : '#2d6a4f', borderRadius: 5, padding: '2px 8px' }}>
                      {m.requires_prescription ? 'Sim' : 'Não'}
                    </span>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}
    </div>
  )
}
