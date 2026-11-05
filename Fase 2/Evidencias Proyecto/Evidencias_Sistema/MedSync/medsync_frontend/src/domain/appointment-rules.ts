import type { Appointment, AppointmentStatus } from './types'

export const PATIENT_CHANGE_NOTICE_HOURS = 24

function scheduledAt(appointment: Appointment, time = appointment.time) {
  return new Date(`${appointment.date}T${time}:00`)
}

export function canPatientModifyAppointment(appointment: Appointment, now = new Date()) {
  return scheduledAt(appointment).getTime() - now.getTime() >= PATIENT_CHANGE_NOTICE_HOURS * 60 * 60 * 1000
}

export function canSetAppointmentStatus(appointment: Appointment, status: AppointmentStatus, now = new Date()) {
  if (status === 'ATENDIDA') return now >= scheduledAt(appointment)
  if (status === 'NO_SHOW') return now >= scheduledAt(appointment, appointment.end)
  return true
}
