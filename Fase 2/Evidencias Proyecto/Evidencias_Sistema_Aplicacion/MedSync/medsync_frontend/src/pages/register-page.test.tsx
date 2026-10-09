// @vitest-environment jsdom
import { cleanup, fireEvent, render, screen, waitFor } from '@testing-library/react'
import { MemoryRouter, Route, Routes } from 'react-router-dom'
import { afterEach, expect, it, vi } from 'vitest'
import { RegisterPage } from './register-page'
import { ClinicProvider } from '@/state/clinic-store'

afterEach(() => {
  cleanup()
  vi.unstubAllGlobals()
  localStorage.clear()
  sessionStorage.clear()
})

it('envía al backend el slug del centro obtenido desde Core y lo reutiliza en el login', async () => {
  const requests: { path: string; body?: Record<string, string> }[] = []
  vi.stubGlobal('fetch', vi.fn(async (input: string, init?: RequestInit) => {
    const path = new URL(input).pathname
    requests.push({ path, body: init?.body ? JSON.parse(String(init.body)) : undefined })
    if (path === '/api/public/centers') return { ok: true, status: 200, json: async () => ({ data: [{ id: 2, name: 'Centro B', slug: 'centro-b' }] }) }
    if (path === '/sanctum/csrf-cookie') return { ok: true, status: 204 }
    if (path === '/api/register') return { ok: true, status: 201, json: async () => ({ data: {} }) }
    if (path === '/api/login') return { ok: true, status: 200, json: async () => ({ data: {
      id: 7, name: 'Juan Pérez', email: 'juan@example.com', role: 'PACIENTE',
      medical_center: { id: 2, name: 'Centro B', slug: 'centro-b' },
      patient: { id: 12 }, professional: null,
    } }) }
    if (path === '/api/v1/services' || path === '/api/v1/appointments/my') return { ok: true, status: 200, json: async () => ({ data: [] }) }
    return { ok: false, status: 401, json: async () => ({ message: 'Sin sesión' }) }
  }))

  render(
    <MemoryRouter initialEntries={['/centro/centro-b/crear-cuenta']}>
      <ClinicProvider>
        <Routes><Route path="/centro/:centerSlug/crear-cuenta" element={<RegisterPage />} /><Route path="/centro/:centerSlug/ingresar" element={<p>Acceso del centro</p>} /><Route path="/centro/:centerSlug" element={<p>Portal del centro</p>} /></Routes>
      </ClinicProvider>
    </MemoryRouter>,
  )

  await screen.findByText('Crea tu acceso privado a Centro B.')
  fireEvent.change(screen.getByLabelText('Nombres'), { target: { value: 'Juan' } })
  fireEvent.change(screen.getByLabelText('Apellidos'), { target: { value: 'Pérez' } })
  fireEvent.change(screen.getByLabelText('RUT'), { target: { value: '12.345.678-5' } })
  fireEvent.change(screen.getByLabelText('Fecha de nacimiento'), { target: { value: '1990-05-10' } })
  fireEvent.change(screen.getByLabelText('Correo electrónico'), { target: { value: 'juan@example.com' } })
  fireEvent.change(screen.getByLabelText('Teléfono'), { target: { value: '+56 9 1234 5678' } })
  fireEvent.change(screen.getByLabelText('Previsión de salud'), { target: { value: 'Fonasa' } })
  fireEvent.change(screen.getByLabelText('Contraseña'), { target: { value: 'Password123' } })
  fireEvent.change(screen.getByLabelText('Confirmar contraseña'), { target: { value: 'Password123' } })
  fireEvent.click(screen.getByRole('checkbox'))
  fireEvent.submit(screen.getByRole('button', { name: 'Crear mi cuenta' }).closest('form')!)

  await waitFor(() => expect(requests.some((request) => request.path === '/api/login')).toBe(true))
  expect(requests.find((request) => request.path === '/api/register')?.body?.center_slug).toBe('centro-b')
  expect(requests.find((request) => request.path === '/api/login')?.body?.center_slug).toBe('centro-b')
})
