import { Button } from "@/components/ui/button";
import { Card, CardContent } from "@/components/ui/card";
import { ArrowLeft } from "lucide-react";
import { useLocation } from "wouter";

export default function Privacy() {
  const [, setLocation] = useLocation();

  return (
    <div className="min-h-screen w-full bg-slate-50 py-10 px-4">
      <div className="max-w-3xl mx-auto">
        <Button variant="ghost" className="mb-4" onClick={() => setLocation("/")}>
          <ArrowLeft className="w-4 h-4 mr-2" />
          Voltar
        </Button>

        <Card className="shadow-sm">
          <CardContent className="pt-8 pb-8 prose prose-slate max-w-none">
            <h1 className="text-2xl font-bold text-slate-900 mb-1">Política de Privacidade — Prof-Aid</h1>
            <p className="text-sm text-slate-500 mb-6">Última atualização: 17 de junho de 2026</p>

            <p>
              Esta Política de Privacidade descreve como o <strong>Prof-Aid</strong> (site
              www.prof-aid.com e aplicativo móvel Prof-Aid) coleta, usa, armazena e protege
              dados pessoais, em conformidade com a Lei Geral de Proteção de Dados (LGPD —
              Lei nº 13.709/2018).
            </p>

            <h2>1. Quem somos</h2>
            <p>
              O Prof-Aid é uma plataforma para criação de avaliações, geração de gabaritos e
              correção automática por câmera, voltada a professores e instituições de ensino,
              operada por <strong>CSVET Editora, Cursos e Treinamentos</strong>.
              Contato do controlador de dados: <a href="mailto:calefi@csvet.com.br">calefi@csvet.com.br</a>.
            </p>

            <h2>2. Dados que coletamos</h2>
            <ul>
              <li><strong>Dados de conta do professor:</strong> nome, e-mail, senha (armazenada de forma criptografada) ou login via Google.</li>
              <li><strong>Dados de perfil institucional:</strong> universidade, telefone, logotipo, quando informados.</li>
              <li><strong>Dados acadêmicos cadastrados pelo professor:</strong> questões, provas, turmas e dados de alunos (nome, matrícula) necessários para gerar gabaritos e relatórios de desempenho.</li>
              <li><strong>Imagens de gabaritos:</strong> fotos/scans enviados para leitura óptica (OMR) das respostas marcadas pelos alunos.</li>
              <li><strong>Dados de pagamento e assinatura:</strong> processados por provedores de pagamento terceirizados; não armazenamos números completos de cartão de crédito.</li>
              <li><strong>Dados de uso:</strong> registros de acesso, atividade na plataforma e identificadores técnicos (IP, navegador) para fins de segurança e melhoria do serviço.</li>
            </ul>

            <h2>3. Como usamos os dados</h2>
            <ul>
              <li>Viabilizar a criação, impressão e correção de avaliações;</li>
              <li>Gerar relatórios de desempenho para professores e instituições;</li>
              <li>Processar pagamentos e gerenciar assinaturas;</li>
              <li>Comunicar atualizações, suporte e questões relacionadas à conta;</li>
              <li>Garantir segurança, prevenir fraudes e cumprir obrigações legais.</li>
            </ul>

            <h2>4. Compartilhamento de dados</h2>
            <p>
              Não vendemos dados pessoais. Compartilhamos dados estritamente necessários com:
            </p>
            <ul>
              <li>Provedores de infraestrutura (hospedagem do servidor e banco de dados);</li>
              <li>Provedores de autenticação (ex.: Google, para login social);</li>
              <li>Provedores de pagamento, para processamento de cobranças e assinaturas;</li>
              <li>Autoridades, quando exigido por lei ou ordem judicial.</li>
            </ul>

            <h2>5. Dados de alunos</h2>
            <p>
              Os dados de alunos (nome, matrícula, respostas e notas) são inseridos pelos
              professores/instituições, que atuam como controladores desses dados perante seus
              próprios alunos. O Prof-Aid atua como operador, processando esses dados apenas
              para viabilizar a geração de gabaritos, correção automática e relatórios
              solicitados pelo professor.
            </p>

            <h2>6. Retenção e exclusão</h2>
            <p>
              Mantemos os dados enquanto a conta estiver ativa ou conforme necessário para
              cumprir obrigações legais. O usuário pode solicitar a exclusão de sua conta e dos
              dados associados a qualquer momento pelo e-mail de contato abaixo.
            </p>

            <h2>7. Seus direitos (LGPD)</h2>
            <p>
              Você pode solicitar, a qualquer momento: confirmação de tratamento, acesso,
              correção, anonimização, portabilidade, eliminação de dados e revogação de
              consentimento, entrando em contato pelo e-mail{" "}
              <a href="mailto:calefi@csvet.com.br">calefi@csvet.com.br</a>.
            </p>

            <h2>8. Segurança</h2>
            <p>
              Adotamos medidas técnicas e organizacionais razoáveis para proteger os dados
              contra acesso não autorizado, perda ou alteração, incluindo senhas criptografadas
              e conexões HTTPS.
            </p>

            <h2>9. Aplicativo móvel</h2>
            <p>
              O aplicativo móvel Prof-Aid se conecta à plataforma web (www.prof-aid.com) para
              autenticação e sincronização de notas, e pode se conectar a um servidor local de
              processamento de imagem (OMR) na rede Wi-Fi do usuário para leitura de gabaritos.
              Nenhuma imagem de gabarito é compartilhada com terceiros além do necessário para
              esse processamento.
            </p>

            <h2>10. Alterações nesta política</h2>
            <p>
              Esta política pode ser atualizada periodicamente. A data da última atualização
              é exibida no topo desta página.
            </p>

            <h2>11. Contato</h2>
            <p>
              Dúvidas sobre esta política ou sobre seus dados pessoais: {" "}
              <a href="mailto:calefi@csvet.com.br">calefi@csvet.com.br</a>.
            </p>
          </CardContent>
        </Card>
      </div>
    </div>
  );
}
