import { apiRequest } from './http'

export interface PlatformServicePrice { id: number; amount_clp: number; effective_from: string; effective_to: string | null }
export interface PlatformService {
  id: number; name: string; category: 'CONSULTATION' | 'EXAM' | 'DIAGNOSIS'
  duration_minutes: number; is_active: boolean; base_price_clp: number | null
  prices: PlatformServicePrice[]; center_service_id?: number | null; effective_price_clp?: number | null
}
const json = (body: object) => JSON.stringify(body)
export const platformServicesApi = {
  platformList: () => apiRequest<{data: PlatformService[]}>('/api/v1/platform/services'),
  centerList: () => apiRequest<{data: PlatformService[]}>('/api/v1/center/platform-services'),
  create: (body: {name: string; category: PlatformService['category']; duration_minutes: number}) =>
    apiRequest<{data: PlatformService}>('/api/v1/platform/services', {method: 'POST', body: json(body)}),
  update: (id: number, body: {is_active?: boolean; name?: string; category?: PlatformService['category']; duration_minutes?: number}) =>
    apiRequest<{data: PlatformService}>(`/api/v1/platform/services/${id}`, {method: 'PATCH', body: json(body)}),
  addPrice: (id: number, body: {amount_clp: number; effective_from: string; effective_to?: string | null}) =>
    apiRequest<{data: PlatformServicePrice}>(`/api/v1/platform/services/${id}/prices`, {method: 'POST', body: json(body)}),
  closePrice: (id: number, effective_to: string) =>
    apiRequest<{data: PlatformServicePrice}>(`/api/v1/platform/service-prices/${id}/close`, {method: 'PATCH', body: json({effective_to})}),
  adopt: (platform_service_id: number, specialty_id: number) =>
    apiRequest('/api/v1/services/from-platform', {method: 'POST', body: json({platform_service_id, specialty_id})}),
}
