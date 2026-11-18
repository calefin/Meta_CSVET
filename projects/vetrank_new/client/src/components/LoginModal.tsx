import { useState, useEffect } from "react";
import { Dialog, DialogContent, DialogHeader, DialogTitle } from "@/components/ui/dialog";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Loader2, Zap, Eye, EyeOff } from "lucide-react";
import { toast } from "sonner";
import { Link } from "wouter";
import { trpc } from "@/lib/trpc";
import { useGoogleReCaptcha } from "react-google-recaptcha-v3";

type Mode = "login" | "register" | "forgot" | "reset" | "check_email";

const SHOW_GOOGLE = import.meta.env.VITE_APP_ID !== "local-dev";

interface Props {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  initialMode?: Mode;
}

export default function LoginModal({ open, onOpenChange, initialMode = "login" }: Props) {
  const [mode, setMode] = useState<Mode>(initialMode);
  const [name, setName] = useState("");
  const [email, setEmail] = useState("");
  const [password, setPassword] = useState("");
  const [confirmPassword, setConfirmPassword] = useState("");
  const [showPassword, setShowPassword] = useState(false);
  const [loading, setLoading] = useState(false);
  const [resetToken, setResetToken] = useState("");
  const [verifyEmail, setVerifyEmail] = useState("");

  const utils = trpc.useUtils();
  const { executeRecaptcha } = useGoogleReCaptcha();

  useEffect(() => {
    // Handle ?verified= param on page load (independent of modal open state)
    const params = new URLSearchParams(window.location.search);
    const verified = params.get("verified");
    if (verified === "ok") {
      toast.success("E-mail verificado com sucesso! Seja bem-vindo ao VetRank.");
      params.delete("verified");
      window.history.replaceState({}, "", window.location.pathname + (params.toString() ? `?${params}` : ""));
    } else if (verified === "expired") {
      toast.error("Link de verificação expirado. Faça login para reenviar um novo link.");
      params.delete("verified");
      window.history.replaceState({}, "", window.location.pathname + (params.toString() ? `?${params}` : ""));
    }
  }, []);

  useEffect(() => {
    if (open) {
      setMode(initialMode);
      const params = new URLSearchParams(window.location.search);
      const token = params.get("reset_token");
      if (token) {
        setResetToken(token);
        setMode("reset");
      }
    }
  }, [open, initialMode]);

  const resetFields = () => {
    setName(""); setEmail(""); setPassword(""); setConfirmPassword("");
    setShowPassword(false);
  };

  const switchMode = (m: Mode) => { setMode(m); resetFields(); };

  const handleClose = (v: boolean) => {
    if (!v) resetFields();
    onOpenChange(v);
  };

  const post = async (path: string, body: object) => {
    const res = await fetch(path, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify(body),
    });
    const data = await res.json();
    if (!res.ok) {
      const err: any = new Error(data.error || "Erro desconhecido.");
      if (data.requiresVerification) err.requiresVerification = true;
      throw err;
    }
    return data;
  };

  const handleLogin = async () => {
    if (!email || !password) { toast.error("Preencha e-mail e senha."); return; }
    setLoading(true);
    try {
      const recaptchaToken = executeRecaptcha ? await executeRecaptcha("login") : undefined;
      await post("/api/auth/email/login", { email, password, recaptchaToken });
      await utils.auth.me.invalidate();
      handleClose(false);
      toast.success("Login realizado com sucesso!");
    } catch (err: any) {
      if (err.requiresVerification) {
        // Antes só mostrava um toast — o usuário não tinha como pedir um novo link.
        setVerifyEmail(email);
        setMode("check_email");
        toast.info("Sua conta ainda não foi confirmada. Reenvie o link de verificação abaixo.");
        return;
      }
      toast.error(err.message);
    } finally {
      setLoading(false);
    }
  };

  const handleRegister = async () => {
    if (!name || !email || !password) { toast.error("Preencha todos os campos."); return; }
    if (password.length < 8) { toast.error("A senha deve ter pelo menos 8 caracteres."); return; }
    if (password !== confirmPassword) { toast.error("As senhas não coincidem."); return; }
    setLoading(true);
    try {
      const recaptchaToken = executeRecaptcha ? await executeRecaptcha("register") : undefined;
      const data = await post("/api/auth/email/register", { name, email, password, recaptchaToken });
      if (data.requiresVerification) {
        setVerifyEmail(email);
        switchMode("check_email");
        return;
      }
      await utils.auth.me.invalidate();
      handleClose(false);
      toast.success("Conta criada! Bem-vindo ao VetRank.");
    } catch (err: any) {
      if (err.requiresVerification) {
        setVerifyEmail(email);
        switchMode("check_email");
        return;
      }
      toast.error(err.message);
    } finally {
      setLoading(false);
    }
  };

  const handleForgot = async () => {
    if (!email) { toast.error("Informe seu e-mail."); return; }
    setLoading(true);
    try {
      await post("/api/auth/email/forgot", { email });
      toast.success("Se o e-mail estiver cadastrado, você receberá o link de redefinição.");
      switchMode("login");
    } catch (err: any) {
      toast.error(err.message);
    } finally {
      setLoading(false);
    }
  };

  const handleReset = async () => {
    if (!password) { toast.error("Informe a nova senha."); return; }
    if (password.length < 8) { toast.error("A senha deve ter pelo menos 8 caracteres."); return; }
    if (password !== confirmPassword) { toast.error("As senhas não coincidem."); return; }
    setLoading(true);
    try {
      await post("/api/auth/email/reset", { token: resetToken, password });
      // Remove token from URL
      const url = new URL(window.location.href);
      url.searchParams.delete("reset_token");
      window.history.replaceState({}, "", url.toString());
      await utils.auth.me.invalidate();
      handleClose(false);
      toast.success("Senha redefinida com sucesso!");
    } catch (err: any) {
      toast.error(err.message);
    } finally {
      setLoading(false);
    }
  };

  const handleResendVerification = async () => {
    if (!verifyEmail) return;
    setLoading(true);
    try {
      await post("/api/auth/email/verify/resend", { email: verifyEmail });
      toast.success("E-mail de verificação reenviado. Confira também a caixa de spam/promoções (pode levar alguns minutos).");
    } catch {
      toast.error("Não foi possível reenviar o e-mail. Tente novamente.");
    } finally {
      setLoading(false);
    }
  };

  const titles: Record<Mode, string> = {
    login: "Entrar no VetRank",
    register: "Criar conta",
    forgot: "Esqueceu a senha?",
    reset: "Nova senha",
    check_email: "Verifique seu e-mail",
  };

  return (
    <Dialog open={open} onOpenChange={handleClose}>
      <DialogContent className="sm:max-w-md">
        <DialogHeader>
          <DialogTitle className="flex items-center gap-2 font-serif">
            <div className="flex h-8 w-8 items-center justify-center rounded-lg bg-primary/20 border border-primary/30">
              <Zap className="h-4 w-4 text-primary" />
            </div>
            {titles[mode]}
          </DialogTitle>
        </DialogHeader>

        <div className="space-y-4 pt-2">
          {/* Email verification screen */}
          {mode === "check_email" && (
            <div className="text-center space-y-4 py-2">
              <div className="text-5xl">📬</div>
              <p className="text-sm text-muted-foreground leading-relaxed">
                Enviamos um link de confirmação para<br/>
                <strong className="text-foreground">{verifyEmail}</strong>.
              </p>
              <p className="text-xs text-muted-foreground">
                Clique no link do e-mail para ativar sua conta e entrar.<br/>
                O link expira em 48 horas. Não chegou? Veja o spam/promoções.
              </p>
              <Button variant="outline" className="w-full" onClick={handleResendVerification} disabled={loading}>
                {loading && <Loader2 className="h-4 w-4 mr-2 animate-spin" />}
                Reenviar e-mail de verificação
              </Button>
              {SHOW_GOOGLE && (
                <Button variant="ghost" className="w-full text-xs" onClick={() => (window.location.href = "/api/auth/google")}>
                  Ou entre agora com Google usando este mesmo e-mail
                </Button>
              )}
              <button className="text-xs text-muted-foreground hover:text-foreground" onClick={() => switchMode("login")}>
                ← Voltar ao login
              </button>
            </div>
          )}

          {/* Google OAuth button */}
          {(mode === "login" || mode === "register") && SHOW_GOOGLE && (
            <>
              <Button
                variant="outline"
                className="w-full gap-2 border-border/60"
                onClick={() => (window.location.href = "/api/auth/google")}
              >
                <svg viewBox="0 0 24 24" className="h-4 w-4" aria-hidden="true">
                  <path d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z" fill="#4285F4"/>
                  <path d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" fill="#34A853"/>
                  <path d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z" fill="#FBBC05"/>
                  <path d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z" fill="#EA4335"/>
                </svg>
                {mode === "login" ? "Entrar com Google" : "Cadastrar com Google"}
              </Button>
              <div className="flex items-center gap-3 text-xs text-muted-foreground">
                <div className="flex-1 border-t border-border/50" />
                ou com e-mail e senha
                <div className="flex-1 border-t border-border/50" />
              </div>
            </>
          )}

          {/* Name field (register only) */}
          {mode === "register" && (
            <div className="space-y-1.5">
              <Label htmlFor="auth-name" className="text-sm">Nome completo</Label>
              <Input
                id="auth-name"
                value={name}
                onChange={e => setName(e.target.value)}
                placeholder="Seu nome"
                autoComplete="name"
                onKeyDown={e => e.key === "Enter" && handleRegister()}
              />
            </div>
          )}

          {/* Email field */}
          {mode !== "reset" && mode !== "check_email" && (
            <div className="space-y-1.5">
              <Label htmlFor="auth-email" className="text-sm">E-mail</Label>
              <Input
                id="auth-email"
                type="email"
                value={email}
                onChange={e => setEmail(e.target.value)}
                placeholder="seu@email.com"
                autoComplete="email"
                onKeyDown={e => {
                  if (e.key === "Enter") {
                    if (mode === "login") handleLogin();
                    else if (mode === "register") handleRegister();
                    else if (mode === "forgot") handleForgot();
                  }
                }}
              />
            </div>
          )}

          {/* Password field */}
          {(mode === "login" || mode === "register" || mode === "reset") && (
            <div className="space-y-1.5">
              <Label htmlFor="auth-password" className="text-sm">
                {mode === "reset" ? "Nova senha" : "Senha"}
              </Label>
              <div className="relative">
                <Input
                  id="auth-password"
                  type={showPassword ? "text" : "password"}
                  value={password}
                  onChange={e => setPassword(e.target.value)}
                  placeholder="Mínimo 8 caracteres"
                  autoComplete={mode === "login" ? "current-password" : "new-password"}
                  onKeyDown={e => {
                    if (e.key === "Enter") {
                      if (mode === "login") handleLogin();
                      else if (mode === "register") handleRegister();
                      else if (mode === "reset") handleReset();
                    }
                  }}
                />
                <button
                  type="button"
                  className="absolute right-3 top-1/2 -translate-y-1/2 text-muted-foreground hover:text-foreground"
                  onClick={() => setShowPassword(v => !v)}
                  tabIndex={-1}
                >
                  {showPassword ? <EyeOff className="h-4 w-4" /> : <Eye className="h-4 w-4" />}
                </button>
              </div>
            </div>
          )}

          {/* Confirm password (register + reset) */}
          {(mode === "register" || mode === "reset") && (
            <div className="space-y-1.5">
              <Label htmlFor="auth-confirm" className="text-sm">Confirmar senha</Label>
              <Input
                id="auth-confirm"
                type={showPassword ? "text" : "password"}
                value={confirmPassword}
                onChange={e => setConfirmPassword(e.target.value)}
                placeholder="Repita a senha"
                autoComplete="new-password"
                onKeyDown={e => {
                  if (e.key === "Enter") {
                    if (mode === "register") handleRegister();
                    else if (mode === "reset") handleReset();
                  }
                }}
              />
            </div>
          )}

          {/* Terms notice for register */}
          {mode === "register" && (
            <p className="text-xs text-muted-foreground leading-relaxed">
              Ao criar conta, você concorda com os{" "}
              <Link href="/terms" className="text-primary hover:underline" onClick={() => handleClose(false)}>Termos de Uso</Link>
              {" "}e a{" "}
              <Link href="/privacy" className="text-primary hover:underline" onClick={() => handleClose(false)}>Política de Privacidade</Link>.
            </p>
          )}

          {/* Primary action button */}
          {mode !== "check_email" && <Button
            className="w-full bg-primary text-primary-foreground hover:bg-primary/90"
            onClick={
              mode === "login" ? handleLogin :
              mode === "register" ? handleRegister :
              mode === "forgot" ? handleForgot :
              handleReset
            }
            disabled={loading}
          >
            {loading && <Loader2 className="h-4 w-4 mr-2 animate-spin" />}
            {mode === "login" ? "Entrar" :
             mode === "register" ? "Criar conta" :
             mode === "forgot" ? "Enviar link de redefinição" :
             "Redefinir senha"}
          </Button>}

          {/* Footer links */}
          {mode !== "check_email" && <div className="flex flex-col items-center gap-1.5 text-sm text-muted-foreground">
            {mode === "login" && (
              <>
                <button className="hover:text-foreground transition-colors" onClick={() => switchMode("forgot")}>
                  Esqueceu a senha?
                </button>
                <span>
                  Não tem conta?{" "}
                  <button className="text-primary hover:underline" onClick={() => switchMode("register")}>
                    Criar conta grátis
                  </button>
                </span>
              </>
            )}
            {mode === "register" && (
              <span>
                Já tem conta?{" "}
                <button className="text-primary hover:underline" onClick={() => switchMode("login")}>
                  Entrar
                </button>
              </span>
            )}
            {(mode === "forgot" || mode === "reset") && (
              <button className="hover:text-foreground transition-colors" onClick={() => switchMode("login")}>
                ← Voltar ao login
              </button>
            )}
          </div>}
        </div>
      </DialogContent>
    </Dialog>
  );
}
