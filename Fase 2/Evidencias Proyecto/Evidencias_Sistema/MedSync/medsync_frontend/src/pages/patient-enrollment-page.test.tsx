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
    } })),
  },
  territorialCatalogApi: { list: vi.fn(async () => ({ data: [] })) },
  firstApiErrorMessage: () => 'Error de acceso',
}))

afterEach(cleanup)

it('completa el nombre desde la cuenta y permite revisarlo antes de confirmar', async () => {
  render(<MemoryRouter initialEntries={['/centro/centro-b/crear-ficha']}><Routes>
    <Route path="/centro/:centerSlug/crear-ficha" element={<PatientEnrollmentPage />} />
  </Routes></MemoryRouter>)

  expect(await screen.findByLabelText('Nombres')).toHaveProperty('value', 'Ana')
  expect(screen.getByLabelText('Apellidos')).toHaveProperty('value', 'Pérez Soto')
  expect(screen.getByText('ana@example.test')).toBeTruthy()
  expect(screen.getByRole('button', { name: 'Crear mi ficha en este centro' })).toBeTruthy()
})
