import { useAuth } from "@/_core/hooks/useAuth";
import VetRankLogo from "@/components/VetRankLogo";
import { useAuthModal } from "@/contexts/AuthModalContext";
import { Button } from "@/components/ui/button";
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";
import { useLanguage, useT } from "@/contexts/LanguageContext";
import { useTheme } from "@/contexts/ThemeContext";
import {
  BookOpen,
  ChevronDown,
  Crown,
  FlaskConical,
  Globe,
  LayoutDashboard,
  AlertTriangle,
  LogOut,
  Medal,
  Menu,
  MessageSquare,
  Moon,
  Pencil,
  ShoppingBag,
  Sun,
  Target,
  Trophy,
  User,
  X,
  GraduationCap,
  Dumbbell,
  Sparkles,
  Landmark,
  PenLine,
  CreditCard,
  Gift,
  Route,
  Newspaper,
  Smartphone,
} from "lucide-react";
import SupportTicketForm from "@/components/SupportTicketForm";
import ProfileEditForm from "@/components/ProfileEditForm";
import { useState, useEffect } from "react";
import { Link, useLocation } from "wouter";
import { trpc } from "@/lib/trpc";
import { toast } from "sonner";

export default function Navbar() {
  const { user, isAuthenticated, logout } = useAuth();
  const [supportOpen, setSupportOpen] = useState(false);
  const [profileEditOpen, setProfileEditOpen] = useState(false);
  const { openLogin } = useAuthModal();
  const { language, setLanguage } = useLanguage();
  const { theme, toggleTheme } = useTheme();
  const t = useT();
  const [location] = useLocation();
  const [mobileOpen, setMobileOpen] = useState(false);

  const utils = trpc.useUtils();
  const dailyLogin = trpc.gamification.dailyLogin.useMutation();

  useEffect(() => {
    if (isAuthenticated) {
      dailyLogin.mutate(undefined, {
        onSuccess: (data) => {
          if (data.xpBonus > 0) {
            toast.success(`🔥 Sequência de ${data.streak} dias! +${data.xpBonus} XP`);
            utils.gamification.myCoins.invalidate();
          }
        },
      });
    }
  }, [isAuthenticated]);

  // Gamification stats para o navbar (somente alunos — roles staff não acumulam XP)
  const isStudent = user?.role === "user";
  const isPremium = user?.plan === "premium";
  const isTrial = user?.plan === "trial";
  const { data: stats } = trpc.gamification.myCoins.useQuery(undefined, {
    enabled: isAuthenticated && isStudent,
    staleTime: 60_000,
  });

  // Links da nav principal — compacto para não transbordar
  const navLinks = [
    { href: "/missions",    label: "Missões",             icon: Target     },
    { href: "/bank",        label: t("nav_bank"),         icon: BookOpen   },
    { href: "/practice",    label: "Praticar",            icon: Dumbbell   },
    { href: "/exam",        label: t("nav_exam"),         icon: FlaskConical },
    { href: "/leaderboard", label: t("nav_leaderboard"),  icon: Trophy     },
    { href: "/discursive",  label: "Discursivas",         icon: PenLine    },
    { href: "/trails",      label: "Trilhas",             icon: Route      },
    { href: "/cursos",      label: "Cursos",              icon: GraduationCap },
    { href: "/blog",        label: "Blog",                icon: Newspaper  },
    { href: "/concursos",   label: "Concursos",         icon: Landmark   },
    { href: "/novidades",   label: "Novidades",         icon: Sparkles   },
    { href: "/pricing",     label: t("nav_pricing"),      icon: Crown      },
  ];

  // Links de role — ficam somente no dropdown do usuário (não na barra desktop)
  const roleLinks: { href: string; label: string; icon: any }[] = [];
  if (user?.role === "admin" || user?.role === "superuser") {
    roleLinks.push({ href: "/admin",       label: t("nav_admin"),   icon: LayoutDashboard });
    roleLinks.push({ href: "/coordinator", label: "Coordenador",    icon: GraduationCap   });
    roleLinks.push({ href: "/teacher",     label: "Professor",      icon: BookOpen        });
  } else if (user?.role === "coordinator") {
    roleLinks.push({ href: "/coordinator", label: "Coordenador",    icon: GraduationCap   });
    roleLinks.push({ href: "/teacher",     label: "Professor",      icon: BookOpen        });
  } else if (user?.role === "teacher") {
    roleLinks.push({ href: "/teacher",     label: "Painel Prof.",   icon: BookOpen        });
  }

  const isActive = (href: string) => location === href || location.startsWith(href + "/");

  return (
    <>
    <nav className="sticky top-0 z-50 border-b border-border/50 bg-background/80 backdrop-blur-xl">
      <div className="container">
        <div className="flex h-16 items-center justify-between">

          {/* Logo */}
          <Link href="/" className="flex items-center shrink-0 mr-2">
            <VetRankLogo className="h-8 w-auto" compact />
          </Link>

          {/* Desktop Nav */}
          <div className="hidden xl:flex items-center gap-0.5 overflow-x-auto flex-1">
            {/* Questões Group Dropdown */}
            <DropdownMenu>
              <DropdownMenuTrigger asChild>
                <Button
                  variant="ghost"
                  className={`flex items-center gap-1 px-2 py-2 h-auto text-xs font-medium transition-colors whitespace-nowrap rounded-lg text-muted-foreground hover:text-foreground hover:bg-accent ${
                    isActive("/bank") || isActive("/practice") || isActive("/discursive") || isActive("/exam")
                      ? "bg-primary/15 text-primary"
                      : ""
                  }`}
                >
                  <BookOpen className="h-3.5 w-3.5 shrink-0" />
                  Questões
                  <ChevronDown className="h-3 w-3 shrink-0 opacity-50" />
                </Button>
              </DropdownMenuTrigger>
              <DropdownMenuContent align="start" className="w-48">
                <DropdownMenuItem asChild>
                  <Link href="/bank" className="flex items-center gap-2 w-full">
                    <BookOpen className="h-4 w-4" /> {t("nav_bank")}
                  </Link>
                </DropdownMenuItem>
                <DropdownMenuItem asChild>
                  <Link href="/practice" className="flex items-center gap-2 w-full">
                    <Dumbbell className="h-4 w-4" /> Praticar
                  </Link>
                </DropdownMenuItem>
                <DropdownMenuItem asChild>
                  <Link href="/discursive" className="flex items-center gap-2 w-full">
                    <PenLine className="h-4 w-4" /> Discursivas
                  </Link>
                </DropdownMenuItem>
                <DropdownMenuItem asChild>
                  <Link href="/exam" className="flex items-center gap-2 w-full">
                    <FlaskConical className="h-4 w-4" /> {t("nav_exam")}
                  </Link>
                </DropdownMenuItem>
              </DropdownMenuContent>
            </DropdownMenu>

            {/* Direct Links */}
            <Link
              href="/trails"
              className={`flex items-center gap-1 px-2 py-2 rounded-lg text-xs font-medium transition-colors whitespace-nowrap ${
                isActive("/trails")
                  ? "bg-primary/15 text-primary"
                  : "text-muted-foreground hover:text-foreground hover:bg-accent"
              }`}
            >
              <Route className="h-3.5 w-3.5 shrink-0" />
              Trilhas
            </Link>

            <Link
              href="/cursos"
              className={`flex items-center gap-1 px-2 py-2 rounded-lg text-xs font-medium transition-colors whitespace-nowrap ${
                isActive("/cursos")
                  ? "bg-primary/15 text-primary"
                  : "text-muted-foreground hover:text-foreground hover:bg-accent"
              }`}
            >
              <GraduationCap className="h-3.5 w-3.5 shrink-0" />
              Cursos
            </Link>

            {/* Ranking Group Dropdown */}
            <DropdownMenu>
              <DropdownMenuTrigger asChild>
                <Button
                  variant="ghost"
                  className={`flex items-center gap-1 px-2 py-2 h-auto text-xs font-medium transition-colors whitespace-nowrap rounded-lg text-muted-foreground hover:text-foreground hover:bg-accent ${
                    isActive("/leaderboard") || isActive("/missions")
                      ? "bg-primary/15 text-primary"
                      : ""
                  }`}
                >
                  <Trophy className="h-3.5 w-3.5 shrink-0" />
                  Ranking
                  <ChevronDown className="h-3 w-3 shrink-0 opacity-50" />
                </Button>
              </DropdownMenuTrigger>
              <DropdownMenuContent align="start" className="w-48">
                <DropdownMenuItem asChild>
                  <Link href="/leaderboard" className="flex items-center gap-2 w-full">
                    <Trophy className="h-4 w-4" /> {t("nav_leaderboard")}
                  </Link>
                </DropdownMenuItem>
                <DropdownMenuItem asChild>
                  <Link href="/missions" className="flex items-center gap-2 w-full">
                    <Target className="h-4 w-4" /> Missões Diárias
                  </Link>
                </DropdownMenuItem>
              </DropdownMenuContent>
            </DropdownMenu>

            {/* Direct Links Continued */}
            <Link
              href="/concursos"
              className={`flex items-center gap-1 px-2 py-2 rounded-lg text-xs font-medium transition-colors whitespace-nowrap ${
                isActive("/concursos")
                  ? "bg-primary/15 text-primary"
                  : "text-muted-foreground hover:text-foreground hover:bg-accent"
              }`}
            >
              <Landmark className="h-3.5 w-3.5 shrink-0" />
              Concursos
            </Link>

            <Link
              href="/blog"
              className={`flex items-center gap-1 px-2 py-2 rounded-lg text-xs font-medium transition-colors whitespace-nowrap ${
                isActive("/blog")
                  ? "bg-primary/15 text-primary"
                  : "text-muted-foreground hover:text-foreground hover:bg-accent"
              }`}
            >
              <Newspaper className="h-3.5 w-3.5 shrink-0" />
              Blog
            </Link>

            <Link
              href="/novidades"
              className={`flex items-center gap-1 px-2 py-2 rounded-lg text-xs font-medium transition-colors whitespace-nowrap ${
                isActive("/novidades")
                  ? "bg-primary/15 text-primary"
                  : "text-muted-foreground hover:text-foreground hover:bg-accent"
              }`}
            >
              <Sparkles className="h-3.5 w-3.5 shrink-0" />
              Novidades
            </Link>

            <Link
              href="/pricing"
              className={`flex items-center gap-1 px-2 py-2 rounded-lg text-xs font-medium transition-colors whitespace-nowrap ${
                isActive("/pricing")
                  ? "bg-primary/15 text-primary"
                  : "text-muted-foreground hover:text-foreground hover:bg-accent"
              }`}
            >
              <Crown className="h-3.5 w-3.5 shrink-0" />
              {t("nav_pricing")}
            </Link>
          </div>

          {/* Right side */}
          <div className="flex items-center gap-1 shrink-0">
            {/* Streak + XP — só alunos, só desktop */}
            {isAuthenticated && isStudent && stats && (
              <div className="hidden md:flex items-center gap-2 mr-1">
                {stats.streak > 0 && (
                  <div className="flex items-center gap-1 px-2 py-1 rounded-lg bg-orange-500/10 border border-orange-500/20">
                    <span className="text-sm">🔥</span>
                    <span className="text-xs font-bold text-orange-400">{stats.streak}</span>
                  </div>
                )}
                <div className="flex items-center gap-1 px-2 py-1 rounded-lg bg-primary/10 border border-primary/20">
                  <span className="text-sm">⚡</span>
                  <span className="text-xs font-bold text-primary">
                    {stats.xp >= 1000 ? `${(stats.xp / 1000).toFixed(1)}k` : stats.xp} XP
                  </span>
                </div>
              </div>
            )}

            {/* Theme toggle */}
            <Button
              variant="ghost"
              size="sm"
              onClick={toggleTheme}
              className="text-muted-foreground hover:text-foreground px-2"
              title={theme === "dark" ? "Modo claro" : "Modo escuro"}
            >
              {theme === "dark" ? <Sun className="h-4 w-4" /> : <Moon className="h-4 w-4" />}
            </Button>

            {/* Language selector */}
            <DropdownMenu>
              <DropdownMenuTrigger asChild>
                <Button variant="ghost" size="sm" className="gap-1.5 text-muted-foreground hover:text-foreground px-2">
                  <Globe className="h-4 w-4" />
                  <span className="text-xs font-medium uppercase hidden sm:inline">{language}</span>
                </Button>
              </DropdownMenuTrigger>
              <DropdownMenuContent align="end" className="w-32">
                <DropdownMenuItem onClick={() => setLanguage("pt")} className={language === "pt" ? "text-primary" : ""}>
                  🇧🇷 Português
                </DropdownMenuItem>
                <DropdownMenuItem onClick={() => setLanguage("en")} className={language === "en" ? "text-primary" : ""}>
                  🇺🇸 English
                </DropdownMenuItem>
              </DropdownMenuContent>
            </DropdownMenu>

            {/* Auth */}
            {isAuthenticated ? (
              <DropdownMenu>
                <DropdownMenuTrigger asChild>
                  <Button variant="ghost" size="sm" className="gap-2 px-2">
                    <div className="flex h-7 w-7 items-center justify-center rounded-full bg-primary/20 border border-primary/30 overflow-hidden shrink-0">
                      {(user as any)?.avatarUrl ? (
                        <img src={(user as any).avatarUrl} alt="avatar" className="w-full h-full object-cover" />
                      ) : (
                        <User className="h-4 w-4 text-primary" />
                      )}
                    </div>
                    <span className="hidden sm:block text-sm max-w-24 truncate">{user?.name?.split(" ")[0]}</span>
                    {isPremium && (
                      <span className="hidden sm:flex items-center gap-0.5 text-[10px] font-bold px-1.5 py-0.5 rounded-full bg-amber-500/20 text-amber-400 border border-amber-500/30 shrink-0">
                        <Crown className="h-2.5 w-2.5" />PRO
                      </span>
                    )}
                    {isTrial && (
                      <span className="hidden sm:flex text-[10px] font-bold px-1.5 py-0.5 rounded-full bg-violet-500/20 text-violet-400 border border-violet-500/30 shrink-0">
                        Trial
                      </span>
                    )}
                    {!isPremium && !isTrial && isAuthenticated && (
                      <span className="hidden sm:flex text-[10px] font-bold px-1.5 py-0.5 rounded-full bg-muted text-muted-foreground border border-border shrink-0">
                        Free
                      </span>
                    )}
                    <ChevronDown className="h-3 w-3" />
                  </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="end" className="w-56">
                  {/* Plano do usuário */}
                  <div className="px-3 py-2 flex items-center justify-between">
                    {isPremium && (
                      <span className="flex items-center gap-1 text-xs font-bold text-amber-400">
                        <Crown className="h-3 w-3" />Plano Premium
                      </span>
                    )}
                    {isTrial && (
                      <span className="text-xs font-bold text-violet-400">Período de Trial</span>
                    )}
                    {!isPremium && !isTrial && (
                      <span className="text-xs text-muted-foreground">Plano Free</span>
                    )}
                    {!isPremium && isStudent && (
                      <Link href="/pricing" className="text-xs font-medium text-primary hover:underline">
                        Fazer upgrade
                      </Link>
                    )}
                  </div>
                  <DropdownMenuSeparator />

                  {/* Gamification stats no topo do dropdown (mobile) */}
                  {isStudent && stats && (
                    <>
                      <div className="flex items-center gap-3 px-3 py-2 md:hidden">
                        {stats.streak > 0 && (
                          <span className="text-xs font-bold text-orange-400">🔥 {stats.streak} dias</span>
                        )}
                        <span className="text-xs font-bold text-primary">⚡ {stats.xp.toLocaleString()} XP</span>
                        <span className="text-xs text-muted-foreground">Nível {stats.level}</span>
                      </div>
                      <DropdownMenuSeparator className="md:hidden" />
                    </>
                  )}

                  {isStudent && (
                    <DropdownMenuItem asChild>
                      <Link href="/missions" className="flex items-center gap-2">
                        <Target className="h-4 w-4" />
                        Missões Diárias
                      </Link>
                    </DropdownMenuItem>
                  )}
                  <DropdownMenuItem asChild>
                    <Link href="/dashboard" className="flex items-center gap-2">
                      <LayoutDashboard className="h-4 w-4" />
                      Meu Desempenho
                    </Link>
                  </DropdownMenuItem>
                  <DropdownMenuItem asChild>
                    <Link href="/profile" className="flex items-center gap-2">
                      <User className="h-4 w-4" />
                      {t("nav_profile")}
                    </Link>
                  </DropdownMenuItem>
                  <DropdownMenuItem onClick={() => setProfileEditOpen(true)} className="flex items-center gap-2">
                    <Pencil className="h-4 w-4" />
                    Editar Perfil
                  </DropdownMenuItem>

                  {/* Liga e Loja no dropdown */}
                  {isStudent && (
                    <>
                      <DropdownMenuSeparator />
                      <DropdownMenuItem asChild>
                        <Link href="/liga" className="flex items-center gap-2">
                          <Medal className="h-4 w-4" />
                          Liga Semanal
                        </Link>
                      </DropdownMenuItem>
                      <DropdownMenuItem asChild>
                        <Link href="/shop" className="flex items-center gap-2">
                          <ShoppingBag className="h-4 w-4" />
                          Loja VetCoins
                        </Link>
                      </DropdownMenuItem>
                      <DropdownMenuItem asChild>
                        <Link href="/caderno-de-erros" className="flex items-center gap-2">
                          <AlertTriangle className="h-4.5 w-4.5 text-rose-500" />
                          Caderno de Erros
                        </Link>
                      </DropdownMenuItem>
                    </>
                  )}

                  <DropdownMenuSeparator />
                  <DropdownMenuItem asChild>
                    <Link href="/referrals" className="flex items-center gap-2">
                      <Gift className="h-4 w-4" />
                      Indicações
                    </Link>
                  </DropdownMenuItem>
                  <DropdownMenuItem asChild>
                    <Link href="/subscription" className="flex items-center gap-2">
                      <CreditCard className="h-4 w-4" />
                      Minha Assinatura
                    </Link>
                  </DropdownMenuItem>

                  {/* Role links no dropdown */}
                  {roleLinks.length > 0 && (
                    <>
                      <DropdownMenuSeparator />
                      {roleLinks.map((link) => (
                        <DropdownMenuItem key={link.href} asChild>
                          <Link href={link.href} className="flex items-center gap-2">
                            <link.icon className="h-4 w-4" />
                            {link.label}
                          </Link>
                        </DropdownMenuItem>
                      ))}
                    </>
                  )}

                  <DropdownMenuSeparator />
                  <DropdownMenuItem onClick={() => setSupportOpen(true)} className="text-muted-foreground">
                    <MessageSquare className="h-4 w-4 mr-2" />
                    Suporte / Chamado
                  </DropdownMenuItem>
                  <DropdownMenuSeparator />
                  <DropdownMenuItem onClick={logout} className="text-destructive focus:text-destructive">
                    <LogOut className="h-4 w-4 mr-2" />
                    {t("nav_logout")}
                  </DropdownMenuItem>
                </DropdownMenuContent>
              </DropdownMenu>
            ) : (
              <Button
                size="sm"
                className="bg-primary text-primary-foreground hover:bg-primary/90 glow-primary"
                onClick={() => openLogin()}
              >
                {t("nav_login")}
              </Button>
            )}

            {/* Mobile menu button */}
            <Button
              variant="ghost"
              size="sm"
              className="xl:hidden px-2"
              onClick={() => setMobileOpen(!mobileOpen)}
            >
              {mobileOpen ? <X className="h-5 w-5" /> : <Menu className="h-5 w-5" />}
            </Button>
          </div>
        </div>

        {/* Mobile menu */}
        {mobileOpen && (
          <div className="xl:hidden border-t border-border/50 py-3 space-y-1 max-h-[calc(100vh-5rem)] overflow-y-auto">
            {navLinks.map((link) => (
              <Link
                key={link.href}
                href={link.href}
                onClick={() => setMobileOpen(false)}
                className={`flex items-center gap-2 px-3 py-2.5 rounded-lg text-sm font-medium transition-colors ${
                  isActive(link.href)
                    ? "bg-primary/15 text-primary"
                    : "text-muted-foreground hover:text-foreground hover:bg-accent"
                }`}
              >
                <link.icon className="h-4 w-4" />
                {link.label}
              </Link>
            ))}
            {isAuthenticated && (
              <>
                <Link href="/dashboard" onClick={() => setMobileOpen(false)} className="flex items-center gap-2 px-3 py-2.5 rounded-lg text-sm font-medium text-muted-foreground hover:text-foreground hover:bg-accent">
                  <LayoutDashboard className="h-4 w-4" />Meu Desempenho
                </Link>
                <Link href="/profile" onClick={() => setMobileOpen(false)} className="flex items-center gap-2 px-3 py-2.5 rounded-lg text-sm font-medium text-muted-foreground hover:text-foreground hover:bg-accent">
                  <User className="h-4 w-4" />{t("nav_profile")}
                </Link>
                {isStudent && <>
                  <Link href="/liga" onClick={() => setMobileOpen(false)} className="flex items-center gap-2 px-3 py-2.5 rounded-lg text-sm font-medium text-muted-foreground hover:text-foreground hover:bg-accent">
                    <Medal className="h-4 w-4" />Liga Semanal
                  </Link>
                  <Link href="/shop" onClick={() => setMobileOpen(false)} className="flex items-center gap-2 px-3 py-2.5 rounded-lg text-sm font-medium text-muted-foreground hover:text-foreground hover:bg-accent">
                    <ShoppingBag className="h-4 w-4" />Loja VetCoins
                  </Link>
                </>}
                {roleLinks.map((link) => (
                  <Link key={link.href} href={link.href} onClick={() => setMobileOpen(false)} className="flex items-center gap-2 px-3 py-2.5 rounded-lg text-sm font-medium text-muted-foreground hover:text-foreground hover:bg-accent">
                    <link.icon className="h-4 w-4" />{link.label}
                  </Link>
                ))}
              </>
            )}

            {/* PWA Installer Button */}
            <button
              onClick={() => {
                setMobileOpen(false);
                window.dispatchEvent(new CustomEvent("trigger-pwa-install"));
              }}
              className="flex items-center gap-2 w-[calc(100%-1.5rem)] mx-3 my-3 px-3 py-2.5 rounded-lg text-sm font-semibold text-primary bg-primary/10 hover:bg-primary/20 transition-all cursor-pointer border border-primary/20"
            >
              <Smartphone className="h-4 w-4" />
              Instalar Aplicativo (PWA)
            </button>
          </div>
        )}
      </div>
    </nav>

    <SupportTicketForm open={supportOpen} onOpenChange={setSupportOpen} />
    <ProfileEditForm open={profileEditOpen} onOpenChange={setProfileEditOpen} />
    </>
  );
}
