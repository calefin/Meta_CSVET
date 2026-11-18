import { useState } from "react";
import { useAuth } from "@/_core/hooks/useAuth";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Progress } from "@/components/ui/progress";
import { Loader2, Upload, CheckCircle, AlertCircle, Edit2, Trash2, Save, X, Download, FileSpreadsheet, FileText, Code } from "lucide-react";
import { trpc } from "@/lib/trpc";
import { toast } from "sonner";
import { useLocation } from "wouter";
import { getLoginUrl } from "@/const";

interface ImportedQuestion {
  type: "multiple_choice" | "essay";
  statement: string;
  alternatives?: string[];
  correctAnswer?: string;
  points?: number;
  sourceFormat?: string;
}

interface FilePreview {
  name: string;
  size: number;
  type: string;
  questionsCount?: number;
  status: "pending" | "processing" | "success" | "error";
  error?: string;
}

export default function ImportQuestions() {
  const { user, loading: authLoading, isAuthenticated } = useAuth();
  const [, navigate] = useLocation();
  const [files, setFiles] = useState<File[]>([]);
  const [filePreviews, setFilePreviews] = useState<FilePreview[]>([]);
  const [isProcessing, setIsProcessing] = useState(false);
  const [importedQuestions, setImportedQuestions] = useState<ImportedQuestion[]>([]);
  const [editingIndex, setEditingIndex] = useState<number | null>(null);
  const [editingQuestion, setEditingQuestion] = useState<ImportedQuestion | null>(null);
  const [overallProgress, setOverallProgress] = useState(0);

  // eslint-disable-next-line @typescript-eslint/no-explicit-any
  const importMutation = (trpc.questions as any).import?.useMutation?.() ?? { mutateAsync: async () => {} };
  const createMutation = trpc.questions.create.useMutation();
  const exportMutation = trpc.questions.export.useMutation();

  if (authLoading) {
    return (
      <div className="flex items-center justify-center min-h-screen">
        <Loader2 className="animate-spin w-8 h-8 text-blue-600" />
      </div>
    );
  }

  if (!isAuthenticated) {
    return (
      <div className="flex items-center justify-center min-h-screen bg-gray-50">
        <Card className="w-full max-w-md">
          <CardHeader>
            <CardTitle>Autenticação Necessária</CardTitle>
            <CardDescription>
              Você precisa estar autenticado para importar questões
            </CardDescription>
          </CardHeader>
          <CardContent className="space-y-4">
            <p className="text-sm text-gray-600">
              Faça login para acessar o sistema de importação de questões.
            </p>
            <Button 
              onClick={() => {
                navigate(getLoginUrl());
              }}
              className="w-full"
            >
              Fazer Login
            </Button>
          </CardContent>
        </Card>
      </div>
    );
  }

  const handleFileSelect = (e: React.ChangeEvent<HTMLInputElement>) => {
    const selectedFiles = Array.from(e.target.files || []);
    setFiles(prev => [...prev, ...selectedFiles]);
    
    // Criar previews
    const newPreviews = selectedFiles.map(file => ({
      name: file.name,
      size: file.size,
      type: file.type,
      status: "pending" as const,
    }));
    setFilePreviews(prev => [...prev, ...newPreviews]);
  };

  const removeFile = (index: number) => {
    setFiles(prev => prev.filter((_, i) => i !== index));
    setFilePreviews(prev => prev.filter((_, i) => i !== index));
  };

  const handleImportAll = async () => {
    if (files.length === 0) {
      toast.error("Selecione pelo menos um arquivo");
      return;
    }

    if (!isAuthenticated) {
      toast.error("Sessão expirada. Faça login novamente.");
      navigate(getLoginUrl());
      return;
    }

    setIsProcessing(true);
    const allQuestions: ImportedQuestion[] = [];
    
    try {
      for (let i = 0; i < files.length; i++) {
        const file = files[i];
        const previewIndex = i;
        
        // Atualizar preview
        setFilePreviews(prev => {
          const updated = [...prev];
          updated[previewIndex] = { ...updated[previewIndex], status: "processing" };
          return updated;
        });

        // Atualizar progresso geral
        setOverallProgress(Math.round((i / files.length) * 100));

        try {
          const reader = new FileReader();
          
          const result = await new Promise<ImportedQuestion[]>((resolve, reject) => {
            reader.onload = async (e) => {
              try {
                const fileData = e.target?.result as string;
                const base64Data = fileData.split(",")[1] || fileData;

                const ext = file.name.split(".").pop()?.toLowerCase() || "";
                const validTypes = ["xlsx", "docx", "pdf", "json"];
                
                if (!validTypes.includes(ext)) {
                  throw new Error("Formato não suportado");
                }

                const result = await importMutation.mutateAsync({
                  fileName: file.name,
                  fileData: base64Data,
                  fileType: ext as "xlsx" | "docx" | "pdf" | "json",
                });

                if (result.success) {
                  resolve(result.questions);
                } else {
                  throw new Error(result.error || "Erro ao importar");
                }
              } catch (error: any) {
                reject(error);
              }
            };
            reader.onerror = () => reject(new Error("Erro ao ler arquivo"));
            reader.readAsDataURL(file);
          });

          allQuestions.push(...result);

          // Atualizar preview com sucesso
          setFilePreviews(prev => {
            const updated = [...prev];
            updated[previewIndex] = { 
              ...updated[previewIndex], 
              status: "success",
              questionsCount: result.length
            };
            return updated;
          });

          toast.success(`${result.length} questões importadas de ${file.name}`);
        } catch (error: any) {
          console.error(`Erro ao importar ${file.name}:`, error);
          
          setFilePreviews(prev => {
            const updated = [...prev];
            updated[previewIndex] = { 
              ...updated[previewIndex], 
              status: "error",
              error: error.message || "Erro desconhecido"
            };
            return updated;
          });

          if (error?.data?.code === "UNAUTHORIZED" || error?.message?.includes("login")) {
            toast.error("Sessão expirada. Faça login novamente.");
            navigate(getLoginUrl());
            return;
          } else {
            toast.error(`Erro ao importar ${file.name}: ${error.message}`);
          }
        }
      }

      setImportedQuestions(allQuestions);
      setOverallProgress(100);
      toast.success(`Total: ${allQuestions.length} questões importadas com sucesso`);
      setFiles([]);
      setFilePreviews([]);
    } finally {
      setIsProcessing(false);
      setOverallProgress(0);
    }
  };

  const handleEditQuestion = (index: number) => {
    setEditingIndex(index);
    setEditingQuestion({ ...importedQuestions[index] });
  };

  const handleSaveEdit = () => {
    if (editingIndex !== null && editingQuestion) {
      const updated = [...importedQuestions];
      updated[editingIndex] = editingQuestion;
      setImportedQuestions(updated);
      setEditingIndex(null);
      setEditingQuestion(null);
      toast.success("Questão atualizada");
    }
  };

  const handleDeleteQuestion = (index: number) => {
    const updated = importedQuestions.filter((_, i) => i !== index);
    setImportedQuestions(updated);
    toast.success("Questão removida");
  };

  const handleSaveAllQuestions = async () => {
    if (importedQuestions.length === 0) {
      toast.error("Nenhuma questão para salvar");
      return;
    }

    setIsProcessing(true);
    try {
      let saved = 0;
      for (let i = 0; i < importedQuestions.length; i++) {
        const question = importedQuestions[i];
        setOverallProgress(Math.round((i / importedQuestions.length) * 100));
        
        try {
          await createMutation.mutateAsync({
            statement: question.statement,
            discipline: "Importado",
            subject: "Importado",
            content: {
              type: question.type as "multiple_choice",
              alternatives: question.alternatives
                ? { a: question.alternatives[0] ?? "", b: question.alternatives[1] ?? "", c: question.alternatives[2] ?? "", d: question.alternatives[3] ?? "" }
                : { a: "", b: "", c: "", d: "" },
              correctAnswer: (question.correctAnswer ?? "a") as "a" | "b" | "c" | "d",
            },
          } as any);
          saved++;
        } catch (error) {
          console.error("Erro ao salvar questão:", error);
        }
      }
      
      toast.success(`${saved} questões salvas no banco`);
      setImportedQuestions([]);
      setOverallProgress(0);
    } catch (error) {
      toast.error("Erro ao salvar questões");
      console.error(error);
    } finally {
      setIsProcessing(false);
    }
  };

  const handleExportQuestions = async () => {
    if (importedQuestions.length === 0) {
      toast.error("Nenhuma questão para exportar");
      return;
    }

    try {
      setIsProcessing(true);
      const result = await exportMutation.mutateAsync({
        format: "xlsx",
        questionIds: undefined,
      });

      // Criar download
      const link = document.createElement("a");
      const blob = new Blob([Buffer.from(result.data, "base64")], { type: "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" });
      link.href = URL.createObjectURL(blob);
      link.download = result.fileName || `questoes_${new Date().toISOString().split("T")[0]}.xlsx`;
      document.body.appendChild(link);
      link.click();
      document.body.removeChild(link);

      toast.success("Questões exportadas com sucesso");
    } catch (error) {
      toast.error("Erro ao exportar questões");
      console.error(error);
    } finally {
      setIsProcessing(false);
    }
  };

  return (
    <div className="min-h-screen bg-gray-50 p-8">
      <div className="max-w-6xl mx-auto space-y-8">
        {/* Header */}
        <div>
          <h1 className="text-4xl font-bold text-gray-900">Importar Questões</h1>
          <p className="text-gray-600 mt-2">
            Importe questões de múltiplos arquivos DOCX, XLSX, PDF ou JSON simultaneamente
          </p>
        </div>

        <div className="grid grid-cols-1 lg:grid-cols-3 gap-8">
          {/* Left Column - Upload */}
          <div className="lg:col-span-1 space-y-4">
            {/* Upload Section */}
            <Card>
              <CardHeader>
                <CardTitle className="flex items-center gap-2">
                  <Upload className="w-5 h-5" />
                  Selecionar Arquivos
                </CardTitle>
                <CardDescription>
                  Múltiplos arquivos suportados
                </CardDescription>
              </CardHeader>
              <CardContent className="space-y-4">
                <div className="space-y-2">
                  <Label htmlFor="files">Arquivos</Label>
                  <Input
                    id="files"
                    type="file"
                    multiple
                    accept=".docx,.xlsx,.pdf,.json"
                    onChange={handleFileSelect}
                    disabled={isProcessing}
                  />
                </div>

                {files.length > 0 && (
                  <div className="space-y-2">
                    <p className="text-sm font-semibold text-gray-700">
                      {files.length} arquivo(s) selecionado(s)
                    </p>
                  </div>
                )}

                <Button
                  onClick={handleImportAll}
                  disabled={files.length === 0 || isProcessing}
                  className="w-full"
                >
                  {isProcessing ? (
                    <>
                      <Loader2 className="w-4 h-4 mr-2 animate-spin" />
                      Importando...
                    </>
                  ) : (
                    <>
                      <Upload className="w-4 h-4 mr-2" />
                      Importar Tudo
                    </>
                  )}
                </Button>

                {isProcessing && (
                  <div className="space-y-2">
                    <div className="flex items-center justify-between text-xs">
                      <span>Progresso</span>
                      <span>{overallProgress}%</span>
                    </div>
                    <Progress value={overallProgress} className="h-2" />
                  </div>
                )}
              </CardContent>
            </Card>

            {/* Template Downloads */}
            <Card className="border-0 shadow-sm bg-gradient-to-br from-blue-50 to-indigo-50 border border-blue-100">
              <CardHeader className="pb-2">
                <CardTitle className="text-sm font-semibold flex items-center gap-2 text-indigo-900">
                  <Download className="w-4 h-4 text-indigo-600" />
                  Modelos de Importação
                </CardTitle>
                <CardDescription className="text-xs text-indigo-700/80">
                  Baixe nossos arquivos de exemplo para estruturar suas questões corretamente.
                </CardDescription>
              </CardHeader>
              <CardContent className="space-y-2 pt-0">
                <div className="flex flex-col gap-2">
                  <a
                    href="/templates/questoes-modelo.xlsx"
                    download
                    className="flex items-center gap-2 p-2 rounded-md bg-white border border-indigo-100 text-xs font-medium text-indigo-950 hover:bg-indigo-50/50 hover:border-indigo-200 transition-all"
                  >
                    <FileSpreadsheet className="w-4 h-4 text-emerald-600 shrink-0" />
                    <span>Planilha Excel (.xlsx)</span>
                  </a>
                  <a
                    href="/templates/questoes-modelo.docx"
                    download
                    className="flex items-center gap-2 p-2 rounded-md bg-white border border-indigo-100 text-xs font-medium text-indigo-950 hover:bg-indigo-50/50 hover:border-indigo-200 transition-all"
                  >
                    <FileText className="w-4 h-4 text-blue-600 shrink-0" />
                    <span>Documento Word (.docx)</span>
                  </a>
                  <a
                    href="/templates/questoes-modelo.json"
                    download
                    className="flex items-center gap-2 p-2 rounded-md bg-white border border-indigo-100 text-xs font-medium text-indigo-950 hover:bg-indigo-50/50 hover:border-indigo-200 transition-all"
                  >
                    <Code className="w-4 h-4 text-amber-600 shrink-0" />
                    <span>Arquivo JSON (.json)</span>
                  </a>
                </div>
              </CardContent>
            </Card>

            {/* File Previews */}
            {filePreviews.length > 0 && (
              <Card>
                <CardHeader>
                  <CardTitle className="text-base">Arquivos</CardTitle>
                </CardHeader>
                <CardContent className="space-y-2">
                  {filePreviews.map((preview, index) => (
                    <div key={index} className="flex items-start justify-between p-2 bg-gray-50 rounded border border-gray-200">
                      <div className="flex-1 min-w-0">
                        <p className="text-sm font-medium text-gray-900 truncate">
                          {preview.name}
                        </p>
                        <p className="text-xs text-gray-500">
                          {(preview.size / 1024).toFixed(2)} KB
                        </p>
                        {preview.status === "success" && preview.questionsCount && (
                          <p className="text-xs text-green-600">
                            ✓ {preview.questionsCount} questões
                          </p>
                        )}
                        {preview.status === "error" && (
                          <p className="text-xs text-red-600">
                            ✗ {preview.error}
                          </p>
                        )}
                      </div>
                      {preview.status === "pending" && (
                        <Button
                          size="sm"
                          variant="ghost"
                          onClick={() => removeFile(index)}
                        >
                          <X className="w-4 h-4" />
                        </Button>
                      )}
                      {preview.status === "processing" && (
                        <Loader2 className="w-4 h-4 animate-spin text-blue-600" />
                      )}
                      {preview.status === "success" && (
                        <CheckCircle className="w-4 h-4 text-green-600" />
                      )}
                      {preview.status === "error" && (
                        <AlertCircle className="w-4 h-4 text-red-600" />
                      )}
                    </div>
                  ))}
                </CardContent>
              </Card>
            )}
          </div>

          {/* Right Column - Questions */}
          <div className="lg:col-span-2 space-y-4">
            {importedQuestions.length > 0 && (
              <>
                <div className="flex items-center justify-between">
                  <h2 className="text-2xl font-bold text-gray-900">
                    Questões Importadas ({importedQuestions.length})
                  </h2>
                  <div className="flex gap-2">
                    <Button
                      onClick={handleExportQuestions}
                      disabled={isProcessing}
                      variant="outline"
                    >
                      <Download className="w-4 h-4 mr-2" />
                      Exportar XLSX
                    </Button>
                    <Button
                      onClick={handleSaveAllQuestions}
                      disabled={isProcessing}
                      className="bg-green-600 hover:bg-green-700"
                    >
                      {isProcessing ? (
                        <>
                          <Loader2 className="w-4 h-4 mr-2 animate-spin" />
                          Salvando...
                        </>
                      ) : (
                        <>
                          <CheckCircle className="w-4 h-4 mr-2" />
                          Salvar Tudo
                        </>
                      )}
                    </Button>
                  </div>
                </div>

                {isProcessing && (
                  <div className="space-y-2">
                    <div className="flex items-center justify-between text-sm">
                      <span>Salvando questões</span>
                      <span>{overallProgress}%</span>
                    </div>
                    <Progress value={overallProgress} className="h-2" />
                  </div>
                )}

                <div className="space-y-3">
                  {importedQuestions.map((question, index) => (
                    <Card key={index}>
                      <CardHeader>
                        <div className="flex items-start justify-between">
                          <div className="flex-1">
                            <div className="flex items-center gap-2 mb-2">
                              <span className="inline-block px-2 py-1 bg-blue-100 text-blue-800 text-xs font-semibold rounded">
                                {question.type === "multiple_choice" ? "Múltipla Escolha" : "Discursiva"}
                              </span>
                              {question.points && (
                                <span className="text-sm text-gray-600">
                                  {question.points} pt
                                </span>
                              )}
                            </div>
                            <CardTitle className="text-base line-clamp-2">{question.statement}</CardTitle>
                          </div>
                          <div className="flex gap-2">
                            <Button
                              size="sm"
                              variant="outline"
                              onClick={() => handleEditQuestion(index)}
                            >
                              <Edit2 className="w-4 h-4" />
                            </Button>
                            <Button
                              size="sm"
                              variant="outline"
                              onClick={() => handleDeleteQuestion(index)}
                            >
                              <Trash2 className="w-4 h-4" />
                            </Button>
                          </div>
                        </div>
                      </CardHeader>
                      {question.alternatives && question.alternatives.length > 0 && (
                        <CardContent>
                          <div className="space-y-2">
                            <p className="font-semibold text-sm">Alternativas:</p>
                            <ul className="space-y-1">
                              {question.alternatives.map((alt, i) => (
                                <li key={i} className="text-sm text-gray-700">
                                  {String.fromCharCode(65 + i)}) {alt}
                                </li>
                              ))}
                            </ul>
                          </div>
                        </CardContent>
                      )}
                    </Card>
                  ))}
                </div>
              </>
            )}

            {importedQuestions.length === 0 && files.length === 0 && (
              <Card className="border-dashed">
                <CardContent className="flex flex-col items-center justify-center py-12">
                  <Upload className="w-12 h-12 text-gray-300 mb-4" />
                  <p className="text-gray-500 text-center">
                    Selecione arquivos para começar a importar questões
                  </p>
                </CardContent>
              </Card>
            )}
          </div>
        </div>

        {/* Edit Modal */}
        {editingIndex !== null && editingQuestion && (
          <Card className="border-2 border-blue-500 fixed bottom-8 right-8 w-96 shadow-lg">
            <CardHeader>
              <CardTitle>Editar Questão #{editingIndex + 1}</CardTitle>
            </CardHeader>
            <CardContent className="space-y-4">
              <div className="space-y-2">
                <Label>Tipo</Label>
                <select
                  value={editingQuestion.type}
                  onChange={(e) =>
                    setEditingQuestion({
                      ...editingQuestion,
                      type: e.target.value as "multiple_choice" | "essay",
                    })
                  }
                  className="w-full px-3 py-2 border border-gray-300 rounded-md"
                >
                  <option value="essay">Discursiva</option>
                  <option value="multiple_choice">Múltipla Escolha</option>
                </select>
              </div>

              <div className="space-y-2">
                <Label>Enunciado</Label>
                <textarea
                  value={editingQuestion.statement}
                  onChange={(e) =>
                    setEditingQuestion({
                      ...editingQuestion,
                      statement: e.target.value,
                    })
                  }
                  rows={4}
                  className="w-full px-3 py-2 border border-gray-300 rounded-md"
                />
              </div>

              <div className="flex gap-2">
                <Button onClick={handleSaveEdit} className="flex-1">
                  <Save className="w-4 h-4 mr-2" />
                  Salvar
                </Button>
                <Button
                  onClick={() => {
                    setEditingIndex(null);
                    setEditingQuestion(null);
                  }}
                  variant="outline"
                  className="flex-1"
                >
                  <X className="w-4 h-4 mr-2" />
                  Cancelar
                </Button>
              </div>
            </CardContent>
          </Card>
        )}
      </div>
    </div>
  );
}
