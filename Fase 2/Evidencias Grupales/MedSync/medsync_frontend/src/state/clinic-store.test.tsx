// @vitest-environment jsdom
import { webcrypto } from 'node:crypto'
import { act, cleanup, renderHook } from '@testing-library/react'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { ClinicProvider, useClinic } from './clinic-store'
import { createMockData } from '@/data/mock-data'

beforeEach(() => { localStorage.clear(); sessionStorage.clear(); vi.stubGlobal('crypto', webcrypto) })
afterEach(() => { cleanup(); vi.unstubAllGlobals() })
const patient = { name: 'Paciente Prueba', rut: '12345678-5', email: 'nuevo@example.com', phone: '+56 9 1234 5678', birthDate: '1990-02-20', address: '', consent: true, healthInsurance: 'Fonasa', medicalInsurance: '' }
const mount = () => renderHook(() => useClinic(), { wrapper: ClinicProvider })
const centerFor = (email: string) => ['admin.alameda@demo.cl', 'recepcion.alameda@demo.cl', 'emilia.silva@demo.cl', 'sofia@demo.cl'].includes(email) ? 'org2' : 'org1'
const signIn = async (result: ReturnType<typeof mount>['result'], email: string) => { await act(async () => { expect(await result.current.login(email, 'Demo2026!', email === 'superadmin@demo.cl' ? 'PLATFORM' : 'CENTER', email === 'superadmin@demo.cl' ? undefined : centerFor(email))).toBe(true) }) }

describe('flujos del proveedor demo', () => {
  it('registra una cuenta, rechaza duplicados y permite entrar con su contraseña', async () => {
    const { result } = mount()
    await act(async () => { await result.current.register(patient, 'Prueba2026', 'Prueba2026', 'org1') })
    const saved = localStorage.getItem('clinica_horizonte_react_v4')!
    expect(saved).not.toContain('Prueba2026')
    await expect(result.current.register(patient, 'Prueba2026', 'Prueba2026', 'org1')).rejects.toThrow('cuenta')
    await act(async () => { await result.current.register(patient, 'Otra2026', 'Otra2026', 'org2') })
    expect(JSON.parse(localStorage.getItem('clinica_horizonte_react_v4')!).users.filter((u: { email: string }) => u.email === patient.email)).toHaveLength(2)
    await act(async () => { expect(await result.current.login(patient.email, 'Demo2026!', 'CENTER', 'org2')).toBe(false); expect(await result.current.login(patient.email, 'Otra2026', 'CENTER', 'org2')).toBe(true) })
    expect(result.current.user?.role).toBe('PACIENTE')
    expect(result.current.data.patients[0].healthInsurance).toBe('Fonasa')
    expect(result.current.data.patients).toHaveLength(1)
    expect(result.current.data.patients[0].organizationId).toBe('org2')
  })
  it('activa una ficha creada por recepción sin duplicar al paciente y protege acciones administrativas', async () => {
    const { result } = mount(); await signIn(result, 'recepcion@demo.cl')
    act(() => result.current.createPatient(patient))
    expect(result.current.data.patients.some((p) => p.email === patient.email)).toBe(true)
    expect(result.current.data.users.some((u) => u.email === patient.email)).toBe(false)
    await act(async () => { await result.current.register(patient, 'Prueba2026', 'Prueba2026', 'org1') })
    expect(result.current.data.patients.filter((p) => p.email === patient.email)).toHaveLength(1)
    const saved = JSON.parse(localStorage.getItem('clinica_horizonte_react_v4')!)
    expect(saved.users.find((u: { email: string }) => u.email === patient.email)?.patientId).toBe(result.current.data.patients.find((p) => p.email === patient.email)?.id)
    await expect(result.current.assignAccess('otro@example.com', 'Otra Persona', { role: 'ADMIN', organizationId: 'org1' }, 'Prueba2026')).rejects.toThrow('permiso')
    expect(() => result.current.addResultType('Biopsia')).toThrow('permiso')
  })
  it('recorre borrador, publicación, lectura y eliminación por el profesional responsable', async () => {
    const { result } = mount(); await signIn(result, 'camila.rojas@demo.cl')
    act(() => result.current.addResult({ patientId: 'c1', professionalId: 'p1', typeId: 'rt1', performedAt: '2026-08-01', filename: 'demo.txt', content: 'data:text/plain;base64,RGVtbw==' }))
    const id = result.current.data.results.find((r) => r.status === 'DRAFT')!.id
    await signIn(result, 'admin@demo.cl')
    expect(() => result.current.publishResult(id)).toThrow('permiso')
    await signIn(result, 'paciente1@demo.cl'); expect(result.current.data.results.some((r) => r.id === id)).toBe(false)
    await signIn(result, 'matias.soto@demo.cl'); expect(() => result.current.publishResult(id)).toThrow('responsable')
    await signIn(result, 'camila.rojas@demo.cl'); act(() => result.current.publishResult(id))
    await signIn(result, 'paciente1@demo.cl'); expect(result.current.data.results.find((r) => r.id === id)?.status).toBe('PUBLISHED')
    await signIn(result, 'camila.rojas@demo.cl'); act(() => result.current.deleteResult(id))
    await signIn(result, 'paciente1@demo.cl'); expect(result.current.data.results.some((r) => r.id === id)).toBe(false)
  })
  it('crea centros y asigna su administrador sin acceder a la clínica', async () => {
    const { result } = mount(); await signIn(result, 'superadmin@demo.cl')
    act(() => result.current.saveOrganization({ id: '', name: 'Centro Demo', slug: 'centro-demo', plan: 'PRO', subscription: 'TRIAL', active: true }))
    const center = result.current.data.organizations.find((o) => o.slug === 'centro-demo')!
    await act(async () => { await result.current.assignAccess('centro@example.com', 'Admin Demo', { organizationId: center.id, role: 'ADMIN' }, 'Prueba2026') })
    expect(result.current.data.patients).toEqual([])
    expect(() => result.current.createPatient(patient)).toThrow('permiso')
    await act(async () => { expect(await result.current.login('centro@example.com', 'Prueba2026', 'CENTER', center.id)).toBe(true) })
    expect(result.current.organization?.id).toBe(center.id)
    expect(result.current.user?.permissions).toContain('users.manage')
  })
  it('preserva datos anteriores y agrega el acceso de plataforma', async () => {
    const old = createMockData(); old.users = old.users.filter((u) => u.role !== 'SUPER_ADMIN'); old.patients[0].name = 'Registro anterior'
    localStorage.setItem('clinica_horizonte_react_v2', JSON.stringify({ ...old, results: undefined, resultTypes: undefined }))
    const { result } = mount()
    await signIn(result, 'recepcion@demo.cl')
    expect(result.current.data.patients[0].name).toBe('Registro anterior')
    expect(result.current.data.results).toEqual([])
    await signIn(result, 'superadmin@demo.cl')
    expect(result.current.user?.role).toBe('SUPER_ADMIN')
    expect(localStorage.getItem('clinica_horizonte_react_v2')).toContain('Registro anterior')
  })
  it('deshabilita un centro sin eliminar su información', async () => {
    const { result } = mount(); await signIn(result, 'superadmin@demo.cl')
    const center = result.current.data.organizations.find((o) => o.id === 'org1')!
    act(() => result.current.saveOrganization({ ...center, active: false, subscription: 'SUSPENDED' }))
    await signIn(result, 'paciente1@demo.cl')
    expect(result.current.organization).toBeNull()
    expect(result.current.publicOrganizations.some((item) => item.id === 'org1')).toBe(false)
    expect(JSON.parse(localStorage.getItem('clinica_horizonte_react_v4')!).patients).toHaveLength(11)
  })
  it('las horas ocupadas por otros pacientes no se ofrecen al paciente', async () => {
    const { result } = mount(); await signIn(result, 'paciente2@demo.cl')
    const reserved = createMockData().appointments.find((a) => a.id === 'a1')!
    expect(result.current.data.appointments.some((a) => a.id === reserved.id)).toBe(false)
    expect(result.current.slots(reserved.professionalId, reserved.serviceId, reserved.date).some((s) => s.time === reserved.time)).toBe(false)
  })
  it('separa el acceso común del acceso de plataforma', async () => {
    const { result } = mount()
    await act(async () => { expect(await result.current.login('superadmin@demo.cl', 'Demo2026!', 'CENTER', 'org1')).toBe(false); expect(await result.current.login('admin@demo.cl', 'Demo2026!', 'PLATFORM')).toBe(false); expect(await result.current.login('superadmin@demo.cl', 'Demo2026!', 'PLATFORM')).toBe(true) })
  })
})
