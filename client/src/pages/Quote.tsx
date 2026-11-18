import { useState } from "react";
import { useI18n } from "@/contexts/I18nContext";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Textarea } from "@/components/ui/textarea";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import { HelpCircle, Building2, CheckCircle2, ChevronRight, Loader2, Sparkles, Database, GraduationCap, Camera } from "lucide-react";
import BackButton from "@/components/BackButton";
import { trpc } from "@/lib/trpc";
import { toast } from "sonner";

export default function Quote() {
  const { t } = useI18n();

  // Form states
  const [name, setName] = useState("");
  const [email, setEmail] = useState("");
  const [phone, setPhone] = useState("");
  const [company, setCompany] = useState("");
  const [role, setRole] = useState("");
  const [projectType, setProjectType] = useState("");
  const [estimatedVolume, setEstimatedVolume] = useState("");
  const [timeframe, setTimeframe] = useState("");
  const [description, setDescription] = useState("");
  const [features, setFeatures] = useState<string[]>([]);
  const [submitting, setSubmitting] = useState(false);
  const [success, setSuccess] = useState(false);

  const createMutation = trpc.quotes.create.useMutation();

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();

    if (!name.trim() || !email.trim() || !phone.trim() || !company.trim() || !role.trim() || !projectType || !estimatedVolume || !timeframe || !description.trim()) {
      toast.error("Por favor, preencha todos os campos obrigatórios.");
      return;
    }

    setSubmitting(true);
    try {
      await createMutation.mutateAsync({
        name,
        email,
        phone,
        company,
        role,
        projectType,
        estimatedVolume,
        timeframe,
        description,
        features,
      });
      toast.success(t("quote.success"));
      setSuccess(true);
    } catch (err: any) {
      toast.error(err?.message || t("quote.error"));
    } finally {
      setSubmitting(false);
    }
  };

  return (
    <div className="max-w-6xl mx-auto space-y-8 px-4 py-6">
      <BackButton />

      {/* Header Banner */}
      <div className="relative rounded-2xl overflow-hidden bg-gradient-to-r from-indigo-900 via-indigo-800 to-blue-900 text-white p-8 md:p-12 shadow-lg">
        <div className="absolute top-0 right-0 w-64 h-64 bg-white/5 rounded-full blur-2xl -mr-16 -mt-16"></div>
        <div className="relative z-10 max-w-2xl space-y-4">
          <div className="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-white/10 text-blue-200 text-xs font-semibold uppercase tracking-wider backdrop-blur-sm">
            <Sparkles className="w-3.5 h-3.5" /> Prof-Aid Enterprise
          </div>
          <h1 className="text-3xl md:text-4xl font-extrabold tracking-tight">
            {t("quote.title")}
          </h1>
          <p className="text-blue-100/90 text-sm md:text-base font-medium">
            {t("quote.subtitle")}
          </p>
        </div>
      </div>

      <div className="grid lg:grid-cols-12 gap-8 items-start">
        {/* Left Column: Enterprise Benefits */}
        <div className="lg:col-span-5 space-y-6">
          <h2 className="text-xl font-bold text-slate-800 tracking-tight">
            Soluções sob medida para sua Organização
          </h2>
          <p className="text-sm text-muted-foreground leading-relaxed">
            Nossa equipe de engenharia educacional desenvolve e personaliza ferramentas de avaliação para grandes fluxos e integração com sistemas legados (LMS).
          </p>

          <div className="space-y-4">
            {/* Feature 1 */}
            <div className="flex gap-4 p-4 rounded-xl border border-slate-100 bg-white shadow-sm hover:shadow-md transition">
              <div className="w-10 h-10 rounded-lg bg-emerald-50 text-emerald-600 flex items-center justify-center shrink-0">
                <Database className="w-5 h-5" />
              </div>
              <div className="space-y-1">
                <h3 className="text-sm font-semibold text-slate-800">
                  {t("quote.types.questions")}
                </h3>
                <p className="text-xs text-muted-foreground leading-relaxed">
                  Criação e diagramação de bancos de questões exclusivos organizados por disciplinas, habilidades e competências exigidas pela sua instituição.
                </p>
              </div>
            </div>

            {/* Feature 2 */}
            <div className="flex gap-4 p-4 rounded-xl border border-slate-100 bg-white shadow-sm hover:shadow-md transition">
              <div className="w-10 h-10 rounded-lg bg-indigo-50 text-indigo-600 flex items-center justify-center shrink-0">
                <GraduationCap className="w-5 h-5" />
              </div>
              <div className="space-y-1">
                <h3 className="text-sm font-semibold text-slate-800">
                  {t("quote.types.exams")}
                </h3>
                <p className="text-xs text-muted-foreground leading-relaxed">
                  Ambientes de provas em nuvem customizados para avaliações online, segurança antifraude, integração com sistemas escolares e relatórios analíticos de desempenho.
                </p>
              </div>
            </div>

            {/* Feature 3 */}
            <div className="flex gap-4 p-4 rounded-xl border border-slate-100 bg-white shadow-sm hover:shadow-md transition">
              <div className="w-10 h-10 rounded-lg bg-teal-50 text-teal-600 flex items-center justify-center shrink-0">
                <Camera className="w-5 h-5" />
              </div>
              <div className="space-y-1">
                <h3 className="text-sm font-semibold text-slate-800">
                  {t("quote.types.grading")}
                </h3>
                <p className="text-xs text-muted-foreground leading-relaxed">
                  Soluções integradas de correção óptica (leitura de gabaritos físicos por celular) projetadas para reduzir custos operacionais e economizar tempo dos avaliadores.
                </p>
              </div>
            </div>
          </div>

          <div className="p-4 rounded-xl bg-slate-50 border border-slate-150 flex items-center gap-3">
            <Building2 className="w-5 h-5 text-slate-500 shrink-0" />
            <p className="text-xs text-slate-600 font-medium">
              Atendimento focado em Escolas, Faculdades, Cursinhos e Concursos Públicos.
            </p>
          </div>
        </div>

        {/* Right Column: Quote Form */}
        <div className="lg:col-span-7">
          {success ? (
            <Card className="border-2 border-emerald-100 bg-emerald-50/20 shadow-md">
              <CardContent className="py-12 text-center space-y-4">
                <div className="w-16 h-16 rounded-full bg-emerald-100 text-emerald-600 flex items-center justify-center mx-auto shadow-inner">
                  <CheckCircle2 className="w-10 h-10" />
                </div>
                <h3 className="text-xl font-bold text-slate-800">Solicitação Enviada!</h3>
                <p className="text-sm text-slate-600 max-w-md mx-auto">
                  {t("quote.success")}
                </p>
                <div className="pt-4">
                  <Button onClick={() => setSuccess(false)} variant="outline" className="gap-1.5">
                    Enviar outro orçamento <ChevronRight className="w-4 h-4" />
                  </Button>
                </div>
              </CardContent>
            </Card>
          ) : (
            <Card className="shadow-md bg-white border border-slate-100">
              <CardHeader className="pb-4">
                <CardTitle className="text-lg tracking-tight">Formulário de Cotação</CardTitle>
                <CardDescription className="text-xs">
                  Preencha os campos abaixo e nosso time comercial e técnico retornará com uma proposta detalhada.
                </CardDescription>
              </CardHeader>
              <CardContent>
                <form onSubmit={handleSubmit} className="space-y-5">
                  <div className="grid sm:grid-cols-2 gap-4">
                    {/* Nome */}
                    <div className="space-y-1.5">
                      <Label htmlFor="q-name">{t("quote.name")} *</Label>
                      <Input
                        id="q-name"
                        required
                        placeholder="Ex: João da Silva"
                        value={name}
                        onChange={(e) => setName(e.target.value)}
                      />
                    </div>

                    {/* E-mail */}
                    <div className="space-y-1.5">
                      <Label htmlFor="q-email">{t("quote.email")} *</Label>
                      <Input
                        id="q-email"
                        type="email"
                        required
                        placeholder="Ex: joao@instituicao.edu.br"
                        value={email}
                        onChange={(e) => setEmail(e.target.value)}
                      />
                    </div>
                  </div>

                  <div className="grid sm:grid-cols-2 gap-4">
                    {/* Telefone */}
                    <div className="space-y-1.5">
                      <Label htmlFor="q-phone">{t("quote.phone")} *</Label>
                      <Input
                        id="q-phone"
                        required
                        placeholder="Ex: (11) 99999-9999"
                        value={phone}
                        onChange={(e) => setPhone(e.target.value)}
                      />
                    </div>

                    {/* Empresa */}
                    <div className="space-y-1.5">
                      <Label htmlFor="q-company">{t("quote.company")} *</Label>
                      <Input
                        id="q-company"
                        required
                        placeholder="Ex: Colégio Professor Aid"
                        value={company}
                        onChange={(e) => setCompany(e.target.value)}
                      />
                    </div>
                  </div>

                  <div className="grid sm:grid-cols-2 gap-4">
                    {/* Cargo */}
                    <div className="space-y-1.5">
                      <Label htmlFor="q-role">{t("quote.role")} *</Label>
                      <Input
                        id="q-role"
                        required
                        placeholder="Ex: Diretor de Tecnologia / Coordenador"
                        value={role}
                        onChange={(e) => setRole(e.target.value)}
                      />
                    </div>

                    {/* Tipo de Projeto */}
                    <div className="space-y-1.5">
                      <Label>{t("quote.projectType")} *</Label>
                      <Select value={projectType} onValueChange={setProjectType}>
                        <SelectTrigger>
                          <SelectValue placeholder="Selecione o serviço" />
                        </SelectTrigger>
                        <SelectContent>
                          <SelectItem value="questions">{t("quote.types.questions")}</SelectItem>
                          <SelectItem value="exams">{t("quote.types.exams")}</SelectItem>
                          <SelectItem value="grading">{t("quote.types.grading")}</SelectItem>
                          <SelectItem value="other">{t("quote.types.other")}</SelectItem>
                        </SelectContent>
                      </Select>
                    </div>
                  </div>

                  <div className="grid sm:grid-cols-2 gap-4">
                    {/* Volume Estimado */}
                    <div className="space-y-1.5">
                      <Label>{t("quote.estimatedVolume")} *</Label>
                      <Select value={estimatedVolume} onValueChange={setEstimatedVolume}>
                        <SelectTrigger>
                          <SelectValue placeholder="Selecione a escala" />
                        </SelectTrigger>
                        <SelectContent>
                          <SelectItem value="v1">{t("quote.volumes.v1")}</SelectItem>
                          <SelectItem value="v2">{t("quote.volumes.v2")}</SelectItem>
                          <SelectItem value="v3">{t("quote.volumes.v3")}</SelectItem>
                        </SelectContent>
                      </Select>
                    </div>

                    {/* Prazo */}
                    <div className="space-y-1.5">
                      <Label>{t("quote.timeframe")} *</Label>
                      <Select value={timeframe} onValueChange={setTimeframe}>
                        <SelectTrigger>
                          <SelectValue placeholder="Selecione o prazo" />
                        </SelectTrigger>
                        <SelectContent>
                          <SelectItem value="now">{t("quote.timeframes.now")}</SelectItem>
                          <SelectItem value="month">{t("quote.timeframes.month")}</SelectItem>
                          <SelectItem value="quarter">{t("quote.timeframes.quarter")}</SelectItem>
                          <SelectItem value="future">{t("quote.timeframes.future")}</SelectItem>
                        </SelectContent>
                      </Select>
                    </div>
                  </div>

                  {/* Recursos Desejados (Múltipla Escolha) */}
                  <div className="space-y-2">
                    <Label className="text-sm font-semibold text-slate-800">Recursos Adicionais Desejados (Opcional)</Label>
                    <div className="grid sm:grid-cols-2 gap-2.5">
                      {[
                        { id: "lms", label: "Integração LMS (Moodle, Canvas, Classroom)" },
                        { id: "ai", label: "Geração de Questões com Inteligência Artificial (IA)" },
                        { id: "latex", label: "Suporte a LaTeX (Fórmulas Matemáticas/Científicas)" },
                        { id: "analytics", label: "Relatórios Estatísticos Avançados (TRI / Métricas)" },
                        { id: "mobile", label: "Aplicativo de Correção Mobile para Professores" },
                        { id: "lockdown", label: "Lockdown Browser / Segurança de Provas Online" },
                      ].map((feat) => (
                        <label
                          key={feat.id}
                          className="flex items-start gap-3 p-3 rounded-lg border border-slate-100 bg-slate-50/30 hover:bg-slate-50/80 cursor-pointer transition select-none"
                        >
                          <input
                            type="checkbox"
                            className="mt-0.5 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500 h-4 w-4 shrink-0"
                            checked={features.includes(feat.id)}
                            onChange={(e) => {
                              if (e.target.checked) {
                                setFeatures([...features, feat.id]);
                              } else {
                                setFeatures(features.filter((id) => id !== feat.id));
                              }
                            }}
                          />
                          <span className="text-xs font-medium text-slate-700 leading-tight">
                            {feat.label}
                          </span>
                        </label>
                      ))}
                    </div>
                  </div>

                  {/* Descrição */}
                  <div className="space-y-1.5">
                    <Label htmlFor="q-desc">{t("quote.description")} *</Label>
                    <Textarea
                      id="q-desc"
                      required
                      placeholder="Descreva detalhes adicionais: se deseja questões discursivas ou de múltipla escolha, disciplinas específicas, integrações com LMS já utilizado, prazos críticos, etc."
                      rows={5}
                      value={description}
                      onChange={(e) => setDescription(e.target.value)}
                    />
                  </div>

                  <Button type="submit" disabled={submitting} className="w-full gap-2">
                    {submitting ? (
                      <>
                        <Loader2 className="w-4 h-4 animate-spin" />
                        {t("quote.submitting")}
                      </>
                    ) : (
                      t("quote.submit")
                    )}
                  </Button>
                </form>
              </CardContent>
            </Card>
          )}
        </div>
      </div>
    </div>
  );
}
