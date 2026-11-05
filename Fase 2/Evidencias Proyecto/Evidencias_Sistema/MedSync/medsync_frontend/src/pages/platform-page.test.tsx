// @vitest-environment jsdom
import { cleanup, fireEvent, render, screen, waitFor } from '@testing-library/react'
import { MemoryRouter } from 'react-router-dom'
import { afterEach, expect, it, vi } from 'vitest'
import { PlatformPage } from './platform-page'

vi.mock('@/state/clinic-store', () => ({
  useClinic: () => ({ user: { role: 'SUPER_ADMIN' } }),
}))

afterEach(() => { cleanup(); vi.unstubAllGlobals() })

it('filtra comunas del centro y envía códigos validados al backend', async () => {
  let payload: Record<string, unknown> | null = null
  vi.stubGlobal('fetch', vi.fn(async (input: string, init?: RequestInit) => {
    const path = new URL(input).pathname
    if (path === '/api/public/territorial-catalog') return { ok: true, status: 200, json: async () => ({ data: [
      { code: '13', name: 'Metropolitana de Santiago', communes: [{ code: '13101', name: 'Santiago' }] },
      { code: '05', name: 'Valparaíso', communes: [{ code: '05101', name: 'Valparaíso' }] },
    ] }) }
    if (path === '/api/v1/platform/centers' && init?.method === 'POST') {
      payload = JSON.parse(String(init.body))
      return { ok: true, status: 201, json: async () => ({ data: { id: 1, ...payload, provisioning_status: 'READY' } }) }
    }
    return { ok: true, status: 200, json: async () => ({ data: [] }) }
  }))

  render(<MemoryRouter><PlatformPage /></MemoryRouter>)
  await screen.findByText('Todavía no hay centros registrados.')
  fireEvent.click(screen.getByRole('button', { name: 'Crear centro' }))
  await screen.findByRole('option', { name: 'Metropolitana de Santiago' })
  fireEvent.change(screen.getByLabelText('Nombre del centro'), { target: { value: 'Centro Nuevo' } })
  fireEvent.change(screen.getByLabelText('Dirección del centro en la plataforma'), { target: { value: 'centro-nuevo' } })
  fireEvent.change(screen.getByLabelText('RUT del centro'), { target: { value: '12.345.678-5' } })
  fireEvent.change(screen.getByLabelText('Dirección (opcional)'), { target: { value: 'Calle 1' } })
  fireEvent.change(screen.getByLabelText('Región (opcional)'), { target: { value: '13' } })
  expect((screen.getByLabelText('Comuna (opcional)') as HTMLSelectElement).options.length).toBe(2)
  fireEvent.change(screen.getByLabelText('Comuna (opcional)'), { target: { value: '13101' } })
  fireEvent.submit(screen.getAllByRole('button', { name: 'Crear centro' }).at(-1)!.closest('form')!)
  await waitFor(() => expect(payload).toMatchObject({ region_code: '13', commune_code: '13101' }))
});
