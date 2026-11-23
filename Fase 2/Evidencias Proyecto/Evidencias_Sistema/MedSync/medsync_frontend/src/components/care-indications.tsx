import { useEffect, useState, type FormEvent } from 'react'
import { toast } from 'sonner'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Field, Select } from '@/components/ui/form-controls'
import { firstApiErrorMessage, serviceApi, type BackendService } from '@/services/http'
import { patientBillingApi, type CareIndication } from '@/services/patient-billing-api'

const money = new Intl.NumberFormat('es-CL', { style: 'currency', currency: 'CLP', maximumFractionDigits: 0 })
const labels = { INDICATED: 'Indicada, sin cobro', CHARGED: 'Cargo confirmado', VOIDED: 'Anulada' }

export function CareIndications({ appointmentId, appointmentSpecialtyId, attended, professional }: { appointmentId: number; appointmentSpecialtyId: number | null; attended: boolean; professional: boolean }) {
  const [indications, setIndications] = useState<CareIndication[]>([])
  const [services, setServices] = useState<BackendService[]>([])
  const [serviceId, setServiceId] = useState('')
  const [error, setError] = useState('')
  const [busy, setBusy] = useState(false)

  useEffect(() => {
    let active = true
    Promise.all([patientBillingApi.appointment(appointmentId), professional ? serviceApi.list() : Promise.resolve({ data: [] })])
      .then(([items, catalog]) => { if (active) { setIndications(items.data); setServices(catalog.data); setError('') } })
      .catch((failure: unknown) => { if (active) setError(firstApiErrorMessage(failure, 'No fue posible cargar las indicaciones.')) })
    return () => { active = false }
  }, [appointmentId, professional])

  async function create(directCharge: boolean) {
    if (!serviceId) return
    setBusy(true)
    try {
      if (directCharge) await patientBillingApi.charge(appointmentId, Number(serviceId))
      else await patientBillingApi.indicate(appointmentId, Number(serviceId))
      setIndications((await patientBillingApi.appointment(appointmentId)).data)
      setServiceId('')
      toast.success(directCharge ? 'Cobro generado para el paciente.' : 'Prestación indicada. Aún no se genera una deuda.')
    } catch (failure) { setError(firstApiErrorMessage(failure, 'No fue posible guardar la indicación.')) }
    finally { setBusy(false) }
  }
  function submit(event: FormEvent) { event.preventDefault(); void create(false) }

  const selected = services.find((service) => String(service.id) === serviceId)
  return <Card className="mt-5"><CardHeader><CardTitle>Prestaciones indicadas</CardTitle>
    <p className="text-sm text-muted-foreground">Puedes generar el cobro directamente o dejar una indicación sin deuda para revisión posterior.</p>
  </CardHeader><CardContent>
    {error && <p role="alert" className="mb-3 text-sm text-destructive">{error}</p>}
    {indications.length ? <ul className="mb-5 space-y-2 text-sm">{indications.map((item) => <li key={item.id} className="flex flex-wrap justify-between gap-2 rounded border p-3"><span>{item.service_name} · {labels[item.status]}</span><span className="flex items-center gap-2"><strong>{money.format(item.amount_clp)}</strong>{professional && attended && item.status === 'INDICATED' && <Button size="sm" type="button" disabled={busy} onClick={async () => {setBusy(true); try {await patientBillingApi.charge(appointmentId, item.service_id); setIndications((await patientBillingApi.appointment(appointmentId)).data); toast.success('Cobro generado.')} catch(e) {setError(firstApiErrorMessage(e, 'No se pudo generar el cobro.'))} finally {setBusy(false)}}}>Generar cobro</Button>}</span></li>)}</ul> : <p className="mb-5 text-sm text-muted-foreground">Todavía no hay prestaciones indicadas.</p>}
    {professional && attended && <form onSubmit={submit} className="grid gap-3 sm:grid-cols-[1fr_auto_auto] sm:items-end"><Field label="Prestación"><Select required value={serviceId} onChange={(event) => setServiceId(event.target.value)}><option value="">Seleccionar prestación</option>{services.filter((service) => service.is_active !== false && service.current_price_clp !== null && service.current_price_clp !== undefined && (service.specialty?.id === appointmentSpecialtyId || (service.category === 'EXAM' && service.is_shared_exam)) && !indications.some((item) => item.service_id === service.id)).map((service) => <option key={service.id} value={service.id}>{service.name} · {money.format(service.current_price_clp ?? 0)}</option>)}</Select></Field><Button type="button" disabled={busy || !serviceId} onClick={() => void create(true)}>{busy ? 'Guardando…' : 'Generar cobro'}</Button><Button variant="outline" disabled={busy || !serviceId}>Solo indicar</Button>{selected && <p className="text-xs text-muted-foreground sm:col-span-3">Valor: {money.format(selected.current_price_clp ?? 0)}. Se conservará el precio vigente al generar el cobro.</p>}</form>}
  </CardContent></Card>
}
