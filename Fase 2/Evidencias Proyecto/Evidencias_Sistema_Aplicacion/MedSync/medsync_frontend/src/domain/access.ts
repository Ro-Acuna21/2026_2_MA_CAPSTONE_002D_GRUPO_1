import type { ClinicData, User, Membership } from './types'
import { permissionsFor } from './permissions'

export function membershipsFor(user: User): Membership[] {
  return user.memberships ?? user.organizationIds.map((organizationId) => ({ organizationId, role: user.role === 'SUPER_ADMIN' ? 'ADMIN' as const : user.role, patientId: user.patientId, professionalId: user.professionalId }))
}
export function activeUser(account: User | null, organizationId: string | null): User | null {
  if (!account || account.role === 'SUPER_ADMIN') return account
  const membership = membershipsFor(account).find((item) => item.organizationId === organizationId)
  return membership ? { ...account, role: membership.role, patientId: membership.patientId, professionalId: membership.professionalId, permissions: [...permissionsFor(membership.role), ...(membership.extraPermissions ?? [])] } : { ...account, permissions: [], patientId: undefined, professionalId: undefined }
}
export function scopeData(all: ClinicData, user: User | null, organizationId?: string): ClinicData {
  const center = user && user.role !== 'SUPER_ADMIN' && membershipsFor(user).some((m) => m.organizationId === organizationId) ? organizationId : undefined
  const appointments = all.appointments.filter((a) => a.organizationId === center && (user?.role === 'PACIENTE' ? a.patientId === user.patientId : user?.role === 'PROFESIONAL' ? a.professionalId === user.professionalId : true))
  const patients = all.patients.filter((p) => p.organizationId === center && (user?.role === 'ADMIN' ? false : user?.role === 'PACIENTE' ? p.id === user.patientId : user?.role === 'PROFESIONAL' ? appointments.some((a) => a.patientId === p.id) || all.results.some((r) => r.organizationId === center && r.professionalId === user.professionalId && r.patientId === p.id) : true))
  return {
    organizations: user?.role === 'SUPER_ADMIN' ? all.organizations : all.organizations.filter((o) => o.id === center),
    users: user?.role === 'SUPER_ADMIN' ? all.users.filter((u) => membershipsFor(u).some((m) => m.role === 'ADMIN')).map((u) => ({ ...u, passwordHash: undefined, passwordSalt: undefined })) : all.users.filter((u) => membershipsFor(u).some((m) => m.organizationId === center) && (user?.role === 'ADMIN' || u.id === user?.id)).map((u) => ({ ...u, passwordHash: undefined, passwordSalt: undefined })),
    patients, appointments,
    professionals: all.professionals.filter((p) => p.organizationId === center),
    specialties: all.specialties.filter((s) => s.organizationId === center), services: all.services.filter((s) => s.organizationId === center),
    availability: all.availability.filter((a) => a.organizationId === center),
    history: all.history.filter((h) => appointments.some((a) => a.id === h.appointmentId)),
    resultTypes: all.resultTypes.filter((t) => t.organizationId === center),
    results: all.results.filter((r) => r.organizationId === center && (user?.role === 'PACIENTE' ? r.patientId === user.patientId && r.status === 'PUBLISHED' : user?.role === 'PROFESIONAL' ? r.professionalId === user.professionalId : user?.role === 'RECEPCIONISTA' && user.permissions.includes('results.upload'))),
  }
}
