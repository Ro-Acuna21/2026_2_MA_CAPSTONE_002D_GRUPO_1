import { useEffect, useState, type FormEvent } from 'react'
import { toast } from 'sonner'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Field, Select } from '@/components/ui/form-controls'
import { firstApiErrorMessage, serviceApi, type BackendService } from '@/services/http'
import { patientBillingApi, type CareIndication } from '@/services/patient-billing-api'

const money = new Intl.NumberFormat('es-CL', { style: 'currency', currency: 'CLP', maximumFractionDigits: 0 })
const labels = { INDICATED: 'Indicada, sin cobro', CHARGED: 'Cargo confirmado', VOIDED: 'Anulada' }

export function CareIndications({ appointmentId, attended, professional }: { appointmentId: number; attended: boolean; professional: boolean }) {
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

  async function submit(event: FormEvent) {
    event.preventDefault()
    if (!serviceId) return
    setBusy(true)
    try {
      await patientBillingApi.indicate(appointmentId, Number(serviceId))
      setIndications((await patientBillingApi.appointment(appointmentId)).data)
      setServiceId('')
      toast.success('Prestación indicada. Aún no se genera una deuda.')
    } catch (failure) { setError(firstApiErrorMessage(failure, 'No fue posible guardar la indicación.')) }
    finally { setBusy(false) }
  }

  const selected = services.find((service) => String(service.id) === serviceId)
  return <Card className="mt-5"><CardHeader><CardTitle>Prestaciones indicadas</CardTitle>
    <p className="text-sm text-muted-foreground">La indicación informa un valor estimado. Solo recepción o administración puede confirmar el cargo cuando corresponda.</p>
  </CardHeader><CardContent>
    {error && <p role="alert" className="mb-3 text-sm text-destructive">{error}</p>}
    {indications.length ? <ul className="mb-5 space-y-2 text-sm">{indications.map((item) => <li key={item.id} className="flex flex-wrap justify-between gap-2 rounded border p-3"><span>{item.service_name} · {labels[item.status]}</span><strong>{money.format(item.amount_clp)}</strong></li>)}</ul> : <p className="mb-5 text-sm text-muted-foreground">Todavía no hay prestaciones indicadas.</p>}
    {professional && attended && <form onSubmit={submit} className="grid gap-3 sm:grid-cols-[1fr_auto] sm:items-end"><Field label="Indicar examen u otra prestación"><Select required value={serviceId} onChange={(event) => setServiceId(event.target.value)}><option value="">Seleccionar prestación</option>{services.filter((service) => service.is_active !== false && service.current_price_clp !== null && service.current_price_clp !== undefined && !indications.some((item) => item.service_id === service.id)).map((service) => <option key={service.id} value={service.id}>{service.name} · {money.format(service.current_price_clp ?? 0)}</option>)}</Select></Field><Button disabled={busy || !serviceId}>{busy ? 'Guardando…' : 'Guardar indicación'}</Button>{selected && <p className="text-xs text-muted-foreground sm:col-span-2">Valor estimado: {money.format(selected.current_price_clp ?? 0)}. Se conservará el precio vigente al indicar.</p>}</form>}
  </CardContent></Card>
}
