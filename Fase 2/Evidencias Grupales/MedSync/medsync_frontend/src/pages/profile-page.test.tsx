// @vitest-environment jsdom
import { cleanup, fireEvent, render, screen, waitFor } from '@testing-library/react'
import { MemoryRouter } from 'react-router-dom'
import { afterEach, expect, it, vi } from 'vitest'
import { ProfilePage } from './profile-page'
import type { BackendPatient } from '@/services/http'

vi.mock('@/state/clinic-store', () => ({
  useClinic: () => ({ user: { role: 'PACIENTE', patientId: '7' } }),
}))

afterEach(() => { cleanup(); vi.unstubAllGlobals(); localStorage.clear() })

it('carga y actualiza el perfil propio mediante API sin persistencia local', async () => {
  const patient: BackendPatient = {
    id: 7, first_name: 'Ana', last_name: 'Prueba', rut: '11111111-1',
    birth_date: '1990-01-01', email: 'ana@example.com', phone: '+56912345678',
    health_insurance: 'Fonasa', medical_insurance: null, address: 'Calle 1',
    consent: true, is_active: true, has_account: true,
  }
  const calls: string[] = []
  vi.stubGlobal('fetch', vi.fn(async (input: string, init?: RequestInit) => {
    calls.push(`${init?.method ?? 'GET'} ${new URL(input).pathname}`)
    if (init?.method === 'PATCH') Object.assign(patient, JSON.parse(String(init.body)))
    return { ok: true, status: 200, json: async () => ({ data: structuredClone(patient) }) }
  }))

  const mount = () => render(<MemoryRouter><ProfilePage /></MemoryRouter>)
  const first = mount()
  await screen.findByDisplayValue('Ana')
  fireEvent.change(screen.getByDisplayValue('Ana'), { target: { value: 'Anita' } })
  fireEvent.submit(screen.getByRole('button', { name: 'Guardar cambios' }).closest('form')!)
  await waitFor(() => expect(patient.first_name).toBe('Anita'))
  first.unmount()

  mount()
  await screen.findByDisplayValue('Anita')
  expect(calls).toContain('GET /api/v1/patients/7')
  expect(calls).toContain('PATCH /api/v1/patients/7')
  expect(localStorage.length).toBe(0)
})
