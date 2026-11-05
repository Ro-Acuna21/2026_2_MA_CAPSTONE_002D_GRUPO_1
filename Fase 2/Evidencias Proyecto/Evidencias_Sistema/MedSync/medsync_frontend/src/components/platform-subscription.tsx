import { useCallback, useEffect, useState } from 'react'
import { toast } from 'sonner'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Field, Input, Select } from '@/components/ui/form-controls'
import { firstApiErrorMessage } from '@/services/http'
import { planApi, subscriptionApi, type CenterSubscription, type Plan, type Subscription } from '@/services/subscription-api'
const clp = new Intl.NumberFormat('es-CL', { style: 'currency', currency: 'CLP', maximumFractionDigits: 0 })
const today = () => new Date().toISOString().slice(0, 10)
const labels = { TRIAL: 'Prueba', ACTIVE: 'Activa', EXPIRED: 'Vencida', SUSPENDED: 'Suspendida' }
export function PlatformSubscription({ centerId, onChanged }: { centerId: number; onChanged: () => void }) {
  const [data, setData] = useState<CenterSubscription | null>(null)
  const [plans, setPlans] = useState<Plan[]>([])
  const [error, setError] = useState('')
  const [busy, setBusy] = useState(false)
  const [planId, setPlanId] = useState('')
  const [status, setStatus] = useState<Subscription['status']>('TRIAL')
  const [startedAt, setStartedAt] = useState(today)
  const [renewsAt, setRenewsAt] = useState('')
  const [notes, setNotes] = useState('')
  const [paymentStatus, setPaymentStatus] = useState<'PENDING' | 'PAID' | 'OVERDUE'>('PENDING')
  const [amount, setAmount] = useState('')
  const [dueAt, setDueAt] = useState(today)
  const [paidAt, setPaidAt] = useState('')
  const [reference, setReference] = useState('')
  const refresh = useCallback(async () => {
    try {
      const [subscription, catalog] = await Promise.all([subscriptionApi.platformShow(centerId), planApi.list()])
      setData(subscription.data); setPlans(catalog.data); setError('')
    } catch (cause) { setError(firstApiErrorMessage(cause, 'No fue posible cargar la suscripción.')) }
  }, [centerId])
  useEffect(() => { void refresh() }, [refresh])
  async function assign(event: React.FormEvent) {
    event.preventDefault(); setBusy(true)
    try {
      await subscriptionApi.create(centerId, { plan_id: Number(planId), status, started_at: startedAt, renews_at: renewsAt || null, notes: notes || null })
      await refresh(); onChanged(); toast.success('Suscripción guardada.')
    } catch (cause) { toast.error(firstApiErrorMessage(cause, 'No fue posible asignar la suscripción.')) }
    finally { setBusy(false) }
  }
  async function changeStatus(value: Subscription['status']) {
    if (!data?.current) return
    setBusy(true)
    try { await subscriptionApi.update(centerId, data.current.id, { status: value }); await refresh(); onChanged(); toast.success('Estado actualizado.') }
    catch (cause) { toast.error(firstApiErrorMessage(cause, 'No fue posible actualizar el estado.')) }
    finally { setBusy(false) }
  }
  async function recordPayment(event: React.FormEvent) {
    event.preventDefault()
    if (!data?.current) return
    setBusy(true)
    try {
      await subscriptionApi.recordPayment(centerId, data.current.id, {
        amount_clp: Number(amount), status: paymentStatus, due_at: dueAt,
        paid_at: paymentStatus === 'PAID' ? paidAt : null,
        provider_reference: paymentStatus === 'PAID' ? reference : null,
      })
      setAmount(''); setReference(''); await refresh(); toast.success('Registro manual guardado.')
    } catch (cause) { toast.error(firstApiErrorMessage(cause, 'No fue posible registrar el pago.')) }
    finally { setBusy(false) }
  }
  return <Card className="mt-5"><CardHeader><CardTitle>Suscripción y pagos registrados</CardTitle><p className="text-sm text-muted-foreground">Los cobros se realizan fuera de MedSync y se registran manualmente aquí. El sistema no verifica pagos de forma automática.</p></CardHeader><CardContent className="grid gap-6">
    {error && <p role="alert" className="text-destructive">{error} <Button variant="outline" onClick={() => void refresh()}>Reintentar</Button></p>}
    {data?.current ? <div className="rounded border p-4"><p className="font-semibold">{data.current.plan?.name} · {labels[data.current.status]}</p><p className="text-sm">Inicio: {data.current.started_at} · Renovación indicada: {data.current.renews_at ?? 'Sin fecha'} · Precio al contratar: {data.current.monthly_price_clp_snapshot == null ? 'No registrado' : clp.format(data.current.monthly_price_clp_snapshot)}</p><Field label="Cambiar estado registrado"><Select disabled={busy} value={data.current.status} onChange={(e) => void changeStatus(e.target.value as Subscription['status'])}>{Object.entries(labels).map(([key, label]) => <option key={key} value={key}>{label}</option>)}</Select></Field></div> : <p>Este centro aún no tiene suscripción registrada.</p>}
    <form onSubmit={assign} className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4"><h3 className="sm:col-span-2 lg:col-span-4 font-semibold">{data?.current ? 'Cambiar de plan (conserva historial)' : 'Asignar suscripción'}</h3>
      <Field label="Plan"><Select required value={planId} onChange={(e) => setPlanId(e.target.value)}><option value="">Seleccionar plan</option>{plans.filter((p) => p.is_active).map((p) => <option key={p.id} value={p.id}>{p.name} · {clp.format(p.monthly_price_clp)}</option>)}</Select></Field>
      <Field label="Estado inicial"><Select value={status} onChange={(e) => setStatus(e.target.value as Subscription['status'])}>{Object.entries(labels).map(([key, label]) => <option key={key} value={key}>{label}</option>)}</Select></Field>
      <Field label="Inicio"><Input type="date" required value={startedAt} onChange={(e) => setStartedAt(e.target.value)} /></Field>
      <Field label="Próxima renovación indicada"><Input type="date" min={startedAt} value={renewsAt} onChange={(e) => setRenewsAt(e.target.value)} /></Field>
      <Field label="Nota interna"><Input value={notes} onChange={(e) => setNotes(e.target.value)} /></Field>
      <Button disabled={busy || !plans.some((p) => p.is_active)}>Guardar suscripción</Button>
    </form>
    {data?.current && <><form onSubmit={recordPayment} className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4"><h3 className="sm:col-span-2 lg:col-span-4 font-semibold">Añadir registro manual de pago</h3>
      <Field label="Importe CLP"><Input type="number" required min="1" value={amount} onChange={(e) => setAmount(e.target.value)} /></Field>
      <Field label="Estado"><Select value={paymentStatus} onChange={(e) => setPaymentStatus(e.target.value as typeof paymentStatus)}><option value="PENDING">Pendiente</option><option value="PAID">Pago informado</option><option value="OVERDUE">Vencido</option></Select></Field>
      <Field label="Vencimiento"><Input type="date" required value={dueAt} onChange={(e) => setDueAt(e.target.value)} /></Field>
      {paymentStatus === 'PAID' && <><Field label="Fecha de pago informada"><Input type="date" required value={paidAt} onChange={(e) => setPaidAt(e.target.value)} /></Field><Field label="Referencia del comprobante"><Input required value={reference} onChange={(e) => setReference(e.target.value)} /></Field></>}
      <Button disabled={busy}>Registrar</Button>
    </form><div><h3 className="mb-2 font-semibold">Movimientos registrados</h3>{!data.current.payments.length && <p className="text-sm text-muted-foreground">No hay pagos registrados. No se puede afirmar que esté al día.</p>}{data.current.payments.map((payment) => <p key={payment.id} className="border-b py-2 text-sm">{payment.due_at} · {clp.format(payment.amount_clp)} · {payment.status === 'PAID' ? 'Pago informado manualmente' : payment.status === 'OVERDUE' ? 'Vencido' : 'Pendiente'}{payment.paid_at ? ` · Fecha informada: ${payment.paid_at}` : ''}{payment.provider_reference ? ` · Ref.: ${payment.provider_reference}` : ''}</p>)}</div></>}
    {!!data?.history.length && <details><summary>Historial de suscripciones ({data.history.length})</summary><div className="mt-2 grid gap-2">{data.history.map((item) => <p key={item.id} className="border-b py-2 text-sm">{item.plan?.name} · {labels[item.status]} · {item.started_at} a {item.ended_at}</p>)}</div></details>}
  </CardContent></Card>
}
