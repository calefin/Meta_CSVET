import { useRef, useState } from "react";
import { useLocation } from "wouter";
import { trpc } from "@/lib/trpc";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { toast } from "sonner";
import { getLoginUrl } from "@/const";
import { PenLine } from "lucide-react";
import ReCAPTCHA from "react-google-recaptcha";

const RECAPTCHA_SITE_KEY = import.meta.env.VITE_RECAPTCHA_SITE_KEY as string | undefined;

export default function Signup() {
  const [, navigate] = useLocation();
  const [email, setEmail] = useState("");
  const [name, setName] = useState("");
  const [password, setPassword] = useState("");
  const [recaptchaToken, setRecaptchaToken] = useState<string | null>(null);
  const recaptchaRef = useRef<ReCAPTCHA>(null);

  const recaptchaRequired = !!RECAPTCHA_SITE_KEY;
  const canSubmit = !recaptchaRequired || !!recaptchaToken;

  const signupMutation = trpc.auth.signup.useMutation({
    onSuccess: () => {
      toast.success("Conta criada com sucesso.");
      navigate("/");
    },
    onError: error => {
      recaptchaRef.current?.reset();
      setRecaptchaToken(null);
      toast.error(error.message || "Falha ao criar conta.");
    },
  });

  const handleSubmit = async (event: React.FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    if (!canSubmit) {
      toast.error("Por favor, complete a verificação reCAPTCHA.");
      return;
    }
    await signupMutation.mutateAsync({
      email,
      password,
      name: name || undefined,
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
          <CardTitle>Cadastrar</CardTitle>
          <CardDescription>Crie sua conta para começar a usar a plataforma.</CardDescription>
        </CardHeader>
        <CardContent>
          <form className="space-y-4" onSubmit={handleSubmit}>
            <div>
              <label className="block text-sm font-medium text-slate-700 mb-1" htmlFor="name">
                Nome
              </label>
              <Input
                id="name"
                type="text"
                value={name}
                onChange={e => setName(e.target.value)}
                placeholder="Seu nome"
              />
            </div>
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
              <div className="flex justify-center my-3">
                <ReCAPTCHA
                  ref={recaptchaRef}
                  sitekey={RECAPTCHA_SITE_KEY!}
                  onChange={(token) => setRecaptchaToken(token)}
                  onExpired={() => setRecaptchaToken(null)}
                  hl="pt"
                />
              </div>
            )}

            <Button type="submit" className="w-full" disabled={signupMutation.isPending || !canSubmit}>
              {signupMutation.isPending ? "Criando conta..." : "Cadastrar"}
            </Button>
          </form>
          <div className="mt-4 text-sm text-slate-600">
            Já tem conta?{' '}
            <button
              type="button"
              className="font-medium text-emerald-600 hover:text-emerald-700"
              onClick={() => navigate(getLoginUrl())}
            >
              Faça login
            </button>
          </div>
        </CardContent>
      </Card>
      </div>
    </div>
  );
}
