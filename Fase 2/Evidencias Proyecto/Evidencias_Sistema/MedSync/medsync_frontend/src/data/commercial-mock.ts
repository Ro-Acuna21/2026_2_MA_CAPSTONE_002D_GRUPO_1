import type { Organization } from '@/domain/types'

export type PaymentStatus = 'PAID' | 'PENDING'

export interface CommercialPlan {
  id: 'STARTER' | 'PRO' | 'ENTERPRISE'
  name: string
  monthlyPrice: number
  professionalLimit: string
  userLimit: string
  features: string[]
  reports: string
}

export interface CommercialCenterMock {
  organizationId: string
  startedAt: string
  renewalAt: string
  paymentStatus: PaymentStatus
}

export const commercialPlans: CommercialPlan[] = [
  { id: 'STARTER', name: 'Básico', monthlyPrice: 49_990, professionalLimit: 'Hasta 3 profesionales', userLimit: 'Hasta 8 usuarios', features: ['Agenda y reservas', 'Fichas de pacientes', 'Resultados clínicos'], reports: 'Reportes operacionales' },
  { id: 'PRO', name: 'Profesional', monthlyPrice: 89_990, professionalLimit: 'Hasta 12 profesionales', userLimit: 'Hasta 30 usuarios', features: ['Todo lo del plan Básico', 'Catálogo de prestaciones', 'Gestión de disponibilidad'], reports: 'Reportes operacionales y comerciales' },
  { id: 'ENTERPRISE', name: 'Enterprise', monthlyPrice: 159_990, professionalLimit: 'Profesionales ilimitados', userLimit: 'Usuarios ilimitados', features: ['Todo lo del plan Profesional', 'Soporte prioritario', 'Configuración avanzada'], reports: 'Reportes avanzados y exportables' },
]

export const commercialCenterMock: CommercialCenterMock[] = [
  { organizationId: 'org1', startedAt: '2026-03-01', renewalAt: '2026-10-01', paymentStatus: 'PAID' },
  { organizationId: 'org2', startedAt: '2026-06-15', renewalAt: '2026-10-15', paymentStatus: 'PENDING' },
]

export function commercialPlanFor(plan: Organization['plan']) {
  return commercialPlans.find((item) => item.id === plan) ?? commercialPlans[0]
}

export function commercialInfoFor(center: Organization): CommercialCenterMock {
  return commercialCenterMock.find((item) => item.organizationId === center.id)
    ?? { organizationId: center.id, startedAt: '2026-09-01', renewalAt: '2026-10-01', paymentStatus: 'PENDING' }
}
