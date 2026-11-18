import { Button } from "@/components/ui/button";
import { Card, CardContent } from "@/components/ui/card";
import { ArrowLeft } from "lucide-react";
import { useLocation } from "wouter";

export default function Terms() {
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
            <h1 className="text-2xl font-bold text-slate-900 mb-1">Termos de Uso — Prof-Aid</h1>
            <p className="text-sm text-slate-500 mb-6">Última atualização: 17 de junho de 2026</p>

            <p>
              Estes Termos de Uso regem o acesso e uso da plataforma Prof-Aid (site
              www.prof-aid.com e aplicativo móvel Prof-Aid), operada por{" "}
              <strong>CSVET Editora, Cursos e Treinamentos</strong>. Ao criar uma conta ou usar
              o aplicativo, você concorda com estes termos e com a nossa{" "}
              <a href="/privacy">Política de Privacidade</a>.
            </p>

            <h2>1. Descrição do serviço</h2>
            <p>
              O Prof-Aid permite a criação de avaliações, geração de gabaritos, correção
              automática por leitura óptica (OMR) e gestão de turmas e alunos para fins
              educacionais.
            </p>

            <h2>2. Cadastro e responsabilidade da conta</h2>
            <p>
              O usuário é responsável por manter a confidencialidade de suas credenciais de
              acesso e por todas as atividades realizadas em sua conta. Os dados inseridos
              sobre alunos e turmas são de responsabilidade do professor/instituição que os
              cadastra.
            </p>

            <h2>3. Planos e pagamentos</h2>
            <p>
              Alguns recursos podem exigir assinatura paga. Os valores, formas de pagamento e
              política de cancelamento são apresentados na página de Planos antes da
              contratação. Pagamentos são processados por provedores terceirizados.
            </p>

            <h2>4. Uso aceitável</h2>
            <p>É proibido usar a plataforma para:</p>
            <ul>
              <li>Atividades ilegais ou que violem direitos de terceiros;</li>
              <li>Tentativas de acesso não autorizado a contas ou dados de outros usuários;</li>
              <li>Envio de conteúdo ofensivo, fraudulento ou que viole direitos autorais.</li>
            </ul>

            <h2>5. Propriedade intelectual</h2>
            <p>
              O conteúdo gerado pelo professor (questões, provas) pertence a ele. A plataforma,
              marca Prof-Aid, código e design pertencem à CSVET Editora, Cursos e Treinamentos.
            </p>

            <h2>6. Disponibilidade e limitação de responsabilidade</h2>
            <p>
              O serviço é fornecido "como está". Buscamos manter alta disponibilidade, mas não
              garantimos operação ininterrupta. Não nos responsabilizamos por perdas decorrentes
              de uso indevido da plataforma ou de falhas alheias à nossa infraestrutura.
            </p>

            <h2>7. Cancelamento e suspensão</h2>
            <p>
              Podemos suspender ou encerrar contas que violem estes termos. O usuário pode
              cancelar sua conta a qualquer momento solicitando pelo e-mail de contato.
            </p>

            <h2>8. Alterações nestes termos</h2>
            <p>
              Podemos atualizar estes termos periodicamente. Alterações relevantes serão
              comunicadas pelos canais da plataforma.
            </p>

            <h2>9. Contato</h2>
            <p>
              Dúvidas sobre estes termos: <a href="mailto:calefi@csvet.com.br">calefi@csvet.com.br</a>.
            </p>
          </CardContent>
        </Card>
      </div>
    </div>
  );
}
