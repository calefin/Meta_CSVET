import { useState, useEffect } from "react";
import { X, Download, Share, PlusSquare, Smartphone, ArrowUpRight } from "lucide-react";
import { Button } from "@/components/ui/button";
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog";

export default function PWAInstallBanner() {
  const [showBanner, setShowBanner] = useState(false);
  const [deferredPrompt, setDeferredPrompt] = useState<any>(null);
  const [isIOS, setIsIOS] = useState(false);
  const [showIOSModal, setShowIOSModal] = useState(false);

  useEffect(() => {
    // 1. Verificar se o app já está rodando em modo standalone (instalado)
    const isStandalone = window.matchMedia("(display-mode: standalone)").matches 
      || (window.navigator as any).standalone 
      || document.referrer.includes("android-app://");

    if (isStandalone) {
      return;
    }

    // 2. Detectar se é iOS (iPhone/iPad)
    const userAgent = window.navigator.userAgent.toLowerCase();
    const detectIOS = /iphone|ipad|ipod/.test(userAgent);
    setIsIOS(detectIOS);

    // 3. Capturar o evento de instalação padrão do Android/Chrome
    const handleBeforeInstallPrompt = (e: Event) => {
      e.preventDefault();
      setDeferredPrompt(e);
      // Apenas exibe o banner em dispositivos móveis
      if (/android|iphone|ipad|ipod|webos|iemobile|opera mini/i.test(userAgent)) {
        setShowBanner(true);
      }
    };

    window.addEventListener("beforeinstallprompt", handleBeforeInstallPrompt);

    // Ouvinte global para disparo programático de qualquer lugar do app
    const handleTriggerInstall = () => {
      if (detectIOS) {
        setShowIOSModal(true);
      } else {
        // Se temos o prompt nativo do Android salvo, executa ele
        if (deferredPrompt) {
          deferredPrompt.prompt();
        } else {
          // Caso contrário, mostra o banner informativo como fallback
          setShowBanner(true);
        }
      }
    };

    window.addEventListener("trigger-pwa-install", handleTriggerInstall);

    // No iOS, como o prompt automático não existe, podemos mostrar o banner após alguns segundos de navegação
    if (detectIOS) {
      const timer = setTimeout(() => {
        // Verificar se o usuário já dispensou nesta sessão
        const dismissed = sessionStorage.getItem("pwa-dismissed");
        if (!dismissed) {
          setShowBanner(true);
        }
      }, 5000);
      return () => {
        clearTimeout(timer);
        window.removeEventListener("trigger-pwa-install", handleTriggerInstall);
      };
    }

    return () => {
      window.removeEventListener("beforeinstallprompt", handleBeforeInstallPrompt);
      window.removeEventListener("trigger-pwa-install", handleTriggerInstall);
    };
  }, [deferredPrompt]);

  const handleInstallClick = async () => {
    if (isIOS) {
      // Abrir modal de tutorial para iOS Safari
      setShowIOSModal(true);
      setShowBanner(false);
      return;
    }

    if (!deferredPrompt) return;

    // Disparar o prompt nativo no Android/Chrome
    deferredPrompt.prompt();
    const { outcome } = await deferredPrompt.userChoice;
    console.log(`[PWA] Escolha do usuário para instalação: ${outcome}`);
    
    // Limpar o prompt salvo
    setDeferredPrompt(null);
    setShowBanner(false);
  };

  const handleDismiss = () => {
    setShowBanner(false);
    sessionStorage.setItem("pwa-dismissed", "true");
  };

  return (
    <>
      {/* Banner flutuante inferior no Mobile */}
      {showBanner && (
        <div className="fixed bottom-4 left-4 right-4 z-50 md:left-auto md:max-w-sm bg-background/95 border border-border/80 p-4 rounded-xl shadow-2xl backdrop-blur-md animate-in fade-in slide-in-from-bottom-5 duration-300">
          <div className="flex items-start gap-3">
            <div className="p-2 bg-primary/10 rounded-lg text-primary shrink-0">
              <Smartphone className="h-6 w-6" />
            </div>
            <div className="flex-1 space-y-1">
              <h4 className="text-sm font-semibold text-foreground">Instalar o VetRank no Celular</h4>
              <p className="text-xs text-muted-foreground leading-relaxed">
                Estude off-line, ganhe XP diário e acesse simulados cronometrados como um aplicativo nativo.
              </p>
              <div className="flex items-center gap-2 pt-2">
                <Button 
                  size="sm" 
                  onClick={handleInstallClick} 
                  className="text-xs h-8 gap-1.5 font-medium shadow-lg shadow-primary/20"
                >
                  <Download className="h-3.5 w-3.5" />
                  Instalar
                </Button>
                <Button 
                  size="sm" 
                  variant="ghost" 
                  onClick={handleDismiss} 
                  className="text-xs h-8 text-muted-foreground hover:text-foreground"
                >
                  Agora não
                </Button>
              </div>
            </div>
            <button 
              onClick={handleDismiss}
              className="text-muted-foreground hover:text-foreground p-1 rounded-lg transition-colors"
            >
              <X className="h-4 w-4" />
            </button>
          </div>
        </div>
      )}

      {/* Modal de Tutorial exclusivo para iPhone / iOS Safari */}
      <Dialog open={showIOSModal} onOpenChange={setShowIOSModal}>
        <DialogContent className="max-w-xs sm:max-w-sm rounded-2xl bg-background border border-border/80 shadow-2xl backdrop-blur-md p-6">
          <DialogHeader className="space-y-2">
            <DialogTitle className="flex items-center gap-2 text-foreground">
              <Smartphone className="h-5 w-5 text-primary" />
              Instalar no iPhone
            </DialogTitle>
            <DialogDescription className="text-xs text-muted-foreground text-left">
              O Safari do iOS exige a instalação manual. Siga este passo a passo de 3 segundos para adicionar o VetRank à sua tela de início:
            </DialogDescription>
          </DialogHeader>

          <div className="space-y-4 my-2 text-sm text-foreground text-left">
            <div className="flex items-center gap-3 bg-muted/30 p-2.5 rounded-lg border border-border/50">
              <span className="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-primary/10 text-xs font-semibold text-primary">
                1
              </span>
              <p className="text-xs leading-relaxed">
                Toque no botão de <strong>Compartilhar</strong> <Share className="inline-block h-4 w-4 mx-1 text-primary" /> na barra inferior do Safari.
              </p>
            </div>

            <div className="flex items-center gap-3 bg-muted/30 p-2.5 rounded-lg border border-border/50">
              <span className="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-primary/10 text-xs font-semibold text-primary">
                2
              </span>
              <p className="text-xs leading-relaxed">
                Role a folha de opções para baixo e selecione <strong>Adicionar à Tela de Início</strong> <PlusSquare className="inline-block h-4 w-4 mx-1 text-primary" />.
              </p>
            </div>

            <div className="flex items-center gap-3 bg-muted/30 p-2.5 rounded-lg border border-border/50">
              <span className="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-primary/10 text-xs font-semibold text-primary">
                3
              </span>
              <p className="text-xs leading-relaxed">
                Toque em <strong>Adicionar</strong> <span className="inline-flex items-center text-xs font-bold text-primary mx-0.5">Adicionar <ArrowUpRight className="h-3 w-3 inline" /></span> no canto superior direito para finalizar.
              </p>
            </div>
          </div>

          <Button 
            className="w-full mt-2 font-medium" 
            onClick={() => setShowIOSModal(false)}
          >
            Entendido, ir para o Safari
          </Button>
        </DialogContent>
      </Dialog>
    </>
  );
}
