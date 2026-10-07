// @vitest-environment jsdom
import { act, cleanup, renderHook, waitFor } from '@testing-library/react'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { ClinicProvider, useClinic } from './clinic-store'
import { createMockData } from '@/data/mock-data'
import { backendUser, centers, stubLaravelApi } from '@/test/laravel-api'
import { appointmentApi } from '@/services/http'
import type { AuthUser } from '@/services/http'

beforeEach(() => { localStorage.clear(); sessionStorage.clear() })
afterEach(() => { cleanup(); vi.unstubAllGlobals() })
const mount = () => renderHook(() => useClinic(), { wrapper: ClinicProvider })
const ready = async (result: ReturnType<typeof mount>['result']) => {
  await waitFor(() => expect(result.current.authLoading && result.current.publicCentersLoading).toBe(false))
}
const account = (role: AuthUser['role'], slug: string | undefined, email: string, id: number) => ({
  ...backendUser(role!, slug, id), email,
})
const patient = { name: 'Paciente Prueba', rut: '12345678-5', email: 'nuevo@example.com', phone: '+56 9 1234 5678', birthDate: '1990-02-20', address: '', consent: true, healthInsurance: 'Fonasa', medicalInsurance: '' }

describe('ClinicProvider con API Laravel y datos demo aún pendientes', () => {
  it('autentica por Sanctum y recupera la sesión al montar de nuevo, sin login local', async () => {
    const user = account('PACIENTE', 'clinica-horizonte', 'ana@example.com', 11)
    const api = stubLaravelApi({ accounts: { [user.email]: user } })
    const first = mount()
    await ready(first.result)
    expect(first.result.current.user).toBeNull()
    await act(async () => {
      expect(await first.result.current.login(user.email, 'Password123', 'CENTER', 'clinica-horizonte')).toBe(true)
    })
    expect(first.result.current.user?.role).toBe('PACIENTE')
    expect(api.requests.find((request) => request.path === '/api/login')?.body?.center_slug).toBe('clinica-horizonte')
    expect(localStorage.getItem('clinica_horizonte_react_v4')).toBeNull()
    first.unmount()

    const second = mount()
    await waitFor(() => expect(second.result.current.user?.email).toBe(user.email))
    expect(api.requests.filter((request) => request.path === '/api/v1/me')).toHaveLength(2)
  })

  it('crea la ficha por API y conserva el contrato de pacientes que consume Reservas', async () => {
    const user = account('RECEPCIONISTA', 'clinica-horizonte', 'recepcion@example.com', 12)
    const api = stubLaravelApi({ session: user })
    const { result } = mount()
    await waitFor(() => expect(result.current.user?.role).toBe('RECEPCIONISTA'))
    const before = localStorage.getItem('clinica_horizonte_react_v4')
    await act(async () => { await result.current.createPatient(patient) })
    const request = api.requests.find((item) => item.path === '/api/v1/patients' && item.method === 'POST')
    expect(request?.body).toMatchObject({ first_name: 'Paciente', last_name: 'Prueba', rut: patient.rut, health_insurance: 'Fonasa', consent: true })
    const bookingList = await appointmentApi.patients()
    expect(bookingList.data).toEqual([expect.objectContaining({ id: 12, first_name: 'Paciente', last_name: 'Prueba', rut: patient.rut })])
    expect(localStorage.getItem('clinica_horizonte_react_v4')).toBe(before)
  })

  it('aplica permisos de rol a los resultados demo que siguen pendientes de backend', async () => {
    const professional = account('PROFESIONAL', 'clinica-horizonte', 'profesional@example.com', 13)
    stubLaravelApi({ session: professional })
    const { result } = mount()
    await waitFor(() => expect(result.current.user?.role).toBe('PROFESIONAL'))
    expect(() => result.current.addResultType('Biopsia')).toThrow('permiso')
    expect(result.current.user?.permissions).toContain('results.upload')
    expect(result.current.user?.permissions).not.toContain('users.manage')
  })

  it('mantiene al SUPER_ADMIN fuera de las operaciones clínicas', async () => {
    const api = stubLaravelApi({ session: backendUser('SUPER_ADMIN') })
    const { result } = mount()
    await waitFor(() => expect(result.current.user?.role).toBe('SUPER_ADMIN'))
    expect(result.current.organization).toBeNull()
    await expect(result.current.createPatient(patient)).rejects.toThrow('permiso')
    expect(api.requests.some((item) => item.path === '/api/v1/patients')).toBe(false)
  })

  it('ignora una sesión demo antigua cuando Sanctum no autentica al usuario', async () => {
    sessionStorage.setItem('clinica_horizonte_user', 'platform')
    stubLaravelApi()
    const { result } = mount()
    await ready(result)
    expect(result.current.user).toBeNull()
    expect(result.current.organization).toBeNull()
    expect(result.current.publicOrganizations).toHaveLength(2)
  })

  it('usa centros activos publicados por Core aunque existan organizaciones demo guardadas', async () => {
    const old = createMockData()
    localStorage.setItem('clinica_horizonte_react_v4', JSON.stringify(old))
    stubLaravelApi({ centers: [centers[0]] })
    const { result } = mount()
    await ready(result)
    expect(result.current.publicOrganizations.map((center) => center.slug)).toEqual(['clinica-horizonte'])
    expect(localStorage.getItem('clinica_horizonte_react_v4')).not.toBeNull()
  })

  it('obtiene horarios libres del backend sin consultar las citas demo locales', async () => {
    const user = account('PACIENTE', 'clinica-horizonte', 'paciente@example.com', 14)
    const api = stubLaravelApi({ session: user, slots: ['09:30', '10:00'] })
    const { result } = mount()
    await waitFor(() => expect(result.current.user?.role).toBe('PACIENTE'))
    const slots = await result.current.patientSlots('3', '4', '2026-10-09')
    expect(slots).toEqual([{ time: '09:30', end: '10:00' }, { time: '10:00', end: '10:30' }])
    const request = api.requests.find((item) => item.path === '/api/v1/appointments/available-slots')
    expect(request).toBeDefined()
  })

  it('separa el acceso de plataforma del login de un centro incluso ante una respuesta con otro rol', async () => {
    const platform = account('SUPER_ADMIN', undefined, 'platform@example.com', 15)
    const admin = account('ADMIN', 'clinica-horizonte', 'admin@example.com', 16)
    const api = stubLaravelApi({ accounts: { [platform.email]: platform, [admin.email]: admin } })
    const { result } = mount()
    await ready(result)
    await act(async () => { expect(await result.current.login(platform.email, 'Password123', 'CENTER', 'clinica-horizonte')).toBe(false) })
    await act(async () => { expect(await result.current.login(admin.email, 'Password123', 'PLATFORM')).toBe(false) })
    await act(async () => { expect(await result.current.login(platform.email, 'Password123', 'PLATFORM')).toBe(true) })
    expect(result.current.user?.role).toBe('SUPER_ADMIN')
    expect(api.requests.filter((item) => item.path === '/api/logout')).toHaveLength(2)
  })
})
