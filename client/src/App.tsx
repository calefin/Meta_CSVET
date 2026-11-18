import { Toaster } from "@/components/ui/sonner";
import { TooltipProvider } from "@/components/ui/tooltip";
import NotFound from "@/pages/NotFound";
import { Route, Switch } from "wouter";
import ErrorBoundary from "./components/ErrorBoundary";
import { ThemeProvider } from "./contexts/ThemeContext";
import { I18nProvider } from "./contexts/I18nContext";
import Home from "./pages/Home";
import Profile from "./pages/Profile";
import ImportQuestions from "./pages/ImportQuestions";
import QuestionBank from "./pages/QuestionBank";
import CreateExam from "./pages/CreateExam";
import ExamsList from "./pages/ExamsList";
import DashboardLayout from "./components/DashboardLayout";
import ExamPDF from "./pages/ExamPDF";
import AnswerSheetUpload from "./pages/AnswerSheetUpload";
import AnswerSheets from "./pages/AnswerSheets";
import Login from "./pages/Login";
import Signup from "./pages/Signup";
import SystemHub from "./pages/SystemHub";
import { CreateQuestion } from "./pages/CreateQuestion";
import { EditQuestion } from "./pages/EditQuestion";
import Institutions from "./pages/Institutions";
import Classes from "./pages/Classes";
import Students from "./pages/Students";
import FAQ from "./pages/FAQ";
import Pricing from "./pages/Pricing";
import Sharing from "./pages/Sharing";
import QuizPublic from "./pages/QuizPublic";
import StudentPortal from "./pages/StudentPortal";
import ExamTemplates from "./pages/ExamTemplates";
import Admin from "./pages/Admin";
import ExamAnalysis from "./pages/ExamAnalysis";
import EssayGrading from "./pages/EssayGrading";
import StudentReport from "./pages/StudentReport";
import Quote from "./pages/Quote";
import GabaritoRapido from "./pages/GabaritoRapido";
import BillingPortal from "./pages/BillingPortal";
import BlogList from "./pages/BlogList";
import BlogPost from "./pages/BlogPost";
import Privacy from "./pages/Privacy";
import Terms from "./pages/Terms";

function Router() {
  return (
    <Switch>
      <Route path="/">
        {() => <Home />}
      </Route>
      <Route path="/login" component={Login} />
      <Route path="/signup" component={Signup} />
      <Route path="/hub" component={SystemHub} />

      {/* Public quiz route — no layout */}
      <Route path="/q/:token">
        {({ params }: any) => <QuizPublic token={params?.token ?? ""} />}
      </Route>

      {/* Public student portal route — no layout */}
      <Route path="/student/:token">
        {({ params }: any) => <StudentPortal token={params?.token ?? ""} />}
      </Route>

      {/* Public legal pages — no layout, no auth required */}
      <Route path="/privacy" component={Privacy} />
      <Route path="/terms" component={Terms} />

      <Route path="/profile">
        {() => (
          <DashboardLayout>
            <Profile />
          </DashboardLayout>
        )}
      </Route>

      <Route path="/import-questions">
        {() => (
          <DashboardLayout>
            <ImportQuestions />
          </DashboardLayout>
        )}
      </Route>

      <Route path="/question-bank">
        {() => (
          <DashboardLayout>
            <QuestionBank />
          </DashboardLayout>
        )}
      </Route>

      <Route path="/questions">
        {() => (
          <DashboardLayout>
            <QuestionBank />
          </DashboardLayout>
        )}
      </Route>

      <Route path="/create-question">
        {() => (
          <DashboardLayout>
            <CreateQuestion />
          </DashboardLayout>
        )}
      </Route>

      <Route path="/questions/:id/edit">
        {(routeParams: any) => (
          <DashboardLayout>
            <EditQuestion questionId={parseInt(routeParams.id)} />
          </DashboardLayout>
        )}
      </Route>

      <Route path="/create-exam">
        {() => (
          <DashboardLayout>
            <CreateExam />
          </DashboardLayout>
        )}
      </Route>

      <Route path="/gabarito-rapido">
        {() => (
          <DashboardLayout>
            <GabaritoRapido />
          </DashboardLayout>
        )}
      </Route>

      <Route path="/exams">
        {() => (
          <DashboardLayout>
            <ExamsList />
          </DashboardLayout>
        )}
      </Route>

      <Route path="/exam/:id/pdf">
        {() => (
          <DashboardLayout>
            <ExamPDF />
          </DashboardLayout>
        )}
      </Route>

      <Route path="/exam/:id/answer-sheet">
        {() => (
          <DashboardLayout>
            <AnswerSheetUpload />
          </DashboardLayout>
        )}
      </Route>

      <Route path="/exam/:id/analysis">
        {() => (
          <DashboardLayout>
            <ExamAnalysis />
          </DashboardLayout>
        )}
      </Route>

      <Route path="/exam/:id/essay-grading">
        {() => (
          <DashboardLayout>
            <EssayGrading />
          </DashboardLayout>
        )}
      </Route>

      {/* Student report — standalone printable page, no layout */}
      <Route path="/student-report/:id">
        {() => <StudentReport />}
      </Route>

      <Route path="/answer-sheets">
        {() => (
          <DashboardLayout>
            <AnswerSheets />
          </DashboardLayout>
        )}
      </Route>

      <Route path="/institutions">
        {() => (
          <DashboardLayout>
            <Institutions />
          </DashboardLayout>
        )}
      </Route>

      <Route path="/classes">
        {() => (
          <DashboardLayout>
            <Classes />
          </DashboardLayout>
        )}
      </Route>

      <Route path="/students">
        {() => (
          <DashboardLayout>
            <Students />
          </DashboardLayout>
        )}
      </Route>

      <Route path="/exam-templates">
        {() => (
          <DashboardLayout>
            <ExamTemplates />
          </DashboardLayout>
        )}
      </Route>

      <Route path="/sharing">
        {() => (
          <DashboardLayout>
            <Sharing />
          </DashboardLayout>
        )}
      </Route>

      <Route path="/faq">
        {() => (
          <DashboardLayout>
            <FAQ />
          </DashboardLayout>
        )}
      </Route>

      <Route path="/pricing">
        {() => (
          <DashboardLayout>
            <Pricing />
          </DashboardLayout>
        )}
      </Route>

      <Route path="/billing">
        {() => (
          <DashboardLayout>
            <BillingPortal />
          </DashboardLayout>
        )}
      </Route>

      <Route path="/blog">
        {() => (
          <DashboardLayout>
            <BlogList />
          </DashboardLayout>
        )}
      </Route>

      <Route path="/blog/:slug">
        {(params) => (
          <DashboardLayout>
            <BlogPost />
          </DashboardLayout>
        )}
      </Route>

      <Route path="/quote">
        {() => (
          <DashboardLayout>
            <Quote />
          </DashboardLayout>
        )}
      </Route>

      <Route path="/admin">
        {() => (
          <DashboardLayout>
            <Admin />
          </DashboardLayout>
        )}
      </Route>

      <Route path="/404" component={NotFound} />
      <Route component={NotFound} />
    </Switch>
  );
}

function App() {
  return (
    <ErrorBoundary>
      <I18nProvider>
        <ThemeProvider defaultTheme="light">
          <TooltipProvider>
            <Toaster />
            <Router />
          </TooltipProvider>
        </ThemeProvider>
      </I18nProvider>
    </ErrorBoundary>
  );
}

export default App;
