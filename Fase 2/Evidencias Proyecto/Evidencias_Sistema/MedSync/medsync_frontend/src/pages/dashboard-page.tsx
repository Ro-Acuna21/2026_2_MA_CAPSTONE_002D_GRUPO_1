import { CalendarCheck2, CalendarClock, CheckCircle2, CircleX } from 'lucide-react'
import { Link } from 'react-router-dom'
import { AppointmentTable } from '@/components/appointment-table'
import { PageHeading } from '@/components/page-heading'
import { Button } from '@/components/ui/button'
import { Card, CardContent } from '@/components/ui/card'
import { dateFromToday } from '@/lib/utils'
import { useClinic } from '@/state/clinic-store'
import { useCenterPath } from '@/lib/tenant'

export function DashboardPage() {
  const { data, user } = useClinic(); const centerPath = useCenterPath(); if (!user) return null
  const appointments = data.appointments.filter((a) => user.role === 'PACIENTE' ? a.patientId === user.patientId : user.role === 'PROFESIONAL' ? a.professionalId === user.professionalId : true)
  const upcoming = appointments.filter((a) => `${a.date}${a.time}` >= `${dateFromToday()}00:00` && a.status !== 'CANCELADA').sort((a, b) => `${a.date}${a.time}`.localeCompare(`${b.date}${b.time}`))
  const stats = [
    { label: 'Citas de hoy', value: appointments.filter((a) => a.date === dateFromToday()).length, detail: 'Agenda del día', icon: CalendarCheck2, className: 'bg-emerald-50 text-emerald-800' },
    { label: 'Próximas', value: upcoming.length, detail: 'Reservas activas', icon: CalendarClock, className: 'bg-blue-50 text-blue-800' },
    { label: 'Atendidas', value: appointments.filter((a) => a.status === 'ATENDIDA').length, detail: 'Registro administrativo', icon: CheckCircle2, className: 'bg-teal-50 text-teal-800' },
    { label: 'No asistió', value: appointments.filter((a) => a.status === 'NO_SHOW').length, detail: 'Seguimiento', icon: CircleX, className: 'bg-rose-50 text-rose-800' },
  ]
  return <><PageHeading title="Inicio" action={user.role !== 'PROFESIONAL' ? <Button asChild><Link to={centerPath('/reservar')}>Reservar una hora</Link></Button> : undefined} />
    <section className="mb-6 overflow-hidden rounded-2xl border bg-[linear-gradient(110deg,#fff,#dcf2eb)] px-6 py-7 sm:px-8"><p className="text-xs font-bold uppercase tracking-widest text-primary">Resumen de actividad</p><h2 className="mt-2 text-2xl font-bold">Hola, {user.name.split(' ')[0]}</h2><p className="mt-1 text-sm text-muted-foreground">Aquí tienes una vista rápida de la agenda disponible para tu rol.</p></section>
    <section className="mb-6 grid grid-cols-2 gap-3 md:grid-cols-3 xl:grid-cols-6">{stats.map(({ label, value, detail, icon: Icon, className }) => <Card key={label} className={className}><CardContent className="p-5"><div className="flex items-center justify-between"><p className="text-xs font-medium opacity-75">{label}</p><Icon className="size-4 opacity-60" /></div><strong className="my-2 block text-3xl">{value}</strong><p className="text-[11px] opacity-65">{detail}</p></CardContent></Card>)}</section>
    <AppointmentTable appointments={upcoming.slice(0, 8)} title="Próximas citas" /></>
}
