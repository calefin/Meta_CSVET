import { useState } from "react";
import { trpc } from "@/lib/trpc";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Textarea } from "@/components/ui/textarea";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { toast } from "sonner";
import { GraduationCap, Loader2, Send, CheckCircle2, BookOpen } from "lucide-react";
import { useLocation } from "wouter";

export default function ContentRequest() {
  const [, navigate] = useLocation();
  const [requestType, setRequestType] = useState<"multiple_choice" | "discursive" | "both">("multiple_choice");
  const [selectedDiscipline, setSelectedDiscipline] = useState<string>("");
  const [selectedSubject, setSelectedSubject] = useState<string>("");
  const [selectedTopic, setSelectedTopic] = useState<string>("");
  const [subjectTag, setSubjectTag] = useState("");
  const [notes, setNotes] = useState("");
  const [submitted, setSubmitted] = useState(false);

  // Queries for dropdowns
  const { data: disciplinesList, isLoading: loadingDisciplines } = trpc.questions.disciplines.useQuery();

  const { data: subjectsList, isLoading: loadingSubjects } = trpc.questions.subjects.useQuery(
    { disciplineId: Number(selectedDiscipline) },
    { enabled: !!selectedDiscipline }
  );

  const { data: topicsList, isLoading: loadingTopics } = trpc.questions.topics.useQuery(
    { subjectId: Number(selectedSubject) },
    { enabled: !!selectedSubject }
  );

  const requestMutation = trpc.contentRequests.create.useMutation({
    onSuccess: () => {
      toast.success("Pedido de conteúdo enviado com sucesso!");
      setSubmitted(true);
    },
    onError: (err) => {
      toast.error(err.message || "Erro ao enviar pedido de conteúdo.");
    },
  });

  const handleDisciplineChange = (val: string) => {
    setSelectedDiscipline(val);
    setSelectedSubject("");
    setSelectedTopic("");
  };

  const handleSubjectChange = (val: string) => {
    setSelectedSubject(val);
    setSelectedTopic("");
  };

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    if (!selectedDiscipline || !selectedSubject) {
      toast.error("Por favor, preencha a Grande Área e a Disciplina.");
      return;
    }

    requestMutation.mutate({
      requestType,
      disciplineId: Number(selectedDiscipline),
      subjectId: Number(selectedSubject),
      topicId: selectedTopic ? Number(selectedTopic) : undefined,
      subjectTag: subjectTag.trim() || undefined,
      notes: notes.trim() || undefined,
    });
  };

  const resetForm = () => {
    setRequestType("multiple_choice");
    setSelectedDiscipline("");
    setSelectedSubject("");
    setSelectedTopic("");
    setSubjectTag("");
    setNotes("");
    setSubmitted(false);
  };

  if (submitted) {
    return (
      <div className="min-h-screen bg-background py-12 px-4 flex items-center justify-center">
        <Card className="max-w-md w-full border-emerald-500/20 bg-emerald-500/5 shadow-lg shadow-emerald-500/5 text-center">
          <CardContent className="pt-8 space-y-4">
            <CheckCircle2 className="h-16 w-16 mx-auto text-emerald-500 animate-bounce" />
            <h1 className="font-serif text-2xl font-bold text-foreground">Solicitação Enviada!</h1>
            <p className="text-muted-foreground text-sm leading-relaxed">
              Obrigado por nos ajudar a melhorar o VetRank. Nossa equipe acadêmica de veterinários irá analisar seu pedido e providenciar a criação das novas questões solicitadas o quanto antes.
            </p>
            <div className="pt-4 flex flex-col gap-2">
              <Button onClick={resetForm} className="w-full">
                Fazer Outro Pedido
              </Button>
              <Button variant="outline" onClick={() => navigate("/")} className="w-full">
                Voltar à Página Inicial
              </Button>
            </div>
          </CardContent>
        </Card>
      </div>
    );
  }

  return (
    <div className="min-h-screen bg-background py-10 px-4">
      <div className="max-w-xl mx-auto space-y-6">
        {/* Header */}
        <div className="text-center space-y-2">
          <div className="inline-flex p-3 rounded-full bg-primary/10 border border-primary/20 text-primary mb-2">
            <GraduationCap className="h-6 w-6" />
          </div>
          <h1 className="font-serif text-3xl font-bold text-foreground">Conteúdo Sob Demanda</h1>
          <p className="text-muted-foreground text-sm max-w-sm mx-auto leading-relaxed">
            Sentiu falta de algum tema específico nas questões? Diga-nos o que você deseja estudar e nossa equipe criará novas questões exclusivas para você.
          </p>
        </div>

        {/* Card Form */}
        <Card className="border-border/50 bg-card">
          <CardHeader>
            <CardTitle className="font-serif text-lg flex items-center gap-2">
              <BookOpen className="h-5 w-5 text-primary" />
              Solicitar Novas Questões
            </CardTitle>
            <CardDescription className="text-xs">
              Preencha os dados abaixo. Nós utilizaremos essas informações para direcionar a produção acadêmica.
            </CardDescription>
          </CardHeader>
          <CardContent>
            <form onSubmit={handleSubmit} className="space-y-4 font-sans text-sm">
              
              {/* Tipo de Questão */}
              <div className="space-y-1.5">
                <Label className="text-xs font-semibold">Tipo de Questão Desejado *</Label>
                <Select value={requestType} onValueChange={(val: any) => setRequestType(val)}>
                  <SelectTrigger className="h-10">
                    <SelectValue />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value="multiple_choice">Múltipla Escolha (Alternativas)</SelectItem>
                    <SelectItem value="discursive">Discursivas (Resposta Escrita)</SelectItem>
                    <SelectItem value="both">Ambos os formatos</SelectItem>
                  </SelectContent>
                </Select>
              </div>

              {/* Grande Área (Discipline) */}
              <div className="space-y-1.5">
                <Label className="text-xs font-semibold flex justify-between">
                  <span>Grande Área *</span>
                  {loadingDisciplines && <Loader2 className="h-3 w-3 animate-spin text-muted-foreground" />}
                </Label>
                <Select value={selectedDiscipline} onValueChange={handleDisciplineChange}>
                  <SelectTrigger className="h-10">
                    <SelectValue placeholder="Selecione a grande área" />
                  </SelectTrigger>
                  <SelectContent>
                    {disciplinesList?.map((d) => (
                      <SelectItem key={d.id} value={String(d.id)}>{d.namePt}</SelectItem>
                    ))}
                  </SelectContent>
                </Select>
              </div>

              {/* Disciplina (Subject) */}
              <div className="space-y-1.5">
                <Label className="text-xs font-semibold flex justify-between">
                  <span>Disciplina *</span>
                  {loadingSubjects && <Loader2 className="h-3 w-3 animate-spin text-muted-foreground" />}
                </Label>
                <Select
                  value={selectedSubject}
                  onValueChange={handleSubjectChange}
                  disabled={!selectedDiscipline}
                >
                  <SelectTrigger className="h-10">
                    <SelectValue placeholder={selectedDiscipline ? "Selecione a disciplina" : "Selecione a grande área primeiro"} />
                  </SelectTrigger>
                  <SelectContent>
                    {subjectsList?.map((s) => (
                      <SelectItem key={s.id} value={String(s.id)}>{s.namePt}</SelectItem>
                    ))}
                  </SelectContent>
                </Select>
              </div>

              {/* Assunto Específico (Topic) */}
              <div className="space-y-1.5">
                <Label className="text-xs font-semibold flex justify-between">
                  <span>Assunto Específico (Opcional)</span>
                  {loadingTopics && <Loader2 className="h-3 w-3 animate-spin text-muted-foreground" />}
                </Label>
                <Select
                  value={selectedTopic}
                  onValueChange={setSelectedTopic}
                  disabled={!selectedSubject}
                >
                  <SelectTrigger className="h-10">
                    <SelectValue placeholder={selectedSubject ? "Selecione o assunto" : "Selecione a disciplina primeiro"} />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value="none">Não listar assunto específico</SelectItem>
                    {topicsList?.map((t) => (
                      <SelectItem key={t.id} value={String(t.id)}>{t.namePt}</SelectItem>
                    ))}
                  </SelectContent>
                </Select>
              </div>

              {/* Tag / Assunto Personalizado */}
              <div className="space-y-1.5">
                <Label className="text-xs font-semibold">Assunto não listado? Escreva aqui:</Label>
                <Input
                  value={subjectTag}
                  onChange={(e) => setSubjectTag(e.target.value)}
                  placeholder="Ex: Fisiologia Renal do Cão, Glomerulonefrite..."
                  className="h-10"
                  maxLength={256}
                />
              </div>

              {/* Observações */}
              <div className="space-y-1.5">
                <Label className="text-xs font-semibold">Observações / Detalhes Adicionais (Opcional)</Label>
                <Textarea
                  value={notes}
                  onChange={(e) => setNotes(e.target.value)}
                  placeholder="Gostaria de sugerir alguma fonte, bibliografia, ou detalhar o tipo de caso clínico que gostaria de praticar? Descreva aqui..."
                  rows={4}
                  className="resize-none text-sm"
                  maxLength={2000}
                />
                <p className="text-[10px] text-muted-foreground text-right">{notes.length} / 2000 caracteres</p>
              </div>

              {/* Submit Button */}
              <Button
                type="submit"
                disabled={!selectedDiscipline || !selectedSubject || requestMutation.isPending}
                className="w-full gap-2 mt-2 h-11 bg-primary hover:bg-primary/90 text-primary-foreground font-semibold"
              >
                {requestMutation.isPending ? (
                  <>
                    <Loader2 className="h-4 w-4 animate-spin" /> Enviando pedido...
                  </>
                ) : (
                  <>
                    <Send className="h-4 w-4" /> Enviar Solicitação
                  </>
                )}
              </Button>
            </form>
          </CardContent>
        </Card>
      </div>
    </div>
  );
}
