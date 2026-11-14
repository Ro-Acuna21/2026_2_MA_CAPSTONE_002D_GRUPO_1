import { apiRequest } from './http'

export interface PlatformReport {
  total_centers: number
  active_centers: number
  total_platform_users: number
  unique_patients: number
  patient_center_registrations: number
  patients_in_multiple_centers: number
  centers: { id: number; name: string; slug: string; is_active: boolean; patient_count: number }[]
}

export const platformReportApi = {
  summary: () => apiRequest<{ data: PlatformReport }>('/api/v1/platform/reports/summary'),
}
