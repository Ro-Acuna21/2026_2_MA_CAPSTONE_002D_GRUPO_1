export const normalizeRut = (value: string) => value.replace(/[.\s-]/g, '').toUpperCase()
export function rutDv(body: string) {
  let sum = 0, factor = 2
  for (const digit of [...body].reverse()) { sum += Number(digit) * factor; factor = factor === 7 ? 2 : factor + 1 }
  const dv = 11 - sum % 11
  return dv === 11 ? '0' : dv === 10 ? 'K' : String(dv)
}
export function validRut(value: string) {
  const rut = normalizeRut(value)
  return /^[1-9]\d{6,7}[\dK]$/.test(rut) && rutDv(rut.slice(0, -1)) === rut.slice(-1)
}
export const formatRut = (value: string) => { const rut = normalizeRut(value); return `${rut.slice(0, -1)}-${rut.slice(-1)}` }
export const validEmail = (value: string) => /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value)
export const validPhone = (value: string) => /^(?:\+?56)?[2-9]\d{8}$/.test(value.replace(/[\s()-]/g, ''))
export const healthInsurances = ['Fonasa', 'Isapre', 'Particular', 'Otra']
export function validatePatient(input: { name: string; rut: string; email: string; phone: string; birthDate: string; healthInsurance?: string }) {
  if (input.name.trim().length < 3) throw new Error('Ingresa nombres y apellidos.')
  if (!validRut(input.rut)) throw new Error('El RUT o su dígito verificador no es válido.')
  if (!validEmail(input.email.trim())) throw new Error('Ingresa un correo válido.')
  if (!validPhone(input.phone)) throw new Error('Ingresa un teléfono chileno de 9 dígitos, con +56 opcional.')
  const birth = new Date(`${input.birthDate}T12:00:00`)
  if (!/^\d{4}-\d{2}-\d{2}$/.test(input.birthDate) || !Number.isFinite(birth.getTime()) || birth > new Date() || birth.getFullYear() < 1900 || birth.toISOString().slice(0, 10) !== input.birthDate) throw new Error('Ingresa una fecha de nacimiento válida, no futura.')
  if (!healthInsurances.includes(input.healthInsurance ?? '')) throw new Error('Selecciona una previsión de salud.')
}
export function validatePassword(password: string, confirmation: string) {
  if (password.length < 8 || !/[a-z]/.test(password) || !/[A-Z]/.test(password) || !/\d/.test(password)) throw new Error('La contraseña debe tener al menos 8 caracteres, mayúscula, minúscula y número.')
  if (password !== confirmation) throw new Error('Las contraseñas no coinciden.')
}
// Credenciales exclusivamente para la demostración local. La autenticación real será de Sanctum.
export async function hashPassword(password: string, salt: string) {
  const key = await crypto.subtle.importKey('raw', new TextEncoder().encode(password), 'PBKDF2', false, ['deriveBits'])
  const bytes = await crypto.subtle.deriveBits({ name: 'PBKDF2', salt: new TextEncoder().encode(salt), iterations: 100000, hash: 'SHA-256' }, key, 256)
  return Array.from(new Uint8Array(bytes), (byte) => byte.toString(16).padStart(2, '0')).join('')
}
