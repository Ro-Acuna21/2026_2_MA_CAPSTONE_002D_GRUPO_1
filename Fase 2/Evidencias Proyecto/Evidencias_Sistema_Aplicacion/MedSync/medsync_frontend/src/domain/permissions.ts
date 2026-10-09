import type { Permission, Role } from './types'

const rolePermissions: Record<Role, Permission[]> = {
  SUPER_ADMIN: ['platform.manage'],
  ADMIN: ['catalog.manage', 'reports.view', 'users.manage', 'results.types'],
  RECEPCIONISTA: ['appointments.view.all', 'appointments.create', 'appointments.update', 'patients.manage'],
  PROFESIONAL: ['appointments.view.own', 'appointments.update', 'results.upload', 'results.publish'],
  PACIENTE: ['appointments.view.own', 'appointments.create', 'appointments.update'],
}

export const permissionsFor = (role: Role) => rolePermissions[role]
export const hasPermission = (permissions: Permission[], permission: Permission) => permissions.includes(permission)
