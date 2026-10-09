import { describe, expect, it } from 'vitest'
import { canPatientModifyAppointment, canSetAppointmentStatus } from './appointment-rules'
import type { Appointment } from './types'

const appointment: Appointment = { id: 'a1', organizationId: 'org1', patientId: 'c1', professionalId: 'p1', specialtyId: 'e1', serviceId: 's1', date: '2026-09-10', time: '10:00', end: '10:30', status: 'CONFIRMADA', source: 'WEB', overbook: false, createdBy: 'u1' }

describe('reglas temporales de la agenda', () => {
  it('permite al paciente cambiar una cita con al menos 24 horas de aviso', () => {
    expect(canPatientModifyAppointment(appointment, new Date('2026-09-09T10:00:00'))).toBe(true)
    expect(canPatientModifyAppointment(appointment, new Date('2026-09-09T10:01:00'))).toBe(false)
  })

  it('evita cerrar citas antes de que ocurra la atención', () => {
    expect(canSetAppointmentStatus(appointment, 'ATENDIDA', new Date('2026-09-10T09:59:00'))).toBe(false)
    expect(canSetAppointmentStatus(appointment, 'ATENDIDA', new Date('2026-09-10T10:00:00'))).toBe(true)
    expect(canSetAppointmentStatus(appointment, 'NO_SHOW', new Date('2026-09-10T10:29:00'))).toBe(false)
    expect(canSetAppointmentStatus(appointment, 'NO_SHOW', new Date('2026-09-10T10:30:00'))).toBe(true)
  })
})
