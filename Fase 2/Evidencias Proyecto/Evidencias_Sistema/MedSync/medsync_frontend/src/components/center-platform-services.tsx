import { useEffect, useState, type FormEvent } from 'react'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Field, Input, Select } from '@/components/ui/form-controls'
import { firstApiErrorMessage, serviceApi, type BackendService } from '@/services/http'
import { platformServicesApi, type PlatformService } from '@/services/platform-services-api'
import { priceApi, type ServicePrice } from '@/services/results-reports-api'
import { useClinic } from '@/state/clinic-store'
import { businessDateAt } from '@/lib/business-date'
import { linkableLocalServices } from '@/lib/service-linking'

const money = new Intl.NumberFormat('es-CL', {style: 'currency', currency: 'CLP', maximumFractionDigits: 0})
const today = businessDateAt
const categoryLabel = (value: PlatformService['category'] | null | undefined) => value === 'CONSULTATION' ? 'Consulta' : value === 'EXAM' ? 'Examen' : value === 'DIAGNOSIS' ? 'Diagnóstico' : 'Sin categoría'

export function CenterPlatformServices() {
  const {data, reloadCatalog} = useClinic()
  const [items, setItems] = useState<PlatformService[]>([])
  const [local, setLocal] = useState<BackendService[]>([])
  const [overrides, setOverrides] = useState<ServicePrice[]>([])
  const [linkIds, setLinkIds] = useState<Record<number, string>>({})
  const [error, setError] = useState('')
  const [busy, setBusy] = useState(false)
  const [specialty, setSpecialty] = useState('')
  async function reload() {try {const [catalog, services, prices] = await Promise.all([platformServicesApi.centerList(), serviceApi.list(), priceApi.list()]); setItems(catalog.data); setLocal(services.data); setOverrides(prices.data); setError('')} catch(e) {setError(firstApiErrorMessage(e, 'No se pudo cargar el catálogo base.'))}}
  useEffect(() => {void reload()}, [])
  async function adopt(id: number) {
    if (!specialty) {setError('Selecciona una especialidad activa.'); return}
    setBusy(true)
    try {await platformServicesApi.adopt(id, Number(specialty)); await reload(); reloadCatalog()}
    catch(e) {setError(firstApiErrorMessage(e, 'No se pudo habilitar la prestación.'))} finally {setBusy(false)}
  }
  async function link(id: number) {
    const localId = Number(linkIds[id]); if (!localId) return
    setBusy(true)
    try {await serviceApi.linkPlatform(localId, id); await reload(); reloadCatalog()}
    catch(e) {setError(firstApiErrorMessage(e, 'No se pudo vincular la prestación existente.'))} finally {setBusy(false)}
  }
  async function override(event: FormEvent<HTMLFormElement>, serviceId: number) {
    event.preventDefault(); setBusy(true)
    const form = event.currentTarget; const data = new FormData(form)
    try {await priceApi.create({service_id: serviceId, amount_clp: Number(data.get('amount')), effective_from: String(data.get('from')), effective_to: String(data.get('to')) || null}); form.reset(); await reload()}
    catch(e) {setError(firstApiErrorMessage(e, 'No se pudo guardar el precio del centro. Cierra antes la vigencia anterior.'))} finally {setBusy(false)}
  }
  async function returnToBase(priceId: number) {
    setBusy(true)
    try {await priceApi.update(priceId, {effective_to: today()}); await reload()}
    catch(e) {setError(firstApiErrorMessage(e, 'No se pudo cerrar el precio propio.'))} finally {setBusy(false)}
  }
  return <Card><CardHeader><CardTitle>Catálogo y precios base de MedSync</CardTitle><p className="text-sm text-muted-foreground">“Habilitar en centro” crea una prestación local desde el catálogo. “Vincular” asocia una prestación local existente de la misma categoría. Nombre, duración y categoría se administran en el centro; si no hay precio propio, se usa el precio base vigente del catálogo. Los cobros guardados conservan su monto y origen.</p></CardHeader><CardContent className="space-y-4">
    {error && <p role="alert" className="text-destructive">{error}</p>}
    <Field label="Especialidad para habilitar prestaciones"><Select value={specialty} onChange={e => setSpecialty(e.target.value)}><option value="">Seleccionar</option>{data.specialties.filter(s => s.active).map(s => <option key={s.id} value={s.id}>{s.name}</option>)}</Select></Field>
    {items.map(item => {
      const linked = local.find(service => service.id === item.center_service_id)
      const eligible = linkableLocalServices(local, item.category)
      const unclassified = local.filter(service => !service.platform_service_id && !service.category)
      return (<div key={item.id} className="rounded border p-4"><div className="flex flex-wrap justify-between gap-3"><div><strong>{item.name}</strong><p className="text-sm">{categoryLabel(item.category)} · Precio base: {item.base_price_clp == null ? 'Sin precio' : money.format(item.base_price_clp)} · Precio en este centro: {item.effective_price_clp == null ? 'Sin precio' : money.format(item.effective_price_clp)}</p></div>{!item.center_service_id && <Button type="button" disabled={busy || !specialty} onClick={() => void adopt(item.id)}>Habilitar en centro</Button>}</div>
      {!item.center_service_id && <div className="mt-3 flex flex-wrap items-end gap-2"><Field label="Vincular prestación local equivalente"><Select value={linkIds[item.id] ?? ''} onChange={e => setLinkIds(current => ({...current, [item.id]: e.target.value}))}><option value="">Seleccionar prestación local</option>{eligible.map(service => <option key={service.id} value={service.id}>{service.name} · {categoryLabel(service.category)}</option>)}</Select></Field><Button type="button" variant="outline" disabled={busy || !linkIds[item.id]} onClick={() => void link(item.id)}>Vincular</Button></div>}
      {!item.center_service_id && eligible.length === 0 && <p className="mt-2 text-xs text-muted-foreground">No hay prestaciones locales disponibles de categoría {categoryLabel(item.category)}. Puedes habilitar una nueva desde el catálogo o clasificar una prestación local antes de vincularla.{unclassified.length > 0 ? ` Hay ${unclassified.length} prestación(es) local(es) sin categoría.` : ''} Las prestaciones ya vinculadas no aparecen en esta lista.</p>}
      {item.center_service_id && <><p className="mt-2 text-xs text-muted-foreground">Vinculada a “{linked?.name ?? `Prestación local #${item.center_service_id}`}” ({categoryLabel(linked?.category)}). El nombre, duración y categoría se administran en este centro; el precio base proviene del catálogo global y un precio propio tiene prioridad. Los cargos ya registrados conservan el monto y origen usados.</p>{linked?.category !== item.category && <p role="alert" className="mt-2 text-sm text-destructive">La categoría local no coincide con el catálogo global. No se aplicará el precio base hasta corregir la categoría.</p>}{overrides.filter(price => price.service_id === item.center_service_id && price.effective_from.slice(0,10) <= today() && (!price.effective_to || price.effective_to.slice(0,10) >= today()) && !price.effective_to).map(price => <Button key={price.id} type="button" variant="outline" className="mt-2" disabled={busy} onClick={() => void returnToBase(price.id)}>Volver al precio base desde mañana</Button>)}<form onSubmit={e => void override(e, item.center_service_id!)} className="mt-3 grid gap-3 sm:grid-cols-4"><Field label="Precio propio CLP"><Input name="amount" type="number" min="0" required /></Field><Field label="Desde"><Input name="from" type="date" defaultValue={today()} required /></Field><Field label="Hasta"><Input name="to" type="date" /></Field><Button disabled={busy}>Guardar precio propio</Button></form></>}
    </div>);
    })}
  </CardContent></Card>
}
