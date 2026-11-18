import { Link } from 'react-router-dom'

export default function TermsPage() {
  return (
    <div style={{ fontFamily: "'DM Sans', sans-serif", background: '#faf6f0', minHeight: '100vh' }}>
      {/* Header */}
      <header style={{ background: '#1a3d2b', padding: '0 40px', height: 60, display: 'flex', alignItems: 'center', justifyContent: 'space-between' }}>
        <Link to="/" style={{ display: 'flex', alignItems: 'center', gap: 8, textDecoration: 'none' }}>
          <span style={{ fontSize: 22 }}>🪶</span>
          <span style={{ fontFamily: "'Cormorant Garamond', serif", fontSize: 20, fontWeight: 700, color: '#faf6f0' }}>Plumora Gen</span>
        </Link>
        <Link to="/register" style={{ color: '#c8d4c8', fontSize: 13, textDecoration: 'none' }}>← Voltar ao cadastro</Link>
      </header>

      <main style={{ maxWidth: 820, margin: '0 auto', padding: '60px 40px 80px' }}>
        <h1 style={{ fontFamily: "'Cormorant Garamond', serif", fontSize: 40, fontWeight: 700, color: '#1a3d2b', marginBottom: 8 }}>
          Termos de Serviço
        </h1>
        <p style={{ fontSize: 13, color: '#7a9a84', marginBottom: 40 }}>Última atualização: junho de 2026</p>

        <div style={{ display: 'flex', flexDirection: 'column', gap: 32 }}>
          <section>
            <h2 style={{ fontFamily: "'Cormorant Garamond', serif", fontSize: 22, color: '#1a3d2b', marginBottom: 10 }}>1. Identificação</h2>
            <p style={{ fontSize: 14, color: '#4a6a54', lineHeight: 1.8 }}>
              O <strong>Plumora Gen</strong> é uma plataforma SaaS desenvolvida e operada pela <strong>CSVET Editora, Cursos e Treinamentos</strong>, pessoa jurídica de direito privado, com sede no Brasil.
              Contato: <a href="mailto:calefi@csvet.com.br" style={{ color: '#2d6a4f' }}>calefi@csvet.com.br</a>
            </p>
          </section>

          <section>
            <h2 style={{ fontFamily: "'Cormorant Garamond', serif", fontSize: 22, color: '#1a3d2b', marginBottom: 10 }}>2. Aceitação dos Termos</h2>
            <p style={{ fontSize: 14, color: '#4a6a54', lineHeight: 1.8 }}>
              Ao se cadastrar e utilizar a plataforma Plumora Gen, o usuário declara ter lido, compreendido e concordado integralmente com estes Termos de Serviço e com nossa <Link to="/privacidade" style={{ color: '#2d6a4f' }}>Política de Privacidade</Link>. Caso não concorde, não utilize a plataforma.
            </p>
          </section>

          <section>
            <h2 style={{ fontFamily: "'Cormorant Garamond', serif", fontSize: 22, color: '#1a3d2b', marginBottom: 10 }}>3. Descrição do Serviço</h2>
            <p style={{ fontSize: 14, color: '#4a6a54', lineHeight: 1.8 }}>
              O Plumora Gen oferece ferramentas de gestão para criadores de aves, incluindo: cadastro de plantel, calculadora genética multi-loci, pedigree e claudograma, controle de vacinação e saúde, módulo PigeonVet com suporte veterinário, registro de corridas e índices zootécnicos.
            </p>
          </section>

          <section>
            <h2 style={{ fontFamily: "'Cormorant Garamond', serif", fontSize: 22, color: '#1a3d2b', marginBottom: 10 }}>4. Cadastro e Conta</h2>
            <p style={{ fontSize: 14, color: '#4a6a54', lineHeight: 1.8 }}>
              O usuário é responsável pela veracidade das informações cadastradas e pela segurança de suas credenciais de acesso. É vedado compartilhar a conta com terceiros ou utilizar a plataforma para fins ilícitos. A CSVET se reserva o direito de suspender contas que violem estes Termos.
            </p>
          </section>

          <section>
            <h2 style={{ fontFamily: "'Cormorant Garamond', serif", fontSize: 22, color: '#1a3d2b', marginBottom: 10 }}>5. Planos e Pagamentos</h2>
            <p style={{ fontSize: 14, color: '#4a6a54', lineHeight: 1.8 }}>
              O Plumora Gen oferece plano gratuito com funcionalidades limitadas e planos pagos com recursos adicionais (Básico, Pro e Premium). Os valores e benefícios de cada plano estão descritos na página de preços. Os pagamentos são processados via Stripe e cobrados de forma recorrente mensal. O cancelamento pode ser realizado a qualquer momento, com validade até o fim do período pago.
            </p>
          </section>

          <section>
            <h2 style={{ fontFamily: "'Cormorant Garamond', serif", fontSize: 22, color: '#1a3d2b', marginBottom: 10 }}>6. Propriedade Intelectual</h2>
            <p style={{ fontSize: 14, color: '#4a6a54', lineHeight: 1.8 }}>
              Todo o conteúdo da plataforma — incluindo código, design, algoritmos genéticos, textos e marca Plumora Gen — é propriedade exclusiva da CSVET Editora, Cursos e Treinamentos. É proibida a reprodução, distribuição ou uso comercial sem autorização prévia por escrito.
            </p>
          </section>

          <section>
            <h2 style={{ fontFamily: "'Cormorant Garamond', serif", fontSize: 22, color: '#1a3d2b', marginBottom: 10 }}>7. Limitação de Responsabilidade</h2>
            <p style={{ fontSize: 14, color: '#4a6a54', lineHeight: 1.8 }}>
              As informações genéticas fornecidas pela plataforma têm caráter orientativo e educacional. A CSVET não se responsabiliza por decisões de cruzamento, resultados sanitários ou prejuízos decorrentes do uso das informações geradas pela plataforma. Para decisões clínicas, consulte sempre um Médico Veterinário habilitado.
            </p>
          </section>

          <section>
            <h2 style={{ fontFamily: "'Cormorant Garamond', serif", fontSize: 22, color: '#1a3d2b', marginBottom: 10 }}>8. Alterações dos Termos</h2>
            <p style={{ fontSize: 14, color: '#4a6a54', lineHeight: 1.8 }}>
              A CSVET poderá atualizar estes Termos periodicamente. Alterações relevantes serão comunicadas por e-mail ou notificação na plataforma. O uso continuado após a publicação de novos Termos implica aceitação das mudanças.
            </p>
          </section>

          <section>
            <h2 style={{ fontFamily: "'Cormorant Garamond', serif", fontSize: 22, color: '#1a3d2b', marginBottom: 10 }}>9. Foro e Lei Aplicável</h2>
            <p style={{ fontSize: 14, color: '#4a6a54', lineHeight: 1.8 }}>
              Estes Termos são regidos pela legislação brasileira. Eventuais disputas serão resolvidas preferencialmente por mediação, e na impossibilidade, pelo foro da comarca de domicílio da CSVET.
            </p>
          </section>

          <section style={{ background: '#f0f5f0', borderRadius: 12, padding: '20px 24px', border: '1px solid #d0e4d8' }}>
            <h2 style={{ fontFamily: "'Cormorant Garamond', serif", fontSize: 22, color: '#1a3d2b', marginBottom: 8 }}>Contato</h2>
            <p style={{ fontSize: 14, color: '#4a6a54', lineHeight: 1.8, margin: 0 }}>
              <strong>CSVET Editora, Cursos e Treinamentos</strong><br />
              Responsável técnico: Prof. Dr. Atilio Sersun Calefi — MV, MSc, DSc<br />
              E-mail: <a href="mailto:calefi@csvet.com.br" style={{ color: '#2d6a4f', fontWeight: 600 }}>calefi@csvet.com.br</a>
            </p>
          </section>
        </div>
      </main>

      <footer style={{ background: '#111d16', padding: '24px 40px', textAlign: 'center' }}>
        <p style={{ fontSize: 12, color: '#4a6a54', margin: 0 }}>
          © 2026 CSVET Editora, Cursos e Treinamentos · <Link to="/privacidade" style={{ color: '#7ab090', textDecoration: 'none' }}>Política de Privacidade</Link>
        </p>
      </footer>
    </div>
  )
}
