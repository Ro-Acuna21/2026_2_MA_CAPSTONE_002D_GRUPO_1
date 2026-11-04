import { useCallback, useEffect, useState } from 'react'
import { ChevronLeft } from 'lucide-react'
import { Link, Navigate } from 'react-router-dom'
import { toast } from 'sonner'
import { PageHeading } from '@/components/page-heading'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Field, Input } from '@/components/ui/form-controls'
import { firstApiErrorMessage } from '@/services/http'
import { planApi, type Plan } from '@/services/subscription-api'
import { useClinic } from '@/state/clinic-store'
const clp = new Intl.NumberFormat('es-CL', { style: 'currency', currency: 'CLP', maximumFractionDigits: 0 })
export function PlansPage() {
  const { user } = useClinic()
  const [plans, setPlans] = useState<Plan[]>([])
  const [error, setError] = useState('')
  const [loading, setLoading] = useState(true)
  const [busy, setBusy] = useState(false)
  const [editing, setEditing] = useState<Plan | null>(null)
  const [showForm, setShowForm] = useState(false)
  const refresh = useCallback(async () => {
    try { const response = await planApi.list(); setPlans(response.data); setError('') }
    catch (cause) { setError(firstApiErrorMessage(cause, 'No fue posible cargar los planes.')) }
    finally { setLoading(false) }
  }, [])
  useEffect(() => { if (user?.role === 'SUPER_ADMIN') void refresh() }, [user?.role, refresh])
  if (user?.role !== 'SUPER_ADMIN') return <Navigate to="/" replace />
  async function submit(event: React.FormEvent<HTMLFormElement>) {
    event.preventDefault()
    const values = new FormData(event.currentTarget)
    const payload = {
      name: String(values.get('name')).trim(), code: String(values.get('code')).trim().toUpperCase(),
      description: String(values.get('description')).trim() || null,
      monthly_price_clp: Number(values.get('monthly_price_clp')),
      professional_limit: values.get('professional_limit') ? Number(values.get('professional_limit')) : null,
      user_limit: values.get('user_limit') ? Number(values.get('user_limit')) : null,
      features: String(values.get('features')).split('\n').map((s) => s.trim()).filter(Boolean),
      is_active: values.get('is_active') === 'on',
    }
    setBusy(true)
    try {
      if (editing) await planApi.update(editing.id, payload)
      else await planApi.create(payload)
      setShowForm(false); setEditing(null); await refresh(); toast.success('Plan guardado.')
    } catch (cause) { toast.error(firstApiErrorMessage(cause, 'No fue posible guardar el plan.')) }
    finally { setBusy(false) }
  }
  return <>
    <PageHeading eyebrow="Comercial MedSync" title="Planes de suscripción" description="Los cambios de precio afectan nuevas suscripciones; las existentes conservan su importe de alta." action={<Button asChild variant="outline"><Link to="/plataforma"><ChevronLeft className="size-4" /> Volver a suscripciones</Link></Button>} />
    <Button className="mb-5" onClick={() => { setEditing(null); setShowForm(true) }}>Crear plan</Button>
    {error && <p role="alert" className="mb-4 text-destructive">{error} <Button variant="outline" onClick={() => void refresh()}>Reintentar</Button></p>}
    {loading && <p>Cargando planes…</p>}
    {showForm && <Card className="mb-5"><CardHeader><CardTitle>{editing ? 'Editar plan' : 'Nuevo plan'}</CardTitle></CardHeader><CardContent>
      <form key={editing?.id ?? 'new'} onSubmit={submit} className="grid gap-4 sm:grid-cols-2">
        <Field label="Nombre"><Input name="name" required defaultValue={editing?.name ?? ''} /></Field>
        <Field label="Código"><Input name="code" required pattern="[A-Z0-9_]+" defaultValue={editing?.code ?? ''} /></Field>
        <Field label="Precio mensual CLP"><Input name="monthly_price_clp" type="number" required min="0" defaultValue={editing?.monthly_price_clp ?? 0} /></Field>
        <Field label="Límite de profesionales"><Input name="professional_limit" type="number" min="1" defaultValue={editing?.professional_limit ?? ''} /></Field>
        <Field label="Límite de usuarios"><Input name="user_limit" type="number" min="1" defaultValue={editing?.user_limit ?? ''} /></Field>
        <Field label="Descripción"><Input name="description" defaultValue={editing?.description ?? ''} /></Field>
        <Field label="Características (una por línea)"><textarea name="features" className="min-h-28 w-full rounded border p-3" defaultValue={(editing?.features ?? []).join('\n')} /></Field>
        <label className="flex items-center gap-2"><input name="is_active" type="checkbox" defaultChecked={editing?.is_active ?? true} /> Plan activo</label>
        <div className="flex gap-2"><Button disabled={busy}>Guardar</Button><Button type="button" variant="outline" onClick={() => setShowForm(false)}>Cancelar</Button></div>
      </form>
    </CardContent></Card>}
    {!loading && !error && !plans.length && <p>No hay planes. Crea el primero para poder asignar suscripciones.</p>}
    <section className="grid gap-5 lg:grid-cols-3">{plans.map((plan) => <Card key={plan.id}><CardHeader><CardTitle>{plan.name}</CardTitle><p className="text-sm text-muted-foreground">{plan.code} · {plan.is_active ? 'Activo' : 'Inactivo'}</p><p className="text-2xl font-bold">{clp.format(plan.monthly_price_clp)} / mes</p></CardHeader><CardContent><p className="text-sm">{plan.description}</p><p className="mt-3 text-sm">Profesionales: {plan.professional_limit ?? 'No definido'} · Usuarios: {plan.user_limit ?? 'No definido'}</p><ul className="my-4 list-inside list-disc text-sm">{(plan.features ?? []).map((feature) => <li key={feature}>{feature}</li>)}</ul><Button variant="outline" onClick={() => { setEditing(plan); setShowForm(true) }}>Editar</Button></CardContent></Card>)}</section>
  </>
}
