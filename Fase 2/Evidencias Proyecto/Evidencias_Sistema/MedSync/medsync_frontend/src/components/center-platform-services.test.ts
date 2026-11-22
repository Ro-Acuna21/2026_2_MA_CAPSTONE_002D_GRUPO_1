import { describe, expect, it } from 'vitest'
import { linkableLocalServices } from '@/lib/service-linking'
import type { BackendService } from '@/services/http'

const localServices: BackendService[] = [
  {id: 1, name: 'Consulta local', description: null, duration_minutes: 30, specialty: null, category: 'CONSULTATION', platform_service_id: null},
  {id: 2, name: 'Examen local', description: null, duration_minutes: 20, specialty: null, category: 'EXAM', platform_service_id: null},
  {id: 3, name: 'Consulta ya vinculada', description: null, duration_minutes: 30, specialty: null, category: 'CONSULTATION', platform_service_id: 20},
  {id: 4, name: 'Antigua sin categoría', description: null, duration_minutes: 30, specialty: null, category: null, platform_service_id: null},
]

describe('prestaciones locales disponibles para vincular', () => {
  it('filtra por categoría y excluye las ya vinculadas', () => {
    expect(linkableLocalServices(localServices, 'CONSULTATION').map(service => service.id)).toEqual([1])
    expect(linkableLocalServices(localServices, 'EXAM').map(service => service.id)).toEqual([2])
  })

  it('devuelve una lista vacía si no hay equivalentes clasificados', () => {
    expect(linkableLocalServices(localServices, 'DIAGNOSIS')).toEqual([])
  })
})
