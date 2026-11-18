import { useState } from 'react'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import api from '@/services/api'

const INPUT_STYLE = {
  width: '100%', border: '1px solid #c8d4c8', borderRadius: 8, padding: '9px 12px',
  fontSize: 13, color: '#1e2620', background: '#fafcfa', outline: 'none', boxSizing: 'border-box' as const,
}
const LABEL_STYLE = { display: 'block', fontSize: 11, fontWeight: 600 as const, color: '#4a6a54', marginBottom: 5 }

interface FeedProduct {
  id: string
  name: string
  brand?: string
  type: string
  composition_notes?: string
  calories_per_100g?: number
}

interface FeedLog {
  id: string
  bird_id: string
  bird_name?: string
  feed_id: string
  feed_name?: string
  date: string
  quantity_grams?: number
  notes?: string
  created_at: string
}

const FEED_TYPES = [
  { value: 'seed', label: 'Semente' },
  { value: 'pellet', label: 'Pellet' },
  { value: 'fresh', label: 'Fresco/Natural' },
  { value: 'supplement', label: 'Suplemento' },
  { value: 'grain', label: 'Grão' },
  { value: 'other', label: 'Outro' },
]

export default function FeedPage() {
  const qc = useQueryClient()
  const [tab, setTab] = useState<'logs' | 'products'>('logs')
  const [showLogForm, setShowLogForm] = useState(false)
  const [showProductForm, setShowProductForm] = useState(false)
  const [logForm, setLogForm] = useState({ bird_id: '', feed_id: '', amount_grams: '', date: new Date().toISOString().split('T')[0], notes: '' })
  const [productForm, setProductForm] = useState({ name: '', brand: '', type: 'seed', composition_notes: '', calories_per_100g: '' })

  const { data: logs = [], isLoading } = useQuery<FeedLog[]>({
    queryKey: ['feed-logs'],
    queryFn: () => api.get('/feed/logs').then(r => r.data),
  })

  const { data: products = [] } = useQuery<FeedProduct[]>({
    queryKey: ['feed-products'],
    queryFn: () => api.get('/feed/products').then(r => r.data),
  })

  const { data: birds = [] } = useQuery({
    queryKey: ['birds'],
    queryFn: () => api.get('/birds').then(r => r.data),
  })

  const createLogMutation = useMutation({
    mutationFn: (data: unknown) => api.post('/feed/logs', data),
    onSuccess: () => { qc.invalidateQueries({ queryKey: ['feed-logs'] }); setShowLogForm(false) },
    onError: (e: unknown) => alert((e as { response?: { data?: { detail?: string } } })?.response?.data?.detail || 'Erro'),
  })

  const deleteLogMutation = useMutation({
    mutationFn: (id: string) => api.delete(`/feed/logs/${id}`),
    onSuccess: () => qc.invalidateQueries({ queryKey: ['feed-logs'] }),
  })

  const createProductMutation = useMutation({
    mutationFn: (data: unknown) => api.post('/feed/products', data),
    onSuccess: () => { qc.invalidateQueries({ queryKey: ['feed-products'] }); setShowProductForm(false) },
  })

  const handleCreateLog = () => {
    if (!logForm.bird_id) return alert('Selecione uma ave')
    if (!logForm.feed_id) return alert('Selecione um alimento')
    createLogMutation.mutate({
      bird_id: logForm.bird_id,
      feed_id: logForm.feed_id,
      amount_grams: logForm.amount_grams ? parseFloat(logForm.amount_grams) : undefined,
      date: new Date(logForm.date).toISOString(),
      notes: logForm.notes || undefined,
    })
  }

  const handleCreateProduct = () => {
    if (!productForm.name.trim()) return alert('Nome do alimento obrigatório')
    createProductMutation.mutate({
      name: productForm.name,
      brand: productForm.brand || undefined,
      type: productForm.type,
      composition_notes: productForm.composition_notes || undefined,
      calories_per_100g: productForm.calories_per_100g ? parseFloat(productForm.calories_per_100g) : undefined,
    })
  }

  const TAB_BTN = (key: 'logs' | 'products', label: string) => (
    <button onClick={() => setTab(key)} style={{ background: tab === key ? '#1a3d2b' : '#fff', color: tab === key ? '#faf6f0' : '#2d6a4f', border: '1px solid #2d6a4f', borderRadius: 8, fontSize: 12, fontWeight: 600, padding: '8px 18px', cursor: 'pointer' }}>
      {label}
    </button>
  )

  return (
    <div>
      <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start', marginBottom: 24 }}>
        <div>
          <h1 style={{ fontFamily: "'Cormorant Garamond',serif", fontSize: 30, fontWeight: 700, color: '#1a3d2b' }}>🌾 Alimentação</h1>
          <p style={{ color: '#6b8a76', fontSize: 13, marginTop: 4 }}>Registros de alimentação e catálogo de alimentos</p>
        </div>
        <div style={{ display: 'flex', gap: 8 }}>
          {TAB_BTN('logs', '📋 Registros')}
          {TAB_BTN('products', '🌿 Alimentos')}
        </div>
      </div>

      {tab === 'logs' && (
        <>
          <div style={{ display: 'flex', justifyContent: 'flex-end', marginBottom: 16 }}>
            <button onClick={() => setShowLogForm(!showLogForm)} style={{ background: '#1a3d2b', border: 'none', borderRadius: 8, color: '#faf6f0', fontSize: 12, fontWeight: 700, padding: '9px 18px', cursor: 'pointer' }}>
              + Registrar Alimentação
            </button>
          </div>

          {showLogForm && (
            <div style={{ background: '#fff', borderRadius: 16, border: '1px solid #e0e8e2', padding: 24, marginBottom: 20, boxShadow: '0 4px 20px rgba(26,61,43,0.08)' }}>
              <h3 style={{ fontFamily: "'Cormorant Garamond',serif", fontSize: 18, color: '#1a3d2b', marginBottom: 16 }}>Novo Registro de Alimentação</h3>
              <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 14, marginBottom: 14 }}>
                <div>
                  <label style={LABEL_STYLE}>Ave *</label>
                  <select value={logForm.bird_id} onChange={e => setLogForm(f => ({ ...f, bird_id: e.target.value }))} style={INPUT_STYLE}>
                    <option value="">Selecione...</option>
                    {(birds as { id: string; name: string }[]).map(b => <option key={b.id} value={b.id}>{b.name}</option>)}
                  </select>
                </div>
                <div>
                  <label style={LABEL_STYLE}>Alimento *</label>
                  <select value={logForm.feed_id} onChange={e => setLogForm(f => ({ ...f, feed_id: e.target.value }))} style={INPUT_STYLE}>
                    <option value="">Selecione...</option>
                    {products.map(p => <option key={p.id} value={p.id}>{p.name}{p.brand ? ` — ${p.brand}` : ''}</option>)}
                  </select>
                </div>
                <div>
                  <label style={LABEL_STYLE}>Quantidade (g)</label>
                  <input type="number" value={logForm.amount_grams} onChange={e => setLogForm(f => ({ ...f, amount_grams: e.target.value }))} style={INPUT_STYLE} placeholder="50" />
                </div>
                <div>
                  <label style={LABEL_STYLE}>Data *</label>
                  <input type="date" value={logForm.date} onChange={e => setLogForm(f => ({ ...f, date: e.target.value }))} style={INPUT_STYLE} />
                </div>
              </div>
              <div style={{ marginBottom: 14 }}>
                <label style={LABEL_STYLE}>Observações</label>
                <textarea value={logForm.notes} onChange={e => setLogForm(f => ({ ...f, notes: e.target.value }))} rows={2} style={{ ...INPUT_STYLE, resize: 'vertical' }} />
              </div>
              <div style={{ display: 'flex', gap: 10 }}>
                <button onClick={handleCreateLog} disabled={createLogMutation.isPending} style={{ background: '#2d6a4f', border: 'none', borderRadius: 10, color: '#faf6f0', fontSize: 13, fontWeight: 700, padding: '10px 24px', cursor: 'pointer' }}>
                  {createLogMutation.isPending ? 'Salvando...' : '✓ Salvar'}
                </button>
                <button onClick={() => setShowLogForm(false)} style={{ background: '#fff', border: '1px solid #c8d4c8', borderRadius: 10, color: '#4a6a54', fontSize: 13, padding: '10px 18px', cursor: 'pointer' }}>
                  Cancelar
                </button>
              </div>
            </div>
          )}

          <div style={{ display: 'grid', gap: 10 }}>
            {isLoading ? (
              <div style={{ padding: 24, textAlign: 'center', color: '#6b8a76' }}>Carregando...</div>
            ) : logs.length === 0 ? (
              <div style={{ background: '#fff', borderRadius: 12, border: '1px solid #e0e8e2', padding: 24, textAlign: 'center', color: '#6b8a76', fontSize: 13 }}>
                Nenhum registro. Adicione o primeiro.
              </div>
            ) : logs.map(log => (
              <div key={log.id} style={{ background: '#fff', borderRadius: 12, border: '1px solid #e0e8e2', padding: 16, display: 'flex', alignItems: 'center', gap: 16 }}>
                <div style={{ flex: 1 }}>
                  <div style={{ display: 'flex', alignItems: 'center', gap: 10, marginBottom: 4 }}>
                    <span style={{ fontSize: 13, fontWeight: 700, color: '#1e2620' }}>{log.feed_name}</span>
                    {log.bird_name && <span style={{ fontSize: 11, background: '#e8f0e8', color: '#2d6a4f', borderRadius: 5, padding: '1px 8px', fontWeight: 600 }}>{log.bird_name}</span>}
                  </div>
                  <div style={{ display: 'flex', gap: 14, fontSize: 11, color: '#6b8a76' }}>
                    <span>📅 {new Date(log.date).toLocaleDateString('pt-BR')}</span>
                    {log.quantity_grams && <span>⚖️ {log.quantity_grams}g</span>}
                  </div>
                  {log.notes && <div style={{ fontSize: 11, color: '#9ab5a0', marginTop: 4, fontStyle: 'italic' }}>{log.notes}</div>}
                </div>
                <button onClick={() => { if (confirm('Excluir registro?')) deleteLogMutation.mutate(log.id) }} style={{ background: '#fdf0ed', border: 'none', borderRadius: 8, color: '#c45c3d', fontSize: 11, fontWeight: 600, padding: '6px 12px', cursor: 'pointer' }}>
                  Excluir
                </button>
              </div>
            ))}
          </div>
        </>
      )}

      {tab === 'products' && (
        <>
          <div style={{ display: 'flex', justifyContent: 'flex-end', marginBottom: 16 }}>
            <button onClick={() => setShowProductForm(!showProductForm)} style={{ background: '#1a3d2b', border: 'none', borderRadius: 8, color: '#faf6f0', fontSize: 12, fontWeight: 700, padding: '9px 18px', cursor: 'pointer' }}>
              + Adicionar Alimento
            </button>
          </div>

          {showProductForm && (
            <div style={{ background: '#fff', borderRadius: 16, border: '1px solid #e0e8e2', padding: 24, marginBottom: 20, boxShadow: '0 4px 20px rgba(26,61,43,0.08)' }}>
              <h3 style={{ fontFamily: "'Cormorant Garamond',serif", fontSize: 18, color: '#1a3d2b', marginBottom: 16 }}>Novo Alimento</h3>
              <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 14, marginBottom: 14 }}>
                <div>
                  <label style={LABEL_STYLE}>Nome *</label>
                  <input value={productForm.name} onChange={e => setProductForm(f => ({ ...f, name: e.target.value }))} style={INPUT_STYLE} placeholder="Ex: Ração Premium para Calopsita" />
                </div>
                <div>
                  <label style={LABEL_STYLE}>Marca</label>
                  <input value={productForm.brand} onChange={e => setProductForm(f => ({ ...f, brand: e.target.value }))} style={INPUT_STYLE} />
                </div>
                <div>
                  <label style={LABEL_STYLE}>Tipo</label>
                  <select value={productForm.type} onChange={e => setProductForm(f => ({ ...f, type: e.target.value }))} style={INPUT_STYLE}>
                    {FEED_TYPES.map(t => <option key={t.value} value={t.value}>{t.label}</option>)}
                  </select>
                </div>
                <div>
                  <label style={LABEL_STYLE}>Calorias por 100g</label>
                  <input type="number" value={productForm.calories_per_100g} onChange={e => setProductForm(f => ({ ...f, calories_per_100g: e.target.value }))} style={INPUT_STYLE} placeholder="320" />
                </div>
              </div>
              <div style={{ marginBottom: 14 }}>
                <label style={LABEL_STYLE}>Composição / Observações</label>
                <textarea value={productForm.composition_notes} onChange={e => setProductForm(f => ({ ...f, composition_notes: e.target.value }))} rows={2} style={{ ...INPUT_STYLE, resize: 'vertical' }} />
              </div>
              <div style={{ display: 'flex', gap: 10 }}>
                <button onClick={handleCreateProduct} disabled={createProductMutation.isPending} style={{ background: '#2d6a4f', border: 'none', borderRadius: 10, color: '#faf6f0', fontSize: 13, fontWeight: 700, padding: '10px 24px', cursor: 'pointer' }}>
                  {createProductMutation.isPending ? 'Salvando...' : '✓ Salvar'}
                </button>
                <button onClick={() => setShowProductForm(false)} style={{ background: '#fff', border: '1px solid #c8d4c8', borderRadius: 10, color: '#4a6a54', fontSize: 13, padding: '10px 18px', cursor: 'pointer' }}>
                  Cancelar
                </button>
              </div>
            </div>
          )}

          <div style={{ background: '#fff', borderRadius: 16, border: '1px solid #e0e8e2', overflow: 'hidden' }}>
            <table style={{ width: '100%', borderCollapse: 'collapse' }}>
              <thead>
                <tr style={{ background: '#f5f8f5' }}>
                  {['Nome', 'Marca', 'Tipo', 'Cal/100g', 'Composição'].map(h => (
                    <th key={h} style={{ padding: '10px 14px', textAlign: 'left', fontSize: 11, fontWeight: 700, color: '#4a6a54', borderBottom: '1px solid #e0e8e2' }}>{h}</th>
                  ))}
                </tr>
              </thead>
              <tbody>
                {products.length === 0 ? (
                  <tr><td colSpan={5} style={{ padding: 24, textAlign: 'center', color: '#6b8a76', fontSize: 13 }}>Nenhum alimento cadastrado</td></tr>
                ) : products.map((p, i) => (
                  <tr key={p.id} style={{ borderTop: i > 0 ? '1px solid #f0f4f0' : 'none', background: i % 2 === 0 ? '#fff' : '#fafcfa' }}>
                    <td style={{ padding: '10px 14px', fontSize: 13, fontWeight: 600, color: '#1e2620' }}>{p.name}</td>
                    <td style={{ padding: '10px 14px', fontSize: 12, color: '#4a6a54' }}>{p.brand || '—'}</td>
                    <td style={{ padding: '10px 14px', fontSize: 11, color: '#6b8a76' }}>{FEED_TYPES.find(t => t.value === p.type)?.label || p.type}</td>
                    <td style={{ padding: '10px 14px', fontSize: 12, color: '#4a6a54' }}>{p.calories_per_100g ? `${p.calories_per_100g} kcal` : '—'}</td>
                    <td style={{ padding: '10px 14px', fontSize: 11, color: '#6b8a76', maxWidth: 200, overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>{p.composition_notes || '—'}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </>
      )}
    </div>
  )
}
