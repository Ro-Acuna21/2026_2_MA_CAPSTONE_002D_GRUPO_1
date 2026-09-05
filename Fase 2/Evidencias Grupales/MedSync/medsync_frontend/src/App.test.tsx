// @vitest-environment jsdom
import { cleanup, render, screen } from '@testing-library/react'
import { MemoryRouter } from 'react-router-dom'
import { afterEach, beforeEach, describe, expect, it } from 'vitest'
import App from './App'
import { ClinicProvider } from './state/clinic-store'

beforeEach(() => { localStorage.clear(); sessionStorage.clear() })
afterEach(cleanup)
const open = (path: string) => render(<MemoryRouter initialEntries={[path]}><ClinicProvider><App /></ClinicProvider></MemoryRouter>)
describe('navegación por roles', () => {
  it('abre centros y suscripciones sin organización activa', async () => {
    sessionStorage.setItem('clinica_horizonte_user', 'platform'); open('/plataforma')
    expect(await screen.findByRole('heading', { name: 'Administración de plataforma' })).toBeDefined()
    expect(screen.getByRole('button', { name: 'Crear centro' })).toBeDefined()
    expect(screen.queryByRole('link', { name: 'Agenda' })).toBeNull()
  })
  it('impide al superadministrador entrar a una ruta clínica', async () => {
    sessionStorage.setItem('clinica_horizonte_user', 'platform'); open('/centro/clinica-horizonte/pacientes')
    expect(await screen.findByRole('heading', { name: 'Administración de plataforma' })).toBeDefined()
  })
  it('muestra el registro público con previsión y contraseña', () => {
    open('/centro/clinica-horizonte/crear-cuenta')
    expect(screen.getByLabelText('Previsión de salud')).toBeDefined()
    expect(screen.getByLabelText('Confirmar contraseña')).toBeDefined()
    expect(screen.getByRole('button', { name: 'Crear mi cuenta' })).toBeDefined()
  })
  it('muestra el acceso de plataforma solo en su dirección específica', () => {
    const regular = open('/centro/clinica-horizonte/ingresar')
    expect(screen.queryByRole('button', { name: 'Superadministrador' })).toBeNull()
    expect(screen.queryByText('Acceso de plataforma')).toBeNull()
    regular.unmount()
    open('/plataforma/acceso')
    expect(screen.getByText('Acceso de plataforma')).toBeDefined()
    expect(screen.getByRole('button', { name: 'Ingresar a la plataforma' })).toBeDefined()
  })
  it('redirige al administrador a configuración y bloquea rutas operativas', async () => {
    sessionStorage.setItem('clinica_horizonte_user', 'u1'); sessionStorage.setItem('clinica_horizonte_organization', 'org1'); open('/centro/clinica-horizonte/citas')
    expect(await screen.findByRole('heading', { name: 'Administración del centro' })).toBeDefined()
    expect(screen.queryByRole('link', { name: 'Agenda' })).toBeNull()
    expect(screen.queryByRole('link', { name: 'Reservar hora' })).toBeNull()
    expect(screen.queryByRole('link', { name: 'Pacientes' })).toBeNull()
  })
  it('no muestra un selector de clínicas en el registro del paciente', () => {
    open('/centro/centro-alameda/crear-cuenta')
    expect(screen.getByText('Crea tu acceso privado a Centro Médico Alameda.')).toBeDefined()
    expect(screen.queryByLabelText('Centro médico')).toBeNull()
    expect(screen.queryByText(/vincular otro centro/i)).toBeNull()
  })
})
