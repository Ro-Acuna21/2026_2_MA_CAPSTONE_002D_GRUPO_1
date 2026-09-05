import { describe, expect, it } from 'vitest'
import { validRut, validatePatient, validatePassword, validPhone } from './validation'

describe('registro validado', () => {
  it.each(['12.345.678-5', '123456785', '6.000.000-k', '1000005K'])('acepta RUT válido %s', (rut) => expect(validRut(rut)).toBe(true))
  it.each(['12.345.678-4', '', '00000000-0', '1234', '12a3456785'])('rechaza RUT inválido %s', (rut) => expect(validRut(rut)).toBe(false))
  it('valida teléfono chileno', () => { expect(validPhone('+56 9 1234 5678')).toBe(true); expect(validPhone('912345678')).toBe(true); expect(validPhone('123')).toBe(false) })
  it('rechaza fecha futura y fecha inexistente', () => {
    const input = { name: 'Paciente Demo', rut: '12345678-5', email: 'test@example.com', phone: '912345678', healthInsurance: 'Fonasa', birthDate: '2999-01-01' }
    expect(() => validatePatient(input)).toThrow('fecha')
    expect(() => validatePatient({ ...input, birthDate: '2025-02-31' })).toThrow('fecha')
    expect(() => validatePatient({ ...input, birthDate: '1990-02-28' })).not.toThrow()
  })
  it('valida contraseña y confirmación', () => { expect(() => validatePassword('12345678', '12345678')).toThrow(); expect(() => validatePassword('Prueba2026', 'Otra2026')).toThrow('coinciden'); expect(() => validatePassword('Prueba2026', 'Prueba2026')).not.toThrow() })
})
