import { useState } from 'react'
import { useQuery, useMutation } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import { geneticsApi, birdsApi } from '@/services/api'
import type { Bird, GeneticLocus, GeneticResultItem } from '@/types'

export default function GeneticsPage() {
  const { t } = useTranslation()
  const [maleBirdId, setMaleBirdId] = useState('')
  const [femaleBirdId, setFemaleBirdId] = useState('')
  const [results, setResults] = useState<GeneticResultItem[]>([])
  const [warnings, setWarnings] = useState<{ locus: string; message: string }[]>([])

  const { data: birds = [] } = useQuery({ queryKey: ['birds'], queryFn: () => birdsApi.list().then(r => r.data as Bird[]) })

  const males = birds.filter(b => b.sex === 'M')
  const females = birds.filter(b => b.sex === 'F')
  const selectedMale = birds.find(b => b.id === maleBirdId)
  const selectedFemale = birds.find(b => b.id === femaleBirdId)

  const { data: maleLoci = [] } = useQuery({
    queryKey: ['loci', selectedMale?.species_id],
    queryFn: () => geneticsApi.loci(selectedMale!.species_id!).then(r => r.data as GeneticLocus[]),
    enabled: !!selectedMale?.species_id,
  })

  const calcMutation = useMutation({
    mutationFn: (data: unknown) => geneticsApi.calculate(data),
    onSuccess: (res) => {
      setResults(res.data.results)
      setWarnings(res.data.warnings)
    },
  })

  const handleCalculate = () => {
    if (!maleBirdId || !femaleBirdId) return alert('Selecione macho e fêmea')
    calcMutation.mutate({ male_bird_id: maleBirdId, female_bird_id: femaleBirdId, include_sexes: true, min_probability: 0.5 })
  }

  return (
    <div>
      <h1 style={{ fontFamily: "'Cormorant Garamond',serif", fontSize: 30, fontWeight: 700, color: '#1a3d2b', marginBottom: 24 }}>{t('genetics.title')}</h1>

      {/* Bird selector */}
      <div style={{ background: '#fff', borderRadius: 16, border: '1px solid #e0e8e2', padding: 24, marginBottom: 20 }}>
        <h2 style={{ fontFamily: "'Cormorant Garamond',serif", fontSize: 20, color: '#1a3d2b', marginBottom: 16 }}>Selecionar Casal para Cruzamento</h2>
        <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 20, marginBottom: 20 }}>
          <div>
            <label style={{ display: 'block', fontSize: 11, fontWeight: 600, color: '#4a6a54', marginBottom: 8 }}>♂ Macho</label>
            <select value={maleBirdId} onChange={e => setMaleBirdId(e.target.value)} style={{ width: '100%', border: '1px solid #c8d4c8', borderRadius: 8, padding: '10px 12px', fontSize: 13, outline: 'none' }}>
              <option value="">Selecione um macho...</option>
              {males.map(b => <option key={b.id} value={b.id}>{b.name} — {b.species?.common_name}</option>)}
            </select>
          </div>
          <div>
            <label style={{ display: 'block', fontSize: 11, fontWeight: 600, color: '#4a6a54', marginBottom: 8 }}>♀ Fêmea</label>
            <select value={femaleBirdId} onChange={e => setFemaleBirdId(e.target.value)} style={{ width: '100%', border: '1px solid #c8d4c8', borderRadius: 8, padding: '10px 12px', fontSize: 13, outline: 'none' }}>
              <option value="">Selecione uma fêmea...</option>
              {females.map(b => <option key={b.id} value={b.id}>{b.name} — {b.species?.common_name}</option>)}
            </select>
          </div>
        </div>

        {/* Pair summary */}
        {selectedMale && selectedFemale && (
          <div style={{ background: '#f5f8f5', borderRadius: 10, padding: 14, marginBottom: 16, display: 'flex', gap: 24 }}>
            <div style={{ textAlign: 'center' }}>
              <div style={{ fontSize: 28 }}>🦜</div>
              <div style={{ fontSize: 13, fontWeight: 600, color: '#1e2620' }}>{selectedMale.name}</div>
              <div style={{ fontSize: 10, color: '#7a9a84' }}>{selectedMale.species?.common_name}</div>
            </div>
            <div style={{ display: 'flex', alignItems: 'center', fontSize: 20, color: '#c8922a' }}>×</div>
            <div style={{ textAlign: 'center' }}>
              <div style={{ fontSize: 28 }}>🦜</div>
              <div style={{ fontSize: 13, fontWeight: 600, color: '#1e2620' }}>{selectedFemale.name}</div>
              <div style={{ fontSize: 10, color: '#7a9a84' }}>{selectedFemale.species?.common_name}</div>
            </div>
          </div>
        )}

        <button onClick={handleCalculate} disabled={!maleBirdId || !femaleBirdId || calcMutation.isPending} style={{ background: '#2d6a4f', border: 'none', borderRadius: 10, color: '#faf6f0', fontSize: 13, fontWeight: 700, padding: '11px 28px', cursor: 'pointer', opacity: (!maleBirdId || !femaleBirdId) ? 0.5 : 1 }}>
          🧬 {calcMutation.isPending ? 'Calculando...' : t('genetics.calculate')}
        </button>
      </div>

      {/* Warnings */}
      {warnings.length > 0 && (
        <div style={{ background: '#fff9f5', border: '1px solid #f0c8b0', borderRadius: 12, padding: 16, marginBottom: 20 }}>
          {warnings.map((w, i) => (
            <div key={i} style={{ fontSize: 12, color: '#c45c3d' }}>⚠️ <strong>{w.locus}</strong>: {w.message}</div>
          ))}
        </div>
      )}

      {/* Results table */}
      {results.length > 0 && (
        <div style={{ background: '#fff', borderRadius: 16, border: '1px solid #e0e8e2', padding: 24 }}>
          <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: 18 }}>
            <h2 style={{ fontFamily: "'Cormorant Garamond',serif", fontSize: 20, color: '#1a3d2b' }}>Resultados — {results.length} fenótipos possíveis</h2>
            <button style={{ background: 'none', border: '1px solid #2d6a4f', borderRadius: 6, color: '#2d6a4f', fontSize: 11, padding: '5px 12px', cursor: 'pointer' }}>📄 Exportar PDF</button>
          </div>
          <div style={{ overflowX: 'auto' }}>
            <table style={{ width: '100%', borderCollapse: 'collapse', fontSize: 12 }}>
              <thead>
                <tr style={{ background: '#f5f8f5' }}>
                  <th style={{ padding: '10px 12px', textAlign: 'left', color: '#4a6a54', fontWeight: 600 }}>Sexo</th>
                  <th style={{ padding: '10px 12px', textAlign: 'left', color: '#4a6a54', fontWeight: 600 }}>Fenótipo</th>
                  <th style={{ padding: '10px 12px', textAlign: 'left', color: '#4a6a54', fontWeight: 600 }}>Splits</th>
                  <th style={{ padding: '10px 12px', textAlign: 'left', color: '#4a6a54', fontWeight: 600 }}>Código Genético</th>
                  <th style={{ padding: '10px 12px', textAlign: 'right', color: '#4a6a54', fontWeight: 600 }}>Probabilidade</th>
                </tr>
              </thead>
              <tbody>
                {results.map((r, i) => (
                  <tr key={i} style={{ borderTop: '1px solid #f0f4f1' }}>
                    <td style={{ padding: '10px 12px' }}>
                      <span style={{ background: r.sex === 'M' ? '#ddeeff' : '#ffdde8', color: r.sex === 'M' ? '#3366aa' : '#aa3366', fontSize: 10, fontWeight: 700, borderRadius: 4, padding: '2px 6px' }}>
                        {r.sex === 'M' ? '♂ M' : '♀ F'}
                      </span>
                    </td>
                    <td style={{ padding: '10px 12px', color: '#1e2620' }}>{r.phenotype.join(', ') || 'Normal'}</td>
                    <td style={{ padding: '10px 12px', color: '#8a6a3a' }}>{r.splits.join(', ') || '—'}</td>
                    <td style={{ padding: '10px 12px', color: '#6b8a76', fontFamily: 'monospace', fontSize: 11 }}>{r.genotype_code || '—'}</td>
                    <td style={{ padding: '10px 12px', textAlign: 'right' }}>
                      <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'flex-end', gap: 8 }}>
                        <div style={{ width: 60, height: 6, background: '#e8f0e8', borderRadius: 3, overflow: 'hidden' }}>
                          <div style={{ width: `${Math.min(r.probability, 100)}%`, height: '100%', background: '#2d6a4f', borderRadius: 3 }} />
                        </div>
                        <span style={{ fontWeight: 700, color: '#2d6a4f', minWidth: 40, textAlign: 'right' }}>{r.probability.toFixed(1)}%</span>
                      </div>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </div>
      )}

      {!results.length && !calcMutation.isPending && (
        <div style={{ textAlign: 'center', padding: 60, color: '#9ab5a0' }}>
          <div style={{ fontSize: 48 }}>🧬</div>
          <h2 style={{ fontFamily: "'Cormorant Garamond',serif", fontSize: 24, color: '#1a3d2b', margin: '12px 0 8px' }}>Calculadora Genética</h2>
          <p style={{ fontSize: 14 }}>Selecione um casal para calcular os fenótipos possíveis da prole.</p>
        </div>
      )}
    </div>
  )
}
