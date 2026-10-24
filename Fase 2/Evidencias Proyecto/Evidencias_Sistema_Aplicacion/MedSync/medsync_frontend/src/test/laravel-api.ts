import { vi } from 'vitest'
import type { AuthUser, BackendAvailability, BackendProfessional } from '@/services/http'
import type { Role } from '@/domain/types'

export const centers = [
  { id: 1, name: 'Clínica Horizonte', slug: 'clinica-horizonte' },
  { id: 2, name: 'Centro Médico Alameda', slug: 'centro-alameda' },
]

export function backendUser(role: Role, centerSlug?: string, id = 1): AuthUser {
  const center = centers.find((item) => item.slug === centerSlug) ?? null
  return {
    id, name: `${role} de prueba`, email: `${role.toLowerCase()}${id}@example.com`, role,
    medical_center: center,
    patient: role === 'PACIENTE' ? { id: 7 } : null,
    professional: role === 'PROFESIONAL' ? { id: 9 } : null,
  }
}

type RequestRecord = { path: string; method: string; body?: Record<string, unknown> }

export function stubLaravelApi(options: {
  centers?: typeof centers
  session?: AuthUser | null
  accounts?: Record<string, AuthUser>
  slots?: string[]
  patients?: { id: number; first_name: string; last_name: string; rut: string }[]
  professionals?: BackendProfessional[]
  availabilities?: BackendAvailability[]
  unauthorizedServices?: boolean
} = {}) {
  const requests: RequestRecord[] = []
  let session = options.session ?? null
  const publishedCenters = options.centers ?? centers
  const patientRecords = [...(options.patients ?? [])]
  const availabilityRecords = [...(options.availabilities ?? [])]
  const ok = (data: unknown, status = 200) => ({ ok: true, status, json: async () => structuredClone(data) })
  const fail = (status: number) => ({ ok: false, status, json: async () => ({ message: 'Sin acceso' }) })

  vi.stubGlobal('fetch', vi.fn(async (input: string, init?: RequestInit) => {
    const url = new URL(input)
    const path = url.pathname
    const method = init?.method ?? 'GET'
    const body = init?.body ? JSON.parse(String(init.body)) as Record<string, unknown> : undefined
    requests.push({ path, method, body })

    if (path === '/sanctum/csrf-cookie') return { ok: true, status: 204 }
    if (path === '/api/public/centers') return ok({ data: publishedCenters })
    if (path === '/api/v1/me') return session ? ok({ data: session }) : fail(401)
    if (path === '/api/login') {
      const account = options.accounts?.[String(body?.email)]
      if (!account) return fail(401)
      session = account
      return ok({ data: account })
    }
    if (path === '/api/logout') { session = null; return { ok: true, status: 204 } }
    if (!session) return fail(401)
    if (path === '/api/v1/professionals') return ok({ data: options.professionals ?? [] })
    if (path === '/api/v1/availabilities' && method === 'GET') return ok({ data: availabilityRecords })
    if (path.startsWith('/api/v1/availabilities/') && method === 'PATCH') {
      const id = Number(path.split('/').at(-1))
      const index = availabilityRecords.findIndex((item) => item.id === id)
      if (index < 0) return fail(404)
      availabilityRecords[index] = { ...availabilityRecords[index], ...body }
      return ok({ data: availabilityRecords[index] })
    }
    if (path === '/api/v1/services' && options.unauthorizedServices) return fail(401)
    if (path === '/api/v1/services' ||
        path === '/api/v1/appointments' || path === '/api/v1/appointments/my') return ok({ data: [] })
    if (path === '/api/v1/patients' && method === 'GET') return ok({ data: patientRecords })
    if (path === '/api/v1/patients' && method === 'POST') {
      const record = {
        id: 12, first_name: String(body?.first_name), last_name: String(body?.last_name),
        rut: String(body?.rut),
      }
      patientRecords.push(record)
      return ok({ data: { ...record, ...body, is_active: true, has_account: false } }, 201)
    }
    if (path === '/api/v1/appointments/available-slots') return ok({ data: {
      date: url.searchParams.get('date'), professional_id: Number(url.searchParams.get('professional_id')),
      service_id: Number(url.searchParams.get('service_id')), duration_minutes: 30,
      slots: options.slots ?? [],
    } })
    return fail(404)
  }))

  return { requests, getSession: () => session }
}
