import { describe, expect, it } from 'vitest'
import { hasPermission, permissionsFor } from './permissions'

describe('permisos por rol', () => {
  it('permite al administrador ver reportes', () => expect(hasPermission(permissionsFor('ADMIN'), 'reports.view')).toBe(true))
  it('impide al paciente gestionar catálogos', () => expect(hasPermission(permissionsFor('PACIENTE'), 'catalog.manage')).toBe(false))
})
