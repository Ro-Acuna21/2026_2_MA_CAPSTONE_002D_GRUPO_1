import type { Appointment, BookingInput, ClinicData, Patient, Slot, User } from '@/domain/types'
import { apiRequest } from './http'

// Contrato esperado por Laravel. Las pantallas no conocen URLs ni detalles de Eloquent.
export const clinicApi = {
  me: () => apiRequest<{ data: User }>('/api/v1/me'),
  bootstrap: () => apiRequest<{ data: ClinicData }>('/api/v1/bootstrap'),
  appointments: (query = '') => apiRequest<{ data: Appointment[] }>(`/api/v1/appointments${query}`),
  slots: (professionalId: string, serviceId: string, date: string) =>
    apiRequest<{ data: Slot[] }>(`/api/v1/slots?professional_id=${professionalId}&service_id=${serviceId}&date=${date}`),
  createAppointment: (input: BookingInput) => apiRequest<{ data: Appointment }>('/api/v1/appointments', { method: 'POST', body: JSON.stringify(input) }),
  updateAppointment: (id: string, input: Partial<Appointment>) => apiRequest<{ data: Appointment }>(`/api/v1/appointments/${id}`, { method: 'PATCH', body: JSON.stringify(input) }),
  createPatient: (input: Omit<Patient, 'id' | 'active' | 'organizationId'>) => apiRequest<{ data: Patient }>('/api/v1/patients', { method: 'POST', body: JSON.stringify(input) }),
  selectOrganization: (organizationId: string) => apiRequest('/api/v1/session/organization', { method: 'PUT', body: JSON.stringify({ organization_id: organizationId }) }),
}
