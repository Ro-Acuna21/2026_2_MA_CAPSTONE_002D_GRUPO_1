import type { Permission, Role } from './types'

const rolePermissions: Record<Role, Permission[]> = {
  ADMIN: ['appointments.view.all', 'appointments.create', 'appointments.update', 'patients.manage', 'catalog.manage', 'reports.view'],
  RECEPCIONISTA: ['appointments.view.all', 'appointments.create', 'appointments.update', 'patients.manage'],
  PROFESIONAL: ['appointments.view.own', 'appointments.update'],
  PACIENTE: ['appointments.view.own', 'appointments.create', 'appointments.update'],
}

export const permissionsFor = (role: Role) => rolePermissions[role]
export const hasPermission = (permissions: Permission[], permission: Permission) => permissions.includes(permission)
