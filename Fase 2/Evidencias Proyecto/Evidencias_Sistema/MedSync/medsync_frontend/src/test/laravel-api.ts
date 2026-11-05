import { vi } from 'vitest'
import type { AuthUser, BackendAvailability, BackendProfessional } from '@/services/http'
import type { Role } from '@/domain/types'
import type { GlobalSpecialty } from '@/services/specialty-catalog-api'
import type { PlatformCenter } from '@/services/platform-center-api'
import type { CenterAccess } from '@/services/center-access-api'

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
  globalSpecialties?: GlobalSpecialty[]
  platformCenters?: PlatformCenter[]
  administrators?: CenterAccess[]
  receptionists?: CenterAccess[]
  pendingEnrollment?: { center: { id: number; name: string; slug: string }; email: string }
} = {}) {
  const requests: RequestRecord[] = []
  let session = options.session ?? null
  const publishedCenters = [...(options.centers ?? centers)]
  const patientRecords = [...(options.patients ?? [])]
  const availabilityRecords = [...(options.availabilities ?? [])]
  const globalSpecialties = [...(options.globalSpecialties ?? [])]
  const platformCenters = [...(options.platformCenters ?? [])]
  const administrators = [...(options.administrators ?? [])]
  const receptionists = [...(options.receptionists ?? [])]
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
    if (path === '/api/v1/patient-enrollment' && options.pendingEnrollment) {
      if (method === 'GET') return ok({ data: options.pendingEnrollment })
      if (method === 'POST') return ok({ data: { center: options.pendingEnrollment.center, patient_id: 99 } }, 201)
    }
    if (!session) return fail(401)
    if (path === '/api/v1/platform/centers' && session.role === 'SUPER_ADMIN') {
      if (method === 'GET') return ok({ data: platformCenters })
      if (method === 'POST') {
        const id = Math.max(0, ...platformCenters.map((item) => item.id)) + 1
        const center = {
          id, name: String(body?.name), slug: String(body?.slug), rut: String(body?.rut),
          phone: body?.phone ? String(body.phone) : null, email: body?.email ? String(body.email) : null,
          plan: body?.plan ?? 'STARTER', subscription_status: body?.subscription_status ?? 'TRIAL',
          is_active: body?.is_active === true, provisioning_status: 'READY', provisioned_at: new Date().toISOString(),
        }
        platformCenters.push(center as PlatformCenter)
        if (center.is_active) publishedCenters.push({ id, name: center.name, slug: center.slug })
        return ok({ data: center }, 201)
      }
    }
    const administratorsMatch = path.match(/^\/api\/v1\/platform\/centers\/(\d+)\/administrators$/)
    if (administratorsMatch && session.role === 'SUPER_ADMIN') {
      if (method === 'GET') return ok({ data: administrators })
      if (method === 'POST') {
        const access = { id: administrators.length + 1, user_id: administrators.length + 10,
          name: String(body?.name), email: String(body?.email), role: 'ADMIN', is_active: true,
          invitation_pending: true, invitation_sent_at: new Date().toISOString() }
        administrators.push(access as CenterAccess)
        return ok({ data: access, account_created: true, invitation_sent: true, message: 'Invitación enviada.' }, 201)
      }
    }
    const staffMatch = path.match(/^\/api\/v1\/platform\/centers\/(\d+)\/staff(?:\/(\d+)\/role)?$/)
    if (staffMatch && session.role === 'SUPER_ADMIN') {
      if (method === 'GET') return ok({ data: [...administrators, ...receptionists] })
      if (method === 'PATCH') {
        const item = [...administrators, ...receptionists].find((entry) => entry.id === Number(staffMatch[2]))
        if (!item) return fail(404)
        item.role = body?.role as CenterAccess['role']
        return ok({ data: item })
      }
    }
    if (path === '/api/v1/center/receptionists' && session.role === 'ADMIN') {
      if (method === 'GET') return ok({ data: receptionists })
      if (method === 'POST') {
        const access = { id: receptionists.length + 1, user_id: receptionists.length + 10,
          name: String(body?.name), email: String(body?.email), role: 'RECEPCIONISTA', is_active: true,
          invitation_pending: true, invitation_sent_at: new Date().toISOString() }
        receptionists.push(access as CenterAccess)
        return ok({ data: access, account_created: true, invitation_sent: true, message: 'Invitación enviada.' }, 201)
      }
    }
    if (path === '/api/v1/center/permissions' && session.role === 'ADMIN') return ok({ data: {
      source: 'fixed_backend_catalog', roles: {
        ADMIN: ['professionals.manage', 'services.manage'],
        RECEPCIONISTA: ['patients.manage', 'appointments.view.all'],
        PROFESIONAL: ['appointments.view.own'],
        PACIENTE: ['patients.view.own', 'appointments.view.own'],
      },
    } })
    if (path === '/api/v1/platform/specialties' && session.role === 'SUPER_ADMIN') {
      if (method === 'GET') return ok({ data: globalSpecialties })
      if (method === 'POST') {
        const specialty = {
          id: Math.max(0, ...globalSpecialties.map((item) => item.id)) + 1,
          name: String(body?.name), description: body?.description ? String(body.description) : null,
          is_active: true,
        }
        globalSpecialties.push(specialty)
        return ok({ data: specialty }, 201)
      }
    }
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
