// @vitest-environment jsdom
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { MemoryRouter } from 'react-router-dom'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { PublicPlansPage } from './public-plans-page'
import { planApi } from '@/services/subscription-api'
import { commercialInquiryApi } from '@/services/commercial-inquiry-api'

vi.mock('@/services/subscription-api', () => ({ planApi: { publicList: vi.fn() } }))
vi.mock('@/services/commercial-inquiry-api', () => ({ commercialInquiryApi: { create: vi.fn() } }))

describe('planes públicos', () => {
  beforeEach(() => {
    vi.mocked(planApi.publicList).mockResolvedValue({ data: [{ id: 2, name: 'Profesional', code: 'PRO', description: 'Para equipos de salud', monthly_price_clp: 89990, professional_limit: 12, user_limit: 30, features: ['Agenda y reservas'], is_active: true }] })
    vi.mocked(commercialInquiryApi.create).mockResolvedValue({ message: 'Solicitud recibida.' })
  })

  it('muestra detalles reales y registra una consulta por el plan elegido', async () => {
    render(<MemoryRouter initialEntries={['/planes?plan=2']}><PublicPlansPage /></MemoryRouter>)
    expect(await screen.findByText('Para equipos de salud')).toBeDefined()
    expect(screen.getByText('Agenda y reservas')).toBeDefined()
    expect(screen.getByText(/89\.990/)).toBeDefined()

    fireEvent.change(screen.getByLabelText('Nombre de contacto'), { target: { value: 'Persona Prueba' } })
    fireEvent.change(screen.getByLabelText('Nombre del centro'), { target: { value: 'Centro Ficticio' } })
    fireEvent.change(screen.getByLabelText('Correo electrónico'), { target: { value: 'contacto@example.test' } })
    fireEvent.click(screen.getByRole('button', { name: 'Enviar solicitud' }))

    await waitFor(() => expect(commercialInquiryApi.create).toHaveBeenCalledWith(expect.objectContaining({ plan_id: 2, center_name: 'Centro Ficticio' })))
    expect(await screen.findByText('Solicitud recibida.')).toBeDefined()
  })
})
