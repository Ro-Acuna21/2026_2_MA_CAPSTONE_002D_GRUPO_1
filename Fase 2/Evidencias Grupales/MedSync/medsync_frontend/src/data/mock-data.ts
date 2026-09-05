import { permissionsFor } from '@/domain/permissions'
import type { ClinicData, Role } from '@/domain/types'
import { nextBusinessDay, uid } from '@/lib/utils'
import { rutDv } from '@/domain/validation'

const user = (id: string, name: string, email: string, role: Role, organizationIds: string[], relation: Record<string, string> = {}) =>
  ({ id, name, email, role, organizationIds, permissions: permissionsFor(role), ...relation })

export function createMockData(): ClinicData {
  const organizations = [
    { id: 'org1', name: 'Clínica Horizonte', slug: 'clinica-horizonte', plan: 'PRO' as const, active: true },
    { id: 'org2', name: 'Centro Médico Alameda', slug: 'centro-alameda', plan: 'STARTER' as const, active: true },
  ]
  const specialties = [
    { id: 'e1', organizationId: 'org1', name: 'Medicina general', description: 'Atención integral para personas adultas.', active: true },
    { id: 'e2', organizationId: 'org1', name: 'Kinesiología', description: 'Evaluación y rehabilitación musculoesquelética.', active: true },
    { id: 'e3', organizationId: 'org1', name: 'Nutrición', description: 'Evaluación y orientación nutricional.', active: true },
    { id: 'e4', organizationId: 'org2', name: 'Pediatría', description: 'Atención integral infantil.', active: true },
  ]
  const services = [
    { id: 's1', organizationId: 'org1', specialtyId: 'e1', name: 'Consulta general', duration: 30, active: true },
    { id: 's2', organizationId: 'org1', specialtyId: 'e1', name: 'Control', duration: 20, active: true },
    { id: 's3', organizationId: 'org1', specialtyId: 'e2', name: 'Evaluación kinésica', duration: 45, active: true },
    { id: 's4', organizationId: 'org1', specialtyId: 'e2', name: 'Sesión de kinesiología', duration: 45, active: true },
    { id: 's5', organizationId: 'org1', specialtyId: 'e3', name: 'Consulta nutricional', duration: 40, active: true },
    { id: 's6', organizationId: 'org2', specialtyId: 'e4', name: 'Consulta pediátrica', duration: 30, active: true },
  ]
  const professionals = [
    { id: 'p1', organizationId: 'org1', name: 'Dra. Camila Rojas', rut: '11.111.111-1', email: 'camila.rojas@demo.cl', phone: '+56 9 5555 0101', specialtyIds: ['e1'], description: 'Medicina familiar y atención ambulatoria.', active: true },
    { id: 'p2', organizationId: 'org1', name: 'Matías Soto', rut: '12.222.222-2', email: 'matias.soto@demo.cl', phone: '+56 9 5555 0202', specialtyIds: ['e2'], description: 'Kinesiólogo musculoesquelético.', active: true },
    { id: 'p3', organizationId: 'org1', name: 'Valentina Muñoz', rut: '13.333.333-3', email: 'valentina.munoz@demo.cl', phone: '+56 9 5555 0303', specialtyIds: ['e3'], description: 'Nutricionista clínica.', active: true },
    { id: 'p4', organizationId: 'org2', name: 'Dra. Emilia Silva', rut: '14.444.444-4', email: 'emilia.silva@demo.cl', phone: '+56 9 5555 0404', specialtyIds: ['e4'], description: 'Pediatra general.', active: true },
  ]
  const patients = Array.from({ length: 10 }, (_, index) => ({
    id: `c${index + 1}`, organizationId: 'org1', rut: `20.000.00${index + 1}-${(index + 1) % 10}`, name: `Paciente ${index + 1} Ejemplo`,
    email: `paciente${index + 1}@demo.cl`, phone: '+56 9 4444 0000', birthDate: '1990-01-15', address: 'Santiago', consent: true, active: true,
  }))
  patients.push({ id: 'c11', organizationId: 'org2', rut: '21.111.111-1', name: 'Sofía Contreras', email: 'sofia@demo.cl', phone: '+56 9 4444 1111', birthDate: '2018-04-10', address: 'Santiago', consent: true, active: true })
  const users = [
    user('platform', 'Administración MedSync', 'superadmin@demo.cl', 'SUPER_ADMIN', []),
    user('u1', 'Alexis Administrador', 'admin@demo.cl', 'ADMIN', ['org1']),
    user('u2', 'Rocío Recepción', 'recepcion@demo.cl', 'RECEPCIONISTA', ['org1']),
    user('u3', 'Andrea Administradora', 'admin.alameda@demo.cl', 'ADMIN', ['org2']),
    user('u4', 'Martín Recepción', 'recepcion.alameda@demo.cl', 'RECEPCIONISTA', ['org2']),
    ...professionals.map((p, i) => user(`up${i}`, p.name, p.email, 'PROFESIONAL', [p.organizationId], { professionalId: p.id })),
    ...patients.map((p, i) => user(`uc${i}`, p.name, p.email, 'PACIENTE', [p.organizationId], { patientId: p.id })),
  ]
  const availability = professionals.flatMap((p) => [1, 2, 3, 4, 5].map((weekday) => ({ id: uid('av'), organizationId: p.organizationId, professionalId: p.id, weekday, start: '09:00', end: '17:00', active: true })))
  const appointments = [
    ['a1', 'c1', 'p1', 'e1', 's1', nextBusinessDay(1), '09:00', '09:30', 'CONFIRMADA', 'uc0'],
    ['a2', 'c2', 'p2', 'e2', 's3', nextBusinessDay(1), '10:00', '10:45', 'PENDIENTE', 'u2'],
    ['a3', 'c3', 'p3', 'e3', 's5', nextBusinessDay(2), '11:00', '11:40', 'ATENDIDA', 'uc2'],
    ['a4', 'c4', 'p1', 'e1', 's2', nextBusinessDay(3), '12:00', '12:20', 'NO_SHOW', 'u2'],
    ['a5', 'c11', 'p4', 'e4', 's6', nextBusinessDay(2), '10:30', '11:00', 'CONFIRMADA', 'u4'],
  ].map(([id, patientId, professionalId, specialtyId, serviceId, date, time, end, status, createdBy]) => ({
    id, organizationId: professionalId === 'p4' ? 'org2' : 'org1', patientId, professionalId, specialtyId, serviceId, date, time, end,
    status: status as 'PENDIENTE' | 'CONFIRMADA' | 'ATENDIDA' | 'NO_SHOW', createdBy, source: 'DEMO' as const, overbook: false,
  }))
  const history = appointments.map((appointment) => ({
    id: uid('history'), appointmentId: appointment.id, type: 'CREACION' as const, to: appointment.status,
    newDate: `${appointment.date} ${appointment.time}`, userId: appointment.createdBy, at: new Date().toISOString(),
  }))
  patients.forEach((patient, index) => { const body = String(20000000 + index); patient.rut = `${body}-${rutDv(body)}` })
  professionals.forEach((professional, index) => { const body = String(11000000 + index); professional.rut = `${body}-${rutDv(body)}` })
  return { organizations: organizations.map((o) => ({ ...o, subscription: 'ACTIVE' })), users, patients: patients.map((p) => ({ ...p, healthInsurance: 'Fonasa', medicalInsurance: '' })), professionals, specialties, services, availability, appointments, history,
    resultTypes: [{ id: 'rt1', organizationId: 'org1', name: 'Informe de atención' }, { id: 'rt2', organizationId: 'org2', name: 'Informe de evaluación' }],
    results: [{ id: 'r1', organizationId: 'org1', patientId: 'c1', professionalId: 'p1', typeId: 'rt1', performedAt: '2026-08-20', publishedAt: '2026-08-21', status: 'PUBLISHED', filename: 'informe-demostracion.txt', content: 'data:text/plain;charset=utf-8,' + encodeURIComponent('DOCUMENTO FICTICIO DE DEMOSTRACIÓN\nInforme de atención\nEste archivo demuestra la consulta de resultados. No contiene datos clínicos reales.'), createdBy: 'up0' }],
  }
}
