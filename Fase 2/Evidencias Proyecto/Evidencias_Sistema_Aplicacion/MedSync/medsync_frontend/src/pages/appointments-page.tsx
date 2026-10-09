import { useMemo, useState } from 'react'
import { Link } from 'react-router-dom'
import { AppointmentTable } from '@/components/appointment-table'
import { PageHeading } from '@/components/page-heading'
import { Button } from '@/components/ui/button'
import { Card, CardContent } from '@/components/ui/card'
import { Field, Input, Select } from '@/components/ui/form-controls'
import type { AppointmentStatus } from '@/domain/types'
import { statusNames } from '@/domain/types'
import { useClinic } from '@/state/clinic-store'
import { useCenterPath } from '@/lib/tenant'

export function AppointmentsPage() {
  const { data, user } = useClinic(); const centerPath = useCenterPath(); const [date, setDate] = useState(''); const [professional, setProfessional] = useState(''); const [specialty, setSpecialty] = useState(''); const [status, setStatus] = useState('')
  const appointments = useMemo(() => data.appointments.filter((a) => user?.role === 'PACIENTE' ? a.patientId === user.patientId : user?.role === 'PROFESIONAL' ? a.professionalId === user.professionalId : true).filter((a) => !date || a.date === date).filter((a) => !professional || a.professionalId === professional).filter((a) => !specialty || a.specialtyId === specialty).filter((a) => !status || a.status === status).sort((a, b) => `${b.date}${b.time}`.localeCompare(`${a.date}${a.time}`)), [data.appointments, date, professional, specialty, status, user])
  return <><PageHeading title={user?.role === 'PACIENTE' ? 'Mis citas' : 'Agenda'} description="Consulta y gestiona las reservas desde un solo lugar." action={user?.role !== 'PROFESIONAL' ? <Button asChild><Link to={centerPath('/reservar')}>Nueva cita</Link></Button> : undefined} />
    <Card className="mb-5"><CardContent className="grid gap-3 p-4 sm:grid-cols-2 xl:grid-cols-5"><Field label="Fecha"><Input type="date" value={date} onChange={(e) => setDate(e.target.value)} /></Field>{user?.role !== 'PROFESIONAL' && <Field label="Profesional"><Select value={professional} onChange={(e) => setProfessional(e.target.value)}><option value="">Todos</option>{data.professionals.map((p) => <option key={p.id} value={p.id}>{p.name}</option>)}</Select></Field>}<Field label="Especialidad"><Select value={specialty} onChange={(e) => setSpecialty(e.target.value)}><option value="">Todas</option>{data.specialties.map((s) => <option key={s.id} value={s.id}>{s.name}</option>)}</Select></Field><Field label="Estado"><Select value={status} onChange={(e) => setStatus(e.target.value)}><option value="">Todos</option>{Object.entries(statusNames).map(([key, label]) => <option key={key} value={key}>{label}</option>)}</Select></Field><Button variant="secondary" className="self-end" onClick={() => { setDate(''); setProfessional(''); setSpecialty(''); setStatus('' as AppointmentStatus) }}>Limpiar filtros</Button></CardContent></Card>
    <AppointmentTable appointments={appointments} /></>
}
