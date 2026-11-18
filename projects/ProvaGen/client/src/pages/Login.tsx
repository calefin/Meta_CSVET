import { useRef, useState } from "react";
import { useLocation } from "wouter";
import { trpc } from "@/lib/trpc";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { toast } from "sonner";
import { getSignupUrl } from "@/const";
import { GoogleLoginButton } from "@/components/GoogleAuth";
import ReCAPTCHA from "react-google-recaptcha";
import { PenLine } from "lucide-react";

const RECAPTCHA_SITE_KEY = import.meta.env.VITE_RECAPTCHA_SITE_KEY as string | undefined;

export default function Login() {
  const [, navigate] = useLocation();
  const [email, setEmail] = useState("");
  const [password, setPassword] = useState("");
  const [recaptchaToken, setRecaptchaToken] = useState<string | null>(null);
  const recaptchaRef = useRef<ReCAPTCHA>(null);
  const utils = trpc.useUtils();

  const loginMutation = trpc.auth.login.useMutation({
    onSuccess: async () => {
      toast.success("Login realizado com sucesso.");
      const user = await utils.auth.me.fetch();
      navigate(user?.role === "admin" ? "/admin" : "/hub");
    },
    onError: error => {
      // Reset reCAPTCHA on failure so user can try again
      recaptchaRef.current?.reset();
      setRecaptchaToken(null);
      toast.error(error.message || "Falha ao fazer login.");
    },
  });

  const recaptchaRequired = !!RECAPTCHA_SITE_KEY;
  const canSubmit = !recaptchaRequired || !!recaptchaToken;

  const handleSubmit = async (event: React.FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    if (!canSubmit) {
      toast.error("Por favor, complete a verificação reCAPTCHA.");
      return;
    }
    await loginMutation.mutateAsync({
      email,
      password,
      recaptchaToken: recaptchaToken ?? undefined,
    });
  };

  return (
    <div className="min-h-screen bg-gradient-to-br from-indigo-50 via-white to-blue-50 flex items-center justify-center px-4 py-12">
      <div className="w-full max-w-md space-y-5">
        <div className="flex items-center justify-center gap-2">
          <PenLine className="h-6 w-6 text-primary" />
          <span className="text-2xl font-bold tracking-tight">Prof-Aid</span>
        </div>
      <Card className="shadow-xl border border-slate-200">
        <CardHeader>
          <CardTitle>Entrar</CardTitle>
          <CardDescription>Use sua conta para acessar o painel.</CardDescription>
        </CardHeader>
        <CardContent>
          <div className="space-y-4">
            <GoogleLoginButton />
            <div className="relative">
              <div className="absolute inset-0 flex items-center">
                <div className="w-full border-t border-slate-200" />
              </div>
              <div className="relative flex justify-center text-sm">
                <span className="px-2 bg-white text-slate-500">Ou continue com email</span>
              </div>
            </div>
          </div>

          <form className="space-y-4 mt-4" onSubmit={handleSubmit}>
            <div>
              <label className="block text-sm font-medium text-slate-700 mb-1" htmlFor="email">
                Email
              </label>
              <Input
                id="email"
                type="email"
                value={email}
                onChange={e => setEmail(e.target.value)}
                placeholder="seu@email.com"
                required
              />
            </div>
            <div>
              <label className="block text-sm font-medium text-slate-700 mb-1" htmlFor="password">
                Senha
              </label>
              <Input
                id="password"
                type="password"
                value={password}
                onChange={e => setPassword(e.target.value)}
                placeholder="••••••••"
                required
                minLength={8}
              />
            </div>

            {recaptchaRequired && (
              <div className="flex justify-center">
                <ReCAPTCHA
                  ref={recaptchaRef}
                  sitekey={RECAPTCHA_SITE_KEY!}
                  onChange={(token) => setRecaptchaToken(token)}
                  onExpired={() => setRecaptchaToken(null)}
                  hl="pt"
                />
              </div>
            )}

            <Button
              type="submit"
              className="w-full"
              disabled={loginMutation.isPending || !canSubmit}
            >
              {loginMutation.isPending ? "Entrando..." : "Entrar"}
            </Button>
          </form>

          <div className="mt-4 text-sm text-slate-600">
            Novo por aqui?{' '}
            <button
              type="button"
              className="font-medium text-emerald-600 hover:text-emerald-700"
              onClick={() => navigate(getSignupUrl())}
            >
              Crie sua conta
            </button>
          </div>
        </CardContent>
      </Card>
      </div>
    </div>
  );
}
