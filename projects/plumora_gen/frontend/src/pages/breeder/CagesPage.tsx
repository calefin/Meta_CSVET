import { useState } from 'react'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import { cagesApi } from '@/services/api'
import type { Cage } from '@/types'

const INPUT_STYLE = {
  width: '100%', border: '1px solid #c8d4c8', borderRadius: 8, padding: '9px 12px',
  fontSize: 13, color: '#1e2620', background: '#fafcfa', outline: 'none', boxSizing: 'border-box' as const,
}
const LABEL_STYLE = { display: 'block', fontSize: 11, fontWeight: 600 as const, color: '#4a6a54', marginBottom: 5 }

export default function CagesPage() {
  const { t } = useTranslation()
  const qc = useQueryClient()
  const [showForm, setShowForm] = useState(false)
  const [editingId, setEditingId] = useState<string | null>(null)
  const [form, setForm] = useState({ name: '', location: '', notes: '' })

  const { data: cages = [], isLoading } = useQuery({
    queryKey: ['cages'],
    queryFn: () => cagesApi.list().then(r => r.data as Cage[]),
  })

  const resetForm = () => { setShowForm(false); setEditingId(null); setForm({ name: '', location: '', notes: '' }) }

  const createMutation = useMutation({
    mutationFn: (data: typeof form) => cagesApi.create(data),
    onSuccess: () => { qc.invalidateQueries({ queryKey: ['cages'] }); resetForm() },
  })

  const updateMutation = useMutation({
    mutationFn: ({ id, data }: { id: string; data: typeof form }) => cagesApi.update(id, data),
    onSuccess: () => { qc.invalidateQueries({ queryKey: ['cages'] }); resetForm() },
  })

  const archiveMutation = useMutation({
    mutationFn: (id: string) => cagesApi.remove(id),
    onSuccess: () => qc.invalidateQueries({ queryKey: ['cages'] }),
  })

  const startEdit = (cage: Cage) => {
    setEditingId(cage.id)
    setForm({ name: cage.name, location: cage.location || '', notes: cage.notes || '' })
    setShowForm(true)
  }

  const handleSave = () => {
    if (!form.name.trim()) return
    if (editingId) updateMutation.mutate({ id: editingId, data: form })
    else createMutation.mutate(form)
  }

  if (isLoading) return <div style={{ textAlign: 'center', padding: 60, color: '#7a9a84' }}>Carregando...</div>

  return (
    <div>
      <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: 24 }}>
        <h1 style={{ fontFamily: "'Cormorant Garamond',serif", fontSize: 30, fontWeight: 700, color: '#1a3d2b' }}>{t('nav.cages')}</h1>
        <button onClick={() => (showForm ? resetForm() : setShowForm(true))} style={{ background: '#1a3d2b', border: 'none', borderRadius: 8, color: '#faf6f0', fontSize: 12, fontWeight: 700, padding: '9px 18px', cursor: 'pointer' }}>
          {showForm ? '✕ Cancelar' : t('cages.add')}
        </button>
      </div>

      {showForm && (
        <div style={{ background: '#fff', borderRadius: 16, border: '1px solid #e0e8e2', padding: 24, marginBottom: 24, boxShadow: '0 4px 20px rgba(26,61,43,0.08)' }}>
          <h2 style={{ fontFamily: "'Cormorant Garamond',serif", fontSize: 20, color: '#1a3d2b', marginBottom: 16 }}>
            {editingId ? t('actions.edit') : t('cages.add')}
          </h2>
          <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 16, marginBottom: 16 }}>
            <div>
              <label style={LABEL_STYLE}>{t('cages.name')} *</label>
              <input value={form.name} onChange={e => setForm(f => ({ ...f, name: e.target.value }))} style={INPUT_STYLE} placeholder="Ex: Gaiola 12" />
            </div>
            <div>
              <label style={LABEL_STYLE}>{t('cages.location')}</label>
              <input value={form.location} onChange={e => setForm(f => ({ ...f, location: e.target.value }))} style={INPUT_STYLE} placeholder="Ex: Galpão A" />
            </div>
          </div>
          <div style={{ marginBottom: 16 }}>
            <label style={LABEL_STYLE}>Observações</label>
            <textarea value={form.notes} onChange={e => setForm(f => ({ ...f, notes: e.target.value }))} rows={2} style={{ ...INPUT_STYLE, resize: 'vertical' }} />
          </div>
          <button onClick={handleSave} disabled={!form.name.trim() || createMutation.isPending || updateMutation.isPending} style={{ background: '#2d6a4f', border: 'none', borderRadius: 10, color: '#faf6f0', fontSize: 13, fontWeight: 700, padding: '10px 24px', cursor: 'pointer' }}>
            ✓ {createMutation.isPending || updateMutation.isPending ? 'Salvando...' : t('actions.save')}
          </button>
        </div>
      )}

      <div style={{ display: 'grid', gridTemplateColumns: 'repeat(3,1fr)', gap: 16 }}>
        {cages.map(cage => (
          <div key={cage.id} style={{ background: '#fff', borderRadius: 16, border: '1px solid #e0e8e2', padding: 20, boxShadow: '0 2px 10px rgba(26,61,43,0.06)', opacity: cage.is_active ? 1 : 0.6 }}>
            <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start', marginBottom: 8 }}>
              <div style={{ fontWeight: 700, fontSize: 16, color: '#1e2620' }}>🏠 {cage.name}</div>
              <span style={{ fontSize: 10, background: cage.is_active ? '#e8f0e8' : '#f0f4f1', color: cage.is_active ? '#2d6a4f' : '#8aa08f', borderRadius: 4, padding: '2px 7px', fontWeight: 600 }}>
                {cage.is_active ? t('cages.active') : t('cages.archived')}
              </span>
            </div>
            <div style={{ fontSize: 12, color: '#6b8a76', marginBottom: 4 }}>{cage.location || '—'}</div>
            {cage.notes && <div style={{ fontSize: 12, color: '#9ab5a0', marginBottom: 10 }}>{cage.notes}</div>}
            <div style={{ display: 'flex', gap: 8, marginTop: 10 }}>
              <button onClick={() => startEdit(cage)} style={{ background: '#e8f0e8', border: 'none', borderRadius: 6, color: '#2d6a4f', fontSize: 10, fontWeight: 600, padding: '4px 10px', cursor: 'pointer' }}>
                {t('actions.edit')}
              </button>
              {cage.is_active && (
                <button onClick={() => { if (confirm('Arquivar esta gaiola?')) archiveMutation.mutate(cage.id) }} style={{ background: '#fdf0ed', border: 'none', borderRadius: 6, color: '#c45c3d', fontSize: 10, fontWeight: 600, padding: '4px 10px', cursor: 'pointer' }}>
                  {t('cages.archive')}
                </button>
              )}
            </div>
          </div>
        ))}
        {cages.length === 0 && (
          <div style={{ gridColumn: '1/-1', textAlign: 'center', padding: 60, color: '#9ab5a0' }}>
            <div style={{ fontSize: 48, marginBottom: 12 }}>🏠</div>
            <h2 style={{ fontFamily: "'Cormorant Garamond',serif", fontSize: 24, color: '#1a3d2b', marginBottom: 8 }}>Nenhuma gaiola cadastrada</h2>
          </div>
        )}
      </div>
    </div>
  )
}
