// @vitest-environment jsdom
import { cleanup, fireEvent, render, screen, waitFor } from '@testing-library/react'
import { afterEach, expect, it, vi } from 'vitest'
import { AccessForm } from './access-form'
import { backendUser, stubLaravelApi } from '@/test/laravel-api'

afterEach(() => { cleanup(); vi.unstubAllGlobals() })

it('persists a receptionist invitation through the tenant API', async () => {
  const api = stubLaravelApi({ session: backendUser('ADMIN', 'clinica-horizonte') })
  render(<AccessForm centerId="1" />)
  expect(await screen.findByText('Todavía no hay accesos asignados.')).toBeDefined()
  fireEvent.change(screen.getByLabelText('Nombre de la persona'), { target: { value: 'Recepción Nueva' } })
  fireEvent.change(screen.getByLabelText('Correo de la cuenta'), { target: { value: 'reception@example.test' } })
  fireEvent.click(screen.getByRole('button', { name: 'Asignar recepción' }))
  await waitFor(() => expect(api.requests.some((request) => request.path === '/api/v1/center/receptionists' && request.method === 'POST')).toBe(true))
  expect(await screen.findByText(/reception@example.test/)).toBeDefined()
})
