import { useLocation } from "wouter";
import { Button } from "@/components/ui/button";
import { ArrowLeft } from "lucide-react";

interface BackButtonProps {
  to?: string;
  label?: string;
}

export default function BackButton({ to = "/", label = "Voltar" }: BackButtonProps) {
  const [, navigate] = useLocation();

  function handleBack() {
    if (window.history.length > 1) {
      window.history.back();
    } else {
      navigate(to);
    }
  }

  return (
    <Button
      variant="ghost"
      size="sm"
      onClick={handleBack}
      className="gap-1.5 text-muted-foreground hover:text-foreground -ml-2 mb-4"
    >
      <ArrowLeft className="w-4 h-4" />
      {label}
    </Button>
  );
}
