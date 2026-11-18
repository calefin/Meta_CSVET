import { Link } from 'react-router-dom'
import { useAuthStore } from '@/store/auth'

const features = [
  {
    icon: '🐦',
    title: 'Gestão de Aves',
    desc: 'Cadastro completo com fotos, anilha, genealogia e histórico. Suporte a psitacídeos, pombos correio e demais espécies.',
  },
  {
    icon: '🧬',
    title: 'Calculadora Genética',
    desc: 'Motor multi-loci com herança autossômica recessiva, dominante, co-dominante e ligada ao sexo. Cálculo probabilístico por casal.',
  },
  {
    icon: '🌳',
    title: 'Pedigree & Claudograma',
    desc: 'Árvore genealógica interativa com até 5 gerações. Coeficiente de consanguinidade (Wright) automático. Exportação em PDF.',
  },
  {
    icon: '💉',
    title: 'Vacinas & Saúde',
    desc: 'Vacinação individual ou em lote por espécie/local. Agenda automática de reforços. Histórico clínico com avaliação de severidade.',
  },
  {
    icon: '🕊️',
    title: 'Pombos Correio',
    desc: 'Genética ZW especializada com haplótipos ligados ao Z (cor + diluído), epistasia, padrão de asa e pé emplumado. Registro de corridas e resultados.',
  },
  {
    icon: '🩺',
    title: 'PigeonVet',
    desc: 'Módulo veterinário integrado. Criadores enviam consultas, veterinários respondem com notas clínicas. Alertas automáticos para casos críticos.',
  },
  {
    icon: '🏁',
    title: 'Corridas & Resultados',
    desc: 'Cadastro de provas com origem, destino e distância. Registro de resultados com tempo de voo e velocidade calculada automaticamente.',
  },
  {
    icon: '📊',
    title: 'Índices Zootécnicos',
    desc: 'Taxa de fertilidade, eclosão, histórico de peso e desempenho por ave. Dashboard com visão geral do plantel.',
  },
]

const plans = [
  {
    name: 'Gratuito',
    price: 'R$ 0',
    period: 'para sempre',
    color: '#7a9a84',
    features: ['Até 10 aves', 'Calculadora genética básica', '2 exports de pedigree/mês', 'Acesso ao PigeonVet (leitura)'],
    cta: 'Criar conta grátis',
    highlight: false,
  },
  {
    name: 'Básico',
    price: 'R$ 19,90',
    period: 'por mês',
    color: '#2d6a4f',
    features: ['Até 50 aves', 'Todas as espécies', 'Pedigree ilimitado', 'Vacinação em lote', 'Suporte por e-mail'],
    cta: 'Assinar Básico',
    highlight: false,
  },
  {
    name: 'Pro',
    price: 'R$ 49,90',
    period: 'por mês',
    color: '#c8922a',
    features: ['Até 200 aves', 'Multi-loci avançado', 'Índices zootécnicos', 'PigeonVet prioritário', 'Relatórios PDF'],
    cta: 'Assinar Pro',
    highlight: true,
  },
  {
    name: 'Premium',
    price: 'R$ 89,90',
    period: 'por mês',
    color: '#1a3d2b',
    features: ['Aves ilimitadas', 'API de integração', 'Consultoria genética', 'Suporte VIP', 'Tudo do Pro'],
    cta: 'Assinar Premium',
    highlight: false,
  },
]

export default function LandingPage() {
  const { isAuthenticated } = useAuthStore()

  return (
    <div style={{ fontFamily: "'DM Sans', sans-serif", color: '#1a3d2b', background: '#faf6f0' }}>
      {/* Topbar */}
      <header style={{ background: '#1a3d2b', padding: '0 40px', height: 64, display: 'flex', alignItems: 'center', justifyContent: 'space-between', position: 'sticky', top: 0, zIndex: 100 }}>
        <div style={{ display: 'flex', alignItems: 'center', gap: 10 }}>
          <span style={{ fontSize: 26 }}>🪶</span>
          <span style={{ fontFamily: "'Cormorant Garamond', serif", fontSize: 24, fontWeight: 700, color: '#faf6f0', letterSpacing: 1 }}>Plumora Gen</span>
          <span style={{ background: '#c8922a', color: '#fff', fontSize: 9, fontWeight: 700, padding: '2px 7px', borderRadius: 20, marginLeft: 4, letterSpacing: 1 }}>v4.0</span>
        </div>
        <nav style={{ display: 'flex', gap: 8, alignItems: 'center' }}>
          <a href="#features" style={{ color: '#c8d4c8', fontSize: 13, textDecoration: 'none', padding: '6px 14px' }}>Funcionalidades</a>
          <a href="#plans" style={{ color: '#c8d4c8', fontSize: 13, textDecoration: 'none', padding: '6px 14px' }}>Planos</a>
          {isAuthenticated ? (
            <Link to="/dashboard" style={{ background: '#c8922a', color: '#fff', borderRadius: 8, padding: '8px 20px', fontSize: 13, fontWeight: 700, textDecoration: 'none' }}>Ir para o App</Link>
          ) : (
            <>
              <Link to="/login" style={{ color: '#c8d4c8', fontSize: 13, textDecoration: 'none', padding: '6px 14px' }}>Entrar</Link>
              <Link to="/register" style={{ background: '#c8922a', color: '#fff', borderRadius: 8, padding: '8px 20px', fontSize: 13, fontWeight: 700, textDecoration: 'none' }}>Criar conta</Link>
            </>
          )}
        </nav>
      </header>

      {/* Hero */}
      <section style={{ background: 'linear-gradient(135deg, #1a3d2b 0%, #2d6a4f 60%, #3a8a62 100%)', padding: '100px 40px 80px', textAlign: 'center', position: 'relative', overflow: 'hidden' }}>
        <div style={{ position: 'absolute', top: -60, right: -60, width: 300, height: 300, background: 'rgba(200,146,42,0.08)', borderRadius: '50%' }} />
        <div style={{ position: 'absolute', bottom: -40, left: -40, width: 200, height: 200, background: 'rgba(200,146,42,0.06)', borderRadius: '50%' }} />
        <div style={{ position: 'relative', maxWidth: 760, margin: '0 auto' }}>
          <div style={{ display: 'inline-block', background: 'rgba(200,146,42,0.15)', border: '1px solid rgba(200,146,42,0.4)', borderRadius: 20, padding: '5px 16px', fontSize: 12, color: '#f0d080', marginBottom: 24, fontWeight: 600 }}>
            🧬 Genética aviária de precisão
          </div>
          <h1 style={{ fontFamily: "'Cormorant Garamond', serif", fontSize: 58, fontWeight: 700, color: '#faf6f0', lineHeight: 1.1, marginBottom: 24 }}>
            Plataforma completa para<br />
            <span style={{ color: '#c8922a' }}>criadores de aves</span>
          </h1>
          <p style={{ fontSize: 18, color: '#a8c4b0', lineHeight: 1.7, marginBottom: 40, maxWidth: 580, margin: '0 auto 40px' }}>
            Gestão genética, pedigree, saúde, vacinação e muito mais — em uma plataforma desenvolvida por e para criadores de aves exóticas, domésticas e pombos correio.
          </p>
          <div style={{ display: 'flex', gap: 16, justifyContent: 'center', flexWrap: 'wrap' }}>
            <Link to="/register" style={{ background: '#c8922a', color: '#fff', borderRadius: 10, padding: '14px 32px', fontSize: 15, fontWeight: 700, textDecoration: 'none', display: 'inline-block' }}>
              Começar gratuitamente →
            </Link>
            <a href="#features" style={{ background: 'rgba(255,255,255,0.08)', border: '1px solid rgba(255,255,255,0.2)', color: '#faf6f0', borderRadius: 10, padding: '14px 32px', fontSize: 15, fontWeight: 600, textDecoration: 'none', display: 'inline-block' }}>
              Conhecer funcionalidades
            </a>
          </div>
          <div style={{ marginTop: 48, display: 'flex', gap: 40, justifyContent: 'center', flexWrap: 'wrap' }}>
            {[['10+', 'Espécies suportadas'], ['6', 'Modos de herança genética'], ['5', 'Gerações de pedigree'], ['100%', 'Self-hosted & privado']].map(([n, l]) => (
              <div key={l} style={{ textAlign: 'center' }}>
                <div style={{ fontFamily: "'Cormorant Garamond', serif", fontSize: 32, fontWeight: 700, color: '#c8922a' }}>{n}</div>
                <div style={{ fontSize: 12, color: '#7ab090', marginTop: 2 }}>{l}</div>
              </div>
            ))}
          </div>
        </div>
      </section>

      {/* Features */}
      <section id="features" style={{ padding: '80px 40px', maxWidth: 1200, margin: '0 auto' }}>
        <div style={{ textAlign: 'center', marginBottom: 56 }}>
          <h2 style={{ fontFamily: "'Cormorant Garamond', serif", fontSize: 40, fontWeight: 700, color: '#1a3d2b', marginBottom: 12 }}>Tudo que você precisa</h2>
          <p style={{ fontSize: 15, color: '#5a8a6a', maxWidth: 520, margin: '0 auto' }}>Do cadastro básico à genética avançada, o Plumora Gen cobre todos os aspectos do criatório profissional.</p>
        </div>
        <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fill, minmax(260px, 1fr))', gap: 24 }}>
          {features.map(f => (
            <div key={f.title} style={{ background: '#fff', borderRadius: 16, border: '1px solid #e0ece4', padding: '28px 24px', transition: 'box-shadow 0.2s' }}>
              <div style={{ fontSize: 36, marginBottom: 14 }}>{f.icon}</div>
              <h3 style={{ fontFamily: "'Cormorant Garamond', serif", fontSize: 20, fontWeight: 700, color: '#1a3d2b', marginBottom: 8 }}>{f.title}</h3>
              <p style={{ fontSize: 13, color: '#6a8a74', lineHeight: 1.6 }}>{f.desc}</p>
            </div>
          ))}
        </div>
      </section>

      {/* Species band */}
      <section style={{ background: '#1a3d2b', padding: '48px 40px', textAlign: 'center' }}>
        <h2 style={{ fontFamily: "'Cormorant Garamond', serif", fontSize: 32, color: '#faf6f0', marginBottom: 8 }}>Espécies suportadas</h2>
        <p style={{ color: '#7ab090', fontSize: 14, marginBottom: 32 }}>Com expansão contínua de loci genéticos validados por literatura científica</p>
        <div style={{ display: 'flex', flexWrap: 'wrap', gap: 12, justifyContent: 'center' }}>
          {['🦜 Calopsita', '🦜 Agapornis', '🦜 Periquito Australiano', '🐦 Pombo Correio', '🦜 Rosela', '🦜 Red-rumped', '🐤 Canário', '🦜 Forpus', '🦅 + Novas espécies'].map(s => (
            <span key={s} style={{ background: 'rgba(255,255,255,0.07)', border: '1px solid rgba(255,255,255,0.12)', color: '#c8d4c8', borderRadius: 20, padding: '8px 18px', fontSize: 13 }}>{s}</span>
          ))}
        </div>
      </section>

      {/* Plans */}
      <section id="plans" style={{ padding: '80px 40px', maxWidth: 1200, margin: '0 auto' }}>
        <div style={{ textAlign: 'center', marginBottom: 56 }}>
          <h2 style={{ fontFamily: "'Cormorant Garamond', serif", fontSize: 40, fontWeight: 700, color: '#1a3d2b', marginBottom: 12 }}>Planos e preços</h2>
          <p style={{ fontSize: 15, color: '#5a8a6a' }}>Comece gratuitamente. Escale conforme o seu plantel crescer.</p>
        </div>
        <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fill, minmax(240px, 1fr))', gap: 24 }}>
          {plans.map(p => (
            <div key={p.name} style={{ background: p.highlight ? p.color : '#fff', borderRadius: 16, border: p.highlight ? 'none' : '1px solid #e0ece4', padding: '32px 24px', position: 'relative', boxShadow: p.highlight ? '0 8px 40px rgba(200,146,42,0.25)' : 'none' }}>
              {p.highlight && <div style={{ position: 'absolute', top: -12, left: '50%', transform: 'translateX(-50%)', background: '#c8922a', color: '#fff', fontSize: 11, fontWeight: 700, padding: '4px 16px', borderRadius: 20, letterSpacing: 1 }}>MAIS POPULAR</div>}
              <h3 style={{ fontFamily: "'Cormorant Garamond', serif", fontSize: 22, fontWeight: 700, color: p.highlight ? '#fff' : '#1a3d2b', marginBottom: 4 }}>{p.name}</h3>
              <div style={{ marginBottom: 20 }}>
                <span style={{ fontFamily: "'Cormorant Garamond', serif", fontSize: 36, fontWeight: 700, color: p.highlight ? '#fdf0cc' : p.color }}>{p.price}</span>
                <span style={{ fontSize: 13, color: p.highlight ? '#c8e4d0' : '#7a9a84', marginLeft: 6 }}>{p.period}</span>
              </div>
              <ul style={{ listStyle: 'none', padding: 0, margin: '0 0 28px', display: 'flex', flexDirection: 'column', gap: 10 }}>
                {p.features.map(f => (
                  <li key={f} style={{ fontSize: 13, color: p.highlight ? '#e8f4ec' : '#4a6a54', display: 'flex', gap: 8, alignItems: 'flex-start' }}>
                    <span style={{ color: p.highlight ? '#90d0a8' : '#2d6a4f', marginTop: 1 }}>✓</span> {f}
                  </li>
                ))}
              </ul>
              <Link to="/register" style={{ display: 'block', textAlign: 'center', background: p.highlight ? '#c8922a' : p.color, color: '#fff', borderRadius: 8, padding: '12px 0', fontSize: 13, fontWeight: 700, textDecoration: 'none' }}>
                {p.cta}
              </Link>
            </div>
          ))}
        </div>
      </section>

      {/* CTA */}
      <section style={{ background: 'linear-gradient(135deg, #2d6a4f, #1a3d2b)', padding: '72px 40px', textAlign: 'center' }}>
        <h2 style={{ fontFamily: "'Cormorant Garamond', serif", fontSize: 40, color: '#faf6f0', marginBottom: 16 }}>Pronto para elevar seu criatório?</h2>
        <p style={{ color: '#7ab090', fontSize: 15, marginBottom: 36 }}>Crie sua conta gratuitamente e comece a usar agora mesmo.</p>
        <Link to="/register" style={{ background: '#c8922a', color: '#fff', borderRadius: 10, padding: '16px 40px', fontSize: 16, fontWeight: 700, textDecoration: 'none', display: 'inline-block' }}>
          Criar conta gratuita →
        </Link>
      </section>

      {/* Footer */}
      <footer style={{ background: '#111d16', padding: '32px 40px', textAlign: 'center' }}>
        <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'center', gap: 8, marginBottom: 12 }}>
          <span style={{ fontSize: 20 }}>🪶</span>
          <span style={{ fontFamily: "'Cormorant Garamond', serif", fontSize: 18, color: '#faf6f0', fontWeight: 700 }}>Plumora Gen</span>
        </div>
        <p style={{ fontSize: 12, color: '#4a6a54', margin: '0 0 8px' }}>
          Desenvolvido por <strong style={{ color: '#7ab090' }}>Prof. Dr. Atilio Sersun Calefi</strong> — Médico Veterinário, MSc e DSc em Patologia Veterinária &amp; Farmacologia
        </p>
        <p style={{ fontSize: 12, color: '#4a6a54', margin: '0 0 8px' }}>
          <strong style={{ color: '#7ab090' }}>CSVET Editora, Cursos e Treinamentos</strong> ·{' '}
          <a href="mailto:calefi@csvet.com.br" style={{ color: '#7ab090' }}>calefi@csvet.com.br</a>
        </p>
        <p style={{ fontSize: 11, color: '#2d4a38', marginTop: 8 }}>
          © 2026 CSVET Editora, Cursos e Treinamentos ·{' '}
          <Link to="/termos" style={{ color: '#4a6a54', textDecoration: 'none' }}>Termos de Serviço</Link>
          {' · '}
          <Link to="/privacidade" style={{ color: '#4a6a54', textDecoration: 'none' }}>Política de Privacidade (LGPD)</Link>
        </p>
      </footer>
    </div>
  )
}
