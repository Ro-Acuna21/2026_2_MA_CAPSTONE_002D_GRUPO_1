// @vitest-environment jsdom
import { cleanup, render, screen } from '@testing-library/react'
import { MemoryRouter } from 'react-router-dom'
import { afterEach, expect, it, vi } from 'vitest'
import { DashboardPage } from './dashboard-page'

vi.mock('@/state/clinic-store', () => ({useClinic: () => ({
  user: {role: 'PACIENTE', name: 'Ana Soto', patientId: '1'}, data: {appointments: []},
})}))
vi.mock('@/lib/tenant', () => ({useCenterPath: () => (path: string) => `/centro/demo${path}`}))
vi.mock('@/services/patient-billing-api', () => ({patientBillingApi: {mine: vi.fn(async () => ({data: {
  charged_clp: 10000, paid_clp: 4000, balance_clp: 6000,
  charges: [{id: 5, appointment_id: 3, patient_id: 1, service_id: 2,
    service_name: 'Examen', amount_clp: 10000, paid_clp: 4000, balance_clp: 6000,
    payment_status: 'PARTIAL', confirmed_at: '2026-11-20'}],
}}))}}))

afterEach(cleanup)

it('muestra al paciente el cargo, abono, saldo y estado parcial', async () => {
  render(<MemoryRouter><DashboardPage /></MemoryRouter>)
  expect(await screen.findByText(/Pago parcial/)).toBeTruthy()
  expect(screen.getByText(/Saldo pendiente/)).toBeTruthy()
  expect(screen.getByText(/Examen/)).toBeTruthy()
})
