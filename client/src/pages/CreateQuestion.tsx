import React, { useState, useEffect } from "react";
import { useAuth } from "@/_core/hooks/useAuth";
import { useLocation } from "wouter";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
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
import { Loader2, AlertTriangle, Eye, Save } from "lucide-react";
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

import { MultipleChoiceForm } from "../components/questions/MultipleChoiceForm";
import { EssayForm } from "../components/questions/EssayForm";
import { AssertionReasonForm } from "../components/questions/AssertionReasonForm";
import { QuestionPreview } from "../components/questions/QuestionPreview";
import { ImageUploader } from "../components/questions/ImageUploader";

export function CreateQuestion() {
  const { user, loading: authLoading } = useAuth();
  const [, navigate] = useLocation();
  const createMutation = trpc.questions.create.useMutation();
  const getStorageQuota = trpc.questions.getStorageQuota.useQuery();
  const getDisciplines = trpc.questions.getDisciplinesAndSubjects.useQuery();

  const [activeTab, setActiveTab] = useState("form");
  const [loading, setLoading] = useState(false);
  const [errors, setErrors] = useState<string[]>([]);

  const [formData, setFormData] = useState<QuestionFormState>({
    type: "multiple_choice",
    statement: "",
    discipline: "",
    subject: "",
    difficulty: "medium",
    tags: [],
    imageBase64Array: [],
    content: {
      type: "multiple_choice",
      alternatives: { a: "", b: "", c: "", d: "" },
      correctAnswer: "a",
    } as any,
  });

  const [customDiscipline, setCustomDiscipline] = useState("");
  const [customSubject, setCustomSubject] = useState("");

  const disciplines = (getDisciplines.data?.disciplines || []) as string[];
  const subjects = (getDisciplines.data?.subjects || []) as string[];
  const quota = getStorageQuota.data;

  if (authLoading) {
    return (
      <div className="flex items-center justify-center min-h-screen">
        <Loader2 className="animate-spin w-8 h-8" />
      </div>
    );
  }

  if (!user) {
    navigate("/");
    return null;
  }

  const handleQuestionTypeChange = (type: string) => {
    let newContent: any = {
      type,
    };

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

    // Validation
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
      const result = await createMutation.mutateAsync({
        statement: formData.statement,
        discipline: finalDiscipline,
        subject: finalSubject,
        difficulty: formData.difficulty as Difficulty,
        tags: formData.tags,
        content: formData.content,
        imageBase64Array: formData.imageBase64Array,
      });

      toast.success("Questão criada com sucesso!");
      navigate("/question-bank");
    } catch (error: any) {
      const errorMsg =
        error.message || "Erro ao criar questão";
      setErrors([errorMsg]);
      toast.error(errorMsg);
    } finally {
      setLoading(false);
    }
  };

  const templateSelected = QUESTION_TYPE_TEMPLATES.find(
    (t) => t.type === formData.type
  );

  return (
    <div className="min-h-screen bg-gray-50 py-8 px-4">
      <div className="max-w-6xl mx-auto">
        <div className="mb-6">
          <h1 className="text-3xl font-bold">Criar Nova Questão</h1>
          <p className="text-gray-600 mt-1">
            Preencha os dados da questão, pré-visualize e salve
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
          <TabsList className="grid w-full grid-cols-3">
            <TabsTrigger value="form">Formulário</TabsTrigger>
            <TabsTrigger value="preview" onClick={() => setErrors([])}>
              <Eye className="w-4 h-4 mr-2" /> Pré-visualização
            </TabsTrigger>
            <TabsTrigger value="images">Imagens</TabsTrigger>
          </TabsList>

          <TabsContent value="form" className="space-y-6">
            <Card>
              <CardHeader>
                <CardTitle>Informações Básicas</CardTitle>
              </CardHeader>
              <CardContent className="space-y-4">
                <div>
                  <Label htmlFor="statement">Enunciado/Pergunta *</Label>
                  <textarea
                    id="statement"
                    value={formData.statement}
                    onChange={(e) =>
                      setFormData({ ...formData, statement: e.target.value })
                    }
                    placeholder="Digite a pergunta ou enunciado da questão"
                    className="w-full min-h-24 p-3 border rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                  />
                </div>

                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                  <div>
                    <Label htmlFor="discipline">Disciplina *</Label>
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
                    <Label htmlFor="subject">Assunto *</Label>
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
                    <Label htmlFor="difficulty">Dificuldade</Label>
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
                    <Label htmlFor="type">Tipo de Questão *</Label>
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
              discipline={
                customDiscipline || formData.discipline === "custom"
                  ? undefined
                  : formData.discipline
              }
              subject={
                customSubject || formData.subject === "custom"
                  ? undefined
                  : formData.subject
              }
              difficulty={formData.difficulty}
              content={formData.content}
            />
          </TabsContent>

          <TabsContent value="images">
            <Card>
              <CardHeader>
                <CardTitle>Upload de Imagens</CardTitle>
                <CardDescription>
                  Adicione imagens para ilustrar a questão
                </CardDescription>
              </CardHeader>
              <CardContent>
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

        <div className="flex justify-between mt-8">
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
                <Save className="w-4 h-4" /> Salvar Questão
              </>
            )}
          </Button>
        </div>
      </div>
    </div>
  );
}
