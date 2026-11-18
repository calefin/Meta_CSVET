import { useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import { pedigreeApi, birdsApi } from '@/services/api'
import type { Bird } from '@/types'

interface PedigreeNode {
  id: string
  name: string
  ring?: string
  sex?: string
  father?: PedigreeNode | null
  mother?: PedigreeNode | null
}

function TreeNode({ node, depth = 0 }: { node: PedigreeNode; depth?: number }) {
  const colors: Record<number, string> = { 0: '#2d6a4f', 1: '#c8922a', 2: '#1a3d2b', 3: '#8b5e3c', 4: '#6b8a76' }
  const color = colors[depth] ?? '#7a9a84'
  const boxW = Math.max(120 - depth * 15, 80)

  return (
    <div style={{ display: 'flex', flexDirection: 'column', alignItems: 'center', gap: 8 }}>
      <div style={{ border: `2px solid ${color}`, borderRadius: 8, padding: '6px 10px', minWidth: boxW, textAlign: 'center', background: '#fff' }}>
        <div style={{ fontSize: Math.max(11 - depth, 8), fontWeight: 700, color }}>{node.name}</div>
        {node.ring && <div style={{ fontSize: 8, color: '#9ab5a0' }}>{node.ring}</div>}
        {node.sex && <div style={{ fontSize: 9, color: node.sex === 'M' ? '#3366aa' : '#aa3366' }}>{node.sex === 'M' ? '♂' : '♀'}</div>}
      </div>
      {(node.father ?? node.mother) && (
        <div style={{ display: 'flex', gap: 16 }}>
          {node.father && <TreeNode node={node.father} depth={depth + 1} />}
          {node.mother && <TreeNode node={node.mother} depth={depth + 1} />}
        </div>
      )}
    </div>
  )
}

export default function PedigreePage() {
  const { t } = useTranslation()
  const [selectedBird, setSelectedBird] = useState('')
  const [generations, setGenerations] = useState(3)

  const { data: birds = [] } = useQuery({ queryKey: ['birds'], queryFn: () => birdsApi.list().then(r => r.data as Bird[]) })
  const { data: pedigreeData, isLoading } = useQuery({
    queryKey: ['pedigree', selectedBird, generations],
    queryFn: () => pedigreeApi.get(selectedBird, generations).then(r => r.data),
    enabled: !!selectedBird,
  })

  return (
    <div>
      <h1 style={{ fontFamily: "'Cormorant Garamond',serif", fontSize: 30, fontWeight: 700, color: '#1a3d2b', marginBottom: 24 }}>{t('nav.pedigree')}</h1>

      <div style={{ background: '#fff', borderRadius: 12, border: '1px solid #e0e8e2', padding: 20, marginBottom: 24 }}>
        <div style={{ display: 'grid', gridTemplateColumns: '1fr 200px 200px', gap: 16, alignItems: 'end' }}>
          <div>
            <label style={{ display: 'block', fontSize: 11, fontWeight: 600, color: '#4a6a54', marginBottom: 5 }}>Selecionar Ave</label>
            <select value={selectedBird} onChange={e => setSelectedBird(e.target.value)} style={{ width: '100%', border: '1px solid #c8d4c8', borderRadius: 8, padding: '10px 12px', fontSize: 13, outline: 'none' }}>
              <option value="">Selecione uma ave...</option>
              {birds.map(b => <option key={b.id} value={b.id}>{b.name} ({b.ring_number})</option>)}
            </select>
          </div>
          <div>
            <label style={{ display: 'block', fontSize: 11, fontWeight: 600, color: '#4a6a54', marginBottom: 5 }}>Gerações</label>
            <select value={generations} onChange={e => setGenerations(Number(e.target.value))} style={{ width: '100%', border: '1px solid #c8d4c8', borderRadius: 8, padding: '10px 12px', fontSize: 13, outline: 'none' }}>
              {[2, 3, 4, 5].map(g => <option key={g} value={g}>{g} gerações</option>)}
            </select>
          </div>
          <button onClick={() => {}} disabled={!selectedBird} style={{ background: '#2d6a4f', border: 'none', borderRadius: 8, color: '#faf6f0', fontSize: 12, fontWeight: 700, padding: '10px 0', cursor: 'pointer', opacity: selectedBird ? 1 : 0.5 }}>
            📄 Exportar PDF
          </button>
        </div>
      </div>

      {pedigreeData?.inbreeding_coefficient_pct !== undefined && (
        <div style={{ background: pedigreeData.inbreeding_coefficient_pct > 10 ? '#fff9f5' : '#f5faf5', border: `1px solid ${pedigreeData.inbreeding_coefficient_pct > 10 ? '#f0c8b0' : '#c8e0c8'}`, borderRadius: 10, padding: '10px 16px', marginBottom: 20, fontSize: 12 }}>
          {pedigreeData.inbreeding_coefficient_pct > 0
            ? <span>⚠️ Coeficiente de consanguinidade (Wright): <strong>{pedigreeData.inbreeding_coefficient_pct}%</strong></span>
            : <span>✓ Sem consanguinidade detectada nas gerações analisadas.</span>}
        </div>
      )}

      {isLoading && <div style={{ textAlign: 'center', padding: 40, color: '#7a9a84' }}>Carregando claudograma...</div>}

      {pedigreeData?.tree && !isLoading && (
        <div style={{ background: '#fff', borderRadius: 16, border: '1px solid #e0e8e2', padding: 32, overflowX: 'auto' }}>
          <h2 style={{ fontFamily: "'Cormorant Garamond',serif", fontSize: 22, color: '#1a3d2b', marginBottom: 24, textAlign: 'center' }}>Claudograma</h2>
          <div style={{ display: 'flex', justifyContent: 'center' }}>
            <TreeNode node={pedigreeData.tree as PedigreeNode} />
          </div>
        </div>
      )}

      {!selectedBird && (
        <div style={{ textAlign: 'center', padding: 60, color: '#9ab5a0' }}>
          <div style={{ fontSize: 48 }}>🌳</div>
          <h2 style={{ fontFamily: "'Cormorant Garamond',serif", fontSize: 24, color: '#1a3d2b', margin: '12px 0 8px' }}>Claudograma</h2>
          <p style={{ fontSize: 14 }}>Selecione uma ave para visualizar sua árvore genealógica.</p>
        </div>
      )}
    </div>
  )
}
