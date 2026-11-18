import { useState } from 'react'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import { vaccinesApi, birdsApi } from '@/services/api'
import api from '@/services/api'
import type { Bird, VaccineProduct, VaccinationRecord } from '@/types'
import { differenceInDays } from 'date-fns'

const BR_STATES = ['AC','AL','AP','AM','BA','CE','DF','ES','GO','MA','MT','MS','MG','PA','PB','PR','PE','PI','RJ','RN','RS','RO','RR','SC','SP','SE','TO']

const INPUT_STYLE = {
  width: '100%', border: '1px solid #c8d4c8', borderRadius: 8, padding: '9px 12px',
  fontSize: 13, color: '#1e2620', background: '#fafcfa', outline: 'none', boxSizing: 'border-box' as const,
}
const LABEL_STYLE = { display: 'block', fontSize: 11, fontWeight: 600 as const, color: '#4a6a54', marginBottom: 5 }

interface VaccRecordExt extends VaccinationRecord {
  applied_by_name?: string
  applied_by_crmv_state?: string
  applied_by_crmv_number?: string
  lot_number_manual?: string
  record_hash?: string
}

export default function VaccinesPage() {
  const { t } = useTranslation()
  const qc = useQueryClient()
  const [showBatch, setShowBatch] = useState(false)
  const [batchSelection, setBatchSelection] = useState('all')
  const [selectedBirds, setSelectedBirds] = useState<string[]>([])
  const [batchForm, setBatchForm] = useState({ name: '', vaccine_product_id: '', application_date: new Date().toISOString().split('T')[0] })

  // Individual record form state
  const [selectedBirdId, setSelectedBirdId] = useState('')
  const [indivForm, setIndivForm] = useState({
    vaccine_product_id: '',
    application_date: new Date().toISOString().split('T')[0],
    route_used: 'SC',
    dose_ml_applied: '',
    applied_by_name: '',
    applied_by_crmv_state: '',
    applied_by_crmv_number: '',
    lot_number_manual: '',
    notes: '',
  })

  const { data: upcoming = [] } = useQuery({ queryKey: ['vaccines-upcoming'], queryFn: () => vaccinesApi.schedule(30).then(r => r.data as VaccinationRecord[]) })
  const { data: products = [] } = useQuery({ queryKey: ['vaccine-products'], queryFn: () => vaccinesApi.products().then(r => r.data as VaccineProduct[]) })
  const { data: birds = [] } = useQuery({ queryKey: ['birds'], queryFn: () => birdsApi.list().then(r => r.data as Bird[]) })

  const { data: birdRecords = [] } = useQuery<VaccRecordExt[]>({
    queryKey: ['vaccine-records', selectedBirdId],
    queryFn: () => selectedBirdId ? api.get(`/vaccines/birds/${selectedBirdId}/records`).then(r => r.data) : [],
    enabled: !!selectedBirdId,
  })

  const toggleBird = (id: string) =>
    setSelectedBirds(prev => prev.includes(id) ? prev.filter(b => b !== id) : [...prev, id])

  const lotMutation = useMutation({
    mutationFn: (data: unknown) => vaccinesApi.createLot(data),
    onSuccess: () => { qc.invalidateQueries({ queryKey: ['vaccines-upcoming'] }); setShowBatch(false) },
  })

  const recordMutation = useMutation({
    mutationFn: (data: unknown) => api.post('/vaccines/records', data),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['vaccines-upcoming'] })
      qc.invalidateQueries({ queryKey: ['vaccine-records', selectedBirdId] })
    },
    onError: (e: unknown) => alert((e as { response?: { data?: { detail?: string } } })?.response?.data?.detail || 'Erro'),
  })

  const deleteMutation = useMutation({
    mutationFn: (id: string) => api.delete(`/vaccines/records/${id}`),
    onSuccess: () => qc.invalidateQueries({ queryKey: ['vaccine-records', selectedBirdId] }),
  })

  const handleConfirmLot = () => {
    if (!batchForm.name || !batchForm.vaccine_product_id) return alert('Preencha nome e vacina')
    lotMutation.mutate({
      ...batchForm,
      selection_type: batchSelection,
      bird_ids: batchSelection === 'manual' ? selectedBirds : undefined,
    })
  }

  const handleSaveRecord = () => {
    if (!selectedBirdId) return alert('Selecione uma ave')
    if (!indivForm.vaccine_product_id) return alert('Selecione a vacina')
    recordMutation.mutate({
      bird_id: selectedBirdId,
      vaccine_product_id: indivForm.vaccine_product_id,
      application_date: new Date(indivForm.application_date).toISOString(),
      route_used: indivForm.route_used || undefined,
      dose_ml_applied: indivForm.dose_ml_applied ? parseFloat(indivForm.dose_ml_applied) : undefined,
      applied_by_name: indivForm.applied_by_name || undefined,
      applied_by_crmv_state: indivForm.applied_by_crmv_state || undefined,
      applied_by_crmv_number: indivForm.applied_by_crmv_number || undefined,
      lot_number_manual: indivForm.lot_number_manual || undefined,
      notes: indivForm.notes || undefined,
    })
  }

  return (
    <div>
      <div style={{ marginBottom: 24, display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start' }}>
        <div>
          <h1 style={{ fontFamily: "'Cormorant Garamond',serif", fontSize: 30, fontWeight: 700, color: '#1a3d2b' }}>{t('vaccine.title')}</h1>
          <p style={{ color: '#6b8a76', fontSize: 13, marginTop: 4 }}>Registros individuais e vacinação em lote</p>
        </div>
        <div style={{ display: 'flex', gap: 10 }}>
          <button onClick={() => setShowBatch(false)} style={{ background: !showBatch ? '#1a3d2b' : '#fff', color: !showBatch ? '#faf6f0' : '#2d6a4f', border: '1px solid #2d6a4f', borderRadius: 8, fontSize: 12, fontWeight: 600, padding: '8px 16px', cursor: 'pointer' }}>
            📋 {t('vaccine.individual')}
          </button>
          <button onClick={() => setShowBatch(true)} style={{ background: showBatch ? '#1a3d2b' : '#fff', color: showBatch ? '#faf6f0' : '#2d6a4f', border: '1px solid #2d6a4f', borderRadius: 8, fontSize: 12, fontWeight: 600, padding: '8px 16px', cursor: 'pointer' }}>
            🗂️ {t('vaccine.batch')}
          </button>
        </div>
      </div>

      {/* Upcoming vaccines */}
      {upcoming.length > 0 && (
        <div style={{ background: '#fff9f5', border: '1px solid #f0c8b0', borderRadius: 12, padding: 16, marginBottom: 20 }}>
          <div style={{ fontSize: 12, fontWeight: 700, color: '#c45c3d', marginBottom: 10 }}>⚠️ {t('dashboard.vaccines_due')}</div>
          {upcoming.map((v, i) => {
            const days = differenceInDays(new Date(v.next_dose_date!), new Date())
            return (
              <div key={v.id} style={{ display: 'flex', alignItems: 'center', gap: 12, padding: '6px 0', borderTop: i > 0 ? '1px solid #f5e8e0' : 'none' }}>
                <span style={{ fontSize: 18 }}>🕊️</span>
                <div style={{ flex: 1 }}>
                  <span style={{ fontSize: 12, color: '#8a6a3a' }}>Dose {v.dose_number}</span>
                </div>
                <span style={{ fontSize: 11, color: '#7a9a84' }}>{new Date(v.next_dose_date!).toLocaleDateString('pt-BR')}</span>
                <span style={{ fontSize: 10, fontWeight: 700, background: days <= 7 ? '#fdf0ed' : '#fdf4e8', color: days <= 7 ? '#c45c3d' : '#c8922a', borderRadius: 5, padding: '2px 8px' }}>D-{days}</span>
              </div>
            )
          })}
        </div>
      )}

      {/* Batch form */}
      {showBatch && (
        <div style={{ background: '#fff', borderRadius: 16, border: '1px solid #e0e8e2', padding: 24, boxShadow: '0 4px 20px rgba(26,61,43,0.08)' }}>
          <h2 style={{ fontFamily: "'Cormorant Garamond',serif", fontSize: 22, fontWeight: 700, color: '#1a3d2b', marginBottom: 20 }}>🗂️ {t('vaccine.batch')}</h2>
          <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 16, marginBottom: 20 }}>
            <div>
              <label style={LABEL_STYLE}>{t('vaccine.batch_name')}</label>
              <input value={batchForm.name} onChange={e => setBatchForm(f => ({ ...f, name: e.target.value }))} style={INPUT_STYLE} />
            </div>
            <div>
              <label style={LABEL_STYLE}>{t('vaccine.product')}</label>
              <select value={batchForm.vaccine_product_id} onChange={e => setBatchForm(f => ({ ...f, vaccine_product_id: e.target.value }))} style={INPUT_STYLE}>
                <option value="">Selecione...</option>
                {products.map(p => <option key={p.id} value={p.id}>{p.name_pt}</option>)}
              </select>
            </div>
            <div>
              <label style={LABEL_STYLE}>{t('vaccine.date')}</label>
              <input type="date" value={batchForm.application_date} onChange={e => setBatchForm(f => ({ ...f, application_date: e.target.value }))} style={INPUT_STYLE} />
            </div>
          </div>

          <div style={{ marginBottom: 16 }}>
            <label style={LABEL_STYLE}>{t('vaccine.select_how')}</label>
            <div style={{ display: 'flex', gap: 8, flexWrap: 'wrap' }}>
              {(['all', 'by_species', 'by_location', 'manual'] as const).map(opt => (
                <button key={opt} onClick={() => setBatchSelection(opt)} style={{ border: `1px solid ${batchSelection === opt ? '#1a3d2b' : '#c8d4c8'}`, borderRadius: 7, padding: '6px 14px', background: batchSelection === opt ? '#1a3d2b' : '#fff', color: batchSelection === opt ? '#faf6f0' : '#4a6a54', fontSize: 11, fontWeight: 600, cursor: 'pointer' }}>
                  {t(`vaccine.${opt}`)}
                </button>
              ))}
            </div>
          </div>

          {batchSelection === 'manual' && (
            <div style={{ background: '#f5f8f5', borderRadius: 10, padding: 14, marginBottom: 16, border: '1px solid #e0e8e2' }}>
              <div style={{ fontSize: 11, fontWeight: 600, color: '#4a6a54', marginBottom: 10 }}>Selecionar aves:</div>
              <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 8 }}>
                {birds.map(bird => (
                  <div key={bird.id} onClick={() => toggleBird(bird.id)} style={{ display: 'flex', alignItems: 'center', gap: 10, padding: '8px 12px', borderRadius: 8, cursor: 'pointer', border: `1px solid ${selectedBirds.includes(bird.id) ? '#1a3d2b' : '#e0e8e2'}`, background: selectedBirds.includes(bird.id) ? '#e8f0e8' : '#fff' }}>
                    <span style={{ fontSize: 16 }}>{bird.species?.category === 'pigeon' ? '🕊️' : '🦜'}</span>
                    <div>
                      <div style={{ fontSize: 12, fontWeight: 600, color: '#1e2620' }}>{bird.name}</div>
                      <div style={{ fontSize: 10, color: '#7a9a84' }}>{bird.species?.common_name}</div>
                    </div>
                    <div style={{ marginLeft: 'auto', width: 16, height: 16, borderRadius: 4, background: selectedBirds.includes(bird.id) ? '#1a3d2b' : '#fff', border: `1.5px solid ${selectedBirds.includes(bird.id) ? '#1a3d2b' : '#c8d4c8'}`, display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
                      {selectedBirds.includes(bird.id) && <span style={{ color: '#fff', fontSize: 9 }}>✓</span>}
                    </div>
                  </div>
                ))}
              </div>
              {selectedBirds.length > 0 && <div style={{ marginTop: 8, fontSize: 11, color: '#2d6a4f', fontWeight: 600 }}>{selectedBirds.length} aves selecionadas</div>}
            </div>
          )}

          <button onClick={handleConfirmLot} disabled={lotMutation.isPending} style={{ background: '#2d6a4f', border: 'none', borderRadius: 10, color: '#faf6f0', fontSize: 13, fontWeight: 700, padding: '12px 28px', cursor: 'pointer', letterSpacing: '0.04em', boxShadow: '0 4px 16px rgba(45,106,79,0.3)' }}>
            ✓ {lotMutation.isPending ? 'Aplicando...' : t('vaccine.confirm')}
          </button>
        </div>
      )}

      {/* Individual record */}
      {!showBatch && (
        <div style={{ background: '#fff', borderRadius: 16, border: '1px solid #e0e8e2', padding: 24, boxShadow: '0 4px 20px rgba(26,61,43,0.08)' }}>
          <h2 style={{ fontFamily: "'Cormorant Garamond',serif", fontSize: 22, fontWeight: 700, color: '#1a3d2b', marginBottom: 20 }}>📋 {t('vaccine.individual')}</h2>

          {/* Bird selector */}
          <div style={{ marginBottom: 16 }}>
            <label style={LABEL_STYLE}>Ave</label>
            <select value={selectedBirdId} onChange={e => setSelectedBirdId(e.target.value)} style={INPUT_STYLE}>
              <option value="">Selecione a ave...</option>
              {birds.map(b => <option key={b.id} value={b.id}>{b.name}</option>)}
            </select>
          </div>

          <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 16, marginBottom: 16 }}>
            <div>
              <label style={LABEL_STYLE}>{t('vaccine.product')}</label>
              <select value={indivForm.vaccine_product_id} onChange={e => setIndivForm(f => ({ ...f, vaccine_product_id: e.target.value }))} style={INPUT_STYLE}>
                <option value="">Selecione a vacina...</option>
                {products.map(p => <option key={p.id} value={p.id}>{p.name_pt}</option>)}
              </select>
            </div>
            <div>
              <label style={LABEL_STYLE}>{t('vaccine.date')}</label>
              <input type="date" value={indivForm.application_date} onChange={e => setIndivForm(f => ({ ...f, application_date: e.target.value }))} style={INPUT_STYLE} />
            </div>
            <div>
              <label style={LABEL_STYLE}>Via de aplicação</label>
              <select value={indivForm.route_used} onChange={e => setIndivForm(f => ({ ...f, route_used: e.target.value }))} style={INPUT_STYLE}>
                <option value="SC">Subcutânea (SC)</option>
                <option value="IM">Intramuscular (IM)</option>
                <option value="oral">Oral</option>
                <option value="ocular">Ocular</option>
              </select>
            </div>
            <div>
              <label style={LABEL_STYLE}>Dose aplicada (ml)</label>
              <input type="number" step="0.01" value={indivForm.dose_ml_applied} onChange={e => setIndivForm(f => ({ ...f, dose_ml_applied: e.target.value }))} style={INPUT_STYLE} placeholder="0.5" />
            </div>
          </div>

          {/* Vet fields */}
          <div style={{ background: '#f5f8f5', borderRadius: 10, padding: 16, marginBottom: 16, border: '1px solid #e0e8e2' }}>
            <div style={{ fontSize: 11, fontWeight: 700, color: '#4a6a54', marginBottom: 12 }}>Dados do Veterinário Responsável</div>
            <div style={{ display: 'grid', gridTemplateColumns: '2fr 1fr 1fr', gap: 12 }}>
              <div>
                <label style={LABEL_STYLE}>Nome do Veterinário Responsável</label>
                <input value={indivForm.applied_by_name} onChange={e => setIndivForm(f => ({ ...f, applied_by_name: e.target.value }))} style={INPUT_STYLE} placeholder="Dr. Nome Completo" />
              </div>
              <div>
                <label style={LABEL_STYLE}>Estado CRMV</label>
                <select value={indivForm.applied_by_crmv_state} onChange={e => setIndivForm(f => ({ ...f, applied_by_crmv_state: e.target.value }))} style={INPUT_STYLE}>
                  <option value="">UF</option>
                  {BR_STATES.map(s => <option key={s} value={s}>{s}</option>)}
                </select>
              </div>
              <div>
                <label style={LABEL_STYLE}>Número CRMV</label>
                <input value={indivForm.applied_by_crmv_number} onChange={e => setIndivForm(f => ({ ...f, applied_by_crmv_number: e.target.value }))} style={INPUT_STYLE} placeholder="12345" />
              </div>
            </div>
            <div style={{ marginTop: 12 }}>
              <label style={LABEL_STYLE}>Número do Lote</label>
              <input value={indivForm.lot_number_manual} onChange={e => setIndivForm(f => ({ ...f, lot_number_manual: e.target.value }))} style={INPUT_STYLE} placeholder="Ex: LOT2024-001" />
            </div>
          </div>

          <div style={{ marginBottom: 16 }}>
            <label style={LABEL_STYLE}>Observações</label>
            <textarea value={indivForm.notes} onChange={e => setIndivForm(f => ({ ...f, notes: e.target.value }))} rows={2} style={{ ...INPUT_STYLE, resize: 'vertical' }} />
          </div>

          <button onClick={handleSaveRecord} disabled={recordMutation.isPending} style={{ background: '#2d6a4f', border: 'none', borderRadius: 10, color: '#faf6f0', fontSize: 13, fontWeight: 700, padding: '10px 24px', cursor: 'pointer' }}>
            ✓ {recordMutation.isPending ? 'Salvando...' : 'Salvar Registro'}
          </button>

          {/* Records list for selected bird */}
          {selectedBirdId && birdRecords.length > 0 && (
            <div style={{ marginTop: 24 }}>
              <h3 style={{ fontFamily: "'Cormorant Garamond',serif", fontSize: 16, color: '#1a3d2b', marginBottom: 12 }}>Registros anteriores</h3>
              <div style={{ display: 'grid', gap: 8 }}>
                {birdRecords.map(r => (
                  <div key={r.id} style={{ background: '#f5f8f5', borderRadius: 10, border: '1px solid #e0e8e2', padding: '12px 16px', display: 'flex', alignItems: 'center', gap: 12 }}>
                    <div style={{ flex: 1 }}>
                      <div style={{ display: 'flex', alignItems: 'center', gap: 10, marginBottom: 4 }}>
                        <span style={{ fontSize: 12, fontWeight: 700, color: '#1e2620' }}>Dose {r.dose_number}</span>
                        <span style={{ fontSize: 11, color: '#6b8a76' }}>{new Date(r.application_date).toLocaleDateString('pt-BR')}</span>
                        {r.record_hash && (
                          <span title={`Hash completo: ${r.record_hash}`} style={{ fontSize: 9, background: '#e8f0e8', color: '#2d6a4f', borderRadius: 4, padding: '1px 6px', fontFamily: 'monospace', cursor: 'help' }}>
                            🔒 {r.record_hash.slice(0, 8)}...
                          </span>
                        )}
                      </div>
                      <div style={{ display: 'flex', gap: 10, fontSize: 10, color: '#6b8a76' }}>
                        {r.applied_by_name && <span>Vet: {r.applied_by_name}{r.applied_by_crmv_state ? ` CRMV-${r.applied_by_crmv_state}` : ''}{r.applied_by_crmv_number ? ` ${r.applied_by_crmv_number}` : ''}</span>}
                        {r.lot_number_manual && <span>Lote: {r.lot_number_manual}</span>}
                      </div>
                    </div>
                    <button onClick={() => { if (confirm('Excluir registro?')) deleteMutation.mutate(r.id) }} style={{ background: '#fdf0ed', border: 'none', borderRadius: 6, color: '#c45c3d', fontSize: 10, fontWeight: 600, padding: '4px 10px', cursor: 'pointer' }}>
                      Excluir
                    </button>
                  </div>
                ))}
              </div>
            </div>
          )}
        </div>
      )}
    </div>
  )
}
