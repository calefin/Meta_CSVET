import { Toaster } from "@/components/ui/sonner";
import { TooltipProvider } from "@/components/ui/tooltip";
import NotFound from "@/pages/NotFound";
import { Route, Switch } from "wouter";
import ErrorBoundary from "./components/ErrorBoundary";
import { ThemeProvider } from "./contexts/ThemeContext";
import { LanguageProvider } from "./contexts/LanguageContext";
import { AuthModalProvider } from "./contexts/AuthModalContext";
import Navbar from "./components/Navbar";
import Home from "./pages/Home";
import QuestionBank from "./pages/QuestionBank";
import ExamPage from "./pages/ExamPage";
import Leaderboard from "./pages/Leaderboard";
import Profile from "./pages/Profile";
import AdminPanel from "./pages/AdminPanel";
import Pricing from "./pages/Pricing";
import Dashboard from "./pages/Dashboard";
import PracticeMode from "./pages/PracticeMode";
import CoordinatorPanel from "./pages/CoordinatorPanel";
import TeacherPanel from "./pages/TeacherPanel";
import Announcements from "./pages/Announcements";
import Updates from "./pages/Updates";
import Concursos from "./pages/Concursos";
import ConcursoDetail from "./pages/ConcursoDetail";
import TermsOfUse from "./pages/TermsOfUse";
import PrivacyPolicy from "./pages/PrivacyPolicy";
import TosAcceptModal from "./components/TosAcceptModal";
import TrialOfferModal from "./components/TrialOfferModal";
import PWAInstallBanner from "./components/PWAInstallBanner";
import PaymentSuccess from "./pages/PaymentSuccess";
import PaymentFailure from "./pages/PaymentFailure";
import DiscursiveBank from "./pages/DiscursiveBank";
import Trails from "./pages/Trails";
import TrailDetail from "./pages/TrailDetail";
import Referrals from "./pages/Referrals";
import MySubscription from "./pages/MySubscription";
import AuthGuard from "./components/AuthGuard";
import SubscriptionGuard from "./components/SubscriptionGuard";
import Shop from "./pages/Shop";
import League from "./pages/League";
import Missions from "./pages/Missions";
import Courses from "./pages/Courses";
import CourseDetail from "./pages/CourseDetail";
import CourseStudy from "./pages/CourseStudy";
import VerifyCertificate from "./pages/VerifyCertificate";
import LandingSEO from "./pages/LandingSEO";
import Blog from "./pages/Blog";
import BlogPostDetail from "./pages/BlogPostDetail";
import MistakesInbox from "./pages/MistakesInbox";
import ContentRequest from "./pages/ContentRequest";

function Router() {
  return (
    <Switch>
      <Route path="/" component={Home} />
      <Route path="/bank" component={QuestionBank} />
      <Route path="/pedir-conteudo">{() => <AuthGuard><ContentRequest /></AuthGuard>}</Route>
      <Route path="/exam">{() => <AuthGuard><SubscriptionGuard><ExamPage /></SubscriptionGuard></AuthGuard>}</Route>
      <Route path="/practice">{() => <AuthGuard><SubscriptionGuard><PracticeMode /></SubscriptionGuard></AuthGuard>}</Route>
      <Route path="/caderno-de-erros">{() => <AuthGuard><SubscriptionGuard><MistakesInbox /></SubscriptionGuard></AuthGuard>}</Route>
      <Route path="/leaderboard" component={Leaderboard} />
      <Route path="/profile" component={Profile} />
      <Route path="/dashboard" component={Dashboard} />
      <Route path="/admin" component={AdminPanel} />
      <Route path="/coordinator" component={CoordinatorPanel} />
      <Route path="/teacher" component={TeacherPanel} />
      <Route path="/announcements" component={Announcements} />
      <Route path="/novidades" component={Updates} />
      <Route path="/concursos" component={Concursos} />
      <Route path="/concursos/:slug" component={ConcursoDetail} />
      <Route path="/pricing" component={Pricing} />
      <Route path="/enade">{() => <LandingSEO type="enade" />}</Route>
      <Route path="/enare">{() => <LandingSEO type="enare" />}</Route>
      <Route path="/residencia">{() => <LandingSEO type="residencia" />}</Route>
      <Route path="/blog" component={Blog} />
      <Route path="/blog/:slug" component={BlogPostDetail} />
      <Route path="/terms" component={TermsOfUse} />
      <Route path="/privacy" component={PrivacyPolicy} />
      <Route path="/payment/success" component={PaymentSuccess} />
      <Route path="/payment/failure" component={PaymentFailure} />
      <Route path="/payment/pending" component={PaymentFailure} />
      <Route path="/discursive">{() => <AuthGuard><SubscriptionGuard><DiscursiveBank /></SubscriptionGuard></AuthGuard>}</Route>
      <Route path="/trails">{() => <AuthGuard><SubscriptionGuard><Trails /></SubscriptionGuard></AuthGuard>}</Route>
      <Route path="/trails/:id">{() => <AuthGuard><SubscriptionGuard><TrailDetail /></SubscriptionGuard></AuthGuard>}</Route>
      <Route path="/referrals">{() => <AuthGuard><Referrals /></AuthGuard>}</Route>
      <Route path="/subscription">{() => <AuthGuard><MySubscription /></AuthGuard>}</Route>
      <Route path="/shop">{() => <AuthGuard><Shop /></AuthGuard>}</Route>
      <Route path="/liga">{() => <AuthGuard><League /></AuthGuard>}</Route>
      <Route path="/missions">{() => <AuthGuard><Missions /></AuthGuard>}</Route>
      <Route path="/cursos" component={Courses} />
      <Route path="/cursos/:slug/aula">{() => <AuthGuard><CourseStudy /></AuthGuard>}</Route>
      <Route path="/cursos/:slug" component={CourseDetail} />
      <Route path="/verificar-certificado/:code" component={VerifyCertificate} />
      <Route path="/verificar-certificado" component={VerifyCertificate} />
      <Route path="/404" component={NotFound} />
      <Route component={NotFound} />
    </Switch>
  );
}

function App() {
  return (
    <ErrorBoundary>
      <ThemeProvider defaultTheme="dark" switchable>
        <LanguageProvider>
          <AuthModalProvider>
            <TooltipProvider>
              <Toaster />
              <TosAcceptModal />
              <TrialOfferModal />
              <PWAInstallBanner />
              <div className="min-h-screen bg-background text-foreground flex flex-col">
                <Navbar />
                <main className="flex-1">
                  <Router />
                </main>
                {/* Footer */}
                <footer className="border-t border-border/40 py-6 px-4 mt-auto">
                  <div className="max-w-7xl mx-auto flex flex-col md:flex-row items-center justify-between gap-3 text-xs text-muted-foreground">
                    <span>© 2026 VetRank. Todos os direitos reservados.</span>
                    <div className="flex gap-4">
                      <a href="/terms" className="hover:text-foreground transition-colors">Termos de Uso</a>
                      <a href="/privacy" className="hover:text-foreground transition-colors">Política de Privacidade</a>
                      <a href="/novidades" className="hover:text-foreground transition-colors">Novidades</a>
                      <a href="/concursos" className="hover:text-foreground transition-colors">Concursos</a>
                    </div>
                  </div>
                </footer>
              </div>
            </TooltipProvider>
          </AuthModalProvider>
        </LanguageProvider>
      </ThemeProvider>
    </ErrorBoundary>
  );
}

export default App;
