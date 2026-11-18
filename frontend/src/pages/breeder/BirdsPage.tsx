import { useState } from 'react'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import { birdsApi } from '@/services/api'
import type { Bird } from '@/types'

export default function BirdsPage() {
  const { t } = useTranslation()
  const qc = useQueryClient()
  const [showForm, setShowForm] = useState(false)
  const [form, setForm] = useState({ name: '', ring_number: '', sex: 'unknown', notes: '' })

  const { data: birds = [], isLoading } = useQuery({
    queryKey: ['birds'],
    queryFn: () => birdsApi.list().then(r => r.data as Bird[]),
  })

  const createMutation = useMutation({
    mutationFn: (data: typeof form) => birdsApi.create(data),
    onSuccess: () => { qc.invalidateQueries({ queryKey: ['birds'] }); setShowForm(false); setForm({ name: '', ring_number: '', sex: 'unknown', notes: '' }) },
  })

  const deleteMutation = useMutation({
    mutationFn: (id: string) => birdsApi.delete(id),
    onSuccess: () => qc.invalidateQueries({ queryKey: ['birds'] }),
  })

  const handleExport = async () => {
    const res = await birdsApi.exportCsv()
    const blob = new Blob([res.data], { type: 'text/csv;charset=utf-8' })
    const url = URL.createObjectURL(blob)
    const a = document.createElement('a')
    a.href = url
    a.download = `plantel-${new Date().toISOString().split('T')[0]}.csv`
    document.body.appendChild(a)
    a.click()
    a.remove()
    URL.revokeObjectURL(url)
  }

  if (isLoading) return <div style={{ textAlign: 'center', padding: 60, color: '#7a9a84' }}>Carregando...</div>

  return (
    <div>
      <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: 24 }}>
        <h1 style={{ fontFamily: "'Cormorant Garamond',serif", fontSize: 30, fontWeight: 700, color: '#1a3d2b' }}>{t('nav.birds')}</h1>
        <div style={{ display: 'flex', gap: 10 }}>
          <button onClick={handleExport} style={{ background: '#fff', border: '1px solid #2d6a4f', borderRadius: 8, color: '#2d6a4f', fontSize: 12, fontWeight: 700, padding: '9px 18px', cursor: 'pointer' }}>
            ⬇ Exportar Planilha
          </button>
          <button onClick={() => setShowForm(!showForm)} style={{ background: '#1a3d2b', border: 'none', borderRadius: 8, color: '#faf6f0', fontSize: 12, fontWeight: 700, padding: '9px 18px', cursor: 'pointer' }}>
            {showForm ? '✕ Cancelar' : t('birds.add')}
          </button>
        </div>
      </div>

      {showForm && (
        <div style={{ background: '#fff', borderRadius: 16, border: '1px solid #e0e8e2', padding: 24, marginBottom: 24, boxShadow: '0 4px 20px rgba(26,61,43,0.08)' }}>
          <h2 style={{ fontFamily: "'Cormorant Garamond',serif", fontSize: 22, fontWeight: 700, color: '#1a3d2b', marginBottom: 20 }}>Nova Ave</h2>
          <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 16, marginBottom: 20 }}>
            {[
              { label: 'Nome', field: 'name', type: 'text' },
              { label: t('birds.ring'), field: 'ring_number', type: 'text' },
            ].map(({ label, field, type }) => (
              <div key={field}>
                <label style={{ display: 'block', fontSize: 11, fontWeight: 600, color: '#4a6a54', marginBottom: 5 }}>{label}</label>
                <input type={type} value={form[field as keyof typeof form]} onChange={e => setForm(f => ({ ...f, [field]: e.target.value }))} style={{ width: '100%', border: '1px solid #c8d4c8', borderRadius: 8, padding: '9px 12px', fontSize: 13, color: '#1e2620', background: '#fafcfa', outline: 'none' }} />
              </div>
            ))}
            <div>
              <label style={{ display: 'block', fontSize: 11, fontWeight: 600, color: '#4a6a54', marginBottom: 5 }}>{t('birds.sex')}</label>
              <select value={form.sex} onChange={e => setForm(f => ({ ...f, sex: e.target.value }))} style={{ width: '100%', border: '1px solid #c8d4c8', borderRadius: 8, padding: '9px 12px', fontSize: 13, color: '#1e2620', background: '#fafcfa', outline: 'none' }}>
                <option value="M">{t('birds.male')}</option>
                <option value="F">{t('birds.female')}</option>
                <option value="unknown">Indeterminado</option>
              </select>
            </div>
            <div>
              <label style={{ display: 'block', fontSize: 11, fontWeight: 600, color: '#4a6a54', marginBottom: 5 }}>Observações</label>
              <input value={form.notes} onChange={e => setForm(f => ({ ...f, notes: e.target.value }))} style={{ width: '100%', border: '1px solid #c8d4c8', borderRadius: 8, padding: '9px 12px', fontSize: 13, color: '#1e2620', background: '#fafcfa', outline: 'none' }} />
            </div>
          </div>
          <button onClick={() => createMutation.mutate(form)} disabled={createMutation.isPending || !form.name} style={{ background: '#2d6a4f', border: 'none', borderRadius: 10, color: '#faf6f0', fontSize: 13, fontWeight: 700, padding: '10px 24px', cursor: 'pointer', boxShadow: '0 4px 16px rgba(45,106,79,0.3)' }}>
            ✓ {createMutation.isPending ? 'Salvando...' : t('actions.save')}
          </button>
        </div>
      )}

      <div style={{ display: 'grid', gridTemplateColumns: 'repeat(3,1fr)', gap: 16 }}>
        {birds.map(bird => (
          <div key={bird.id} style={{ background: '#fff', borderRadius: 16, border: '1px solid #e0e8e2', padding: 20, boxShadow: '0 2px 10px rgba(26,61,43,0.06)', cursor: 'pointer' }}>
            <div style={{ width: '100%', height: 80, borderRadius: 10, marginBottom: 14, background: 'linear-gradient(135deg,#e8f0e8,#c8d4c8)', display: 'flex', alignItems: 'center', justifyContent: 'center', fontSize: 40 }}>
              {bird.species?.category === 'pigeon' ? '🕊️' : '🦜'}
            </div>
            <div style={{ fontWeight: 700, fontSize: 16, color: '#1e2620', marginBottom: 2 }}>{bird.name}</div>
            <div style={{ fontSize: 11, color: '#7a9a84', marginBottom: 8 }}>{bird.ring_number || '—'}</div>
            <div style={{ display: 'flex', gap: 6, flexWrap: 'wrap', marginBottom: 10 }}>
              <span style={{ fontSize: 10, background: '#e8f0e8', color: '#2d6a4f', borderRadius: 4, padding: '2px 7px', fontWeight: 600 }}>{bird.species?.common_name || 'Espécie não definida'}</span>
              <span style={{ fontSize: 10, background: bird.sex === 'M' ? '#ddeeff' : '#ffdde8', color: bird.sex === 'M' ? '#3366aa' : '#aa3366', borderRadius: 4, padding: '2px 7px', fontWeight: 600 }}>
                {bird.sex === 'M' ? '♂' : bird.sex === 'F' ? '♀' : '?'}
              </span>
              <span style={{ fontSize: 10, background: '#f0f4f1', color: '#6b8a76', borderRadius: 4, padding: '2px 7px' }}>{bird.status}</span>
            </div>
            <button onClick={(e) => { e.stopPropagation(); if (confirm('Excluir esta ave?')) deleteMutation.mutate(bird.id) }} style={{ background: 'none', border: '1px solid #f0c8b0', borderRadius: 6, color: '#c45c3d', fontSize: 10, padding: '3px 8px', cursor: 'pointer' }}>
              Excluir
            </button>
          </div>
        ))}
        {birds.length === 0 && (
          <div style={{ gridColumn: '1/-1', textAlign: 'center', padding: 60, color: '#9ab5a0' }}>
            <div style={{ fontSize: 48, marginBottom: 12 }}>🐦</div>
            <h2 style={{ fontFamily: "'Cormorant Garamond',serif", fontSize: 24, color: '#1a3d2b', marginBottom: 8 }}>Nenhuma ave cadastrada</h2>
            <p style={{ fontSize: 14 }}>Clique em "+ Adicionar Ave" para começar.</p>
          </div>
        )}
      </div>
    </div>
  )
}
