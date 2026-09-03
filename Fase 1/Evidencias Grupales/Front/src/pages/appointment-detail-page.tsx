import { ArrowLeft, CheckCircle2 } from 'lucide-react'
import { Link, Navigate, useNavigate, useParams } from 'react-router-dom'
import { PageHeading } from '@/components/page-heading'
import { Button } from '@/components/ui/button'
import { Card, CardContent } from '@/components/ui/card'
import { Field, Input } from '@/components/ui/form-controls'
import { statusNames } from '@/domain/types'
import { dateFromToday, formatDateTime } from '@/lib/utils'
import { useClinic } from '@/state/clinic-store'
import { useMemo, useState, type FormEvent } from 'react'

export function AppointmentHistoryPage() {
  const { id } = useParams(); const { data, user } = useClinic(); const appointment = data.appointments.find((a) => a.id === id)
  const allowed = appointment && (['ADMIN', 'RECEPCIONISTA'].includes(user?.role ?? '') || appointment.patientId === user?.patientId || appointment.professionalId === user?.professionalId)
  if (!allowed || !appointment) return <Navigate to="/citas" replace />
  const history = data.history.filter((item) => item.appointmentId === appointment.id).sort((a, b) => b.at.localeCompare(a.at))
  return <><PageHeading title="Historial de la cita" description={`${formatDateTime(appointment.date, appointment.time)} · ${data.professionals.find((p) => p.id === appointment.professionalId)?.name}`} action={<Button asChild variant="outline"><Link to="/citas"><ArrowLeft className="size-4" /> Volver</Link></Button>} /><Card className="max-w-3xl"><CardContent className="p-6"><ol className="relative border-l border-border pl-7">{history.map((entry) => <li className="relative pb-8 last:pb-0" key={entry.id}><span className="absolute -left-[38px] grid size-5 place-items-center rounded-full bg-primary text-white"><CheckCircle2 className="size-3" /></span><p className="font-semibold">{entry.type.replaceAll('_', ' ')}</p><p className="mt-1 text-sm text-muted-foreground">{entry.from && `${statusNames[entry.from]} → `}{entry.to && statusNames[entry.to]}{entry.oldDate && <><br />{entry.oldDate} → {entry.newDate}</>}</p><small className="mt-2 block text-xs text-muted-foreground">{new Intl.DateTimeFormat('es-CL', { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(entry.at))} · {data.users.find((u) => u.id === entry.userId)?.email}</small></li>)}</ol></CardContent></Card></>
}

export function ReschedulePage() {
  const { id } = useParams(); const { data, user, slots, reschedule } = useClinic(); const navigate = useNavigate(); const appointment = data.appointments.find((a) => a.id === id); const [date, setDate] = useState(''); const [time, setTime] = useState('')
  const available = useMemo(() => appointment ? slots(appointment.professionalId, appointment.serviceId, date, appointment.id) : [], [appointment, date, slots])
  const allowed = appointment && user?.role !== 'PROFESIONAL' && (['ADMIN', 'RECEPCIONISTA'].includes(user?.role ?? '') || appointment.patientId === user?.patientId)
  if (!allowed || !appointment) return <Navigate to="/citas" replace />
  const submit = (event: FormEvent) => { event.preventDefault(); if (!time) return; reschedule(appointment.id, date, time); navigate('/citas') }
  return <><PageHeading title="Reprogramar cita" description="El horario anterior se conservará en el historial." /><Card className="max-w-2xl"><CardContent className="p-6"><form onSubmit={submit}><Field label="Nueva fecha"><Input type="date" min={dateFromToday(1)} required value={date} onChange={(e) => { setDate(e.target.value); setTime('') }} /></Field><div className="my-6"><p className="mb-3 text-sm font-medium">Horarios disponibles</p><div className="flex min-h-16 flex-wrap gap-2 rounded-xl bg-muted p-4">{available.map((slot) => <Button type="button" key={slot.time} size="sm" variant={time === slot.time ? 'default' : 'outline'} onClick={() => setTime(slot.time)}>{slot.time}</Button>)}{!date && <p className="text-sm text-muted-foreground">Selecciona una fecha.</p>}{date && !available.length && <p className="text-sm text-muted-foreground">No hay horas disponibles.</p>}</div></div><div className="flex justify-end gap-3"><Button type="button" variant="ghost" onClick={() => navigate('/citas')}>Cancelar</Button><Button disabled={!time}>Guardar reprogramación</Button></div></form></CardContent></Card></>
}
