import { useEffect, useState } from "react";
import { useLocation } from "wouter";
import { useAuth } from "@/_core/hooks/useAuth";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Checkbox } from "@/components/ui/checkbox";
import { Loader2, FileText, ClipboardList, PenLine, CheckSquare, GraduationCap, Users, FileCheck2 } from "lucide-react";
import { getLoginUrl } from "@/const";

const PREFERENCE_KEY = "profaid-preferred-system";
type SystemChoice = "provas" | "gestao";

// Clique direto no card: gesto do usuário, então window.open (nova aba) passa no bloqueador de pop-up.
function accessSystem(choice: SystemChoice, navigate: (path: string) => void) {
  if (choice === "gestao") {
    window.open("/api/gestao/sso", "_blank", "noopener,noreferrer");
    navigate("/");
  } else {
    navigate("/");
  }
}

// Redirecionamento automático (preferência lembrada, sem clique) — window.open aqui seria bloqueado
// como pop-up por não vir de um gesto do usuário, então a navegação de Gestão usa a própria aba.
function autoAccessSystem(choice: SystemChoice, navigate: (path: string) => void) {
  if (choice === "gestao") {
    window.location.href = "/api/gestao/sso";
  } else {
    navigate("/");
  }
}

export default function SystemHub() {
  const { user, loading } = useAuth();
  const [, navigate] = useLocation();
  const [rememberChoice, setRememberChoice] = useState(false);
  const [autoRedirecting, setAutoRedirecting] = useState(false);

  // "Trocar Sistema" no menu manda ?choose=1 pra sempre mostrar a tela, mesmo com preferência salva.
  const forceChoose = typeof window !== "undefined" && new URLSearchParams(window.location.search).has("choose");

  useEffect(() => {
    if (loading || !user || forceChoose) return;
    const saved = localStorage.getItem(PREFERENCE_KEY) as SystemChoice | null;
    if (saved === "provas" || saved === "gestao") {
      setAutoRedirecting(true);
      autoAccessSystem(saved, navigate);
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [loading, user, forceChoose]);

  if (loading || autoRedirecting) {
    return (
      <div className="flex items-center justify-center min-h-screen">
        <Loader2 className="animate-spin w-8 h-8 text-primary" />
      </div>
    );
  }

  if (!user) {
    return (
      <div className="flex items-center justify-center min-h-screen">
        <div className="flex flex-col items-center gap-6 p-8 max-w-md w-full">
          <h1 className="text-2xl font-semibold tracking-tight text-center">Faça login para continuar</h1>
          <Button onClick={() => navigate(getLoginUrl())} size="lg" className="w-full">
            Entrar
          </Button>
        </div>
      </div>
    );
  }

  function handleAccess(choice: SystemChoice) {
    if (rememberChoice) {
      localStorage.setItem(PREFERENCE_KEY, choice);
    } else {
      localStorage.removeItem(PREFERENCE_KEY);
    }
    accessSystem(choice, navigate);
  }

  return (
    <div className="min-h-screen bg-gradient-to-br from-indigo-50 via-white to-blue-50 flex items-center justify-center px-4 py-12">
      <div className="w-full max-w-4xl space-y-8">
        <div className="text-center space-y-2">
          <div className="flex items-center justify-center gap-2">
            <PenLine className="h-6 w-6 text-primary" />
            <span className="text-2xl font-bold tracking-tight">Prof-Aid</span>
          </div>
          <h1 className="text-xl font-semibold text-slate-900">
            Olá, {user.name?.split(" ")[0] ?? "professor(a)"}! O que você quer acessar hoje?
          </h1>
          <p className="text-sm text-slate-500">Escolha um sistema para continuar. Você pode trocar a qualquer momento pelo menu lateral.</p>
        </div>

        <div className="grid md:grid-cols-2 gap-6">
          <Card className="shadow-lg border border-slate-200 hover:border-primary/40 hover:shadow-xl transition-all flex flex-col">
            <CardHeader>
              <div className="p-2.5 rounded-lg bg-blue-50 w-fit mb-2">
                <FileText className="w-6 h-6 text-blue-700" />
              </div>
              <CardTitle>Sistema de Provas</CardTitle>
            </CardHeader>
            <CardContent className="flex flex-col flex-1 gap-4">
              <ul className="text-sm text-slate-600 space-y-2 flex-1">
                <li className="flex items-start gap-2">
                  <FileText className="w-4 h-4 text-blue-600 mt-0.5 shrink-0" />
                  Banco de questões, importação e geração de provas em PDF
                </li>
                <li className="flex items-start gap-2">
                  <CheckSquare className="w-4 h-4 text-blue-600 mt-0.5 shrink-0" />
                  Correção automática de gabaritos por câmera
                </li>
              </ul>
              <Button className="w-full" onClick={() => handleAccess("provas")}>
                Acessar Provas
              </Button>
            </CardContent>
          </Card>

          <Card className="shadow-lg border border-slate-200 hover:border-primary/40 hover:shadow-xl transition-all flex flex-col relative">
            <span className="absolute -top-2.5 right-4 bg-emerald-600 text-white text-[10px] font-semibold px-2 py-0.5 rounded-full">
              Uso diário mais comum
            </span>
            <CardHeader>
              <div className="p-2.5 rounded-lg bg-emerald-50 w-fit mb-2">
                <ClipboardList className="w-6 h-6 text-emerald-700" />
              </div>
              <CardTitle>Gestão de Alunos</CardTitle>
            </CardHeader>
            <CardContent className="flex flex-col flex-1 gap-4">
              <ul className="text-sm text-slate-600 space-y-2 flex-1">
                <li className="flex items-start gap-2">
                  <GraduationCap className="w-4 h-4 text-emerald-600 mt-0.5 shrink-0" />
                  Orientandos, turmas e documentos de TCC/IC
                </li>
                <li className="flex items-start gap-2">
                  <FileCheck2 className="w-4 h-4 text-emerald-600 mt-0.5 shrink-0" />
                  Acompanhamento de assinaturas e etapas de entrega
                </li>
              </ul>
              <Button className="w-full bg-emerald-700 hover:bg-emerald-800" onClick={() => handleAccess("gestao")}>
                Acessar Gestão
              </Button>
            </CardContent>
          </Card>
        </div>

        <label className="flex items-center justify-center gap-2 text-sm text-slate-600 cursor-pointer select-none">
          <Checkbox checked={rememberChoice} onCheckedChange={(v) => setRememberChoice(v === true)} />
          Lembrar minha escolha e pular esta tela nos próximos acessos
        </label>
      </div>
    </div>
  );
}
