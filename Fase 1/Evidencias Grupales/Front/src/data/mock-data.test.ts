import { describe, expect, it } from 'vitest'
import { createMockData } from './mock-data'

describe('datos multiempresa', () => {
  it('asocia cada recurso de negocio a una organización', () => {
    const data = createMockData()
    expect(data.organizations).toHaveLength(2)
    expect(data.patients.every((item) => Boolean(item.organizationId))).toBe(true)
    expect(data.appointments.every((item) => Boolean(item.organizationId))).toBe(true)
  })

  it('permite al administrador demo acceder a ambas empresas', () => {
    const data = createMockData()
    expect(data.users.find((item) => item.email === 'admin@demo.cl')?.organizationIds).toEqual(['org1', 'org2'])
  })
})
