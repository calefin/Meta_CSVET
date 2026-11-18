import { Link } from 'react-router-dom'

export default function PrivacyPage() {
  return (
    <div style={{ fontFamily: "'DM Sans', sans-serif", background: '#faf6f0', minHeight: '100vh' }}>
      <header style={{ background: '#1a3d2b', padding: '0 40px', height: 60, display: 'flex', alignItems: 'center', justifyContent: 'space-between' }}>
        <Link to="/" style={{ display: 'flex', alignItems: 'center', gap: 8, textDecoration: 'none' }}>
          <span style={{ fontSize: 22 }}>🪶</span>
          <span style={{ fontFamily: "'Cormorant Garamond', serif", fontSize: 20, fontWeight: 700, color: '#faf6f0' }}>Plumora Gen</span>
        </Link>
        <Link to="/register" style={{ color: '#c8d4c8', fontSize: 13, textDecoration: 'none' }}>← Voltar ao cadastro</Link>
      </header>

      <main style={{ maxWidth: 820, margin: '0 auto', padding: '60px 40px 80px' }}>
        <h1 style={{ fontFamily: "'Cormorant Garamond', serif", fontSize: 40, fontWeight: 700, color: '#1a3d2b', marginBottom: 8 }}>
          Política de Privacidade
        </h1>
        <p style={{ fontSize: 13, color: '#7a9a84', marginBottom: 8 }}>Última atualização: junho de 2026</p>
        <p style={{ fontSize: 13, color: '#7a9a84', marginBottom: 40 }}>Em conformidade com a <strong>Lei Geral de Proteção de Dados (LGPD) — Lei nº 13.709/2018</strong></p>

        <div style={{ display: 'flex', flexDirection: 'column', gap: 32 }}>
          <section>
            <h2 style={{ fontFamily: "'Cormorant Garamond', serif", fontSize: 22, color: '#1a3d2b', marginBottom: 10 }}>1. Controlador dos Dados</h2>
            <p style={{ fontSize: 14, color: '#4a6a54', lineHeight: 1.8 }}>
              O controlador dos seus dados pessoais é a <strong>CSVET Editora, Cursos e Treinamentos</strong>, representada pelo Prof. Dr. Atilio Sersun Calefi.
              Contato do encarregado (DPO): <a href="mailto:calefi@csvet.com.br" style={{ color: '#2d6a4f' }}>calefi@csvet.com.br</a>
            </p>
          </section>

          <section>
            <h2 style={{ fontFamily: "'Cormorant Garamond', serif", fontSize: 22, color: '#1a3d2b', marginBottom: 10 }}>2. Dados Coletados</h2>
            <p style={{ fontSize: 14, color: '#4a6a54', lineHeight: 1.8, marginBottom: 12 }}>Coletamos os seguintes dados:</p>
            <ul style={{ fontSize: 14, color: '#4a6a54', lineHeight: 2, paddingLeft: 20 }}>
              <li><strong>Cadastro:</strong> nome completo, endereço de e-mail, senha (armazenada com hash bcrypt)</li>
              <li><strong>Plantel:</strong> informações sobre suas aves, registros genéticos, histórico de saúde e vacinação</li>
              <li><strong>Uso:</strong> logs de acesso, endereço IP, dados de sessão (tokens JWT)</li>
              <li><strong>Pagamento:</strong> plano contratado (dados de cartão processados exclusivamente pelo Stripe, não armazenados por nós)</li>
            </ul>
          </section>

          <section>
            <h2 style={{ fontFamily: "'Cormorant Garamond', serif", fontSize: 22, color: '#1a3d2b', marginBottom: 10 }}>3. Finalidade do Tratamento</h2>
            <ul style={{ fontSize: 14, color: '#4a6a54', lineHeight: 2, paddingLeft: 20 }}>
              <li>Prestação dos serviços da plataforma Plumora Gen</li>
              <li>Comunicações relacionadas à conta e à assinatura</li>
              <li>Alertas veterinários e lembretes de vacinação</li>
              <li>Melhoria contínua da plataforma (dados anonimizados)</li>
              <li>Cumprimento de obrigações legais</li>
            </ul>
          </section>

          <section>
            <h2 style={{ fontFamily: "'Cormorant Garamond', serif", fontSize: 22, color: '#1a3d2b', marginBottom: 10 }}>4. Base Legal (LGPD)</h2>
            <p style={{ fontSize: 14, color: '#4a6a54', lineHeight: 1.8 }}>
              O tratamento de dados é realizado com base nos seguintes fundamentos da LGPD (art. 7º):
            </p>
            <ul style={{ fontSize: 14, color: '#4a6a54', lineHeight: 2, paddingLeft: 20 }}>
              <li><strong>Consentimento</strong> (art. 7º, I) — coletado no momento do cadastro</li>
              <li><strong>Execução de contrato</strong> (art. 7º, V) — necessário para prestar o serviço</li>
              <li><strong>Legítimo interesse</strong> (art. 7º, IX) — segurança e melhoria da plataforma</li>
            </ul>
          </section>

          <section>
            <h2 style={{ fontFamily: "'Cormorant Garamond', serif", fontSize: 22, color: '#1a3d2b', marginBottom: 10 }}>5. Compartilhamento de Dados</h2>
            <p style={{ fontSize: 14, color: '#4a6a54', lineHeight: 1.8 }}>
              Não vendemos seus dados. Compartilhamos apenas com fornecedores essenciais para operar a plataforma:
            </p>
            <ul style={{ fontSize: 14, color: '#4a6a54', lineHeight: 2, paddingLeft: 20 }}>
              <li><strong>Stripe</strong> — processamento de pagamentos</li>
              <li><strong>Resend</strong> — envio de e-mails transacionais</li>
              <li><strong>MinIO</strong> — armazenamento de arquivos (self-hosted, dados não saem dos servidores)</li>
            </ul>
          </section>

          <section>
            <h2 style={{ fontFamily: "'Cormorant Garamond', serif", fontSize: 22, color: '#1a3d2b', marginBottom: 10 }}>6. Retenção de Dados</h2>
            <p style={{ fontSize: 14, color: '#4a6a54', lineHeight: 1.8 }}>
              Seus dados são mantidos enquanto a conta estiver ativa. Após o cancelamento, os dados são retidos por 90 dias para fins de conformidade legal, sendo então excluídos permanentemente, salvo obrigação legal de retenção por prazo maior.
            </p>
          </section>

          <section>
            <h2 style={{ fontFamily: "'Cormorant Garamond', serif", fontSize: 22, color: '#1a3d2b', marginBottom: 10 }}>7. Seus Direitos (LGPD, art. 18)</h2>
            <p style={{ fontSize: 14, color: '#4a6a54', lineHeight: 1.8, marginBottom: 12 }}>Você tem direito a:</p>
            <ul style={{ fontSize: 14, color: '#4a6a54', lineHeight: 2, paddingLeft: 20 }}>
              <li>Confirmar a existência de tratamento de seus dados</li>
              <li>Acessar seus dados pessoais</li>
              <li>Corrigir dados incompletos, inexatos ou desatualizados</li>
              <li>Solicitar a anonimização, bloqueio ou eliminação de dados desnecessários</li>
              <li>Portabilidade dos dados a outro fornecedor de serviço</li>
              <li>Revogar o consentimento a qualquer momento</li>
              <li>Peticionar à Autoridade Nacional de Proteção de Dados (ANPD)</li>
            </ul>
            <p style={{ fontSize: 14, color: '#4a6a54', lineHeight: 1.8, marginTop: 12 }}>
              Para exercer seus direitos, entre em contato: <a href="mailto:calefi@csvet.com.br" style={{ color: '#2d6a4f', fontWeight: 600 }}>calefi@csvet.com.br</a>
            </p>
          </section>

          <section>
            <h2 style={{ fontFamily: "'Cormorant Garamond', serif", fontSize: 22, color: '#1a3d2b', marginBottom: 10 }}>8. Segurança</h2>
            <p style={{ fontSize: 14, color: '#4a6a54', lineHeight: 1.8 }}>
              Adotamos medidas técnicas e organizacionais para proteger seus dados: senhas com hash bcrypt (custo 12), tokens JWT com expiração curta, comunicação HTTPS, rate limiting por IP, e infraestrutura self-hosted com acesso restrito.
            </p>
          </section>

          <section>
            <h2 style={{ fontFamily: "'Cormorant Garamond', serif", fontSize: 22, color: '#1a3d2b', marginBottom: 10 }}>9. Cookies</h2>
            <p style={{ fontSize: 14, color: '#4a6a54', lineHeight: 1.8 }}>
              Utilizamos apenas armazenamento local (localStorage) para manter sua sessão ativa. Não utilizamos cookies de rastreamento ou publicidade.
            </p>
          </section>

          <section style={{ background: '#f0f5f0', borderRadius: 12, padding: '20px 24px', border: '1px solid #d0e4d8' }}>
            <h2 style={{ fontFamily: "'Cormorant Garamond', serif", fontSize: 22, color: '#1a3d2b', marginBottom: 8 }}>Encarregado de Proteção de Dados (DPO)</h2>
            <p style={{ fontSize: 14, color: '#4a6a54', lineHeight: 1.8, margin: 0 }}>
              <strong>CSVET Editora, Cursos e Treinamentos</strong><br />
              Prof. Dr. Atilio Sersun Calefi — MV, MSc, DSc em Patologia Veterinária e Farmacologia<br />
              E-mail: <a href="mailto:calefi@csvet.com.br" style={{ color: '#2d6a4f', fontWeight: 600 }}>calefi@csvet.com.br</a>
            </p>
          </section>
        </div>
      </main>

      <footer style={{ background: '#111d16', padding: '24px 40px', textAlign: 'center' }}>
        <p style={{ fontSize: 12, color: '#4a6a54', margin: 0 }}>
          © 2026 CSVET Editora, Cursos e Treinamentos · <Link to="/termos" style={{ color: '#7ab090', textDecoration: 'none' }}>Termos de Serviço</Link>
        </p>
      </footer>
    </div>
  )
}
