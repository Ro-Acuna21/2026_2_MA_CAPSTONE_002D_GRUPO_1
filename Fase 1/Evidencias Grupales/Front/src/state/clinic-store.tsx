/* eslint-disable react-refresh/only-export-components */
import { createContext, useCallback, useContext, useState, type ReactNode } from 'react'
import { toast } from 'sonner'
import { createMockData } from '@/data/mock-data'
import { permissionsFor } from '@/domain/permissions'
import type { AppointmentStatus, BookingInput, ClinicData, Organization, Patient, Role, Slot, User } from '@/domain/types'
import { dateFromToday, toMinutes, toTime, uid } from '@/lib/utils'

const DATA_KEY = 'clinica_horizonte_react_v2'
const SESSION_KEY = 'clinica_horizonte_user'
const ORGANIZATION_KEY = 'clinica_horizonte_organization'
const ACTIVE: AppointmentStatus[] = ['PENDIENTE', 'CONFIRMADA', 'ATENDIDA', 'NO_SHOW']

function loadData() { try { const value = localStorage.getItem(DATA_KEY); return value ? JSON.parse(value) as ClinicData : createMockData() } catch { return createMockData() } }

interface ClinicContextValue {
  data: ClinicData; user: User | null; organization: Organization | null; availableOrganizations: Organization[]
  login(email: string, password: string): boolean; logout(): void; reset(): void; selectOrganization(id: string): boolean
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
  const user = allData.users.find((item) => item.id === userId) ?? null
  const availableOrganizations = allData.organizations.filter((item) => user?.organizationIds.includes(item.id) && item.active)
  const organization = availableOrganizations.find((item) => item.id === organizationId) ?? null
  const appointmentIds = new Set(allData.appointments.filter((item) => item.organizationId === organization?.id).map((item) => item.id))
  const data: ClinicData = {
    organizations: organization ? [organization] : [],
    users: allData.users.filter((item) => item.organizationIds.some((id) => id === organization?.id)),
    patients: allData.patients.filter((item) => item.organizationId === organization?.id),
    professionals: allData.professionals.filter((item) => item.organizationId === organization?.id),
    specialties: allData.specialties.filter((item) => item.organizationId === organization?.id),
    services: allData.services.filter((item) => item.organizationId === organization?.id),
    availability: allData.availability.filter((item) => item.organizationId === organization?.id),
    appointments: allData.appointments.filter((item) => item.organizationId === organization?.id),
    history: allData.history.filter((item) => appointmentIds.has(item.appointmentId)),
  }
  const commit = useCallback((updater: (current: ClinicData) => ClinicData) => setAllData((current) => { const next = updater(current); localStorage.setItem(DATA_KEY, JSON.stringify(next)); return next }), [])

  const login = (email: string, password: string) => {
    const found = allData.users.find((item) => item.email.toLowerCase() === email.toLowerCase())
    if (!found || password !== 'Demo2026!') return false
    sessionStorage.setItem(SESSION_KEY, found.id); setUserId(found.id)
    if (found.organizationIds.length === 1) { sessionStorage.setItem(ORGANIZATION_KEY, found.organizationIds[0]); setOrganizationId(found.organizationIds[0]) }
    else { sessionStorage.removeItem(ORGANIZATION_KEY); setOrganizationId(null) }
    return true
  }
  const logout = () => { sessionStorage.removeItem(SESSION_KEY); sessionStorage.removeItem(ORGANIZATION_KEY); setUserId(null); setOrganizationId(null) }
  const reset = () => { const next = createMockData(); localStorage.setItem(DATA_KEY, JSON.stringify(next)); setAllData(next); sessionStorage.removeItem(ORGANIZATION_KEY); setOrganizationId(null); toast.success('Datos demo restablecidos') }
  const selectOrganization = (id: string) => {
    if (!user?.organizationIds.includes(id)) return false
    sessionStorage.setItem(ORGANIZATION_KEY, id); setOrganizationId(id); return true
  }
  const slots = (professionalId: string, serviceId: string, date: string, excludeId?: string) => {
    const service = data.services.find((item) => item.id === serviceId)
    if (!service || !date) return []
    const day = new Date(`${date}T12:00`).getDay()
    return data.availability.filter((rule) => rule.active && rule.professionalId === professionalId && rule.weekday === day).flatMap((rule) => {
      const result: Slot[] = []
      for (let start = toMinutes(rule.start); start + service.duration <= toMinutes(rule.end); start += service.duration) {
        const time = toTime(start), end = toTime(start + service.duration)
        if (date === dateFromToday() && new Date(`${date}T${time}:00`) <= new Date()) continue
        const conflict = data.appointments.some((a) => a.id !== excludeId && a.professionalId === professionalId && a.date === date && ACTIVE.includes(a.status) && !a.overbook && start < toMinutes(a.end) && toMinutes(a.time) < start + service.duration)
        if (!conflict) result.push({ time, end })
      }
      return result
    })
  }
  const createAppointment = (input: BookingInput) => {
    if (!user || !organization) return
    const service = data.services.find((item) => item.id === input.serviceId)
    const patientId = input.patientId ?? user.patientId
    if (!service || !patientId) return
    commit((current) => {
      const appointment = { ...input, id: uid('a'), organizationId: organization.id, patientId, end: toTime(toMinutes(input.time) + service.duration), status: 'PENDIENTE' as const, source: user.role === 'PACIENTE' ? 'WEB' as const : 'RECEPCION' as const, overbook: !!input.overbook, createdBy: user.id }
      return { ...current, appointments: [...current.appointments, appointment], history: [...current.history, { id: uid('h'), appointmentId: appointment.id, type: 'CREACION', to: 'PENDIENTE', newDate: `${input.date} ${input.time}`, userId: user.id, at: new Date().toISOString() }] }
    })
    toast.success('Reserva creada correctamente')
  }
  const changeStatus = (id: string, status: AppointmentStatus) => {
    if (!user || !organization) return
    commit((current) => { const appointment = current.appointments.find((a) => a.id === id && a.organizationId === organization.id); if (!appointment) return current; return { ...current, appointments: current.appointments.map((a) => a.id === id ? { ...a, status } : a), history: [...current.history, { id: uid('h'), appointmentId: id, type: status === 'CANCELADA' ? 'CANCELACION' : 'CAMBIO_ESTADO', from: appointment.status, to: status, userId: user.id, at: new Date().toISOString() }] } })
    toast.success('Estado de la cita actualizado')
  }
  const reschedule = (id: string, date: string, time: string) => {
    if (!user || !organization) return
    commit((current) => { const appointment = current.appointments.find((a) => a.id === id && a.organizationId === organization.id); const service = current.services.find((s) => s.id === appointment?.serviceId); if (!appointment || !service) return current; return { ...current, appointments: current.appointments.map((a) => a.id === id ? { ...a, date, time, end: toTime(toMinutes(time) + service.duration), status: 'PENDIENTE' } : a), history: [...current.history, { id: uid('h'), appointmentId: id, type: 'REPROGRAMACION', to: 'PENDIENTE', oldDate: `${appointment.date} ${appointment.time}`, newDate: `${date} ${time}`, userId: user.id, at: new Date().toISOString() }] } })
    toast.success('Cita reprogramada')
  }
  const createPatient = (input: Omit<Patient, 'id' | 'active' | 'organizationId'>) => {
    if (!organization) return
    const patient = { ...input, id: uid('c'), organizationId: organization.id, active: true }
    const newUser: User = { id: uid('u'), name: patient.name, email: patient.email, role: 'PACIENTE' as Role, organizationIds: [organization.id], permissions: permissionsFor('PACIENTE'), patientId: patient.id }
    commit((current) => ({ ...current, patients: [...current.patients, patient], users: [...current.users, newUser] })); toast.success('Paciente registrado')
  }
  const updateProfile = (input: Pick<Patient, 'name' | 'email' | 'phone' | 'address'>) => {
    if (!user?.patientId) return
    commit((current) => ({ ...current, patients: current.patients.map((p) => p.id === user.patientId ? { ...p, ...input } : p), users: current.users.map((u) => u.id === user.id ? { ...u, name: input.name, email: input.email } : u) })); toast.success('Datos actualizados')
  }
  const value = { data, user, organization, availableOrganizations, login, logout, reset, selectOrganization, slots, createAppointment, changeStatus, reschedule, createPatient, updateProfile }
  return <ClinicContext.Provider value={value}>{children}</ClinicContext.Provider>
}

export function useClinic() { const context = useContext(ClinicContext); if (!context) throw new Error('useClinic debe usarse dentro de ClinicProvider'); return context }
