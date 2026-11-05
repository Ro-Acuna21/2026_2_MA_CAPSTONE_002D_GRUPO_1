import { useCallback, useEffect, useState } from 'react'
import { Navigate } from 'react-router-dom'
import { toast } from 'sonner'
import { useClinic } from '@/state/clinic-store'
import { PageHeading } from '@/components/page-heading'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Field, Input, Select } from '@/components/ui/form-controls'
import { Button } from '@/components/ui/button'
import { firstApiErrorMessage, professionalApi, serviceApi, type BackendProfessional, type BackendService } from '@/services/http'
import { priceApi, reportApi, type ReportSummary, type ServicePrice } from '@/services/results-reports-api'
const clp = new Intl.NumberFormat('es-CL', { style: 'currency', currency: 'CLP', maximumFractionDigits: 0 })
const today = () => new Date().toISOString().slice(0, 10)
const yearStart = () => `${new Date().getFullYear()}-01-01`
function Bars({ title, items }: { title: string; items: { label: string; total: number }[] }) {
  const max = Math.max(1, ...items.map((item) => item.total))
  return <Card><CardHeader><CardTitle>{title}</CardTitle></CardHeader><CardContent className="grid gap-4">{items.length ? items.map((item) => <div key={item.label}><div className="flex justify-between gap-2 text-sm"><span>{item.label}</span><strong>{item.total}</strong></div><div className="mt-2 h-2 rounded bg-muted"><div className="h-2 rounded bg-primary" style={{ width: `${100 * item.total / max}%` }} /></div></div>) : <p className="text-sm text-muted-foreground">Sin datos para este período.</p>}</CardContent></Card>
}
export function ReportsPage({ commercial = false, prices = false }: { commercial?: boolean; prices?: boolean }) {
  const { user } = useClinic()
  const [from, setFrom] = useState(yearStart)
  const [to, setTo] = useState(today)
  const [serviceId, setServiceId] = useState('')
  const [professionalId, setProfessionalId] = useState('')
  const [services, setServices] = useState<BackendService[]>([])
  const [professionals, setProfessionals] = useState<BackendProfessional[]>([])
  const [summary, setSummary] = useState<ReportSummary | null>(null)
  const [history, setHistory] = useState<ServicePrice[]>([])
  const [amount, setAmount] = useState('')
  const [priceServiceId, setPriceServiceId] = useState('')
  const [effectiveFrom, setEffectiveFrom] = useState(today)
  const [effectiveTo, setEffectiveTo] = useState('')
  const [error, setError] = useState('')
  const [busy, setBusy] = useState(false)
  const refresh = useCallback(async () => {
    try {
      setError('')
      const [report, priceList] = await Promise.all([
        reportApi.summary({ from, to, ...(serviceId ? { service_id: Number(serviceId) } : {}), ...(professionalId ? { professional_id: Number(professionalId) } : {}) }),
        prices ? priceApi.list() : Promise.resolve({ data: [] }),
      ])
      setSummary(report.data); setHistory(priceList.data)
    } catch (cause) { setError(firstApiErrorMessage(cause, 'No fue posible cargar los reportes.')); setSummary(null) }
  }, [from, to, serviceId, professionalId, prices])
  useEffect(() => { if (user?.role === 'ADMIN') { void Promise.all([serviceApi.list(), professionalApi.list()]).then(([s, p]) => { setServices(s.data); setProfessionals(p.data) }).catch((cause) => setError(firstApiErrorMessage(cause, 'No fue posible cargar los filtros.'))) } }, [user?.role])
  useEffect(() => { if (user?.role === 'ADMIN' && from && to && from <= to) void refresh() }, [user?.role, from, to, refresh])
  if (user?.role !== 'ADMIN') return <Navigate to="/" replace />
  async function createPrice(event: React.FormEvent) {
    event.preventDefault(); setBusy(true)
    try {
      await priceApi.create({ service_id: Number(priceServiceId), amount_clp: Number(amount), effective_from: effectiveFrom, effective_to: effectiveTo || null })
      await refresh(); setAmount(''); toast.success('Precio guardado.')
    } catch (cause) { toast.error(firstApiErrorMessage(cause, 'No fue posible guardar el precio.')) }
    finally { setBusy(false) }
  }
  const counts = summary?.counts ?? {}
  return <><PageHeading title={prices ? 'Comercial y finanzas' : commercial ? 'Reportes comerciales' : 'Reportes generales'} description="Indicadores del centro calculados desde sus citas y el historial de precios. Los montos son estimaciones, no pagos cobrados." />
    <Card className="mb-5"><CardContent className="grid gap-3 p-5 sm:grid-cols-2 xl:grid-cols-4">
      <Field label="Desde"><Input type="date" value={from} max={to} onChange={(event) => setFrom(event.target.value)} /></Field>
      <Field label="Hasta"><Input type="date" value={to} min={from} onChange={(event) => setTo(event.target.value)} /></Field>
      <Field label="Prestación"><Select value={serviceId} onChange={(event) => setServiceId(event.target.value)}><option value="">Todas</option>{services.map((s) => <option key={s.id} value={s.id}>{s.name}</option>)}</Select></Field>
      <Field label="Profesional"><Select value={professionalId} onChange={(event) => setProfessionalId(event.target.value)}><option value="">Todos</option>{professionals.map((p) => <option key={p.id} value={p.id}>{p.first_name} {p.last_name}</option>)}</Select></Field>
    </CardContent></Card>
    {error && <p role="alert" className="mb-4 text-destructive">{error}</p>}
    {!summary && !error && <p>Cargando reportes…</p>}
    {summary && <><section className="mb-5 grid grid-cols-2 gap-3 lg:grid-cols-5">{[
      ['Total citas', summary.total_appointments], ['Atendidas', counts.ATENDIDA ?? 0], ['Canceladas', counts.CANCELADA ?? 0],
      ['No-show', counts.NO_SHOW ?? 0], ['Ingresos estimados', clp.format(summary.estimated_revenue_clp)],
      ['Pérdida estimada', clp.format(summary.lost_estimate_clp)], ['Ticket estimado', clp.format(summary.average_ticket_clp)],
      ['Sin precio vigente', summary.unpriced_appointments],
    ].map(([label, value]) => <Card key={label}><CardContent className="p-5"><p className="text-xs text-muted-foreground">{label}</p><strong className="mt-2 block text-xl">{value}</strong></CardContent></Card>)}</section>
      <section className="grid gap-5 lg:grid-cols-2"><Bars title="Citas por mes" items={summary.months.map((m) => ({ label: m.month, total: m.bookings }))} /><Bars title="Ingresos estimados por mes (CLP)" items={summary.months.map((m) => ({ label: m.month, total: m.total }))} /><Bars title="Citas por prestación" items={summary.services.map((s) => ({ label: s.label, total: s.bookings }))} /><Bars title="Ingresos estimados por profesional (CLP)" items={summary.professionals.map((p) => ({ label: p.label, total: p.revenue }))} /></section>
    </>}
    {prices && <Card className="mt-6"><CardHeader><CardTitle>Precios por prestación e historial</CardTitle></CardHeader><CardContent><form onSubmit={createPrice} className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
      <Field label="Prestación"><Select required value={priceServiceId} onChange={(e) => setPriceServiceId(e.target.value)}><option value="">Seleccionar</option>{services.map((s) => <option key={s.id} value={s.id}>{s.name}</option>)}</Select></Field>
      <Field label="Precio CLP"><Input required type="number" min="0" value={amount} onChange={(e) => setAmount(e.target.value)} /></Field>
      <Field label="Vigente desde"><Input required type="date" value={effectiveFrom} onChange={(e) => setEffectiveFrom(e.target.value)} /></Field>
      <Field label="Vigente hasta"><Input type="date" min={effectiveFrom} value={effectiveTo} onChange={(e) => setEffectiveTo(e.target.value)} /></Field>
      <Button disabled={busy}>Guardar precio</Button>
    </form><div className="mt-5 grid gap-2">{history.map((price) => <div key={price.id} className="flex flex-wrap items-center justify-between gap-2 rounded border p-3 text-sm"><span>{services.find((s) => s.id === price.service_id)?.name ?? `Prestación ${price.service_id}`} · {clp.format(price.amount_clp)} · {price.effective_from.slice(0, 10)} a {price.effective_to?.slice(0, 10) ?? 'sin cierre'}</span><div className="flex gap-2">{!price.effective_to && price.effective_from.slice(0, 10) <= today() && <Button variant="outline" disabled={busy} onClick={async () => { const date = window.prompt('Último día de vigencia (AAAA-MM-DD)', today()); if (!date) return; try { await priceApi.update(price.id, { effective_to: date }); await refresh() } catch (cause) { toast.error(firstApiErrorMessage(cause, 'No se pudo cerrar la vigencia.')) } }}>Cerrar vigencia</Button>}{price.effective_from.slice(0, 10) > today() && <><Button variant="outline" disabled={busy} onClick={async () => { const value = window.prompt('Nuevo precio CLP', String(price.amount_clp)); if (value === null) return; try { await priceApi.update(price.id, { amount_clp: Number(value) }); await refresh() } catch (cause) { toast.error(firstApiErrorMessage(cause, 'No se pudo editar.')) } }}>Editar futuro</Button><Button variant="destructive" disabled={busy} onClick={async () => { if (!window.confirm('¿Eliminar este precio futuro?')) return; try { await priceApi.remove(price.id); await refresh() } catch (cause) { toast.error(firstApiErrorMessage(cause, 'No se pudo eliminar.')) } }}>Eliminar futuro</Button></>}</div></div>)}</div></CardContent></Card>}
  </>
}
