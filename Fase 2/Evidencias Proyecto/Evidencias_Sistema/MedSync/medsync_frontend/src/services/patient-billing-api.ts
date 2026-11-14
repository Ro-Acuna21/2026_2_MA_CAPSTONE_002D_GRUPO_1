import { apiRequest } from './http'

export interface CareIndication {
  id: number
  patient_id: number
  appointment_id: number
  service_id: number
  service_name: string
  amount_clp: number
  status: 'INDICATED' | 'CHARGED' | 'VOIDED'
  created_at: string
  first_name?: string
  last_name?: string
}
export interface PatientBalance {
  id: number
  name: string
  rut: string
  charged_clp: number
  paid_clp: number
  balance_clp: number
}
export interface PatientPayment {
  id: number
  patient_id: number
  amount_clp: number
  method: 'CASH' | 'CARD' | 'TRANSFER' | 'OTHER'
  reference: string | null
  created_at: string
}
export interface BillingSummary {
  charged_clp: number
  paid_clp: number
  balance_clp: number
  pending_indications: number
  patients: PatientBalance[]
  indications: CareIndication[]
  payments: PatientPayment[]
}

const post = <T>(url: string, body?: object) => apiRequest<{ data: T }>(url, {
  method: 'POST', body: JSON.stringify(body ?? {}),
})

export const patientBillingApi = {
  appointment: (appointmentId: number) => apiRequest<{ data: CareIndication[] }>(`/api/v1/appointments/${appointmentId}/indications`),
  indicate: (appointmentId: number, serviceId: number) => post<CareIndication>(`/api/v1/appointments/${appointmentId}/indications`, { service_id: serviceId }),
  summary: () => apiRequest<{ data: BillingSummary }>('/api/v1/billing/summary'),
  confirm: (id: number) => post<CareIndication>(`/api/v1/billing/indications/${id}/confirm`),
  voidIndication: (id: number, reason: string) => post<CareIndication>(`/api/v1/billing/indications/${id}/void`, { reason }),
  pay: (patientId: number, input: { amount_clp: number; method: PatientPayment['method']; reference?: string }) => post<PatientPayment>(`/api/v1/billing/patients/${patientId}/payments`, input),
  voidPayment: (id: number, reason: string) => post<PatientPayment>(`/api/v1/billing/payments/${id}/void`, { reason }),
}
