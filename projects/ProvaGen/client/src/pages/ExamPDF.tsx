import { useState } from "react";
import { useAuth } from "@/_core/hooks/useAuth";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Loader2, Download, Eye, ArrowLeft, Printer, CheckSquare, FileText, Layers } from "lucide-react";
import { trpc } from "@/lib/trpc";
import { toast } from "sonner";
import { useLocation } from "wouter";
import { generateAnswerSheetSVG, AnswerSheetOverflowError } from "@/lib/answerSheetSvg";
import { printAnswerSheetPDF, downloadAnswerSheetPNG } from "@/lib/downloadAnswerSheet";
import buildQrPayload from "@/lib/buildQrPayload";
import { Label } from "@/components/ui/label";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";

export default function ExamPDF() {
  const { user, loading: authLoading } = useAuth();
  const [, navigate] = useLocation();
  const [isGenerating, setIsGenerating] = useState(false);
  const [generatedPdfs, setGeneratedPdfs] = useState<any[]>([]);
  const [pdfLayout, setPdfLayout] = useState<"merged" | "separated" | "questions_only" | "answer_sheet_only">("merged");

  // Get examId from URL
  const examId = parseInt(window.location.pathname.split("/").pop() || "0");

  const { data: exam, isLoading: examLoading } = trpc.exams.get.useQuery(
    { id: examId },
    { enabled: !!examId }
  );

  const { data: examQuestions = [] } = trpc.exams.getQuestions.useQuery(
    { examId },
    { enabled: !!examId }
  );

  const getDynamicAnswerSheetConfig = () => {
    if (!exam || !examQuestions) return null;

    const questionsList: any[] = [];
    let currentBlock: any = null;

    examQuestions.forEach((eq: any, index: number) => {
      const q = eq.question;
      if (!q) return;

      const qType = (q.type === "multiple_choice" || q.type === "multiple_choice_complex") ? "multiple_choice" : "short_response";
      let choices: string[] = ["A", "B", "C", "D", "E"];
      if ((q.type === "multiple_choice" || q.type === "multiple_choice_complex") && q.alternatives) {
        try {
          const alts = JSON.parse(q.alternatives);
          if (Array.isArray(alts) && alts.length > 0) {
            choices = Array.from({ length: alts.length }, (_, i) => String.fromCharCode(65 + i));
          }
        } catch (e) {}
      }

      if (currentBlock && currentBlock.type === qType && JSON.stringify(currentBlock.choices) === JSON.stringify(choices)) {
        currentBlock.count += 1;
      } else {
        if (currentBlock) {
          questionsList.push(currentBlock);
        }
        currentBlock = {
          id: `block-${index}`,
          type: qType,
          count: 1,
          startNumber: index + 1,
          choices: choices,
        };
      }
    });

    if (currentBlock) {
      questionsList.push(currentBlock);
    }

    return {
      header: {
        fields: [
          { id: "name",  defaultLabel: "Name",  displayLabel: "Nome",  enabled: true,  width: "large" as const },
          { id: "class", defaultLabel: "Class", displayLabel: "Turma", enabled: true,  width: "medium" as const },
          { id: "date",  defaultLabel: "Date",  displayLabel: "Data",  enabled: true,  width: "medium" as const },
        ],
        includeStudentId: !!exam.classId,
        studentIdDigits: 5,
        studentIdLabel: "Matrícula",
        includeKeyVersion: exam.numberOfVersions > 1,
        keyVersionLetters: Array.from({ length: exam.numberOfVersions }, (_, i) => String.fromCharCode(65 + i)).join(""),
        keyVersionLabel: "Versão",
      },
      questions: questionsList,
    };
  };

  const handlePrintAnswerSheet = () => {
    if (!exam || examQuestions.length === 0) {
      toast.error("Nenhuma questão carregada para gerar a folha de respostas.");
      return;
    }

    const config = getDynamicAnswerSheetConfig();
    if (!config) return;

    const qrPayload = buildQrPayload({ templateId: String(exam.id) });
    try {
      const svg = generateAnswerSheetSVG(exam.name, config, { standalone: true, formId: String(exam.id), qrPayload });
      printAnswerSheetPDF([svg], `${exam.name} — Gabarito`);
    } catch (e) {
      toast.error(e instanceof AnswerSheetOverflowError ? e.message : "Erro ao gerar o gabarito.");
    }
  };

  const handleDownloadAnswerSheetPNG = () => {
    if (!exam || examQuestions.length === 0) {
      toast.error("Nenhuma questão carregada para gerar a folha de respostas.");
      return;
    }

    const config = getDynamicAnswerSheetConfig();
    if (!config) return;

    const qrPayload = buildQrPayload({ templateId: String(exam.id) });
    try {
      const svg = generateAnswerSheetSVG(exam.name, config, { standalone: true, formId: String(exam.id), qrPayload });
      downloadAnswerSheetPNG(svg, `${exam.name}_Gabarito`.replace(/\s+/g, "_"));
    } catch (e) {
      toast.error(e instanceof AnswerSheetOverflowError ? e.message : "Erro ao gerar o gabarito.");
    }
  };

  const generateMutation = trpc.pdf.generate.useMutation();
  const previewMutation = trpc.pdf.preview.useQuery(
    { examId, version: 1 },
    { enabled: !!examId }
  );

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

  const handleGenerate = async () => {
    setIsGenerating(true);
    try {
      const result = await generateMutation.mutateAsync({ examId, pdfLayout });
      setGeneratedPdfs(result.pdfs);
      toast.success(`${result.pdfs.length} versão(ões) gerada(s) com sucesso!`);
    } catch (error) {
      toast.error(error instanceof Error && error.message ? error.message : "Erro ao gerar PDFs");
      console.error(error);
    } finally {
      setIsGenerating(false);
    }
  };

  const handleDownload = (url: string, filename: string) => {
    const link = document.createElement("a");
    link.href = url;
    link.download = filename;
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
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
          <h1 className="text-4xl font-bold text-slate-900 mb-2">{exam.name}</h1>
          <p className="text-slate-600">Gerar e baixar PDFs da prova</p>
        </div>

        {/* Exam Info */}
        <Card className="border-0 shadow-lg mb-8">
          <CardHeader>
            <CardTitle>Informações da Prova</CardTitle>
          </CardHeader>
          <CardContent>
            <div className="grid md:grid-cols-2 gap-4">
              <div>
                <p className="text-sm text-slate-600">Curso</p>
                <p className="font-medium text-slate-900">{exam.course}</p>
              </div>
              <div>
                <p className="text-sm text-slate-600">Disciplina</p>
                <p className="font-medium text-slate-900">{exam.discipline}</p>
              </div>
              <div>
                <p className="text-sm text-slate-600">Professor</p>
                <p className="font-medium text-slate-900">{exam.professor}</p>
              </div>
              <div>
                <p className="text-sm text-slate-600">Versões</p>
                <p className="font-medium text-slate-900">{exam.numberOfVersions}</p>
              </div>
            </div>
            {exam.instructions && (
              <div className="mt-4 pt-4 border-t border-slate-100">
                <p className="text-sm text-slate-600 font-semibold mb-1">Instruções da Avaliação</p>
                <p className="text-slate-700 whitespace-pre-line text-xs bg-slate-50 p-3 rounded-lg border border-slate-200 font-mono">
                  {exam.instructions}
                </p>
              </div>
            )}
          </CardContent>
        </Card>

        {/* Generate Button */}
        <Card className="border-0 shadow-lg mb-8">
          <CardHeader>
            <CardTitle className="flex items-center gap-2">
              <Layers className="w-5 h-5 text-blue-600" />
              Gerar PDFs da Prova
            </CardTitle>
            <CardDescription>
              Escolha o formato de layout e gere as {exam.numberOfVersions} versão(ões) da avaliação.
            </CardDescription>
          </CardHeader>
          <CardContent className="space-y-4">
            <div className="space-y-2">
              <Label className="text-slate-700 font-medium">Layout do Arquivo PDF</Label>
              <Select
                value={pdfLayout}
                onValueChange={(val: any) => setPdfLayout(val)}
              >
                <SelectTrigger className="w-full">
                  <SelectValue placeholder="Selecione o formato" />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="merged">Completo (Folha de Respostas + Questões no mesmo arquivo)</SelectItem>
                  <SelectItem value="separated">Arquivos Separados (Recomendado - Questões em um PDF, Gabarito/Instruções em outro)</SelectItem>
                  <SelectItem value="questions_only">Apenas Questões (Sem folha de respostas)</SelectItem>
                  <SelectItem value="answer_sheet_only">Apenas Folha de Respostas e Instruções</SelectItem>
                </SelectContent>
              </Select>
              <p className="text-xs text-slate-500 mt-1">
                {pdfLayout === "merged" && "Gera um único PDF contendo a folha de respostas na página 1 e as questões a partir da página 2."}
                {pdfLayout === "separated" && "Gera dois PDFs por versão: um compacto contendo apenas as questões da avaliação, e outro de página única contendo a Folha de Respostas com as Instruções no verso."}
                {pdfLayout === "questions_only" && "Gera um PDF contendo apenas as questões da prova, iniciando diretamente na primeira página. Útil para provas tradicionais ou sem correção automática."}
                {pdfLayout === "answer_sheet_only" && "Gera um PDF contendo apenas a folha de respostas óptica para preenchimento com as instruções do exame no verso."}
              </p>
            </div>

            <Button
              onClick={handleGenerate}
              disabled={isGenerating}
              className="bg-blue-600 hover:bg-blue-700 text-white w-full"
              size="lg"
            >
              {isGenerating ? (
                <>
                  <Loader2 className="animate-spin w-4 h-4 mr-2" />
                  Gerando...
                </>
              ) : (
                "Gerar PDFs"
              )}
            </Button>
          </CardContent>
        </Card>

        {/* Folha de Respostas (Gabarito) */}
        <Card className="border-0 shadow-lg mb-8">
          <CardHeader>
            <CardTitle className="flex items-center gap-2">
              <CheckSquare className="w-5 h-5 text-indigo-600" />
              Folha de Respostas (Gabarito de Correção)
            </CardTitle>
            <CardDescription>
              Imprima ou baixe a folha de respostas otimizada para correção automática via câmera (compatível com ZipGrade e Gradepen).
            </CardDescription>
          </CardHeader>
          <CardContent className="space-y-4">
            <div className="p-4 bg-slate-50 border border-slate-200 rounded-lg text-sm text-slate-700 space-y-2">
              <p>
                <strong>Características desta folha:</strong>
              </p>
              <ul className="list-disc list-inside space-y-1 text-xs text-slate-600">
                <li>Ajustada para <strong>{examQuestions.length}</strong> questão(ões)</li>
                {exam.numberOfVersions > 1 && (
                  <li>Contém o campo para preenchimento de versão (A, B, etc.)</li>
                )}
                {exam.classId ? (
                  <li>Contém campo de preenchimento para Matrícula de Aluno</li>
                ) : (
                  <li>Sem campo de Matrícula (apenas Nome)</li>
                )}
                <li>Contém marcadores de canto ArUco para leitura ótica</li>
                <li>Inclui QR Code contendo as informações da prova e do aluno</li>
              </ul>
            </div>
            <div className="flex flex-wrap gap-3">
              <Button
                onClick={handlePrintAnswerSheet}
                className="bg-indigo-600 hover:bg-indigo-700 text-white flex-1 min-w-[150px]"
              >
                <Printer className="w-4 h-4 mr-2" />
                Imprimir / Salvar PDF
              </Button>
              <Button
                onClick={handleDownloadAnswerSheetPNG}
                variant="outline"
                className="border-indigo-200 text-indigo-700 hover:bg-indigo-50 flex-1 min-w-[150px]"
              >
                <Download className="w-4 h-4 mr-2" />
                Baixar Imagem PNG
              </Button>
            </div>
          </CardContent>
        </Card>

        {/* Generated PDFs */}
        {generatedPdfs.length > 0 && (
          <Card className="border-0 shadow-lg">
            <CardHeader>
              <CardTitle>PDFs Gerados</CardTitle>
              <CardDescription>Baixe as versões da sua prova</CardDescription>
            </CardHeader>
            <CardContent>
              <div className="space-y-3">
                {generatedPdfs.map((pdf) => (
                  <div
                    key={`${pdf.version}-${pdf.filename}`}
                    className="flex items-center justify-between p-4 border border-slate-200 rounded-lg hover:border-slate-300 transition"
                  >
                    <div>
                      <p className="font-medium text-slate-900">Versão {pdf.version}</p>
                      <p className="text-sm text-slate-600">{pdf.filename}</p>
                    </div>
                    <Button
                      onClick={() => handleDownload(pdf.url, pdf.filename)}
                      className="bg-green-600 hover:bg-green-700 text-white"
                    >
                      <Download className="w-4 h-4 mr-2" />
                      Baixar
                    </Button>
                  </div>
                ))}
              </div>
            </CardContent>
          </Card>
        )}

        {/* Preview */}
        {previewMutation.data?.pdf && (
          <Card className="border-0 shadow-lg mt-8">
            <CardHeader>
              <CardTitle>Preview</CardTitle>
              <CardDescription>Visualização da primeira página</CardDescription>
            </CardHeader>
            <CardContent>
              <iframe
                src={`data:application/pdf;base64,${previewMutation.data.pdf}`}
                className="w-full h-96 border border-slate-200 rounded-lg"
              />
            </CardContent>
          </Card>
        )}
      </div>
    </div>
  );
}
