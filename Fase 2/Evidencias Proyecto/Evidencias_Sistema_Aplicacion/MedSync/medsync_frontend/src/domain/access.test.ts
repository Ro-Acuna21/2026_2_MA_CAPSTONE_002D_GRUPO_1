import { describe, expect, it } from 'vitest'
import { createMockData } from '@/data/mock-data'
import { activeUser, scopeData } from './access'
import { permissionsFor } from './permissions'

describe('roles y aislamiento por centro', () => {
  it('la plataforma no recibe datos clínicos', () => {
    const all = createMockData(), user = all.users.find((u) => u.role === 'SUPER_ADMIN')!
    const data = scopeData(all, user, 'org1')
    expect(data.organizations).toHaveLength(2)
    for (const key of ['patients', 'professionals', 'appointments', 'history', 'results'] as const) expect(data[key]).toEqual([])
    expect(permissionsFor('SUPER_ADMIN')).toEqual(['platform.manage'])
  })
  it('un mismo usuario obtiene el rol del centro seleccionado', () => {
    const account = { ...createMockData().users.find((u) => u.role === 'ADMIN')!, memberships: [{ organizationId: 'org1', role: 'ADMIN' as const }, { organizationId: 'org2', role: 'RECEPCIONISTA' as const }] }
    expect(activeUser(account, 'org1')?.permissions).toContain('users.manage')
    expect(activeUser(account, 'org2')?.role).toBe('RECEPCIONISTA')
    expect(activeUser(account, 'org2')?.permissions).not.toContain('users.manage')
  })
  it('el paciente solo recibe sus resultados publicados', () => {
    const all = createMockData(), account = all.users.find((u) => u.email === 'paciente1@demo.cl')!
    all.results.push({ ...all.results[0], id: 'draft', status: 'DRAFT' }, { ...all.results[0], id: 'other', patientId: 'c2' }, { ...all.results[0], id: 'other-center', organizationId: 'org2' })
    const data = scopeData(all, activeUser(account, 'org1'), 'org1')
    expect(data.results.map((r) => r.id)).toEqual(['r1'])
    expect(data.patients.map((p) => p.id)).toEqual(['c1'])
    expect(scopeData(all, account, 'org2').results).toEqual([])
  })
  it('recepción no publica ni asigna permisos', () => {
    expect(permissionsFor('RECEPCIONISTA')).toContain('appointments.create')
    expect(permissionsFor('RECEPCIONISTA')).not.toContain('catalog.manage')
    expect(permissionsFor('RECEPCIONISTA')).not.toContain('users.manage')
    expect(permissionsFor('RECEPCIONISTA')).not.toContain('results.publish')
    expect(permissionsFor('ADMIN')).not.toContain('results.publish')
  })
  it('el administrador del centro no recibe fichas de pacientes ni resultados', () => {
    const all = createMockData(), account = all.users.find((u) => u.email === 'admin@demo.cl')!
    const data = scopeData(all, activeUser(account, 'org1'), 'org1')
    expect(data.patients).toEqual([])
    expect(data.results).toEqual([])
    expect(data.appointments.length).toBeGreaterThan(0)
    expect(permissionsFor('ADMIN')).not.toContain('appointments.view.all')
    expect(permissionsFor('ADMIN')).not.toContain('appointments.create')
    expect(permissionsFor('ADMIN')).not.toContain('patients.manage')
  })
})
