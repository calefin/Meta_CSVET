import { useAuth } from "@/_core/hooks/useAuth";
import { Button } from "@/components/ui/button";
import { SidebarTrigger } from "@/components/ui/sidebar";
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";
import { useLocation } from "wouter";
import { Globe } from "lucide-react";
import { useI18n } from "@/contexts/I18nContext";
import type { Locale } from "@shared/locales/index";

const LOCALE_FLAGS: Record<Locale, string> = { pt: "🇧🇷", en: "🇺🇸", es: "🇪🇸" };
const LOCALE_LABELS: Record<Locale, string> = { pt: "Português", en: "English", es: "Español" };

// Map path to readable page title
const PAGE_TITLES: Record<string, string> = {
  "/":              "Início",
  "/questions":     "Questões",
  "/exams":         "Avaliações",
  "/create-exam":   "Nova Avaliação",
  "/answer-sheets": "Gabaritos",
  "/classes":       "Turmas",
  "/students":      "Alunos",
  "/institutions":  "Instituições",
  "/sharing":       "Compartilhamento",
  "/profile":       "Minha Conta",
  "/faq":           "FAQ / Suporte",
  "/pricing":       "Preços",
  "/quote":         "Solicitar Orçamento",
  "/import-questions": "Importar Questões",
  "/create-question":  "Nova Questão",
};

export default function Navbar() {
  const [location] = useLocation();
  const { locale, setLocale } = useI18n();

  const pageTitle = PAGE_TITLES[location] ?? PAGE_TITLES[Object.keys(PAGE_TITLES).find(k => location.startsWith(k) && k !== "/") ?? ""];

  return (
    <header className="h-14 border-b bg-background shrink-0 flex items-center px-4 gap-3">
      {/* Mobile sidebar trigger */}
      <SidebarTrigger className="h-8 w-8 flex items-center justify-center rounded-md hover:bg-accent md:hidden shrink-0" />

      {/* Page title */}
      <span className="font-medium text-sm text-foreground truncate flex-1 min-w-0">
        {pageTitle ?? "Prof-Aid"}
      </span>

      {/* Language selector */}
      <DropdownMenu>
        <DropdownMenuTrigger asChild>
          <Button variant="ghost" size="sm" className="h-8 px-2 gap-1.5 shrink-0">
            <Globe className="w-4 h-4 text-muted-foreground" />
            <span className="text-sm">{LOCALE_FLAGS[locale]}</span>
            <span className="hidden sm:inline text-xs text-muted-foreground">{LOCALE_LABELS[locale]}</span>
          </Button>
        </DropdownMenuTrigger>
        <DropdownMenuContent align="end" className="w-36">
          {(["pt", "en", "es"] as Locale[]).map((l) => (
            <DropdownMenuItem
              key={l}
              onClick={() => setLocale(l)}
              className={`text-sm cursor-pointer ${locale === l ? "bg-accent font-medium" : ""}`}
            >
              {LOCALE_FLAGS[l]} {LOCALE_LABELS[l]}
            </DropdownMenuItem>
          ))}
        </DropdownMenuContent>
      </DropdownMenu>
    </header>
  );
}
