import { apiRequest } from './http'

export type CenterRole = 'ADMIN' | 'RECEPCIONISTA' | 'PROFESIONAL' | 'PACIENTE'
export interface CenterPermissionCatalog {
  source: 'fixed_backend_catalog'
  roles: Record<CenterRole, string[]>
}

export const centerPermissionApi = {
  list: () => apiRequest<{ data: CenterPermissionCatalog }>('/api/v1/center/permissions'),
}
