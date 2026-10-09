import { Check, ChevronLeft, ChevronRight } from 'lucide-react'
import { useEffect, useState, type FormEvent } from 'react'
import { Navigate, useNavigate } from 'react-router-dom'
import { PageHeading } from '@/components/page-heading'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Field, Input, Select } from '@/components/ui/form-controls'
import { dateFromToday } from '@/lib/utils'
import { useClinic } from '@/state/clinic-store'
import { toast } from 'sonner'
import { useCenterPath } from '@/lib/tenant'
import type { Slot } from '@/domain/types'
import { appointmentApi, type BackendPatientSummary, type BackendService } from '@/services/http'

export function BookingPage() {
  const { user, patientSlots, professionalsForService, createAppointment } = useClinic()
  const navigate = useNavigate()
  const centerPath = useCenterPath()

  // Ambos flujos de reserva usan el catálogo y la disponibilidad reales.
  // Recepción agrega el paciente existente que recibe la atención.
  const staff = user?.role === 'RECEPCIONISTA'

  const [step, setStep] = useState(1)
  const [patientId, setPatientId] = useState('')
  const [specialtyId, setSpecialtyId] = useState('')
  const [professionalId, setProfessionalId] = useState('')
  const [serviceId, setServiceId] = useState('')
  const [date, setDate] = useState('')
  const [time, setTime] = useState('')
  const [catalog, setCatalog] = useState<BackendService[]>([])
  const [patients, setPatients] = useState<BackendPatientSummary[]>([])
  const [realProfessionals, setRealProfessionals] = useState<{ id: string; name: string }[]>([])
  const [available, setAvailable] = useState<Slot[]>([])

  const specialties = Array.from(
    new Map(catalog.filter((service) => service.specialty).map((service) => [String(service.specialty!.id), service.specialty!])).values(),
  )
  const services = catalog.filter((service) => String(service.specialty?.id) === specialtyId)
  const professionals = realProfessionals

  useEffect(() => {
    let active = true

    appointmentApi.services().then((response) => {
      if (active) setCatalog(response.data)
    }).catch(() => {
      if (active) toast.error('No fue posible cargar el catálogo de reservas.')
    })

    if (staff) {
      appointmentApi.patients().then((response) => {
        if (active) setPatients(response.data)
      }).catch(() => {
        if (active) toast.error('No fue posible cargar los pacientes del centro.')
      })
    } else {
      setPatients([])
    }

    return () => { active = false }
  }, [staff])

  // Profesionales reales que atienden la prestación seleccionada.
  useEffect(() => {
    if (!serviceId) {
      setRealProfessionals([])
      return
    }

    let active = true

    professionalsForService(serviceId).then((result) => {
      if (active) setRealProfessionals(result)
    })

    return () => {
      active = false
    }
  }, [serviceId, professionalsForService])

  // Los horarios siempre provienen de Laravel para evitar solapamientos.
  useEffect(() => {
    if (!professionalId || !serviceId || !date) {
      setAvailable([])
      return
    }

    let active = true

    patientSlots(serviceId, professionalId, date).then((result) => {
      if (active) setAvailable(result)
    })

    return () => {
      active = false
    }
  }, [professionalId, serviceId, date, patientSlots])

  if (!['RECEPCIONISTA', 'PACIENTE'].includes(user?.role ?? '')) return <Navigate to="/" replace />

  const next = () => {
    if (step === 1 && (!specialtyId || !professionalId || !serviceId || (staff && !patientId))) return
    if (step === 2 && (!date || !time)) return
    setStep(Math.min(3, step + 1))
  }

  const submit = async (event: FormEvent) => {
    event.preventDefault()

    try {
      await createAppointment({
        patientId: staff ? patientId : undefined,
        specialtyId,
        professionalId,
        serviceId,
        date,
        time,
      })

      navigate(centerPath('/citas'))
    } catch (error) {
      toast.error((error as Error).message)
    }
  }

  const selectedProfessionalName = realProfessionals.find((p) => p.id === professionalId)?.name
  const selectedPatient = patients.find((patient) => String(patient.id) === patientId)

  const selection = `${specialties.find((s) => String(s.id) === specialtyId)?.name} · ${selectedProfessionalName}`

  return (
    <>
      <PageHeading title="Reservar hora" description="Completa los datos en tres pasos." />

      <div className="mx-auto mb-8 flex max-w-2xl items-center">
        {['Atención', 'Horario', 'Confirmación'].map((label, index) => (
          <div key={label} className="contents">
            <div className="grid justify-items-center gap-2">
              <span
                className={`grid size-9 place-items-center rounded-full border-2 text-sm font-bold ${
                  step > index ? 'border-primary bg-primary text-white' : 'border-border bg-background text-muted-foreground'
                }`}
              >
                {step > index + 1 ? <Check className="size-4" /> : index + 1}
              </span>
              <small className="hidden text-xs font-medium sm:block">{label}</small>
            </div>
            {index < 2 && <span className={`mb-5 h-0.5 flex-1 ${step > index + 1 ? 'bg-primary' : 'bg-border'}`} />}
          </div>
        ))}
      </div>

      <form onSubmit={submit}>
        <Card className="mx-auto max-w-3xl">
          <CardHeader>
            <CardTitle>
              {step === 1 ? 'Selecciona la atención' : step === 2 ? 'Elige fecha y horario' : 'Revisa tu reserva'}
            </CardTitle>
            <CardDescription>
              {step === 1
                ? 'La disponibilidad depende de la prestación y profesional.'
                : step === 2
                  ? selection
                  : 'Confirma que los datos sean correctos.'}
            </CardDescription>
          </CardHeader>
          <CardContent>
            {step === 1 && (
              <div className="grid gap-5 sm:grid-cols-2">
                {staff && (
                  <Field className="sm:col-span-2" label="Paciente">
                    <Select required value={patientId} onChange={(e) => setPatientId(e.target.value)}>
                      <option value="">Seleccionar paciente</option>
                      {patients.map((p) => (
                          <option key={p.id} value={p.id}>
                            {p.rut} · {p.first_name} {p.last_name}
                          </option>
                        ))}
                    </Select>
                  </Field>
                )}
                <Field label="Especialidad">
                  <Select
                    required
                    value={specialtyId}
                    onChange={(e) => {
                      setSpecialtyId(e.target.value)
                      setProfessionalId('')
                      setServiceId('')
                    }}
                  >
                    <option value="">Seleccionar</option>
                    {specialties.map((s) => (
                        <option key={s.id} value={s.id}>
                          {s.name}
                        </option>
                      ))}
                  </Select>
                </Field>
                <Field label="Prestación">
                  <Select
                    required
                    disabled={!specialtyId}
                    value={serviceId}
                    onChange={(e) => {
                      setServiceId(e.target.value)
                      setProfessionalId('')
                    }}
                  >
                    <option value="">Seleccionar</option>
                    {services.map((s) => (
                      <option key={s.id} value={s.id}>
                        {s.name} · {s.duration_minutes} min
                      </option>
                    ))}
                  </Select>
                </Field>
                <Field className="sm:col-span-2" label="Profesional">
                  <Select
                    required
                    disabled={!serviceId}
                    value={professionalId}
                    onChange={(e) => setProfessionalId(e.target.value)}
                  >
                    <option value="">Seleccionar</option>
                    {professionals.map((p) => (
                      <option key={p.id} value={p.id}>
                        {p.name}
                      </option>
                    ))}
                  </Select>
                </Field>
              </div>
            )}

            {step === 2 && (
              <div className="grid gap-6">
                <Field label="Fecha">
                  <Input
                    type="date"
                    min={dateFromToday(1)}
                    value={date}
                    onChange={(e) => {
                      setDate(e.target.value)
                      setTime('')
                    }}
                    required
                  />
                </Field>
                <div>
                  <p className="mb-3 text-sm font-medium">Horarios disponibles</p>
                  <div className="flex min-h-16 flex-wrap gap-2 rounded-xl bg-muted/55 p-4">
                    {date &&
                      available.map((slot) => (
                        <Button
                          type="button"
                          key={slot.time}
                          variant={time === slot.time ? 'default' : 'outline'}
                          size="sm"
                          onClick={() => setTime(slot.time)}
                        >
                          {slot.time}
                        </Button>
                      ))}
                    {!date && <p className="text-sm text-muted-foreground">Selecciona una fecha para consultar.</p>}
                    {date && available.length === 0 && (
                      <p className="text-sm text-muted-foreground">No hay horas disponibles para este día.</p>
                    )}
                  </div>
                </div>
              </div>
            )}

            {step === 3 && (
              <dl className="grid gap-5 rounded-xl bg-muted/50 p-5 sm:grid-cols-2">
                <div>
                  <dt className="text-xs text-muted-foreground">Paciente</dt>
                  <dd className="font-medium">
                    {staff && selectedPatient
                      ? `${selectedPatient.first_name} ${selectedPatient.last_name}`
                      : user?.name}
                  </dd>
                </div>
                <div>
                  <dt className="text-xs text-muted-foreground">Atención</dt>
                  <dd className="font-medium">{catalog.find((s) => String(s.id) === serviceId)?.name}</dd>
                </div>
                <div>
                  <dt className="text-xs text-muted-foreground">Profesional</dt>
                  <dd className="font-medium">{selectedProfessionalName}</dd>
                </div>
                <div>
                  <dt className="text-xs text-muted-foreground">Fecha y hora</dt>
                  <dd className="font-medium">
                    {date} · {time}
                  </dd>
                </div>
              </dl>
            )}

            <div className="mt-7 flex justify-between">
              <Button type="button" variant="ghost" disabled={step === 1} onClick={() => setStep(step - 1)}>
                <ChevronLeft className="size-4" /> Volver
              </Button>
              {step < 3 ? (
                <Button type="button" onClick={next}>
                  Continuar <ChevronRight className="size-4" />
                </Button>
              ) : (
                <Button type="submit">Confirmar reserva</Button>
              )}
            </div>
          </CardContent>
        </Card>
      </form>
    </>
  )
}
