import { apiRequest } from './http'

export interface PlatformCenter {
  id: number
  name: string
  slug: string
  rut: string
  phone: string | null
  email: string | null
  public_description?: string | null
  address_line?: string | null
  commune?: string | null
  region?: string | null
  plan: string | null
  subscription_status: 'TRIAL' | 'ACTIVE' | 'EXPIRED' | 'SUSPENDED' | null
  is_active: boolean
  provisioning_status: 'PENDING' | 'READY' | 'FAILED'
  provisioned_at: string | null
  subscription_renews_at?: string | null
}

export type CenterInput = Pick<PlatformCenter, 'name' | 'slug' | 'rut' | 'phone' | 'email' | 'public_description' | 'address_line' | 'is_active'> & {
  region_code?: string | null
  commune_code?: string | null
}

export const platformCenterApi = {
  list: () => apiRequest<{ data: PlatformCenter[] }>('/api/v1/platform/centers'),
  create: (input: CenterInput) => apiRequest<{ data: PlatformCenter }>('/api/v1/platform/centers', {
    method: 'POST', body: JSON.stringify(input),
  }),
  update: (id: number, input: Partial<CenterInput>) => apiRequest<{ data: PlatformCenter }>(`/api/v1/platform/centers/${id}`, {
    method: 'PATCH', body: JSON.stringify(input),
  }),
  retry: (id: number) => apiRequest<{ data: PlatformCenter }>(`/api/v1/platform/centers/${id}/provision`, {
    method: 'POST', body: JSON.stringify({ is_active: true }),
  }),
}
