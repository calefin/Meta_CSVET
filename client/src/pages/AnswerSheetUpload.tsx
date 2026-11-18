import { useState } from "react";
import { useAuth } from "@/_core/hooks/useAuth";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Loader2, Upload, CheckCircle, AlertCircle, ArrowLeft } from "lucide-react";
import { trpc } from "@/lib/trpc";
import { toast } from "sonner";
import { useLocation } from "wouter";

export default function AnswerSheetUpload() {
  const { user, loading: authLoading } = useAuth();
  const [, navigate] = useLocation();
  const [isUploading, setIsUploading] = useState(false);
  const [uploadedSheets, setUploadedSheets] = useState<any[]>([]);

  // Get examId from URL
  const examId = parseInt(window.location.pathname.split("/").pop() || "0");

  const { data: exam, isLoading: examLoading } = trpc.exams.get.useQuery(
    { id: examId },
    { enabled: !!examId }
  );

  const { data: sheets } = trpc.answerSheets.listByExam.useQuery(
    { examId },
    { enabled: !!examId }
  );

  const uploadMutation = trpc.answerSheets.upload.useMutation();

  if (authLoading || examLoading) {
    return (
      <div className="flex items-center justify-center min-h-screen">
        <Loader2 className="animate-spin w-8 h-8 text-blue-600" />
      </div>
    );
  }

  if (!user || !exam) {
    navigate("/exams");
    return null;
  }

  const handleFileUpload = async (event: React.ChangeEvent<HTMLInputElement>) => {
    const file = event.target.files?.[0];
    if (!file) return;

    setIsUploading(true);
    try {
      const reader = new FileReader();
      reader.onload = async (e) => {
        const base64 = (e.target?.result as string).split(",")[1];
        const result = await uploadMutation.mutateAsync({
          examId,
          imageData: base64,
        });

        toast.success("Gabarito processado com sucesso!");
        setUploadedSheets([...uploadedSheets, result]);

        // Reset input
        if (event.target) {
          event.target.value = "";
        }
      };
      reader.readAsDataURL(file);
    } catch (error) {
      toast.error("Erro ao processar gabarito");
      console.error(error);
    } finally {
      setIsUploading(false);
    }
  };

  return (
    <div className="min-h-screen bg-gradient-to-br from-slate-50 to-slate-100">
      {/* Main Content */}
      <div className="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
        <div className="mb-6">
          <Button variant="ghost" onClick={() => navigate("/exams")} className="text-slate-700 mb-4 px-0 hover:bg-transparent hover:text-slate-900">
            <ArrowLeft className="w-4 h-4 mr-2" />
            Voltar para Avaliações
          </Button>
          <h1 className="text-4xl font-bold text-slate-900 mb-2">Upload de Gabarito</h1>
          <p className="text-slate-600">Processe imagens de gabaritos ZipGrade para {exam.name}</p>
        </div>

        {/* Upload Area */}
        <Card className="border-0 shadow-lg mb-8">
          <CardHeader>
            <CardTitle>Enviar Gabarito</CardTitle>
            <CardDescription>
              Envie uma imagem do gabarito preenchido (JPEG, PNG)
            </CardDescription>
          </CardHeader>
          <CardContent>
            <label className="flex flex-col items-center justify-center w-full h-64 border-2 border-dashed border-slate-300 rounded-lg cursor-pointer hover:bg-slate-50 transition">
              <div className="flex flex-col items-center justify-center pt-5 pb-6">
                <Upload className="w-10 h-10 text-slate-400 mb-2" />
                <p className="mb-2 text-sm text-slate-500">
                  <span className="font-semibold">Clique para enviar</span> ou arraste uma imagem
                </p>
                <p className="text-xs text-slate-500">PNG, JPG até 10MB</p>
              </div>
              <input
                type="file"
                className="hidden"
                accept="image/*"
                onChange={handleFileUpload}
                disabled={isUploading}
              />
            </label>

            {isUploading && (
              <div className="mt-4 flex items-center justify-center">
                <Loader2 className="animate-spin w-5 h-5 text-blue-600 mr-2" />
                <span className="text-slate-600">Processando gabarito...</span>
              </div>
            )}
          </CardContent>
        </Card>

        {/* Processed Sheets */}
        {sheets && sheets.length > 0 && (
          <Card className="border-0 shadow-lg">
            <CardHeader>
              <CardTitle>Gabaritos Processados</CardTitle>
              <CardDescription>Lista de gabaritos já enviados</CardDescription>
            </CardHeader>
            <CardContent>
              <div className="space-y-3">
                {sheets.map((sheet: any) => (
                  <div
                    key={sheet.id}
                    className="flex items-center justify-between p-4 border border-slate-200 rounded-lg"
                  >
                    <div className="flex items-center gap-3">
                      {sheet.processingStatus === "completed" ? (
                        <CheckCircle className="w-5 h-5 text-green-600" />
                      ) : sheet.processingStatus === "failed" ? (
                        <AlertCircle className="w-5 h-5 text-red-600" />
                      ) : (
                        <Loader2 className="w-5 h-5 text-blue-600 animate-spin" />
                      )}
                      <div>
                        <p className="font-medium text-slate-900">
                          Gabarito #{sheet.id}
                        </p>
                        <p className="text-sm text-slate-600">
                          {new Date(sheet.createdAt).toLocaleDateString("pt-BR")}
                        </p>
                      </div>
                    </div>
                    <span className="text-xs px-2 py-1 bg-blue-100 text-blue-700 rounded">
                      {sheet.processingStatus === "completed"
                        ? "Processado"
                        : sheet.processingStatus === "failed"
                          ? "Erro"
                          : "Processando"}
                    </span>
                  </div>
                ))}
              </div>
            </CardContent>
          </Card>
        )}
      </div>
    </div>
  );
}
