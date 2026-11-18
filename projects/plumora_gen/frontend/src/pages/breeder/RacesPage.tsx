import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import { useState } from 'react'
import { racesApi } from '@/services/api'
import type { Race } from '@/types'

export default function RacesPage() {
  const { t } = useTranslation()
  const qc = useQueryClient()
  const [showForm, setShowForm] = useState(false)
  const [form, setForm] = useState({ name: '', date: new Date().toISOString().split('T')[0], distance_km: '', organizer: '', category: '', notes: '' })

  const { data: races = [] } = useQuery({ queryKey: ['races'], queryFn: () => racesApi.list().then(r => r.data as Race[]) })

  const createMutation = useMutation({
    mutationFn: (data: unknown) => racesApi.create(data),
    onSuccess: () => { qc.invalidateQueries({ queryKey: ['races'] }); setShowForm(false) },
  })

  return (
    <div>
      <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: 24 }}>
        <h1 style={{ fontFamily: "'Cormorant Garamond',serif", fontSize: 30, fontWeight: 700, color: '#1a3d2b' }}>{t('nav.races')}</h1>
        <button onClick={() => setShowForm(!showForm)} style={{ background: '#1a3d2b', border: 'none', borderRadius: 8, color: '#faf6f0', fontSize: 12, fontWeight: 700, padding: '9px 18px', cursor: 'pointer' }}>
          {showForm ? '✕ Cancelar' : '+ Nova Prova'}
        </button>
      </div>

      {showForm && (
        <div style={{ background: '#fff', borderRadius: 16, border: '1px solid #e0e8e2', padding: 24, marginBottom: 24 }}>
          <h2 style={{ fontFamily: "'Cormorant Garamond',serif", fontSize: 20, color: '#1a3d2b', marginBottom: 18 }}>Nova Prova</h2>
          <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 16, marginBottom: 16 }}>
            {[
              { label: 'Nome da Prova', field: 'name' },
              { label: 'Data', field: 'date', type: 'date' },
              { label: 'Distância (km)', field: 'distance_km', type: 'number' },
              { label: 'Organizador', field: 'organizer' },
              { label: 'Categoria', field: 'category' },
            ].map(({ label, field, type = 'text' }) => (
              <div key={field}>
                <label style={{ display: 'block', fontSize: 11, fontWeight: 600, color: '#4a6a54', marginBottom: 5 }}>{label}</label>
                <input type={type} value={form[field as keyof typeof form]} onChange={e => setForm(f => ({ ...f, [field]: e.target.value }))} style={{ width: '100%', border: '1px solid #c8d4c8', borderRadius: 8, padding: '9px 12px', fontSize: 13, outline: 'none' }} />
              </div>
            ))}
          </div>
          <button onClick={() => createMutation.mutate({ ...form, date: new Date(form.date).toISOString(), distance_km: form.distance_km ? parseFloat(form.distance_km) : undefined })} disabled={!form.name || createMutation.isPending} style={{ background: '#2d6a4f', border: 'none', borderRadius: 10, color: '#faf6f0', fontSize: 13, fontWeight: 700, padding: '10px 24px', cursor: 'pointer' }}>
            ✓ {createMutation.isPending ? 'Salvando...' : 'Registrar Prova'}
          </button>
        </div>
      )}

      <div style={{ display: 'grid', gap: 12 }}>
        {races.map(race => (
          <div key={race.id} style={{ background: '#fff', borderRadius: 12, border: '1px solid #e0e8e2', padding: 18, display: 'flex', alignItems: 'center', justifyContent: 'space-between' }}>
            <div style={{ display: 'flex', alignItems: 'center', gap: 16 }}>
              <span style={{ fontSize: 28 }}>🏆</span>
              <div>
                <div style={{ fontSize: 15, fontWeight: 700, color: '#1e2620' }}>{race.name}</div>
                <div style={{ fontSize: 11, color: '#7a9a84' }}>{new Date(race.date).toLocaleDateString('pt-BR')} {race.distance_km ? `· ${race.distance_km}km` : ''}</div>
              </div>
            </div>
            <span style={{ background: '#e8f0e8', color: '#2d6a4f', borderRadius: 6, padding: '4px 10px', fontSize: 11, fontWeight: 600 }}>{race.category || 'Aberta'}</span>
          </div>
        ))}
        {races.length === 0 && (
          <div style={{ textAlign: 'center', padding: 60, color: '#9ab5a0' }}>
            <div style={{ fontSize: 48 }}>🏆</div>
            <h2 style={{ fontFamily: "'Cormorant Garamond',serif", fontSize: 24, color: '#1a3d2b', margin: '12px 0 8px' }}>Nenhuma prova registrada</h2>
          </div>
        )}
      </div>
    </div>
  )
}
