// @vitest-environment jsdom
import { cleanup, fireEvent, render, screen, waitFor } from '@testing-library/react'
import { MemoryRouter } from 'react-router-dom'
import { afterEach, expect, it, vi } from 'vitest'
import { PatientsPage } from './patients-page'
import type { BackendPatient } from '@/services/http'

vi.mock('@/state/clinic-store', () => ({
  useClinic: () => ({ user: { role: 'RECEPCIONISTA', organizationIds: ['center-1'] } }),
}))

afterEach(() => { cleanup(); vi.unstubAllGlobals(); localStorage.clear() })

it('obtiene fichas de API y conserva alta y edición tras montar de nuevo la pantalla', async () => {
  const records: BackendPatient[] = []
  const calls: string[] = []
  vi.stubGlobal('fetch', vi.fn(async (input: string, init?: RequestInit) => {
    const path = new URL(input).pathname
    calls.push(`${init?.method ?? 'GET'} ${path}`)
    let data: BackendPatient | BackendPatient[]
    let status = 200
    if (path === '/api/v1/patients' && init?.method === 'POST') {
      const body = JSON.parse(String(init.body))
      data = { id: 1, first_name: body.first_name, last_name: body.last_name, rut: body.rut,
        birth_date: body.birth_date, email: body.email, phone: body.phone,
        health_insurance: body.health_insurance, medical_insurance: body.medical_insurance,
        address: body.address, consent: true, is_active: true, has_account: false }
      records.push(data)
      status = 201
    } else if (path === '/api/v1/patients' && !init?.method) {
      data = records
    } else if (path === '/api/v1/patients/1' && init?.method === 'PATCH') {
      Object.assign(records[0], JSON.parse(String(init.body)))
      data = records[0]
    } else {
      data = records[0]
    }
    return { ok: true, status, json: async () => ({ data: structuredClone(data) }) }
  }))

  const mount = () => render(<MemoryRouter><PatientsPage /></MemoryRouter>)
  const first = mount()
  await screen.findByText('No hay pacientes para mostrar.')
  fireEvent.click(screen.getByRole('button', { name: 'Registrar paciente' }))
  fireEvent.change(screen.getByLabelText('Nombres'), { target: { value: 'Carla' } })
  fireEvent.change(screen.getByLabelText('Apellidos'), { target: { value: 'Paciente' } })
  fireEvent.change(screen.getByLabelText('RUT'), { target: { value: '12.345.678-5' } })
  fireEvent.change(screen.getByLabelText('Fecha de nacimiento'), { target: { value: '1995-02-03' } })
  fireEvent.change(screen.getByLabelText('Correo electrónico'), { target: { value: 'carla@example.com' } })
  fireEvent.change(screen.getByLabelText('Teléfono'), { target: { value: '+56 9 1234 5678' } })
  fireEvent.change(screen.getByLabelText('Previsión de salud'), { target: { value: 'Fonasa' } })
  fireEvent.click(screen.getByRole('checkbox'))
  fireEvent.submit(screen.getByRole('button', { name: 'Guardar paciente' }).closest('form')!)
  await screen.findByText('Carla Paciente')
  first.unmount()

  const second = mount()
  await screen.findByText('Carla Paciente')
  fireEvent.click(screen.getByRole('button', { name: 'Ver detalle' }))
  await waitFor(() => expect(calls).toContain('GET /api/v1/patients/1'))
  fireEvent.click(screen.getByRole('button', { name: 'Editar ficha' }))
  fireEvent.change(screen.getByLabelText('Teléfono'), { target: { value: '+56 9 8765 4321' } })
  fireEvent.submit(screen.getByRole('button', { name: 'Guardar cambios' }).closest('form')!)
  await waitFor(() => expect(records[0].phone).toBe('+56 9 8765 4321'))
  second.unmount()

  mount()
  await screen.findByText('+56 9 8765 4321')
  expect(calls.filter((call) => call === 'GET /api/v1/patients')).toHaveLength(3)
  expect(calls).toContain('POST /api/v1/patients')
  expect(calls).toContain('PATCH /api/v1/patients/1')
  expect(localStorage.length).toBe(0)
})
