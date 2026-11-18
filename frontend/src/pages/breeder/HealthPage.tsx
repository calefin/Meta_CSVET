import { useState } from 'react'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import { healthApi, birdsApi } from '@/services/api'
import type { Bird, HealthRecord } from '@/types'

const SYMPTOMS = ['Espirros', 'Letargia', 'Perda de apetite', 'Diarreia', 'Corrimento nasal', 'Penas eriçadas', 'Dificuldade respiratória', 'Tremores']

const INPUT_STYLE = {
  width: '100%', border: '1px solid #c8d4c8', borderRadius: 8, padding: '9px 12px',
  fontSize: 13, color: '#1e2620', background: '#fafcfa', outline: 'none', boxSizing: 'border-box' as const,
}
const LABEL_STYLE = { display: 'block', fontSize: 11, fontWeight: 600 as const, color: '#4a6a54', marginBottom: 5 }

export default function HealthPage() {
  const { t } = useTranslation()
  const qc = useQueryClient()
  const [selectedBird, setSelectedBird] = useState<string>('')
  const [showForm, setShowForm] = useState(false)
  const [showVetForm, setShowVetForm] = useState<string | null>(null) // health record id
  const [form, setForm] = useState({ weight_grams: '', temperature_celsius: '', behavior_notes: '', severity: 'normal', symptoms_json: [] as string[] })
  const [vetForm, setVetForm] = useState({
    description: '',
    priority: 'medium',
    appointment_type: 'telemedicine',
    scheduled_date: '',
    scheduled_time: '',
    location_address: '',
  })

  const { data: birds = [] } = useQuery({ queryKey: ['birds'], queryFn: () => birdsApi.list().then(r => r.data as Bird[]) })
  const { data: records = [] } = useQuery({
    queryKey: ['health', selectedBird],
    queryFn: () => selectedBird ? healthApi.list(selectedBird).then(r => r.data as HealthRecord[]) : [],
    enabled: !!selectedBird,
  })

  const createMutation = useMutation({
    mutationFn: (data: unknown) => healthApi.create(selectedBird, data),
    onSuccess: () => { qc.invalidateQueries({ queryKey: ['health', selectedBird] }); setShowForm(false) },
  })

  const vetMutation = useMutation({
    mutationFn: ({ recordId, data }: { recordId: string; data: unknown }) =>
      healthApi.requestVet(selectedBird, recordId, data),
    onSuccess: () => { setShowVetForm(null); setVetForm({ description: '', priority: 'medium', appointment_type: 'telemedicine', scheduled_date: '', scheduled_time: '', location_address: '' }) },
    onError: (e: unknown) => alert((e as { response?: { data?: { detail?: string } } })?.response?.data?.detail || 'Erro ao abrir consulta'),
  })

  const toggleSymptom = (s: string) => setForm(f => ({
    ...f,
    symptoms_json: f.symptoms_json.includes(s) ? f.symptoms_json.filter(x => x !== s) : [...f.symptoms_json, s],
  }))

  return (
    <div>
      <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: 24 }}>
        <h1 style={{ fontFamily: "'Cormorant Garamond',serif", fontSize: 30, fontWeight: 700, color: '#1a3d2b' }}>{t('nav.health')}</h1>
        <button onClick={() => setShowForm(!showForm)} disabled={!selectedBird} style={{ background: '#1a3d2b', border: 'none', borderRadius: 8, color: '#faf6f0', fontSize: 12, fontWeight: 700, padding: '9px 18px', cursor: 'pointer', opacity: selectedBird ? 1 : 0.5 }}>
          + Novo Registro
        </button>
      </div>

      {/* Bird selector */}
      <div style={{ background: '#fff', borderRadius: 12, border: '1px solid #e0e8e2', padding: 16, marginBottom: 20 }}>
        <label style={LABEL_STYLE}>Selecionar Ave</label>
        <div style={{ display: 'flex', gap: 8, flexWrap: 'wrap' }}>
          {birds.map(b => (
            <button key={b.id} onClick={() => setSelectedBird(b.id)} style={{ border: `1px solid ${selectedBird === b.id ? '#1a3d2b' : '#e0e8e2'}`, borderRadius: 8, padding: '6px 14px', background: selectedBird === b.id ? '#1a3d2b' : '#fff', color: selectedBird === b.id ? '#faf6f0' : '#4a6a54', fontSize: 12, fontWeight: 500, cursor: 'pointer' }}>
              {b.name}
            </button>
          ))}
        </div>
      </div>

      {showForm && selectedBird && (
        <div style={{ background: '#fff', borderRadius: 16, border: '1px solid #e0e8e2', padding: 24, marginBottom: 24 }}>
          <h2 style={{ fontFamily: "'Cormorant Garamond',serif", fontSize: 20, color: '#1a3d2b', marginBottom: 18 }}>Novo Registro de Saúde</h2>
          <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr 1fr', gap: 16, marginBottom: 16 }}>
            <div>
              <label style={LABEL_STYLE}>Peso (g)</label>
              <input type="number" value={form.weight_grams} onChange={e => setForm(f => ({ ...f, weight_grams: e.target.value }))} style={INPUT_STYLE} />
            </div>
            <div>
              <label style={LABEL_STYLE}>Temperatura (°C)</label>
              <input type="number" value={form.temperature_celsius} onChange={e => setForm(f => ({ ...f, temperature_celsius: e.target.value }))} style={INPUT_STYLE} />
            </div>
            <div>
              <label style={LABEL_STYLE}>Gravidade</label>
              <select value={form.severity} onChange={e => setForm(f => ({ ...f, severity: e.target.value }))} style={INPUT_STYLE}>
                <option value="normal">Normal</option>
                <option value="attention">Atenção</option>
                <option value="urgent">Urgente</option>
              </select>
            </div>
          </div>
          <div style={{ marginBottom: 16 }}>
            <label style={LABEL_STYLE}>Sintomas observados</label>
            <div style={{ display: 'flex', flexWrap: 'wrap', gap: 6 }}>
              {SYMPTOMS.map(s => (
                <button key={s} onClick={() => toggleSymptom(s)} style={{ border: `1px solid ${form.symptoms_json.includes(s) ? '#c45c3d' : '#e0e8e2'}`, borderRadius: 6, padding: '4px 10px', background: form.symptoms_json.includes(s) ? '#fdf0ed' : '#fff', color: form.symptoms_json.includes(s) ? '#c45c3d' : '#6b8a76', fontSize: 11, cursor: 'pointer' }}>
                  {s}
                </button>
              ))}
            </div>
          </div>
          <div style={{ marginBottom: 16 }}>
            <label style={LABEL_STYLE}>Observações de comportamento</label>
            <textarea rows={3} value={form.behavior_notes} onChange={e => setForm(f => ({ ...f, behavior_notes: e.target.value }))} style={{ ...INPUT_STYLE, resize: 'vertical' }} />
          </div>
          <div style={{ display: 'flex', gap: 12 }}>
            <button onClick={() => createMutation.mutate({ ...form, weight_grams: form.weight_grams ? parseFloat(form.weight_grams) : undefined, temperature_celsius: form.temperature_celsius ? parseFloat(form.temperature_celsius) : undefined })} disabled={createMutation.isPending} style={{ background: '#2d6a4f', border: 'none', borderRadius: 10, color: '#faf6f0', fontSize: 13, fontWeight: 700, padding: '10px 24px', cursor: 'pointer' }}>
              ✓ Salvar
            </button>
          </div>
        </div>
      )}

      {/* Vet consult form (shown per-record) */}
      {showVetForm && (
        <div style={{ position: 'fixed', inset: 0, background: 'rgba(0,0,0,0.5)', display: 'flex', alignItems: 'center', justifyContent: 'center', zIndex: 1000 }}>
          <div style={{ background: '#faf6f0', borderRadius: 20, padding: 32, width: 520, maxWidth: '95vw', maxHeight: '90vh', overflowY: 'auto', boxShadow: '0 8px 40px rgba(0,0,0,0.3)' }}>
            <h2 style={{ fontFamily: "'Cormorant Garamond',serif", fontSize: 22, fontWeight: 700, color: '#c45c3d', marginBottom: 20 }}>⚕️ Abrir Consulta PigeonVet</h2>

            <div style={{ marginBottom: 14 }}>
              <label style={LABEL_STYLE}>Descrição do problema</label>
              <textarea value={vetForm.description} onChange={e => setVetForm(f => ({ ...f, description: e.target.value }))} rows={3} style={{ ...INPUT_STYLE, resize: 'vertical' }} placeholder="Descreva os sintomas e histórico..." />
            </div>

            <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 14, marginBottom: 14 }}>
              <div>
                <label style={LABEL_STYLE}>Prioridade</label>
                <select value={vetForm.priority} onChange={e => setVetForm(f => ({ ...f, priority: e.target.value }))} style={INPUT_STYLE}>
                  <option value="low">Baixa</option>
                  <option value="medium">Média</option>
                  <option value="high">Alta</option>
                  <option value="emergency">Emergência</option>
                </select>
              </div>
              <div>
                <label style={LABEL_STYLE}>Tipo de Atendimento</label>
                <div style={{ display: 'flex', gap: 12, marginTop: 6 }}>
                  {[{ value: 'telemedicine', label: 'Telemedicina' }, { value: 'in_person', label: 'Presencial' }].map(opt => (
                    <label key={opt.value} style={{ display: 'flex', alignItems: 'center', gap: 6, fontSize: 13, color: '#4a6a54', cursor: 'pointer' }}>
                      <input type="radio" name="appointment_type" value={opt.value} checked={vetForm.appointment_type === opt.value} onChange={e => setVetForm(f => ({ ...f, appointment_type: e.target.value }))} style={{ accentColor: '#1a3d2b' }} />
                      {opt.label}
                    </label>
                  ))}
                </div>
              </div>
            </div>

            <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 14, marginBottom: 14 }}>
              <div>
                <label style={LABEL_STYLE}>Data preferencial</label>
                <input type="date" value={vetForm.scheduled_date} onChange={e => setVetForm(f => ({ ...f, scheduled_date: e.target.value }))} style={INPUT_STYLE} />
              </div>
              <div>
                <label style={LABEL_STYLE}>Horário</label>
                <input type="time" value={vetForm.scheduled_time} onChange={e => setVetForm(f => ({ ...f, scheduled_time: e.target.value }))} style={INPUT_STYLE} />
              </div>
            </div>

            {vetForm.appointment_type === 'in_person' && (
              <div style={{ marginBottom: 14 }}>
                <label style={LABEL_STYLE}>Endereço para atendimento presencial</label>
                <input value={vetForm.location_address} onChange={e => setVetForm(f => ({ ...f, location_address: e.target.value }))} style={INPUT_STYLE} placeholder="Rua, número, cidade, estado" />
              </div>
            )}

            <div style={{ display: 'flex', gap: 10 }}>
              <button
                onClick={() => vetMutation.mutate({ recordId: showVetForm, data: vetForm })}
                disabled={vetMutation.isPending}
                style={{ flex: 1, background: '#c45c3d', border: 'none', borderRadius: 10, color: '#fff', fontSize: 13, fontWeight: 700, padding: '12px 0', cursor: 'pointer' }}
              >
                {vetMutation.isPending ? 'Enviando...' : '⚕️ Abrir Chamado'}
              </button>
              <button onClick={() => setShowVetForm(null)} style={{ background: '#fff', border: '1px solid #c8d4c8', borderRadius: 10, color: '#4a6a54', fontSize: 13, fontWeight: 600, padding: '12px 20px', cursor: 'pointer' }}>
                Cancelar
              </button>
            </div>
          </div>
        </div>
      )}

      {/* Records list */}
      <div style={{ display: 'grid', gap: 12 }}>
        {records.map(r => (
          <div key={r.id} style={{ background: '#fff', borderRadius: 12, border: `1px solid ${r.severity === 'urgent' ? '#f0c8b0' : r.severity === 'attention' ? '#fdf4e8' : '#e0e8e2'}`, padding: 18 }}>
            <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: 8 }}>
              <span style={{ fontSize: 13, fontWeight: 600, color: '#1e2620' }}>{new Date(r.date).toLocaleDateString('pt-BR')}</span>
              <div style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
                <span style={{ fontSize: 10, fontWeight: 700, borderRadius: 5, padding: '2px 8px', background: r.severity === 'urgent' ? '#fdf0ed' : r.severity === 'attention' ? '#fdf4e8' : '#e8f0e8', color: r.severity === 'urgent' ? '#c45c3d' : r.severity === 'attention' ? '#c8922a' : '#2d6a4f' }}>
                  {r.severity === 'urgent' ? '🚨 Urgente' : r.severity === 'attention' ? '⚠️ Atenção' : '✓ Normal'}
                </span>
                {r.severity !== 'normal' && (
                  <button onClick={() => setShowVetForm(r.id)} style={{ background: '#c45c3d', border: 'none', borderRadius: 6, color: '#fff', fontSize: 10, fontWeight: 600, padding: '3px 10px', cursor: 'pointer' }}>
                    ⚕️ {t('vet.consult')}
                  </button>
                )}
              </div>
            </div>
            {r.weight_grams && <div style={{ fontSize: 12, color: '#6b8a76' }}>⚖️ {r.weight_grams}g</div>}
            {r.symptoms_json && r.symptoms_json.length > 0 && (
              <div style={{ display: 'flex', flexWrap: 'wrap', gap: 4, marginTop: 6 }}>
                {r.symptoms_json.map(s => <span key={s} style={{ fontSize: 9, background: '#fdf0ed', color: '#c45c3d', borderRadius: 4, padding: '1px 5px' }}>{s}</span>)}
              </div>
            )}
          </div>
        ))}
      </div>
    </div>
  )
}
