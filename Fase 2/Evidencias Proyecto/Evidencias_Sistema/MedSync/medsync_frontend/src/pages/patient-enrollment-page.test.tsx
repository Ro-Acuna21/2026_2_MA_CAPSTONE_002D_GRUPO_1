// @vitest-environment jsdom
import { cleanup, render, screen } from '@testing-library/react'
import { MemoryRouter, Route, Routes } from 'react-router-dom'
import { afterEach, expect, it, vi } from 'vitest'
import { PatientEnrollmentPage } from './patient-enrollment-page'

vi.mock('@/services/http', () => ({
  sanctum: {
    pendingEnrollment: vi.fn(async () => ({ data: {
      center: { id: 2, name: 'Centro B', slug: 'centro-b' },
      email: 'ana@example.test', name: 'Ana Pérez Soto',
      prefill: { first_name: 'Ana María', last_name: 'Pérez Soto', rut: '12345678-5', birth_date: '1990-05-10', phone: '912345678' },
    } })),
  },
  territorialCatalogApi: { list: vi.fn(async () => ({ data: [] })) },
  firstApiErrorMessage: () => 'Error de acceso',
}))

afterEach(cleanup)

it('precarga los datos personales verificados y permite revisarlos antes de confirmar', async () => {
  render(<MemoryRouter initialEntries={['/centro/centro-b/crear-ficha']}><Routes>
    <Route path="/centro/:centerSlug/crear-ficha" element={<PatientEnrollmentPage />} />
  </Routes></MemoryRouter>)

  expect(await screen.findByLabelText('Nombres')).toHaveProperty('value', 'Ana María')
  expect(screen.getByLabelText('Apellidos')).toHaveProperty('value', 'Pérez Soto')
  expect(screen.getByLabelText('RUT')).toHaveProperty('value', '12345678-5')
  expect(screen.getByLabelText('Fecha de nacimiento')).toHaveProperty('value', '1990-05-10')
  expect(screen.getByLabelText('Teléfono')).toHaveProperty('value', '912345678')
  expect(screen.getByLabelText('Previsión de salud')).toHaveProperty('value', '')
  expect(screen.getByText('ana@example.test')).toBeTruthy()
  expect(screen.getByRole('button', { name: 'Crear mi ficha en este centro' })).toBeTruthy()
})
