// @vitest-environment jsdom
import { cleanup, fireEvent, render, screen, waitFor } from '@testing-library/react'
import { MemoryRouter } from 'react-router-dom'
import { afterEach, expect, it, vi } from 'vitest'
import { PatientBillingPage } from './patient-billing-page'

const { confirm } = vi.hoisted(() => ({ confirm: vi.fn(async () => ({ data: {} })) }))
vi.mock('@/state/clinic-store', () => ({ useClinic: () => ({ user: { role: 'ADMIN' } }) }))
vi.mock('@/services/patient-billing-api', () => ({ patientBillingApi: {
  summary: vi.fn(async () => ({ data: {
    charged_clp: 0, paid_clp: 0, balance_clp: 0, pending_indications: 1,
    patients: [], payments: [], indications: [{ id: 7, patient_id: 1, appointment_id: 4, service_id: 2,
      service_name: 'Examen', amount_clp: 10000, status: 'INDICATED', created_at: '2026-11-14', first_name: 'Ana', last_name: 'Soto' }],
  } })),
  confirm,
} }))

afterEach(() => { cleanup(); confirm.mockClear() })

it('separa la indicación pendiente del saldo y permite confirmar el cargo', async () => {
  render(<MemoryRouter><PatientBillingPage /></MemoryRouter>)
  expect(await screen.findByText('Ana Soto')).toBeTruthy()
  expect(screen.getByText(/Las indicaciones pendientes no son deuda/)).toBeTruthy()
  fireEvent.click(screen.getByRole('button', { name: 'Confirmar cargo' }))
  await waitFor(() => expect(confirm).toHaveBeenCalledWith(7))
})
