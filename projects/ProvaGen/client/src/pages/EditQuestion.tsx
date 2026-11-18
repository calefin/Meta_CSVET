import React, { useState, useEffect } from "react";
import { useAuth } from "@/_core/hooks/useAuth";
import { useLocation } from "wouter";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from "@/components/ui/card";
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs";
import { Alert, AlertDescription } from "@/components/ui/alert";
import { Badge } from "@/components/ui/badge";
import { Loader2, AlertTriangle, Eye, Save, Trash2, History, RotateCcw, ChevronDown, ChevronUp } from "lucide-react";
import { toast } from "sonner";
import { trpc } from "@/lib/trpc";

import {
  QuestionFormState,
  QUESTION_TYPE_TEMPLATES,
  validateQuestion,
  MultipleChoiceQuestion,
  EssayQuestion,
  AssertionReasonQuestion,
  Difficulty,
} from "@/../../shared/_core/questionTypes";

import { MultipleChoiceForm } from "@/components/questions/MultipleChoiceForm";
import { EssayForm } from "@/components/questions/EssayForm";
import { AssertionReasonForm } from "@/components/questions/AssertionReasonForm";
import { QuestionPreview } from "@/components/questions/QuestionPreview";
import { ImageUploader } from "@/components/questions/ImageUploader";
import {
  AlertDialog,
  AlertDialogAction,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogTitle,
} from "@/components/ui/alert-dialog";

interface EditQuestionProps {
  questionId: number;
}

export function EditQuestion({ questionId }: EditQuestionProps) {
  const { user, loading: authLoading } = useAuth();
  const [, navigate] = useLocation();
  const getQuestion = trpc.questions.get.useQuery({ id: questionId });
  const updateMutation = trpc.questions.update.useMutation();
  const deleteMutation = trpc.questions.delete.useMutation();
  const revertMutation = trpc.questions.revertToVersion.useMutation();
  const getStorageQuota = trpc.questions.getStorageQuota.useQuery();
  const getDisciplines = trpc.questions.getDisciplinesAndSubjects.useQuery();
  const getVersionHistory = trpc.questions.getVersionHistory.useQuery({ questionId });

  const [activeTab, setActiveTab] = useState("form");
  const [loading, setLoading] = useState(false);
  const [showDeleteDialog, setShowDeleteDialog] = useState(false);
  const [errors, setErrors] = useState<string[]>([]);
  const [expandedVersion, setExpandedVersion] = useState<number | null>(null);

  const [formData, setFormData] = useState<QuestionFormState | null>(null);
  const [customDiscipline, setCustomDiscipline] = useState("");
  const [customSubject, setCustomSubject] = useState("");
  const [removeImageKeys, setRemoveImageKeys] = useState<string[]>([]);

  const disciplines = (getDisciplines.data?.disciplines || []) as string[];
  const subjects = (getDisciplines.data?.subjects || []) as string[];
  const quota = getStorageQuota.data;

  useEffect(() => {
    if (getQuestion.data && !formData) {
      const question = getQuestion.data;
      setFormData({
        id: question.id,
        type: question.type,
        statement: question.statement,
        discipline: question.discipline,
        subject: question.subject,
        difficulty: question.difficulty as Difficulty,
        tags: question.tags || [],
        content: question.content,
        imageBase64Array: [],
      });
    }
  }, [getQuestion.data, formData]);

  if (authLoading || getQuestion.isLoading) {
    return (
      <div className="flex items-center justify-center min-h-screen">
        <Loader2 className="animate-spin w-8 h-8" />
      </div>
    );
  }

  if (!user || !formData) {
    navigate("/question-bank");
    return null;
  }

  const handleQuestionTypeChange = (type: string) => {
    let newContent: any = { type };

    switch (type) {
      case "multiple_choice":
      case "multiple_choice_complex":
        newContent = {
          ...newContent,
          alternatives: { a: "", b: "", c: "", d: "" },
          correctAnswer: "a",
        };
        break;
      case "essay":
        newContent = {
          ...newContent,
          suggestedAnswer: "",
          evaluationCriteria: [],
        };
        break;
      case "assertion_reason":
        newContent = {
          ...newContent,
          items: [
            {
              id: 1,
              assertion: "",
              isTrue: true,
              reason: "",
              reasonCorrect: true,
            },
            {
              id: 2,
              assertion: "",
              isTrue: true,
              reason: "",
              reasonCorrect: true,
            },
            {
              id: 3,
              assertion: "",
              isTrue: true,
              reason: "",
              reasonCorrect: true,
            },
            {
              id: 4,
              assertion: "",
              isTrue: true,
              reason: "",
              reasonCorrect: true,
            },
          ],
        };
        break;
    }

    setFormData({
      ...formData,
      type: type as any,
      content: newContent,
    });
  };

  const handleSave = async () => {
    setErrors([]);

    const validation = validateQuestion(formData);
    if (!validation.valid) {
      setErrors(validation.errors);
      toast.error("Existem erros no formulário");
      return;
    }

    const finalDiscipline = customDiscipline || formData.discipline;
    const finalSubject = customSubject || formData.subject;

    if (!finalDiscipline || !finalSubject) {
      setErrors(["Disciplina e assunto são obrigatórios"]);
      toast.error("Preencha disciplina e assunto");
      return;
    }

    setLoading(true);
    try {
      await updateMutation.mutateAsync({
        id: formData.id || questionId,
        statement: formData.statement,
        discipline: finalDiscipline,
        subject: finalSubject,
        difficulty: formData.difficulty,
        tags: formData.tags,
        content: formData.content,
        imageBase64Array: formData.imageBase64Array,
        removeImageKeys,
      });

      toast.success("Questão atualizada com sucesso!");
      navigate("/question-bank");
    } catch (error: any) {
      const errorMsg = error.message || "Erro ao atualizar questão";
      setErrors([errorMsg]);
      toast.error(errorMsg);
    } finally {
      setLoading(false);
    }
  };

  const handleDelete = async () => {
    setLoading(true);
    try {
      await deleteMutation.mutateAsync({
        id: formData.id || questionId,
      });
      toast.success("Questão deletada com sucesso!");
      navigate("/question-bank");
    } catch (error: any) {
      toast.error(error.message || "Erro ao deletar questão");
    } finally {
      setLoading(false);
      setShowDeleteDialog(false);
    }
  };

  const templateSelected = QUESTION_TYPE_TEMPLATES.find(
    (t) => t.type === formData.type
  );

  return (
    <div className="min-h-screen bg-gray-50 py-8 px-4">
      <div className="max-w-6xl mx-auto">
        <div className="mb-6">
          <h1 className="text-3xl font-bold">Editar Questão</h1>
          <p className="text-gray-600 mt-1">
            Modifique os dados da questão e salve as alterações
          </p>
        </div>

        {errors.length > 0 && (
          <Alert variant="destructive" className="mb-6">
            <AlertTriangle className="h-4 w-4" />
            <AlertDescription>
              <ul className="mt-2 space-y-1">
                {errors.map((error, idx) => (
                  <li key={idx}>• {error}</li>
                ))}
              </ul>
            </AlertDescription>
          </Alert>
        )}

        <Tabs value={activeTab} onValueChange={setActiveTab} className="w-full">
          <TabsList className="grid w-full grid-cols-4">
            <TabsTrigger value="form">Formulário</TabsTrigger>
            <TabsTrigger value="preview" onClick={() => setErrors([])}>
              <Eye className="w-4 h-4 mr-1" /> Prévia
            </TabsTrigger>
            <TabsTrigger value="images">Imagens</TabsTrigger>
            <TabsTrigger value="history">
              <History className="w-4 h-4 mr-1" /> Histórico
              {(getVersionHistory.data?.length ?? 0) > 0 && (
                <Badge variant="secondary" className="ml-1 text-xs px-1 py-0 h-4">
                  {getVersionHistory.data!.length}
                </Badge>
              )}
            </TabsTrigger>
          </TabsList>

          <TabsContent value="form" className="space-y-6">
            <Card>
              <CardHeader>
                <CardTitle>Informações Básicas</CardTitle>
              </CardHeader>
              <CardContent className="space-y-4">
                <div>
                  <label htmlFor="statement" className="block text-sm font-medium mb-1">
                    Enunciado/Pergunta *
                  </label>
                  <textarea
                    id="statement"
                    value={formData.statement}
                    onChange={(e) =>
                      setFormData({ ...formData, statement: e.target.value })
                    }
                    placeholder="Digite a pergunta ou enunciado da questão"
                    className="w-full min-h-24 p-3 border rounded-lg focus:ring-2 focus:ring-blue-500"
                  />
                </div>

                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                  <div>
                    <label htmlFor="discipline" className="block text-sm font-medium mb-1">
                      Disciplina *
                    </label>
                    <Select
                      value={formData.discipline}
                      onValueChange={(value) => {
                        setFormData({ ...formData, discipline: value });
                        setCustomDiscipline("");
                      }}
                    >
                      <SelectTrigger id="discipline">
                        <SelectValue placeholder="Selecionar..." />
                      </SelectTrigger>
                      <SelectContent>
                        {disciplines.map((d) => (
                          <SelectItem key={d} value={d}>
                            {d}
                          </SelectItem>
                        ))}
                        <SelectItem value="custom">+ Nova disciplina</SelectItem>
                      </SelectContent>
                    </Select>
                    {formData.discipline === "custom" && (
                      <Input
                        placeholder="Digite a nova disciplina"
                        value={customDiscipline}
                        onChange={(e) =>
                          setCustomDiscipline(e.target.value)
                        }
                        className="mt-2"
                      />
                    )}
                  </div>

                  <div>
                    <label htmlFor="subject" className="block text-sm font-medium mb-1">
                      Assunto *
                    </label>
                    <Select
                      value={formData.subject}
                      onValueChange={(value) => {
                        setFormData({ ...formData, subject: value });
                        setCustomSubject("");
                      }}
                    >
                      <SelectTrigger id="subject">
                        <SelectValue placeholder="Selecionar..." />
                      </SelectTrigger>
                      <SelectContent>
                        {subjects.map((s) => (
                          <SelectItem key={s} value={s}>
                            {s}
                          </SelectItem>
                        ))}
                        <SelectItem value="custom">+ Novo assunto</SelectItem>
                      </SelectContent>
                    </Select>
                    {formData.subject === "custom" && (
                      <Input
                        placeholder="Digite o novo assunto"
                        value={customSubject}
                        onChange={(e) =>
                          setCustomSubject(e.target.value)
                        }
                        className="mt-2"
                      />
                    )}
                  </div>
                </div>

                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                  <div>
                    <label htmlFor="difficulty" className="block text-sm font-medium mb-1">
                      Dificuldade
                    </label>
                    <Select
                      value={formData.difficulty}
                      onValueChange={(value) =>
                        setFormData({
                          ...formData,
                          difficulty: value as Difficulty,
                        })
                      }
                    >
                      <SelectTrigger id="difficulty">
                        <SelectValue />
                      </SelectTrigger>
                      <SelectContent>
                        <SelectItem value="easy">Fácil</SelectItem>
                        <SelectItem value="medium">Médio</SelectItem>
                        <SelectItem value="hard">Difícil</SelectItem>
                      </SelectContent>
                    </Select>
                  </div>

                  <div>
                    <label htmlFor="type" className="block text-sm font-medium mb-1">
                      Tipo de Questão *
                    </label>
                    <Select
                      value={formData.type}
                      onValueChange={handleQuestionTypeChange}
                    >
                      <SelectTrigger id="type">
                        <SelectValue />
                      </SelectTrigger>
                      <SelectContent>
                        {QUESTION_TYPE_TEMPLATES.map((template) => (
                          <SelectItem key={template.type} value={template.type}>
                            {template.label}
                          </SelectItem>
                        ))}
                      </SelectContent>
                    </Select>
                  </div>
                </div>
              </CardContent>
            </Card>

            <Card>
              <CardHeader>
                <CardTitle>{templateSelected?.label}</CardTitle>
                <CardDescription>
                  {templateSelected?.description}
                </CardDescription>
              </CardHeader>
              <CardContent>
                {formData.type === "multiple_choice" && (
                  <MultipleChoiceForm
                    data={formData.content as MultipleChoiceQuestion}
                    onChange={(content) =>
                      setFormData({
                        ...formData,
                        content,
                      })
                    }
                  />
                )}

                {formData.type === "multiple_choice_complex" && (
                  <MultipleChoiceForm
                    data={formData.content as MultipleChoiceQuestion}
                    onChange={(content) =>
                      setFormData({
                        ...formData,
                        content,
                      })
                    }
                    showComplexOptions
                  />
                )}

                {formData.type === "essay" && (
                  <EssayForm
                    data={formData.content as EssayQuestion}
                    onChange={(content) =>
                      setFormData({
                        ...formData,
                        content,
                      })
                    }
                  />
                )}

                {formData.type === "assertion_reason" && (
                  <AssertionReasonForm
                    data={formData.content as AssertionReasonQuestion}
                    onChange={(content) =>
                      setFormData({
                        ...formData,
                        content,
                      })
                    }
                  />
                )}
              </CardContent>
            </Card>
          </TabsContent>

          <TabsContent value="preview">
            <QuestionPreview
              statement={formData.statement}
              discipline={formData.discipline}
              subject={formData.subject}
              difficulty={formData.difficulty}
              content={formData.content}
            />
          </TabsContent>

          <TabsContent value="history">
            <Card>
              <CardHeader>
                <CardTitle className="flex items-center gap-2">
                  <History className="w-5 h-5" /> Histórico de Versões
                </CardTitle>
                <CardDescription>
                  Cada vez que você salva alterações, um snapshot é criado automaticamente.
                  Você pode restaurar qualquer versão anterior.
                </CardDescription>
              </CardHeader>
              <CardContent>
                {getVersionHistory.isLoading ? (
                  <div className="flex justify-center py-6">
                    <Loader2 className="animate-spin w-6 h-6" />
                  </div>
                ) : !getVersionHistory.data || getVersionHistory.data.length === 0 ? (
                  <div className="text-center py-8 text-gray-500">
                    <History className="w-10 h-10 mx-auto mb-2 opacity-30" />
                    <p>Nenhuma versão salva ainda.</p>
                    <p className="text-sm mt-1">O histórico é criado automaticamente ao salvar alterações.</p>
                  </div>
                ) : (
                  <div className="space-y-3">
                    {getVersionHistory.data.map((v: any) => (
                      <div
                        key={v.id}
                        className="border rounded-lg overflow-hidden"
                      >
                        <div className="flex items-center gap-3 px-4 py-3 bg-gray-50">
                          <div className="flex-1 min-w-0">
                            <div className="flex items-center gap-2">
                              <Badge variant="outline" className="text-xs shrink-0">
                                v{v.versionNumber}
                              </Badge>
                              <span className="text-sm font-medium truncate">
                                {v.changeSummary || "Alteração sem descrição"}
                              </span>
                            </div>
                            <p className="text-xs text-gray-500 mt-0.5">
                              {new Date(v.createdAt).toLocaleString("pt-BR")}
                            </p>
                          </div>
                          <div className="flex gap-2 shrink-0">
                            <Button
                              variant="ghost"
                              size="sm"
                              onClick={() => setExpandedVersion(expandedVersion === v.id ? null : v.id)}
                              className="text-xs gap-1"
                            >
                              {expandedVersion === v.id ? <ChevronUp className="w-3 h-3" /> : <ChevronDown className="w-3 h-3" />}
                              Detalhes
                            </Button>
                            <Button
                              variant="outline"
                              size="sm"
                              className="text-xs gap-1"
                              disabled={revertMutation.isPending}
                              onClick={async () => {
                                try {
                                  await revertMutation.mutateAsync({
                                    questionId,
                                    versionId: v.id,
                                  });
                                  toast.success(`Restaurado para versão ${v.versionNumber}`);
                                  getQuestion.refetch();
                                  getVersionHistory.refetch();
                                  setActiveTab("form");
                                } catch {
                                  toast.error("Erro ao restaurar versão");
                                }
                              }}
                            >
                              <RotateCcw className="w-3 h-3" />
                              Restaurar
                            </Button>
                          </div>
                        </div>

                        {expandedVersion === v.id && v.snapshot && (
                          <div className="px-4 py-3 bg-white border-t space-y-2 text-sm">
                            <div className="grid grid-cols-2 gap-x-4 gap-y-1">
                              <div>
                                <span className="text-gray-500">Disciplina:</span>{" "}
                                <span className="font-medium">{v.snapshot.discipline || "—"}</span>
                              </div>
                              <div>
                                <span className="text-gray-500">Assunto:</span>{" "}
                                <span className="font-medium">{v.snapshot.subject || "—"}</span>
                              </div>
                              <div>
                                <span className="text-gray-500">Dificuldade:</span>{" "}
                                <span className="font-medium">
                                  {{ easy: "Fácil", medium: "Médio", hard: "Difícil" }[v.snapshot.difficulty as string] || v.snapshot.difficulty || "—"}
                                </span>
                              </div>
                              <div>
                                <span className="text-gray-500">Tags:</span>{" "}
                                <span className="font-medium">
                                  {v.snapshot.tags
                                    ? v.snapshot.tags.split(",").filter(Boolean).join(", ") || "—"
                                    : "—"}
                                </span>
                              </div>
                            </div>
                            <div>
                              <p className="text-gray-500 mb-1">Enunciado:</p>
                              <p className="bg-gray-50 rounded p-2 text-xs line-clamp-3">
                                {v.snapshot.statement}
                              </p>
                            </div>
                          </div>
                        )}
                      </div>
                    ))}
                  </div>
                )}
              </CardContent>
            </Card>
          </TabsContent>

          <TabsContent value="images">
            <Card>
              <CardHeader>
                <CardTitle>Gerenciar Imagens</CardTitle>
                <CardDescription>
                  Adicione novas imagens ou remova as existentes
                </CardDescription>
              </CardHeader>
              <CardContent className="space-y-6">
                {getQuestion.data?.images && getQuestion.data.images.length > 0 && (
                  <div>
                    <h4 className="font-semibold mb-3">Imagens Atuais</h4>
                    <div className="grid grid-cols-2 gap-4">
                      {getQuestion.data.images.map((image: any) => (
                        <div key={image.storageKey} className="relative">
                          <img
                            src={image.url}
                            alt={image.originalFileName}
                            className="w-full h-24 object-cover rounded border"
                          />
                          <Button
                            variant="destructive"
                            size="sm"
                            className="absolute top-1 right-1"
                            onClick={() =>
                              setRemoveImageKeys([
                                ...removeImageKeys,
                                image.storageKey,
                              ])
                            }
                          >
                            <Trash2 className="w-3 h-3" />
                          </Button>
                        </div>
                      ))}
                    </div>
                  </div>
                )}

                {quota && (
                  <ImageUploader
                    onImagesSelected={(files, base64Array) => {
                      setFormData({
                        ...formData,
                        imageBase64Array: base64Array,
                      });
                      toast.success(
                        `${files.length} imagem(ns) selecionada(s)`
                      );
                    }}
                    usedStorageMB={quota.usedStorageMB}
                    quotaLimitMB={quota.quotaLimitMB}
                  />
                )}
              </CardContent>
            </Card>
          </TabsContent>
        </Tabs>

        <div className="flex justify-between mt-8 gap-4">
          <Button
            variant="destructive"
            onClick={() => setShowDeleteDialog(true)}
          >
            <Trash2 className="w-4 h-4 mr-2" /> Deletar Questão
          </Button>

          <div className="flex gap-4">
            <Button
              variant="outline"
              onClick={() => navigate("/question-bank")}
            >
              Cancelar
            </Button>

            <Button
              onClick={handleSave}
              disabled={loading}
              className="gap-2"
            >
              {loading ? (
                <>
                  <Loader2 className="w-4 h-4 animate-spin" /> Salvando...
                </>
              ) : (
                <>
                  <Save className="w-4 h-4" /> Salvar Alterações
                </>
              )}
            </Button>
          </div>
        </div>
      </div>

      <AlertDialog open={showDeleteDialog} onOpenChange={setShowDeleteDialog}>
        <AlertDialogContent>
          <AlertDialogTitle>Confirmar Exclusão</AlertDialogTitle>
          <AlertDialogDescription>
            Tem certeza que deseja deletar esta questão? Esta ação não pode ser
            desfeita.
          </AlertDialogDescription>
          <div className="flex gap-3 justify-end mt-4">
            <AlertDialogCancel>Cancelar</AlertDialogCancel>
            <AlertDialogAction
              onClick={handleDelete}
              className="bg-destructive hover:bg-destructive/90"
            >
              Deletar
            </AlertDialogAction>
          </div>
        </AlertDialogContent>
      </AlertDialog>
    </div>
  );
}
