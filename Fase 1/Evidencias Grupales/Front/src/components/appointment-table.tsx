import { CalendarClock, Ellipsis, RotateCcw } from 'lucide-react'
import { Link } from 'react-router-dom'
import type { Appointment, AppointmentStatus, Role } from '@/domain/types'
import { formatDateTime } from '@/lib/utils'
import { useClinic } from '@/state/clinic-store'
import { StatusBadge } from './ui/badge'
import { Button } from './ui/button'
import { Select } from './ui/form-controls'

export function AppointmentTable({ appointments, title = 'Agenda' }: { appointments: Appointment[]; title?: string }) {
  const { data, user, changeStatus } = useClinic()
  const patient = (id: string) => data.patients.find((item) => item.id === id)
  const professional = (id: string) => data.professionals.find((item) => item.id === id)
  const service = (id: string) => data.services.find((item) => item.id === id)
  const canReschedule = (role?: Role) => role !== 'PROFESIONAL'
  const change = (id: string, value: string) => value && changeStatus(id, value as AppointmentStatus)
  return <div className="overflow-hidden rounded-xl border bg-card shadow-soft">
    <div className="flex items-center justify-between border-b px-5 py-4"><div><h2 className="font-semibold">{title}</h2><p className="text-xs text-muted-foreground">{appointments.length} registros visibles</p></div><Ellipsis className="size-5 text-muted-foreground" /></div>
    <div className="overflow-x-auto"><table className="w-full min-w-[850px] text-left text-sm"><thead className="bg-muted/60 text-[11px] uppercase tracking-wider text-muted-foreground"><tr><th className="px-5 py-3">Fecha y hora</th><th className="px-4 py-3">Paciente</th><th className="px-4 py-3">Profesional</th><th className="px-4 py-3">Atención</th><th className="px-4 py-3">Estado</th><th className="px-4 py-3">Acciones</th></tr></thead>
      <tbody>{appointments.map((appointment) => { const closed = ['CANCELADA', 'ATENDIDA'].includes(appointment.status); return <tr key={appointment.id} className="border-t hover:bg-muted/30"><td className="px-5 py-4 font-medium"><span className="flex items-center gap-2"><CalendarClock className="size-4 text-primary" />{formatDateTime(appointment.date, appointment.time)}</span>{appointment.overbook && <small className="mt-1 block text-amber-700">Sobreturno</small>}</td><td className="px-4 py-4">{patient(appointment.patientId)?.name}</td><td className="px-4 py-4">{professional(appointment.professionalId)?.name}</td><td className="px-4 py-4">{service(appointment.serviceId)?.name}</td><td className="px-4 py-4"><StatusBadge status={appointment.status} /></td><td className="px-4 py-4"><div className="flex items-center gap-2">
        <Button asChild variant="ghost" size="sm"><Link to={`/citas/${appointment.id}`}>Historial</Link></Button>
        {!closed && canReschedule(user?.role) && <Button asChild variant="ghost" size="sm"><Link to={`/citas/${appointment.id}/reprogramar`}><RotateCcw className="size-3" /> Reprogramar</Link></Button>}
        {!closed && user?.role === 'PACIENTE' && <Button variant="ghost" size="sm" className="text-destructive" onClick={() => changeStatus(appointment.id, 'CANCELADA')}>Cancelar</Button>}
        {!closed && user?.role === 'PROFESIONAL' && <Button variant="secondary" size="sm" onClick={() => changeStatus(appointment.id, 'ATENDIDA')}>Marcar atendida</Button>}
        {!closed && ['ADMIN', 'RECEPCIONISTA'].includes(user?.role ?? '') && <Select aria-label="Cambiar estado" className="h-8 w-32 text-xs" defaultValue="" onChange={(event) => change(appointment.id, event.target.value)}><option value="" disabled>Cambiar…</option><option value="CONFIRMADA">Confirmar</option><option value="ATENDIDA">Atendida</option><option value="NO_SHOW">No asistió</option><option value="CANCELADA">Cancelar</option></Select>}
      </div></td></tr> })}{appointments.length === 0 && <tr><td colSpan={6} className="p-12 text-center text-muted-foreground">No hay citas para mostrar.</td></tr>}</tbody>
    </table></div>
  </div>
}
