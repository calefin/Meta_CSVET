import { useState } from "react";
import { useRoute, useLocation } from "wouter";
import { trpc } from "@/lib/trpc";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Skeleton } from "@/components/ui/skeleton";
import {
  Award,
  CheckCircle2,
  Clock,
  Download,
  GraduationCap,
  Loader2,
  Search,
  ShieldCheck,
  User,
  XCircle,
} from "lucide-react";

function ResultCard({ data }: { data: any }) {
  if (!data.valid) {
    return (
      <div className="max-w-lg mx-auto rounded-2xl border border-red-500/30 bg-red-500/5 p-8 text-center">
        <XCircle className="h-14 w-14 text-red-500 mx-auto mb-4" />
        <h2 className="font-semibold text-lg mb-2">Certificado não encontrado</h2>
        <p className="text-sm text-muted-foreground">
          O código informado não corresponde a nenhum certificado em nossa base de dados.
        </p>
      </div>
    );
  }

  const issuedAt = data.issuedAt
    ? new Date(data.issuedAt).toLocaleDateString("pt-BR", { day: "2-digit", month: "long", year: "numeric" })
    : "—";

  const downloadUrl = `/api/cursos/certificado/${data.validationCode}`;

  return (
    <div className="max-w-lg mx-auto rounded-2xl border border-green-500/30 bg-card shadow-xl shadow-black/5 overflow-hidden">
      {/* Header */}
      <div className="bg-gradient-to-br from-green-500/10 to-emerald-500/5 px-8 py-6 text-center border-b border-border/30">
        <CheckCircle2 className="h-14 w-14 text-green-500 mx-auto mb-3" />
        <Badge className="bg-green-500/15 text-green-700 dark:text-green-400 border-0 mb-2">
          Certificado válido
        </Badge>
        <p className="text-xs text-muted-foreground">Autenticado pela plataforma VetRank</p>
      </div>

      {/* Details */}
      <div className="px-8 py-6 space-y-4">
        <div className="flex items-start gap-3">
          <User className="h-4 w-4 text-muted-foreground mt-0.5 shrink-0" />
          <div>
            <p className="text-xs text-muted-foreground">Aluno</p>
            <p className="font-semibold">{data.studentName ?? "—"}</p>
            {data.cpfMasked && (
              <p className="text-xs text-muted-foreground">CPF: {data.cpfMasked}</p>
            )}
          </div>
        </div>

        <div className="flex items-start gap-3">
          <GraduationCap className="h-4 w-4 text-muted-foreground mt-0.5 shrink-0" />
          <div>
            <p className="text-xs text-muted-foreground">Curso concluído</p>
            <p className="font-semibold">{data.courseTitle ?? "—"}</p>
            {data.instructorName && (
              <p className="text-xs text-muted-foreground">Instrutor: {data.instructorName}</p>
            )}
          </div>
        </div>

        {data.durationHours > 0 && (
          <div className="flex items-start gap-3">
            <Clock className="h-4 w-4 text-muted-foreground mt-0.5 shrink-0" />
            <div>
              <p className="text-xs text-muted-foreground">Carga horária</p>
              <p className="font-semibold">{data.durationHours}h</p>
            </div>
          </div>
        )}

        <div className="flex items-start gap-3">
          <Award className="h-4 w-4 text-muted-foreground mt-0.5 shrink-0" />
          <div>
            <p className="text-xs text-muted-foreground">Data de emissão</p>
            <p className="font-semibold">{issuedAt}</p>
          </div>
        </div>

        <div className="pt-2 border-t border-border/30">
          <p className="text-[10px] text-muted-foreground break-all">
            Código: {data.validationCode}
          </p>
        </div>
      </div>

      <div className="px-8 pb-6">
        <a href={downloadUrl} download>
          <Button className="w-full gap-2">
            <Download className="h-4 w-4" /> Baixar certificado (PDF)
          </Button>
        </a>
      </div>
    </div>
  );
}

export default function VerifyCertificate() {
  const [, params] = useRoute("/verificar-certificado/:code");
  const [, setLocation] = useLocation();
  const [inputCode, setInputCode] = useState(params?.code ?? "");
  const [searchCode, setSearchCode] = useState(params?.code ?? "");

  const { data, isLoading } = trpc.courses.verifyCertificate.useQuery(
    { code: searchCode },
    { enabled: !!searchCode },
  );

  const handleSearch = () => {
    const trimmed = inputCode.trim();
    if (!trimmed) return;
    setSearchCode(trimmed);
    setLocation(`/verificar-certificado/${trimmed}`, { replace: true });
  };

  return (
    <div className="min-h-screen bg-background">
      {/* Hero */}
      <div className="border-b border-border/40 bg-card/20">
        <div className="max-w-3xl mx-auto px-4 py-12 text-center">
          <div className="inline-flex items-center gap-2 mb-4 px-3 py-1.5 rounded-full bg-primary/10 text-primary text-xs font-medium">
            <ShieldCheck className="h-3.5 w-3.5" /> Verificação Autêntica
          </div>
          <h1 className="font-serif text-3xl font-bold mb-3">Verificar Certificado</h1>
          <p className="text-muted-foreground">
            Insira o código de validação para confirmar a autenticidade de um certificado VetRank.
          </p>
        </div>
      </div>

      <div className="max-w-3xl mx-auto px-4 py-10 space-y-8">
        {/* Search */}
        <div className="flex gap-2 max-w-lg mx-auto">
          <Input
            value={inputCode}
            onChange={e => setInputCode(e.target.value)}
            onKeyDown={e => e.key === "Enter" && handleSearch()}
            placeholder="Cole o código de validação aqui..."
            className="flex-1 font-mono text-sm"
          />
          <Button onClick={handleSearch} disabled={!inputCode.trim() || isLoading}>
            {isLoading ? <Loader2 className="h-4 w-4 animate-spin" /> : <Search className="h-4 w-4" />}
          </Button>
        </div>

        {/* Loading */}
        {isLoading && (
          <div className="max-w-lg mx-auto rounded-2xl border border-border/50 overflow-hidden">
            <div className="p-8 space-y-4">
              <Skeleton className="h-14 w-14 rounded-full mx-auto" />
              <Skeleton className="h-5 w-40 mx-auto" />
              <Skeleton className="h-3 w-60 mx-auto" />
              <Skeleton className="h-3 w-48 mx-auto" />
            </div>
          </div>
        )}

        {/* Result */}
        {!isLoading && data && <ResultCard data={data} />}

        {/* Info box when no search yet */}
        {!searchCode && (
          <div className="max-w-lg mx-auto rounded-2xl border border-border/40 bg-card/30 p-6 text-center">
            <ShieldCheck className="h-10 w-10 mx-auto mb-3 text-muted-foreground/30" />
            <p className="text-sm text-muted-foreground">
              O código de validação está impresso no certificado e tem o formato de um UUID (ex: <span className="font-mono">550e8400-e29b-41d4-a716-446655440000</span>).
            </p>
          </div>
        )}
      </div>
    </div>
  );
}
