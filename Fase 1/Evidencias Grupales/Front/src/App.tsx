import { Navigate, Outlet, Route, Routes } from 'react-router-dom'
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
import { OrganizationSelectPage } from '@/pages/organization-select-page'

function ProtectedRoute() { const { user, organization } = useClinic(); return user ? organization ? <Outlet /> : <Navigate to="/seleccionar-empresa" replace /> : <Navigate to="/ingresar" replace /> }
export default function App() { return <Routes><Route path="/ingresar" element={<LoginPage />} /><Route path="/seleccionar-empresa" element={<OrganizationSelectPage />} /><Route element={<ProtectedRoute />}><Route element={<AppLayout />}><Route index element={<DashboardPage />} /><Route path="citas" element={<AppointmentsPage />} /><Route path="citas/:id" element={<AppointmentHistoryPage />} /><Route path="citas/:id/reprogramar" element={<ReschedulePage />} /><Route path="reservar" element={<BookingPage />} /><Route path="pacientes" element={<PatientsPage />} /><Route path="reportes" element={<ReportsPage />} /><Route path="administracion" element={<AdminPage />} /><Route path="perfil" element={<ProfilePage />} /><Route path="*" element={<NotFoundPage />} /></Route></Route></Routes> }
