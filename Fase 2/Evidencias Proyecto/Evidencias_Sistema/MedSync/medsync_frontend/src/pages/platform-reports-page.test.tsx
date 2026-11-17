// @vitest-environment jsdom
import { cleanup, render, screen } from '@testing-library/react'
import { afterEach, expect, it, vi } from 'vitest'
import { PlatformReportsPage } from './platform-reports-page'

vi.mock('@/services/platform-report-api', () => ({
  platformReportApi: { summary: vi.fn(async () => ({ data: {
    total_centers: 2, active_centers: 1, total_platform_users: 4,
    unique_patients: 1, patient_center_registrations: 2,
    patients_in_multiple_centers: 1,
    centers: [
      { id: 1, name: 'Centro A', slug: 'centro-a', is_active: true, patient_count: 1 },
      { id: 2, name: 'Centro B', slug: 'centro-b', is_active: false, patient_count: 1 },
    ],
  } })) },
}))

afterEach(cleanup)

it('muestra pacientes únicos y sus inscripciones por centro por separado', async () => {
  render(<PlatformReportsPage />)
  expect(await screen.findByText('Pacientes únicos con cuenta')).toBeTruthy()
  expect(screen.getByText('Inscripciones de pacientes')).toBeTruthy()
  expect(screen.getByText('Centro A')).toBeTruthy()
  expect(screen.getByText('Centro B')).toBeTruthy()
  expect(screen.queryByText('Cuentas sin centro')).toBeNull()
})
