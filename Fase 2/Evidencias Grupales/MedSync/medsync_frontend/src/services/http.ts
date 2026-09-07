const API_URL = import.meta.env.VITE_API_URL ?? 'http://localhost:8000'

export class ApiError extends Error {
  constructor(public status: number, message: string, public errors?: Record<string, string[]>) { super(message) }
}

// Laravel/Sanctum espera el token CSRF (seteado como cookie XSRF-TOKEN por
// /sanctum/csrf-cookie) de vuelta en el header X-XSRF-TOKEN. fetch() no lo
// hace automáticamente como axios, así que lo leemos de document.cookie.
function xsrfHeader(): Record<string, string> {
  const match = document.cookie.match(/(?:^|; )XSRF-TOKEN=([^;]*)/)
  return match ? { 'X-XSRF-TOKEN': decodeURIComponent(match[1]) } : {}
}

export async function apiRequest<T>(path: string, init: RequestInit = {}): Promise<T> {
  const response = await fetch(`${API_URL}${path}`, {
    ...init,
    credentials: 'include',
    headers: { Accept: 'application/json', 'Content-Type': 'application/json', ...xsrfHeader(), ...init.headers },
  })
  if (!response.ok) {
    const body = await response.json().catch(() => ({ message: 'No fue posible completar la solicitud.' }))
    throw new ApiError(response.status, body.message, body.errors)
  }
  return response.status === 204 ? (undefined as T) : response.json()
}

export interface PatientRegisterPayload {
  first_name: string
  last_name: string
  rut: string
  birth_date: string
  email: string
  phone: string
  health_insurance: string
  medical_insurance?: string | null
  address?: string | null
  password: string
  password_confirmation: string
  consent: boolean
}

export const sanctum = {
  csrf: () => apiRequest<void>('/sanctum/csrf-cookie'),
  login: async (email: string, password: string) => { await sanctum.csrf(); return apiRequest('/api/login', { method: 'POST', body: JSON.stringify({ email, password }) }) },
  logout: () => apiRequest<void>('/api/logout', { method: 'POST' }),
  register: async (payload: PatientRegisterPayload) => { await sanctum.csrf(); return apiRequest('/api/register', { method: 'POST', body: JSON.stringify(payload) }) },
}
