import { useState } from "react";
import { useI18n } from "@/contexts/I18nContext";
import { trpc } from "@/lib/trpc";
import { toast } from "sonner";
import { Button } from "@/components/ui/button";
import { Card, CardContent } from "@/components/ui/card";
import {
  Dialog, DialogContent, DialogHeader, DialogTitle, DialogFooter,
} from "@/components/ui/dialog";
import {
  Select, SelectContent, SelectItem, SelectTrigger, SelectValue,
} from "@/components/ui/select";
import { Input } from "@/components/ui/input";
import { Badge } from "@/components/ui/badge";
import { Share2, Plus, Copy, Link2Off, Users, Timer, GraduationCap, ShieldCheck } from "lucide-react";
import { Switch } from "@/components/ui/switch";
import BackButton from "@/components/BackButton";

export default function Sharing() {
  const { t } = useI18n();
  const utils = trpc.useUtils();

  const { data: links = [], isLoading } = trpc.sharing.list.useQuery();
  const { data: exams = [] } = trpc.exams.list.useQuery();
  const { data: classes = [] } = trpc.classes.list.useQuery({ includeArchived: false });

  const [showCreate, setShowCreate] = useState(false);
  const [selectedExamId, setSelectedExamId] = useState("");
  const [resultMode, setResultMode] = useState<"none" | "score" | "answer_key" | "all">("none");
  const [timeLimitStr, setTimeLimitStr] = useState("");
  const [selectedClassId, setSelectedClassId] = useState("");
  const [requireStudentId, setRequireStudentId] = useState(false);

  const createMutation = trpc.sharing.create.useMutation({
    onSuccess: () => {
      utils.sharing.list.invalidate();
      setShowCreate(false);
      setSelectedExamId("");
      setResultMode("none");
      setTimeLimitStr("");
      setSelectedClassId("");
      setRequireStudentId(false);
      toast.success(t("common.success"));
    },
    onError: (e) => toast.error(e.message),
  });
  const deactivateMutation = trpc.sharing.deactivate.useMutation({
    onSuccess: () => { utils.sharing.list.invalidate(); toast.success(t("common.success")); },
    onError: (e) => toast.error(e.message),
  });

  function buildPublicUrl(token: string): string {
    return `${window.location.origin}/q/${token}`;
  }

  function copyLink(token: string) {
    navigator.clipboard.writeText(buildPublicUrl(token));
    toast.success(t("sharing.linkCopied"));
  }

  return (
    <div className="max-w-3xl mx-auto space-y-6">
      <BackButton />
      <div className="flex items-center justify-between">
        <div className="flex items-center gap-3">
          <Share2 className="w-7 h-7 text-primary" />
          <div>
            <h1 className="text-2xl font-bold">{t("sharing.title")}</h1>
            <p className="text-muted-foreground text-sm">{t("sharing.subtitle")}</p>
          </div>
        </div>
        <Button onClick={() => setShowCreate(true)}>
          <Plus className="w-4 h-4 mr-2" />{t("sharing.createLink")}
        </Button>
      </div>

      {isLoading && <p className="text-muted-foreground">{t("common.loading")}</p>}

      {!isLoading && links.length === 0 && (
        <Card>
          <CardContent className="flex flex-col items-center py-12 gap-4">
            <Share2 className="w-12 h-12 text-muted-foreground" />
            <p className="text-muted-foreground">{t("sharing.noLinks")}</p>
            <Button onClick={() => setShowCreate(true)}>
              <Plus className="w-4 h-4 mr-2" />{t("sharing.createLink")}
            </Button>
          </CardContent>
        </Card>
      )}

      <div className="space-y-3">
        {links.map((link: any) => {
          const exam = exams.find((e: any) => e.id === link.examId);
          const url = buildPublicUrl(link.token);
          const isActive = link.active === "true";

          return (
            <Card key={link.id} className={!isActive ? "opacity-60" : ""}>
              <CardContent className="py-4">
                <div className="flex items-start justify-between gap-3">
                  <div className="flex-1 min-w-0">
                    <div className="flex items-center gap-2 mb-1 flex-wrap">
                      <p className="font-medium truncate">{exam?.name ?? `Prova #${link.examId}`}</p>
                      <Badge variant={isActive ? "default" : "secondary"} className="text-xs">
                        {isActive ? "Ativo" : "Inativo"}
                      </Badge>
                      {link.resultMode && link.resultMode !== "none" && (
                        <Badge variant="outline" className="text-xs text-blue-600 border-blue-300">
                          {{
                            score: "Nota visível",
                            answer_key: "Gabarito visível",
                            all: "Nota + Gabarito",
                          }[link.resultMode as string] ?? ""}
                        </Badge>
                      )}
                    </div>
                    <div className="flex items-center gap-2">
                      <Input value={url} readOnly className="h-7 text-xs font-mono" />
                      <Button variant="outline" size="icon" className="h-7 w-7 shrink-0" onClick={() => copyLink(link.token)}>
                        <Copy className="w-3.5 h-3.5" />
                      </Button>
                    </div>
                    <div className="flex items-center gap-3 mt-2 text-xs text-muted-foreground flex-wrap">
                      <span><Users className="inline w-3 h-3 mr-1" />{link.responseCount ?? 0} {t("sharing.responses")}</span>
                      {link.timeLimitMinutes && (
                        <span className="flex items-center gap-1 text-amber-600">
                          <Timer className="w-3 h-3" />{link.timeLimitMinutes} min
                        </span>
                      )}
                      {link.classId && (
                        <span className="flex items-center gap-1 text-emerald-700">
                          <GraduationCap className="w-3 h-3" />
                          {(classes as any[]).find((c: any) => c.id === link.classId)?.name ?? `Turma #${link.classId}`}
                        </span>
                      )}
                      {link.requireStudentId && (
                        <span className="flex items-center gap-1 text-violet-600">
                          <ShieldCheck className="w-3 h-3" />Exige matrícula
                        </span>
                      )}
                      <span>{t("sharing.createdAt")}: {new Date(link.createdAt).toLocaleDateString()}</span>
                    </div>
                  </div>
                  {isActive && (
                    <Button variant="ghost" size="sm" className="text-destructive hover:text-destructive shrink-0" onClick={() => deactivateMutation.mutate({ id: link.id })}>
                      <Link2Off className="w-4 h-4 mr-1" />{t("sharing.deactivate")}
                    </Button>
                  )}
                </div>
              </CardContent>
            </Card>
          );
        })}
      </div>

      <Dialog open={showCreate} onOpenChange={(o) => { if (!o) { setShowCreate(false); setSelectedExamId(""); } }}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>{t("sharing.createLink")}</DialogTitle>
          </DialogHeader>
          <div className="py-2 space-y-4">
            <div className="space-y-1">
              <p className="text-sm font-medium">Avaliação</p>
              <Select value={selectedExamId} onValueChange={setSelectedExamId}>
                <SelectTrigger><SelectValue placeholder="Selecionar avaliação..." /></SelectTrigger>
                <SelectContent>
                  {exams.map((e: any) => <SelectItem key={e.id} value={e.id.toString()}>{e.name}</SelectItem>)}
                </SelectContent>
              </Select>
            </div>

            <div className="space-y-1">
              <p className="text-sm font-medium">O que o aluno vê após enviar?</p>
              <Select value={resultMode} onValueChange={(v) => setResultMode(v as any)}>
                <SelectTrigger><SelectValue /></SelectTrigger>
                <SelectContent>
                  <SelectItem value="none">Nada (somente confirmação de envio)</SelectItem>
                  <SelectItem value="score">Apenas a nota</SelectItem>
                  <SelectItem value="answer_key">Somente o gabarito geral (sem nota)</SelectItem>
                  <SelectItem value="all">Nota + gabarito completo</SelectItem>
                </SelectContent>
              </Select>
              <p className="text-xs text-muted-foreground">
                {resultMode === "none" && "O aluno verá apenas uma mensagem de agradecimento."}
                {resultMode === "score" && "O aluno verá a pontuação obtida, mas não as respostas corretas."}
                {resultMode === "answer_key" && "O aluno verá as respostas corretas, mas não a nota numérica."}
                {resultMode === "all" && "O aluno verá nota e gabarito comparativo."}
              </p>
            </div>

            <div className="space-y-1">
              <p className="text-sm font-medium flex items-center gap-1.5">
                <GraduationCap className="w-4 h-4 text-emerald-600" />
                Restringir a uma turma (opcional)
              </p>
              <Select
                value={selectedClassId}
                onValueChange={(v) => { setSelectedClassId(v); if (!v) setRequireStudentId(false); }}
              >
                <SelectTrigger>
                  <SelectValue placeholder="Pública (qualquer pessoa com o link)" />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="">Pública — sem restrição de turma</SelectItem>
                  {(classes as any[]).map((c: any) => (
                    <SelectItem key={c.id} value={c.id.toString()}>
                      {c.name}{c.classYear ? ` (${c.classYear})` : ""}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
              {selectedClassId && (
                <div className="flex items-center justify-between pt-2">
                  <div>
                    <p className="text-sm font-medium flex items-center gap-1.5">
                      <ShieldCheck className="w-4 h-4 text-violet-500" />
                      Exigir número de matrícula
                    </p>
                    <p className="text-xs text-muted-foreground">
                      Aluno informa a matrícula e o sistema valida antes de liberar a prova.
                    </p>
                  </div>
                  <Switch
                    checked={requireStudentId}
                    onCheckedChange={setRequireStudentId}
                  />
                </div>
              )}
            </div>

            <div className="space-y-1">
              <p className="text-sm font-medium flex items-center gap-1.5">
                <Timer className="w-4 h-4 text-amber-500" />
                Limite de tempo (opcional)
              </p>
              <div className="flex items-center gap-2">
                <Input
                  type="number"
                  min={1}
                  max={600}
                  placeholder="Ex: 60"
                  value={timeLimitStr}
                  onChange={(e) => setTimeLimitStr(e.target.value)}
                  className="w-28"
                />
                <span className="text-sm text-muted-foreground">minutos</span>
              </div>
              <p className="text-xs text-muted-foreground">
                Deixe em branco para sem limite. O aluno verá um cronômetro regressivo e o quiz será enviado automaticamente ao expirar.
              </p>
            </div>
          </div>
          <DialogFooter>
            <Button variant="outline" onClick={() => setShowCreate(false)}>{t("common.cancel")}</Button>
            <Button
              onClick={() => {
                if (!selectedExamId) return;
                const timeLimit = timeLimitStr ? parseInt(timeLimitStr) : null;
                createMutation.mutate({
                  examId: Number(selectedExamId),
                  resultMode,
                  timeLimitMinutes: timeLimit,
                  classId: selectedClassId ? Number(selectedClassId) : null,
                  requireStudentId,
                });
              }}
              disabled={!selectedExamId || createMutation.isPending}
            >
              {t("sharing.createLink")}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </div>
  );
}
