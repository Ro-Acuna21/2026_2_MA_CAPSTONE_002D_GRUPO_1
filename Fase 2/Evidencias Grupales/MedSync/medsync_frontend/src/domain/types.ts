export type Role = 'SUPER_ADMIN' | 'ADMIN' | 'RECEPCIONISTA' | 'PROFESIONAL' | 'PACIENTE'
export type AppointmentStatus = 'PENDIENTE' | 'CONFIRMADA' | 'ATENDIDA' | 'CANCELADA' | 'NO_SHOW'
export type Permission =
  | 'appointments.view.all' | 'appointments.view.own' | 'appointments.create'
  | 'appointments.update' | 'patients.manage' | 'catalog.manage' | 'reports.view'
  | 'platform.manage' | 'users.manage' | 'results.upload' | 'results.publish' | 'results.types'

export interface Membership { organizationId: string; role: Exclude<Role, 'SUPER_ADMIN'>; patientId?: string; professionalId?: string; extraPermissions?: Permission[] }

export interface User {
  id: string; name: string; email: string; role: Role; permissions: Permission[]
  organizationIds: string[]; patientId?: string; professionalId?: string
  memberships?: Membership[]; passwordHash?: string; passwordSalt?: string
}
export type SubscriptionStatus = 'TRIAL' | 'ACTIVE' | 'EXPIRED' | 'SUSPENDED'
export interface Organization { id: string; name: string; slug: string; plan: 'STARTER' | 'PRO'; active: boolean; subscription?: SubscriptionStatus }
export interface Patient {
  id: string; organizationId: string; rut: string; name: string; email: string; phone: string
  birthDate: string; address: string; consent: boolean; active: boolean
  healthInsurance?: string; medicalInsurance?: string
}
export interface Professional {
  id: string; organizationId: string; name: string; rut: string; email: string; phone: string
  specialtyIds: string[]; description: string; active: boolean
}
export interface Specialty { id: string; organizationId: string; name: string; description: string; active: boolean }
export interface Service { id: string; organizationId: string; specialtyId: string; name: string; duration: number; active: boolean }
export interface Availability {
  id: string; organizationId: string; professionalId: string; weekday: number; start: string; end: string; active: boolean
}
export interface Appointment {
  id: string; organizationId: string; patientId: string; professionalId: string; specialtyId: string; serviceId: string
  date: string; time: string; end: string; status: AppointmentStatus; source: 'WEB' | 'RECEPCION' | 'DEMO'
  note?: string; overbook: boolean; createdBy: string
}
export interface HistoryEntry {
  id: string; appointmentId: string; type: 'CREACION' | 'CAMBIO_ESTADO' | 'REPROGRAMACION' | 'CANCELACION'
  from?: AppointmentStatus; to?: AppointmentStatus; oldDate?: string; newDate?: string; userId: string; at: string
}
export interface ClinicData {
  organizations: Organization[]; users: User[]; patients: Patient[]; professionals: Professional[]; specialties: Specialty[]
  services: Service[]; availability: Availability[]; appointments: Appointment[]; history: HistoryEntry[]
  resultTypes: ResultType[]; results: MedicalResult[]
}
export interface ResultType { id: string; organizationId: string; name: string }
export interface MedicalResult {
  id: string; organizationId: string; patientId: string; professionalId: string; typeId: string
  appointmentId?: string; performedAt: string; publishedAt?: string; status: 'DRAFT' | 'PUBLISHED'
  filename: string; content: string; createdBy: string
}
export interface BookingInput {
  patientId?: string; professionalId: string; specialtyId: string; serviceId: string
  date: string; time: string; note?: string; overbook?: boolean
}
export interface Slot { time: string; end: string }

export const roleNames: Record<Role, string> = {
  SUPER_ADMIN: 'Superadministrador de plataforma', ADMIN: 'Administrador del centro', RECEPCIONISTA: 'Recepción', PROFESIONAL: 'Profesional', PACIENTE: 'Paciente',
}
export const statusNames: Record<AppointmentStatus, string> = {
  PENDIENTE: 'Pendiente', CONFIRMADA: 'Confirmada', ATENDIDA: 'Atendida', CANCELADA: 'Cancelada', NO_SHOW: 'No asistió',
}
