import { Routes, Route, Navigate } from 'react-router-dom'
import { useAuthStore } from '@/store/auth'
import Layout from '@/components/Layout'
import LandingPage from '@/pages/LandingPage'
import LoginPage from '@/pages/LoginPage'
import RegisterPage from '@/pages/RegisterPage'
import TermsPage from '@/pages/TermsPage'
import PrivacyPage from '@/pages/PrivacyPage'
import DashboardPage from '@/pages/breeder/DashboardPage'
import BirdsPage from '@/pages/breeder/BirdsPage'
import VaccinesPage from '@/pages/breeder/VaccinesPage'
import BreedingPage from '@/pages/breeder/BreedingPage'
import BreedingDetailPage from '@/pages/breeder/BreedingDetailPage'
import CagesPage from '@/pages/breeder/CagesPage'
import HealthPage from '@/pages/breeder/HealthPage'
import GeneticsPage from '@/pages/breeder/GeneticsPage'
import RacesPage from '@/pages/breeder/RacesPage'
import PedigreePage from '@/pages/breeder/PedigreePage'
import ProfilePage from '@/pages/breeder/ProfilePage'
import RingsPage from '@/pages/breeder/RingsPage'
import MedicationPage from '@/pages/breeder/MedicationPage'
import FeedPage from '@/pages/breeder/FeedPage'
import VetQueuePage from '@/pages/vet/VetQueuePage'
import AdminDashboardPage from '@/pages/admin/AdminDashboardPage'
import AdminCampaignsPage from '@/pages/admin/AdminCampaignsPage'

function PrivateRoute({ children }: { children: React.ReactNode }) {
  const { isAuthenticated } = useAuthStore()
  return isAuthenticated ? <>{children}</> : <Navigate to="/login" replace />
}

function RoleRoute({ children, roles }: { children: React.ReactNode; roles: string[] }) {
  const { user } = useAuthStore()
  if (!user || !roles.includes(user.role)) return <Navigate to="/app/dashboard" replace />
  return <>{children}</>
}

export default function AppRouter() {
  return (
    <Routes>
      <Route path="/" element={<LandingPage />} />
      <Route path="/login" element={<LoginPage />} />
      <Route path="/register" element={<RegisterPage />} />
      <Route path="/termos" element={<TermsPage />} />
      <Route path="/privacidade" element={<PrivacyPage />} />
      <Route
        path="/app"
        element={
          <PrivateRoute>
            <Layout />
          </PrivateRoute>
        }
      >
        <Route index element={<Navigate to="/app/dashboard" replace />} />
        <Route path="dashboard" element={<DashboardPage />} />
        <Route path="birds" element={<BirdsPage />} />
        <Route path="vaccines" element={<VaccinesPage />} />
        <Route path="breeding" element={<BreedingPage />} />
        <Route path="breeding/:id" element={<BreedingDetailPage />} />
        <Route path="cages" element={<CagesPage />} />
        <Route path="health" element={<HealthPage />} />
        <Route path="genetics" element={<GeneticsPage />} />
        <Route path="races" element={<RacesPage />} />
        <Route path="pedigree" element={<PedigreePage />} />
        <Route path="profile" element={<ProfilePage />} />
        <Route path="rings" element={<RingsPage />} />
        <Route path="medications" element={<MedicationPage />} />
        <Route path="feed" element={<FeedPage />} />

        <Route
          path="vet"
          element={
            <RoleRoute roles={['vet', 'admin']}>
              <VetQueuePage />
            </RoleRoute>
          }
        />
        <Route
          path="admin"
          element={
            <RoleRoute roles={['admin']}>
              <AdminDashboardPage />
            </RoleRoute>
          }
        />
        <Route
          path="admin/campaigns"
          element={
            <RoleRoute roles={['admin']}>
              <AdminCampaignsPage />
            </RoleRoute>
          }
        />
      </Route>
      <Route path="*" element={<Navigate to="/" replace />} />
    </Routes>
  )
}
