import { useEffect, useState, type FormEvent } from 'react'
import { Navigate } from 'react-router-dom'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Field, Input, Select } from '@/components/ui/form-controls'
import { PageHeading } from '@/components/page-heading'
import { firstApiErrorMessage } from '@/services/http'
import { platformServicesApi, type PlatformService } from '@/services/platform-services-api'
import { useClinic } from '@/state/clinic-store'
import { businessDateAt } from '@/lib/business-date'

const money = new Intl.NumberFormat('es-CL', {style: 'currency', currency: 'CLP', maximumFractionDigits: 0})
const categories = {CONSULTATION: 'Consulta', EXAM: 'Examen', DIAGNOSIS: 'Diagnóstico'}
const today = businessDateAt

export function PlatformServicesPage() {
  const {user} = useClinic()
  const [items, setItems] = useState<PlatformService[]>([])
  const [error, setError] = useState('')
  const [busy, setBusy] = useState(false)
  async function reload() { try {setItems((await platformServicesApi.platformList()).data); setError('')} catch (e) {setError(firstApiErrorMessage(e, 'No se pudo cargar el catálogo.'))} }
  useEffect(() => {if (user?.role === 'SUPER_ADMIN') void reload()}, [user?.role])
  if (user?.role !== 'SUPER_ADMIN') return <Navigate to="/" replace />
  async function create(event: FormEvent<HTMLFormElement>) {
    event.preventDefault(); setBusy(true)
    const form = event.currentTarget; const data = new FormData(form)
    try { await platformServicesApi.create({name: String(data.get('name')).trim(), category: String(data.get('category')) as PlatformService['category'], duration_minutes: Number(data.get('duration'))}); form.reset(); await reload() }
    catch (e) {setError(firstApiErrorMessage(e, 'No se pudo crear la prestación.'))} finally {setBusy(false)}
  }
  async function addPrice(event: FormEvent<HTMLFormElement>, id: number) {
    event.preventDefault(); setBusy(true)
    const data = new FormData(event.currentTarget)
    try {await platformServicesApi.addPrice(id, {amount_clp: Number(data.get('amount')), effective_from: String(data.get('from')), effective_to: String(data.get('to')) || null}); await reload()}
    catch (e) {setError(firstApiErrorMessage(e, 'No se pudo guardar el precio.'))} finally {setBusy(false)}
  }
  async function update(event: FormEvent<HTMLFormElement>, id: number) {
    event.preventDefault(); setBusy(true)
    const data = new FormData(event.currentTarget)
    try {await platformServicesApi.update(id, {name: String(data.get('name')).trim(), category: String(data.get('category')) as PlatformService['category'], duration_minutes: Number(data.get('duration'))}); await reload()}
    catch(e) {setError(firstApiErrorMessage(e, 'No se pudo actualizar la prestación.'))} finally {setBusy(false)}
  }
  return <><PageHeading title="Prestaciones y precios base" description="Catálogo global de la plataforma. Cada centro puede conservar el precio base o definir el suyo." />
    {error && <p role="alert" className="mb-4 text-destructive">{error}</p>}
    <Card className="mb-5"><CardHeader><CardTitle>Nueva prestación global</CardTitle></CardHeader><CardContent><form onSubmit={create} className="grid gap-3 sm:grid-cols-4"><Field label="Nombre"><Input name="name" required maxLength={120} /></Field><Field label="Tipo"><Select name="category"><option value="CONSULTATION">Consulta</option><option value="EXAM">Examen</option><option value="DIAGNOSIS">Diagnóstico</option></Select></Field><Field label="Duración (min)"><Input name="duration" type="number" min="5" max="480" defaultValue="30" required /></Field><Button disabled={busy}>Crear prestación</Button></form></CardContent></Card>
    <div className="grid gap-3">{items.map(item => <Card key={item.id}><CardContent className="space-y-3 p-5"><div className="flex flex-wrap justify-between gap-3"><div><strong>{item.name}</strong><p className="text-sm text-muted-foreground">{categories[item.category]} · {item.duration_minutes} min · Base vigente: {item.base_price_clp == null ? 'Sin precio' : money.format(item.base_price_clp)}</p></div><Button type="button" variant="outline" disabled={busy} onClick={async () => {try {await platformServicesApi.update(item.id, {is_active: !item.is_active}); await reload()} catch(e) {setError(firstApiErrorMessage(e, 'No se pudo actualizar.'))}}}>{item.is_active ? 'Desactivar' : 'Activar'}</Button></div>
      <details><summary className="cursor-pointer text-sm font-medium">Editar prestación</summary><form onSubmit={event => void update(event, item.id)} className="mt-3 grid gap-3 sm:grid-cols-4"><Field label="Nombre"><Input name="name" defaultValue={item.name} required /></Field><Field label="Tipo"><Select name="category" defaultValue={item.category}><option value="CONSULTATION">Consulta</option><option value="EXAM">Examen</option><option value="DIAGNOSIS">Diagnóstico</option></Select></Field><Field label="Duración (min)"><Input name="duration" type="number" min="5" max="480" defaultValue={item.duration_minutes} required /></Field><Button disabled={busy}>Guardar cambios</Button></form></details>
      <p className="text-xs text-muted-foreground">Los períodos incluyen las fechas de inicio y término. Para iniciar otro precio, primero cierra el vigente hasta el día anterior.</p><form onSubmit={event => void addPrice(event, item.id)} className="grid gap-3 sm:grid-cols-4"><Field label="Precio base CLP"><Input name="amount" type="number" min="0" required /></Field><Field label="Desde (hora de Chile)"><Input name="from" type="date" defaultValue={today()} required /></Field><Field label="Hasta (opcional)"><Input name="to" type="date" /></Field><Button disabled={busy}>Guardar precio</Button></form>
      <div className="text-sm">{item.prices.map(price => <div key={price.id} className="flex flex-wrap justify-between gap-2 border-t py-2"><span>{money.format(price.amount_clp)} · {price.effective_from.slice(0,10)} a {price.effective_to?.slice(0,10) ?? 'vigente'}</span>{!price.effective_to && <Button type="button" size="sm" variant="outline" onClick={async () => {const date = window.prompt('Último día de vigencia (AAAA-MM-DD)', today()); if (date) {try {await platformServicesApi.closePrice(price.id, date); await reload()} catch(e) {setError(firstApiErrorMessage(e, 'No se pudo cerrar el precio.'))}}}}>Cerrar vigencia</Button>}</div>)}</div>
    </CardContent></Card>)}</div></>
}
