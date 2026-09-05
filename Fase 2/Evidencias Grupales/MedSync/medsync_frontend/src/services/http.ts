const API_URL = import.meta.env.VITE_API_URL ?? 'http://localhost:8000'

export class ApiError extends Error {
  constructor(public status: number, message: string, public errors?: Record<string, string[]>) { super(message) }
}

export async function apiRequest<T>(path: string, init: RequestInit = {}): Promise<T> {
  const response = await fetch(`${API_URL}${path}`, {
    ...init,
    credentials: 'include',
    headers: { Accept: 'application/json', 'Content-Type': 'application/json', ...init.headers },
  })
  if (!response.ok) {
    const body = await response.json().catch(() => ({ message: 'No fue posible completar la solicitud.' }))
    throw new ApiError(response.status, body.message, body.errors)
  }
  return response.status === 204 ? (undefined as T) : response.json()
}

export const sanctum = {
  csrf: () => apiRequest<void>('/sanctum/csrf-cookie'),
  login: async (email: string, password: string) => { await sanctum.csrf(); return apiRequest('/api/login', { method: 'POST', body: JSON.stringify({ email, password }) }) },
  logout: () => apiRequest<void>('/api/logout', { method: 'POST' }),
}
