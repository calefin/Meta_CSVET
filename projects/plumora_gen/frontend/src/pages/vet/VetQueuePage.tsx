import { useState } from 'react'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import { vetApi } from '@/services/api'
import type { VetConsultRequest } from '@/types'

const PRIORITY_COLORS: Record<string, string> = {
  emergency: '#c45c3d',
  high: '#e87a5a',
  medium: '#c8922a',
  low: '#2d6a4f',
}

export default function VetQueuePage() {
  const { t } = useTranslation()
  const qc = useQueryClient()
  const [selectedConsult, setSelectedConsult] = useState<string | null>(null)
  const [response, setResponse] = useState('')

  const { data: queue = [] } = useQuery({ queryKey: ['vet-queue'], queryFn: () => vetApi.queue().then(r => r.data as VetConsultRequest[]) })

  const respondMutation = useMutation({
    mutationFn: ({ id, text }: { id: string; text: string }) => vetApi.respond(id, { response_text: text, status: 'responded' }),
    onSuccess: () => { qc.invalidateQueries({ queryKey: ['vet-queue'] }); setSelectedConsult(null); setResponse('') },
  })

  return (
    <div>
      <div style={{ display: 'flex', alignItems: 'center', gap: 12, marginBottom: 28 }}>
        <span style={{ fontSize: 28 }}>⚕️</span>
        <div>
          <h1 style={{ fontFamily: "'Cormorant Garamond',serif", fontSize: 30, fontWeight: 700, color: '#c45c3d' }}>{t('nav.pigeonvet')}</h1>
          <p style={{ fontSize: 12, color: '#7a9a84', marginTop: 2 }}>{t('vet.by')}</p>
        </div>
      </div>

      <div style={{ display: 'grid', gridTemplateColumns: selectedConsult ? '1fr 1fr' : '1fr', gap: 20 }}>
        {/* Queue */}
        <div>
          <h2 style={{ fontFamily: "'Cormorant Garamond',serif", fontSize: 20, color: '#1a3d2b', marginBottom: 16 }}>{t('vet.queue')} ({queue.length})</h2>
          <div style={{ display: 'flex', flexDirection: 'column', gap: 12 }}>
            {queue.map(consult => (
              <div key={consult.id} onClick={() => setSelectedConsult(consult.id)} style={{ background: '#fff', borderRadius: 12, border: `1px solid ${selectedConsult === consult.id ? '#c45c3d' : '#e0e8e2'}`, padding: 18, cursor: 'pointer', boxShadow: selectedConsult === consult.id ? '0 0 0 2px rgba(196,92,61,0.2)' : 'none' }}>
                <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: 8 }}>
                  <span style={{ fontSize: 10, fontWeight: 700, borderRadius: 4, padding: '2px 7px', background: `${PRIORITY_COLORS[consult.priority]}22`, color: PRIORITY_COLORS[consult.priority] }}>
                    {consult.priority.toUpperCase()}
                  </span>
                  <span style={{ fontSize: 10, color: '#9ab5a0' }}>{new Date(consult.created_at).toLocaleDateString('pt-BR')}</span>
                </div>
                <div style={{ fontSize: 13, fontWeight: 500, color: '#1e2620', marginBottom: 4 }}>Ave ID: {consult.bird_id.substring(0, 8)}...</div>
                <div style={{ fontSize: 11, color: '#7a9a84' }}>{consult.description?.substring(0, 100) || 'Sem descrição'}</div>
                <div style={{ marginTop: 6 }}>
                  <span style={{ fontSize: 9, background: consult.status === 'pending' ? '#fdf4e8' : '#e8f0e8', color: consult.status === 'pending' ? '#c8922a' : '#2d6a4f', borderRadius: 4, padding: '2px 6px', fontWeight: 700 }}>
                    {consult.status.toUpperCase()}
                  </span>
                </div>
              </div>
            ))}
            {queue.length === 0 && (
              <div style={{ textAlign: 'center', padding: 40, color: '#9ab5a0' }}>
                <div style={{ fontSize: 36 }}>✅</div>
                <p style={{ marginTop: 8 }}>Fila vazia — nenhum atendimento pendente.</p>
              </div>
            )}
          </div>
        </div>

        {/* Response panel */}
        {selectedConsult && (() => {
          const consult = queue.find(c => c.id === selectedConsult)!
          return (
            <div style={{ background: '#fff', borderRadius: 16, border: '1px solid #e0e8e2', padding: 24 }}>
              <h2 style={{ fontFamily: "'Cormorant Garamond',serif", fontSize: 20, color: '#c45c3d', marginBottom: 16 }}>Responder Consulta</h2>
              <div style={{ background: '#f5f8f5', borderRadius: 10, padding: 14, marginBottom: 16 }}>
                <div style={{ fontSize: 11, fontWeight: 600, color: '#4a6a54', marginBottom: 6 }}>Descrição do Criador</div>
                <div style={{ fontSize: 13, color: '#1e2620' }}>{consult.description || 'Sem descrição'}</div>
              </div>
              <div style={{ marginBottom: 16 }}>
                <label style={{ display: 'block', fontSize: 11, fontWeight: 600, color: '#4a6a54', marginBottom: 8 }}>Resposta Veterinária</label>
                <textarea rows={6} value={response} onChange={e => setResponse(e.target.value)} placeholder="Diagnóstico, recomendações, prescrições..." style={{ width: '100%', border: '1px solid #c8d4c8', borderRadius: 8, padding: '10px 12px', fontSize: 13, outline: 'none', resize: 'vertical' }} />
              </div>
              <div style={{ display: 'flex', gap: 10 }}>
                <button onClick={() => respondMutation.mutate({ id: selectedConsult, text: response })} disabled={!response || respondMutation.isPending} style={{ background: '#c45c3d', border: 'none', borderRadius: 10, color: '#fff', fontSize: 13, fontWeight: 700, padding: '10px 24px', cursor: 'pointer' }}>
                  ⚕️ {respondMutation.isPending ? 'Enviando...' : 'Enviar Resposta'}
                </button>
                <button onClick={() => setSelectedConsult(null)} style={{ background: 'none', border: '1px solid #c8d4c8', borderRadius: 10, color: '#6b8a76', fontSize: 13, padding: '10px 16px', cursor: 'pointer' }}>
                  Cancelar
                </button>
              </div>
            </div>
          )
        })()}
      </div>
    </div>
  )
}
