// @vitest-environment jsdom
import { cleanup, fireEvent, render, screen, waitFor } from '@testing-library/react'
import { MemoryRouter } from 'react-router-dom'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import App from './App'
import { ClinicProvider } from './state/clinic-store'
import { backendUser, centers, stubLaravelApi } from './test/laravel-api'

beforeEach(() => { localStorage.clear(); sessionStorage.clear() })
afterEach(() => { cleanup(); vi.unstubAllGlobals() })
const open = (path: string) => render(<MemoryRouter initialEntries={[path]}><ClinicProvider><App /></ClinicProvider></MemoryRouter>)

describe('navegación por roles con sesión Laravel', () => {
  it('abre plataforma con sesión de SUPER_ADMIN, sin centro clínico activo', async () => {
    stubLaravelApi({ session: backendUser('SUPER_ADMIN') })
    open('/plataforma')
    expect(await screen.findByRole('heading', { name: 'Comercial y suscripciones' })).toBeDefined()
    expect(screen.getByRole('button', { name: 'Crear centro' })).toBeDefined()
    expect(screen.queryByRole('link', { name: 'Agenda' })).toBeNull()
  })

  it('crea un centro desde plataforma mediante la API y actualiza el directorio público', async () => {
    const api = stubLaravelApi({ session: backendUser('SUPER_ADMIN'), centers: [] })
    open('/plataforma')
    fireEvent.click(await screen.findByRole('button', { name: 'Crear centro' }))
    fireEvent.change(screen.getByLabelText('Nombre del centro'), { target: { value: 'Centro Nuevo' } })
    fireEvent.change(screen.getByLabelText('Dirección del centro en la plataforma'), { target: { value: 'centro-nuevo' } })
    fireEvent.change(screen.getByLabelText('RUT del centro'), { target: { value: '12.345.678-5' } })
    fireEvent.click(screen.getByRole('button', { name: 'Crear centro' }))
    expect(await screen.findByText('Centro Nuevo')).toBeDefined()
    expect(api.requests.some((request) => request.path === '/api/v1/platform/centers' && request.method === 'POST' && request.body?.slug === 'centro-nuevo')).toBe(true)
  })

  it('cambia el rol del personal en la membresía existente desde plataforma', async () => {
    const api = stubLaravelApi({
      session: backendUser('SUPER_ADMIN'),
      platformCenters: [{ id: 1, name: 'Clínica Horizonte', slug: 'clinica-horizonte', rut: '76543210-3',
        phone: null, email: null, plan: 'STARTER', subscription_status: 'ACTIVE', is_active: true,
        provisioning_status: 'READY', provisioned_at: null }],
      receptionists: [{ id: 44, user_id: 9, name: 'Equipo Prueba', email: 'equipo@example.test',
        role: 'RECEPCIONISTA', is_active: true, invitation_pending: false, invitation_sent_at: null }],
    })
    open('/plataforma')
    fireEvent.click(await screen.findByRole('button', { name: 'Detalle' }))
    const role = await screen.findByRole('combobox', { name: 'Rol de Equipo Prueba' })
    fireEvent.change(role, { target: { value: 'ADMIN' } })
    fireEvent.click(screen.getByRole('button', { name: 'Guardar rol' }))
    await waitFor(() => expect(api.requests.some((request) => request.path === '/api/v1/platform/centers/1/staff/44/role' && request.method === 'PATCH' && request.body?.role === 'ADMIN')).toBe(true))
  })

  it('redirige al SUPER_ADMIN fuera de una ruta clínica', async () => {
    stubLaravelApi({ session: backendUser('SUPER_ADMIN') })
    open('/centro/clinica-horizonte/pacientes')
    expect(await screen.findByRole('heading', { name: 'Comercial y suscripciones' })).toBeDefined()
    expect(screen.queryByRole('heading', { name: 'Pacientes' })).toBeNull()
  })

  it('muestra registro público del centro validado por Core', async () => {
    stubLaravelApi({ centers: [centers[0]] })
    open('/centro/clinica-horizonte/crear-cuenta')
    expect(await screen.findByLabelText('Previsión de salud')).toBeDefined()
    expect(screen.getByLabelText('Confirmar contraseña')).toBeDefined()
    expect(screen.getByRole('button', { name: 'Crear mi cuenta' })).toBeDefined()
  })

  it('solicita una ficha local a la cuenta global autenticada sin pedir otra contraseña', async () => {
    stubLaravelApi({ pendingEnrollment: { center: centers[1], email: 'existing@example.test' } })
    open('/centro/centro-alameda/crear-ficha')
    expect(await screen.findByText('existing@example.test')).toBeDefined()
    expect(screen.getByLabelText('RUT')).toBeDefined()
    expect(screen.getByRole('button', { name: 'Crear mi ficha en este centro' })).toBeDefined()
    expect(screen.queryByLabelText('Contraseña')).toBeNull()
    expect(screen.queryByLabelText('Correo electrónico')).toBeNull()
  })

  it('mantiene el acceso de plataforma solo en su dirección específica', async () => {
    stubLaravelApi({ centers: [centers[0]] })
    const regular = open('/centro/clinica-horizonte/ingresar')
    await screen.findByRole('button', { name: 'Ingresar' })
    expect(screen.queryByText('Acceso de plataforma')).toBeNull()
    regular.unmount()
    open('/plataforma/acceso')
    expect(await screen.findByText('Acceso de plataforma')).toBeDefined()
    expect(screen.getByRole('button', { name: 'Ingresar a la plataforma' })).toBeDefined()
  })

  it('ofrece solo centros activos publicados por Core antes del login contextual', async () => {
    stubLaravelApi({ centers: [centers[0]] })
    open('/ingresar')
    expect((await screen.findByRole('link', { name: /Ingresar a Clínica Horizonte/i })).getAttribute('href')).toBe('/centro/clinica-horizonte/ingresar')
    expect(screen.queryByRole('link', { name: /Ingresar a Centro Médico Alameda/i })).toBeNull()
    expect(screen.getByRole('link', { name: /Ir al acceso de plataforma/i }).getAttribute('href')).toBe('/plataforma/acceso')
  })

  it('redirige a ADMIN hacia configuración y oculta rutas operativas', async () => {
    stubLaravelApi({ session: backendUser('ADMIN', 'clinica-horizonte') })
    open('/centro/clinica-horizonte/citas')
    expect(await screen.findByRole('heading', { name: 'Administración del centro' })).toBeDefined()
    expect(screen.queryByRole('link', { name: 'Agenda' })).toBeNull()
    expect(screen.queryByRole('link', { name: 'Reservar hora' })).toBeNull()
    expect(screen.queryByRole('link', { name: 'Pacientes' })).toBeNull()
    expect(await screen.findByText('Permisos por rol')).toBeDefined()
    expect(screen.getByText(/Los permisos de estos roles son fijos/)).toBeDefined()
    expect(await screen.findByText('patients.manage')).toBeDefined()
  })

  it('agrupa horarios reales por profesional y desactiva con la API existente', async () => {
    const api = stubLaravelApi({
      session: backendUser('ADMIN', 'clinica-horizonte'),
      professionals: [{ id: 17, user_id: null, first_name: 'Ana', last_name: 'Rojas', rut: '11111111-1', email: 'ana@example.test', phone: '+56911111111', is_active: true, specialty_ids: [], specialties: [], description: null }],
      availabilities: [
        { id: 1, professional_id: 17, weekday: 1, start_time: '09:00', end_time: '12:00', is_active: true },
        { id: 2, professional_id: 17, weekday: 3, start_time: '10:00', end_time: '13:00', is_active: true },
      ],
    })
    open('/centro/clinica-horizonte/administracion')
    const summary = await screen.findByText('2 bloques')
    expect(summary.closest('summary')?.textContent).toContain('Ana Rojas')
    fireEvent.click(summary.closest('summary')!)
    expect(screen.getByText((_, element) => element?.tagName === 'P' && element.textContent?.includes('Lunes · 09:00–12:00') === true)).toBeDefined()
    expect(screen.getByText((_, element) => element?.tagName === 'P' && element.textContent?.includes('Miércoles · 10:00–13:00') === true)).toBeDefined()
    fireEvent.click(screen.getAllByRole('button', { name: 'Desactivar' })[0])
    await waitFor(() => expect(api.requests.some((request) => request.path === '/api/v1/availabilities/1' && request.method === 'PATCH' && request.body?.is_active === false)).toBe(true))
  })

  it('crea una especialidad global mediante la API de plataforma', async () => {
    const api = stubLaravelApi({ session: backendUser('SUPER_ADMIN') })
    open('/plataforma/especialidades')
    expect(await screen.findByRole('heading', { name: 'Catálogo global de especialidades' })).toBeDefined()
    expect(await screen.findByText('Todavía no hay especialidades globales.')).toBeDefined()
    fireEvent.change(screen.getByLabelText('Nombre'), { target: { value: 'Cardiología' } })
    fireEvent.click(screen.getByRole('button', { name: 'Crear especialidad' }))
    expect(await screen.findByText('Cardiología · Activa')).toBeDefined()
    expect(api.requests.some((request) => request.path === '/api/v1/platform/specialties' && request.method === 'POST' && request.body?.name === 'Cardiología')).toBe(true)
  })

  it('vuelve al acceso del centro cuando expira la sesión al cargar reservas', async () => {
    stubLaravelApi({ session: backendUser('PACIENTE', 'clinica-horizonte'), unauthorizedServices: true })
    open('/centro/clinica-horizonte/reservar')
    expect(await screen.findByRole('heading', { name: 'Acceso a Clínica Horizonte' })).toBeDefined()
    expect(screen.queryByRole('heading', { name: 'Reservar hora' })).toBeNull()
  })

  it('no ofrece cambio libre de centro dentro del registro contextual', async () => {
    stubLaravelApi({ centers: [centers[1]] })
    open('/centro/centro-alameda/crear-cuenta')
    expect(await screen.findByText('Crea tu acceso privado a Centro Médico Alameda.')).toBeDefined()
    expect(screen.queryByLabelText('Centro médico')).toBeNull()
    expect(screen.queryByText(/vincular otro centro/i)).toBeNull()
  })
})
