/* eslint-disable react-refresh/only-export-components */
import { createContext, useCallback, useContext, useState, type ReactNode } from 'react'
import { toast } from 'sonner'
import { createMockData } from '@/data/mock-data'
import { permissionsFor } from '@/domain/permissions'
import type { AppointmentStatus, BookingInput, ClinicData, Organization, Patient, Slot, User, Professional, Membership, MedicalResult, Permission } from '@/domain/types'
import { activeUser, membershipsFor, scopeData } from '@/domain/access'
import { formatRut, normalizeRut, validatePatient, validatePassword, hashPassword, validEmail, validPhone, validRut } from '@/domain/validation'
import { dateFromToday, toMinutes, toTime, uid } from '@/lib/utils'
import { canPatientModifyAppointment, canSetAppointmentStatus } from '@/domain/appointment-rules'

const DATA_KEY = 'clinica_horizonte_react_v4'
const SESSION_KEY = 'clinica_horizonte_user'
const ORGANIZATION_KEY = 'clinica_horizonte_organization'
const ACTIVE: AppointmentStatus[] = ['PENDIENTE', 'CONFIRMADA', 'ATENDIDA', 'NO_SHOW']

function loadData(): ClinicData {
  try {
    const value = localStorage.getItem(DATA_KEY) ?? localStorage.getItem('clinica_horizonte_react_v3') ?? localStorage.getItem('clinica_horizonte_react_v2')
    if (!value) return createMockData()
    const old = JSON.parse(value) as ClinicData
    const withPlatform = old.users.some((u) => u.role === 'SUPER_ADMIN') ? old.users : [...old.users, createMockData().users[0]]
    const tenantUsers = withPlatform.flatMap((account) => {
      if (account.role === 'SUPER_ADMIN') return [account]
      const memberships = membershipsFor(account)
      return memberships.map((membership, index) => ({ ...account, id: index === 0 ? account.id : `${account.id}_${membership.organizationId}`, role: membership.role, organizationIds: [membership.organizationId], memberships: [membership], patientId: membership.patientId, professionalId: membership.professionalId, permissions: permissionsFor(membership.role) }))
    })
    return { ...old, organizations: old.organizations.map((o) => ({ ...o, subscription: o.subscription ?? 'ACTIVE' })), users: tenantUsers, resultTypes: old.resultTypes ?? [], results: old.results ?? [] }
  } catch { return createMockData() }
}

interface ClinicContextValue {
  data: ClinicData; user: User | null; organization: Organization | null
  login(email: string, password: string, portal?: 'CENTER' | 'PLATFORM', centerId?: string): Promise<boolean>; logout(): void; reset(): void
  publicOrganizations: Organization[]
  register(input: Omit<Patient, 'id' | 'active' | 'organizationId'>, password: string, confirmation: string, centerId: string): Promise<void>
  saveOrganization(input: Organization): void
  saveProfessional(input: Omit<Professional, 'id' | 'organizationId'>, id?: string): void
  saveCatalog(kind: 'specialty' | 'service' | 'availability', input: Record<string, string>, id?: string): void
  assignAccess(email: string, name: string, membership: Membership, password?: string): Promise<void>
  addResultType(name: string): void
  addResult(input: Omit<MedicalResult, 'id' | 'organizationId' | 'createdBy' | 'status'>): void
  publishResult(id: string): void
  deleteResult(id: string): void
  slots(professionalId: string, serviceId: string, date: string, excludeId?: string): Slot[]
  createAppointment(input: BookingInput): void; changeStatus(id: string, status: AppointmentStatus): void
  reschedule(id: string, date: string, time: string): void; createPatient(input: Omit<Patient, 'id' | 'active' | 'organizationId'>): void
  updateProfile(input: Pick<Patient, 'name' | 'email' | 'phone' | 'address'>): void
}

const ClinicContext = createContext<ClinicContextValue | null>(null)

export function ClinicProvider({ children }: { children: ReactNode }) {
  const [allData, setAllData] = useState(loadData)
  const [userId, setUserId] = useState(() => sessionStorage.getItem(SESSION_KEY))
  const [organizationId, setOrganizationId] = useState(() => sessionStorage.getItem(ORGANIZATION_KEY))
  const account = allData.users.find((item) => item.id === userId) ?? null
  const user = activeUser(account, organizationId)
  const availableOrganizations = allData.organizations.filter((item) => account && account.role !== 'SUPER_ADMIN' && membershipsFor(account).some((m) => m.organizationId === item.id) && item.active)
  const organization = availableOrganizations.find((item) => item.id === organizationId) ?? null
  const data = scopeData(allData, user, organization?.id)
  const publicOrganizations = allData.organizations.filter((o) => o.active)
  const commit = useCallback((updater: (current: ClinicData) => ClinicData) => {
    const next = updater(allData)
    try { localStorage.setItem(DATA_KEY, JSON.stringify(next)) } catch { throw new Error('No hay espacio en el navegador. Usa un documento más pequeño.') }
    setAllData(next)
  }, [allData])
  const requirePermission = (permission: Permission) => {
    if (!user || !organization || user.role === 'SUPER_ADMIN' || !user.permissions.includes(permission)) throw new Error('No tienes permiso para realizar esta acción en este centro.')
  }

  const login = async (email: string, password: string, portal: 'CENTER' | 'PLATFORM' = 'CENTER', centerId?: string) => {
    if (portal === 'CENTER' && !centerId) return false
    const found = allData.users.find((item) => item.email.toLowerCase() === email.trim().toLowerCase() && (portal === 'PLATFORM' ? item.role === 'SUPER_ADMIN' : item.role !== 'SUPER_ADMIN' && (!centerId || membershipsFor(item).some((membership) => membership.organizationId === centerId))))
    if (!found) return false
    if ((portal === 'PLATFORM') !== (found.role === 'SUPER_ADMIN')) return false
    const valid = found.passwordHash && found.passwordSalt ? await hashPassword(password, found.passwordSalt) === found.passwordHash : createMockData().users.some((u) => u.id === found.id) && password === 'Demo2026!'
    if (!valid) return false
    sessionStorage.setItem(SESSION_KEY, found.id); setUserId(found.id)
    const targetCenter = centerId ?? (found.organizationIds.length === 1 ? found.organizationIds[0] : undefined)
    if (targetCenter) { sessionStorage.setItem(ORGANIZATION_KEY, targetCenter); setOrganizationId(targetCenter) }
    else { sessionStorage.removeItem(ORGANIZATION_KEY); setOrganizationId(null) }
    return true
  }
  const logout = () => { sessionStorage.removeItem(SESSION_KEY); sessionStorage.removeItem(ORGANIZATION_KEY); setUserId(null); setOrganizationId(null) }
  const reset = () => { const next = createMockData(); localStorage.setItem(DATA_KEY, JSON.stringify(next)); setAllData(next); logout(); toast.success('Datos demo restablecidos') }
  const slots = (professionalId: string, serviceId: string, date: string, excludeId?: string) => {
    const service = data.services.find((item) => item.id === serviceId)
    if (!service || !date) return []
    const day = new Date(`${date}T12:00`).getDay()
    return data.availability.filter((rule) => rule.active && rule.professionalId === professionalId && rule.weekday === day).flatMap((rule) => {
      const result: Slot[] = []
      for (let start = toMinutes(rule.start); start + service.duration <= toMinutes(rule.end); start += service.duration) {
        const time = toTime(start), end = toTime(start + service.duration)
        if (date === dateFromToday() && new Date(`${date}T${time}:00`) <= new Date()) continue
        const conflict = allData.appointments.some((a) => a.organizationId === organization?.id && a.id !== excludeId && a.professionalId === professionalId && a.date === date && ACTIVE.includes(a.status) && !a.overbook && start < toMinutes(a.end) && toMinutes(a.time) < start + service.duration)
        if (!conflict) result.push({ time, end })
      }
      return result
    })
  }
  const createAppointment = (input: BookingInput) => {
    requirePermission('appointments.create')
    if (!user || !organization) return
    const service = data.services.find((item) => item.id === input.serviceId)
    const patientId = user.role === 'PACIENTE' ? user.patientId : input.patientId
    if (!service || !patientId || !data.patients.some((p) => p.id === patientId && p.active) || !data.professionals.some((p) => p.id === input.professionalId && p.active && p.specialtyIds.includes(input.specialtyId)) || service.specialtyId !== input.specialtyId) throw new Error('Selecciona paciente, profesional y prestación del centro.')
    if (input.date < dateFromToday() || !slots(input.professionalId, input.serviceId, input.date).some((s) => s.time === input.time)) throw new Error('El horario ya no está disponible.')
    if (input.overbook && user.role !== 'RECEPCIONISTA') throw new Error('No puedes crear sobreturnos.')
    commit((current) => {
      const appointment = { ...input, id: uid('a'), organizationId: organization.id, patientId, end: toTime(toMinutes(input.time) + service.duration), status: 'PENDIENTE' as const, source: user.role === 'PACIENTE' ? 'WEB' as const : 'RECEPCION' as const, overbook: !!input.overbook, createdBy: user.id }
      return { ...current, appointments: [...current.appointments, appointment], history: [...current.history, { id: uid('h'), appointmentId: appointment.id, type: 'CREACION', to: 'PENDIENTE', newDate: `${input.date} ${input.time}`, userId: user.id, at: new Date().toISOString() }] }
    })
    toast.success('Reserva creada correctamente')
  }
  const changeStatus = (id: string, status: AppointmentStatus) => {
    requirePermission('appointments.update')
    const target = data.appointments.find((a) => a.id === id)
    if (!target || ['CANCELADA', 'ATENDIDA'].includes(target.status) || (user?.role === 'PACIENTE' && (status !== 'CANCELADA' || !canPatientModifyAppointment(target))) || (user?.role === 'PROFESIONAL' && !['ATENDIDA', 'NO_SHOW'].includes(status))) throw new Error('No puedes realizar este cambio de estado.')
    if (!canSetAppointmentStatus(target, status)) throw new Error(status === 'NO_SHOW' ? 'Solo puedes marcar inasistencia después de la hora de término.' : 'Solo puedes marcar una cita como atendida desde su hora de inicio.')
    if (!user || !organization) return
    commit((current) => { const appointment = current.appointments.find((a) => a.id === id && a.organizationId === organization.id); if (!appointment) return current; return { ...current, appointments: current.appointments.map((a) => a.id === id ? { ...a, status } : a), history: [...current.history, { id: uid('h'), appointmentId: id, type: status === 'CANCELADA' ? 'CANCELACION' : 'CAMBIO_ESTADO', from: appointment.status, to: status, userId: user.id, at: new Date().toISOString() }] } })
    toast.success('Estado de la cita actualizado')
  }
  const reschedule = (id: string, date: string, time: string) => {
    requirePermission('appointments.update')
    const target = data.appointments.find((a) => a.id === id)
    if (!target || ['CANCELADA', 'ATENDIDA'].includes(target.status) || user?.role === 'PROFESIONAL' || (user?.role === 'PACIENTE' && !canPatientModifyAppointment(target)) || date < dateFromToday() || !slots(target.professionalId, target.serviceId, date, id).some((s) => s.time === time)) throw new Error('La reprogramación no está permitida o el horario no está disponible.')
    if (!user || !organization) return
    commit((current) => { const appointment = current.appointments.find((a) => a.id === id && a.organizationId === organization.id); const service = current.services.find((s) => s.id === appointment?.serviceId); if (!appointment || !service) return current; return { ...current, appointments: current.appointments.map((a) => a.id === id ? { ...a, date, time, end: toTime(toMinutes(time) + service.duration), status: 'PENDIENTE' } : a), history: [...current.history, { id: uid('h'), appointmentId: id, type: 'REPROGRAMACION', to: 'PENDIENTE', oldDate: `${appointment.date} ${appointment.time}`, newDate: `${date} ${time}`, userId: user.id, at: new Date().toISOString() }] } })
    toast.success('Cita reprogramada')
  }
  const createPatient = (input: Omit<Patient, 'id' | 'active' | 'organizationId'>) => {
    requirePermission('patients.manage')
    validatePatient(input)
    if (!input.consent) throw new Error('Confirma el consentimiento del paciente.')
    if (!organization) return
    if (data.patients.some((p) => normalizeRut(p.rut) === normalizeRut(input.rut) || p.email.toLowerCase() === input.email.trim().toLowerCase())) throw new Error('Ya existe un paciente con ese RUT o correo en este centro.')
    const patient = { ...input, rut: formatRut(input.rut), email: input.email.trim().toLowerCase(), id: uid('c'), organizationId: organization.id, active: true }
    commit((current) => ({ ...current, patients: [...current.patients, patient] })); toast.success('Ficha registrada. El acceso se gestiona por separado.')
  }
  const updateProfile = (input: Pick<Patient, 'name' | 'email' | 'phone' | 'address'>) => {
    if (!user?.patientId) return
    if (input.name.trim().length < 3 || !validEmail(input.email) || !validPhone(input.phone)) throw new Error('Revisa nombre, correo y teléfono.')
    if (allData.users.some((u) => u.id !== user.id && u.email.toLowerCase() === input.email.trim().toLowerCase() && membershipsFor(u).some((membership) => membership.organizationId === organization?.id))) throw new Error('El correo ya tiene una cuenta en este centro.')
    commit((current) => ({ ...current, patients: current.patients.map((p) => p.id === user.patientId ? { ...p, ...input } : p), users: current.users.map((u) => u.id === user.id ? { ...u, name: input.name, email: input.email } : u) })); toast.success('Datos actualizados')
  }
  const register: ClinicContextValue['register'] = async (input, password, confirmation, centerId) => {
    validatePatient(input); validatePassword(password, confirmation)
    if (!input.consent) throw new Error('Debes aceptar el tratamiento de los datos para crear tu cuenta.')
    if (!publicOrganizations.some((o) => o.id === centerId)) throw new Error('Selecciona un centro disponible.')
    const email = input.email.trim().toLowerCase()
    if (allData.users.some((u) => u.email.toLowerCase() === email && membershipsFor(u).some((membership) => membership.organizationId === centerId))) throw new Error('El correo ya tiene una cuenta en este centro. Ingresa con ella.')
    const patientByRut = allData.patients.find((p) => p.organizationId === centerId && normalizeRut(p.rut) === normalizeRut(input.rut))
    const patientByEmail = allData.patients.find((p) => p.organizationId === centerId && p.email.toLowerCase() === email)
    if ((patientByRut || patientByEmail) && (!patientByRut || patientByRut.id !== patientByEmail?.id)) throw new Error('El RUT o correo ya está asociado a otra ficha del centro. Contacta a recepción para corregir tus datos.')
    const salt = crypto.randomUUID(), passwordHash = await hashPassword(password, salt)
    const patient: Patient = patientByRut ?? { ...input, name: input.name.trim(), email, rut: formatRut(input.rut), id: uid('c'), organizationId: centerId, active: true }
    const newUser: User = { id: uid('u'), name: patient.name, email, role: 'PACIENTE', patientId: patient.id, organizationIds: [centerId], permissions: permissionsFor('PACIENTE'), memberships: [{ organizationId: centerId, role: 'PACIENTE', patientId: patient.id }], passwordSalt: salt, passwordHash }
    commit((current) => ({ ...current, patients: patientByRut ? current.patients : [...current.patients, patient], users: [...current.users, newUser] }))
  }
  const saveOrganization = (input: Organization) => {
    if (user?.role !== 'SUPER_ADMIN') throw new Error('Solo la administración de plataforma puede gestionar centros.')
    if (input.name.trim().length < 3 || !/^[a-z0-9]+(?:-[a-z0-9]+)*$/.test(input.slug)) throw new Error('Revisa nombre y dirección del centro (letras minúsculas, números y guiones).')
    if (allData.organizations.some((o) => o.id !== input.id && o.slug === input.slug)) throw new Error('La dirección del centro ya existe.')
    commit((current) => ({ ...current, organizations: current.organizations.some((o) => o.id === input.id) ? current.organizations.map((o) => o.id === input.id ? input : o) : [...current.organizations, { ...input, id: uid('org') }] }))
    toast.success('Centro actualizado')
  }
  const saveProfessional: ClinicContextValue['saveProfessional'] = (input, id) => {
    requirePermission('catalog.manage')
    if (!validRut(input.rut) || !validEmail(input.email) || !validPhone(input.phone) || input.name.trim().length < 3 || !input.specialtyIds.length || input.specialtyIds.some((s) => !data.specialties.some((item) => item.id === s))) throw new Error('Revisa nombre, RUT, correo, teléfono y especialidad.')
    if (id && !data.professionals.some((p) => p.id === id)) throw new Error('Profesional fuera del centro.')
    if (data.professionals.some((p) => p.id !== id && (normalizeRut(p.rut) === normalizeRut(input.rut) || p.email === input.email))) throw new Error('El profesional ya está registrado.')
    const professional = { ...input, rut: formatRut(input.rut), id: id ?? uid('p'), organizationId: organization!.id }
    commit((current) => ({ ...current, professionals: id ? current.professionals.map((p) => p.id === id ? professional : p) : [...current.professionals, professional] })); toast.success('Ficha profesional guardada')
  }
  const saveCatalog: ClinicContextValue['saveCatalog'] = (kind, input, id) => {
    requirePermission('catalog.manage')
    if (kind !== 'availability' && user?.role !== 'ADMIN') throw new Error('Solo el administrador gestiona especialidades y prestaciones.')
    const base = { id: id ?? uid(kind), organizationId: organization!.id, active: input.active !== 'false' }
    if (kind === 'specialty') {
      if (!input.name?.trim()) throw new Error('Ingresa el nombre.')
      if (id && !data.specialties.some((s) => s.id === id)) throw new Error('Especialidad fuera del centro.')
      const item = { ...base, name: input.name.trim(), description: input.description ?? '' }
      commit((c) => ({ ...c, specialties: id ? c.specialties.map((s) => s.id === id ? item : s) : [...c.specialties, item] }))
    } else if (kind === 'service') {
      if (!input.name?.trim() || !data.specialties.some((s) => s.id === input.specialtyId) || !Number.isInteger(Number(input.duration)) || Number(input.duration) < 5 || Number(input.duration) > 480) throw new Error('Revisa nombre, especialidad y duración (5 a 480 minutos).')
      if (id && !data.services.some((s) => s.id === id)) throw new Error('Prestación fuera del centro.')
      const item = { ...base, name: input.name.trim(), specialtyId: input.specialtyId, duration: Number(input.duration) }
      commit((c) => ({ ...c, services: id ? c.services.map((s) => s.id === id ? item : s) : [...c.services, item] }))
    } else {
      if (!data.professionals.some((p) => p.id === input.professionalId) || !/^[0-6]$/.test(input.weekday) || !/^([01]\d|2[0-3]):[0-5]\d$/.test(input.start) || !/^([01]\d|2[0-3]):[0-5]\d$/.test(input.end) || input.start >= input.end) throw new Error('Revisa profesional, día y horas de inicio y término.')
      if (id && !data.availability.some((a) => a.id === id)) throw new Error('Horario fuera del centro.')
      if (base.active && data.availability.some((a) => a.id !== id && a.active && a.professionalId === input.professionalId && a.weekday === Number(input.weekday) && input.start < a.end && a.start < input.end)) throw new Error('Este horario se superpone con otro del profesional.')
      const item = { ...base, professionalId: input.professionalId, weekday: Number(input.weekday), start: input.start, end: input.end }
      commit((c) => ({ ...c, availability: id ? c.availability.map((a) => a.id === id ? item : a) : [...c.availability, item] }))
    }
    toast.success('Configuración guardada')
  }
  const assignAccess: ClinicContextValue['assignAccess'] = async (emailValue, name, membership, password) => {
    const platform = user?.role === 'SUPER_ADMIN'
    if (!platform) requirePermission('users.manage')
    if (!allData.organizations.some((o) => o.id === membership.organizationId) || (platform ? membership.role !== 'ADMIN' : membership.organizationId !== organization?.id)) throw new Error('El acceso solicitado no está permitido.')
    const email = emailValue.trim().toLowerCase()
    if (!validEmail(email) || name.trim().length < 3) throw new Error('Revisa nombre y correo.')
    const existing = allData.users.find((u) => u.email.toLowerCase() === email && membershipsFor(u).some((m) => m.organizationId === membership.organizationId))
    if (existing?.role === 'SUPER_ADMIN') throw new Error('La cuenta de plataforma no puede operar en centros.')
    if (existing && membership.role !== 'ADMIN' && membershipsFor(existing).some((m) => m.organizationId === membership.organizationId && m.role === 'ADMIN') && !allData.users.some((u) => u.id !== existing.id && membershipsFor(u).some((m) => m.organizationId === membership.organizationId && m.role === 'ADMIN'))) throw new Error('Asigna otro administrador antes de cambiar el rol del último administrador del centro.')
    if (allData.users.some((u) => u.id !== existing?.id && membershipsFor(u).some((m) => m.organizationId === membership.organizationId && ((membership.role === 'PACIENTE' && m.patientId === membership.patientId) || (membership.role === 'PROFESIONAL' && m.professionalId === membership.professionalId))))) throw new Error('La ficha ya está vinculada a otra cuenta.')
    if (membership.role === 'PACIENTE' && !allData.patients.some((p) => p.organizationId === membership.organizationId && p.id === membership.patientId && p.email.toLowerCase() === email)) throw new Error('Selecciona una ficha de paciente con el mismo correo.')
    if (membership.role === 'PROFESIONAL' && !allData.professionals.some((p) => p.organizationId === membership.organizationId && p.id === membership.professionalId && p.email.toLowerCase() === email)) throw new Error('Selecciona una ficha profesional con el mismo correo.')
    const clean: Membership = { organizationId: membership.organizationId, role: membership.role, patientId: membership.role === 'PACIENTE' ? membership.patientId : undefined, professionalId: membership.role === 'PROFESIONAL' ? membership.professionalId : undefined, extraPermissions: membership.role === 'RECEPCIONISTA' && membership.extraPermissions?.includes('results.upload') ? ['results.upload'] : [] }
    let credential = {}
    if (!existing) { validatePassword(password ?? '', password ?? ''); const passwordSalt = crypto.randomUUID(); credential = { passwordSalt, passwordHash: await hashPassword(password!, passwordSalt) } }
    const memberships = [...(existing ? membershipsFor(existing).filter((m) => m.organizationId !== clean.organizationId) : []), clean]
    const next: User = { ...(existing ?? { id: uid('u'), name: name.trim(), email, role: clean.role, permissions: permissionsFor(clean.role) }), ...credential, memberships, organizationIds: memberships.map((m) => m.organizationId) }
    commit((c) => ({ ...c, users: existing ? c.users.map((u) => u.id === existing.id ? next : u) : [...c.users, next] })); toast.success('Acceso del centro guardado')
  }
  const addResultType = (name: string) => {
    requirePermission('results.types')
    if (!name.trim() || data.resultTypes.some((t) => t.name.toLowerCase() === name.trim().toLowerCase())) throw new Error('Ingresa un nombre de tipo nuevo.')
    commit((c) => ({ ...c, resultTypes: [...c.resultTypes, { id: uid('rt'), organizationId: organization!.id, name: name.trim() }] }))
  }
  const addResult: ClinicContextValue['addResult'] = (input) => {
    requirePermission('results.upload')
    if (!data.patients.some((p) => p.id === input.patientId) || !data.professionals.some((p) => p.id === input.professionalId) || !data.resultTypes.some((t) => t.id === input.typeId) || (user?.role === 'PROFESIONAL' && input.professionalId !== user.professionalId)) throw new Error('Selecciona paciente, responsable y tipo del centro.')
    if (input.appointmentId && !data.appointments.some((a) => a.id === input.appointmentId && a.patientId === input.patientId && a.professionalId === input.professionalId)) throw new Error('La atención no corresponde al paciente y profesional.')
    if (!input.performedAt || input.performedAt > dateFromToday() || !input.filename || !/^data:(application\/pdf|image\/png|image\/jpeg|text\/plain)[;,]/.test(input.content) || input.content.length > 750000) throw new Error('Revisa la fecha y el documento (PDF, PNG, JPEG o TXT; hasta 500 KB).')
    commit((c) => ({ ...c, results: [...c.results, { ...input, id: uid('r'), organizationId: organization!.id, createdBy: user!.id, status: 'DRAFT', publishedAt: undefined }] })); toast.success('Borrador cargado. Pendiente de publicación profesional.')
  }
  const publishResult = (id: string) => {
    requirePermission('results.publish')
    if (!data.results.some((r) => r.id === id && r.professionalId === user?.professionalId && r.status === 'DRAFT')) throw new Error('Solo el profesional responsable puede publicar este borrador.')
    commit((c) => ({ ...c, results: c.results.map((r) => r.id === id ? { ...r, status: 'PUBLISHED', publishedAt: new Date().toISOString() } : r) })); toast.success('Resultado publicado para el paciente')
  }
  const deleteResult = (id: string) => {
    requirePermission('results.publish')
    if (user?.role !== 'PROFESIONAL' || !data.results.some((r) => r.id === id && r.professionalId === user.professionalId)) throw new Error('Solo el profesional responsable puede eliminar este informe.')
    commit((c) => ({ ...c, results: c.results.filter((r) => r.id !== id) })); toast.success('Informe eliminado')
  }
  const value = { data, user, organization, publicOrganizations, register, saveOrganization, saveProfessional, saveCatalog, assignAccess, addResultType, addResult, publishResult, deleteResult, login, logout, reset, slots, createAppointment, changeStatus, reschedule, createPatient, updateProfile }
  return <ClinicContext.Provider value={value}>{children}</ClinicContext.Provider>
}

export function useClinic() { const context = useContext(ClinicContext); if (!context) throw new Error('useClinic debe usarse dentro de ClinicProvider'); return context }
