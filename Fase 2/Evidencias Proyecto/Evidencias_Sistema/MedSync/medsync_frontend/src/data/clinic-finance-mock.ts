export interface RevenuePoint { month: string; total: number }
export interface ServicePerformance { serviceId: string; label: string; bookings: number; revenue: number }
export interface ProfessionalPerformance { professionalId: string; label: string; revenue: number }
export interface AppointmentState { label: string; total: number; tone: 'bg-blue-500' | 'bg-emerald-500' | 'bg-rose-500' | 'bg-violet-500' }

export interface ClinicFinanceMock {
  organizationId: string
  revenueByMonth: RevenuePoint[]
  services: ServicePerformance[]
  professionals: ProfessionalPerformance[]
  appointmentStates: AppointmentState[]
  lostRevenue: number
}

export const clinicFinanceMock: ClinicFinanceMock[] = [
  { organizationId: 'org1', revenueByMonth: [{ month: '2026-04', total: 1_120_000 }, { month: '2026-05', total: 1_285_000 }, { month: '2026-06', total: 1_190_000 }, { month: '2026-07', total: 1_460_000 }, { month: '2026-08', total: 1_580_000 }, { month: '2026-09', total: 1_735_000 }], services: [{ serviceId: 's1', label: 'Consulta general', bookings: 38, revenue: 760_000 }, { serviceId: 's3', label: 'Evaluación kinésica', bookings: 24, revenue: 720_000 }, { serviceId: 's5', label: 'Consulta nutricional', bookings: 19, revenue: 475_000 }, { serviceId: 's2', label: 'Control', bookings: 27, revenue: 405_000 }], professionals: [{ professionalId: 'p1', label: 'Dra. Camila Rojas', revenue: 1_080_000 }, { professionalId: 'p2', label: 'Matías Soto', revenue: 930_000 }, { professionalId: 'p3', label: 'Valentina Muñoz', revenue: 680_000 }], appointmentStates: [{ label: 'Agendadas', total: 22, tone: 'bg-blue-500' }, { label: 'Realizadas', total: 81, tone: 'bg-emerald-500' }, { label: 'Canceladas', total: 9, tone: 'bg-rose-500' }, { label: 'No-show', total: 6, tone: 'bg-violet-500' }], lostRevenue: 315_000 },
  { organizationId: 'org2', revenueByMonth: [{ month: '2026-04', total: 420_000 }, { month: '2026-05', total: 510_000 }, { month: '2026-06', total: 470_000 }, { month: '2026-07', total: 620_000 }, { month: '2026-08', total: 680_000 }, { month: '2026-09', total: 730_000 }], services: [{ serviceId: 's6', label: 'Consulta pediátrica', bookings: 36, revenue: 720_000 }], professionals: [{ professionalId: 'p4', label: 'Dra. Emilia Silva', revenue: 720_000 }], appointmentStates: [{ label: 'Agendadas', total: 12, tone: 'bg-blue-500' }, { label: 'Realizadas', total: 31, tone: 'bg-emerald-500' }, { label: 'Canceladas', total: 3, tone: 'bg-rose-500' }, { label: 'No-show', total: 2, tone: 'bg-violet-500' }], lostRevenue: 95_000 },
]

const emptyFinance = (organizationId: string): ClinicFinanceMock => ({ organizationId, revenueByMonth: [], services: [], professionals: [], appointmentStates: [], lostRevenue: 0 })

export function financeForOrganization(organizationId: string) {
  return clinicFinanceMock.find((item) => item.organizationId === organizationId) ?? emptyFinance(organizationId)
}
