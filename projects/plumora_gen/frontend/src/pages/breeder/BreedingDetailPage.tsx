import { useEffect, useState } from 'react'
import { useParams, Link } from 'react-router-dom'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import { breedingApi, birdsApi, cagesApi } from '@/services/api'
import type { Bird, Breeding, Cage, Clutch, Offspring, Species } from '@/types'

const INPUT_STYLE = {
  width: '100%', border: '1px solid #c8d4c8', borderRadius: 8, padding: '9px 12px',
  fontSize: 13, color: '#1e2620', background: '#fafcfa', outline: 'none', boxSizing: 'border-box' as const,
}
const LABEL_STYLE = { display: 'block', fontSize: 11, fontWeight: 600 as const, color: '#4a6a54', marginBottom: 5 }
const CARD_STYLE = { background: '#fff', borderRadius: 16, border: '1px solid #e0e8e2', padding: 20, marginBottom: 20 }
const SECTION_TITLE = { fontFamily: "'Cormorant Garamond',serif", fontSize: 20, fontWeight: 700, color: '#1a3d2b', marginBottom: 14 }

const STATUS_LABELS: Record<string, string> = {
  paired: 'status_paired', incubating: 'status_incubating', hatched: 'status_hatched',
  failed: 'status_failed', completed: 'status_completed',
}

function errorDetail(e: unknown): string {
  return (e as { response?: { data?: { detail?: string } } })?.response?.data?.detail || 'Erro ao salvar'
}

function OffspringRow({ offspring, birdsById }: { offspring: Offspring; birdsById: Record<string, Bird> }) {
  const { t } = useTranslation()
  const qc = useQueryClient()
  const [showPromote, setShowPromote] = useState(false)
  const { data: species = [] } = useQuery({ queryKey: ['species'], queryFn: () => birdsApi.species().then(r => r.data as Species[]) })
  const [promoteForm, setPromoteForm] = useState({ name: '', sex: 'unknown', species_id: '', color: '' })

  const promotedBird = offspring.bird_id ? birdsById[offspring.bird_id] : undefined

  const promoteMutation = useMutation({
    mutationFn: () => breedingApi.promoteOffspring(offspring.id, {
      name: promoteForm.name,
      sex: promoteForm.sex,
      species_id: promoteForm.species_id || undefined,
      color: promoteForm.color || undefined,
    }),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['offspring', offspring.clutch_id] })
      qc.invalidateQueries({ queryKey: ['birds'] })
      setShowPromote(false)
    },
    onError: (e: unknown) => alert(errorDetail(e)),
  })

  const deleteMutation = useMutation({
    mutationFn: () => breedingApi.removeOffspring(offspring.id),
    onSuccess: () => qc.invalidateQueries({ queryKey: ['offspring', offspring.clutch_id] }),
    onError: (e: unknown) => alert(errorDetail(e)),
  })

  return (
    <div style={{ border: '1px solid #f0f4f0', borderRadius: 10, padding: 12, marginBottom: 8 }}>
      <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
        <div style={{ fontSize: 13, color: '#1e2620' }}>
          <strong>{offspring.ring_number || 'Sem anilha'}</strong>
          {offspring.hatch_date && <span style={{ color: '#7a9a84' }}> · eclodiu em {new Date(offspring.hatch_date).toLocaleDateString('pt-BR')}</span>}
          {offspring.hatch_weight_g && <span style={{ color: '#7a9a84' }}> · {offspring.hatch_weight_g}g</span>}
        </div>
        {promotedBird ? (
          <Link to="/app/birds" style={{ fontSize: 10, background: '#e8f0e8', color: '#2d6a4f', borderRadius: 4, padding: '3px 8px', fontWeight: 600, textDecoration: 'none' }}>
            ✓ {t('breeding.promoted')}: {promotedBird.name}
          </Link>
        ) : (
          <div style={{ display: 'flex', gap: 6 }}>
            <button onClick={() => setShowPromote(v => !v)} style={{ background: '#e8f0e8', border: 'none', borderRadius: 6, color: '#2d6a4f', fontSize: 10, fontWeight: 600, padding: '4px 10px', cursor: 'pointer' }}>
              {t('breeding.promote')}
            </button>
            <button onClick={() => { if (confirm('Excluir este registro de filhote?')) deleteMutation.mutate() }} style={{ background: '#fdf0ed', border: 'none', borderRadius: 6, color: '#c45c3d', fontSize: 10, fontWeight: 600, padding: '4px 10px', cursor: 'pointer' }}>
              {t('actions.delete')}
            </button>
          </div>
        )}
      </div>

      {showPromote && (
        <div style={{ marginTop: 10, paddingTop: 10, borderTop: '1px solid #f0f4f0', display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 10 }}>
          <div>
            <label style={LABEL_STYLE}>Nome *</label>
            <input value={promoteForm.name} onChange={e => setPromoteForm(f => ({ ...f, name: e.target.value }))} style={INPUT_STYLE} />
          </div>
          <div>
            <label style={LABEL_STYLE}>{t('birds.sex')}</label>
            <select value={promoteForm.sex} onChange={e => setPromoteForm(f => ({ ...f, sex: e.target.value }))} style={INPUT_STYLE}>
              <option value="unknown">Indeterminado</option>
              <option value="M">{t('birds.male')}</option>
              <option value="F">{t('birds.female')}</option>
            </select>
          </div>
          <div>
            <label style={LABEL_STYLE}>{t('birds.species')}</label>
            <select value={promoteForm.species_id} onChange={e => setPromoteForm(f => ({ ...f, species_id: e.target.value }))} style={INPUT_STYLE}>
              <option value="">Herdar do pai</option>
              {species.map(s => <option key={s.id} value={s.id}>{s.common_name}</option>)}
            </select>
          </div>
          <div>
            <label style={LABEL_STYLE}>Cor</label>
            <input value={promoteForm.color} onChange={e => setPromoteForm(f => ({ ...f, color: e.target.value }))} style={INPUT_STYLE} />
          </div>
          <div style={{ gridColumn: '1/-1' }}>
            <button onClick={() => promoteMutation.mutate()} disabled={!promoteForm.name.trim() || promoteMutation.isPending} style={{ background: '#2d6a4f', border: 'none', borderRadius: 8, color: '#faf6f0', fontSize: 12, fontWeight: 700, padding: '8px 18px', cursor: 'pointer' }}>
              ✓ {promoteMutation.isPending ? 'Promovendo...' : t('breeding.promote')}
            </button>
          </div>
        </div>
      )}
    </div>
  )
}

function ClutchCard({ clutch, birdsById }: { clutch: Clutch; birdsById: Record<string, Bird> }) {
  const { t } = useTranslation()
  const qc = useQueryClient()
  const [editing, setEditing] = useState(false)
  const [editForm, setEditForm] = useState({
    fertile_count: clutch.fertile_count ?? '', hatched_count: clutch.hatched_count ?? '', candling_notes: clutch.candling_notes || '',
  })
  const [showOffspringForm, setShowOffspringForm] = useState(false)
  const [offspringForm, setOffspringForm] = useState({ hatch_date: '', hatch_weight_g: '', ring_number: '', notes: '' })

  const { data: offspring = [] } = useQuery({
    queryKey: ['offspring', clutch.id],
    queryFn: () => breedingApi.listOffspring(clutch.id).then(r => r.data as Offspring[]),
  })

  const updateClutchMutation = useMutation({
    mutationFn: () => breedingApi.updateClutch(clutch.id, {
      fertile_count: editForm.fertile_count === '' ? undefined : Number(editForm.fertile_count),
      hatched_count: editForm.hatched_count === '' ? undefined : Number(editForm.hatched_count),
      candling_notes: editForm.candling_notes || undefined,
    }),
    onSuccess: () => { qc.invalidateQueries({ queryKey: ['clutches'] }); qc.invalidateQueries({ queryKey: ['breeding-stats'] }); setEditing(false) },
    onError: (e: unknown) => alert(errorDetail(e)),
  })

  const addOffspringMutation = useMutation({
    mutationFn: () => breedingApi.addOffspring(clutch.id, {
      hatch_date: offspringForm.hatch_date ? new Date(offspringForm.hatch_date).toISOString() : undefined,
      hatch_weight_g: offspringForm.hatch_weight_g ? Number(offspringForm.hatch_weight_g) : undefined,
      ring_number: offspringForm.ring_number || undefined,
      notes: offspringForm.notes || undefined,
    }),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['offspring', clutch.id] })
      setShowOffspringForm(false)
      setOffspringForm({ hatch_date: '', hatch_weight_g: '', ring_number: '', notes: '' })
    },
    onError: (e: unknown) => alert(errorDetail(e)),
  })

  return (
    <div style={{ border: '1px solid #e0e8e2', borderRadius: 12, padding: 16, marginBottom: 14 }}>
      <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: 8 }}>
        <div style={{ fontSize: 14, fontWeight: 600, color: '#1e2620' }}>
          🥚 Postura de {new Date(clutch.lay_date).toLocaleDateString('pt-BR')} — {clutch.egg_count} ovo(s)
        </div>
        <button onClick={() => setEditing(v => !v)} style={{ background: 'none', border: '1px solid #c8d4c8', borderRadius: 6, color: '#4a6a54', fontSize: 10, padding: '3px 8px', cursor: 'pointer' }}>
          {t('actions.edit')}
        </button>
      </div>
      <div style={{ fontSize: 12, color: '#6b8a76', marginBottom: 10 }}>
        {t('breeding.fertile_count')}: {clutch.fertile_count ?? '—'} · {t('breeding.hatched_count')}: {clutch.hatched_count ?? '—'}
        {clutch.candling_notes && <div style={{ marginTop: 4 }}>{t('breeding.candling_notes')}: {clutch.candling_notes}</div>}
      </div>

      {editing && (
        <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr 2fr', gap: 10, marginBottom: 12, background: '#fafcfa', padding: 12, borderRadius: 8 }}>
          <div>
            <label style={LABEL_STYLE}>{t('breeding.fertile_count')}</label>
            <input type="number" value={editForm.fertile_count} onChange={e => setEditForm(f => ({ ...f, fertile_count: e.target.value }))} style={INPUT_STYLE} />
          </div>
          <div>
            <label style={LABEL_STYLE}>{t('breeding.hatched_count')}</label>
            <input type="number" value={editForm.hatched_count} onChange={e => setEditForm(f => ({ ...f, hatched_count: e.target.value }))} style={INPUT_STYLE} />
          </div>
          <div>
            <label style={LABEL_STYLE}>{t('breeding.candling_notes')}</label>
            <input value={editForm.candling_notes} onChange={e => setEditForm(f => ({ ...f, candling_notes: e.target.value }))} style={INPUT_STYLE} />
          </div>
          <div style={{ gridColumn: '1/-1' }}>
            <button onClick={() => updateClutchMutation.mutate()} disabled={updateClutchMutation.isPending} style={{ background: '#2d6a4f', border: 'none', borderRadius: 8, color: '#faf6f0', fontSize: 12, fontWeight: 700, padding: '8px 18px', cursor: 'pointer' }}>
              ✓ {t('actions.save')}
            </button>
          </div>
        </div>
      )}

      <div style={{ marginTop: 10 }}>
        <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: 8 }}>
          <div style={{ fontSize: 12, fontWeight: 700, color: '#4a6a54' }}>{t('breeding.offspring')}</div>
          <button onClick={() => setShowOffspringForm(v => !v)} style={{ background: '#e8f0e8', border: 'none', borderRadius: 6, color: '#2d6a4f', fontSize: 10, fontWeight: 600, padding: '4px 10px', cursor: 'pointer' }}>
            {t('breeding.add_offspring')}
          </button>
        </div>

        {showOffspringForm && (
          <div style={{ display: 'grid', gridTemplateColumns: 'repeat(4,1fr)', gap: 10, marginBottom: 12, background: '#fafcfa', padding: 12, borderRadius: 8 }}>
            <div>
              <label style={LABEL_STYLE}>{t('breeding.hatch_date')}</label>
              <input type="date" value={offspringForm.hatch_date} onChange={e => setOffspringForm(f => ({ ...f, hatch_date: e.target.value }))} style={INPUT_STYLE} />
            </div>
            <div>
              <label style={LABEL_STYLE}>{t('breeding.hatch_weight')}</label>
              <input type="number" value={offspringForm.hatch_weight_g} onChange={e => setOffspringForm(f => ({ ...f, hatch_weight_g: e.target.value }))} style={INPUT_STYLE} />
            </div>
            <div>
              <label style={LABEL_STYLE}>{t('breeding.ring_number')}</label>
              <input value={offspringForm.ring_number} onChange={e => setOffspringForm(f => ({ ...f, ring_number: e.target.value }))} style={INPUT_STYLE} />
            </div>
            <div style={{ display: 'flex', alignItems: 'flex-end' }}>
              <button onClick={() => addOffspringMutation.mutate()} disabled={addOffspringMutation.isPending} style={{ background: '#2d6a4f', border: 'none', borderRadius: 8, color: '#faf6f0', fontSize: 12, fontWeight: 700, padding: '9px 16px', cursor: 'pointer', width: '100%' }}>
                ✓ {addOffspringMutation.isPending ? '...' : t('actions.save')}
              </button>
            </div>
          </div>
        )}

        {offspring.map(o => <OffspringRow key={o.id} offspring={o} birdsById={birdsById} />)}
        {offspring.length === 0 && <div style={{ fontSize: 12, color: '#9ab5a0', padding: '8px 0' }}>Nenhum filhote registrado nesta postura ainda.</div>}
      </div>
    </div>
  )
}

export default function BreedingDetailPage() {
  const { id } = useParams<{ id: string }>()
  const { t } = useTranslation()
  const qc = useQueryClient()

  const { data: breeding } = useQuery({
    queryKey: ['breeding', id],
    queryFn: () => breedingApi.get(id as string).then(r => r.data as Breeding),
    enabled: !!id,
  })
  const { data: birds = [] } = useQuery({ queryKey: ['birds'], queryFn: () => birdsApi.list().then(r => r.data as Bird[]) })
  const { data: cages = [] } = useQuery({ queryKey: ['cages'], queryFn: () => cagesApi.list().then(r => r.data as Cage[]) })
  const { data: clutches = [] } = useQuery({
    queryKey: ['clutches', id],
    queryFn: () => breedingApi.listClutches(id as string).then(r => r.data as Clutch[]),
    enabled: !!id,
  })

  const birdsById = Object.fromEntries(birds.map(b => [b.id, b]))
  const cagesById = Object.fromEntries(cages.map(c => [c.id, c]))

  const [editForm, setEditForm] = useState<{ status: string; cage_id: string; separation_date: string; notes: string } | null>(null)
  useEffect(() => {
    if (breeding && !editForm) {
      setEditForm({
        status: breeding.status,
        cage_id: breeding.cage_id || '',
        separation_date: breeding.separation_date ? breeding.separation_date.split('T')[0] : '',
        notes: breeding.notes || '',
      })
    }
  }, [breeding, editForm])

  const [showAddClutch, setShowAddClutch] = useState(false)
  const [clutchForm, setClutchForm] = useState({ lay_date: new Date().toISOString().split('T')[0], egg_count: '' })

  const updateBreedingMutation = useMutation({
    mutationFn: () => breedingApi.update(id as string, {
      status: editForm!.status,
      cage_id: editForm!.cage_id || null,
      separation_date: editForm!.separation_date ? new Date(editForm!.separation_date).toISOString() : null,
      notes: editForm!.notes || undefined,
    }),
    onSuccess: () => qc.invalidateQueries({ queryKey: ['breeding', id] }),
    onError: (e: unknown) => alert(errorDetail(e)),
  })

  const addClutchMutation = useMutation({
    mutationFn: () => breedingApi.addClutch(id as string, {
      lay_date: new Date(clutchForm.lay_date).toISOString(),
      egg_count: clutchForm.egg_count ? Number(clutchForm.egg_count) : 0,
    }),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['clutches', id] })
      qc.invalidateQueries({ queryKey: ['breeding', id] })
      setShowAddClutch(false)
      setClutchForm({ lay_date: new Date().toISOString().split('T')[0], egg_count: '' })
    },
    onError: (e: unknown) => alert(errorDetail(e)),
  })

  if (!breeding || !editForm) return <div style={{ textAlign: 'center', padding: 60, color: '#7a9a84' }}>Carregando...</div>

  const male = birdsById[breeding.male_id]
  const female = birdsById[breeding.female_id]

  return (
    <div>
      <Link to="/app/breeding" style={{ fontSize: 12, color: '#4a6a54', textDecoration: 'none' }}>← Voltar</Link>
      <h1 style={{ fontFamily: "'Cormorant Garamond',serif", fontSize: 30, fontWeight: 700, color: '#1a3d2b', margin: '8px 0 24px' }}>
        {male?.name || '?'} × {female?.name || '?'}
      </h1>

      <div style={CARD_STYLE}>
        <div style={SECTION_TITLE}>Casal</div>
        <div style={{ display: 'grid', gridTemplateColumns: 'repeat(4,1fr)', gap: 14, marginBottom: 14 }}>
          <div>
            <label style={LABEL_STYLE}>{t('breeding.status')}</label>
            <select value={editForm.status} onChange={e => setEditForm(f => ({ ...f!, status: e.target.value }))} style={INPUT_STYLE}>
              {Object.entries(STATUS_LABELS).map(([value, key]) => <option key={value} value={value}>{t(`breeding.${key}`)}</option>)}
            </select>
          </div>
          <div>
            <label style={LABEL_STYLE}>{t('breeding.cage')}</label>
            <select value={editForm.cage_id} onChange={e => setEditForm(f => ({ ...f!, cage_id: e.target.value }))} style={INPUT_STYLE}>
              <option value="">{t('breeding.no_cage')}</option>
              {cages.map(c => <option key={c.id} value={c.id}>{c.name}{!c.is_active ? ' (arquivada)' : ''}</option>)}
            </select>
          </div>
          <div>
            <label style={LABEL_STYLE}>{t('breeding.separation_date')}</label>
            <input type="date" value={editForm.separation_date} onChange={e => setEditForm(f => ({ ...f!, separation_date: e.target.value }))} style={INPUT_STYLE} />
          </div>
          <div>
            <label style={LABEL_STYLE}>Observações</label>
            <input value={editForm.notes} onChange={e => setEditForm(f => ({ ...f!, notes: e.target.value }))} style={INPUT_STYLE} />
          </div>
        </div>
        <button onClick={() => updateBreedingMutation.mutate()} disabled={updateBreedingMutation.isPending} style={{ background: '#2d6a4f', border: 'none', borderRadius: 10, color: '#faf6f0', fontSize: 13, fontWeight: 700, padding: '10px 24px', cursor: 'pointer' }}>
          ✓ {updateBreedingMutation.isPending ? 'Salvando...' : t('actions.save')}
        </button>
      </div>

      <div style={CARD_STYLE}>
        <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: 14 }}>
          <div style={SECTION_TITLE}>{t('breeding.clutches')}</div>
          <button onClick={() => setShowAddClutch(v => !v)} style={{ background: '#1a3d2b', border: 'none', borderRadius: 8, color: '#faf6f0', fontSize: 12, fontWeight: 700, padding: '9px 18px', cursor: 'pointer' }}>
            {t('breeding.add_clutch')}
          </button>
        </div>

        {showAddClutch && (
          <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr auto', gap: 14, marginBottom: 16, background: '#fafcfa', padding: 14, borderRadius: 10 }}>
            <div>
              <label style={LABEL_STYLE}>{t('breeding.lay_date')}</label>
              <input type="date" value={clutchForm.lay_date} onChange={e => setClutchForm(f => ({ ...f, lay_date: e.target.value }))} style={INPUT_STYLE} />
            </div>
            <div>
              <label style={LABEL_STYLE}>{t('breeding.egg_count')}</label>
              <input type="number" value={clutchForm.egg_count} onChange={e => setClutchForm(f => ({ ...f, egg_count: e.target.value }))} style={INPUT_STYLE} />
            </div>
            <div style={{ display: 'flex', alignItems: 'flex-end' }}>
              <button onClick={() => addClutchMutation.mutate()} disabled={addClutchMutation.isPending} style={{ background: '#2d6a4f', border: 'none', borderRadius: 8, color: '#faf6f0', fontSize: 12, fontWeight: 700, padding: '9px 18px', cursor: 'pointer' }}>
                ✓ {t('actions.save')}
              </button>
            </div>
          </div>
        )}

        {clutches.map(c => <ClutchCard key={c.id} clutch={c} birdsById={birdsById} />)}
        {clutches.length === 0 && <div style={{ fontSize: 13, color: '#9ab5a0', textAlign: 'center', padding: 30 }}>Nenhuma postura registrada ainda.</div>}
      </div>
    </div>
  )
}
