import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import { useState } from 'react'
import { Link } from 'react-router-dom'
import { breedingApi, birdsApi, cagesApi } from '@/services/api'
import type { Bird, Breeding, Cage } from '@/types'

const STATUS_LABELS: Record<string, string> = {
  paired: 'status_paired',
  incubating: 'status_incubating',
  hatched: 'status_hatched',
  failed: 'status_failed',
  completed: 'status_completed',
}

function errorDetail(e: unknown): string {
  return (e as { response?: { data?: { detail?: string } } })?.response?.data?.detail || 'Erro ao salvar'
}

export default function BreedingPage() {
  const { t } = useTranslation()
  const qc = useQueryClient()
  const [showForm, setShowForm] = useState(false)
  const [form, setForm] = useState({ male_id: '', female_id: '', cage_id: '', pairing_date: new Date().toISOString().split('T')[0], notes: '' })

  const { data: breedings = [] } = useQuery({ queryKey: ['breedings'], queryFn: () => breedingApi.list().then(r => r.data as Breeding[]) })
  const { data: birds = [] } = useQuery({ queryKey: ['birds'], queryFn: () => birdsApi.list().then(r => r.data as Bird[]) })
  const { data: cages = [] } = useQuery({ queryKey: ['cages', 'active'], queryFn: () => cagesApi.list(true).then(r => r.data as Cage[]) })
  const { data: stats } = useQuery({ queryKey: ['breeding-stats'], queryFn: () => breedingApi.stats().then(r => r.data) })

  const males = birds.filter(b => b.sex === 'M')
  const females = birds.filter(b => b.sex === 'F')
  const birdsById = Object.fromEntries(birds.map(b => [b.id, b]))
  const cagesById = Object.fromEntries(cages.map(c => [c.id, c]))

  const createMutation = useMutation({
    mutationFn: (data: typeof form) =>
      breedingApi.create({ ...data, cage_id: data.cage_id || undefined, pairing_date: new Date(form.pairing_date).toISOString() }),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['breedings'] })
      setShowForm(false)
      setForm({ male_id: '', female_id: '', cage_id: '', pairing_date: new Date().toISOString().split('T')[0], notes: '' })
    },
    onError: (e: unknown) => alert(errorDetail(e)),
  })

  return (
    <div>
      <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: 24 }}>
        <h1 style={{ fontFamily: "'Cormorant Garamond',serif", fontSize: 30, fontWeight: 700, color: '#1a3d2b' }}>{t('nav.breeding')}</h1>
        <button onClick={() => setShowForm(!showForm)} style={{ background: '#1a3d2b', border: 'none', borderRadius: 8, color: '#faf6f0', fontSize: 12, fontWeight: 700, padding: '9px 18px', cursor: 'pointer' }}>
          {showForm ? '✕ Cancelar' : '+ Novo Casal'}
        </button>
      </div>

      {/* Stats */}
      {stats && (
        <div style={{ display: 'grid', gridTemplateColumns: 'repeat(4,1fr)', gap: 16, marginBottom: 24 }}>
          {[
            { label: 'Casais', value: stats.total_pairs, icon: '🥚' },
            { label: 'Ninhadas', value: stats.total_clutches, icon: '🐣' },
            { label: 'Taxa Fertilidade', value: `${stats.fertility_rate_pct}%`, icon: '📊' },
            { label: 'Taxa Eclosão', value: `${stats.hatch_rate_pct}%`, icon: '✓' },
          ].map(s => (
            <div key={s.label} style={{ background: '#fff', borderRadius: 12, border: '1px solid #e0e8e2', padding: '16px 18px', textAlign: 'center' }}>
              <div style={{ fontSize: 24, marginBottom: 6 }}>{s.icon}</div>
              <div style={{ fontSize: 22, fontWeight: 700, color: '#2d6a4f', fontFamily: "'Cormorant Garamond',serif" }}>{s.value}</div>
              <div style={{ fontSize: 11, color: '#7a9a84' }}>{s.label}</div>
            </div>
          ))}
        </div>
      )}

      {showForm && (
        <div style={{ background: '#fff', borderRadius: 16, border: '1px solid #e0e8e2', padding: 24, marginBottom: 24 }}>
          <h2 style={{ fontFamily: "'Cormorant Garamond',serif", fontSize: 20, color: '#1a3d2b', marginBottom: 18 }}>Novo Acasalamento</h2>
          <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 16, marginBottom: 16 }}>
            <div>
              <label style={{ display: 'block', fontSize: 11, fontWeight: 600, color: '#4a6a54', marginBottom: 5 }}>♂ Macho</label>
              <select value={form.male_id} onChange={e => setForm(f => ({ ...f, male_id: e.target.value }))} style={{ width: '100%', border: '1px solid #c8d4c8', borderRadius: 8, padding: '9px 12px', fontSize: 13, outline: 'none' }}>
                <option value="">Selecione...</option>
                {males.map(b => <option key={b.id} value={b.id}>{b.name} ({b.ring_number})</option>)}
              </select>
            </div>
            <div>
              <label style={{ display: 'block', fontSize: 11, fontWeight: 600, color: '#4a6a54', marginBottom: 5 }}>♀ Fêmea</label>
              <select value={form.female_id} onChange={e => setForm(f => ({ ...f, female_id: e.target.value }))} style={{ width: '100%', border: '1px solid #c8d4c8', borderRadius: 8, padding: '9px 12px', fontSize: 13, outline: 'none' }}>
                <option value="">Selecione...</option>
                {females.map(b => <option key={b.id} value={b.id}>{b.name} ({b.ring_number})</option>)}
              </select>
            </div>
            <div>
              <label style={{ display: 'block', fontSize: 11, fontWeight: 600, color: '#4a6a54', marginBottom: 5 }}>{t('breeding.cage')}</label>
              <select value={form.cage_id} onChange={e => setForm(f => ({ ...f, cage_id: e.target.value }))} style={{ width: '100%', border: '1px solid #c8d4c8', borderRadius: 8, padding: '9px 12px', fontSize: 13, outline: 'none' }}>
                <option value="">{t('breeding.no_cage')}</option>
                {cages.map(c => <option key={c.id} value={c.id}>{c.name}</option>)}
              </select>
            </div>
            <div>
              <label style={{ display: 'block', fontSize: 11, fontWeight: 600, color: '#4a6a54', marginBottom: 5 }}>Data do acasalamento</label>
              <input type="date" value={form.pairing_date} onChange={e => setForm(f => ({ ...f, pairing_date: e.target.value }))} style={{ width: '100%', border: '1px solid #c8d4c8', borderRadius: 8, padding: '9px 12px', fontSize: 13, outline: 'none' }} />
            </div>
          </div>
          <button onClick={() => createMutation.mutate(form)} disabled={!form.male_id || !form.female_id || createMutation.isPending} style={{ background: '#2d6a4f', border: 'none', borderRadius: 10, color: '#faf6f0', fontSize: 13, fontWeight: 700, padding: '10px 24px', cursor: 'pointer' }}>
            ✓ {createMutation.isPending ? 'Salvando...' : 'Registrar Casal'}
          </button>
        </div>
      )}

      <div style={{ display: 'grid', gap: 12 }}>
        {breedings.map((b) => {
          const male = birdsById[b.male_id]
          const female = birdsById[b.female_id]
          const cage = b.cage_id ? cagesById[b.cage_id] : undefined
          return (
            <Link key={b.id} to={`/app/breeding/${b.id}`} style={{ textDecoration: 'none' }}>
              <div style={{ background: '#fff', borderRadius: 12, border: '1px solid #e0e8e2', padding: 18, display: 'flex', alignItems: 'center', justifyContent: 'space-between' }}>
                <div style={{ display: 'flex', alignItems: 'center', gap: 16 }}>
                  <span style={{ fontSize: 28 }}>🥚</span>
                  <div>
                    <div style={{ fontSize: 14, fontWeight: 600, color: '#1e2620' }}>
                      {male?.name || '?'} × {female?.name || '?'}
                    </div>
                    <div style={{ fontSize: 11, color: '#7a9a84' }}>
                      {cage ? `🏠 ${cage.name}` : t('breeding.no_cage')} · {new Date(b.pairing_date).toLocaleDateString('pt-BR')}
                    </div>
                  </div>
                </div>
                <span style={{ background: '#e8f0e8', color: '#2d6a4f', borderRadius: 6, padding: '4px 10px', fontSize: 11, fontWeight: 600 }}>
                  {t(`breeding.${STATUS_LABELS[b.status] || b.status}`)}
                </span>
              </div>
            </Link>
          )
        })}
        {breedings.length === 0 && (
          <div style={{ textAlign: 'center', padding: 60, color: '#9ab5a0' }}>
            <div style={{ fontSize: 48 }}>🥚</div>
            <h2 style={{ fontFamily: "'Cormorant Garamond',serif", fontSize: 24, color: '#1a3d2b', margin: '12px 0 8px' }}>Nenhum acasalamento registrado</h2>
          </div>
        )}
      </div>
    </div>
  )
}
