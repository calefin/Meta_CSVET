import { Button } from "@/components/ui/button";
import { useGoogleAuth } from "@/_core/hooks/useGoogleAuth";
import { useEffect, useState } from "react";
import { useLocation } from "wouter";

export function GoogleLoginButton() {
  const { initiateGoogleLogin } = useGoogleAuth();
  const [isLoading, setIsLoading] = useState(false);

  const handleGoogleLogin = async () => {
    setIsLoading(true);
    try {
      initiateGoogleLogin();
    } catch (error) {
      console.error("Google login error:", error);
      setIsLoading(false);
    }
  };

  return (
    <Button
      onClick={handleGoogleLogin}
      disabled={isLoading}
      className="w-full"
      variant="outline"
    >
      {isLoading ? "Conectando..." : "Entrar com Google"}
    </Button>
  );
}

export function GoogleLogoutButton() {
  const { logout } = useGoogleAuth();
  const [, navigate] = useLocation();
  const [isLoading, setIsLoading] = useState(false);

  const handleLogout = async () => {
    setIsLoading(true);
    try {
      await logout();
    } catch (error) {
      console.error("Logout error:", error);
    } finally {
      setIsLoading(false);
    }
  };

  return (
    <Button
      onClick={handleLogout}
      disabled={isLoading}
      variant="ghost"
    >
      {isLoading ? "Saindo..." : "Sair"}
    </Button>
  );
}

export function GoogleAuthStatus() {
  const { checkSession } = useGoogleAuth();
  const [isAuthenticated, setIsAuthenticated] = useState<boolean | null>(null);

  useEffect(() => {
    checkSession().then(setIsAuthenticated);
  }, [checkSession]);

  if (isAuthenticated === null) {
    return <div>Verificando sessão...</div>;
  }

  return (
    <div>
      {isAuthenticated ? (
        <GoogleLogoutButton />
      ) : (
        <GoogleLoginButton />
      )}
    </div>
  );
}
