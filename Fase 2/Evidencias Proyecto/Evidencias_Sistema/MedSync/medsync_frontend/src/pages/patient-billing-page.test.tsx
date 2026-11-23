// @vitest-environment jsdom
import { cleanup, fireEvent, render, screen, waitFor } from '@testing-library/react'
import { MemoryRouter } from 'react-router-dom'
import { afterEach, expect, it, vi } from 'vitest'
import { PatientBillingPage } from './patient-billing-page'

const { confirm, pay } = vi.hoisted(() => ({ confirm: vi.fn(async () => ({ data: {} })), pay: vi.fn(async (_patientId: number, _payload: {idempotency_key: string}) => { void _patientId; void _payload; return {data: {}} }) }))
vi.mock('@/state/clinic-store', () => ({ useClinic: () => ({ user: { role: 'ADMIN' } }) }))
vi.mock('@/services/patient-billing-api', () => ({ patientBillingApi: {
  summary: vi.fn(async () => ({ data: {
    charged_clp: 0, paid_clp: 0, balance_clp: 0, pending_indications: 1,
    patients: [{id: 1, name: 'Ana Soto', rut: '12345678-5', charged_clp: 10000, paid_clp: 0, balance_clp: 10000}], payments: [],
    charges: [{id: 8, appointment_id: 4, patient_id: 1, service_id: 2, service_name: 'Examen', amount_clp: 10000, paid_clp: 0, balance_clp: 10000, payment_status: 'PENDING', confirmed_at: '2026-11-14'}],
    indications: [{ id: 7, patient_id: 1, appointment_id: 4, service_id: 2,
      service_name: 'Examen', amount_clp: 10000, status: 'INDICATED', created_at: '2026-11-14', first_name: 'Ana', last_name: 'Soto' }],
  } })),
  confirm,
  pay,
} }))

afterEach(() => { cleanup(); confirm.mockClear(); pay.mockClear() })

it('separa la indicación pendiente del saldo y permite confirmar el cargo', async () => {
  render(<MemoryRouter><PatientBillingPage /></MemoryRouter>)
  expect((await screen.findAllByText('Ana Soto')).length).toBeGreaterThan(0)
  expect(screen.getByText(/Las indicaciones pendientes no son deuda/)).toBeTruthy()
  fireEvent.click(screen.getByRole('button', { name: 'Confirmar cargo' }))
  await waitFor(() => expect(confirm).toHaveBeenCalledWith(7))
})

it('asigna el pago al cargo seleccionado', async () => {
  render(<MemoryRouter><PatientBillingPage /></MemoryRouter>)
  await screen.findAllByText('Ana Soto')
  fireEvent.change(screen.getByLabelText('Paciente'), {target: {value: '1'}})
  fireEvent.change(screen.getByLabelText('Cargo'), {target: {value: '8'}})
  fireEvent.click(screen.getByRole('button', {name: 'Completar saldo del cargo'}))
  fireEvent.click(screen.getByRole('button', {name: 'Registrar pago'}))
  await waitFor(() => expect(pay).toHaveBeenCalledWith(1, expect.objectContaining({
    amount_clp: 10000, allocations: [{care_indication_id: 8, amount_clp: 10000}],
  })))
})

it('reutiliza la clave del pago cuando se reintenta la misma operación', async () => {
  pay.mockRejectedValueOnce(new Error('Respuesta perdida'))
  render(<MemoryRouter><PatientBillingPage /></MemoryRouter>)
  await screen.findAllByText('Ana Soto')
  fireEvent.change(screen.getByLabelText('Paciente'), {target: {value: '1'}})
  fireEvent.change(screen.getByLabelText('Cargo'), {target: {value: '8'}})
  fireEvent.click(screen.getByRole('button', {name: 'Completar saldo del cargo'}))
  fireEvent.click(screen.getByRole('button', {name: 'Registrar pago'}))
  await waitFor(() => expect(pay).toHaveBeenCalledTimes(1))
  fireEvent.click(screen.getByRole('button', {name: 'Registrar pago'}))
  await waitFor(() => expect(pay).toHaveBeenCalledTimes(2))
  const first = pay.mock.calls[0][1]
  const second = pay.mock.calls[1][1]
  expect(first.idempotency_key).toMatch(/^[0-9a-f-]{36}$/i)
  expect(second.idempotency_key).toBe(first.idempotency_key)
})
