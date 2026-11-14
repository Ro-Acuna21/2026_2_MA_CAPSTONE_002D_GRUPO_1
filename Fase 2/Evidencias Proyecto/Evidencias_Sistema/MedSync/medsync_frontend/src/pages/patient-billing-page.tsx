import { useCallback, useEffect, useState, type FormEvent } from 'react'
import { Navigate } from 'react-router-dom'
import { toast } from 'sonner'
import { PageHeading } from '@/components/page-heading'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Field, Input, Select } from '@/components/ui/form-controls'
import { firstApiErrorMessage } from '@/services/http'
import { patientBillingApi, type BillingSummary, type PatientPayment } from '@/services/patient-billing-api'
import { useClinic } from '@/state/clinic-store'

const money = new Intl.NumberFormat('es-CL', { style: 'currency', currency: 'CLP', maximumFractionDigits: 0 })
const methods = { CASH: 'Efectivo', CARD: 'Tarjeta', TRANSFER: 'Transferencia', OTHER: 'Otro' }

export function PatientBillingPage() {
  const { user } = useClinic()
  const [summary, setSummary] = useState<BillingSummary | null>(null)
  const [error, setError] = useState('')
  const [busy, setBusy] = useState(false)
  const [patientId, setPatientId] = useState('')
  const [amount, setAmount] = useState('')
  const [method, setMethod] = useState<PatientPayment['method']>('CASH')
  const [reference, setReference] = useState('')
  const refresh = useCallback(async () => {
    try { setSummary((await patientBillingApi.summary()).data); setError('') }
    catch (failure) { setError(firstApiErrorMessage(failure, 'No fue posible cargar el estado de cobros.')) }
  }, [])
  useEffect(() => { if (user?.role === 'ADMIN' || user?.role === 'RECEPCIONISTA') void refresh() }, [user?.role, refresh])
  if (!user || !['ADMIN', 'RECEPCIONISTA'].includes(user.role)) return <Navigate to="/" replace />

  async function action(task: () => Promise<unknown>, message: string) {
    setBusy(true)
    try { await task(); await refresh(); toast.success(message); return true }
    catch (failure) { setError(firstApiErrorMessage(failure, 'No fue posible guardar el movimiento.')); return false }
    finally { setBusy(false) }
  }
  async function pay(event: FormEvent) {
    event.preventDefault()
    if (!patientId || !amount) return
    if (await action(() => patientBillingApi.pay(Number(patientId), { amount_clp: Number(amount), method, reference: reference.trim() || undefined }), 'Abono registrado.')) {
      setAmount(''); setReference('')
    }
  }
  const selected = summary?.patients.find((patient) => String(patient.id) === patientId)
  return <><PageHeading eyebrow="Centro médico" title="Cobros de pacientes" description="Cargos confirmados, abonos y saldos reales del centro. Las indicaciones pendientes no son deuda." />
    {error && <p role="alert" className="mb-4 text-destructive">{error}</p>}
    {!summary && !error && <p role="status">Cargando cobros…</p>}
    {summary && <>
      <section className="mb-5 grid gap-3 sm:grid-cols-4">{[
        ['Cargos confirmados', money.format(summary.charged_clp)], ['Abonos registrados', money.format(summary.paid_clp)],
        ['Saldo pendiente', money.format(summary.balance_clp)], ['Indicaciones sin cobro', summary.pending_indications],
      ].map(([label, value]) => <Card key={label}><CardContent className="p-5"><p className="text-xs text-muted-foreground">{label}</p><strong className="mt-2 block text-xl">{value}</strong></CardContent></Card>)}</section>
      <Card className="mb-5"><CardHeader><CardTitle>Indicaciones de atención</CardTitle><p className="text-sm text-muted-foreground">Confirma un cargo solo cuando corresponda cobrar la prestación. El precio se guardó al indicarla.</p></CardHeader><CardContent className="space-y-2">{summary.indications.filter((item) => item.status !== 'VOIDED').map((item) => <div key={item.id} className="flex flex-wrap items-center justify-between gap-3 rounded border p-3 text-sm"><div><strong>{item.first_name} {item.last_name}</strong><p>{item.service_name} · {money.format(item.amount_clp)} · {item.status === 'INDICATED' ? 'Sin cobro' : 'Cargo confirmado'}</p></div><div className="flex gap-2">{item.status === 'INDICATED' && <Button size="sm" disabled={busy} onClick={() => void action(() => patientBillingApi.confirm(item.id), 'Cargo confirmado.')}>Confirmar cargo</Button>}<Button size="sm" variant="outline" disabled={busy} onClick={() => { const reason = window.prompt('Motivo de anulación (mínimo 5 caracteres)'); if (reason) void action(() => patientBillingApi.voidIndication(item.id, reason), 'Indicación anulada.') }}>Anular</Button></div></div>)}{summary.indications.filter((item) => item.status !== 'VOIDED').length === 0 && <p className="text-sm text-muted-foreground">Sin indicaciones registradas.</p>}</CardContent></Card>
      <Card className="mb-5"><CardHeader><CardTitle>Saldo por paciente</CardTitle></CardHeader><CardContent className="overflow-x-auto p-0"><table className="w-full text-left text-sm"><thead className="bg-muted/60 text-xs uppercase text-muted-foreground"><tr><th className="px-5 py-3">Paciente</th><th className="px-5 py-3 text-right">Cargos</th><th className="px-5 py-3 text-right">Abonos</th><th className="px-5 py-3 text-right">Debe</th></tr></thead><tbody>{summary.patients.map((patient) => <tr key={patient.id} className="border-t"><td className="px-5 py-3"><strong>{patient.name}</strong><span className="block text-xs text-muted-foreground">{patient.rut}</span></td><td className="px-5 py-3 text-right">{money.format(patient.charged_clp)}</td><td className="px-5 py-3 text-right">{money.format(patient.paid_clp)}</td><td className="px-5 py-3 text-right font-semibold">{money.format(patient.balance_clp)}</td></tr>)}</tbody></table>{summary.patients.length === 0 && <p className="p-5 text-sm text-muted-foreground">Todavía no hay cargos ni abonos.</p>}</CardContent></Card>
      <Card className="mb-5"><CardHeader><CardTitle>Registrar abono</CardTitle></CardHeader><CardContent><form onSubmit={pay} className="grid gap-3 sm:grid-cols-2"><Field label="Paciente"><Select required value={patientId} onChange={(event) => setPatientId(event.target.value)}><option value="">Seleccionar paciente con saldo</option>{summary.patients.filter((patient) => patient.balance_clp > 0).map((patient) => <option key={patient.id} value={patient.id}>{patient.name} · debe {money.format(patient.balance_clp)}</option>)}</Select></Field><Field label="Monto CLP"><Input type="number" min="1" max={selected?.balance_clp} required value={amount} onChange={(event) => setAmount(event.target.value)} /></Field><Field label="Medio de pago"><Select value={method} onChange={(event) => setMethod(event.target.value as PatientPayment['method'])}>{Object.entries(methods).map(([key, label]) => <option value={key} key={key}>{label}</option>)}</Select></Field><Field label="Referencia (opcional)"><Input maxLength={120} value={reference} onChange={(event) => setReference(event.target.value)} /></Field><div className="sm:col-span-2"><Button disabled={busy || !patientId}>Registrar abono</Button></div></form></CardContent></Card>
      {user.role === 'ADMIN' && summary.payments.length > 0 && <Card><CardHeader><CardTitle>Abonos registrados</CardTitle></CardHeader><CardContent className="space-y-2">{summary.payments.map((payment) => <div key={payment.id} className="flex flex-wrap items-center justify-between gap-2 rounded border p-3 text-sm"><span>{summary.patients.find((patient) => patient.id === payment.patient_id)?.name ?? `Paciente ${payment.patient_id}`} · {money.format(payment.amount_clp)} · {methods[payment.method]} · {payment.reference ?? 'Sin referencia'}</span><Button size="sm" variant="outline" disabled={busy} onClick={() => { const reason = window.prompt('Motivo de anulación del abono (mínimo 5 caracteres)'); if (reason) void action(() => patientBillingApi.voidPayment(payment.id, reason), 'Abono anulado.') }}>Anular abono</Button></div>)}</CardContent></Card>}
    </>}
  </>
}
