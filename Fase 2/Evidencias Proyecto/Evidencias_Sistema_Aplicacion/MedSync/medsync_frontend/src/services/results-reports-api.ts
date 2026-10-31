import { apiRequest, AUTH_EXPIRED_EVENT, ApiError } from '@/services/http'

export interface ResultType { id: number; name: string; description: string | null; is_active: boolean }
export interface MedicalResult {
  id: number; appointment_id: number; patient_id: number; patient_name: string; professional_id: number; professional_name: string
  result_type_id: number; result_type_name: string; performed_at: string; status: 'DRAFT' | 'PUBLISHED' | 'VOIDED'
  published_at: string | null; voided_at: string | null; void_reason: string | null
  files: { id: number; filename: string; mime_type: string; size_bytes: number }[]
}
export interface ServicePrice { id: number; service_id: number; amount_clp: number; effective_from: string; effective_to: string | null }
export interface ReportSummary {
  from: string; to: string; total_appointments: number; counts: Record<string, number>
  estimated_revenue_clp: number; lost_estimate_clp: number; unpriced_appointments: number; average_ticket_clp: number
  months: { month: string; total: number; bookings: number }[]
  services: { id: number; label: string; bookings: number; attended: number; revenue: number }[]
  professionals: { id: number; label: string; bookings: number; attended: number; revenue: number }[]
}
const json = (value: unknown) => JSON.stringify(value)
export const resultApi = {
  types: () => apiRequest<{ data: ResultType[] }>('/api/v1/result-types'),
  createType: (name: string) => apiRequest<{ data: ResultType }>('/api/v1/result-types', { method: 'POST', body: json({ name }) }),
  updateType: (id: number, payload: Partial<ResultType>) => apiRequest<{ data: ResultType }>(`/api/v1/result-types/${id}`, { method: 'PATCH', body: json(payload) }),
  list: () => apiRequest<{ data: MedicalResult[] }>('/api/v1/results'),
  create: (form: FormData) => apiRequest<{ data: MedicalResult }>('/api/v1/results', { method: 'POST', body: form }),
  publish: (id: number) => apiRequest<{ data: MedicalResult }>(`/api/v1/results/${id}/publish`, { method: 'POST' }),
  void: (id: number, reason: string) => apiRequest<{ data: MedicalResult }>(`/api/v1/results/${id}/void`, { method: 'POST', body: json({ reason }) }),
  download: async (resultId: number, fileId: number) => {
    const base = import.meta.env.VITE_API_URL ?? `${window.location.protocol}//${window.location.hostname}:8000`
    const response = await fetch(`${base}/api/v1/results/${resultId}/files/${fileId}`, { credentials: 'include', headers: { Accept: 'application/octet-stream' } })
    if (!response.ok) {
      if (response.status === 401) window.dispatchEvent(new Event(AUTH_EXPIRED_EVENT))
      throw new ApiError(response.status, 'No tienes acceso al documento.')
    }
    return response.blob()
  },
}
export const priceApi = {
  list: () => apiRequest<{ data: ServicePrice[] }>('/api/v1/service-prices'),
  create: (payload: Omit<ServicePrice, 'id'>) => apiRequest<{ data: ServicePrice }>('/api/v1/service-prices', { method: 'POST', body: json(payload) }),
  update: (id: number, payload: Partial<ServicePrice>) => apiRequest<{ data: ServicePrice }>(`/api/v1/service-prices/${id}`, { method: 'PATCH', body: json(payload) }),
  remove: (id: number) => apiRequest<void>(`/api/v1/service-prices/${id}`, { method: 'DELETE' }),
}
export const reportApi = {
  summary: (filters: { from: string; to: string; service_id?: number; professional_id?: number }) =>
    apiRequest<{ data: ReportSummary }>(`/api/v1/reports/summary?${new URLSearchParams(Object.entries(filters).map(([k, v]) => [k, String(v)])).toString()}`),
}
