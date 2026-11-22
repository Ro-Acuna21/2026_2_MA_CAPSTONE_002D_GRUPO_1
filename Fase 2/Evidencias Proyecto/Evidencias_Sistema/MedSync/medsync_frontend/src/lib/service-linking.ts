import type { BackendService } from '@/services/http'

export type ServiceCategory = 'CONSULTATION' | 'EXAM' | 'DIAGNOSIS'

export function linkableLocalServices(local: BackendService[], category: ServiceCategory): BackendService[] {
  return local.filter(service => !service.platform_service_id && service.category === category)
}
