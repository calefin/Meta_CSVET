import { createContext, useContext, useState, useEffect, type ReactNode } from "react";
import LoginModal from "@/components/LoginModal";

interface AuthModalContextType {
  openLogin: (mode?: "login" | "register" | "reset") => void;
}

const AuthModalContext = createContext<AuthModalContextType>({ openLogin: () => {} });

export function AuthModalProvider({ children }: { children: ReactNode }) {
  const [open, setOpen] = useState(false);
  const [initialMode, setInitialMode] = useState<"login" | "register" | "reset">("login");

  // Auto-open in reset mode when ?reset_token is in URL
  useEffect(() => {
    const params = new URLSearchParams(window.location.search);
    if (params.get("reset_token")) {
      setInitialMode("reset");
      setOpen(true);
    }
  }, []);

  const openLogin = (mode: "login" | "register" | "reset" = "login") => {
    setInitialMode(mode);
    setOpen(true);
  };

  return (
    <AuthModalContext.Provider value={{ openLogin }}>
      {children}
      <LoginModal open={open} onOpenChange={setOpen} initialMode={initialMode} />
    </AuthModalContext.Provider>
  );
}

export const useAuthModal = () => useContext(AuthModalContext);
