import { useState } from "react";
import { useI18n } from "@/contexts/I18nContext";
import {
  Accordion, AccordionContent, AccordionItem, AccordionTrigger,
} from "@/components/ui/accordion";
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Textarea } from "@/components/ui/textarea";
import { Badge } from "@/components/ui/badge";
import {
  Dialog,
  DialogContent,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import { HelpCircle, Mail, MessageCircle, Plus, Loader2, Clock, CheckCircle, AlertTriangle } from "lucide-react";
import BackButton from "@/components/BackButton";
import { trpc } from "@/lib/trpc";
import { toast } from "sonner";

const FAQ_PT = [
  {
    q: "Como criar minha primeira avaliação?",
    a: "Acesse 'Avaliações' no menu lateral e clique em 'Criar Nova Avaliação'. Siga o assistente de 3 passos: informações, questões e preparação para impressão.",
  },
  {
    q: "Como importar questões de uma planilha Excel?",
    a: "Acesse 'Questões' > 'Importar Questões'. Faça upload de um arquivo .xlsx ou .docx com as questões formatadas. O sistema processará automaticamente.",
  },
  {
    q: "Como importar alunos em uma turma?",
    a: "Acesse 'Alunos' e clique em 'Importar CSV' ou 'Adicionar Aluno'. Após cadastrar, vá em 'Turmas' e os alunos estarão disponíveis para associação.",
  },
  {
    q: "Como funciona a correção automática por câmera?",
    a: "Após gerar o PDF com gabarito, acesse 'Gabaritos' e envie a foto do gabarito preenchido. O sistema extrai as respostas marcadas automaticamente.",
  },
  {
    q: "Posso gerar múltiplas versões com questões embaralhadas?",
    a: "Sim! Na etapa de preparação para impressão, use o slider de 'Versões Diferentes' (máximo 50) e selecione a opção de embaralhamento desejada.",
  },
  {
    q: "Onde encontro os modelos de arquivos para download?",
    a: "Os modelos estão disponíveis diretamente nas páginas de importação ('Importar Questões' e 'Alunos'). Você pode baixá-los em formatos XLSX, DOCX, CSV ou JSON para preenchê-los seguindo as orientações e evitar falhas de importação.",
  },
  {
    q: "O que é o gabarito adaptativo e como gerá-lo?",
    a: "O gabarito adaptativo ajusta o número de questões e alternativas dinamicamente com base nas questões da sua prova, otimizando o espaço da folha para economizar papel. Na tela de detalhes da sua prova, clique em 'Visualizar Gabarito' para baixar a folha de respostas em PDF ou exportá-la como imagem de alta resolução (PNG) para leitura similar ao ZipGrade e Gradepen.",
  },
  {
    q: "Como funciona o registro de atividades?",
    a: "O sistema registra automaticamente suas ações mais importantes (como criar provas, importar turmas ou cadastrar alunos). Esse histórico fica visível na linha do tempo da tela inicial ('Início') para ajudar você a se situar no seu trabalho.",
  },
  {
    q: "Como acompanhar e ver a resposta do suporte aos meus chamados?",
    a: "Na própria tela de FAQ, logo abaixo das perguntas frequentes, você tem a seção 'Seus Chamados de Suporte'. Ali é exibida a lista de todos os seus chamados com o status atual (Aberto, Em Andamento, Resolvido) e o campo com a resposta detalhada fornecida pelo administrador do sistema.",
  },
];

const FAQ_EN = [
  {
    q: "How do I create my first assessment?",
    a: "Go to 'Assessments' in the sidebar and click 'Create New Assessment'. Follow the 3-step wizard: information, questions, and print preparation.",
  },
  {
    q: "How do I import questions from an Excel spreadsheet?",
    a: "Go to 'Questions' > 'Import Questions'. Upload a .xlsx or .docx file with formatted questions. The system will process them automatically.",
  },
  {
    q: "Where can I find the template files for download?",
    a: "The templates are available directly on the import pages ('Import Questions' and 'Students'). You can download them in XLSX, DOCX, CSV, or JSON formats to fill them out following the guidelines and prevent import failures.",
  },
  {
    q: "What is the adaptive answer sheet and how do I generate it?",
    a: "The adaptive answer sheet dynamically adjusts the number of questions and alternatives based on your exam, optimizing the page layout to save paper. On the exam details page, click 'View Answer Sheet' to download it in PDF format or export it as a high-resolution PNG image, compatible with OMR systems like ZipGrade.",
  },
  {
    q: "How does the activity log work?",
    a: "The system automatically logs your most important actions (like creating exams, importing students, or saving questions). This history is visible in the timeline on the home page ('Home') to help you track your progress.",
  },
  {
    q: "How do I track and view responses to my support tickets?",
    a: "On this FAQ page, right below the frequently asked questions, you will find the 'Your Support Tickets' section. It displays all your submitted tickets with their current status (Open, In Progress, Resolved) and the detailed reply from the administrator.",
  },
];

const STATUS_LABELS: Record<string, string> = {
  open: "Aberto",
  in_progress: "Em Andamento",
  resolved: "Resolvido",
  closed: "Fechado",
};

const STATUS_COLORS: Record<string, string> = {
  open: "bg-blue-50 text-blue-700 border-blue-200",
  in_progress: "bg-amber-50 text-amber-700 border-amber-200",
  resolved: "bg-green-50 text-green-700 border-green-200",
  closed: "bg-slate-50 text-slate-700 border-slate-200",
};

const PRIORITY_LABELS: Record<string, string> = {
  low: "Baixa",
  medium: "Média",
  high: "Alta",
};

export default function FAQ() {
  const { t, locale } = useI18n();
  const faqItems = locale === "en" ? FAQ_EN : FAQ_PT;

  const [dialogOpen, setDialogOpen] = useState(false);
  const [title, setTitle] = useState("");
  const [description, setDescription] = useState("");
  const [priority, setPriority] = useState<"low" | "medium" | "high">("medium");
  const [submitting, setSubmitting] = useState(false);

  const { data: tickets = [], isLoading: ticketsLoading, refetch } = trpc.tickets.listMyTickets.useQuery();
  const createMutation = trpc.tickets.create.useMutation();

  const handleOpenCreate = () => {
    setTitle("");
    setDescription("");
    setPriority("medium");
    setDialogOpen(true);
  };

  const handleCreateTicket = async () => {
    if (!title.trim() || !description.trim()) {
      toast.error("Preencha todos os campos obrigatórios.");
      return;
    }
    setSubmitting(true);
    try {
      await createMutation.mutateAsync({
        title,
        description,
        priority,
      });
      toast.success("Chamado de suporte aberto com sucesso!");
      setDialogOpen(false);
      refetch();
    } catch (e: any) {
      toast.error(e?.message || "Erro ao abrir chamado.");
    } finally {
      setSubmitting(false);
    }
  };

  return (
    <div className="max-w-4xl mx-auto space-y-8">
      <BackButton />
      
      {/* Header */}
      <div className="flex items-center gap-3">
        <HelpCircle className="w-8 h-8 text-primary" />
        <div>
          <h1 className="text-2xl font-bold">{t("faq.title")}</h1>
          <p className="text-muted-foreground text-sm">{t("faq.subtitle")}</p>
        </div>
      </div>

      <div className="grid md:grid-cols-3 gap-6 items-start">
        {/* Left Column: FAQ Accordion */}
        <div className="md:col-span-2 space-y-4">
          <h2 className="text-lg font-semibold text-slate-800">Dúvidas Frequentes</h2>
          <Accordion type="single" collapsible className="space-y-2">
            {faqItems.map((item, i) => (
              <AccordionItem key={i} value={`faq-${i}`} className="border rounded-lg px-4 bg-white shadow-sm">
                <AccordionTrigger className="text-left text-sm font-medium hover:no-underline py-3">{item.q}</AccordionTrigger>
                <AccordionContent className="text-sm text-muted-foreground pb-3">{item.a}</AccordionContent>
              </AccordionItem>
            ))}
          </Accordion>
        </div>

        {/* Right Column: Support Channels */}
        <div className="md:col-span-1 space-y-4">
          <h2 className="text-lg font-semibold text-slate-800">Canais de Contato</h2>
          <Card className="shadow-sm">
            <CardHeader className="pb-3">
              <CardTitle className="text-sm font-semibold">Contato Direto</CardTitle>
            </CardHeader>
            <CardContent className="space-y-3">
              <Button variant="outline" className="w-full gap-2 justify-start text-xs h-9">
                <Mail className="w-4 h-4 text-primary" /> suporte@prof-aid.com
              </Button>
              <Button variant="outline" className="w-full gap-2 justify-start text-xs h-9">
                <MessageCircle className="w-4 h-4 text-primary" /> Chat de Suporte Online
              </Button>
            </CardContent>
          </Card>
        </div>
      </div>

      {/* Support Tickets Section */}
      <div className="space-y-4 pt-4 border-t">
        <div className="flex items-center justify-between flex-wrap gap-2">
          <div>
            <h2 className="text-lg font-semibold text-slate-800">Seus Chamados de Suporte</h2>
            <p className="text-xs text-muted-foreground">Registre problemas ou solicite melhorias no sistema</p>
          </div>
          <Button onClick={handleOpenCreate} size="sm" className="gap-1.5">
            <Plus className="w-4 h-4" /> Novo Chamado
          </Button>
        </div>

        {ticketsLoading ? (
          <div className="flex justify-center py-8">
            <Loader2 className="w-6 h-6 animate-spin text-blue-600" />
          </div>
        ) : tickets.length === 0 ? (
          <Card className="border border-dashed bg-slate-50/50">
            <CardContent className="py-12 text-center text-sm text-muted-foreground">
              Você não possui nenhum chamado de suporte em aberto.
            </CardContent>
          </Card>
        ) : (
          <div className="grid sm:grid-cols-2 gap-4">
            {tickets.map((ticket: any) => (
              <Card key={ticket.id} className="border shadow-sm bg-white hover:shadow-md transition">
                <CardHeader className="pb-2">
                  <div className="flex items-start justify-between gap-2">
                    <span className="font-semibold text-sm text-slate-800 truncate">{ticket.title}</span>
                    <Badge variant="outline" className={`text-[10px] px-2 py-0.5 border font-semibold shrink-0 rounded-full ${STATUS_COLORS[ticket.status]}`}>
                      {STATUS_LABELS[ticket.status]}
                    </Badge>
                  </div>
                  <CardDescription className="text-xs flex items-center gap-1.5 mt-1">
                    <Clock className="w-3.5 h-3.5" />
                    {new Date(ticket.createdAt).toLocaleDateString("pt-BR")} às {new Date(ticket.createdAt).toLocaleTimeString("pt-BR", { hour: "2-digit", minute: "2-digit" })}
                    <span className="text-slate-300">·</span>
                    Prioridade: {PRIORITY_LABELS[ticket.priority]}
                  </CardDescription>
                </CardHeader>
                <CardContent className="space-y-3">
                  <p className="text-xs text-slate-600 line-clamp-3 bg-slate-50 p-2.5 rounded border border-slate-100">{ticket.description}</p>
                  
                  {ticket.adminResponse ? (
                    <div className="p-3 bg-emerald-50/50 border border-emerald-100 rounded-lg space-y-1">
                      <p className="text-[10px] font-semibold text-emerald-800 flex items-center gap-1">
                        <CheckCircle className="w-3.5 h-3.5 text-emerald-600" />
                        Resposta do Suporte:
                      </p>
                      <p className="text-xs text-emerald-950 font-medium whitespace-pre-wrap">{ticket.adminResponse}</p>
                      {ticket.respondedAt && (
                        <p className="text-[9px] text-emerald-700/60 mt-1">
                          Respondido em: {new Date(ticket.respondedAt).toLocaleDateString("pt-BR")}
                        </p>
                      )}
                    </div>
                  ) : (
                    <div className="text-[10px] text-amber-700 bg-amber-50/40 border border-amber-100 p-2 rounded flex items-center gap-1.5">
                      <AlertTriangle className="w-3.5 h-3.5 shrink-0" />
                      <span>Aguardando análise da equipe de suporte técnica.</span>
                    </div>
                  )}
                </CardContent>
              </Card>
            ))}
          </div>
        )}
      </div>

      {/* Ticket Create Dialog */}
      <Dialog open={dialogOpen} onOpenChange={setDialogOpen}>
        <DialogContent className="max-w-md">
          <DialogHeader>
            <DialogTitle>Abrir Novo Chamado de Suporte</DialogTitle>
          </DialogHeader>
          <div className="space-y-4 py-2">
            <div className="space-y-1.5">
              <Label htmlFor="t-title">Título / Assunto *</Label>
              <Input
                id="t-title"
                placeholder="Ex: Erro ao gerar PDF da Prova"
                value={title}
                onChange={(e) => setTitle(e.target.value)}
              />
            </div>
            <div className="space-y-1.5">
              <Label htmlFor="t-desc">Descrição Detalhada *</Label>
              <Textarea
                id="t-desc"
                placeholder="Explique o que aconteceu, passos para reproduzir o problema ou qual melhoria você deseja sugerir..."
                rows={4}
                value={description}
                onChange={(e) => setDescription(e.target.value)}
              />
            </div>
            <div className="space-y-1.5">
              <Label>Prioridade</Label>
              <Select value={priority} onValueChange={(v: any) => setPriority(v)}>
                <SelectTrigger>
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="low">Baixa (Dúvida ou Sugestão)</SelectItem>
                  <SelectItem value="medium">Média (Erro pontual)</SelectItem>
                  <SelectItem value="high">Alta (Travamento de recurso)</SelectItem>
                </SelectContent>
              </Select>
            </div>
          </div>
          <DialogFooter>
            <Button variant="outline" onClick={() => setDialogOpen(false)} disabled={submitting}>
              Cancelar
            </Button>
            <Button onClick={handleCreateTicket} disabled={submitting} className="gap-1.5">
              {submitting && <Loader2 className="w-4 h-4 animate-spin" />}
              Enviar Chamado
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </div>
  );
}
