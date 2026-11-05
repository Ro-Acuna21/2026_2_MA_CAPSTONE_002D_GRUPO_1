import { ArrowLeft, CheckCircle2 } from 'lucide-react'
import { Link, Navigate, useNavigate, useParams } from 'react-router-dom'
import { PageHeading } from '@/components/page-heading'
import { Button } from '@/components/ui/button'
import { Card, CardContent } from '@/components/ui/card'
import { Field, Input, Select, Textarea } from '@/components/ui/form-controls'
import { statusNames, type Slot } from '@/domain/types'
import { dateFromToday } from '@/lib/utils'
import { useClinic } from '@/state/clinic-store'
import { appointmentApi, firstApiErrorMessage, type AppointmentHistoryResponse } from '@/services/http'
import { useEffect, useState, type FormEvent } from 'react'
import { toast } from 'sonner'
import { useCenterPath } from '@/lib/tenant'
import { canPatientModifyAppointment } from '@/domain/appointment-rules'

export function AppointmentHistoryPage() {
  const { id } = useParams()
  const { user } = useClinic()
  const centerPath = useCenterPath()
  const [history, setHistory] = useState<AppointmentHistoryResponse['data'] | null>(null)
  const [error, setError] = useState('')
  const [loading, setLoading] = useState(true)
  const [reload, setReload] = useState(0)
  useEffect(() => {
    if (!id || !/^\d+$/.test(id) || !user || !['RECEPCIONISTA', 'PROFESIONAL', 'PACIENTE'].includes(user.role)) return
    let live = true
    appointmentApi.history(Number(id)).then((response) => {
      if (live) { setHistory(response.data); setError('') }
    }).catch((cause) => {
      if (live) setError(firstApiErrorMessage(cause, 'No fue posible cargar el historial.'))
    }).finally(() => { if (live) setLoading(false) })
    return () => { live = false }
  }, [id, user, reload])
  if (!id || !/^\d+$/.test(id) || !user || !['RECEPCIONISTA', 'PROFESIONAL', 'PACIENTE'].includes(user.role)) return <Navigate to={centerPath('/citas')} replace />
  const appointment = history?.appointment
  return <><PageHeading title="Historial de la cita" description={appointment ? `${appointment.appointment_date} · ${appointment.start_time} · ${appointment.professional?.first_name ?? ''} ${appointment.professional?.last_name ?? ''}` : 'Historial registrado de la cita'} action={<Button asChild variant="outline"><Link to={centerPath('/citas')}><ArrowLeft className="size-4" /> Volver</Link></Button>} />
    <Card className="max-w-3xl"><CardContent className="p-6">
      {loading && <p role="status">Cargando historial…</p>}
      {error && <div><p role="alert" className="text-destructive">{error}</p><Button variant="outline" onClick={() => { setLoading(true); setReload((n) => n + 1) }}>Reintentar</Button></div>}
      {!loading && !error && history && (history.events.length ? <ol className="relative border-l border-border pl-7">{history.events.map((event) => <li className="relative pb-8 last:pb-0" key={event.id}><span className="absolute -left-[38px] grid size-5 place-items-center rounded-full bg-primary text-white"><CheckCircle2 className="size-3" /></span><p className="font-semibold">{event.event_type.replaceAll('_', ' ')}</p><p className="mt-1 text-sm text-muted-foreground">{event.previous_status && `${statusNames[event.previous_status]} → `}{event.new_status && statusNames[event.new_status]}{event.old_date && <><br />{event.old_date} → {event.new_date}</>}{event.old_professional_id && event.new_professional_id && <><br />{event.old_professional_name ?? `Profesional #${event.old_professional_id}`} → {event.new_professional_name ?? `Profesional #${event.new_professional_id}`}</>}{event.reason && <><br />Motivo: {event.reason}</>}</p><small className="mt-2 block text-xs text-muted-foreground">{new Date(event.created_at).toLocaleString('es-CL')}</small></li>)}</ol> : <p className="text-sm text-muted-foreground">Esta cita todavía no tiene eventos de historial.</p>)}
    </CardContent></Card>
  </>
}
export function ReschedulePage() {
  const { id } = useParams()
  const { data, user, slots, patientSlots, professionalsForService, reschedule } = useClinic()
  const navigate = useNavigate()
  const centerPath = useCenterPath()
  const appointment = data.appointments.find((a) => a.id === id)
  const [date, setDate] = useState('')
  const [time, setTime] = useState('')
  const [professionalId, setProfessionalId] = useState(() => appointment?.professionalId ?? '')
  const [reassignmentReason, setReassignmentReason] = useState('')
  const [available, setAvailable] = useState<Slot[]>([])
  const [realProfessionals, setRealProfessionals] = useState<{ id: string; name: string }[]>([])
  const mockReplacementProfessionals = appointment
    ? data.professionals.filter((professional) => professional.active && professional.specialtyIds.includes(appointment.specialtyId))
    : []
  const replacementProfessionals = user?.role === 'RECEPCIONISTA' ? realProfessionals : mockReplacementProfessionals
  const isReassignment = user?.role === 'RECEPCIONISTA' && professionalId !== appointment?.professionalId

  useEffect(() => {
    if (!appointment || user?.role !== 'RECEPCIONISTA') {
      setRealProfessionals([])
      return
    }

    let active = true

    professionalsForService(appointment.serviceId).then((professionals) => {
      if (active) setRealProfessionals(professionals)
    })

    return () => {
      active = false
    }
  }, [appointment, professionalsForService, user?.role])

  // El paciente consulta horarios reales contra Laravel; recepción
  // sigue usando el cálculo mock.
  useEffect(() => {
    if (!appointment || !date) {
      setAvailable([])
      return
    }

    if (user?.role === 'PACIENTE' || user?.role === 'RECEPCIONISTA') {
      let active = true

      patientSlots(appointment.serviceId, professionalId, date).then((result) => {
        if (active) setAvailable(result)
      })

      return () => {
        active = false
      }
    }

    setAvailable(slots(professionalId, appointment.serviceId, date, appointment.id))
  }, [appointment, date, professionalId, slots, patientSlots, user])

  const allowed = appointment && user?.role !== 'PROFESIONAL' && (user?.role === 'RECEPCIONISTA' || (appointment.patientId === user?.patientId && canPatientModifyAppointment(appointment)))

  if (!allowed || !appointment) return <Navigate to={centerPath('/citas')} replace />

  const submit = async (event: FormEvent) => {
    event.preventDefault()
    if (!time || !professionalId) return

    try {
      await reschedule(appointment.id, date, time, professionalId, reassignmentReason)
      navigate(centerPath('/citas'))
    } catch (error) {
      toast.error((error as Error).message)
    }
  }

  return <><PageHeading title="Reprogramar cita" description="El horario anterior se conservará en el historial." /><Card className="max-w-2xl"><CardContent className="p-6"><form onSubmit={submit}>{user?.role === 'RECEPCIONISTA' && <><Field label="Profesional responsable"><Select required value={professionalId} onChange={(event) => { setProfessionalId(event.target.value); setTime('') }}><option value="">Seleccionar profesional</option>{replacementProfessionals.map((professional) => <option key={professional.id} value={professional.id}>{professional.name}{professional.id === appointment.professionalId ? ' · profesional original' : ''}</option>)}</Select></Field><p className="mt-2 text-sm text-muted-foreground">Solo se muestran profesionales activos que atienden la especialidad de esta cita.</p>{isReassignment && <Field className="mt-5" label="Motivo de la reasignación"><Textarea required maxLength={300} value={reassignmentReason} onChange={(event) => setReassignmentReason(event.target.value)} placeholder="Ej.: profesional ausente por licencia médica." /></Field>}</>}<Field className="mt-5" label="Nueva fecha"><Input type="date" min={dateFromToday(1)} required value={date} onChange={(e) => { setDate(e.target.value); setTime('') }} /></Field><div className="my-6"><p className="mb-3 text-sm font-medium">Horarios disponibles</p><div className="flex min-h-16 flex-wrap gap-2 rounded-xl bg-muted p-4">{available.map((slot) => <Button type="button" key={slot.time} size="sm" variant={time === slot.time ? 'default' : 'outline'} onClick={() => setTime(slot.time)}>{slot.time}</Button>)}{!date && <p className="text-sm text-muted-foreground">Selecciona una fecha para consultar.</p>}{date && !available.length && <p className="text-sm text-muted-foreground">No hay horas disponibles para este profesional.</p>}</div></div><div className="flex justify-end gap-3"><Button type="button" variant="ghost" onClick={() => navigate(centerPath('/citas'))}>Cancelar</Button><Button disabled={!time || !professionalId}>Guardar reprogramación</Button></div></form></CardContent></Card></>
}
