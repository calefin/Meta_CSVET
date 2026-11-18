import { useState } from 'react'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import api from '@/services/api'

const INPUT_STYLE = {
  width: '100%', border: '1px solid #c8d4c8', borderRadius: 8, padding: '9px 12px',
  fontSize: 13, color: '#1e2620', background: '#fafcfa', outline: 'none', boxSizing: 'border-box' as const,
}
const LABEL_STYLE = { display: 'block', fontSize: 11, fontWeight: 600 as const, color: '#4a6a54', marginBottom: 5 }

interface Ring {
  id: string
  ring_number: string
  year?: number
  color?: string
  species_id?: string
  species_name?: string
  bird_id?: string
  bird_name?: string
  source?: string
  notes?: string
  created_at: string
}

export default function RingsPage() {
  const qc = useQueryClient()
  const [showForm, setShowForm] = useState(false)
  const [search, setSearch] = useState('')
  const [assignModal, setAssignModal] = useState<string | null>(null)
  const [assignBirdId, setAssignBirdId] = useState('')
  const [form, setForm] = useState({ ring_number: '', year: '', color: '', species_id: '', source: '', notes: '' })

  const { data: rings = [], isLoading } = useQuery<Ring[]>({
    queryKey: ['rings'],
    queryFn: () => api.get('/rings').then(r => r.data),
  })

  const { data: stats } = useQuery({
    queryKey: ['rings-stats'],
    queryFn: () => api.get('/rings/stats').then(r => r.data),
  })

  const { data: species = [] } = useQuery({
    queryKey: ['species'],
    queryFn: () => api.get('/birds/species').then(r => r.data).catch(() => []),
  })

  const { data: birds = [] } = useQuery({
    queryKey: ['birds'],
    queryFn: () => api.get('/birds').then(r => r.data),
  })

  const createMutation = useMutation({
    mutationFn: (data: unknown) => api.post('/rings', data),
    onSuccess: () => { qc.invalidateQueries({ queryKey: ['rings'] }); qc.invalidateQueries({ queryKey: ['rings-stats'] }); setShowForm(false); setForm({ ring_number: '', year: '', color: '', species_id: '', source: '', notes: '' }) },
  })

  const deleteMutation = useMutation({
    mutationFn: (id: string) => api.delete(`/rings/${id}`),
    onSuccess: () => { qc.invalidateQueries({ queryKey: ['rings'] }); qc.invalidateQueries({ queryKey: ['rings-stats'] }) },
    onError: (e: unknown) => alert((e as { response?: { data?: { detail?: string } } })?.response?.data?.detail || 'Erro ao excluir'),
  })

  const assignMutation = useMutation({
    mutationFn: ({ id, bird_id }: { id: string; bird_id: string }) => api.post(`/rings/${id}/assign`, { bird_id }),
    onSuccess: () => { qc.invalidateQueries({ queryKey: ['rings'] }); setAssignModal(null); setAssignBirdId('') },
  })

  const handleCreate = () => {
    if (!form.ring_number.trim()) return alert('Número da anilha obrigatório')
    createMutation.mutate({
      ring_number: form.ring_number,
      year: form.year ? parseInt(form.year) : undefined,
      color: form.color || undefined,
      species_id: form.species_id || undefined,
      source: form.source || undefined,
      notes: form.notes || undefined,
    })
  }

  const filtered = rings.filter(r =>
    r.ring_number.toLowerCase().includes(search.toLowerCase()) ||
    (r.bird_name && r.bird_name.toLowerCase().includes(search.toLowerCase()))
  )

  return (
    <div>
      <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: 24 }}>
        <div>
          <h1 style={{ fontFamily: "'Cormorant Garamond',serif", fontSize: 30, fontWeight: 700, color: '#1a3d2b' }}>💍 Anilhas</h1>
          <p style={{ color: '#6b8a76', fontSize: 13, marginTop: 4 }}>Gerencie as anilhas do seu plantel</p>
        </div>
        <button onClick={() => setShowForm(!showForm)} style={{ background: '#1a3d2b', border: 'none', borderRadius: 8, color: '#faf6f0', fontSize: 12, fontWeight: 700, padding: '9px 18px', cursor: 'pointer' }}>
          + Registrar Anilha
        </button>
      </div>

      {/* Stats */}
      {stats && (
        <div style={{ display: 'grid', gridTemplateColumns: 'repeat(3, 1fr)', gap: 14, marginBottom: 20 }}>
          {[
            { label: 'Total de Anilhas', value: stats.total_rings, color: '#1a3d2b' },
            { label: 'Atribuídas', value: stats.assigned_rings, color: '#2d6a4f' },
            { label: 'Disponíveis', value: stats.unassigned_rings, color: '#c8922a' },
          ].map(s => (
            <div key={s.label} style={{ background: '#fff', borderRadius: 12, border: '1px solid #e0e8e2', padding: '16px 20px', textAlign: 'center' }}>
              <div style={{ fontSize: 28, fontWeight: 700, color: s.color, fontFamily: "'Cormorant Garamond',serif" }}>{s.value}</div>
              <div style={{ fontSize: 11, color: '#6b8a76', marginTop: 2 }}>{s.label}</div>
            </div>
          ))}
        </div>
      )}

      {/* Create form */}
      {showForm && (
        <div style={{ background: '#fff', borderRadius: 16, border: '1px solid #e0e8e2', padding: 24, marginBottom: 20, boxShadow: '0 4px 20px rgba(26,61,43,0.08)' }}>
          <h2 style={{ fontFamily: "'Cormorant Garamond',serif", fontSize: 20, color: '#1a3d2b', marginBottom: 16 }}>Registrar Anilha</h2>
          <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr 1fr', gap: 14, marginBottom: 14 }}>
            <div>
              <label style={LABEL_STYLE}>Número da Anilha *</label>
              <input value={form.ring_number} onChange={e => setForm(f => ({ ...f, ring_number: e.target.value }))} style={INPUT_STYLE} placeholder="Ex: BR-2024-1234" />
            </div>
            <div>
              <label style={LABEL_STYLE}>Ano</label>
              <input type="number" value={form.year} onChange={e => setForm(f => ({ ...f, year: e.target.value }))} style={INPUT_STYLE} placeholder="2024" />
            </div>
            <div>
              <label style={LABEL_STYLE}>Cor</label>
              <input value={form.color} onChange={e => setForm(f => ({ ...f, color: e.target.value }))} style={INPUT_STYLE} placeholder="Ex: Amarela" />
            </div>
            <div>
              <label style={LABEL_STYLE}>Espécie</label>
              <select value={form.species_id} onChange={e => setForm(f => ({ ...f, species_id: e.target.value }))} style={INPUT_STYLE}>
                <option value="">Selecione...</option>
                {(species as { id: string; common_name: string }[]).map((s) => <option key={s.id} value={s.id}>{s.common_name}</option>)}
              </select>
            </div>
            <div>
              <label style={LABEL_STYLE}>Origem / Associação</label>
              <input value={form.source} onChange={e => setForm(f => ({ ...f, source: e.target.value }))} style={INPUT_STYLE} placeholder="Ex: CBP" />
            </div>
          </div>
          <div style={{ marginBottom: 14 }}>
            <label style={LABEL_STYLE}>Observações</label>
            <textarea value={form.notes} onChange={e => setForm(f => ({ ...f, notes: e.target.value }))} rows={2} style={{ ...INPUT_STYLE, resize: 'vertical' }} />
          </div>
          <div style={{ display: 'flex', gap: 10 }}>
            <button onClick={handleCreate} disabled={createMutation.isPending} style={{ background: '#2d6a4f', border: 'none', borderRadius: 10, color: '#faf6f0', fontSize: 13, fontWeight: 700, padding: '10px 24px', cursor: 'pointer' }}>
              {createMutation.isPending ? 'Salvando...' : '✓ Salvar Anilha'}
            </button>
            <button onClick={() => setShowForm(false)} style={{ background: '#fff', border: '1px solid #c8d4c8', borderRadius: 10, color: '#4a6a54', fontSize: 13, padding: '10px 18px', cursor: 'pointer' }}>
              Cancelar
            </button>
          </div>
        </div>
      )}

      {/* Search */}
      <div style={{ marginBottom: 16 }}>
        <input value={search} onChange={e => setSearch(e.target.value)} placeholder="Filtrar por número ou nome da ave..." style={{ ...INPUT_STYLE, maxWidth: 380 }} />
      </div>

      {/* Table */}
      <div style={{ background: '#fff', borderRadius: 16, border: '1px solid #e0e8e2', overflow: 'hidden', boxShadow: '0 2px 12px rgba(26,61,43,0.06)' }}>
        <table style={{ width: '100%', borderCollapse: 'collapse' }}>
          <thead>
            <tr style={{ background: '#f5f8f5' }}>
              {['Número', 'Ano', 'Cor', 'Espécie', 'Ave', 'Origem', 'Data', 'Ações'].map(h => (
                <th key={h} style={{ padding: '10px 14px', textAlign: 'left', fontSize: 11, fontWeight: 700, color: '#4a6a54', borderBottom: '1px solid #e0e8e2' }}>{h}</th>
              ))}
            </tr>
          </thead>
          <tbody>
            {isLoading ? (
              <tr><td colSpan={8} style={{ padding: 24, textAlign: 'center', color: '#6b8a76', fontSize: 13 }}>Carregando...</td></tr>
            ) : filtered.length === 0 ? (
              <tr><td colSpan={8} style={{ padding: 24, textAlign: 'center', color: '#6b8a76', fontSize: 13 }}>Nenhuma anilha encontrada</td></tr>
            ) : filtered.map((r, i) => (
              <tr key={r.id} style={{ borderTop: i > 0 ? '1px solid #f0f4f0' : 'none', background: i % 2 === 0 ? '#fff' : '#fafcfa' }}>
                <td style={{ padding: '10px 14px', fontSize: 13, fontWeight: 600, color: '#1e2620' }}>{r.ring_number}</td>
                <td style={{ padding: '10px 14px', fontSize: 12, color: '#4a6a54' }}>{r.year || '—'}</td>
                <td style={{ padding: '10px 14px', fontSize: 12, color: '#4a6a54' }}>{r.color || '—'}</td>
                <td style={{ padding: '10px 14px', fontSize: 12, color: '#4a6a54' }}>{r.species_name || '—'}</td>
                <td style={{ padding: '10px 14px', fontSize: 12, color: r.bird_name ? '#2d6a4f' : '#9ab5a0', fontWeight: r.bird_name ? 600 : 400 }}>
                  {r.bird_name || 'Não atribuída'}
                </td>
                <td style={{ padding: '10px 14px', fontSize: 11, color: '#6b8a76' }}>{r.source || '—'}</td>
                <td style={{ padding: '10px 14px', fontSize: 11, color: '#6b8a76' }}>{new Date(r.created_at).toLocaleDateString('pt-BR')}</td>
                <td style={{ padding: '10px 14px' }}>
                  <div style={{ display: 'flex', gap: 6 }}>
                    {!r.bird_id && (
                      <button onClick={() => { setAssignModal(r.id); setAssignBirdId('') }} style={{ background: '#e8f0e8', border: 'none', borderRadius: 6, color: '#2d6a4f', fontSize: 10, fontWeight: 600, padding: '4px 10px', cursor: 'pointer' }}>
                        Associar
                      </button>
                    )}
                    {!r.bird_id && (
                      <button onClick={() => { if (confirm('Excluir anilha?')) deleteMutation.mutate(r.id) }} style={{ background: '#fdf0ed', border: 'none', borderRadius: 6, color: '#c45c3d', fontSize: 10, fontWeight: 600, padding: '4px 10px', cursor: 'pointer' }}>
                        Excluir
                      </button>
                    )}
                    {r.bird_id && <span style={{ fontSize: 10, color: '#c8922a', fontWeight: 600 }}>🔒 Em uso</span>}
                  </div>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>

      {/* Assign modal */}
      {assignModal && (
        <div style={{ position: 'fixed', inset: 0, background: 'rgba(0,0,0,0.5)', display: 'flex', alignItems: 'center', justifyContent: 'center', zIndex: 1000 }}>
          <div style={{ background: '#faf6f0', borderRadius: 16, padding: 28, width: 380, boxShadow: '0 8px 40px rgba(0,0,0,0.3)' }}>
            <h3 style={{ fontFamily: "'Cormorant Garamond',serif", fontSize: 20, color: '#1a3d2b', marginBottom: 16 }}>Associar Anilha a uma Ave</h3>
            <div style={{ marginBottom: 16 }}>
              <label style={LABEL_STYLE}>Selecionar Ave</label>
              <select value={assignBirdId} onChange={e => setAssignBirdId(e.target.value)} style={INPUT_STYLE}>
                <option value="">Selecione...</option>
                {(birds as { id: string; name: string }[]).map(b => <option key={b.id} value={b.id}>{b.name}</option>)}
              </select>
            </div>
            <div style={{ display: 'flex', gap: 10 }}>
              <button onClick={() => { if (assignBirdId) assignMutation.mutate({ id: assignModal, bird_id: assignBirdId }) }} disabled={!assignBirdId || assignMutation.isPending} style={{ flex: 1, background: '#1a3d2b', border: 'none', borderRadius: 10, color: '#faf6f0', fontSize: 13, fontWeight: 700, padding: '10px 0', cursor: 'pointer', opacity: assignBirdId ? 1 : 0.5 }}>
                Confirmar
              </button>
              <button onClick={() => setAssignModal(null)} style={{ background: '#fff', border: '1px solid #c8d4c8', borderRadius: 10, color: '#4a6a54', fontSize: 13, padding: '10px 18px', cursor: 'pointer' }}>
                Cancelar
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  )
}
