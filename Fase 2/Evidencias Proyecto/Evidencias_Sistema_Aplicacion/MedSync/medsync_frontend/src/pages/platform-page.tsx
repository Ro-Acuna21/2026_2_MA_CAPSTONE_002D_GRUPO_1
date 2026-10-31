import { Building2, Eye, PauseCircle, PlayCircle } from 'lucide-react'
import { useEffect, useState, type ComponentType } from 'react'
import { Navigate } from 'react-router-dom'
import { toast } from 'sonner'
import { PageHeading } from '@/components/page-heading'
import { ActionForm } from '@/components/action-form'
import { AccessForm } from '@/components/access-form'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Field, Input, Select } from '@/components/ui/form-controls'
import { firstApiErrorMessage } from '@/services/http'
import { platformCenterApi, type CenterInput, type PlatformCenter } from '@/services/platform-center-api'
import { useClinic } from '@/state/clinic-store'

const subscriptionLabels = { TRIAL: 'Prueba', ACTIVE: 'Activa', EXPIRED: 'Vencida', SUSPENDED: 'Suspendida' }
const provisioningLabels = { PENDING: 'Preparando base', READY: 'Base lista', FAILED: 'Error de aprovisionamiento' }

function CenterForm({ center, onSaved }: { center?: PlatformCenter; onSaved: (input: CenterInput) => Promise<void> }) {
  return <ActionForm reset={!center} className="sm:grid-cols-2" label={center ? 'Guardar cambios' : 'Crear centro'} onSave={async (values) => {
    await onSaved({
      name: values.name.trim(), slug: values.slug.trim(), rut: values.rut.trim(),
      email: values.email.trim() || null, phone: values.phone.trim() || null,
      plan: values.plan as CenterInput['plan'],
      subscription_status: values.subscription_status as CenterInput['subscription_status'],
      is_active: values.is_active === 'true',
    })
  }}>
    <Field label="Nombre del centro"><Input name="name" defaultValue={center?.name} required minLength={3} maxLength={150} /></Field>
    <Field label="Dirección del centro en la plataforma"><Input name="slug" defaultValue={center?.slug} pattern="[a-z0-9]+(-[a-z0-9]+)*" required maxLength={150} /></Field>
    <Field label="RUT del centro"><Input name="rut" defaultValue={center?.rut} required placeholder="12.345.678-5" /></Field>
    <Field label="Correo de contacto"><Input name="email" type="email" defaultValue={center?.email ?? ''} /></Field>
    <Field label="Teléfono"><Input name="phone" defaultValue={center?.phone ?? ''} /></Field>
    <Field label="Plan"><Select name="plan" defaultValue={center?.plan ?? 'STARTER'}><option value="STARTER">Básico</option><option value="PRO">Profesional</option></Select></Field>
    <Field label="Estado de suscripción"><Select name="subscription_status" defaultValue={center?.subscription_status ?? 'TRIAL'}>{Object.entries(subscriptionLabels).map(([value, label]) => <option key={value} value={value}>{label}</option>)}</Select></Field>
    <Field label="Acceso del centro"><Select name="is_active" defaultValue={String(center?.is_active ?? true)}><option value="true">Habilitado</option><option value="false">Deshabilitado</option></Select></Field>
  </ActionForm>
}

export function PlatformPage() {
  const { user } = useClinic()
  const [centers, setCenters] = useState<PlatformCenter[]>([])
  const [status, setStatus] = useState<'loading' | 'ready' | 'error'>('loading')
  const [error, setError] = useState('')
  const [reload, setReload] = useState(0)
  const [selectedId, setSelectedId] = useState<number | null>(null)
  const [showCreate, setShowCreate] = useState(false)
  const [busyId, setBusyId] = useState<number | null>(null)

  useEffect(() => {
    if (user?.role !== 'SUPER_ADMIN') return
    let cancelled = false
    platformCenterApi.list().then((response) => {
      if (cancelled) return
      setCenters(response.data)
      setStatus('ready')
      setError('')
    }).catch((failure: unknown) => {
      if (cancelled) return
      setError(firstApiErrorMessage(failure, 'No fue posible cargar los centros.'))
      setStatus('error')
    })
    return () => { cancelled = true }
  }, [user?.role, reload])

  if (user?.role !== 'SUPER_ADMIN') return <Navigate to="/" replace />

  const selected = centers.find((center) => center.id === selectedId)
  const active = centers.filter((center) => center.is_active).length
  const stats: { label: string; value: number; Icon: ComponentType<{ className?: string }> }[] = [
    { label: 'Centros registrados', value: centers.length, Icon: Building2 },
    { label: 'Centros habilitados', value: active, Icon: PlayCircle },
    { label: 'Centros deshabilitados', value: centers.length - active, Icon: PauseCircle },
  ]

  async function save(input: CenterInput, center?: PlatformCenter) {
    const request = center ? platformCenterApi.update(center.id, input) : platformCenterApi.create(input)
    const response = await request.catch((failure: unknown) => {
      setReload((value) => value + 1)
      throw new Error(firstApiErrorMessage(failure, 'No fue posible guardar el centro.'))
    })
    setCenters((current) => [...current.filter((item) => item.id !== response.data.id), response.data].sort((a, b) => a.name.localeCompare(b.name, 'es')))
    setShowCreate(false)
    toast.success(center ? 'Centro actualizado en Core.' : 'Centro creado y base clínica preparada.')
    window.dispatchEvent(new Event('medsync:centers-updated'))
  }

  async function change(center: PlatformCenter, operation: 'toggle' | 'retry') {
    setBusyId(center.id)
    setError('')
    try {
      const response = operation === 'retry'
        ? await platformCenterApi.retry(center.id)
        : await platformCenterApi.update(center.id, { is_active: !center.is_active })
      setCenters((current) => current.map((item) => item.id === center.id ? response.data : item))
      window.dispatchEvent(new Event('medsync:centers-updated'))
      toast.success(operation === 'retry' ? 'Base clínica preparada.' : 'Acceso del centro actualizado.')
    } catch (failure) {
      setError(firstApiErrorMessage(failure, 'No fue posible actualizar el centro.'))
      setReload((value) => value + 1)
    } finally {
      setBusyId(null)
    }
  }

  return <>
    <PageHeading eyebrow="Plataforma MedSync" title="Comercial y suscripciones" description="Centros registrados en Core y estado de su base clínica." action={<Button onClick={() => setShowCreate((value) => !value)}>{showCreate ? 'Cerrar' : 'Crear centro'}</Button>} />
    {status === 'loading' && <p role="status" className="mb-6">Cargando centros desde Core…</p>}
    {status === 'error' && <div className="mb-6"><p role="alert" className="text-rose-700">{error}</p><Button variant="outline" onClick={() => { setStatus('loading'); setReload((value) => value + 1) }}>Reintentar</Button></div>}
    {status === 'ready' && <>
      {error && <p role="alert" className="mb-6 text-rose-700">{error}</p>}
      <section className="mb-6 grid gap-4 sm:grid-cols-3">{stats.map(({ label, value, Icon }) => <Card key={label}><CardContent className="flex items-start justify-between p-5"><div><p className="text-xs text-muted-foreground">{label}</p><strong className="mt-2 block text-2xl">{value}</strong></div><Icon className="size-5 text-primary" /></CardContent></Card>)}</section>
      {showCreate && <Card className="mb-6"><CardHeader><CardTitle>Nuevo centro médico</CardTitle></CardHeader><CardContent><CenterForm onSaved={(input) => save(input)} /></CardContent></Card>}
      <Card className="mb-6"><CardHeader><CardTitle>Centros</CardTitle></CardHeader><CardContent className="overflow-x-auto p-0"><table className="w-full min-w-[800px] text-left text-sm"><thead className="bg-muted/60 text-xs uppercase text-muted-foreground"><tr><th className="px-5 py-3">Centro</th><th className="px-4 py-3">Plan</th><th className="px-4 py-3">Suscripción</th><th className="px-4 py-3">Base clínica</th><th className="px-4 py-3">Acceso</th><th className="px-4 py-3">Acciones</th></tr></thead><tbody>{centers.map((center) => <tr className="border-t" key={center.id}><td className="px-5 py-4"><strong>{center.name}</strong><span className="block text-xs text-muted-foreground">/{center.slug}</span></td><td className="px-4 py-4">{center.plan ?? 'Sin plan'}</td><td className="px-4 py-4">{center.subscription_status ? subscriptionLabels[center.subscription_status] : 'Sin definir'}</td><td className="px-4 py-4">{provisioningLabels[center.provisioning_status]}</td><td className="px-4 py-4">{center.is_active ? 'Habilitado' : 'Deshabilitado'}</td><td className="flex flex-wrap gap-2 px-4 py-4"><Button size="sm" variant="outline" onClick={() => setSelectedId(center.id)}><Eye className="size-4" /> Detalle</Button>{center.provisioning_status === 'FAILED' && <Button size="sm" disabled={busyId === center.id} onClick={() => void change(center, 'retry')}>Reintentar base</Button>}{center.provisioning_status === 'READY' && <Button size="sm" variant="outline" disabled={busyId === center.id} onClick={() => void change(center, 'toggle')}>{center.is_active ? 'Deshabilitar' : 'Habilitar'}</Button>}</td></tr>)}</tbody></table>{centers.length === 0 && <p className="p-5 text-sm text-muted-foreground">Todavía no hay centros registrados.</p>}</CardContent></Card>
      {selected && <Card><CardHeader><CardTitle>Detalle · {selected.name}</CardTitle></CardHeader><CardContent className="grid gap-8 xl:grid-cols-2"><div><h3 className="mb-4 font-semibold">Centro y acceso</h3><CenterForm key={selected.id} center={selected} onSaved={(input) => save(input, selected)} /></div>{selected.provisioning_status === 'READY' && <div><h3 className="mb-4 font-semibold">Administradores del centro</h3><AccessForm centerId={String(selected.id)} platform /></div>}</CardContent></Card>}
    </>}
  </>
}
