import { useEffect } from 'react'
import { Navigate, Outlet, Route, Routes, useParams } from 'react-router-dom'
import { AppLayout } from '@/components/app-layout'
import { useClinic } from '@/state/clinic-store'
import { AdminPage } from '@/pages/admin-page'
import { AppointmentHistoryPage, ReschedulePage } from '@/pages/appointment-detail-page'
import { AppointmentsPage } from '@/pages/appointments-page'
import { BookingPage } from '@/pages/booking-page'
import { DashboardPage } from '@/pages/dashboard-page'
import { LoginPage } from '@/pages/login-page'
import { NotFoundPage } from '@/pages/not-found-page'
import { PatientsPage } from '@/pages/patients-page'
import { ProfilePage } from '@/pages/profile-page'
import { ReportsPage } from '@/pages/reports-page'
import { RegisterPage } from '@/pages/register-page'
import { PlatformPage } from '@/pages/platform-page'
import { ResultsPage } from '@/pages/results-page'
import type { Role } from '@/domain/types'
import { centerBase, useCenterPath } from '@/lib/tenant'

function PlatformProtectedRoute() { const { user, organization } = useClinic(); return !user ? <Navigate to="/plataforma/acceso" replace /> : user.role === 'SUPER_ADMIN' ? <Outlet /> : <Navigate to={organization ? centerBase(organization.slug) : '/'} replace /> }
function ResetTenantSession() { const { logout } = useClinic(); useEffect(() => logout(), [logout]); return <main className="grid min-h-screen place-items-center"><p>Abriendo el portal del centro…</p></main> }
function CenterProtectedRoute() {
  const { user, organization, publicOrganizations } = useClinic(); const { centerSlug = '' } = useParams(); const center = publicOrganizations.find((item) => item.slug === centerSlug)
  if (!center) return <main className="grid min-h-screen place-items-center text-center"><div><h1 className="text-2xl font-bold">Portal no disponible</h1><p className="mt-2 text-muted-foreground">Revisa la dirección entregada por tu centro médico.</p></div></main>
  if (!user) return <Navigate to={`${centerBase(center.slug)}/ingresar`} replace />
  if (user.role === 'SUPER_ADMIN') return <Navigate to="/plataforma" replace />
  if (organization?.id !== center.id) return <ResetTenantSession />
  return <Outlet />
}
function RoleRoute({ allowed }: { allowed: Role[] }) { const { user } = useClinic(); const centerPath = useCenterPath(); return user && allowed.includes(user.role) ? <Outlet /> : <Navigate to={centerPath()} replace /> }
function HomePage() { const { user } = useClinic(); return user?.role === 'ADMIN' ? <Navigate to="administracion" replace /> : <DashboardPage /> }
export default function App() { return <Routes>
  <Route path="/" element={<Navigate to="/centro/clinica-horizonte/ingresar" replace />} />
  <Route path="/ingresar" element={<Navigate to="/centro/clinica-horizonte/ingresar" replace />} />
  <Route path="/crear-cuenta" element={<Navigate to="/centro/clinica-horizonte/crear-cuenta" replace />} />
  <Route path="/plataforma/acceso" element={<LoginPage platform />} />
  <Route path="/centro/:centerSlug/ingresar" element={<LoginPage />} />
  <Route path="/centro/:centerSlug/crear-cuenta" element={<RegisterPage />} />
  <Route element={<PlatformProtectedRoute />}><Route element={<AppLayout />}><Route path="/plataforma" element={<PlatformPage />} /></Route></Route>
  <Route path="/centro/:centerSlug" element={<CenterProtectedRoute />}><Route element={<AppLayout />}>
    <Route index element={<HomePage />} />
    <Route element={<RoleRoute allowed={['RECEPCIONISTA', 'PROFESIONAL', 'PACIENTE']} />}><Route path="citas" element={<AppointmentsPage />} /><Route path="citas/:id" element={<AppointmentHistoryPage />} /></Route>
    <Route element={<RoleRoute allowed={['RECEPCIONISTA', 'PACIENTE']} />}><Route path="citas/:id/reprogramar" element={<ReschedulePage />} /><Route path="reservar" element={<BookingPage />} /></Route>
    <Route element={<RoleRoute allowed={['RECEPCIONISTA']} />}><Route path="pacientes" element={<PatientsPage />} /></Route>
    <Route element={<RoleRoute allowed={['ADMIN']} />}><Route path="reportes" element={<ReportsPage />} /><Route path="administracion" element={<AdminPage />} /></Route>
    <Route element={<RoleRoute allowed={['PACIENTE']} />}><Route path="perfil" element={<ProfilePage />} /></Route>
    <Route element={<RoleRoute allowed={['ADMIN', 'RECEPCIONISTA', 'PROFESIONAL', 'PACIENTE']} />}><Route path="resultados" element={<ResultsPage />} /></Route>
    <Route path="*" element={<NotFoundPage />} />
  </Route></Route>
</Routes> }
