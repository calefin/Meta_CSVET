import { useAuth } from "@/_core/hooks/useAuth";
import { Avatar, AvatarFallback } from "@/components/ui/avatar";
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";
import {
  Sidebar,
  SidebarContent,
  SidebarFooter,
  SidebarGroup,
  SidebarGroupContent,
  SidebarGroupLabel,
  SidebarHeader,
  SidebarInset,
  SidebarMenu,
  SidebarMenuButton,
  SidebarMenuItem,
  SidebarProvider,
  useSidebar,
  SidebarMenuSub,
  SidebarMenuSubButton,
  SidebarMenuSubItem,
  SidebarSeparator,
} from "@/components/ui/sidebar";
import { getLoginUrl } from "@/const";
import { useIsMobile } from "@/hooks/useMobile";
import { useI18n } from "@/contexts/I18nContext";
import {
  BookOpen, FileText, GraduationCap, Users, Building2, Share2,
  User, HelpCircle, CreditCard, LogOut, PanelLeft, CheckSquare,
  Home, LayoutTemplate, ShieldCheck, PenLine, Zap, ChevronRight,
  ClipboardList, LayoutGrid,
} from "lucide-react";
import { CSSProperties, useEffect, useRef, useState } from "react";
import { useLocation } from "wouter";
import { useActivityLog } from "@/hooks/useActivityLog";
import { DashboardLayoutSkeleton } from "./DashboardLayoutSkeleton";
import Navbar from "./Navbar";
import { Button } from "./ui/button";
import {
  Collapsible,
  CollapsibleContent,
  CollapsibleTrigger,
} from "@/components/ui/collapsible";

const SIDEBAR_WIDTH_KEY = "sidebar-width";
const DEFAULT_WIDTH = 220;
const MIN_WIDTH = 160;
const MAX_WIDTH = 360;

export default function DashboardLayout({ children }: { children: React.ReactNode }) {
  const [sidebarWidth, setSidebarWidth] = useState(() => {
    const saved = localStorage.getItem(SIDEBAR_WIDTH_KEY);
    return saved ? parseInt(saved, 10) : DEFAULT_WIDTH;
  });
  const { loading, user } = useAuth();
  const [, navigate] = useLocation();

  useEffect(() => {
    localStorage.setItem(SIDEBAR_WIDTH_KEY, sidebarWidth.toString());
  }, [sidebarWidth]);

  if (loading) return <DashboardLayoutSkeleton />;

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

  return (
    <SidebarProvider
      style={{ "--sidebar-width": `${sidebarWidth}px` } as CSSProperties}
    >
      <DashboardLayoutContent sidebarWidth={sidebarWidth} setSidebarWidth={setSidebarWidth}>
        {children}
      </DashboardLayoutContent>
    </SidebarProvider>
  );
}

function DashboardLayoutContent({
  children,
  sidebarWidth,
  setSidebarWidth,
}: {
  children: React.ReactNode;
  sidebarWidth: number;
  setSidebarWidth: (w: number) => void;
}) {
  const { user, logout } = useAuth();
  const [location, setLocation] = useLocation();
  const { state, toggleSidebar, setOpenMobile } = useSidebar();
  const { logActivity } = useActivityLog();
  const isCollapsed = state === "collapsed";
  const [isResizing, setIsResizing] = useState(false);
  const isMobile = useIsMobile();
  const { t } = useI18n();

  // Navigate and close the mobile drawer — otherwise it stays open on top of the new page.
  function go(path: string) {
    setLocation(path);
    if (isMobile) setOpenMobile(false);
  }

  useEffect(() => {
    const pathToLabel: Record<string, string> = {
      "/": "Painel Inicial",
      "/gabarito-rapido": "Gabarito Rápido",
      "/questions": "Banco de Questões",
      "/exams": "Avaliações",
      "/exam-templates": "Modelos de Prova",
      "/answer-sheets": "Gabaritos",
      "/classes": "Minhas Turmas",
      "/students": "Listagem de Alunos",
      "/institutions": "Instituições",
      "/sharing": "Links Públicos",
      "/profile": "Meu Perfil",
      "/faq": "Ajuda & FAQ",
      "/pricing": "Planos e Preços",
      "/quote": "Solicitar Orçamento",
      "/admin": "Painel Admin",
      "/import-questions": "Importação de Questões",
    };

    let label = pathToLabel[location];
    if (!label) {
      if (location.startsWith("/exam/") && location.endsWith("/pdf")) {
        label = "Visualização de Prova / PDF";
      } else if (location.startsWith("/create-exam")) {
        label = "Criação de Prova";
      } else if (location.startsWith("/create-question")) {
        label = "Criação de Questão";
      } else if (location.startsWith("/edit-question/")) {
        label = "Edição de Questão";
      } else if (location.startsWith("/student/")) {
        label = "Portal do Aluno";
      } else {
        label = `Acessou ${location}`;
      }
    }

    logActivity(label, location, "navigation");
  }, [location]);

  const mainItems = [
    { icon: Home,          label: "Início",               path: "/" },
    { icon: Zap,           label: "Gabarito Rápido",      path: "/gabarito-rapido" },
    { icon: BookOpen,      label: t("nav.questions"),     path: "/questions" },
    { icon: FileText,      label: t("nav.assessments"),   path: "/exams" },
    { icon: LayoutTemplate,label: "Modelos de Prova",     path: "/exam-templates" },
    { icon: CheckSquare,   label: t("nav.answerSheets"),  path: "/answer-sheets" },
    { icon: GraduationCap, label: t("nav.classes"),       path: "/classes" },
    { icon: Users,         label: t("nav.students"),      path: "/students" },
    { icon: Building2,     label: t("nav.institutions"),  path: "/institutions" },
    { icon: Share2,        label: t("nav.sharing"),       path: "/sharing" },
  ];

  const secondaryItems = [
    { icon: User,        label: t("nav.myAccount"), path: "/profile" },
    { icon: HelpCircle,  label: t("nav.faq"),       path: "/faq" },
    { icon: CreditCard,  label: t("nav.pricing"),   path: "/pricing" },
    { icon: CreditCard,  label: "Assinatura",        path: "/billing" },
    { icon: Building2,   label: t("nav.quote"),     path: "/quote" },
  ];

  const adminItems = user?.role === "admin"
    ? [{ icon: ShieldCheck, label: "Painel Admin", path: "/admin" }]
    : [];

  useEffect(() => {
    if (isCollapsed) setIsResizing(false);
  }, [isCollapsed]);

  useEffect(() => {
    const handleMouseMove = (e: MouseEvent) => {
      if (!isResizing) return;
      const newWidth = e.clientX;
      if (newWidth >= MIN_WIDTH && newWidth <= MAX_WIDTH) setSidebarWidth(newWidth);
    };
    const handleMouseUp = () => setIsResizing(false);
    if (isResizing) {
      document.addEventListener("mousemove", handleMouseMove);
      document.addEventListener("mouseup", handleMouseUp);
      document.body.style.cursor = "col-resize";
      document.body.style.userSelect = "none";
    }
    return () => {
      document.removeEventListener("mousemove", handleMouseMove);
      document.removeEventListener("mouseup", handleMouseUp);
      document.body.style.cursor = "";
      document.body.style.userSelect = "";
    };
  }, [isResizing, setSidebarWidth]);

  function isActive(path: string) {
    if (path === "/") return location === "/";
    return location === path || location.startsWith(path + "/");
  }

  return (
    <>
      <Sidebar collapsible="icon" className="border-r" disableTransition={isResizing}>
        {/* Header */}
        <SidebarHeader className="h-14 border-b flex items-center justify-center px-2">
          <div className="flex items-center gap-2 w-full">
            <button
              onClick={toggleSidebar}
              className="h-8 w-8 flex items-center justify-center hover:bg-accent rounded-md transition-colors shrink-0"
              aria-label="Toggle sidebar"
            >
              <PanelLeft className="h-4 w-4 text-muted-foreground" />
            </button>
            {!isCollapsed && (
              <div className="flex items-center gap-1.5 min-w-0">
                <PenLine className="h-4 w-4 text-primary shrink-0" />
                <span className="font-bold truncate text-sm tracking-tight">Prof-Aid</span>
              </div>
            )}
          </div>
        </SidebarHeader>

        {/* Content — scrollable */}
        <SidebarContent className="overflow-y-auto">
          {/* Main navigation */}
          <SidebarGroup>
            {!isCollapsed && (
              <SidebarGroupLabel className="text-[10px] uppercase tracking-wider px-3 py-2 text-muted-foreground/60">
                Navegação
              </SidebarGroupLabel>
            )}
            <SidebarGroupContent>
              <SidebarMenu>
                {/* 1. Início */}
                <SidebarMenuItem>
                  <SidebarMenuButton
                    isActive={location === "/"}
                    onClick={() => go("/")}
                    tooltip="Início"
                    className="h-9 text-sm"
                  >
                    <Home className="h-4 w-4 shrink-0" />
                    <span className="truncate group-data-[collapsible=icon]:hidden">Início</span>
                  </SidebarMenuButton>
                </SidebarMenuItem>

                {/* 2. Gabarito Rápido */}
                <SidebarMenuItem>
                  <SidebarMenuButton
                    isActive={location === "/gabarito-rapido"}
                    onClick={() => go("/gabarito-rapido")}
                    tooltip="Gabarito Rápido"
                    className="h-9 text-sm"
                  >
                    <Zap className="h-4 w-4 shrink-0" />
                    <span className="truncate group-data-[collapsible=icon]:hidden">Gabarito Rápido</span>
                  </SidebarMenuButton>
                </SidebarMenuItem>

                {/* 3. Avaliações (Collapsible) */}
                <Collapsible
                  asChild
                  className="group/collapsible"
                  defaultOpen={location === "/exams" || location === "/questions" || location === "/exam-templates" || location.startsWith("/create-exam") || location.startsWith("/create-question") || location.startsWith("/edit-question/")}
                >
                  <SidebarMenuItem>
                    <CollapsibleTrigger asChild>
                      <SidebarMenuButton tooltip="Avaliações" className="h-9 text-sm">
                        <FileText className="h-4 w-4 shrink-0" />
                        <span className="truncate group-data-[collapsible=icon]:hidden">Avaliações</span>
                        <ChevronRight className="ml-auto h-4 w-4 transition-transform duration-200 group-data-[state=open]/collapsible:rotate-90 group-data-[collapsible=icon]:hidden" />
                      </SidebarMenuButton>
                    </CollapsibleTrigger>
                    <CollapsibleContent>
                      <SidebarMenuSub>
                        <SidebarMenuSubItem>
                          <SidebarMenuSubButton onClick={() => go("/exams")} isActive={location === "/exams"} className="h-8 text-xs">
                            <span>Minhas Avaliações</span>
                          </SidebarMenuSubButton>
                        </SidebarMenuSubItem>
                        <SidebarMenuSubItem>
                          <SidebarMenuSubButton onClick={() => go("/questions")} isActive={location === "/questions"} className="h-8 text-xs">
                            <span>Banco de Questões</span>
                          </SidebarMenuSubButton>
                        </SidebarMenuSubItem>
                        <SidebarMenuSubItem>
                          <SidebarMenuSubButton onClick={() => go("/exam-templates")} isActive={location === "/exam-templates"} className="h-8 text-xs">
                            <span>Modelos de Prova</span>
                          </SidebarMenuSubButton>
                        </SidebarMenuSubItem>
                      </SidebarMenuSub>
                    </CollapsibleContent>
                  </SidebarMenuItem>
                </Collapsible>

                {/* 4. Minhas Turmas (Collapsible) */}
                <Collapsible
                  asChild
                  className="group/collapsible"
                  defaultOpen={location === "/classes" || location === "/students" || location === "/answer-sheets"}
                >
                  <SidebarMenuItem>
                    <CollapsibleTrigger asChild>
                      <SidebarMenuButton tooltip="Gestão Escolar" className="h-9 text-sm">
                        <GraduationCap className="h-4 w-4 shrink-0" />
                        <span className="truncate group-data-[collapsible=icon]:hidden">Gestão Escolar</span>
                        <ChevronRight className="ml-auto h-4 w-4 transition-transform duration-200 group-data-[state=open]/collapsible:rotate-90 group-data-[collapsible=icon]:hidden" />
                      </SidebarMenuButton>
                    </CollapsibleTrigger>
                    <CollapsibleContent>
                      <SidebarMenuSub>
                        <SidebarMenuSubItem>
                          <SidebarMenuSubButton onClick={() => go("/classes")} isActive={location === "/classes"} className="h-8 text-xs">
                            <span>Minhas Turmas</span>
                          </SidebarMenuSubButton>
                        </SidebarMenuSubItem>
                        <SidebarMenuSubItem>
                          <SidebarMenuSubButton onClick={() => go("/students")} isActive={location === "/students"} className="h-8 text-xs">
                            <span>Listagem de Alunos</span>
                          </SidebarMenuSubButton>
                        </SidebarMenuSubItem>
                        <SidebarMenuSubItem>
                          <SidebarMenuSubButton onClick={() => go("/answer-sheets")} isActive={location === "/answer-sheets"} className="h-8 text-xs">
                            <span>Leitura de Gabaritos</span>
                          </SidebarMenuSubButton>
                        </SidebarMenuSubItem>
                      </SidebarMenuSub>
                    </CollapsibleContent>
                  </SidebarMenuItem>
                </Collapsible>

                {/* 5. Gestão de Alunos — módulo separado (orientandos/TCC), acesso direto via SSO, fora do fluxo de prova */}
                <SidebarMenuItem>
                  <SidebarMenuButton
                    onClick={() => {
                      window.open("/api/gestao/sso", "_blank", "noopener,noreferrer");
                      if (isMobile) setOpenMobile(false);
                    }}
                    tooltip="Gestão de Alunos"
                    className="h-9 text-sm"
                  >
                    <ClipboardList className="h-4 w-4 shrink-0" />
                    <span className="truncate group-data-[collapsible=icon]:hidden">Gestão de Alunos</span>
                  </SidebarMenuButton>
                </SidebarMenuItem>

                {/* 6. Trocar Sistema — reabre a tela de escolha (Provas x Gestão), ?choose=1 ignora a preferência salva */}
                <SidebarMenuItem>
                  <SidebarMenuButton
                    onClick={() => go("/hub?choose=1")}
                    tooltip="Trocar Sistema"
                    className="h-9 text-sm"
                  >
                    <LayoutGrid className="h-4 w-4 shrink-0" />
                    <span className="truncate group-data-[collapsible=icon]:hidden">Trocar Sistema</span>
                  </SidebarMenuButton>
                </SidebarMenuItem>
              </SidebarMenu>
            </SidebarGroupContent>
          </SidebarGroup>

          <SidebarSeparator />

          {/* Secondary navigation */}
          <SidebarGroup>
            {!isCollapsed && (
              <SidebarGroupLabel className="text-[10px] uppercase tracking-wider px-3 py-2 text-muted-foreground/60">
                Configurações
              </SidebarGroupLabel>
            )}
            <SidebarGroupContent>
              <SidebarMenu>
                {/* Configurações & Ajuda (Collapsible) */}
                <Collapsible
                  asChild
                  className="group/collapsible"
                  defaultOpen={location === "/profile" || location === "/institutions" || location === "/sharing" || location === "/pricing" || location === "/billing" || location === "/quote" || location === "/faq"}
                >
                  <SidebarMenuItem>
                    <CollapsibleTrigger asChild>
                      <SidebarMenuButton tooltip="Configurações & Ajuda" className="h-9 text-sm">
                        <User className="h-4 w-4 shrink-0" />
                        <span className="truncate group-data-[collapsible=icon]:hidden">Config. & Ajuda</span>
                        <ChevronRight className="ml-auto h-4 w-4 transition-transform duration-200 group-data-[state=open]/collapsible:rotate-90 group-data-[collapsible=icon]:hidden" />
                      </SidebarMenuButton>
                    </CollapsibleTrigger>
                    <CollapsibleContent>
                      <SidebarMenuSub>
                        <SidebarMenuSubItem>
                          <SidebarMenuSubButton onClick={() => go("/profile")} isActive={location === "/profile"} className="h-8 text-xs">
                            <span>Meu Perfil</span>
                          </SidebarMenuSubButton>
                        </SidebarMenuSubItem>
                        <SidebarMenuSubItem>
                          <SidebarMenuSubButton onClick={() => go("/institutions")} isActive={location === "/institutions"} className="h-8 text-xs">
                            <span>Instituições</span>
                          </SidebarMenuSubButton>
                        </SidebarMenuSubItem>
                        <SidebarMenuSubItem>
                          <SidebarMenuSubButton onClick={() => go("/sharing")} isActive={location === "/sharing"} className="h-8 text-xs">
                            <span>Links Públicos</span>
                          </SidebarMenuSubButton>
                        </SidebarMenuSubItem>
                        <SidebarMenuSubItem>
                          <SidebarMenuSubButton onClick={() => go("/pricing")} isActive={location === "/pricing"} className="h-8 text-xs">
                            <span>Planos & Preços</span>
                          </SidebarMenuSubButton>
                        </SidebarMenuSubItem>
                        <SidebarMenuSubItem>
                          <SidebarMenuSubButton onClick={() => go("/billing")} isActive={location === "/billing"} className="h-8 text-xs">
                            <span>Assinatura (Painel)</span>
                          </SidebarMenuSubButton>
                        </SidebarMenuSubItem>
                        <SidebarMenuSubItem>
                          <SidebarMenuSubButton onClick={() => go("/quote")} isActive={location === "/quote"} className="h-8 text-xs">
                            <span>Solicitar Orçamento</span>
                          </SidebarMenuSubButton>
                        </SidebarMenuSubItem>
                        <SidebarMenuSubItem>
                          <SidebarMenuSubButton onClick={() => go("/faq")} isActive={location === "/faq"} className="h-8 text-xs">
                            <span>Ajuda & FAQ</span>
                          </SidebarMenuSubButton>
                        </SidebarMenuSubItem>
                      </SidebarMenuSub>
                    </CollapsibleContent>
                  </SidebarMenuItem>
                </Collapsible>
              </SidebarMenu>
            </SidebarGroupContent>
          </SidebarGroup>
          {/* Admin navigation */}
          {adminItems.length > 0 && (
            <>
            <SidebarSeparator />
            <SidebarGroup>
              {!isCollapsed && (
                <SidebarGroupLabel className="text-[10px] uppercase tracking-wider px-3 py-2 text-muted-foreground/60">
                  Admin
                </SidebarGroupLabel>
              )}
              <SidebarGroupContent>
                <SidebarMenu>
                  {adminItems.map((item) => (
                    <SidebarMenuItem key={item.path}>
                      <SidebarMenuButton
                        isActive={isActive(item.path)}
                        onClick={() => go(item.path)}
                        tooltip={item.label}
                        className="h-9 text-sm text-primary"
                      >
                        <item.icon className="h-4 w-4 shrink-0" />
                        <span className="truncate group-data-[collapsible=icon]:hidden">{item.label}</span>
                      </SidebarMenuButton>
                    </SidebarMenuItem>
                  ))}
                </SidebarMenu>
              </SidebarGroupContent>
            </SidebarGroup>
            </>
          )}
        </SidebarContent>

        {/* Footer — user info + version */}
        <SidebarFooter className="border-t p-2">
          <DropdownMenu>
            <DropdownMenuTrigger asChild>
              <button className="flex items-center gap-2 rounded-md px-2 py-2 hover:bg-accent/60 transition-colors w-full text-left">
                <Avatar className="h-7 w-7 border shrink-0">
                  <AvatarFallback className="text-xs font-medium">
                    {user?.name?.charAt(0).toUpperCase()}
                  </AvatarFallback>
                </Avatar>
                {!isCollapsed && (
                  <div className="flex-1 min-w-0">
                    <p className="text-xs font-medium truncate leading-none">{user?.name || "-"}</p>
                    <p className="text-[10px] text-muted-foreground truncate mt-1">{user?.email || "-"}</p>
                  </div>
                )}
              </button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" className="w-44">
              <DropdownMenuItem
                onClick={logout}
                className="cursor-pointer text-destructive focus:text-destructive text-sm"
              >
                <LogOut className="mr-2 h-4 w-4" />
                Sair
              </DropdownMenuItem>
            </DropdownMenuContent>
          </DropdownMenu>
          {!isCollapsed && (
            <div className="px-2 pb-0.5">
              <span className="text-[10px] text-muted-foreground/50 select-none">v{__APP_VERSION__}</span>
            </div>
          )}
        </SidebarFooter>
      </Sidebar>

      <SidebarInset className="flex flex-col h-svh overflow-hidden relative">
        {/* Resize handle */}
        {!isCollapsed && (
          <div
            className="fixed top-0 bottom-0 w-1 cursor-col-resize hover:bg-primary/20 transition-colors z-50"
            style={{ left: `${sidebarWidth}px` }}
            onMouseDown={() => setIsResizing(true)}
          />
        )}
        <Navbar />
        <main className="flex-1 overflow-y-auto p-4">{children}</main>
      </SidebarInset>
    </>
  );
}
