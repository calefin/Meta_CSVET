import { useEffect } from "react";
import { useAuth } from "@/_core/hooks/useAuth";
import { useAuthModal } from "@/contexts/AuthModalContext";
import { Skeleton } from "@/components/ui/skeleton";
import { Lock, RefreshCw } from "lucide-react";
import { Button } from "@/components/ui/button";
import VetRankLogo from "@/components/VetRankLogo";

interface AuthGuardProps {
  children: React.ReactNode;
  message?: string;
}

const IS_LOCAL_DEV = import.meta.env.VITE_APP_ID === "local-dev";

function LocalDevLogin() {
  return (
    <div className="min-h-screen bg-[#0d1a14] flex flex-col items-center justify-center gap-6 text-emerald-100">
      <div className="flex flex-col items-center gap-2">
        <VetRankLogo className="h-14 w-auto" />
        <span className="text-xs bg-emerald-900 text-emerald-400 px-3 py-1 rounded-full">
          Ambiente Local de Desenvolvimento
        </span>
      </div>
      <p className="text-sm text-emerald-700">Escolha o perfil para entrar:</p>
      <div className="flex gap-3">
        <a
          href="/api/dev/login?user=admin"
          className="px-6 py-3 bg-emerald-700 hover:bg-emerald-600 text-white font-semibold rounded-lg transition-colors"
        >
          Admin <span className="text-xs opacity-70 ml-1">premium</span>
        </a>
        <a
          href="/api/dev/login?user=user"
          className="px-6 py-3 bg-cyan-800 hover:bg-cyan-700 text-white font-semibold rounded-lg transition-colors"
        >
          Usuário <span className="text-xs opacity-70 ml-1">trial</span>
        </a>
      </div>
    </div>
  );
}

export default function AuthGuard({ children, message }: AuthGuardProps) {
  const { isAuthenticated, loading, error, refresh } = useAuth();
  const { openLogin } = useAuthModal();

  const isNetworkError = error && (
    error.message?.includes("fetch") ||
    error.message?.includes("network") ||
    error.message?.includes("Network") ||
    (error as any)?.data?.code === undefined
  );

  // Só abre o modal quando sabemos com certeza que não está autenticado
  // (não em erro de rede — pode ser problema temporário do servidor)
  useEffect(() => {
    if (loading) return;
    if (isNetworkError) return; // erro de rede: aguarda sem redirecionar
    if (isAuthenticated) return;
    if (IS_LOCAL_DEV) return;
    openLogin();
  }, [loading, isAuthenticated, isNetworkError]);

  // Carregando
  if (loading) {
    return (
      <div className="min-h-screen bg-background flex flex-col items-center justify-center gap-4">
        <Skeleton className="h-10 w-64" />
        <Skeleton className="h-6 w-48" />
        <Skeleton className="h-48 w-full max-w-2xl" />
      </div>
    );
  }

  // Erro de rede (servidor temporariamente indisponível)
  if (isNetworkError && !isAuthenticated) {
    return (
      <div className="min-h-screen bg-background flex flex-col items-center justify-center gap-4">
        <RefreshCw className="h-8 w-8 text-muted-foreground animate-spin" />
        <p className="text-sm text-muted-foreground">Verificando conexão com o servidor...</p>
        <Button variant="outline" size="sm" onClick={() => refresh()}>
          Tentar novamente
        </Button>
      </div>
    );
  }

  // Não autenticado em ambiente local
  if (!isAuthenticated && IS_LOCAL_DEV) return <LocalDevLogin />;

  // Não autenticado — modal já foi aberto pelo useEffect
  if (!isAuthenticated) {
    return (
      <div className="min-h-screen bg-background flex flex-col items-center justify-center gap-4">
        <Lock className="h-10 w-10 text-primary/50" />
        <p className="text-sm text-muted-foreground">{message ?? "Faça login para continuar."}</p>
        <Button size="sm" onClick={() => openLogin()}>Entrar</Button>
      </div>
    );
  }

  return <>{children}</>;
}
